<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;

/**
 * The auto-reject rule's write leg (user brief "Auto-Reject Pending Requests",
 * 2026-11-06): a PENDING request whose proposed start sits within (or exactly)
 * 3 working hours of now is REJECTED by the system.
 *
 * The release family is `CancelPendingRequest`'s (the hardening that a
 * SYSTEM-driven sweep needs — it never throws, it skips drifted rows):
 * ordered slot locks → per-slot version-checked `pending→available` flip →
 * junction DELETE → the request stamp → audits. The difference is the STAMP:
 *
 *  - `status = 'rejected'` + the fixed system `rejection_reason`;
 *  - `approver_id` stays NULL — no human decided, so the row must not claim one
 *    (the queue prints "reviewedBy" from `approver_id`);
 *  - every audit row rides `action = 'rejected'` (the `audit_logs.action` CHECK
 *    has no system value and widening it would be a schema change on the demo
 *    DBs) with `details.auto = true`, and is attributed to the request's own
 *    PROPOSER — `audit_logs.user_id` is NOT NULL, and the honest subject of this
 *    transition is the lecturer whose window closed.
 *
 * No mail: the sweep can run at any hour, and the in-app feed already derives
 * the rejection from the status (`NavNotifications`), so the lecturer still
 * gets the receipt where every other decision lands.
 */
final class AutoRejectRequest
{
    /** The stored reason — also the sentence the history/detail views print. */
    public const REASON = 'Automatically rejected — fewer than 3 working hours remained before the proposed start, so it could no longer be decided in time.';

    /** @return bool false when the row is gone or already decided (raced by a PL) */
    public function __invoke(int $requestId): bool
    {
        $request = ReplacementRequest::query()->find($requestId);

        if ($request === null || (string) $request->status !== 'pending') {
            return false;
        }

        $rejected = DB::transaction(function () use ($request): bool {
            // The request-row lock: a PL approving at the same instant wins or
            // loses cleanly, never half-releases.
            $locked = ReplacementRequest::query()->whereKey($request->id)->lockForUpdate()->first();

            if ($locked === null || (string) $locked->status !== 'pending') {
                return false;
            }

            $slotIds = ReplacementRequestSlot::query()
                ->where('replacement_request_id', $locked->id)
                ->orderBy('time_slot_id')
                ->pluck('time_slot_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($slotIds === []) {
                $slotIds = [(int) $locked->replacement_time_slot_id];
            }

            $slots = TimeSlot::query()
                ->whereIn('id', $slotIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $flipped = [];

            foreach ($slotIds as $slotId) {
                $slot = $slots->get($slotId);

                if ($slot === null || (string) $slot->status !== 'pending') {
                    continue; // skip drifted rows: no flip, no false audit
                }

                $expected = (int) $slot->version;
                $affected = DB::update(
                    'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                    ['available', $expected + 1, $slot->id, $expected],
                );

                if ($affected === 0) {
                    continue; // someone flipped it first — leave their state alone
                }

                $flipped[] = (int) $slot->id;

                AuditLog::create([
                    'user_id' => (int) $locked->proposer_id,
                    'action' => 'rejected',
                    'replacement_request_id' => $locked->id,
                    'time_slot_id' => (int) $slot->id,
                    'old_status' => 'pending',
                    'new_status' => 'available',
                    'details' => ['decision' => 'rejected', 'auto' => true, 'reason' => self::REASON],
                ]);
            }

            // The junction rows DELETE (the receipt completes its life).
            ReplacementRequestSlot::query()
                ->where('replacement_request_id', $locked->id)
                ->delete();

            $locked->fill([
                'status' => 'rejected',
                'approver_id' => null,
                'decided_at' => now(),
                'rejection_reason' => self::REASON,
            ]);
            $locked->save();

            AuditLog::create([
                'user_id' => (int) $locked->proposer_id,
                'action' => 'rejected',
                'replacement_request_id' => $locked->id,
                'time_slot_id' => (int) $locked->replacement_time_slot_id,
                'old_status' => 'pending',
                'new_status' => null,
                'details' => [
                    'decision' => 'rejected',
                    'auto' => true,
                    'reason' => self::REASON,
                    'slots' => $flipped,
                ],
            ]);

            return true;
        });

        return $rejected;
    }
}
