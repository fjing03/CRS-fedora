<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\OriginalOccurrencePlan;
use App\Services\OriginalOccurrenceReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The original-occurrence ownership planner (SDD cancelled-slot-reuse,
 * design D3, tasks A02/A02b) — geometry, no-ops, refusals and provenance.
 *
 * Every scenario is built from explicit minimal fixtures (no seed data): the
 * classification matrix S01–S04 is pinned cell by cell, including the
 * adversarial cases — stale class ids, junction-less claims, inactive links,
 * contradictory claims, and audit evidence that disqualifies the pristine
 * recurring reservation. Failed `occ_conflict` attempts and cancellation
 * receipts must NEVER count as lifecycle evidence.
 */
final class OriginalOccurrenceReservationTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private User $lecturer;

    private ClassSession $session;

    private OriginalOccurrenceReservation $planner;

    /** The occurrence under test: week 5 of a Tuesday 09:00–11:00 class → four 30-minute cells. */
    private const WEEK = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => 'OOR001',
            'label' => 'Original Reservation Fixture',
            'start_date' => '2026-09-21', // a Monday
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $this->venue = Venue::create([
            'room_code' => 'OR101',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $this->module = Module::create([
            'module_code' => 'OR1001',
            'module_name' => 'Original Reservation Module',
            'allowed_session_types' => 'L,T',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer']);

        $this->session = $this->makeSession(); // Tue 09:00–11:00 (120 minutes)
        $this->planner = new OriginalOccurrenceReservation;
    }

    private function makeSession(array $overrides = []): ClassSession
    {
        return ClassSession::create(array_merge([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ], $overrides));
    }

    /**
     * One 30-minute row at the session's recurring coordinates for one week.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function cell(ClassSession $session, string $start, int $week = self::WEEK, array $overrides = []): TimeSlot
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

    /** The occurrence's four original cells, week 5, in time order. */
    private function originalCells(ClassSession $session, int $week = self::WEEK): array
    {
        return [
            $this->cell($session, '09:00:00', $week, ['version' => 1]),
            $this->cell($session, '09:30:00', $week, ['version' => 2]),
            $this->cell($session, '10:00:00', $week, ['version' => 3]),
            $this->cell($session, '10:30:00', $week, ['version' => 4]),
        ];
    }

    /**
     * A request whose LIVE receipt is the given slot ids (junction rows), with
     * an optional anchor/span override for junction-less fallback fixtures.
     *
     * @param  list<int>  $slotIds
     * @param  array<string, mixed>  $overrides
     */
    private function requestHolding(ClassSession $source, array $slotIds, array $overrides = [], ?int $anchorId = null, ?int $span = null): ReplacementRequest
    {
        $request = ReplacementRequest::create(array_merge([
            'semester_id' => (int) $source->semester_id,
            'proposer_id' => (int) $source->lecturer_id,
            'class_session_id' => (int) $source->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => $anchorId ?? ($slotIds[0] ?? null),
            'replacement_span' => $span ?? count($slotIds),
            'status' => 'pending',
            'submitted_at' => now(),
        ], $overrides));

        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        return $request;
    }

    private function plan(?ClassSession $session = null, int $week = self::WEEK): OriginalOccurrencePlan
    {
        return $this->planner->plan($session ?? $this->session, $week);
    }

    // ------------------------------------------------------------------ S01

    public function test_a_two_hour_occurrence_yields_four_original_entries_in_time_order(): void
    {
        $cells = $this->originalCells($this->session);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertTrue($plan->isReleasable());
        $this->assertFalse($plan->isNoOp());
        $this->assertCount(4, $plan->entries);
        $this->assertSame(['09:00:00', '09:30:00', '10:00:00', '10:30:00'], array_map(fn ($e) => $e->startTime, $plan->entries));

        foreach ($plan->entries as $index => $entry) {
            $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $entry->classification);
            $this->assertSame($cells[$index]->id, $entry->slotId);
            $this->assertSame('occupied', $entry->status);
            $this->assertSame((int) $this->session->id, $entry->ownerSessionId);
            $this->assertSame($index + 1, $entry->version, 'each row keeps its own version');
            $this->assertSame([$cells[$index]->id], $entry->duplicateSlotIds);
            $this->assertNull($entry->reason);
            $this->assertNull($entry->heldByRequestId);
        }

        $this->assertSame(
            [$cells[0]->id, $cells[1]->id, $cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
        );
        $this->assertSame([], $plan->outOfRange);
        $this->assertSame([], $plan->claimingRequestIds);
        $this->assertSame(
            [$cells[0]->id, $cells[1]->id, $cells[2]->id, $cells[3]->id],
            $plan->candidateSlotIds,
        );
    }

    public function test_a_thirty_minute_and_a_sixty_minute_class_yield_one_and_two_cells(): void
    {
        $halfHour = $this->makeSession(['module_id' => $this->module->id, 'start_time' => '09:00:00', 'end_time' => '09:30:00']);
        $halfHourCell = $this->cell($halfHour, '09:00:00', self::WEEK, ['class_session_id' => (int) $halfHour->id]);

        $hour = $this->makeSession(['start_time' => '13:00:00', 'end_time' => '14:00:00']);
        $hourCells = [$this->cell($hour, '13:00:00'), $this->cell($hour, '13:30:00')];

        $halfHourPlan = $this->plan($halfHour);
        $this->assertCount(1, $halfHourPlan->entries);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $halfHourPlan->entries[0]->classification);
        $this->assertSame($halfHourCell->id, $halfHourPlan->entries[0]->slotId);

        $hourPlan = $this->plan($hour);
        $this->assertCount(2, $hourPlan->entries);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $hourPlan->entries[0]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $hourPlan->entries[1]->classification);
        $this->assertSame([$hourCells[0]->id, $hourCells[1]->id], $hourPlan->releaseEligibleSlotIds());
    }

    // -------------------------------------------------- S01 geometry / S04 reporting

    public function test_linked_rows_outside_the_occurrence_are_reported_and_never_classified(): void
    {
        $cells = $this->originalCells($this->session);

        $secondVenue = Venue::create([
            'room_code' => 'OR102',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        // The same session's own recurring cell in ANOTHER week, another venue and another day.
        $otherWeek = $this->cell($this->session, '09:00:00', 6);
        $otherVenue = $this->cell($this->session, '09:00:00', self::WEEK, ['venue_id' => $secondVenue->id]);
        $otherDay = $this->cell($this->session, '09:00:00', self::WEEK, ['day_of_week' => 3]);

        $plan = $this->plan();

        $this->assertCount(4, $plan->entries, 'only the occurrence\'s own coordinates classify');
        $reported = array_column($plan->outOfRange, 'slot_id');
        sort($reported);
        $expectedReported = [$otherDay->id, $otherVenue->id, $otherWeek->id];
        sort($expectedReported);
        $this->assertSame(
            $expectedReported,
            $reported,
            'every linked row outside (semester, week, day, room, range) is reported, ascending',
        );

        foreach ($plan->outOfRange as $row) {
            $this->assertSame('occupied', $row['status']);
            $this->assertSame(1, $row['version']);
            $this->assertSame((int) $this->session->semester_id, $row['semester_id']);
            $this->assertContains($row['slot_id'], [$otherWeek->id, $otherVenue->id, $otherDay->id]);
        }

        $this->assertSame(
            [$cells[0]->id, $cells[1]->id, $cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
            'out-of-range rows are never release candidates',
        );
        $expectedLocks = array_merge(array_column($cells, 'id'), [$otherDay->id, $otherVenue->id, $otherWeek->id]);
        sort($expectedLocks);
        $this->assertSame(
            $expectedLocks,
            $plan->candidateSlotIds,
            'the caller locks the reported rows too',
        );
    }

    public function test_candidate_slot_ids_also_cover_fallback_anchors_outside_the_session_range(): void
    {
        $cells = $this->originalCells($this->session);

        // A junction-less request anchored BEFORE the session's range whose
        // span still reaches into it — its anchor must be lockable.
        $cells[0]->update(['status' => 'pending', 'class_session_id' => null]);
        $cells[1]->update(['status' => 'pending', 'class_session_id' => null]);
        $anchor = $this->cell($this->session, '08:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'pending']);
        $this->requestHolding($this->session, [], ['class_session_id' => (int) $this->session->id], $anchor->id, 4);

        $plan = $this->plan();

        $this->assertTrue(in_array($anchor->id, $plan->candidateSlotIds, true));

        $expectedIds = array_merge(array_column($cells, 'id'), [$anchor->id]);
        sort($expectedIds);
        $this->assertSame($expectedIds, $plan->candidateSlotIds, 'the fallback anchor joins the expected rows, ascending');
        $this->assertSame(
            $plan->candidateSlotIds,
            $this->planner->candidateSlotIds($this->session, self::WEEK),
            'the pre-lock helper and the plan expose the same lock union',
        );
    }

    // ------------------------------------------------------------------ S02

    public function test_every_available_row_is_a_no_op_including_duplicate_available_rows(): void
    {
        $first = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'available']);
        $duplicate = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'available', 'class_session_id' => null]);
        $third = $this->cell($this->session, '09:30:00', self::WEEK, ['status' => 'available', 'class_session_id' => null]);
        $fourth = $this->cell($this->session, '10:00:00', self::WEEK, ['status' => 'available']);
        $fifth = $this->cell($this->session, '10:30:00', self::WEEK, ['status' => 'available']);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertTrue($plan->isNoOp());
        $this->assertSame([], $plan->releaseEligibleSlotIds(), 'stale ids on available rows never make them release targets');
        $this->assertCount(4, $plan->entries);

        $nineOclock = $plan->entries[0];
        $this->assertSame(OriginalOccurrencePlan::AVAILABLE, $nineOclock->classification);
        $this->assertSame([$first->id, $duplicate->id], $nineOclock->duplicateSlotIds, 'the duplicate available rows are reported, not repaired');

        foreach ([$plan->entries[1], $plan->entries[2], $plan->entries[3]] as $entry) {
            $this->assertSame(OriginalOccurrencePlan::AVAILABLE, $entry->classification);
        }

        $this->assertSame(
            [$first->id, $duplicate->id, $third->id, $fourth->id, $fifth->id],
            $plan->candidateSlotIds,
        );
    }

    public function test_a_mixed_occurrence_releases_only_the_original_subset(): void
    {
        $original = $this->cell($this->session, '09:00:00');
        $available = $this->cell($this->session, '09:30:00', self::WEEK, ['status' => 'available', 'class_session_id' => null]);
        $originalTwo = $this->cell($this->session, '10:00:00', self::WEEK, ['version' => 7]);
        $availableTwo = $this->cell($this->session, '10:30:00', self::WEEK, ['status' => 'available']);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[0]->classification);
        $this->assertSame(OriginalOccurrencePlan::AVAILABLE, $plan->entries[1]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[2]->classification);
        $this->assertSame(OriginalOccurrencePlan::AVAILABLE, $plan->entries[3]->classification);
        $this->assertSame([$original->id, $originalTwo->id], $plan->releaseEligibleSlotIds());
        $this->assertSame([$available->id], $plan->entries[1]->duplicateSlotIds);
        $this->assertSame(7, $plan->entries[2]->version, 'the plan carries the version each flip must condition on');
    }

    // ------------------------------------------------- S04 refusals (matrix)

    public function test_a_missing_cell_refuses_the_occurrence(): void
    {
        $this->cell($this->session, '09:00:00');
        // 09:30, 10:00, 10:30 never imported.

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertFalse($plan->isReleasable());
        $this->assertSame('missing_row', $plan->refusalReason());
        $this->assertSame(OriginalOccurrencePlan::MISSING, $plan->entries[1]->classification);
        $this->assertSame('missing_row', $plan->entries[1]->reason);
        $this->assertNull($plan->entries[1]->slotId);
    }

    public function test_duplicate_coordinates_with_a_non_available_row_are_ambiguous(): void
    {
        $occupied = $this->cell($this->session, '09:00:00');
        $availableTwin = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'available', 'class_session_id' => null]);
        $this->cell($this->session, '09:30:00');

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('duplicate_coordinates', $plan->entries[0]->reason);
        $this->assertSame([$occupied->id, $availableTwin->id], $plan->entries[0]->duplicateSlotIds);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[1]->classification, 'the clean cells still classify');
    }

    public function test_an_unexplained_pending_row_is_ambiguous(): void
    {
        $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'pending']);
        $this->cell($this->session, '09:30:00');

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('unexplained_pending', $plan->entries[0]->reason);
    }

    public function test_a_mismatched_occupied_owner_is_ambiguous(): void
    {
        $otherSession = $this->makeSession(['day_of_week' => 4, 'start_time' => '14:00:00', 'end_time' => '15:00:00']);

        $this->cell($this->session, '09:00:00', self::WEEK, ['class_session_id' => (int) $otherSession->id]);
        $this->cell($this->session, '09:30:00');

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('mismatched_occupied_owner', $plan->entries[0]->reason);
    }

    public function test_an_inactive_link_is_ambiguous(): void
    {
        $cell = $this->cell($this->session, '09:00:00');
        $this->cell($this->session, '09:30:00');

        // The request family no longer owns this receipt — drift, not evidence.
        $this->requestHolding($this->session, [$cell->id], ['status' => 'rejected']);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('inactive_link', $plan->entries[0]->reason);
    }

    public function test_a_contradictory_claim_is_ambiguous(): void
    {
        $cell = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'pending']);
        $this->cell($this->session, '09:30:00', self::WEEK, ['status' => 'pending']);

        $decoy = $this->cell($this->session, '14:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'pending']);

        // The receipt links the cell, but the request's anchor lives elsewhere:
        // the claim contradicts its own junction rows.
        $request = $this->requestHolding($this->session, [$cell->id], [], $decoy->id, 1);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('contradictory_claim', $plan->entries[0]->reason);
        $this->assertSame([(int) $request->id], $plan->claimingRequestIds, 'the malformed claim is still reported for lock scoping');
    }

    public function test_conflicting_claims_are_ambiguous(): void
    {
        $first = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'pending', 'class_session_id' => null]);
        $second = $this->cell($this->session, '09:30:00', self::WEEK, ['status' => 'pending', 'class_session_id' => null]);
        $this->cell($this->session, '10:00:00');

        $otherSession = $this->makeSession(['day_of_week' => 4, 'start_time' => '15:00:00', 'end_time' => '16:00:00']);

        // Two junction-less active claims whose anchor runs overlap on 09:30.
        $this->requestHolding($this->session, [], ['class_session_id' => (int) $this->session->id], $first->id, 2);
        $this->requestHolding($otherSession, [], ['class_session_id' => (int) $otherSession->id], $second->id, 1);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[0]->classification, '09:00 has a single live owner');
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[1]->classification);
        $this->assertSame('conflicting_claims', $plan->entries[1]->reason);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[2]->classification);
    }

    public function test_a_live_claim_on_an_available_row_is_ambiguous(): void
    {
        $available = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'available']);
        $this->cell($this->session, '09:30:00');

        $this->requestHolding($this->session, [$available->id]);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('active_claim_on_available_row', $plan->entries[0]->reason);
    }

    // ------------------------------------------------------------------ S03

    public function test_a_pending_request_holds_its_cells_over_a_stale_class_id(): void
    {
        // The original cell was released, then the SAME source class re-reserved
        // it — the row still carries the stale source id AND the live receipt.
        $cells = $this->originalCells($this->session);
        $cell = $cells[0];
        $cell->update(['status' => 'pending']);

        $request = $this->requestHolding($this->session, [$cell->id], ['class_session_id' => (int) $this->session->id]);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[0]->classification);
        $this->assertSame((int) $request->id, $plan->entries[0]->heldByRequestId);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[1]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[2]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[3]->classification);
        $this->assertSame([(int) $request->id], $plan->claimingRequestIds);
        $this->assertSame(
            [$cells[1]->id, $cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
            'the untouched originals stay releasable — the claim only protects its own cell',
        );
    }

    public function test_the_junction_less_anchor_span_fallback_holds_cells(): void
    {
        // Legacy pre-junction flow: the request carries NO receipt rows; the
        // mapper's anchor/span read is the only ownership evidence. OCC flipped
        // the whole covered run pending when it reserved, so every claimed
        // span cell reads pending — the claim's own state.
        $cells = $this->originalCells($this->session);
        foreach (array_slice($cells, 0, 3) as $cell) {
            $cell->update(['status' => 'pending', 'class_session_id' => null]);
        }

        $request = $this->requestHolding($this->session, [], ['class_session_id' => (int) $this->session->id], $cells[0]->id, 3);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[0]->classification);
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[1]->classification, 'the span\'s second cell is held through the mapper fallback');
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[2]->classification, 'the span\'s third cell is held too');
        $this->assertSame((int) $request->id, $plan->entries[0]->heldByRequestId);
        $this->assertSame((int) $request->id, $plan->entries[1]->heldByRequestId);
        $this->assertSame((int) $request->id, $plan->entries[2]->heldByRequestId);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[3]->classification, 'the run\'s half-open end cell is not covered');
        $this->assertSame([(int) $request->id], $plan->claimingRequestIds);
        $this->assertSame([$cells[3]->id], $plan->releaseEligibleSlotIds(), 'the uncovered remainder stays releasable');
    }

    public function test_a_junction_less_claim_covering_an_available_row_is_ambiguous(): void
    {
        // A junction-less claim says its run was reserved; an available row
        // inside that run contradicts the claim — refuse, never ignore it.
        $anchorRow = $this->cell($this->session, '09:00:00', self::WEEK, ['status' => 'available', 'class_session_id' => null]);
        $this->cell($this->session, '09:30:00', self::WEEK, ['status' => 'pending', 'class_session_id' => null]);

        $request = $this->requestHolding($this->session, [], ['class_session_id' => (int) $this->session->id], $anchorRow->id, 2);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('active_claim_on_available_row', $plan->entries[0]->reason);
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[1]->classification, 'the covered pending cell is still protected');
        $this->assertSame((int) $request->id, $plan->entries[1]->heldByRequestId);
    }

    public function test_an_approved_request_also_holds_its_cells(): void
    {
        $cells = $this->originalCells($this->session);
        $cell = $cells[0];
        $cell->update(['class_session_id' => null]);

        $otherSession = $this->makeSession(['day_of_week' => 4, 'start_time' => '15:00:00', 'end_time' => '16:00:00']);
        $request = $this->requestHolding($this->session, [$cell->id], [
            'class_session_id' => (int) $otherSession->id,
            'status' => 'approved',
            'approver_id' => $this->lecturer->id,
            'decided_at' => now(),
        ]);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[0]->classification);
        $this->assertSame((int) $request->id, $plan->entries[0]->heldByRequestId);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[1]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[2]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[3]->classification);
        $this->assertSame(
            [$cells[1]->id, $cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
            'the untouched originals stay releasable',
        );
    }

    public function test_a_fallback_anchor_outside_the_session_range_still_protects_the_first_cells(): void
    {
        $cells = $this->originalCells($this->session);
        $cells[0]->update(['status' => 'pending', 'class_session_id' => null]);
        $cells[1]->update(['status' => 'pending', 'class_session_id' => null]);

        $anchor = $this->cell($this->session, '08:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'pending']);
        $request = $this->requestHolding($this->session, [], ['class_session_id' => (int) $this->session->id], $anchor->id, 4);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[0]->classification);
        $this->assertSame(OriginalOccurrencePlan::REQUEST_HELD, $plan->entries[1]->classification, '08:00 + 4 cells reach 10:00, so 09:00 and 09:30 are held');
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[2]->classification, '10:00 is the run\'s half-open end');
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[3]->classification);
        $this->assertSame((int) $request->id, $plan->entries[0]->heldByRequestId);
        $this->assertSame(
            [$cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
        );
    }

    // --------------------------------------------- S04 provenance / lifecycle

    public function test_request_lifecycle_evidence_disqualifies_the_original_provenance(): void
    {
        $reused = $this->cell($this->session, '09:00:00');
        $pristine = $this->cell($this->session, '09:30:00');
        $this->cell($this->session, '10:00:00');
        $this->cell($this->session, '10:30:00');

        // A successful reservation on the row, since released: the occupied
        // state can no longer be trusted as the initial recurring reservation.
        AuditLog::create([
            'user_id' => $this->lecturer->id,
            'action' => 'submitted',
            'replacement_request_id' => null,
            'time_slot_id' => $reused->id,
            'old_status' => 'available',
            'new_status' => 'pending',
            'occ_validation_result' => 'success',
            'details' => ['version' => 1],
        ]);

        // Failed attempts and cancellation receipts are NOT lifecycle evidence.
        AuditLog::create([
            'user_id' => $this->lecturer->id,
            'action' => 'submitted',
            'time_slot_id' => $pristine->id,
            'old_status' => null,
            'new_status' => null,
            'occ_validation_result' => 'conflict',
            'details' => ['conflict_reason' => 'slot_not_available'],
        ]);
        AuditLog::create([
            'user_id' => $this->lecturer->id,
            'action' => 'class_cancelled',
            'time_slot_id' => $pristine->id,
            'old_status' => null,
            'new_status' => null,
            'details' => ['reason' => 'annual_leave'],
        ]);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('provenance_contradicted', $plan->entries[0]->reason);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[1]->classification, 'conflict attempts and cancellation receipts never disqualify the pristine row');
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[2]->classification);
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[3]->classification);
    }

    /**
     * A non-transition audit summary (old == new) is not a slot change — it
     * never disqualifies the pristine recurring reservation.
     */
    public function test_a_non_transition_audit_summary_does_not_disqualify_the_pristine_original(): void
    {
        $cells = $this->originalCells($this->session);

        // A row-level summary that re-states the same status: no transition, no evidence.
        AuditLog::create([
            'user_id' => $this->lecturer->id,
            'action' => 'approved',
            'time_slot_id' => $cells[0]->id,
            'old_status' => 'occupied',
            'new_status' => 'occupied',
            'details' => ['summary' => true],
        ]);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $plan->entries[0]->classification);
        $this->assertSame([$cells[0]->id, $cells[1]->id, $cells[2]->id, $cells[3]->id], $plan->releaseEligibleSlotIds());
    }

    public function test_a_pending_claim_on_an_occupied_cell_is_contradictory_not_owner_proof(): void
    {
        $cells = $this->originalCells($this->session);

        // The receipt run is geometrically fine, but its second cell is still
        // OCCUPIED while the request is PENDING — the claim contradicts itself.
        $request = $this->requestHolding($this->session, [$cells[0]->id, $cells[1]->id]);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('claim_state_mismatch', $plan->entries[0]->reason);
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[1]->classification);
        $this->assertSame('claim_state_mismatch', $plan->entries[1]->reason);
        $this->assertSame([(int) $request->id], $plan->claimingRequestIds);
        $this->assertSame(
            [$cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
            'the untouched originals outside the contradictory run stay releasable',
        );
    }

    public function test_a_non_contiguous_junction_run_is_a_malformed_receipt(): void
    {
        $cells = $this->originalCells($this->session);

        // The receipt jumps from 09:00 to 14:00 — one run, one room, but not
        // contiguous: the claim cannot be read, so it proves nothing.
        $gapCell = $this->cell($this->session, '14:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'pending']);
        $request = $this->requestHolding($this->session, [$cells[0]->id, $gapCell->id]);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('malformed_receipt', $plan->entries[0]->reason);
        $this->assertSame([(int) $request->id], $plan->claimingRequestIds);
    }

    public function test_a_cross_midnight_fallback_span_refuses_the_occurrence(): void
    {
        $cells = $this->originalCells($this->session);

        // A junction-less claim anchored at 23:00 whose stored span of two
        // cells runs past midnight: the span cannot be placed on one day —
        // refuse, never protect arbitrary wraparound cells.
        $anchor = $this->cell($this->session, '23:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'pending']);
        $this->requestHolding($this->session, [], ['class_session_id' => (int) $this->session->id], $anchor->id, 2);

        $plan = $this->plan();

        $this->assertTrue($plan->hasRefusal());
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[0]->classification);
        $this->assertSame('malformed_fallback', $plan->entries[0]->reason);
        $this->assertSame(OriginalOccurrencePlan::AMBIGUOUS, $plan->entries[3]->classification, 'the unreadable span fails the whole occurrence closed');
        $this->assertSame('malformed_fallback', $plan->entries[3]->reason);
    }

    public function test_a_claim_anchored_in_another_room_does_not_touch_this_occurrence(): void
    {
        $cells = $this->originalCells($this->session);

        $secondVenue = Venue::create([
            'room_code' => 'OR103',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        // A junction-less pending request anchored in ANOTHER room the same
        // week/day is unrelated to this occurrence — it must not be consulted
        // and must not mark anything ambiguous. Its source class lives in that
        // other room: replicate() is unsaved until ->save(), so persist it
        // before requestHolding (which casts the source id).
        $otherSource = $this->session->replicate();
        $otherSource->venue_id = $secondVenue->id;
        $otherSource->save();

        $unrelatedAnchor = $this->cell($otherSource, '14:00:00', self::WEEK, ['class_session_id' => null, 'status' => 'pending']);
        // The anchor cell stays junction-less (class_session_id null) as
        // intended, but the REQUEST keeps its real source — do not null the FK.
        $unrelated = $this->requestHolding($otherSource, [], [], $unrelatedAnchor->id, 2);

        $plan = $this->plan();

        $this->assertFalse($plan->hasRefusal());
        foreach ($plan->entries as $index => $entry) {
            $this->assertSame(OriginalOccurrencePlan::ORIGINAL, $entry->classification);
            $this->assertNull($entry->heldByRequestId);
        }
        $this->assertNotContains((int) $unrelated->id, $plan->claimingRequestIds);
        $this->assertSame(
            [$cells[0]->id, $cells[1]->id, $cells[2]->id, $cells[3]->id],
            $plan->releaseEligibleSlotIds(),
        );
    }

    // ----------------------------------------------------- plan-level refusals

    public function test_a_week_outside_the_semester_and_a_malformed_range_are_plan_refusals(): void
    {
        $weekPlan = $this->plan($this->session, 0);
        $this->assertSame('invalid_week', $weekPlan->refusalReason());
        $this->assertTrue($weekPlan->hasRefusal());
        $this->assertSame([], $weekPlan->entries);
        $this->assertSame([], $weekPlan->candidateSlotIds);

        $rangePlan = $this->plan($this->makeSession(['start_time' => '09:00:00', 'end_time' => '10:45:00']));
        $this->assertSame('malformed_session_range', $rangePlan->refusalReason());
        $this->assertSame([], $rangePlan->entries);
    }
}
