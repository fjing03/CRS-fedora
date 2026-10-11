<?php

namespace Tests\Feature;

use App\Livewire\ReplacementHome;
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
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * replacement-flow-wiring spec (task 2.2): the home is 100% the frozen UI
 * design — the component is a data bridge emitting the mock's
 * `conflictedClasses` row shape from real rows: FR 2.16 exceptions carry the
 * lecturer's real reason, Public Holiday from the semester calendar, Venue
 * Restriction from the live B005 × Diploma derivation. Own-records scoping
 * is server-side (RBAC never weakened).
 */
final class ReplacementHomeTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private Semester $semester;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        // Sequences are NOT transactional — TimetableWiringTest asserts literal
        // ids, so every test here starts from a pristine counter.
        foreach (['replacement_requests', 'class_exceptions', 'audit_logs'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
        }

        $this->semester = Semester::create([
            'semester_code' => '202605',
            'label' => '202605 Semester',
            'start_date' => '2026-08-31', // a Monday
            'end_date' => '2026-12-06',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        $dept = Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $rsd = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);
        $dit = Programme::create(['programme_code' => 'DIT', 'programme_name' => 'DIT', 'faculty_id' => $faculty->id]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);
        $restricted = Venue::create([
            'room_code' => 'B005', 'room_name' => 'Diploma Lab B005',
            'room_type' => 'lab', 'allowed_session_types' => 'L,T', 'capacity' => 30,
        ]);

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);
        $this->lecturerB = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer B']);
        Lecturer::create(['user_id' => $this->lecturerB->id, 'staff_id' => '6002', 'dept_id' => $dept->id, 'is_pl' => false]);

        $cohortRsd = Cohort::create([
            'programme_id' => $rsd->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 20,
        ]);
        $cohortDiploma = Cohort::create([
            'programme_id' => $dit->id, 'current_year' => 2, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2024', 'student_count' => 25,
        ]);
        $cohortRsdB = Cohort::create([
            'programme_id' => $rsd->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 18,
        ]);

        $moduleA = Module::create(['module_code' => 'BMIT1001', 'module_name' => 'Software Engineering', 'allowed_session_types' => 'L,T']);
        $moduleB = Module::create(['module_code' => 'BMIT2002', 'module_name' => 'Databases', 'allowed_session_types' => 'L,T']);

        // A's class: Monday 10:00–12:00 at B100 — conflicts only via PH/exception weeks.
        $this->createSession($this->lecturerA, $moduleA, [$cohortRsd], $this->venue);
        // A's restricted class: B005 × Diploma cohort — Venue Restriction every week.
        $this->createSession($this->lecturerA, $moduleA, [$cohortDiploma], $restricted);
        // B's class with its own PH — must never leak into A's payload.
        $this->createSession($this->lecturerB, $moduleB, [$cohortRsdB], $this->venue);

        Holiday::create(['semester_id' => $this->semester->id, 'week_number' => 5, 'day_of_week' => 1, 'label' => 'Test Holiday']);
    }

    private function createSession(User $lecturer, Module $module, array $cohorts, Venue $venue): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach(collect($cohorts)->pluck('id')->all());

        return $session;
    }

    private function componentFor(User $lecturer): Testable
    {
        return Livewire::actingAs($lecturer)->test(ReplacementHome::class);
    }

    public function test_home_injects_own_conflicted_rows_only(): void
    {
        // B has a PH on their class too — it must never leak into A's payload.
        Holiday::create(['semester_id' => $this->semester->id, 'week_number' => 6, 'day_of_week' => 1, 'label' => 'BH']);

        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $this->assertNotEmpty($payload['conflictedClasses']);
                $this->assertFalse(collect($payload['conflictedClasses'])->contains(
                    fn (array $r): bool => $r['code'] === 'BMIT2002',
                ), 'another lecturer’s rows must not leak (FR 2.11)');

                return true;
            });
    }

    public function test_home_rows_carry_the_mock_row_shape(): void
    {
        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $ph = collect($payload['conflictedClasses'])->first(
                    fn (array $r): bool => $r['conflictReason'] === 'Public Holiday',
                );
                $this->assertNotNull($ph, 'the PH week must surface as a conflicted row');

                // Mock row contract (ui-common HtmlBuilder.replacementHomeRow).
                $this->assertSame('BMIT1001', $ph['code']);
                $this->assertSame('Software Engineering', $ph['name']);
                $this->assertSame('L', $ph['type']);
                $this->assertSame('2026-09-29', $ph['date']); // week 5, day_of_week 1 (Tue)
                $this->assertSame('Tuesday', $ph['day']);
                $this->assertSame('10:00', $ph['timeStart']);
                $this->assertSame('12:00', $ph['timeEnd']);
                $this->assertSame(2, $ph['duration']);
                $this->assertSame('B100', $ph['venue']);
                $this->assertSame(20, $ph['totalStudents']);
                $this->assertSame(['RSD3 (S1) G1'], $ph['cohorts']); // the mock's spaced convention
                $this->assertSame('Lecturer A', $ph['lecturer']);

                return true;
            });
    }

    public function test_home_cancelled_week_carries_the_real_reason(): void
    {
        $session = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->where('venue_id', $this->venue->id)->firstOrFail();
        ClassException::create(['class_session_id' => $session->id, 'week_number' => 3, 'reason' => 'annual_leave']);

        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $cancelled = collect($payload['conflictedClasses'])->first(
                    fn (array $r): bool => $r['conflictReason'] === 'Annual Leave',
                );
                $this->assertNotNull($cancelled, 'FR 2.16: the enum reason surfaces as the frozen badge label');
                $this->assertSame('2026-09-15', $cancelled['date']); // week 3, day_of_week 1 (Tue)

                return true;
            });
    }

    private function createSlot(int $week, int $seq = 0): TimeSlot
    {
        // Distinct (venue, day, start) per call — the no-double-book unique index.
        $starts = ['14:00:00', '15:00:00', '16:00:00', '09:00:00', '10:00:00'];

        return TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null,
            'week_number' => $week, 'day_of_week' => 3,
            'start_time' => $starts[$seq % count($starts)],
            'end_time' => date('H:i:s', strtotime($starts[$seq % count($starts)]) + 1800),
            'venue_id' => $this->venue->id, 'status' => 'available',
        ]);
    }

    public function test_home_hides_occurrences_with_an_active_request(): void
    {
        // D1 — one active request per block occurrence: a pending (or approved)
        // request covers the occurrence, so it leaves the "needs arrangement"
        // list; a rejected/cancelled one frees it again.
        // Cover EVERY day-1 session of the lecturer — the PH row surfaces per
        // session, and the pending request must suppress each of them.
        $seq = 0;
        foreach (ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->get() as $session) {
            ReplacementRequest::create([
                'semester_id' => $this->semester->id, 'proposer_id' => $this->lecturerA->id,
                'class_session_id' => $session->id, 'week_number' => 5,
                'replacement_time_slot_id' => $this->createSlot(5, $seq++)->id,
                'status' => 'pending', 'submitted_at' => now(),
            ]);
        }

        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $this->assertFalse(collect($payload['conflictedClasses'])->contains(
                    fn (array $r): bool => $r['week'] === 5,
                ), 'a pending request covers the week-5 occurrence');

                return true;
            });

        ReplacementRequest::query()->update(['status' => 'rejected', 'decided_at' => now()]);
        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $this->assertTrue(collect($payload['conflictedClasses'])->contains(
                    fn (array $r): bool => $r['week'] === 5,
                ), 'a rejected request frees the occurrence back onto the list');

                return true;
            });
    }

    public function test_home_rows_carry_the_occurrence_identity_for_the_handoff(): void
    {
        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $row = collect($payload['conflictedClasses'])->firstOrFail();
                $session = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();

                // The D1 pair — what the handoff and the request row both key on.
                $this->assertSame((int) $session->id, $row['sessionId']);
                $this->assertSame(5, $row['week']); // the seeded PH week

                return true;
            });
    }

    public function test_home_venue_restriction_rows_every_week(): void
    {
        $this->componentFor($this->lecturerA)
            ->assertViewHas('payload', function (array $payload): bool {
                $restricted = collect($payload['conflictedClasses'])->filter(
                    fn (array $r): bool => $r['conflictReason'] === 'Venue Restriction',
                );
                $this->assertSame(13, $restricted->count(), 'B005 × Diploma conflicts every week (week 5 shows PH instead)');
                $this->assertSame('B005', $restricted->first()['venue']);

                return true;
            });
    }

    public function test_home_no_rows_when_no_conflicts(): void
    {
        // Remove the only PH so A's B100 class is clean.
        Holiday::query()->where('week_number', 5)->delete();

        Livewire::actingAs($this->lecturerB)->test(ReplacementHome::class)
            ->assertViewHas('payload', fn (array $payload): bool => $payload['conflictedClasses'] === []);
    }
}
