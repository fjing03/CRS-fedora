<?php

namespace Tests\Feature;

use App\Actions\ApproveRequest;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * SDD cancelled-slot-reuse, task A08 (design D3/D4, spec S11) — the SAFE
 * approval: the original occurrence is planned/locked INSIDE the decision
 * transaction, a missing or ambiguous original refuses the WHOLE decision
 * before any landing flip, and the original release goes through the SHARED
 * `ReleaseOriginalOccurrenceSlots` with the locked pre-flip plan — so the
 * approving request's own landing cells (exact or partial overlaps, stale
 * `class_session_id` included) are never freed by their own approval.
 *
 * Minimal synthetic fixtures (no seed, frozen dates): the source class is a
 * Monday 09:00–11:00 lecture → four 30-minute original cells; the post-submit
 * reserved state is simulated with pending rows at version 2.
 *
 * A08 blocker fix (independent review FAIL): a junction-less pending request
 * with a stored width > 1 is a LEGAL fallback claim (D3 — spanFromAnchor,
 * legacy null = one cell). The approval proves the WHOLE covered run through
 * the canonical `validateRequestClaim()` and flips every current-pending
 * cell; a missing, available or wrongly-occupied tail refuses with the
 * named-time copy and NO decision, NO audit and NO partial flip.
 */
