<?php

namespace App\Actions;

use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ONE slot-release implementation (SDD cancel-whole-day, task T1).
 *
 * Extracted from the three actions that already released a request's slots by
 * hand — `CancelPendingRequest`, `RejectRequest`, `AutoRejectRequest` — because
 * a fourth copy was about to be written for the whole-day cancel. The three did
 * NOT behave identically, so this helper takes the POLICY as an argument instead
 * of silently picking one:
 *
 *   strict = true   a slot that is missing or no longer `pending`, or a flip that
 *                   loses the version race, THROWS — the caller's transaction
 *                   rolls back and nothing is half-released. (`RejectRequest`.)
 *   strict = false  a drifted slot is SKIPPED: no flip, no audit row. The request
 *                   still closes, exactly as cancel/auto-reject have always done.
 *
 * Everything else is common and unchanged: the junction rows are the release set
 * (the legacy anchor is the fallback), the lock is ordered by id, every flip is
 * version-checked, and the junction rows are deleted before this returns.
 *
 * The per-flip audit row is a caller concern (each family writes a different
 * `action`/`details`), so it arrives as a closure and is only called for slots
 * this helper actually freed.
 */
final class ReleaseRequestSlots
{
    /**
     * @param  (callable(TimeSlot): void)|null  $audit  called once per FREED slot
     * @return array<int, int> the freed slot ids, in lock order
     *
     * @throws ReleaseDriftException only when $strict and a slot did not release
     */
    public function __invoke(ReplacementRequest $request, bool $strict = true, ?callable $audit = null): array
    {
        /** @var Collection<int, int> $slotIds */
        $slotIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id);

        if ($slotIds->isEmpty()) {
            // legacy anchor-only request — no junction rows to read
            $slotIds = collect([(int) $request->replacement_time_slot_id]);
        }

        $slots = TimeSlot::query()
            ->whereIn('id', $slotIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($strict && $slots->count() !== $slotIds->count()) {
            throw new ReleaseDriftException;
        }

        $freed = [];

        foreach ($slotIds as $slotId) {
            /** @var TimeSlot|null $slot */
            $slot = $slots->get($slotId);

            if ($slot === null) {
                if ($strict) {
                    throw new ReleaseDriftException;
                }

                continue;
            }

            if ((string) $slot->status !== 'pending') {
                if ($strict) {
                    throw new ReleaseDriftException($slot);
                }

                continue; // skip drifted rows: no flip, no false audit row
            }

            $expected = (int) $slot->version;
            $affected = DB::update(
                'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                ['available', $expected + 1, $slot->id, $expected],
            );

            if ($affected === 0) {
                if ($strict) {
                    throw new ReleaseDriftException($slot);
                }

                continue; // someone flipped it first — leave their state alone
            }

            $freed[] = (int) $slot->id;

            if ($audit !== null) {
                $audit($slot);
            }
        }

        // The junction rows DELETE — the reservation receipt completes its life.
        ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->delete();

        return $freed;
    }
}

/**
 * A slot did not release in strict mode. The caller maps it to its own refusal
 * (RejectRequest turns it into the PL's "block runs into an existing booking"
 * message), so this class carries the failing slot and nothing else.
 */
final class ReleaseDriftException extends RuntimeException
{
    public function __construct(public readonly ?TimeSlot $slot = null)
    {
        parent::__construct('The reserved block is no longer fully pending.');
    }
}
