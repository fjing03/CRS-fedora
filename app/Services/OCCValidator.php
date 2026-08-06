<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class OCCValidator
{
    /**
     * Validate OCC and reserve the time slot (available → pending).
     *
     * Steps: request validation → FOR UPDATE lock → status check →
     * version++ with raw UPDATE → refresh → audit log → return OCCResult.
     *
     * @throws ModelNotFoundException
     */
    public function validateAndReserve(int $timeSlotId, int $userId, int $replacementRequestId): OCCResult
    {
        return DB::transaction(function () use ($timeSlotId, $userId, $replacementRequestId): OCCResult {
            // Step 1 — Request validation
            $request = ReplacementRequest::query()->findOrFail($replacementRequestId);

            if ($request->status !== 'pending' || (int) $request->replacement_time_slot_id !== $timeSlotId) {
                return $this->auditConflict($userId, $request, null, 'request_not_pending_or_slot_mismatch');
            }

            // Step 2 — Lock the slot row
            $slot = TimeSlot::query()->whereKey($timeSlotId)->lockForUpdate()->firstOrFail();

            // Step 3 — Status check
            if ($slot->status !== 'available') {
                return $this->auditConflict($userId, $request, $slot, 'slot_not_available');
            }

            // Step 4 — Version check + atomic raw UPDATE
            $expectedVersion = (int) $slot->version;
            $affected = DB::update(
                'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                ['pending', $expectedVersion + 1, $timeSlotId, $expectedVersion]
            );

            if ($affected === 0) {
                return $this->auditConflict($userId, $request, $slot, 'concurrent_reserve_conflict');
            }

            // Step 5 — Refresh the model
            $slot->refresh();

            // Step 6 — Audit log (success)
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'submitted',
                'replacement_request_id' => $replacementRequestId,
                'time_slot_id' => $timeSlotId,
                'old_status' => 'available',
                'new_status' => 'pending',
                'occ_validation_result' => 'success',
                'details' => ['version' => $expectedVersion],
            ]);

            return OCCResult::success($slot);
        });
    }

    private function auditConflict(int $userId, ReplacementRequest $request, ?TimeSlot $slot, string $reason): OCCResult
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => 'submitted',
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot?->id,
            'old_status' => null,
            'new_status' => null,
            'occ_validation_result' => 'conflict',
            'details' => ['conflict_reason' => $reason],
        ]);

        return OCCResult::conflict($reason);
    }
}
