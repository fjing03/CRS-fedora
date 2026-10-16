<?php

namespace Tests\Feature;

use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Semester;
use App\Models\User;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * T1 contract tests (SDD arrange-intent-session-choice, design D1):
 * the shared catalog's shape is load-bearing — `sessions` MUST stay an
 * id-keyed map (courseCtx()/wanted-bind lookups at arrangement :2339/:2698)
 * with a parallel `order` array in the canonical (day_of_week, start_time,
 * id) order, and the rule map must be the frozen data table. Regresses the
 * exact failure class the rounds fought over (array-shape silent break).
 */
final class LectureSessionCatalogTest extends TestCase
{
    use DatabaseMigrations;

    private LectureSessionCatalog $catalog;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->catalog = new LectureSessionCatalog;

        $this->lecturer = User::query()
            ->whereHas('lecturer')
            ->firstOrFail();
    }

    public function test_rule_map_is_the_frozen_data_table(): void
    {
        $rule = LectureSessionCatalog::ruleMap();

        $this->assertSame(['lab', 'cisco_lab'], $rule['P'], 'R1 (venue-restrictions doc): P → labs incl. the Cisco lab');
        $this->assertSame(['tutorial'], $rule['T'], 'R1: T → tutorial rooms only');
        $this->assertSame(['lecture_hall'], $rule['L'], 'R1: L → lecture halls only');
        $this->assertSame('B005', $rule['b005_venue_code'], 'R2: the no-Diploma venue code');
        $this->assertSame('^\\s*D', $rule['b005_no_diploma_pattern'], 'R2: Diploma = D* programmes');
    }

    public function test_ordered_ids_are_day_start_id_and_the_map_is_id_keyed(): void
    {
        // Fixture: one module with deliberately out-of-day-order sessions.
        $venueLab = Venue::where('room_type', 'lab')->firstOrFail();
        $venueHall = Venue::where('room_type', 'lecture_hall')->firstOrFail();

        $later = $this->makeSession($venueLab->id, 'P', 3, '14:00:00', '16:00:00'); // Thu 14:00
        $earlier = $this->makeSession($venueHall->id, 'L', 0, '09:00:00', '10:00:00'); // Mon 09:00

        $payload = $this->catalog->payloadFor($this->lecturer->id);

        $this->assertNotEmpty($payload, 'the seeded + fixture lecturer teach at least one module');
        $module = collect($payload)->firstWhere('code', 'FIX101');
        $this->assertNotNull($module);

        $this->assertArrayHasKey($earlier->id, $module['sessions'], 'D1 (round-1 🔴2): id-keyed map — the courseCtx/wanted-bind lookups survive by key, not by ordinal');
        $this->assertSame(
            [(int) $earlier->id, (int) $later->id],
            $module['order'],
            '(day_of_week, start_time, id) — Mon 09:00 before Thu 14:00 despite fixture insert order'
        );
        $this->assertSame('09:00', $module['sessions'][(string) $earlier->id]['startTime']);
        $this->assertSame('16:00', $module['sessions'][(string) $later->id]['endTime']);
        $this->assertSame(4, $module['sessions'][(string) $later->id]['durationSlots'], '2h = 4 half-hour slots');
    }

    public function test_module_order_follows_first_occurrence_timetable_order(): void
    {
        Venue::where('room_type', 'lab')->firstOrFail();
        $venueHall = Venue::where('room_type', 'lecture_hall')->firstOrFail();

        $this->makeSession($venueHall->id, 'L', 4, '11:00:00', '12:00:00', 'FIXZZ'); // Fri (later in week)
        $this->makeSession($venueHall->id, 'L', 0, '08:00:00', '09:00:00', 'FIXAA'); // Mon (earliest)

        $payload = $this->catalog->payloadFor($this->lecturer->id);
        $codes = collect($payload)->pluck('code')->all();
        $this->assertSame(
            ['FIXAA', 'FIXZZ'],  // insertion order must NOT decide; timetable order must
            array_values(array_intersect($codes, ['FIXAA', 'FIXZZ'])),
            'Q2 (design D1): first-occurrence timetable order, not insertion order'
        );
    }

    public function test_week1_facts_render_the_frozen_label_grain(): void
    {
        $venueHall = Venue::where('room_type', 'lecture_hall')->firstOrFail();
        $session = $this->makeSession($venueHall->id, 'L', 0, '09:00:00', '10:00:00');

        $week1 = 1;
        // conflicted-only policy: make the occurrence conflicted at week1
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => $week1,
            'reason' => 'medical_leave',
        ]);
        $payload = $this->catalog->payloadFor($this->lecturer->id, $week1);

        $module = collect($payload)->firstWhere('code', 'FIX101');
        $entry = $module['sessions'][(string) $session->id];
        $this->assertTrue($entry['conflicted']);
        $this->assertSame('Cancelled — Medical Leave', $entry['conflictReason']);

        $semesterModel = Semester::active();
        $this->assertNotNull($semesterModel, 'the seeded testing semester is active');
        $semesterStart = Carbon::parse($semesterModel->start_date)->format('d M Y');
        $this->assertSame($semesterStart, $entry['date'], 'R4-conf 🟡2: the label date comes from the server, W1 Mon = semester start');
        $this->assertSame('Mon', $entry['dayAbbr']);
        $this->assertSame($week1, $entry['weekNumber']);
    }

    public function test_session_allowed_for_vehicle_venue_matrix_and_b005_diploma_rule(): void
    {
        $session = $this->makeSession(Venue::where('room_type', 'lab')->firstOrFail()->id, 'P', 0, '09:00:00', '11:00:00');

        // R1 matrix through the service (raw DB encodings):
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'lab', $session, 'B009'));
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'cisco_lab', $session, 'B006'), 'B006 hosts P today');
        $this->assertFalse(LectureSessionCatalog::sessionAllowedForVenue('P', 'lecture_hall', $session, 'B110'));
        $this->assertFalse(LectureSessionCatalog::sessionAllowedForVenue('L', 'lab', $session, 'B009'));

        // R2 through the service: the B005 Diploma cohort blocks (Bachelor passes).
        $module = Module::where('module_code', 'FIX101')->firstOrFail();
        $diplomaCohort = Cohort::whereHas('programme', fn ($q) => $q->where('programme_code', 'like', 'D%'))->with('programme')->firstOrFail();
        $session->cohorts()->syncWithoutDetaching([$diplomaCohort->id]);
        $session->load('cohorts.programme');

        $this->assertFalse(LectureSessionCatalog::sessionAllowedForVenue('P', 'lab', $session, 'B005'), 'R2: D* cohort in B005 → blocked');
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'lab', $session, 'B009'), 'R2 applies to B005 only');
    }

    /**
     * A fresh module + one session owned by the test lecturer (medium fixture).
     */
    private function makeSession(int $venueId, string $type, int $day, string $start, string $end, ?string $moduleCode = null): ClassSession
    {
        $code = $moduleCode ?? 'FIX101';
        $module = Module::firstOrCreate(
            ['module_code' => $code],
            ['module_name' => 'Fixture Module '.substr($code, 3), 'allowed_session_types' => 'L,T,P'],
        );

        $lecturerRecord = Lecturer::where('user_id', $this->lecturer->id)->firstOrFail();

        $bachelorCohort = Cohort::whereHas('programme', fn ($q) => $q->where('programme_code', 'like', 'R%'))->where('current_year', 1)->where('semester', 1)->where('tutorial_group', 1)->firstOrFail();
        $semesterModel = Semester::active();
        if ($semesterModel === null) {
            $semesterModel = Semester::active()->create(['semester_code' => '202605', 'label' => '202605 Semester', 'start_date' => '2026-09-21', 'end_date' => '2026-12-27', 'week_count' => 14]);
        }
        $session = ClassSession::create([
            'module_id' => $module->id,
            'lecturer_id' => $lecturerRecord->user_id,
            'venue_id' => $venueId,
            'session_type' => $type,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'semester_id' => (Semester::active()->id ?? Semester::orderBy('id')->value('id')),
        ]);
        $session->cohorts()->attach([$bachelorCohort->id]);
        $session->refresh();

        return $session;
    }
}