final class CancelledSlotApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** The original occurrence's week. */
    private const WEEK = 5;

    /** An ordinary make-up week, separate from the original week. */
    private const LANDING_WEEK = 7;

    /** The fixture's own Department (lecturers.dept_id is a real FK). */
    private int $departmentId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00')); // a Monday, week 5
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ───── S11 — the exact same-range rebook is never freed by itself ─────

    public function test_exact_same_range_rebook_holds_the_whole_range_despite_stale_ids(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        // The cancelled class re-requested its EXACT original range: the cells
        // still carry the STALE source id and sit pending under the request's
        // own junction receipt (the post-cancel → re-submit state).
        $cells = $this->originalGrid($session, self::WEEK, ['status' => 'pending', 'version' => 2]);
        $cellIds = array_map(static fn (TimeSlot $cell): int => (int) $cell->id, $cells);

        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $cellIds, 4);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        // The whole range is the APPROVED landing: occupied via the normal
        // decision flip (2 → 3) — never freed back to available, never owner-
        // cleared, exactly one decision version each.
        foreach ($cellIds as $i => $id) {
            $row = TimeSlot::find($id);
            $this->assertSame('occupied', (string) $row->status, "cell #$i");
            $this->assertSame(3, (int) $row->version, "cell #$i: 1 +1(reserve) +1(decide)");
            $this->assertSame((int) $session->id, (int) $row->class_session_id, "cell #$i: the stale id is not tidied");
        }

        // The junctions STAY (the live receipt) and the request is approved.
        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('approved', (string) $request->refresh()->status);

        // The receipt: zero frees, four protected no-ops naming the owner.
        $rider = $this->summaryRider((int) $request->id);
        $this->assertSame([], $rider['freed_slots'], 'own landing cells are never freed');
        $this->assertSame(self::WEEK, $rider['week_number'], 'the ORIGINAL week is receipted');
        $this->assertSame([], $rider['out_of_range']);
        $this->assertCount(4, $rider['no_ops']);

        foreach ($rider['no_ops'] as $noOp) {
            $this->assertSame('request_held', $noOp['reason']);
            $this->assertSame((int) $request->id, $noOp['held_by_request_id']);
        }

        // No exception existed before: the approval stamps it exactly once.
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', self::WEEK)
            ->where('reason', 'official_event')
            ->count());
        $this->assertTrue($rider['exception_created_here']);
        $this->assertSame(5, AuditLog::query()->where('replacement_request_id', $request->id)
            ->where('action', 'approved')->count(), 'summary + per-slot, no extra rows');
    }

    public function test_partial_overlap_landing_is_protected_and_remaining_originals_freed(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        // The original occurrence: only the FIRST cell is still the pristine
        // recurring reservation; the tail three were released by the earlier
        // cancellation and re-reserved by the class's own PARTIAL landing —
        // still carrying the STALE source id.
        $first = $this->cell($session, '09:00:00', self::WEEK);
        $tailIds = $this->landingSpan($session, self::WEEK, '09:30:00', 3, ['class_session_id' => (int) $session->id]);

        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $tailIds, 3);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        // The proven remaining original is freed through the shared release.
        $freedRow = TimeSlot::find($first->id);
        $this->assertSame('available', (string) $freedRow->status);
        $this->assertNull($freedRow->class_session_id, 'the freed cell loses its original owner');
        $this->assertSame(2, (int) $freedRow->version);

        // The landing membership: occupied via the decision flip, stale ids
        // preserved, exactly one decision version each.
        foreach ($tailIds as $i => $id) {
            $row = TimeSlot::find($id);
            $this->assertSame('occupied', (string) $row->status, "landing #$i");
            $this->assertSame(3, (int) $row->version, "landing #$i");
            $this->assertSame((int) $session->id, (int) $row->class_session_id, "landing #$i: stale id kept");
        }

        $this->assertSame(3, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('approved', (string) $request->refresh()->status);

        $rider = $this->summaryRider((int) $request->id);
        $this->assertSame([(int) $first->id], $rider['freed_slots'], 'only the independently proven original freed');
        $this->assertSame(self::WEEK, $rider['week_number']);
        $this->assertCount(3, $rider['no_ops']);

        foreach ($rider['no_ops'] as $noOp) {
            $this->assertSame('request_held', $noOp['reason']);
        }
    }

    public function test_a_newer_other_request_holding_original_cells_is_preserved(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();
        [$otherLecturer, $otherSession] = $this->secondClass($semester, $venue);

        // Post-cancellation state of the source's original occurrence: the
        // first two cells were re-reserved by ANOTHER class's newer pending
        // request; the last two were freed and stay available.
        $heldIds = $this->landingSpan($session, self::WEEK, '09:00:00', 2);
        [$newerSlots, $newer] = $this->pendingRequest($otherSession, $otherLecturer, self::WEEK, $heldIds, 2);

        $availableCells = [
            $this->cell($session, '10:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'available', 'version' => 2]),
            $this->cell($session, '10:30:00', self::WEEK, ['class_session_id' => null, 'status' => 'available', 'version' => 2]),
        ];

        // The source's own request lands ELSEWHERE (a different week) — the
        // ordinary approval shape; source week and landing week stay separate.
        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 2);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 2);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        // The newer request's cells: untouched pending rows, junction intact.
        foreach ($heldIds as $i => $id) {
            $row = TimeSlot::find($id);
            $this->assertSame('pending', (string) $row->status, "held #$i");
            $this->assertSame(2, (int) $row->version, "held #$i: no version bump");
            $this->assertNull($row->class_session_id);
        }
        $this->assertSame('pending', (string) $newer->refresh()->status);
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $newer->id)->count());

        // The already-freed cells: no-ops, not re-flipped, versions untouched.
        foreach ($availableCells as $i => $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('available', (string) $row->status, "free #$i");
            $this->assertSame(2, (int) $row->version, "free #$i");
        }

        // The approving request's own landing: normally occupied.
        foreach ($landingIds as $id) {
            $this->assertSame('occupied', (string) TimeSlot::find($id)->status);
        }

        $rider = $this->summaryRider((int) $request->id);
        $this->assertSame([], $rider['freed_slots']);
        $this->assertSame(self::WEEK, $rider['week_number']);
        $this->assertCount(4, $rider['no_ops']);

        $reasons = array_column($rider['no_ops'], 'reason');
        $this->assertSame(['request_held', 'request_held', 'already_available', 'already_available'], $reasons);
    }

    // ───── S11 — the exception stamp is once-only and never replaces a reason ─────

    public function test_an_existing_user_cancellation_reason_is_never_replaced(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $this->originalGrid($session, self::WEEK);
        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 2);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 2);

        ClassException::create([
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'reason' => 'medical_leave',
        ]);

        Mail::fake();

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        // Exactly ONE row and the USER's reason survives the approval stamp.
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', self::WEEK)
            ->count());
        $this->assertSame('medical_leave', (string) ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', self::WEEK)
            ->value('reason'));

        $rider = $this->summaryRider((int) $request->id);
        $this->assertTrue($rider['exception_stamped']);
        $this->assertFalse($rider['exception_created_here'], 'the pair already carried the user exception');
        $this->assertCount(4, $rider['freed_slots'], 'the ordinary original grid is still released');
    }

    public function test_an_existing_official_event_reason_is_never_replaced(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $this->originalGrid($session, self::WEEK);
        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 2);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 2);

        ClassException::create([
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'reason' => 'official_event',
        ]);

        Mail::fake();

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', self::WEEK)
            ->where('reason', 'official_event')
            ->count(), 'no second stamp for the approval-stamped reason value');

        $rider = $this->summaryRider((int) $request->id);
        $this->assertFalse($rider['exception_created_here']);
    }

    // ───── S08 — a mid-decision failure rolls back everything, notifies nothing ─────

    public function test_a_forced_audit_failure_rolls_back_the_whole_decision(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $cells = $this->originalGrid($session, self::WEEK);
        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 4);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 4);

        Mail::fake();

        AuditLog::creating(function (): never {
            throw new RuntimeException('forced audit fault');
        });

        try {
            app(ApproveRequest::class)($pl, (int) $request->id);
            $this->fail('the forced audit fault must abort the decision');
        } catch (RuntimeException) {
            // the fault rides out of the action; the transaction has rolled back
        }

        // Nothing decided, nothing flipped, nothing stamped, nothing queued.
        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        foreach ($landingIds as $i => $id) {
            $row = TimeSlot::find($id);
            $this->assertSame('pending', (string) $row->status, "landing #$i untouched");
            $this->assertSame(2, (int) $row->version, "landing #$i untouched");
        }

        foreach ($cells as $i => $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('occupied', (string) $row->status, "original #$i untouched");
            $this->assertSame(1, (int) $row->version, "original #$i untouched");
            $this->assertSame((int) $session->id, (int) $row->class_session_id);
        }

        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('notifications')->count(), 'no notification island rows after the rollback');
    }

    // ───── S04 — missing/ambiguous originals refuse the WHOLE decision ─────

    public function test_a_missing_original_grid_refuses_the_approval_without_writes(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        // NO rows at all at the original coordinates — intentionally strict.
        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 2);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 2);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'The block runs into an existing booking at 09:00–09:30 — pick another slot.',
            $result['conflict_reason'],
            'the first missing original cell names its own coordinates',
        );

        // Strict refusal: pending row, untouched cells, no stamp, no audits.
        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        foreach ($landingIds as $id) {
            $row = TimeSlot::find($id);
            $this->assertSame('pending', (string) $row->status);
            $this->assertSame(2, (int) $row->version);
        }

        Mail::assertNothingQueued();
    }

    public function test_an_ambiguous_original_cell_refuses_and_writes_nothing(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        // A pending original cell with NO junction receipt anywhere is an
        // unexplained pending state — ambiguous evidence, never a guess.
        $this->cell($session, '09:00:00', self::WEEK);
        $this->cell($session, '09:30:00', self::WEEK, ['status' => 'pending', 'class_session_id' => (int) $session->id]);
        $this->cell($session, '10:00:00', self::WEEK);
        $this->cell($session, '10:30:00', self::WEEK);

        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 2);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 2);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'The block runs into an existing booking at 09:30–10:00 — pick another slot.',
            $result['conflict_reason'],
        );

        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('pending', (string) TimeSlot::find($landingIds[0])->status);
        $this->assertSame('occupied', (string) TimeSlot::query()
            ->where('week_number', self::WEEK)
            ->where('day_of_week', 1)
            ->where('venue_id', $venue->id)
            ->where('start_time', '09:00:00')
            ->value('status'), 'the original cells are byte-identical after the refusal');
        Mail::assertNothingQueued();
    }

    // ───── the ordinary baseline: originals freed, weeks separate ─────

    public function test_ordinary_approval_frees_the_original_grid_and_keeps_weeks_separate(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $cells = $this->originalGrid($session, self::WEEK);
        $landingIds = $this->landingSpan($session, self::LANDING_WEEK, '14:00:00', 4);
        [$slotIds, $request] = $this->pendingRequest($session, $lecturer, self::WEEK, $landingIds, 4);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        // The ORIGINAL week's grid: released (available, owner cleared, +1).
        foreach ($cells as $i => $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('available', (string) $row->status, "original #$i");
            $this->assertNull($row->class_session_id, "original #$i");
            $this->assertSame(2, (int) $row->version, "original #$i");
        }

        // The LANDING week: occupied via the normal decision flip (+1).
        foreach ($landingIds as $i => $id) {
            $row = TimeSlot::find($id);
            $this->assertSame('occupied', (string) $row->status, "landing #$i");
            $this->assertSame(3, (int) $row->version, "landing #$i");
            $this->assertNull($row->class_session_id);
        }

        $rider = $this->summaryRider((int) $request->id);
        $this->assertSame(self::WEEK, $rider['week_number'], 'the receipt keys the ORIGINAL week, never the landing week');
        $this->assertSame(
            array_map(static fn (TimeSlot $cell): int => (int) $cell->id, $cells),
            $rider['freed_slots'],
            'the freed ids ride the typed receipt',
        );
        $this->assertSame([], $rider['no_ops']);
        $this->assertSame([], $rider['out_of_range']);

        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('approved', (string) $request->refresh()->status);
    }

    public function test_a_legacy_anchor_only_request_still_approves(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $this->originalGrid($session, self::WEEK);

        // The OCC legacy shape: ONE anchor cell, no junction rows, no width.
        $anchor = $this->cell($session, '15:00:00', self::LANDING_WEEK, [
            'class_session_id' => null,
            'status' => 'pending',
            'version' => 2,
        ]);
        $request = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => $lecturer->id,
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => (int) $anchor->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        $this->assertSame('occupied', (string) TimeSlot::find($anchor->id)->status);
        $this->assertSame(3, (int) TimeSlot::find($anchor->id)->version);
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('approved', (string) $request->refresh()->status);
        $this->assertCount(4, $this->summaryRider((int) $request->id)['freed_slots']);
    }

    // ───── A08 blocker fix — the junction-less fallback span is a legal claim ─────

    public function test_a_junction_less_span_two_request_approves_its_whole_covered_span(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $cells = $this->originalGrid($session, self::WEEK);

        // The legacy restoration shape (request #35's precedent): NO junction
        // rows — the stored width is the ONLY evidence of the second cell.
        $anchor = $this->cell($session, '14:00:00', self::LANDING_WEEK, [
            'class_session_id' => null,
            'status' => 'pending',
            'version' => 2,
        ]);
        $tail = $this->cell($session, '14:30:00', self::LANDING_WEEK, [
            'class_session_id' => null,
            'status' => 'pending',
            'version' => 2,
        ]);
        $request = $this->legacySpanRequest($session, $lecturer, self::WEEK, (int) $anchor->id, 2);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        // BOTH covered cells flipped, exactly one decision version each.
        foreach ([$anchor, $tail] as $i => $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('occupied', (string) $row->status, "covered #$i");
            $this->assertSame(3, (int) $row->version, "covered #$i: 1 +1(reserve) +1(decide)");
        }

        // No orphan pending tail survives in the landing week, and NO
        // fabricated junction links (the legacy absence stays).
        $this->assertSame(0, TimeSlot::query()
            ->where('semester_id', (int) $session->semester_id)
            ->where('week_number', self::LANDING_WEEK)
            ->where('status', 'pending')
            ->count(), 'no orphan pending tail');
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('approved', (string) $request->refresh()->status);

        // Full-span receipts: the summary names BOTH cells, one per-slot row each.
        $summary = $this->summaryAudit((int) $request->id);
        $this->assertSame([(int) $anchor->id, (int) $tail->id], $summary->details['slots']);
        $this->assertSame(3, AuditLog::query()->where('replacement_request_id', $request->id)
            ->where('action', 'approved')->count(), 'summary + two per-slot');

        // The ORIGINAL grid is still released by the same decision.
        foreach ($cells as $i => $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('available', (string) $row->status, "original #$i");
            $this->assertNull($row->class_session_id, "original #$i");
            $this->assertSame(2, (int) $row->version, "original #$i");
        }

        $rider = $summary->details['original_release'];
        $this->assertSame(
            array_map(static fn (TimeSlot $cell): int => (int) $cell->id, $cells),
            $rider['freed_slots'],
        );
        $this->assertSame([], $rider['no_ops']);
    }

    public function test_a_junction_less_span_four_same_range_rebook_holds_every_landing_cell(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        // The cancelled class re-requested its EXACT original range with NO
        // junction receipt: the cells carry the STALE source id and sit
        // pending under the anchor/span fallback alone.
        $cells = $this->originalGrid($session, self::WEEK, ['status' => 'pending', 'version' => 2]);
        $request = $this->legacySpanRequest($session, $lecturer, self::WEEK, (int) $cells[0]->id, 4);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok'], (string) $result['conflict_reason']);

        foreach ($cells as $i => $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('occupied', (string) $row->status, "cell #$i");
            $this->assertSame(3, (int) $row->version, "cell #$i");
            $this->assertSame((int) $session->id, (int) $row->class_session_id, "cell #$i: the stale id is not tidied");
        }

        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count(), 'no fabricated links');
        $this->assertSame('approved', (string) $request->refresh()->status);

        $summary = $this->summaryAudit((int) $request->id);
        $this->assertSame(
            array_map(static fn (TimeSlot $cell): int => (int) $cell->id, $cells),
            $summary->details['slots'],
        );
        $this->assertSame(5, AuditLog::query()->where('replacement_request_id', $request->id)
            ->where('action', 'approved')->count(), 'summary + four per-slot');

        // The receipt: zero frees, four protected no-ops naming the owner.
        $rider = $summary->details['original_release'];
        $this->assertSame([], $rider['freed_slots'], 'own landing cells are never freed');
        $this->assertSame(self::WEEK, $rider['week_number']);
        $this->assertSame([], $rider['out_of_range']);
        $this->assertCount(4, $rider['no_ops']);

        foreach ($rider['no_ops'] as $noOp) {
            $this->assertSame('request_held', $noOp['reason']);
            $this->assertSame((int) $request->id, $noOp['held_by_request_id']);
        }

        $this->assertTrue($rider['exception_created_here']);
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', self::WEEK)
            ->where('reason', 'official_event')
            ->count());
    }

    public function test_a_junction_less_span_with_a_missing_tail_refuses_and_writes_nothing(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $this->originalGrid($session, self::WEEK);

        // Only the ANCHOR row exists: the 14:30 tail coordinate has NO row.
        $anchor = $this->cell($session, '14:00:00', self::LANDING_WEEK, [
            'class_session_id' => null,
            'status' => 'pending',
            'version' => 2,
        ]);
        $request = $this->legacySpanRequest($session, $lecturer, self::WEEK, (int) $anchor->id, 2);

        Mail::fake();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'The block runs into an existing booking at 14:30–15:00 — pick another slot.',
            $result['conflict_reason'],
            'the missing tail names its own computed coordinate',
        );

        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame('pending', (string) TimeSlot::find($anchor->id)->status);
        $this->assertSame(2, (int) TimeSlot::find($anchor->id)->version);
        Mail::assertNothingQueued();
    }

    public function test_a_junction_less_span_whose_tail_drifted_to_the_wrong_state_refuses_and_writes_nothing(): void
    {
        [$semester, $venue, $module, $lecturer, $pl, $session] = $this->world();

        $this->originalGrid($session, self::WEEK);

        // ONE pending request is reused across BOTH wrong-tail states — the
        // active-block uniqueness (`uq_replacement_requests_active_block`) allows
        // only one active request per session/week, so the second iteration
        // drifts the SAME tail instead of inserting a second request.
        $anchor = $this->cell($session, '14:00:00', self::LANDING_WEEK, [
            'class_session_id' => null,
            'status' => 'pending',
            'version' => 2,
        ]);
        $tail = $this->cell($session, '14:30:00', self::LANDING_WEEK, [
            'class_session_id' => null,
            'status' => 'available',
            'version' => 3,
        ]);
        $request = $this->legacySpanRequest($session, $lecturer, self::WEEK, (int) $anchor->id, 2);

        Mail::fake();

        // (a) the tail was RELEASED back to available by an earlier decision.
        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'The block runs into an existing booking at 14:30–15:00 — pick another slot.',
            $result['conflict_reason'],
            'the drifted tail names its own coordinates',
        );

        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame('pending', (string) TimeSlot::find($anchor->id)->status);
        $this->assertSame('available', (string) TimeSlot::find($tail->id)->status);
        Mail::assertNothingQueued();

        // (b) the tail was re-reserved by ANOTHER booking: occupied.
        $tail->forceFill(['status' => 'occupied', 'version' => 3])->save();

        $resultB = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($resultB['ok']);
        $this->assertSame(
            'The block runs into an existing booking at 14:30–15:00 — pick another slot.',
            $resultB['conflict_reason'],
            'the occupied tail names the same coordinates',
        );

        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, ClassException::query()->count());
        $this->assertSame('pending', (string) TimeSlot::find($anchor->id)->status);
        $this->assertSame('occupied', (string) TimeSlot::find($tail->id)->status);
        Mail::assertNothingQueued();
    }

    // ───── fixtures ─────

    /**
     * The frozen minimal world: semester (a Monday start), one venue, one
     * module, a lecturer, a PL and the source class — Mon 09:00–11:00.
     *
     * @return array{0: Semester, 1: Venue, 2: Module, 3: User, 4: User, 5: ClassSession}
     */
    private function world(): array
    {
        $semester = Semester::create([
            'semester_code' => 'CSA001',
            'label' => 'Cancelled Slot Approval Fixture',
            'start_date' => '2026-09-21', // a Monday — frozen
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        // Deterministic room code (room_code is UNIQUE): RefreshDatabase rolls
        // each test back, so one fixed code can never collide. lecture_hall is
        // a CHECK-valid room_type that admits L sessions (venue-restrictions R1).
        $venue = Venue::create([
            'room_code' => 'CSA-HALL-01',
            'capacity' => 40,
            'room_type' => 'lecture_hall',
            'allowed_session_types' => 'L,T',
        ]);

        $module = Module::create([
            'module_code' => 'CSA'.random_int(1000, 9999),
            'module_name' => 'Cancelled Slot Approval Module',
            'allowed_session_types' => 'L,T',
        ]);

        // The fixture's own base identities (lecturers.dept_id is a real FK
        // to departments): a real Faculty + Department pair, not a constant.
        $faculty = Faculty::create([
            'faculty_code' => 'CSA-F',
            'faculty_name' => 'Cancelled Slot Approval Faculty',
        ]);

        $department = Department::create([
            'dept_code' => 'CSA-D',
            'dept_name' => 'Cancelled Slot Approval Department',
            'faculty_id' => $faculty->id,
        ]);

        $this->departmentId = (int) $department->id;

        $lecturer = $this->lecturer('CSA Lecturer', $this->departmentId);
        $pl = $this->lecturer('CSA PL', $this->departmentId, true);

        $session = ClassSession::create([
            'semester_id' => (int) $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'L',
        ]);

        return [$semester, $venue, $module, $lecturer, $pl, $session];
    }

    /**
     * A second independent class in the same venue — the "newer other
     * request" holder's source.
     *
     * @return array{0: User, 1: ClassSession}
     */
    private function secondClass(Semester $semester, Venue $venue): array
    {
        $lecturer = $this->lecturer('CSA Other Lecturer', $this->departmentId);

        $module = Module::create([
            'module_code' => 'CSB'.random_int(1000, 9999),
            'module_name' => 'Cancelled Slot Approval Module B',
            'allowed_session_types' => 'L,T',
        ]);

        $session = ClassSession::create([
            'semester_id' => (int) $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'L',
        ]);

        return [$lecturer, $session];
    }

    private function lecturer(string $name, int $departmentId, bool $isPl = false): User
    {
        $user = User::factory()->create(['role' => 'lecturer', 'name' => $name]);
        Lecturer::create([
            'user_id' => $user->id,
            'staff_id' => 'S'.random_int(10000, 99999),
            'dept_id' => $departmentId,
            'is_pl' => $isPl,
        ]);

        return $user;
    }

    /**
     * One 30-minute row at the session's recurring coordinates.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function cell(ClassSession $session, string $start, int $week, array $overrides = []): TimeSlot
    {
        return TimeSlot::create(array_merge([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => (int) $session->id,
            'week_number' => $week,
            'day_of_week' => (int) $session->day_of_week,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => (int) $session->venue_id,
            'status' => 'occupied',
            'version' => 1,
        ], $overrides));
    }

    /**
     * The occurrence's four original cells at the session's coordinates, in
     * time order.
     *
     * @param  array<string, mixed>  $overrides
     * @return list<TimeSlot>
     */
    private function originalGrid(ClassSession $session, int $week = self::WEEK, array $overrides = []): array
    {
        return [
            $this->cell($session, '09:00:00', $week, $overrides),
            $this->cell($session, '09:30:00', $week, $overrides),
            $this->cell($session, '10:00:00', $week, $overrides),
            $this->cell($session, '10:30:00', $week, $overrides),
        ];
    }

    /**
     * A contiguous landing span of pending cells — the post-submit reserved
     * state (available → pending, one version each).
     *
     * @param  array<string, mixed>  $overrides
     * @return list<int>
     */
    private function landingSpan(ClassSession $session, int $week, string $start, int $span, array $overrides = []): array
    {
        $ids = [];

        for ($k = 0; $k < $span; $k++) {
            $st = date('H:i:s', strtotime($start) + $k * 1800);

            $ids[] = (int) $this->cell($session, $st, $week, array_merge([
                'class_session_id' => null,
                'status' => 'pending',
                'version' => 2,
            ], $overrides))->id;
        }

        return $ids;
    }

    /**
     * A pending full-span request + its junction receipt over $slotIds, whose
     * anchor opens the run.
     *
     * @param  list<int>  $slotIds  contiguous, anchor first
     * @return array{0: list<int>, 1: ReplacementRequest}
     */
    private function pendingRequest(ClassSession $session, User $proposer, int $sourceWeek, array $slotIds, ?int $span = null): array
    {
        $request = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => $proposer->id,
            'class_session_id' => (int) $session->id,
            'week_number' => $sourceWeek,
            'replacement_time_slot_id' => $slotIds[0],
            'replacement_span' => $span,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        return [$slotIds, $request];
    }

    /**
     * A junction-less pending request whose width rides `replacement_span`
     * alone (the legacy restoration shape — no junction rows at all).
     */
    private function legacySpanRequest(ClassSession $session, User $proposer, int $sourceWeek, int $anchorId, int $span): ReplacementRequest
    {
        return ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => $proposer->id,
            'class_session_id' => (int) $session->id,
            'week_number' => $sourceWeek,
            'replacement_time_slot_id' => $anchorId,
            'replacement_span' => $span,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    /** The summary audit row. */
    private function summaryAudit(int $requestId): AuditLog
    {
        return AuditLog::query()
            ->where('replacement_request_id', $requestId)
            ->where('action', 'approved')
            ->where('occ_validation_result', 'success')
            ->firstOrFail();
    }

    /** The summary audit's `original_release` rider. */
    private function summaryRider(int $requestId): array
    {
        return $this->summaryAudit($requestId)->details['original_release'];
    }
}
