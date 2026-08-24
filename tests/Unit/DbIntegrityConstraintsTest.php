<?php

namespace Tests\Unit;

use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DbIntegrityConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * OCCValidatorTest::seedData() pattern; proposer is a distinct user from the
     * lecturer so deleting the proposer only exercises the still-CASCADE proposer_id FK.
     *
     * @return array<string, int>
     */
    private function seedFixture(): array
    {
        $semesterId = (int) DB::table('semesters')->insertGetId([
            'semester_code' => 'T'.mt_rand(100, 999),
            'label' => 'Integrity Semester',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'week_count' => 14,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $venueId = (int) DB::table('venues')->insertGetId([
            'room_code' => 'V'.mt_rand(100, 999),
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $moduleId = (int) DB::table('modules')->insertGetId([
            'module_code' => 'MOD'.mt_rand(100, 999),
            'module_name' => 'Integrity Module',
            'allowed_session_types' => 'L,T,P',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lecturerId = User::factory()->lecturer()->create()->id;
        $proposerId = User::factory()->student()->create()->id;

        $sessionId = (int) DB::table('class_sessions')->insertGetId([
            'semester_id' => $semesterId,
            'module_id' => $moduleId,
            'lecturer_id' => $lecturerId,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $venueId,
            'session_type' => 'L',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slot = TimeSlot::factory()->create([
            'semester_id' => $semesterId,
            'class_session_id' => null,
            'venue_id' => $venueId,
            'status' => 'available',
            'version' => 1,
        ]);

        $request = ReplacementRequest::factory()->create([
            'semester_id' => $semesterId,
            'proposer_id' => $proposerId,
            'class_session_id' => $sessionId,
            'replacement_time_slot_id' => $slot->id,
            'week_number' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return [
            'lecturerId' => $lecturerId,
            'proposerId' => $proposerId,
            'sessionId' => $sessionId,
            'slotId' => (int) $slot->id,
            'requestId' => $request->id,
        ];
    }

    public function test_d1_unique_guard_blocks_second_active_request_for_same_block(): void
    {
        ['requestId' => $requestId] = $this->seedFixture();
        $request = DB::table('replacement_requests')->where('id', $requestId)->first();

        $duplicate = [
            'semester_id' => $request->semester_id,
            'proposer_id' => $request->proposer_id,
            'class_session_id' => $request->class_session_id,
            'week_number' => $request->week_number,
            'replacement_time_slot_id' => $request->replacement_time_slot_id,
            'status' => $request->status,
            'submitted_at' => $request->submitted_at,
        ];

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('replacement_requests')->insert($duplicate);
    }

    public function test_restrict_fk_blocks_deleting_referenced_time_slot_and_class_session(): void
    {
        ['sessionId' => $sessionId, 'slotId' => $slotId, 'requestId' => $requestId] = $this->seedFixture();

        try {
            DB::transaction(fn () => DB::table('time_slots')->where('id', $slotId)->delete());
            $this->fail('Deleting a referenced time slot should raise a foreign-key violation.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23001', '23503']);
        }

        try {
            DB::transaction(fn () => DB::table('class_sessions')->where('id', $sessionId)->delete());
            $this->fail('Deleting a referenced class session should raise a foreign-key violation.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23001', '23503']);
        }

        $this->assertDatabaseHas('replacement_requests', ['id' => $requestId]);
    }

    public function test_proposer_delete_still_cascades_by_design(): void
    {
        ['proposerId' => $proposerId, 'requestId' => $requestId] = $this->seedFixture();

        DB::table('users')->where('id', $proposerId)->delete();

        $this->assertDatabaseMissing('replacement_requests', ['id' => $requestId]);
    }

    public function test_class_cancelled_audit_action_accepted(): void
    {
        ['proposerId' => $proposerId] = $this->seedFixture();

        DB::table('audit_logs')->insert([
            'user_id' => $proposerId,
            'action' => 'class_cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'class_cancelled']);
    }
}
