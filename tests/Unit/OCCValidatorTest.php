<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Services\OCCValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OCCValidatorTest extends TestCase
{
    use RefreshDatabase;

    private function occ(): OCCValidator
    {
        return new OCCValidator;
    }

    private function seedData(array $slotOverrides = [], array $requestOverrides = []): array
    {
        $existing = DB::table('semesters')->first();
        if ($existing) {
            $semesterId = (int) $existing->id;
        } else {
            $semesterId = (int) DB::table('semesters')->insertGetId([
                'semester_code' => 'T'.mt_rand(100, 999),
                'label' => 'Test Semester',
                'start_date' => now()->subMonth(),
                'end_date' => now()->addMonth(),
                'week_count' => 14,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingVenue = DB::table('venues')->first();
        if ($existingVenue) {
            $venueId = (int) $existingVenue->id;
        } else {
            $venueId = (int) DB::table('venues')->insertGetId([
                'room_code' => 'V'.mt_rand(100, 999),
                'capacity' => 40,
                'room_type' => 'tutorial',
                'allowed_session_types' => 'L,T,P',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingModule = DB::table('modules')->first();
        if ($existingModule) {
            $moduleId = (int) $existingModule->id;
        } else {
            $moduleId = (int) DB::table('modules')->insertGetId([
                'module_code' => 'MOD'.mt_rand(100, 999),
                'module_name' => 'Test Module',
                'allowed_session_types' => 'L,T,P',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingUser = DB::table('users')->where('role', 'student')->first();
        if ($existingUser) {
            $userId = (int) $existingUser->id;
        } else {
            $userId = (int) DB::table('users')->insertGetId([
                'name' => 'Test User',
                'email' => 'test_'.mt_rand(1000, 9999).'@test.com',
                'password' => bcrypt('password'),
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $classSessionId = (int) DB::table('class_sessions')->insertGetId([
            'semester_id' => $semesterId,
            'module_id' => $moduleId,
            'lecturer_id' => $userId,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $venueId,
            'session_type' => 'L',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slot = TimeSlot::factory()->create(array_merge([
            'semester_id' => $semesterId,
            'class_session_id' => null,
            'venue_id' => $venueId,
            'status' => 'available',
            'version' => 1,
        ], $slotOverrides));

        $request = ReplacementRequest::factory()->create(array_merge([
            'semester_id' => $semesterId,
            'proposer_id' => $userId,
            'class_session_id' => $classSessionId,
            'replacement_time_slot_id' => $slot->id,
            'week_number' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ], $requestOverrides));

        $student = DB::table('users')->where('id', $userId)->first();

        return ['slot' => $slot, 'request' => $request, 'student' => $student];
    }

    public function test_s1_happy_path_available_slot(): void
    {
        $data = $this->seedData();
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertTrue($result->success);
        $this->assertNull($result->conflictReason);
        $this->assertNotNull($result->timeSlot);
        $this->assertEquals('pending', $result->timeSlot->status);
        $this->assertEquals(2, $result->timeSlot->version);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $student->id,
            'action' => 'submitted',
            'occ_validation_result' => 'success',
            'old_status' => 'available',
            'new_status' => 'pending',
        ]);
    }

    public function test_s2_slot_occupied_returns_conflict(): void
    {
        $data = $this->seedData(slotOverrides: ['status' => 'occupied']);
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertFalse($result->success);
        $this->assertEquals('slot_not_available', $result->conflictReason);
        $this->assertNull($result->timeSlot);

        $slot->refresh();
        $this->assertEquals('occupied', $slot->status);
        $this->assertEquals(1, $slot->version);

        $this->assertDatabaseHas('audit_logs', [
            'occ_validation_result' => 'conflict',
        ]);
    }

    public function test_s3_slot_pending_returns_conflict(): void
    {
        $data = $this->seedData(slotOverrides: ['status' => 'pending']);
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertFalse($result->success);
        $this->assertEquals('slot_not_available', $result->conflictReason);
    }

    public function test_s4_version_mismatch_returns_conflict(): void
    {
        $data = $this->seedData();
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $slot->refresh();
        $this->assertEquals(1, $slot->version);
        $this->assertEquals('available', $slot->status);

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertTrue($result->success);
        $this->assertNull($result->conflictReason);
        $this->assertEquals('pending', $result->timeSlot->status);
        $this->assertEquals(2, $result->timeSlot->version);

        $this->assertDatabaseHas('audit_logs', [
            'occ_validation_result' => 'success',
        ]);
    }

    public function test_s5_request_not_found_throws(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->occ()->validateAndReserve(1, 1, 999999);
    }

    public function test_s6_request_not_pending_returns_conflict(): void
    {
        $data = $this->seedData(requestOverrides: ['status' => 'approved']);
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertFalse($result->success);
        $this->assertEquals('request_not_pending_or_slot_mismatch', $result->conflictReason);
    }

    public function test_s7_request_slot_mismatch_returns_conflict(): void
    {
        $data = $this->seedData();
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $otherSlot = TimeSlot::factory()->create([
            'semester_id' => $slot->semester_id,
            'status' => 'available',
            'version' => 1,
        ]);

        $result = $this->occ()->validateAndReserve((int) $otherSlot->id, (int) $student->id, (int) $request->id);

        $this->assertFalse($result->success);
        $this->assertEquals('request_not_pending_or_slot_mismatch', $result->conflictReason);
    }

    public function test_s8_audit_log_on_conflict(): void
    {
        $data = $this->seedData(slotOverrides: ['status' => 'occupied']);
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $audit = AuditLog::where('user_id', $student->id)
            ->where('occ_validation_result', 'conflict')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('submitted', $audit->action);
        $this->assertNull($audit->old_status);
        $this->assertNull($audit->new_status);
    }

    public function test_s9_slot_not_found_throws(): void
    {
        $this->expectException(ModelNotFoundException::class);

        DB::transaction(function () {
            $this->occ()->validateAndReserve(999999, 1, 1);
        });
    }

    public function test_s10_version_corruption_simulates_concurrency(): void
    {
        $data = $this->seedData();
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        DB::table('time_slots')
            ->where('id', $slot->id)
            ->update(['version' => 2, 'status' => 'pending']);

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertFalse($result->success);
        $this->assertEquals('slot_not_available', $result->conflictReason);

        $slot->refresh();
        $this->assertEquals('pending', $slot->status);
        $this->assertEquals(2, $slot->version);
    }

    public function test_s11_cancelled_request_returns_conflict(): void
    {
        $data = $this->seedData(requestOverrides: ['status' => 'cancelled']);
        $slot = $data['slot'];
        $request = $data['request'];
        $student = $data['student'];

        $result = $this->occ()->validateAndReserve((int) $slot->id, (int) $student->id, (int) $request->id);

        $this->assertFalse($result->success);
        $this->assertEquals('request_not_pending_or_slot_mismatch', $result->conflictReason);
    }
}
