<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancel the caller's own pending replacement request (FR 2.10): the slot
 * returns to available (D6 OCC release, version++), the request becomes
 * `cancelled` with decided_at, and the transition is audited.
 *
 * Full-block extension (SDD full-block-reservation, design D5): the release
 * set = the junction rows (the LIVE receipt; nullable for legacy anchor-only
 * requests). ONE transaction, the row lock WAIT is ordered by id (same
 * deadlock pairing as the reservation), per-slot version-checked flip, a
 * per-slot audit row per ACTUAL flip (round-3 🟡: non-pending rows skip flip
 * AND audit — no false rows), the junction DELETE, and the request flip +
 * summary audit row.
 */
final class CancelPendingRequest
{
    /**
     * @return bool false when the request does not exist, is not pending, or
     *              is not owned by the caller (FR 2.11 scoping)
     */
    public function __invoke(User $user, int $requestId): bool
    {
        $request = ReplacementRequest::query()->find($requestId);

        if ($request === null
            || $request->status !== 'pending'
            || (int) $request->proposer_id !== (int) $user->id) {
            return false;
        }

        DB::transaction(function () use ($request, $user): void {
            /* 2026-11-11 (SDD cancel-whole-day, T1): the release itself now lives
               in ONE action. Cancel keeps its NON-STRICT policy — a drifted slot is
               skipped, no flip and no audit row — and keeps its own per-flip audit
               (the helper only frees; the wording is the caller's). */
            (new ReleaseRequestSlots)($request, false, function (TimeSlot $slot) use ($request, $user): void {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'cancelled',
                    'replacement_request_id' => $request->id,
                    'time_slot_id' => $slot->id,
                    'old_status' => 'pending',
                    'new_status' => 'available',
                    'details' => ['cancelled_by' => 'proposer'],
                ]);
            });

            $request->fill([
                'status' => 'cancelled',
                'decided_at' => now(),
            ]);
            $request->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'cancelled',
                'replacement_request_id' => $request->id,
                'time_slot_id' => $request->replacement_time_slot_id,
                'old_status' => 'pending',
                'new_status' => null,
                'details' => ['cancelled_by' => 'proposer'],
            ]);
        });

        return true;
    }
}
