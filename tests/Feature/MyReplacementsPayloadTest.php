<?php

namespace Tests\Feature;

use App\Livewire\ReplacementHome;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\ReplacementRequestMapper;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * replacement-merge-one-table — design §8.1 cases 1–9: the merged payload.
 *
 * The page lists CLASSES (one row per class_session_id:week_number) and the row's
 * state is its NEWEST request. Every value has one source (proposal §6b): the
 * status is derived by ReplacementRequestMapper::effectiveStatus(), the label /
 * short form / sentence / tip by its six-state vocabulary, and the requested
 * replacement by the BATCH span read with the anchor fallback — never invented.
 *
 * Case 10 (three URLs, query string intact) cannot run until T14 adds the third
 * route; it is written in that step, not skipped here.
 */
final class MyReplacementsPayloadTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private User $lecturer;

    private Carbon $startDate;

    protected function setUp(): void
    {
        parent::setUp();

        // The semester starts three weeks ago (a Monday), so "yesterday",
        // "today" and "tomorrow" all fall inside it and the completed boundary
        // is testable without hard-coding a calendar date.
        $start = today()->subWeeks(3)->startOfWeek(Carbon::MONDAY);

        $this->semester = Semester::create([
            'semester_code' => 'MRG001',
            'label' => 'Merge Fixture Semester',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addWeeks(20)->toDateString(),
            'week_count' => 14,
        ]);
        $this->startDate = Carbon::parse($this->semester->start_date);

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        $dept = Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $programme = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);

        $cohort = Cohort::create([
            'programme_id' => $programme->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 20,
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer', 'name' => 'Merge Lecturer']);
        Lecturer::create(['user_id' => $this->lecturer->id, 'staff_id' => '6100', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->cohort = $cohort;
    }

    private Cohort $cohort;

    // ── fixture helpers ────────────────────────────────────────────────────

    /** @return array{0: int, 1: int} [week, day] for a date inside the fixture semester */
    private function weekDayFor(CarbonInterface $date): array
    {
        $days = (int) $this->startDate->copy()->startOfDay()->diffInDays($date->copy()->startOfDay());

        return [(int) floor($days / 7) + 1, $days % 7];
    }

    /** A conflicted occurrence: the session plus the week's exception (the row set's entry).
     *  `$reason = null` skips the exception — the row then comes from a calendar holiday. */
    private function occurrence(int $week, int $day, string $code = 'BMIT1001', ?string $reason = 'annual_leave'): ClassSession
    {
        $module = Module::create([
            'module_code' => $code,
            'module_name' => $code.' Module',
            'allowed_session_types' => 'L,T',
        ]);

        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => $day,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach([$this->cohort->id]);

        if ($reason !== null) {
            ClassException::create([
                'class_session_id' => $session->id,
                'week_number' => $week,
                'reason' => $reason,
            ]);
        }

        return $session;
    }

    /** One available 30-min cell (the anchor shape). */
    private function slot(int $week, int $day, string $start = '14:00:00'): TimeSlot
    {
        return TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => null,
            'week_number' => $week,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => $this->venue->id,
            'status' => 'available',
            'version' => 1,
        ]);
    }

    /**
     * A request on the occurrence, anchored on the first slot it is given.
     *
     * @param  array<int, TimeSlot>  $cells  junction cells (span); empty = anchor-only
     */
    private function requestFor(
        ClassSession $session,
        int $week,
        string $status = 'pending',
        array $cells = [],
        ?string $remarks = null,
        ?string $rejectionReason = null,
        ?CarbonInterface $submittedAt = null,
    ): ReplacementRequest {
        $anchor = $cells !== [] ? $cells[0] : $this->slot($week, (int) $session->day_of_week);

        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => $session->id,
            'week_number' => $week,
            'replacement_time_slot_id' => $anchor->id,
            'status' => $status,
            'submitted_at' => $submittedAt ?? now(),
            'remarks' => $remarks,
            'rejection_reason' => $rejectionReason,
        ]);

        foreach ($cells as $cell) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $cell->id,
            ]);
            // the submit action reserves every span cell (status pending,
            // version++), so the cancel fixture starts from the same state.
            $cell->update(['status' => 'pending', 'version' => (int) $cell->version + 1]);
        }

        return $request;
    }

    /** @return array<int, TimeSlot> a contiguous span of 30-min cells from 14:00 */
    private function cells(int $week, int $day, int $count): array
    {
        $cells = [];
        foreach (range(0, $count - 1) as $k) {
            $cells[] = $this->slot($week, $day, date('H:i:s', strtotime('14:00:00') + $k * 1800));
        }

        return $cells;
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $payload = null;

        Livewire::actingAs($this->lecturer)
            ->test(ReplacementHome::class)
            ->assertViewHas('payload', function (array $p) use (&$payload): bool {
                $payload = $p;

                return true;
            });

        return $payload['conflictedClasses'] ?? [];
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function rowFor(array $rows, ClassSession $session, int $week): ?array
    {
        foreach ($rows as $row) {
            if ($row['sessionId'] === (int) $session->id && $row['week'] === $week) {
                return $row;
            }
        }

        return null;
    }

    // ── case 1 ─────────────────────────────────────────────────────────────

    public function test_one_row_per_occurrence_and_the_newest_request_wins(): void
    {
        $session = $this->occurrence(5, 0);
        $this->requestFor($session, 5, 'rejected', [], null, 'Slot unavailable', now()->subMinutes(30));
        $this->requestFor($session, 5, 'cancelled', [], null, null, now()->subMinutes(20));
        $this->requestFor($session, 5, 'pending', [], null, null, now()->subMinutes(10));

        $rows = $this->rows();
        $mine = array_values(array_filter($rows, fn (array $r): bool => $r['sessionId'] === (int) $session->id));

        $this->assertCount(1, $mine, 'three requests on one occurrence are ONE row');
        $this->assertSame('Pending — Waiting for Approval', $mine[0]['requestStatusLabel']);
        $this->assertSame('pending', $mine[0]['requestStatus']);
    }

    // ── case 2 ─────────────────────────────────────────────────────────────

    public function test_a_row_without_a_request_carries_the_full_vocabulary(): void
    {
        $session = $this->occurrence(4, 0);

        $row = $this->rowFor($this->rows(), $session, 4);

        $this->assertNotNull($row);
        $this->assertNull($row['requestId']);
        $this->assertNull($row['requestStatus']);
        foreach (['replacementDate', 'replacementTime', 'replacementWeek', 'replacementDay', 'replacementVenue', 'replacementVenueName', 'remarks', 'rejectionReason'] as $key) {
            $this->assertNull($row[$key], $key.' is null, never "" or "—"');
        }
        $this->assertSame('Need to Arrange', $row['requestStatusLabel']);
        $this->assertSame('Need to Arrange', $row['requestStatusShort']);
        $this->assertSame('No replacement has been proposed for this class yet.', $row['requestStatusDesc']);
        $this->assertNotSame('', $row['requestStatusTip'], 'the outlined pill carries its tip');
        $this->assertSame('Tutorial Room B100', $row['venueName'], 'the original venue name is emitted without a request');
    }

    public function test_an_unnamed_venue_emits_a_null_venue_name(): void
    {
        $this->venue->update(['room_name' => null]);
        $session = $this->occurrence(4, 0);

        $row = $this->rowFor($this->rows(), $session, 4);

        $this->assertNotNull($row);
        $this->assertNull($row['venueName'], 'no name is null — never an empty string');
    }

    // ── case 3 ─────────────────────────────────────────────────────────────

    public function test_the_six_states_speak_one_language(): void
    {
        $mapper = app(ReplacementRequestMapper::class);
        $yesterday = today()->subDay()->toDateString();

        $cases = [
            [null, 'Need to Arrange', 'Need to Arrange', 'No replacement has been proposed for this class yet.'],
            ['pending', 'Pending — Waiting for Approval', 'Pending', 'The proposed replacement is waiting for the PL to approve or reject it.'],
            ['approved', 'Approved', 'Approved', 'The PL approved the proposed replacement, so this class will run at the new time.'],
            ['rejected', 'Rejected', 'Rejected', 'The PL declined the proposed replacement, so a different slot has to be arranged.'],
            ['cancelled', 'Cancelled by YOU', 'Cancelled', 'You cancelled this request, so the class is free to be arranged again.'],
            ['completed', 'Completed — 1 day ago', 'Completed', 'The replacement took place 1 day ago.'],
        ];

        foreach ($cases as [$status, $label, $short, $sentence]) {
            $date = $status === 'completed' ? $yesterday : today()->toDateString();

            $this->assertSame($label, $mapper->statusLabel($status, $date), 'label for '.var_export($status, true));
            $this->assertSame($short, $mapper->statusShort($status), 'short for '.var_export($status, true));
            $this->assertSame($sentence, $mapper->statusSentence($status, $date), 'sentence for '.var_export($status, true));
            $this->assertNotSame('', $mapper->statusTip($status), 'tip for '.var_export($status, true));
        }

        // the `today.` guard: a completed row whose date is today (only reachable
        // through a caller, never through effectiveStatus) still reads a sentence.
        $this->assertStringEndsWith('today.', $mapper->statusSentence('completed', today()->toDateString()));
    }

    // ── case 4 ─────────────────────────────────────────────────────────────

    public function test_completed_is_derived_and_the_boundary_is_today(): void
    {
        $yesterday = today()->subDay();
        [$w, $d] = $this->weekDayFor($yesterday);
        $past = $this->occurrence($w, $d, 'BMIT3001');
        $this->requestFor($past, $w, 'approved', [], null, null, now()->subDays(3));

        [$tw] = $this->weekDayFor(today());
        $todaySession = $this->occurrence($tw, (int) today()->dayOfWeekIso - 1, 'BMIT3002');
        $this->requestFor($todaySession, $tw, 'approved', [], null, null, now()->subDay());

        $tomorrow = today()->addDay();
        [$nw, $nd] = $this->weekDayFor($tomorrow);
        $future = $this->occurrence($nw, $nd, 'BMIT3003');
        $this->requestFor($future, $nw, 'approved', [], null, null, now()->subDay());

        $rows = $this->rows();

        $completed = $this->rowFor($rows, $past, $w);
        $this->assertNotNull($completed);
        $this->assertSame('completed', $completed['requestStatus']);
        $this->assertSame('Completed — 1 day ago', $completed['requestStatusLabel']);
        $this->assertSame('The replacement took place 1 day ago.', $completed['requestStatusDesc']);
        $this->assertSame('approved', ReplacementRequest::query()->where('class_session_id', $past->id)->value('status'), 'derived — nothing stored');

        $todayRow = $this->rowFor($rows, $todaySession, $tw);
        $this->assertNotNull($todayRow);
        $this->assertSame('approved', $todayRow['requestStatus'], 'a class TODAY is not completed');

        $futureRow = $this->rowFor($rows, $future, $nw);
        $this->assertNotNull($futureRow);
        $this->assertSame('approved', $futureRow['requestStatus'], 'a class TOMORROW is not completed');
    }

    // ── case 5 ─────────────────────────────────────────────────────────────

    public function test_the_requested_replacement_uses_the_junction_then_the_anchor(): void
    {
        $week = 5;
        $day = 0;
        $session = $this->occurrence($week, $day);
        $cells = $this->cells($week, $day, 4);
        $request = $this->requestFor($session, $week, 'pending', $cells);

        $row = $this->rowFor($this->rows(), $session, $week);
        $this->assertNotNull($row);
        $this->assertSame('14:00 – 16:00', $row['replacementTime'], 'the junction TRUE range');
        $this->assertSame($cells[0]->id, ReplacementRequest::query()->whereKey($request->id)->value('replacement_time_slot_id'));

        // Cancel (user brief 2026-11-06): the occurrence displays as NO ACTIVE
        // REQUEST — the badge, the dash and the missing Proposed tab all follow
        // from one normalisation in buildRow(). The stored row is untouched.
        $component = Livewire::actingAs($this->lecturer)->test(ReplacementHome::class);
        $result = $component->instance()->cancelRequest((int) $request->id);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['row']['requestStatus'], 'displayed as no active request');
        $this->assertSame('Need to Arrange', $result['row']['requestStatusLabel']);
        $this->assertSame('Need to Arrange', $result['row']['requestStatusShort']);
        $this->assertSame('No replacement has been proposed for this class yet.', $result['row']['requestStatusDesc']);
        $this->assertSame(
            'No request has been made for this class yet — it still needs arranging.',
            $result['row']['requestStatusTip'],
            'the outlined pill carries the no-request tip, not the cancellation copy'
        );
        foreach (['replacementDate', 'replacementTime', 'replacementWeek', 'replacementDay', 'replacementVenue', 'replacementVenueName', 'remarks', 'rejectionReason'] as $key) {
            $this->assertNull($result['row'][$key], $key.' is null — nothing proposed is displayed');
        }
        $this->assertSame((int) $request->id, $result['row']['requestId'], 'the row keeps its identity (notification jump, data-request-id)');

        // the RECORD is untouched — cancelled, audited, junction released
        $this->assertSame('cancelled', (string) ReplacementRequest::query()->whereKey($request->id)->value('status'));
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        // and a fresh render agrees with the returned row
        $fresh = $this->rowFor($this->rows(), $session, $week);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh['requestStatus']);
        $this->assertSame('Need to Arrange', $fresh['requestStatusLabel']);
        $this->assertNull($fresh['replacementTime']);

        /* The anchor fallback is still what a DECIDED row reads (the junction is
           released by RejectRequest too). Re-pointing the same occurrence at
           `rejected` reproduces exactly the shape that action leaves behind, and
           proves the fallback stayed alive behind the cancellation display law. */
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'rejected', 'rejection_reason' => 'Slot unavailable']);
        $rejectedRow = $this->rowFor($this->rows(), $session, $week);
        $this->assertNotNull($rejectedRow);
        $this->assertSame('rejected', $rejectedRow['requestStatus']);
        $this->assertSame('14:00 – 14:30', $rejectedRow['replacementTime'], 'anchor-only after the release — still displayed for a rejected row');
    }

    // ── case 6 ─────────────────────────────────────────────────────────────

    public function test_remark_and_rejection_reason_are_independent(): void
    {
        $rejected = $this->occurrence(6, 0, 'BMIT2001');
        $this->requestFor($rejected, 6, 'rejected', [], 'Please try Tuesday.', 'Venue unavailable');

        $approvedWeek = 6;
        $approved = $this->occurrence($approvedWeek, 1, 'BMIT2002');
        $this->requestFor($approved, $approvedWeek, 'approved', [], 'Agreed with the cohort.', null);

        $rows = $this->rows();

        $rejectedRow = $this->rowFor($rows, $rejected, 6);
        $this->assertNotNull($rejectedRow);
        $this->assertSame('Please try Tuesday.', $rejectedRow['remarks']);
        $this->assertSame('Venue unavailable', $rejectedRow['rejectionReason']);

        $approvedRow = $this->rowFor($rows, $approved, $approvedWeek);
        $this->assertNotNull($approvedRow);
        $this->assertSame('Agreed with the cohort.', $approvedRow['remarks']);
        $this->assertNull($approvedRow['rejectionReason'], 'an approved row has no rejection reason');
    }

    // ── case 7 ─────────────────────────────────────────────────────────────

    public function test_a_request_on_a_non_conflicted_occurrence_produces_no_row(): void
    {
        // A conflicted occurrence (the only kind that renders a row)…
        $conflicted = $this->occurrence(7, 0, 'BMIT2101');
        // …and a second session with NO exception and NO holiday, but a request.
        $module = Module::create(['module_code' => 'BMIT2102', 'module_name' => 'No Conflict', 'allowed_session_types' => 'L,T']);
        $clean = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 2,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $clean->cohorts()->attach([$this->cohort->id]);
        $this->requestFor($clean, 7, 'pending');

        $rows = $this->rows();

        $this->assertNotNull($this->rowFor($rows, $conflicted, 7));
        $this->assertNull($this->rowFor($rows, $clean, 7), 'the row set is closed: a request adds no row');
        $this->assertCount(1, $rows);
    }

    // ── case 8 ─────────────────────────────────────────────────────────────

    public function test_the_render_issues_a_constant_number_of_statements(): void
    {
        $occurrences = [];
        foreach (range(4, 7) as $week) {
            $occurrences[$week] = $this->occurrence($week, 0, 'BMIT3'.(100 + $week));
        }

        // 1 request, over a junction span, on the first occurrence.
        $this->requestFor($occurrences[4], 4, 'pending', $this->cells(4, 0, 2));

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = (string) $query->sql;
        });

        $this->rows();
        $one = $statements;

        // 3 more approved requests with junction spans → 4 in total.
        $statements = [];
        foreach ([5, 6, 7] as $week) {
            $this->requestFor($occurrences[$week], $week, 'approved', $this->cells($week, 0, 2), null, null, now()->subDays(2));
        }
        $statements = [];

        $this->rows();
        $four = $statements;

        $junction = fn (array $list): int => count(array_filter(
            $list,
            fn (string $sql): bool => str_contains($sql, 'replacement_request_slots')
        ));

        $this->assertSame(1, $junction($one), 'the junction is read exactly once for 1 request');
        $this->assertSame(1, $junction($four), 'the junction is read exactly once for 4 requests');
        $this->assertSame(
            count($one),
            count($four),
            'the statement count does not grow with the number of requests (1 vs 4)'
        );
    }

    // ── case 10 (deferred to T14: only now do all three URLs exist) ─────────

    public function test_all_three_urls_render_the_merged_page_and_keep_the_query_string(): void
    {
        $session = $this->occurrence(5, 0);
        $request = $this->requestFor($session, 5, 'pending');

        $query = '?notif-hl=r'.$request->id.'&view=request';

        foreach (['/my-replacements', '/replacement-home-ui', '/my-request-history-ui'] as $url) {
            $response = $this->actingAs($this->lecturer)->get($url.$query);

            /* 200 (never a 302): the alias is a route entry, so the query string
               survives an arrival instead of being dropped by a redirect. The
               body carries the SAME merged payload — the legacy mock template
               has no `conflictedClasses` script at all, so a leftover route
               entry that fell back to it would fail here. */
            $response->assertOk();
            $response->assertSee('"conflictedClasses"', false);
            $response->assertSee('"requestId":'.$request->id, false);
        }
    }

    // ── case 9 ─────────────────────────────────────────────────────────────

    public function test_cancel_returns_the_fresh_row_and_refuses_a_decided_request(): void
    {
        $week = 8;
        $day = 0;
        $session = $this->occurrence($week, $day);
        $cells = $this->cells($week, $day, 2);
        $request = $this->requestFor($session, $week, 'pending', $cells);

        $component = Livewire::actingAs($this->lecturer)->test(ReplacementHome::class);
        $result = $component->instance()->cancelRequest((int) $request->id);

        $this->assertTrue($result['ok']);
        $this->assertIsArray($result['row']);
        $this->assertNull($result['row']['requestStatus'], 'the re-read row displays as no active request');
        $this->assertSame('cancelled', (string) ReplacementRequest::query()->whereKey($request->id)->value('status'));
        $this->assertSame(
            count($cells) + 1,
            AuditLog::query()->where('action', 'cancelled')->count(),
            'one audit row per released cell, plus the request flip'
        );

        // D6: every released slot is available again.
        foreach ($cells as $cell) {
            $this->assertSame('available', (string) TimeSlot::query()->whereKey($cell->id)->value('status'));
        }

        // A decided (approved) request is refused, and no row comes back.
        $approvedWeek = 9;
        $approvedSession = $this->occurrence($approvedWeek, 0, 'BMIT4501');
        $approved = $this->requestFor($approvedSession, $approvedWeek, 'approved');

        $refused = $component->instance()->cancelRequest((int) $approved->id);

        $this->assertFalse($refused['ok']);
        $this->assertNull($refused['row']);
        $this->assertSame('approved', (string) ReplacementRequest::query()->whereKey($approved->id)->value('status'));
    }

    // ── case 11 (explore-brief row 4) ───────────────────────────────────────

    public function test_the_conflict_badge_names_the_holiday_and_falls_back_literally(): void
    {
        // 1. A calendar holiday with no exception row: the day itself is named.
        $holiday = $this->occurrence(9, 1, 'BMIT4001', null);
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => 9,
            'day_of_week' => 1,
            'label' => 'Deepavali Holiday (In Lieu)',
        ]);

        // 2. An exception stamped `public_holiday` with no calendar row behind it:
        //    the prototype's literal fallback, never an invented day name.
        $stamped = $this->occurrence(10, 2, 'BMIT4002', 'public_holiday');

        // 3. Every other reason is a cancellation, so it carries no holiday name.
        $leave = $this->occurrence(11, 3, 'BMIT4003', 'medical_leave');

        $rows = $this->rows();

        $holidayRow = $this->rowFor($rows, $holiday, 9);
        $this->assertNotNull($holidayRow);
        $this->assertSame('Public Holiday', $holidayRow['conflictReason']);
        $this->assertSame('Deepavali Holiday (In Lieu)', $holidayRow['holidayName'], 'the badge names the day itself');

        $stampedRow = $this->rowFor($rows, $stamped, 10);
        $this->assertNotNull($stampedRow);
        $this->assertSame('Public Holiday', $stampedRow['conflictReason']);
        $this->assertSame('Semester holiday', $stampedRow['holidayName'], 'the prototype literal fallback');

        $leaveRow = $this->rowFor($rows, $leave, 11);
        $this->assertNotNull($leaveRow);
        $this->assertSame('Medical/Sick Leave', $leaveRow['conflictReason']);
        $this->assertNull($leaveRow['holidayName'], 'only a public holiday carries a name');
    }
}
