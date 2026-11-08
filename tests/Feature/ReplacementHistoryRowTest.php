<?php

namespace Tests\Feature;

use App\Livewire\ReplacementHistory;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Student-side health check S10 + S11 (2026-11-08) — the Replacement History row
 * must describe the class and the replacement truthfully.
 *
 * S10: the Original Slot is the CLASS's own occurrence — its own week, day and
 *      ../../block indices. Before this, the row reused the replacement's week and
 *      day, so a week-14 Thursday class replaced into week 8 printed
 *      "Week 8 · Fri, 13 Nov" (wrong week, wrong day, wrong date) — and the
 *      Hours summary counted the replacement's length, not the class's.
 * S11: the New Slot's time string spans the whole run of 30-minute cells. Before
 *      this it read the anchor cell alone and printed "13:00 – 13:30" for a
 *      13:00–14:00 replacement, while the duration correctly said 1 hour.
 */
final class ReplacementHistoryRowTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

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

    private ClassSession $session;

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
            'submitted_at' => '2026-11-03 03:10:00',
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
        $this->assertSame(8, $row['start'], '12:00 is the 8th half-hour block from 08:00');
        $this->assertSame(9, $row['end'], '12:00–13:00 spans blocks 8..9 (one hour)');
        $this->assertSame('12:00 – 13:00', $row['originalTime']);
        $this->assertSame('Thursday', $row['originalDay']);
        $this->assertSame('B010', $row['originalVenue']);
    }

    public function test_the_new_slot_spans_the_whole_run(): void
    {
        $row = $this->row($this->request('approved'));

        $this->assertSame(7, $row['week'], 'the row files under the replacement\'s week 8 (0-based 7)');
        $this->assertSame(4, $row['newDi'], 'Friday');
        $this->assertSame(10, $row['newStart'], '13:00 is block 10');
        $this->assertSame(11, $row['newEnd'], '13:00–14:00 spans blocks 10..11');
        $this->assertSame('13:00 – 14:00', $row['newTime'], 'the time string covers the whole run — not just the anchor cell');
    }

    public function test_a_pending_row_keeps_the_classes_slot_and_shows_no_new_slot(): void
    {
        $row = $this->row($this->request('pending'));

        $this->assertSame(13, $row['originalWeek'], 'the original slot is still the class\'s own week');
        $this->assertSame(3, $row['di']);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['newDay'], 'a pending row has no confirmed new slot');
        $this->assertNull($row['newTime']);
        $this->assertNull($row['newStart']);
        $this->assertNull($row['newEnd']);
    }
}
