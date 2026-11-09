<?php

namespace Tests\Feature;

use App\Livewire\ReplacementHistory;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Replacement History row — what the 2026-11-09 UI update changed, and the
 * two laws it must never lose (S10 + S11, 2026-11-08).
 *
 * S10: the Original Slot is the CLASS's own occurrence — its own week, day and
 *      time. Before that pass the row reused the replacement's week and day, so a
 *      week-14 Thursday class replaced into week 8 printed "Week 8 · Fri, 13 Nov".
 * S11: the time string spans the whole run of 30-minute cells, not the anchor.
 *
 * 2026-11-09 (user brief, fourteen rulings):
 *  - the row carries the two slots as ONE primitive shape (`orig` / `req`), so the
 *    table, the cards and the pop-up format them with one set of helpers;
 *  - a PENDING request now ships its REAL requested slot (Q2=A) — the PL's
 *    approval confirms that same slot, it does not pick a new one;
 *  - the status is the derived vocabulary the staff page prints (Q4=B/Q5=A):
 *    pending → approved → completed, where completed means the requested DATE
 *    has passed;
 *  - the conflict reason is the CLASS's cause, and a student reads the bare
 *    "Class Cancelled" for anything that is not a calendar holiday (Q1=B — no
 *    leave type ever reaches a student).
 *
 * Dates are frozen with setTestNow so the assertions never depend on the day the
 * suite runs (a suite run on a Sunday used to break sibling tests this way).
 */
