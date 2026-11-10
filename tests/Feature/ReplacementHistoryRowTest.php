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
 * `student-class-replacements` (2026-11-09) — the page lists CONFLICTED CLASS
 * OCCURRENCES, not requests, and the row must tell the truth about each one.
 *
 * The laws this file pins:
 *  - a holiday occurrence and a cancelled class are rows **even with no request**;
 *  - a cancelled or rejected request leaves its occurrence UNARRANGED: the row
 *    reads `Awaiting Arrangement`, carries no requested slot and no request id;
 *  - the LATEST request per occurrence wins;
 *  - `completed` follows the make-up date (clock frozen, never the wall clock);
 *  - the conflict reason is the CLASS's cause, and a leave type never reaches the
 *    browser;
 *  - the original slot is the class's own week, day, time, length and room.
 *
 * S10/S11 (2026-11-08) survive here: the original slot is the class's own
 * occurrence, and the requested slot's time string spans the whole run.
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

        /* Monday 12 Oct 2026 — frozen so "pending"/"approved"/"completed" mean the
           same thing on every run (a suite run on a Sunday used to break siblings). */
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00'));

        $this->semester = Semester::create([
            'semester_code' => 'HIS001',
            'label' => 'Class Replacements Fixture',
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

    /** A holiday on the class's own day, in its own week — the commonest row by far. */
    private function holidayOnTheClass(string $label = 'Deepavali Holiday (In Lieu)'): void
    {
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => 14,
            'day_of_week' => 3,
            'label' => $label,
        ]);
    }

    /** @return array<int, TimeSlot> a run of 30-minute cells, 2 hours from 13:00 by default */
    private function cells(int $count = 2, int $week = 8, int $day = 4, int $startHour = 13): array
    {
        $cells = [];
        foreach (range(0, $count - 1) as $k) {
            $start = $startHour * 3600 + $k * 1800;
            $cells[] = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => null,
                'week_number' => $week,
                'day_of_week' => $day,
                'start_time' => date('H:i:s', $start),
                'end_time' => date('H:i:s', $start + 1800),
                'venue_id' => $this->venue->id,
                'status' => 'available',
                'version' => 1,
            ]);
        }

        return $cells;
    }

    private function request(
        string $status,
        int $cells = 2,
        int $week = 8,
        int $day = 4,
        int $startHour = 13,
        string $submittedAt = '2026-10-01 03:10:00',
    ): ReplacementRequest {
        $span = $this->cells($cells, $week, $day, $startHour);
        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => $this->session->id,
            'week_number' => 14,            // the CLASS's week
            'replacement_time_slot_id' => $span[0]->id,
            'status' => $status,
            'submitted_at' => $submittedAt,
        ]);
        foreach ($span as $cell) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $cell->id,
            ]);
        }

        return $request;
    }

    /** @return array<string, mixed> the row for this session's week-14 occurrence */
    private function row(): array
    {
        $payload = null;
        Livewire::test(ReplacementHistory::class)
            ->assertViewHas('payload', function (array $data) use (&$payload): bool {
                $payload = $data;

                return true;
            });

        $rows = array_values(array_filter(
            $payload['replacementHistory'],
            fn (array $r): bool => (string) $r['orig']['week'] === '14',
        ));

        $this->assertCount(1, $rows, 'the week-14 occurrence appears exactly once');

        return $rows[0];
    }

    /**
     * 2026-11-10 (user bug fix): the LECTURER column was printing a bare name. It now
     * carries the title too, and it comes from the ONE reader — `User::displayName()` —
     * so the page and the rest of the app cannot drift.
     */
    public function test_the_lecturer_cell_carries_the_honorific(): void
    {
        $this->lecturer->update(['honorific' => 'Pn.']);
        $this->holidayOnTheClass();

        $row = $this->row();

        $this->assertSame('Pn. Row Lecturer', $row['lecturer'], 'title + name, from User::displayName()');
        $this->assertSame($this->lecturer->fresh()->displayName(), $row['lecturer']);
    }

    /** A lecturer with no title on file still reads as the bare name — no stray space. */
    public function test_a_lecturer_without_a_title_reads_as_the_bare_name(): void
    {
        $this->holidayOnTheClass();

        $this->assertSame('Row Lecturer', $this->row()['lecturer']);
    }

    public function test_a_holiday_occurrence_is_a_row_even_with_no_request(): void
    {
        $this->holidayOnTheClass();

        $row = $this->row();

        $this->assertSame($this->session->id.':14', $row['id'], 'the OCCURRENCE is the row identity');
        $this->assertSame('none', $row['status']);
        $this->assertSame('Awaiting Arrangement', $row['statusLabel']);
        $this->assertNull($row['req'], 'nothing has been arranged');
        $this->assertNull($row['requestId'], 'and nothing to jump to');
        $this->assertSame('Public Holiday', $row['conflictKind']);
        $this->assertSame('Deepavali Holiday (In Lieu)', $row['holidayName']);
    }

    public function test_a_cancelled_class_with_no_request_is_a_row_and_never_names_the_leave(): void
    {
        ClassException::create([
            'class_session_id' => $this->session->id,
            'week_number' => 14,
            'reason' => 'medical_leave',
        ]);

        $row = $this->row();

        $this->assertSame('Class Cancelled', $row['conflictKind']);
        $this->assertSame('Awaiting Arrangement', $row['statusLabel']);
        $this->assertStringNotContainsStringIgnoringCase(
            'medical',
            (string) json_encode($row),
            'a student never sees the staff member\'s leave type',
        );
    }

    public function test_a_pending_request_prints_its_real_requested_slot_and_keeps_the_occurrence_id(): void
    {
        $this->holidayOnTheClass();
        $this->request('pending', 4);   // four half-hour cells = a 2-hour run

        $row = $this->row();

        $this->assertSame('pending', $row['status']);
        $this->assertSame('Pending — Waiting for Approval', $row['statusLabel']);
        $this->assertNotNull($row['requestId'], 'an arranged row can be jumped to');
        $this->assertStringContainsString('AWAITING', $row['statusTip'], 'the tip speaks to a student');
        $this->assertSame('2026-11-13', $row['req']['date'], 'Friday of week 8');
        $this->assertSame('13:00', $row['req']['start']);
        $this->assertSame('15:00', $row['req']['end'], 'the time string spans the WHOLE run, not just the anchor cell');
        $this->assertSame(2, $row['req']['duration']);
    }

    public function test_a_cancelled_request_leaves_the_occurrence_unarranged(): void
    {
        $this->holidayOnTheClass();
        $this->request('cancelled');

        $row = $this->row();

        $this->assertSame('none', $row['status'], 'the class is still conflicted, the request is dead');
        $this->assertSame('Awaiting Arrangement', $row['statusLabel']);
        $this->assertNull($row['req'], 'no requested slot is shown for a dead request');
        $this->assertNull($row['requestId']);
    }

    public function test_a_rejected_request_leaves_the_occurrence_unarranged_too(): void
    {
        $this->holidayOnTheClass();
        $this->request('rejected');

        $row = $this->row();

        $this->assertSame('none', $row['status']);
        $this->assertSame('Awaiting Arrangement', $row['statusLabel'], 'the rejection itself is never shown');
        $this->assertNull($row['req']);
    }

    public function test_the_latest_request_wins_when_an_occurrence_holds_two(): void
    {
        $this->holidayOnTheClass();
        // the dead one first, the live one after it
        $this->request('cancelled', 2, 8, 4, 13, '2026-10-01 03:10:00');
        $this->request('approved', 2, 9, 0, 10, '2026-10-05 03:10:00');

        $row = $this->row();

        $this->assertSame('approved', $row['status']);
        $this->assertSame('2026-11-16', $row['req']['date'], 'week 9 Monday — the newest request, not the cancelled one');
        $this->assertSame('10:00', $row['req']['start']);
    }

    public function test_completed_is_derived_from_the_make_up_date(): void
    {
        $this->holidayOnTheClass();
        $this->request('approved');

        $this->assertSame('approved', $this->row()['status'], 'the make-up is still ahead');

        // the make-up (Fri 13 Nov 2026) is now in the past
        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00:00'));
        $row = $this->row();

        $this->assertSame('completed', $row['status']);
        $this->assertStringStartsWith('Completed', $row['statusLabel']);
    }

    public function test_a_normal_occurrence_is_not_a_row(): void
    {
        $payload = null;
        Livewire::test(ReplacementHistory::class)
            ->assertViewHas('payload', function (array $data) use (&$payload): bool {
                $payload = $data;

                return true;
            });

        $this->assertSame([], $payload['replacementHistory'], 'a class that runs normally has no row at all');
    }

    public function test_the_original_slot_is_the_classes_own_week_day_time_and_room(): void
    {
        $this->holidayOnTheClass();

        $orig = $this->row()['orig'];

        $this->assertSame(14, $orig['week']);
        $this->assertSame('Thursday', $orig['day']);
        $this->assertSame('2026-12-24', $orig['date'], 'Thursday of week 14 (21 Dec 2026 + 3 days)');
        $this->assertSame('12:00', $orig['start']);
        $this->assertSame('13:00', $orig['end']);
        $this->assertSame(1, $orig['duration']);
        $this->assertSame('B010', $orig['venue']);
    }
}
