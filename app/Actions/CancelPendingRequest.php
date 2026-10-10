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
            $slot = TimeSlot::query()->whereKey($request->replacement_time_slot_id)->lockForUpdate()->first();

            $oldStatus = null;
            if ($slot !== null && $slot->status === 'pending') {
                $expected = (int) $slot->version;
                $affected = DB::update(
                    'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                    ['available', $expected + 1, $slot->id, $expected],
                );

                if ($affected > 0) {
                    $oldStatus = 'pending';
                    $slot->refresh();
                }
            }

            $request->fill([
                'status' => 'cancelled',
                'decided_at' => now(),
            ]);
            $request->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'cancelled',
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slot?->id,
                'old_status' => $oldStatus,
                'new_status' => $oldStatus === 'pending' ? 'available' : null,
                'details' => ['cancelled_by' => 'proposer'],
            ]);
        });

        return true;
    }
}
