<?php

namespace Tests\Feature;

use App\Actions\CancelClassBatch;
use App\Livewire\MyTimetable;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Holiday;
use App\Models\Module;
use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Support\Notifications\NotificationEmitter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SDD cancelled-slot-reuse A05/A06/A07 — the original-slot release integrated
 * into the cancellation paths.
 *
 * The laws pinned here (spec R1–R4 selected scenarios):
 *   · 30/60/120-minute originals release cell by cell; other weeks and the
 *     recurring session are untouched (S01);
 *   · already-available originals are structured no-ops, never repairs (S02);
 *   · a newer request holding an original cell is preserved, never released (S03);
 *   · missing/ambiguous originals refuse the WHOLE batch and write nothing (S04);
 *   · attached pending AND approved requests close with only their proven
 *     landing cells freed, the audit stating the ACTUAL old status (S06);
 *   · an attached receipt that is incoherent — two rooms, a contradictory
 *     anchor, a gap, a state contradiction, a midnight-wrapping legacy span —
 *     proves nothing and refuses the batch with nothing written (S06/A);
 *   · the day path is ONE transaction over the class batch and the ticked
 *     withdrawals — a refused batch stops them, a failed withdrawal rolls
 *     everything back, and notifications fire only after the outermost commit
 *     (S07/S08).
 *
 * Minimal explicit fixtures (no seed data), frozen semester dates; the
 * class-cancellation audits require RefreshDatabase (the widened CHECK's
 * down() declines on leftover rows).
 */
final class CancelledSlotReleaseTest extends TestCase
{
    use RefreshDatabase;

    /** Week 12 of the frozen semester is comfortably inside the cancel window. */
    private const WEEK = 12;

    private const DAY = 1; // Tuesday

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => 'CSR001',
            'label' => 'Cancelled Slot Release Fixture',
            'start_date' => '2026-09-21', // a Monday
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $this->venue = Venue::create([
            'room_code' => 'CS101',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);

        $this->module = Module::create([
            'module_code' => 'CS1001',
            'module_name' => 'Cancelled Slot Release Module',
            'allowed_session_types' => 'L,T,P',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer']);
    }

    private function makeSession(string $start, string $end, ?User $lecturer = null): ClassSession
    {
        return ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => ($lecturer ?? $this->lecturer)->id,
            'day_of_week' => self::DAY,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
    }

    /**
     * Occupy the session's original cells for ONE week (the initial recurring
     * reservation): one 30-minute row per cell, matching the session's owner.
     *
     * @return list<TimeSlot>
     */
    private function occupyOccurrence(ClassSession $session, int $week, int $fromVersion = 1): array
    {
        $cells = [];

        foreach ($this->cellStarts($session) as $start) {
            $cells[] = TimeSlot::create([
                'semester_id' => (int) $session->semester_id,
                'class_session_id' => (int) $session->id,
                'week_number' => $week,
                'day_of_week' => (int) $session->day_of_week,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => (int) $session->venue_id,
                'status' => 'occupied',
                'version' => $fromVersion,
            ]);
        }

        return $cells;
    }

    /** @return list<string> the occurrence's 30-minute cell starts */
    private function cellStarts(ClassSession $session): array
    {
        $starts = [];
        $cursor = Carbon::parse((string) $session->start_time);
        $end = Carbon::parse((string) $session->end_time);

        while ($cursor->lessThan($end)) {
            $starts[] = $cursor->format('H:i:s');
            $cursor->addMinutes(30);
        }

        return $starts;
    }

    /**
     * A request holding a landing block of given slot rows: the LIVE junction
     * receipt plus the claim's own cell state, so the request genuinely owns
     * its cells the way approval leaves them.
     *
     * @param  list<TimeSlot>  $landing
     * @param  array<string, mixed>  $overrides
     */
    private function requestHolding(ClassSession $source, array $landing, string $status, array $overrides = []): ReplacementRequest
    {
        $expectedState = $status === 'pending' ? 'pending' : 'occupied';

        foreach ($landing as $cell) {
            if ((string) $cell->status !== $expectedState) {
                $cell->update(['status' => $expectedState, 'version' => (int) $cell->version + 1]);
            }
        }

        $request = ReplacementRequest::create(array_merge([
            'semester_id' => (int) $source->semester_id,
            'proposer_id' => (int) $source->lecturer_id,
            'class_session_id' => (int) $source->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => (int) $landing[0]->id,
            'replacement_span' => count($landing),
            'status' => $status,
            'submitted_at' => now(),
            'decided_at' => $status === 'approved' ? now() : null,
        ], $overrides));

        foreach ($landing as $cell) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => (int) $cell->id,
            ]);
        }

