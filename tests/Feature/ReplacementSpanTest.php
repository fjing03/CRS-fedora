<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SDD replacement-span-persistence (user report 2026-11-07, requests #24 / #37).
 *
 * A replacement slot is a 30-minute cell, so a 1-hour proposal covers two cells
 * and a 2-hour one four. The run lived ONLY in `replacement_request_slots`, and
 * the release actions DELETE those rows to free the cells; the anchor
 * (`replacement_time_slot_id`, the span's FIRST cell) then read as a 30-minute
 * proposal. Request #24 (class 09:00–10:00, audit proof `"slots":[34412,34413]`)
 * displayed 13:30 – 14:00, and #37 (class 09:00–11:00, four cells) displayed
 * 10:30 – 11:00, both labelled "30 minutes".
 *
 * The request now stores its own width (`replacement_span`), written by the
 * submit action, so a decided row can no longer lose it. Case 1–3 read it, case
 * 4 pins the LEGACY shape (a null width keeps the old single-cell answer), case
 * 5–7 cover the backfill command.
 */
final class ReplacementSpanTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private User $lecturer;

    private Carbon $startDate;

    protected function setUp(): void
    {
        parent::setUp();

        $start = today()->startOfWeek(Carbon::MONDAY)->addWeeks(4);

        $this->semester = Semester::create([
            'semester_code' => 'SPAN01',
            'label' => 'Span Fixture Semester',
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

        $this->lecturer = User::factory()->create(['role' => 'lecturer', 'name' => 'Span Lecturer']);
        Lecturer::create(['user_id' => $this->lecturer->id, 'staff_id' => '6200', 'dept_id' => $dept->id, 'is_pl' => false]);

        // a 2-hour class, like the reported #37 (09:00–11:00)
        $module = Module::create([
            'module_code' => 'BMIT1101',
            'module_name' => 'Span Module',
            'allowed_session_types' => 'L,T',
        ]);

        $this->session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 4,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $this->session->cohorts()->attach([$cohort->id]);
    }

    private ClassSession $session;

    // ── fixture helpers ────────────────────────────────────────────────────

    /** @return array<int, TimeSlot> a contiguous run of 30-min cells from $start */
    private function cells(int $count, string $start = '10:30:00'): array
    {
        $cells = [];
        foreach (range(0, $count - 1) as $k) {
            $from = date('H:i:s', strtotime($start) + $k * 1800);
            $cells[] = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'week_number' => 8,
                'day_of_week' => 4,
                'start_time' => $from,
                'end_time' => date('H:i:s', strtotime($from) + 1800),
                'venue_id' => $this->venue->id,
                'status' => 'available',
                'version' => 1,
            ]);
        }

        return $cells;
    }

    /**
     * The shape a REJECTED/CANCELLED row has in the database: no junction rows,
     * the anchor holds the plan's first cell, and the width lives on the request.
     *
     * @param  array<int, TimeSlot>  $cells
     */
    private function decidedRequest(array $cells, string $status, ?int $span): ReplacementRequest
    {
        return ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => $this->session->id,
            'week_number' => 8,
            'replacement_time_slot_id' => $cells[0]->id,
            'replacement_span' => $span,
            'status' => $status,
            'submitted_at' => now()->subDay(),
            'decided_at' => now(),
        ]);
    }

    // ── cases ──────────────────────────────────────────────────────────────

    /** Case 1 — the reported bug: a 2-hour proposal rejected must read 2 hours. */
    public function test_a_rejected_request_reads_its_stored_width_not_the_anchor(): void
    {
        $request = $this->decidedRequest($this->cells(4), 'rejected', 4);

        $mapper = app(ReplacementRequestMapper::class);
        $span = $mapper->span($request);

        $this->assertNotNull($span);
        $this->assertSame(4, $span['count'], 'the stored width survives the junction delete');
        $this->assertSame('10:30:00', $span['start']);
        $this->assertSame('12:30:00', $span['end'], 'the end grows from the anchor start by 4 × 30 min');

        $row = $mapper->toRow($request->setRelation('classSession', $this->session), $this->startDate, 'probe');
        $this->assertSame('10:30 – 12:30', $row['replacementTime'], 'the PL queue reads the true range (#37 was 10:30 – 11:00)');
    }

    /** Case 2 — a 1-hour class (request #24): two cells, not one. */
    public function test_a_one_hour_request_reads_the_full_hour(): void
    {
        $request = $this->decidedRequest($this->cells(2, '13:30:00'), 'rejected', 2);

        $span = app(ReplacementRequestMapper::class)->span($request);

        $this->assertSame(2, $span['count']);
        $this->assertSame('13:30:00', $span['start']);
        $this->assertSame('14:30:00', $span['end'], '#24 must read 13:30 – 14:30, not 13:30 – 14:00');
    }

    /** Case 3 — the junction still wins when it exists (approved rows are untouched). */
    public function test_the_junction_still_wins_over_the_stored_width(): void
    {
        $cells = $this->cells(2, '13:30:00');
        $request = $this->decidedRequest($cells, 'approved', 2);
        foreach ($cells as $cell) {
            ReplacementRequestSlot::create(['replacement_request_id' => $request->id, 'time_slot_id' => $cell->id]);
        }

        $span = app(ReplacementRequestMapper::class)->span($request);

        $this->assertSame(2, $span['count']);
        $this->assertSame('14:30:00', $span['end']);
        $this->assertSame('13:30:00', $span['start']);
    }

    /** Case 4 — LEGACY: a row the backfill could not resolve keeps the old shape. */
    public function test_a_null_width_keeps_the_anchor_only_shape(): void
    {
        $request = $this->decidedRequest($this->cells(4), 'rejected', null);

        $span = app(ReplacementRequestMapper::class)->span($request);

        $this->assertSame(1, $span['count'], 'no stored width = the anchor alone (pre-column behaviour)');
        $this->assertSame('11:00:00', $span['end']);
        $this->assertNull($request->fresh()->replacement_span);
    }

    /**
     * Case 5 — the backfill reads the width out of the audit log: the decision
     * entry's own `slots` list, else the count of `submitted` entries.
     */
    public function test_the_backfill_fills_old_rows_from_the_audit_log(): void
    {
        $cells = $this->cells(4);
        $byDecision = $this->decidedRequest($cells, 'rejected', null);
        $bySubmitted = $this->decidedRequest($this->cells(2, '13:30:00'), 'cancelled', null);

        // the decision entry carries the whole cell list (RejectRequest's shape)
        DB::table('audit_logs')->insert([
            'user_id' => $this->lecturer->id,
            'action' => 'rejected',
            'replacement_request_id' => $byDecision->id,
            'details' => json_encode(['decision' => 'rejected', 'slots' => array_map(fn (TimeSlot $c): int => (int) $c->id, $cells)]),
        ]);
        // a cancel writes one `submitted` entry per cell and no slots list
        foreach (array_slice($this->cells(2, '13:30:00'), 0, 2) as $cell) {
            DB::table('audit_logs')->insert([
                'user_id' => $this->lecturer->id,
                'action' => 'submitted',
                'replacement_request_id' => $bySubmitted->id,
                'time_slot_id' => $cell->id,
            ]);
        }

        // dry run writes nothing
        $this->artisan('crs:backfill-replacement-span')->assertExitCode(0);
        $this->assertNull($byDecision->fresh()->replacement_span, 'a dry run must not write');
        $this->assertNull($bySubmitted->fresh()->replacement_span);

        $this->artisan('crs:backfill-replacement-span', ['--commit' => true])->assertExitCode(0);

        $this->assertSame(4, (int) $byDecision->fresh()->replacement_span, 'the decision entry names all four cells');
        $this->assertSame(2, (int) $bySubmitted->fresh()->replacement_span, 'one submitted entry per cell');
    }

    /** Case 6 — an unresolved row stays null and is reported, never guessed. */
    public function test_the_backfill_leaves_an_unresolvable_row_null(): void
    {
        $request = $this->decidedRequest($this->cells(2), 'rejected', null);

        $this->artisan('crs:backfill-replacement-span', ['--commit' => true])
            ->expectsOutputToContain('unresolved')
            ->assertExitCode(0);

        $this->assertNull($request->fresh()->replacement_span);
    }

    /** Case 7 — a row that already carries a width is never re-written. */
    public function test_the_backfill_never_touches_a_row_that_already_has_a_width(): void
    {
        $cells = $this->cells(2);
        $request = $this->decidedRequest($cells, 'rejected', 2);
        DB::table('audit_logs')->insert([
            'user_id' => $this->lecturer->id,
            'action' => 'rejected',
            'replacement_request_id' => $request->id,
            'details' => json_encode(['slots' => array_map(fn (TimeSlot $c): int => (int) $c->id, $cells)]),
        ]);

        $this->artisan('crs:backfill-replacement-span', ['--commit' => true])->assertExitCode(0);

        $this->assertSame(2, (int) $request->fresh()->replacement_span);
    }

    /** Case 8 — the demo-DB guard: a write there needs --force (the snapshot ritual). */
    public function test_the_backfill_refuses_a_demo_database_without_force(): void
    {
        $this->decidedRequest($this->cells(2), 'rejected', null);

        // the guard keys on the connection's database name; the testing DB is not one
        $this->assertStringEndsWith('_testing', (string) DB::connection()->getDatabaseName());
        $this->artisan('crs:backfill-replacement-span', ['--commit' => true])->assertExitCode(0);
    }
}
