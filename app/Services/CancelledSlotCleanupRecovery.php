<?php

namespace App\Services;

use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The TESTING-ONLY recovery rehearsal helper (SDD cancelled-slot-reuse,
 * design D9, spec S30) — NOT part of the CLI, never scheduled, never
 * invoked by any controller or render path, and hard-guarded: it refuses to
 * run against anything except `class_replacement_testing`.
 *
 * A pre-reuse recovery is a SEPARATELY APPROVED operation. This helper
 * implements its two checkable halves for rehearsal only:
 *
 *  · `check()` — verify EVERY receipt cell against its recorded post-state:
 *    still available, null owner, the EXACT recorded post-version, and no
 *    live claim on it (junction membership or the junction-less anchor/span
 *    fallback). Any drifted or reused cell makes the whole verdict
 *    `refused_no_partial_restore` — no partial restore is ever proposed;
 *  · `rehearse()` — inside one transaction, re-lock and re-check, then
 *    restore ONLY cells whose verdict is restorable: back to the receipt's
 *    recorded pre-owner with the CURRENT version incremented (monotonic —
 *    an old version is never restored). The cancellation exception, the
 *    maintenance audits and all history stay untouched: a recovery re-fills
 *    cells, it does not undo the class cancellation.
 *
 * If any cell has drifted or been reused, this helper stops before writing
 * and reports the affected bookings — the real recovery decision belongs to
 * the operator.
 */
final class CancelledSlotCleanupRecovery
{
    public const REHEARSAL_TARGET = CancelledSlotCleanupTargetPolicy::REHEARSAL_TARGET;

    public function __construct(
        private readonly ReplacementRequestMapper $mapper = new ReplacementRequestMapper,
    ) {}

    /**
     * Verify every receipt cell (a cleanup receipt's `changed` entries).
     *
     * @param  list<array<string, mixed>>  $receiptCells  entries shaped like {slot_id, start_time, end_time, before: {status, class_session_id, version}, after: {...}}
     * @return array{verdict: string, cells: list<array<string, mixed>>, refusals: list<string>}
     */
    public function check(array $receiptCells): array
    {
        $this->guard();

        return $this->assess($receiptCells);
    }

    /**
     * The transactional rehearsal: restore only the safe receipt cells, or
     * nothing at all.
     *
     * @param  list<array<string, mixed>>  $receiptCells
     * @return array{verdict: string, restored: list<array<string, mixed>>, refusal_reason: string|null}
     */
    public function rehearse(array $receiptCells): array
    {
        $this->guard();

        try {
            $restored = DB::transaction(function () use ($receiptCells): array {
                $slotIds = array_values(array_unique(array_map(
                    fn (array $cell): int => (int) ($cell['slot_id'] ?? 0),
                    $receiptCells,
                )));
                sort($slotIds);

                if (in_array(0, $slotIds, true)) {
                    throw new CancelledSlotCleanupRecoveryRefused('malformed_receipt_cell');
                }

                TimeSlot::query()->whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get();

                $verdict = $this->assess($receiptCells);

                if ($verdict['verdict'] !== 'restore_allowed') {
                    throw new CancelledSlotCleanupRecoveryRefused('drifted_or_reused_cell');
                }

                $restored = [];

                foreach ($verdict['cells'] as $cell) {
                    $row = DB::table('time_slots')->where('id', (int) $cell['slot_id'])->first();

                    if ($row === null) {
                        throw new CancelledSlotCleanupRecoveryRefused('row_vanished:'.((int) $cell['slot_id']));
                    }

                    if ($cell['owner_session_id'] === null) {
                        throw new CancelledSlotCleanupRecoveryRefused('owner_missing:'.((int) $cell['slot_id']));
                    }

                    $currentVersion = (int) $row->version;
                    $affected = DB::update(
                        'UPDATE time_slots SET status = ?, class_session_id = ?, version = ?'
                        .' WHERE id = ? AND status = ? AND class_session_id IS NULL AND version = ?',
                        [
                            'occupied',
                            (int) $cell['owner_session_id'],
                            $currentVersion + 1,
                            (int) $cell['slot_id'],
                            'available',
                            $currentVersion,
                        ],
                    );

                    if ($affected === 0) {
                        throw new CancelledSlotCleanupRecoveryRefused('stale_cell:'.((int) $cell['slot_id']));
                    }

                    $restored[] = [
                        'slot_id' => (int) $cell['slot_id'],
                        'owner_session_id' => (int) $cell['owner_session_id'],
                        'before_version' => $currentVersion,
                        'after_version' => $currentVersion + 1,
                    ];
                }

                return $restored;
            });

            return ['verdict' => 'restored', 'restored' => $restored, 'refusal_reason' => null];
        } catch (CancelledSlotCleanupRecoveryRefused $e) {
            return ['verdict' => 'refused_no_partial_restore', 'restored' => [], 'refusal_reason' => $e->reason];
        }
    }