        return $request;
    }

    /** The class-cancellation audit row of one occurrence (details is json). */
    private function cancellationAudit(ClassSession $session, int $week): ?AuditLog
    {
        return AuditLog::query()
            ->where('action', 'class_cancelled')
            ->get()
            ->first(fn (AuditLog $row): bool => (int) ($row->details['class_session_id'] ?? 0) === (int) $session->id
                && (int) ($row->details['week_number'] ?? 0) === $week);
    }

    // ── S01: 30/60/120 originals release, other weeks untouched ──────────

    public function test_original_cells_release_for_30_60_and_120_minute_occurrences(): void
    {
        $halfHour = $this->makeSession('09:00:00', '09:30:00');
        $oneHour = $this->makeSession('10:00:00', '11:00:00');
        $twoHours = $this->makeSession('13:00:00', '15:00:00');

        $weekTwelve = [
            $this->occupyOccurrence($halfHour, self::WEEK),
            $this->occupyOccurrence($oneHour, self::WEEK),
            $this->occupyOccurrence($twoHours, self::WEEK),
        ];
        $weekEleven = [
            $this->occupyOccurrence($halfHour, self::WEEK - 1),
            $this->occupyOccurrence($oneHour, self::WEEK - 1),
            $this->occupyOccurrence($twoHours, self::WEEK - 1),
        ];
        $beforeVersions = [];
        foreach (array_merge(...$weekTwelve) as $cell) {
            $beforeVersions[(int) $cell->id] = (int) $cell->version;
        }

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $halfHour, 'week' => self::WEEK],
            ['session' => $oneHour, 'week' => self::WEEK],
            ['session' => $twoHours, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertTrue($result['ok'], (string) $result['hint']);

        foreach ($weekTwelve as $i => $cells) {
            $this->assertCount([1, 2, 4][$i], $cells);

            foreach ($cells as $cell) {
                $cell->refresh();
                $this->assertSame('available', (string) $cell->status, 'the original cell is freed');
                $this->assertNull($cell->class_session_id, 'the original owner id is cleared');
                $this->assertSame($beforeVersions[(int) $cell->id] + 1, (int) $cell->version, 'exactly one version bump');
            }
        }

        // the recurring schedule and every OTHER week stay exactly as they were
        foreach ($weekEleven as $cells) {
            foreach ($cells as $cell) {
                $cell->refresh();
                $this->assertSame('occupied', (string) $cell->status);
                $this->assertSame(1, (int) $cell->version, 'other weeks are byte-identical');
            }
        }

        foreach ([$halfHour, $oneHour, $twoHours] as $session) {
            $this->assertNotNull(ClassSession::find($session->id), 'the recurring session stays');
            $this->assertSame(1, ClassException::query()
                ->where('class_session_id', $session->id)
                ->where('week_number', self::WEEK)->count());
        }
    }

    // ── S02: available originals are structured no-ops ────────────────────

    public function test_an_already_available_original_is_a_no_op_never_a_repair(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');

        // stale original ids on AVAILABLE rows — old data, never tidied up
        foreach ($this->cellStarts($session) as $start) {
            TimeSlot::create([
                'semester_id' => (int) $session->semester_id,
                'class_session_id' => (int) $session->id,
                'week_number' => self::WEEK,
                'day_of_week' => (int) $session->day_of_week,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => (int) $session->venue_id,
                'status' => 'available',
                'version' => 1,
            ]);
        }

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertTrue($result['ok'], (string) $result['hint']);

        foreach (TimeSlot::query()->where('class_session_id', $session->id)->get() as $cell) {
            $this->assertSame('available', (string) $cell->status);
            $this->assertSame(1, (int) $cell->version, 'no version bump on a no-op');
            $this->assertNotNull($cell->class_session_id, 'stale ids are NOT cleared just to tidy data');
        }

        $audit = $this->cancellationAudit($session, self::WEEK);
        $this->assertNotNull($audit);
        $this->assertSame([], $audit->details['original_release']['changed']);
        $this->assertCount(2, $audit->details['original_release']['no_ops']);
        $this->assertSame('already_available', $audit->details['original_release']['no_ops'][0]['reason']);
    }

    // ── S04: missing / ambiguous refuse the whole batch, nothing written ──

    public function test_a_missing_original_refuses_the_whole_batch_and_writes_nothing(): void
    {
        $first = $this->makeSession('09:00:00', '10:00:00');
        $second = $this->makeSession('13:00:00', '14:00:00');

        $firstCells = $this->occupyOccurrence($first, self::WEEK);
        $secondCells = $this->occupyOccurrence($second, self::WEEK);
        $secondCells[0]->delete(); // one expected cell is simply gone

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $first, 'week' => self::WEEK],
            ['session' => $second, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertFalse($result['ok']);
        $this->assertSame('original_release', $result['refused']);
        $this->assertStringContainsString('missing_row', (string) $result['hint'], 'the refusal names the reason');

        // ALL-or-nothing: the first class's cells and receipts are untouched
        foreach ($firstCells as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status);
            $this->assertSame($cell->class_session_id, (int) $first->id);
        }

        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'class_cancelled')->count());
    }

    public function test_an_ambiguous_original_refuses_the_whole_batch(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // an inactive junction link on an original cell: contradictory lifecycle
        // evidence the classification may not ignore (S04, fail closed)
        $stale = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => (int) $cells[0]->id,
            'status' => 'rejected',
            'submitted_at' => now(),
            'decided_at' => now(),
            'rejection_reason' => 'old decision',
        ]);
        ReplacementRequestSlot::create([
            'replacement_request_id' => $stale->id,
            'time_slot_id' => (int) $cells[0]->id,
        ]);

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertFalse($result['ok']);
        $this->assertSame('original_release', $result['refused']);

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status, 'nothing was freed');
        }

        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(1, ReplacementRequestSlot::query()->where('replacement_request_id', $stale->id)->count());
    }

    // ── S03: a newer request holding an original cell is preserved ───────

    public function test_a_newer_request_holding_an_original_cell_is_preserved(): void
    {
        $session = $this->makeSession('09:00:00', '11:00:00'); // four cells
        $otherLecturer = User::factory()->create(['role' => 'lecturer']);
        $otherClass = $this->makeSession('13:00:00', '14:00:00', $otherLecturer);

        $cells = $this->occupyOccurrence($session, self::WEEK);

        // a newer request (another class) whose live receipt covers the FIRST
        // two original cells — a live claim outranks the stale original id
        $newer = $this->requestHolding($otherClass, [$cells[0], $cells[1]], 'approved');

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertTrue($result['ok'], (string) $result['hint']);

        foreach ([$cells[0], $cells[1]] as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status, 'the newer holder keeps the cell');
            $this->assertNotNull($cell->class_session_id, 'its protected fields are byte-identical');
        }

        foreach ([$cells[2], $cells[3]] as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the rest of the occurrence is freed');
            $this->assertNull($cell->class_session_id);
        }

        $newer->refresh();
        $this->assertSame('approved', (string) $newer->status);
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $newer->id)->count(),
            'the newer receipt is untouched');

        $audit = $this->cancellationAudit($session, self::WEEK);
        $this->assertNotNull($audit);
        $this->assertCount(2, $audit->details['original_release']['changed']);
        $this->assertCount(2, $audit->details['original_release']['no_ops']);
        $this->assertSame('request_held', $audit->details['original_release']['no_ops'][0]['reason']);
        $this->assertSame((int) $newer->id, $audit->details['original_release']['no_ops'][0]['held_by_request_id']);
    }

    // ── S06: attached pending AND approved requests close correctly ──────

    public function test_pending_and_approved_attached_requests_close_with_only_their_cells_freed(): void
    {
        $first = $this->makeSession('09:00:00', '10:00:00');
        $second = $this->makeSession('13:00:00', '14:00:00');

        $firstCells = $this->occupyOccurrence($first, self::WEEK);
        $secondCells = $this->occupyOccurrence($second, self::WEEK);

        // landing blocks in ANOTHER week, at the claim's own state
        $pendingLanding = [];
        foreach (['16:00:00', '16:30:00'] as $i => $start) {
            $pendingLanding[] = TimeSlot::create([
                'semester_id' => (int) $first->semester_id,
                'class_session_id' => null,
                'week_number' => self::WEEK + 1,
                'day_of_week' => 4,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => (int) $first->venue_id,
                'status' => 'pending',
                'version' => 2,
            ]);
        }
        $pending = $this->requestHolding($first, $pendingLanding, 'pending');

        $approvedLanding = [];
        foreach (['17:00:00', '17:30:00'] as $start) {
            $approvedLanding[] = TimeSlot::create([
                'semester_id' => (int) $second->semester_id,
                'class_session_id' => null,
                'week_number' => self::WEEK + 1,
                'day_of_week' => 4,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => (int) $second->venue_id,
                'status' => 'occupied',
                'version' => 2,
            ]);
        }
        $approved = $this->requestHolding($second, $approvedLanding, 'approved');

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $first, 'week' => self::WEEK],
            ['session' => $second, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertTrue($result['ok'], (string) $result['hint']);
        $this->assertSame(2, $result['requestsClosed']);

        foreach ([$pending, $approved] as $request) {
            $request->refresh();
            $this->assertSame('cancelled', (string) $request->status);
            $this->assertNotNull($request->decided_at);
            $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        }

        foreach ($pendingLanding as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the pending block is freed');
            $this->assertSame(3, (int) $cell->version);
        }

        foreach ($approvedLanding as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the OCCUPIED block is freed by the cancellation-only policy');
            $this->assertSame(3, (int) $cell->version);
        }

        foreach (array_merge($firstCells, $secondCells) as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the originals are freed too');
        }

        // the closure audit states the ACTUAL old status — no phantom pending stamp
        $pendingAudit = AuditLog::query()
            ->where('action', 'cancelled')
            ->where('replacement_request_id', $pending->id)
            ->where('old_status', 'pending')
            ->first();
        $approvedAudit = AuditLog::query()
            ->where('action', 'cancelled')
            ->where('replacement_request_id', $approved->id)
            ->where('old_status', 'approved')
            ->first();
        $this->assertNotNull($pendingAudit);
        $this->assertNotNull($approvedAudit, 'an approved closure is not stamped as pending');
    }

    // ── A: adversarial attached receipts — geometry/state drift refuses ──

    /** A pending request whose junction receipt is built by hand. */
    private function requestWithReceipt(ClassSession $source, array $landing, string $status, int $anchorId): ReplacementRequest
    {
        $request = ReplacementRequest::create([
            'semester_id' => (int) $source->semester_id,
            'proposer_id' => (int) $source->lecturer_id,
            'class_session_id' => (int) $source->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => $anchorId,
            'status' => $status,
            'submitted_at' => now(),
            'decided_at' => $status === 'approved' ? now() : null,
        ]);

        foreach ($landing as $cell) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => (int) $cell->id,
            ]);
        }

        return $request;
    }

    /** A free-coordinate pending cell in one room. */
    private function pendingCell(int $semesterId, int $venueId, string $start): TimeSlot
    {
        return TimeSlot::create([
            'semester_id' => $semesterId,
            'class_session_id' => null,
            'week_number' => self::WEEK + 1,
            'day_of_week' => 4,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => $venueId,
            'status' => 'pending',
            'version' => 2,
        ]);
    }

    /** The batch refused on the attached receipt and wrote NOTHING. */
    private function assertNothingWrittenFor(ClassSession $session, array $originalCells, ReplacementRequest $request, array $receiptCells, string $because): void
    {
        /* The exact ordered receipt membership BEFORE the operation — the
           AFTER assertion compares the SAME ordered id list, never the count
           of the CHECKED cells: a checked set may legitimately be wider than
           the junction (a receipt opened by a different anchor) or have no
           junction at all (a legacy fallback claim). */
        $junctionIds = static fn (): array => array_values(ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->all());

        $junctionBefore = $junctionIds();

        /* Byte-exact row snapshots — status + version + owner pointer — for
           the originals AND every checked receipt cell, so "nothing written"
           is proven per row, not weakened to a status-only read. */
        $snapshot = static function (array $cells): array {
            $rows = [];

            foreach ($cells as $cell) {
                $rows[(int) $cell->id] = [
                    'status' => (string) $cell->status,
                    'version' => (int) $cell->version,
                    'class_session_id' => $cell->class_session_id !== null ? (int) $cell->class_session_id : null,
                ];
            }

            return $rows;
        };

        $before = $snapshot(array_merge($originalCells, $receiptCells));

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertFalse($result['ok'], $because);
        $this->assertSame('original_release', $result['refused']);

        $this->assertSame($junctionBefore, $junctionIds(), 'the receipt stands');

        $request->refresh();
        $this->assertSame('pending', (string) $request->status, 'the request was not closed');

        foreach (array_merge($originalCells, $receiptCells) as $cell) {
            $cell->refresh();

            $this->assertSame($before[(int) $cell->id], [
                'status' => (string) $cell->status,
                'version' => (int) $cell->version,
                'class_session_id' => $cell->class_session_id !== null ? (int) $cell->class_session_id : null,
            ], 'the row is byte-identical — nothing was freed, nothing was closed');
        }

        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'class_cancelled')->count());
    }

    public function test_an_attached_receipt_spanning_two_venues_refuses_the_batch(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        $otherVenue = Venue::create([
            'room_code' => 'CS-DRIFT',
            'capacity' => 30,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);

        $home = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '16:00:00');
        $foreign = $this->pendingCell((int) $session->semester_id, (int) $otherVenue->id, '16:30:00');
        $request = $this->requestWithReceipt($session, [$home, $foreign], 'pending', (int) $home->id);

        $this->assertNothingWrittenFor($session, $cells, $request, [$home, $foreign],
            'a receipt spanning two rooms is unreadable — it proves nothing and must refuse');
    }

    public function test_an_attached_receipt_not_opened_by_its_anchor_refuses_the_batch(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        $first = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '16:00:00');
        $second = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '16:30:00');
        $elsewhere = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '17:00:00');
        $request = $this->requestWithReceipt($session, [$first, $second], 'pending', (int) $elsewhere->id);

        $this->assertNothingWrittenFor($session, $cells, $request, [$first, $second, $elsewhere],
            'the anchor must open its own run — a contradictory claim proves nothing');
    }

    public function test_a_non_contiguous_attached_receipt_refuses_the_batch(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        $first = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '16:00:00');
        $third = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '17:00:00');
        $request = $this->requestWithReceipt($session, [$first, $third], 'pending', (int) $first->id);

        $this->assertNothingWrittenFor($session, $cells, $request, [$first, $third],
            'a gap breaks the run — a malformed receipt proves nothing');
    }

    public function test_an_attached_pending_claim_on_an_occupied_cell_refuses_the_batch(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        $free = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '16:00:00');
        $taken = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '16:30:00');
        $taken->update(['status' => 'occupied', 'version' => 3]);
        $request = $this->requestWithReceipt($session, [$free, $taken], 'pending', (int) $free->id);

        $this->assertNothingWrittenFor($session, $cells, $request, [$free, $taken],
            'a pending claim on an occupied cell is contradictory state, not owner proof');
    }

    public function test_a_legacy_fallback_span_wrapping_midnight_refuses_the_batch(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // junction-less legacy claim whose stored span runs past midnight
        $anchor = $this->pendingCell((int) $session->semester_id, (int) $session->venue_id, '23:30:00');
        $request = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => (int) $anchor->id,
            'replacement_span' => 2,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->assertNothingWrittenFor($session, $cells, $request, [$anchor],
            'a midnight-wrapping fallback span cannot be placed on one day — refuse, never protect arbitrary cells');
    }

    public function test_a_freed_attached_cell_loses_a_stale_original_owner_pointer(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // the class's OWN approved request lands on its original coordinates —
        // the freed rows still carry the source id, and the release must clear
        // the pointer instead of leaving a misleading "original owner"
        $landing = $this->requestHolding($session, [$cells[0], $cells[1]], 'approved');

        $result = (new CancelClassBatch)($this->lecturer, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertTrue($result['ok'], (string) $result['hint']);
        $this->assertSame(1, $result['requestsClosed']);

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the approved landing block is freed');
            $this->assertNull($cell->class_session_id, 'the stale original owner pointer is cleared');
        }

        // the audit receipt states the truth: occupied → available, per flip
        $audit = $this->cancellationAudit($session, self::WEEK);
        $this->assertNotNull($audit);
        $attached = $audit->details['attached_release'];
        $this->assertSame((int) $landing->id, $attached['request_id']);
        $this->assertSame('approved', $attached['old_status'], 'the ACTUAL old status, no phantom pending stamp');
        $this->assertSame([(int) $cells[0]->id, (int) $cells[1]->id], $attached['junction_slot_ids']);
        $this->assertCount(2, $attached['changed']);
        $this->assertSame('occupied', $attached['changed'][0]['before']['status']);
        $this->assertSame((int) $session->id, $attached['changed'][0]['before']['class_session_id']);
        $this->assertSame('available', $attached['changed'][0]['after']['status']);
        $this->assertNull($attached['changed'][0]['after']['class_session_id']);
    }

    // ── S07/S08: the day path is ONE transaction ─────────────────────────

    private function actingLecturer(): void
    {
        $this->actingAs($this->lecturer);
    }

    private function cancelDay(string $reason, array $requestIds = [], array $sessionIds = []): array
    {
        $this->actingLecturer();

        return (new MyTimetable)->cancelClassDay(self::WEEK, self::DAY, $reason, null, $requestIds, $sessionIds);
    }

    public function test_the_day_cancel_commits_the_class_and_the_ticked_withdrawal_together(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // a make-up request of MY OWN, anchored on this day, class in another week
        $landing = TimeSlot::create([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => null,
            'week_number' => self::WEEK,
            'day_of_week' => self::DAY,
            'start_time' => '16:00:00',
            'end_time' => '16:30:00',
            'venue_id' => (int) $session->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);
        $makeUp = $this->requestHolding($session, [$landing], 'pending', ['week_number' => self::WEEK - 1]);

        $result = $this->cancelDay('medical_leave', [(int) $makeUp->id], [(int) $session->id]);

        $this->assertTrue($result['ok'], (string) ($result['hint'] ?? ''));
        $this->assertCount(1, $result['cancelled']);
        $this->assertSame(1, $result['requestsWithdrawn'], 'the ticked make-up was withdrawn');
        $this->assertSame(0, $result['requestsClosed'], 'the make-up is NOT the class\'s own request');

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the originals are released');
        }

        $landing->refresh();
        $this->assertSame('available', (string) $landing->status, 'the withdrawal frees its block');

        $makeUp->refresh();
        $this->assertSame('cancelled', (string) $makeUp->status);

        // notifications ONLY after the outermost commit — and it committed
        $this->assertSame(1, Notification::query()
            ->where('user_id', (int) $this->lecturer->id)
            ->where('type', 'update')
            ->where('class_session_id', (int) $session->id)
            ->where('week', self::WEEK)
            ->count(), 'the class-cancelled fan-out fired once');
    }

    public function test_a_refused_class_batch_stops_the_withdrawals(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $this->occupyOccurrence($session, self::WEEK);

        // the batch refuses on the holiday ban (the day read does not check it)
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => self::WEEK,
            'day_of_week' => self::DAY,
            'label' => 'Batch Refusal Holiday',
        ]);

        $landing = TimeSlot::create([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => null,
            'week_number' => self::WEEK,
            'day_of_week' => self::DAY,
            'start_time' => '16:00:00',
            'end_time' => '16:30:00',
            'venue_id' => (int) $session->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);
        $makeUp = $this->requestHolding($session, [$landing], 'pending', ['week_number' => self::WEEK - 1]);

        $result = $this->cancelDay('medical_leave', [(int) $makeUp->id], [(int) $session->id]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('holiday', (string) $result['hint'], 'the day confirms the batch\'s refusal');
        $this->assertSame(0, $result['requestsWithdrawn'], 'a refused batch stops the withdrawals');

        $makeUp->refresh();
        $this->assertSame('pending', (string) $makeUp->status, 'the request was NOT withdrawn');
        $this->assertSame('pending', (string) $landing->status, 'its cells are untouched');
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(1, ReplacementRequestSlot::query()->where('replacement_request_id', $makeUp->id)->count());
        $this->assertSame(0, Notification::query()->count(), 'a refusal notifies nobody');
    }

    public function test_a_failed_withdrawal_rolls_back_the_whole_selection(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // the class's OWN request, landing in another week, ticked for
        // withdrawal AND closed by the batch — a contradictory selection
        $landing = [];
        foreach (['16:00:00', '16:30:00'] as $start) {
            $landing[] = TimeSlot::create([
                'semester_id' => (int) $session->semester_id,
                'class_session_id' => null,
                'week_number' => self::WEEK + 1,
                'day_of_week' => 4,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => (int) $session->venue_id,
                'status' => 'pending',
                'version' => 2,
            ]);
        }
        $ownRequest = $this->requestHolding($session, $landing, 'pending');

        $result = $this->cancelDay('medical_leave', [(int) $ownRequest->id], [(int) $session->id]);

        $this->assertFalse($result['ok'], 'the contradictory selection cannot half-commit');
        $this->assertSame(0, $result['requestsWithdrawn']);
        $this->assertSame([], $result['cancelled']);
        $this->assertSame(0, $result['requestsClosed']);

        // EVERYTHING rolled back: the request is still pending with its receipt
        $ownRequest->refresh();
        $this->assertSame('pending', (string) $ownRequest->status);
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $ownRequest->id)->count());

        foreach ($landing as $cell) {
            $cell->refresh();
            $this->assertSame('pending', (string) $cell->status, 'its cells were not freed');
            $this->assertSame(2, (int) $cell->version);
        }

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status, 'the originals were not released');
            $this->assertSame(1, (int) $cell->version);
        }

        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'class_cancelled')->count());
        $this->assertSame(0, Notification::query()->count(), 'a rollback sends no notification');
    }

    public function test_all_invalid_request_ids_are_refused_before_anything_is_written(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $this->occupyOccurrence($session, self::WEEK);

        $result = $this->cancelDay('medical_leave', [999999], [(int) $session->id]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not yours to withdraw', (string) $result['hint']);
        $this->assertSame(0, $result['requestsWithdrawn']);
        $this->assertSame(0, ClassException::query()->count(), 'the class batch never ran');
        $this->assertSame(0, AuditLog::query()->where('action', 'class_cancelled')->count());
    }

    public function test_a_ticked_make_up_withdraws_only_the_request_its_source_stays(): void
    {
        // the make-up's SOURCE class teaches the same day — it must survive
        $sourceClass = $this->makeSession('09:00:00', '10:00:00');
        $sourceCells = $this->occupyOccurrence($sourceClass, self::WEEK - 1);

        $openedClass = $this->makeSession('13:00:00', '14:00:00');
        $openedCells = $this->occupyOccurrence($openedClass, self::WEEK);

        $landing = TimeSlot::create([
            'semester_id' => (int) $sourceClass->semester_id,
            'class_session_id' => null,
            'week_number' => self::WEEK,
            'day_of_week' => self::DAY,
            'start_time' => '16:00:00',
            'end_time' => '16:30:00',
            'venue_id' => (int) $sourceClass->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);
        $makeUp = $this->requestHolding($sourceClass, [$landing], 'pending', ['week_number' => self::WEEK - 1]);

        $result = $this->cancelDay('medical_leave', [(int) $makeUp->id], [(int) $openedClass->id]);

        $this->assertTrue($result['ok'], (string) ($result['hint'] ?? ''));
        $this->assertSame(1, $result['requestsWithdrawn']);

        $makeUp->refresh();
        $this->assertSame('cancelled', (string) $makeUp->status);
        $landing->refresh();
        $this->assertSame('available', (string) $landing->status, 'the withdrawn block is freed');

        // the make-up's SOURCE class is never cancelled
        $this->assertSame(0, ClassException::query()
            ->where('class_session_id', $sourceClass->id)
            ->where('week_number', self::WEEK - 1)->count());
        foreach ($sourceCells as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status);
        }

        // only the opened class was cancelled
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $openedClass->id)
            ->where('week_number', self::WEEK)->count());
        foreach ($openedCells as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status);
        }
    }

    public function test_a_failing_post_commit_notification_fanout_never_rolls_back_or_lies(): void
    {
        /* The post-commit fan-out is best-effort: the emitter is bound to
           THROW when the afterCommit callback resolves it, and the day path's
           outer catch must NEVER read that as a rollback — the commit already
           stands, the hint stays empty and the day's writes are kept. */
        $this->app->bind(NotificationEmitter::class, function (): NotificationEmitter {
            throw new \RuntimeException('fan-out fault');
        });

        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        $result = $this->cancelDay('medical_leave', [], [(int) $session->id]);

        $this->assertTrue($result['ok'], 'a post-commit notification fault is not a failed day');
        $this->assertNull($result['hint'], 'no false "Nothing was changed" rollback claim');
        $this->assertCount(1, $result['cancelled']);

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the day commit stands despite the fan-out fault');
        }

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $session->id)
            ->where('week_number', self::WEEK)->count());
    }
}