final class ReplacementHistoryRowTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private User $lecturer;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        /* Monday 12 Oct 2026 — the semester's week 4; frozen so "completed" and
           "pending" mean the same thing on every run. */
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00'));

        $this->semester = Semester::create([
            'semester_code' => 'HIS001',
            'label' => 'History Row Fixture',
            'start_date' => '2026-09-21',   // Monday, week 1 day 0
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $programme = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);

        $this->venue = Venue::create([
            'room_code' => 'B010', 'room_name' => 'Tutorial Room B010',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T,P', 'capacity' => 35,
        ]);

        $cohort = Cohort::create([
            'programme_id' => $programme->id, 'current_year' => 2, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2026/27', 'intake' => 'June 2024', 'student_count' => 20,
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer', 'name' => 'Row Lecturer']);

        // the student who reads the page (Student's primary key IS user_id)
        $studentUser = User::factory()->create(['role' => 'student', 'name' => 'Row Student']);
        Student::create(['user_id' => $studentUser->id, 'student_id' => '24RSD0099', 'cohort_id' => $cohort->id]);
        $this->actingAs($studentUser);

        // the class: week 14, Thursday, 12:00–13:00, B010
        $module = Module::create([
            'module_code' => 'BMSE2163', 'module_name' => 'Software Engineering',
            'allowed_session_types' => 'L,T,P',
        ]);
        $this->session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 3,             // Thursday
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'P',
        ]);
        $this->session->cohorts()->attach([$cohort->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<int, TimeSlot> a run of 30-minute cells in week 8, Friday from 13:00 */
    private function cells(int $count): array
    {
        $cells = [];
        foreach (range(0, $count - 1) as $k) {
            $start = 13 * 3600 + $k * 1800;
            $cells[] = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => null,
                'week_number' => 8,
                'day_of_week' => 4,         // Friday
                'start_time' => date('H:i:s', $start),
                'end_time' => date('H:i:s', $start + 1800),
                'venue_id' => $this->venue->id,
                'status' => 'available',
                'version' => 1,
            ]);
        }

        return $cells;
    }

    private function request(string $status, int $cells = 2): ReplacementRequest
    {
        $span = $this->cells($cells);
        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => $this->session->id,
            'week_number' => 14,            // the CLASS's week
            'replacement_time_slot_id' => $span[0]->id,
            'status' => $status,
            'submitted_at' => '2026-10-01 03:10:00',
        ]);
        foreach ($span as $cell) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $cell->id,
            ]);
        }

        return $request;
    }

    /** @return array<string, mixed> the single payload row the page would render */
    private function row(ReplacementRequest $request): array
    {
        $payload = null;
        Livewire::test(ReplacementHistory::class)
            ->assertViewHas('payload', function (array $data) use (&$payload): bool {
                $payload = $data;

                return true;
            });

        $rows = array_values(array_filter(
            $payload['replacementHistory'],
            fn (array $r): bool => (int) $r['id'] === (int) $request->id,
        ));

        $this->assertCount(1, $rows, 'the request shows up as one row');

        return $rows[0];
    }

    public function test_the_original_slot_is_the_classes_own_week_day_and_time(): void
    {
        $row = $this->row($this->request('approved'));

        $this->assertSame(13, $row['originalWeek'], 'the class sits in week 14 (0-based 13) — not the replacement\'s week 8');
        $this->assertSame(3, $row['di'], 'Thursday — the class day, never the replacement\'s Friday');

        $orig = $row['orig'];
        $this->assertSame(14, $orig['week'], 'the Original Slot block says Week 14');
        $this->assertSame('Thursday', $orig['day']);
        $this->assertSame('12:00', $orig['start']);
        $this->assertSame('13:00', $orig['end']);
        $this->assertSame(1, $orig['duration']);
        $this->assertSame('B010', $orig['venue']);
        $this->assertSame('2026-12-24', $orig['date'], 'Thursday of week 14 (21 Dec 2026 + 3 days)');
    }

    public function test_the_requested_slot_spans_the_whole_run(): void
    {
        $row = $this->row($this->request('approved'));

        $this->assertSame(7, $row['week'], 'the row files under the requested week 8 (0-based 7)');

        $req = $row['req'];
        $this->assertSame(8, $req['week']);
        $this->assertSame('Friday', $req['day']);
        $this->assertSame('2026-11-13', $req['date'], 'Friday of week 8 (9 Nov 2026 + 4 days)');
        $this->assertSame('13:00', $req['start'], 'the anchor cell starts at 13:00');
        $this->assertSame('14:00', $req['end'], 'the run ends at 14:00 — not at the anchor cell\'s 13:30');
        $this->assertSame(1, $req['duration'], 'one hour, matching the run');
        $this->assertSame('B010', $req['venue']);
    }

    public function test_a_pending_row_now_carries_its_real_requested_slot(): void
    {
        $row = $this->row($this->request('pending'));

        $this->assertSame(13, $row['originalWeek'], 'the original slot is still the class\'s own week');
        $this->assertSame('pending', $row['status']);

        $this->assertNotNull($row['req'], '2026-11-09 (Q2=A): a pending request ships the slot it asked for');
        $this->assertSame('2026-11-13', $row['req']['date']);
        $this->assertSame('13:00', $row['req']['start']);
        $this->assertSame('14:00', $row['req']['end'], 'the pending run spans the whole ask');
        $this->assertSame('Pending — Waiting for Approval', $row['statusLabel'], 'the staff label, verbatim');
        $this->assertSame('Pending', $row['statusShort']);
        $this->assertStringContainsString(
            'AWAITING the Programme Leader',
            $row['statusTip'],
            'the tip speaks to a student, not to the proposer',
        );
        $this->assertStringNotContainsString('Your request', $row['statusTip']);
    }

    public function test_completed_is_derived_from_the_requested_date(): void
    {
        $request = $this->request('approved');

        $this->assertSame('approved', $this->row($request)['status'], 'a future requested slot stays Approved');

        // the requested Friday (13 Nov 2026) is now in the past
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00'));
        $row = $this->row($request);

        $this->assertSame('completed', $row['status']);
        $this->assertStringStartsWith('Completed', $row['statusLabel'], 'the vocabularies agree: "Completed — n days ago"');
    }

    public function test_a_holiday_is_named_but_leave_never_reaches_a_student(): void
    {
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => 14,
            'day_of_week' => 3,
            'label' => 'Christmas Day',
        ]);

        $row = $this->row($this->request('approved'));

        $this->assertSame('Public Holiday', $row['conflictKind']);
        $this->assertSame('Christmas Day', $row['holidayName'], 'a calendar holiday keeps its real name');
    }

    public function test_an_exception_reason_is_reduced_to_class_cancelled(): void
    {
        ClassException::create([
            'class_session_id' => $this->session->id,
            'week_number' => 14,
            'reason' => 'medical_leave',
        ]);

        $row = $this->row($this->request('pending'));

        $this->assertSame('Class Cancelled', $row['conflictKind'], 'the kind is still the staff page\'s classification');
        $this->assertNull($row['holidayName']);
        $this->assertStringNotContainsStringIgnoringCase(
            'medical',
            (string) json_encode($row),
            '2026-11-09 (Q1=B): a student never sees the staff member\'s leave type',
        );
    }
}