    /**
     * The shared assessment — SELECT-only, usable inside a transaction.
     *
     * @param  list<array<string, mixed>>  $receiptCells
     * @return array{verdict: string, cells: list<array<string, mixed>>, refusals: list<string>}
     */
    private function assess(array $receiptCells): array
    {
        $cells = [];
        $refusals = [];

        foreach ($receiptCells as $receiptCell) {
            $slotId = (int) ($receiptCell['slot_id'] ?? 0);
            $after = is_array($receiptCell['after'] ?? null) ? $receiptCell['after'] : null;
            $before = is_array($receiptCell['before'] ?? null) ? $receiptCell['before'] : null;
            $expectedVersion = $after === null ? -1 : (int) ($after['version'] ?? -1);
            $ownerSessionId = $before === null ? null : ($before['class_session_id'] ?? null);

            if ($after === null || $slotId === 0) {
                $refusals[] = 'malformed_receipt_cell';
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'malformed_receipt_cell'];

                continue;
            }

            $row = DB::table('time_slots')->where('id', $slotId)->first();

            if ($row === null) {
                $refusals[] = $slotId.':missing_row';
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'missing_row'];

                continue;
            }

            if ((string) $row->status !== 'available') {
                $refusals[] = $slotId.':not_available';
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'not_available'];

                continue;
            }

            if ($row->class_session_id !== null) {
                $refusals[] = $slotId.':owner_changed';
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'owner_changed'];

                continue;
            }

            if ((int) $row->version !== $expectedVersion) {
                $refusals[] = $slotId.':version_drifted';
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'version_drifted'];

                continue;
            }

            $junctionClaim = DB::table('replacement_request_slots as l')
                ->join('replacement_requests as r', 'r.id', '=', 'l.replacement_request_id')
                ->where('l.time_slot_id', $slotId)
                ->whereIn('r.status', ['pending', 'approved'])
                ->first();

            if ($junctionClaim !== null) {
                $refusals[] = $slotId.':live_junction_claim:request_'.$junctionClaim->replacement_request_id;
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'live_junction_claim'];

                continue;
            }

            $fallbackClaim = $this->fallbackClaim($row);

            if ($fallbackClaim !== null) {
                $refusals[] = $slotId.':live_fallback_claim:request_'.$fallbackClaim;
                $cells[] = ['slot_id' => $slotId, 'restorable' => false, 'reason' => 'live_fallback_claim'];

                continue;
            }

            $cells[] = [
                'slot_id' => $slotId,
                'restorable' => true,
                'reason' => null,
                'owner_session_id' => $ownerSessionId === null ? null : (int) $ownerSessionId,
                'current_version' => (int) $row->version,
            ];
        }

        $verdict = $refusals === [] ? 'restore_allowed' : 'refused_no_partial_restore';

        return ['verdict' => $verdict, 'cells' => $cells, 'refusals' => $refusals];
    }

    /**
     * The junction-less anchor/span fallback (the documented D3 fallback):
     * an active request of the semester anchored in this cell's room/week/day
     * at or before this cell's start, WITHOUT junction rows, whose mapped
     * span covers the cell — it is a live claim, so the cell is reused.
     *
     * @param  \stdClass  $row  the raw `time_slots` row under assessment
     */
    private function fallbackClaim(\stdClass $row): ?int
    {
        $candidates = DB::table('replacement_requests as r')
            ->join('time_slots as a', 'a.id', '=', 'r.replacement_time_slot_id')
            ->where('r.semester_id', $row->semester_id)
            ->whereIn('r.status', ['pending', 'approved'])
            ->where('a.week_number', $row->week_number)
            ->where('a.day_of_week', $row->day_of_week)
            ->where('a.venue_id', $row->venue_id)
            ->where('a.start_time', '<=', $row->start_time)
            ->get(['r.id', 'r.replacement_span', 'r.replacement_time_slot_id']);

        foreach ($candidates as $candidate) {
            $junctionCount = DB::table('replacement_request_slots')
                ->where('replacement_request_id', $candidate->id)
                ->count();

            if ($junctionCount > 0) {
                continue; // a live receipt owns its cells directly; the fallback does not speak for it
            }

            // A scalar single-row read (never `find()`, which may return a
            // Collection): the mapper's anchor is exactly one TimeSlot.
            $anchor = TimeSlot::query()->where('id', $candidate->replacement_time_slot_id)->first();
            $span = $this->mapper->spanFromAnchor($anchor, $candidate->replacement_span !== null ? (int) $candidate->replacement_span : null);

            if ($span === null
                || (int) $span['week'] !== (int) $row->week_number
                || (int) $span['day'] !== (int) $row->day_of_week
                || (int) $span['venue_id'] !== (int) $row->venue_id) {
                continue;
            }

            $cellStart = Carbon::parse((string) $row->start_time);

            if ($cellStart->greaterThanOrEqualTo(Carbon::parse($span['start']))
                && $cellStart->lessThan(Carbon::parse($span['end']))) {
                return (int) $candidate->id;
            }
        }

        return null;
    }

    /** Fail closed on anything but the testing rehearsal database. */
    private function guard(): void
    {
        $row = DB::selectOne('SELECT current_database() AS db');
        $name = is_object($row) ? ($row->db ?? null) : null;
        $actual = $name === null ? '' : (string) $name;

        if ($actual !== self::REHEARSAL_TARGET) {
            throw new RuntimeException(
                'Cancelled-slot recovery rehearsal is TESTING-ONLY: refusing database "'.$actual.'".'
                .' A pre-reuse recovery on any other database is a separately approved operator operation.',
            );
        }
    }
}
