<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\OCCValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OCCValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_s12_integration_real_seed(): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");

        $this->seed();

        $semesterId = (int) DB::table('semesters')->value('id');

        // S-12 Given: DatabaseSeeder loaded, a time_slot with status='available'.
        $slot = TimeSlot::query()
            ->where('semester_id', $semesterId)
            ->where('status', 'available')
            ->firstOrFail();

        $expectedVersion = (int) $slot->version;

        $session = ClassSession::query()->firstOrFail();
        $proposer = User::query()->where('role', 'lecturer')->firstOrFail();

        $request = ReplacementRequest::create([
            'semester_id' => $semesterId,
            'proposer_id' => $proposer->id,
            'class_session_id' => $session->id,
            'week_number' => (int) $slot->week_number,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $occ = new OCCValidator;

        // S-12 When: OCCValidator reserves it.
        $result = $occ->validateAndReserve((int) $slot->id, (int) $proposer->id, (int) $request->id);

        // S-12 Then: success, status='pending', version incremented, audit log written.
        $this->assertTrue($result->success, 'Expected OCC success on an available seeded slot');
        $this->assertNotNull($result->timeSlot, 'Success result must carry the reserved slot');
        $this->assertSame('pending', $result->timeSlot->status, 'Returned slot must read pending');

        $slot->refresh();
        $this->assertSame('pending', $slot->status, 'Reserved slot status must be pending (S-12)');
        $this->assertSame(
            $expectedVersion + 1,
            (int) $slot->version,
            'Reserved slot version must increment by exactly one (S-12)'
        );

        $successAudit = AuditLog::query()
            ->where('replacement_request_id', $request->id)
            ->where('occ_validation_result', 'success')
            ->firstOrFail();

        $this->assertSame('submitted', $successAudit->action);
        $this->assertSame((int) $slot->id, (int) $successAudit->time_slot_id);
        $this->assertSame('available', $successAudit->old_status);
        $this->assertSame('pending', $successAudit->new_status);
        $this->assertSame($expectedVersion, $successAudit->details['version'] ?? null);

        // S-12 And: a sequential second call on the same slot returns conflict.
        $second = $occ->validateAndReserve((int) $slot->id, (int) $proposer->id, (int) $request->id);

        $this->assertFalse($second->success, 'Second reserve of the same slot must conflict (S-12)');
        $this->assertSame('slot_not_available', $second->conflictReason, 'Conflict reason must identify the taken slot');

        $slot->refresh();
        $this->assertSame('pending', $slot->status, 'Conflict path must not alter the slot');
        $this->assertSame(
            $expectedVersion + 1,
            (int) $slot->version,
            'Conflict path must not bump the version again'
        );

        $conflictAudit = AuditLog::query()
            ->where('replacement_request_id', $request->id)
            ->where('occ_validation_result', 'conflict')
            ->firstOrFail();

        $this->assertSame('submitted', $conflictAudit->action);
        $this->assertSame((int) $slot->id, (int) $conflictAudit->time_slot_id);
        $this->assertNull($conflictAudit->old_status, 'Conflict must record no prior status');
        $this->assertNull($conflictAudit->new_status, 'Conflict must record no new status');
        $this->assertSame('slot_not_available', $conflictAudit->details['conflict_reason'] ?? null);

        $this->assertSame(
            2,
            AuditLog::query()->where('replacement_request_id', $request->id)->count(),
            'Exactly one success row and one conflict row must survive'
        );
    }
}
