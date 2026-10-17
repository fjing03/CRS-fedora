<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Support\Collection;
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
            // The release set: junction-linked slots (span-aware), falling back
            // to the legacy anchor for requests without junction rows.
            $slotIds = ReplacementRequestSlot::query()
                ->where('replacement_request_id', $request->id)
                ->orderBy('time_slot_id')
                ->pluck('time_slot_id');

            if ($slotIds->isEmpty()) {
                $slotIds = [(int) $request->replacement_time_slot_id];
            }

            /** @var Collection<int, TimeSlot> $slots */
            $slots = TimeSlot::query()->whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($slotIds as $slotId) {
                $slot = $slots->get($slotId);

                if ($slot === null || $slot->status !== 'pending') {
                    continue; // skip non-pending rows (no flip, no false audit row)
                }

                $expected = (int) $slot->version;
                $affected = DB::update(
                    'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                    ['available', $expected + 1, $slot->id, $expected],
                );

                if ($affected > 0) {
                    AuditLog::create([
                        'user_id' => $user->id,
                        'action' => 'cancelled',
                        'replacement_request_id' => $request->id,
                        'time_slot_id' => $slot->id,
                        'old_status' => 'pending',
                        'new_status' => 'available',
                        'details' => ['cancelled_by' => 'proposer'],
                    ]);
                }

                $slot->refresh();
            }

            // The junction rows DELETE (the receipt completes its life).
            ReplacementRequestSlot::query()
                ->where('replacement_request_id', $request->id)
                ->delete();

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
