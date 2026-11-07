<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Faculty;
use App\Models\Module;
use App\Models\Programme;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SDD venue-restrictions-enforcement: the OCC-time engine receipts. The
 * gate rides `validateSlot(..., ?ClassSession $session)` (design D3) —
 * with a session the admission is the matrix read; without it the
 * historic headcount+type read stands byte-identical (the byte-compat
 * row). Users/cohorts/fixtures mirror MatrixIntersectionEngineTest's
 * setUp ( house pattern) with the REAL room codes (B005/B006 carry the
 * code-keyed gates).
 */
final class EnforcementEngineValidateSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->lecturerId = User::factory()->lecturer()->create()->id;
    }

    /** One AMIT2033 L session (DFT·2S1G1) on its own cohort. */
    private function networkingSession(string $type, array $programmeCodes = ['DFT']): ClassSession
    {
        $module = Module::query()->where('module_code', 'AMIT2033')->first();
        $this->assertNotNull($module, 'the seeded deck must carry AMIT2033');

        $cohortIds = [];
        foreach ($programmeCodes as $i => $code) {
            $cohortIds[] = Cohort::create([
                'programme_id' => Programme::query()->whereKey($this->programmeId($code))->value('id'),
                'current_year' => 1,
                'semester' => 1,
                'tutorial_group' => 9 + $i,
                'academic_year' => '2026',
                'intake' => 'March 2026',
                'student_count' => 1,
            ])->id;
        }

        $session = ClassSession::factory()->create([
            'semester_id' => $this->semesterId(),
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturerId,
            'day_of_week' => 3,
            /* 08:00–09:00 — the anchor probes sit at 14:00 (clear of the
               session's own busy cells: lecturer + cohorts) */
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'venue_id' => Venue::query()->where('room_code', 'B103')->value('id'),
            'session_type' => $type,
        ]);
        $session->cohorts()->attach($cohortIds);

        return $session->fresh(['module', 'cohorts.programme']);
    }

    /** The free replacement slot in $venueCode for W2 Wed 09:00. */
    private function slot(string $venueCode, string $type): TimeSlot
    {
        return TimeSlot::factory()->create([
            'semester_id' => $this->semesterId(),
            'week_number' => 2,
            'day_of_week' => 3,
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'venue_id' => Venue::query()->where('room_code', $venueCode)->value('id'),
            'status' => 'available',
        ]);
    }

    private function semesterId(): int
    {
        $id = DB::table('semesters')->first()?->id;

        return (int) $id;
    }

    private function programmeId(string $code): int
    {
        return Programme::query()->where('programme_code', '=', $code)->value('id')
            ?? Programme::create([
                'programme_code' => $code,
                'programme_name' => $code.' fixture programme',
                'faculty_id' => Faculty::query()->first()->id,
            ])->id;
    }

    /** The matrix rows exercised through the ONE authoritative entry. */
    public static function engineRows(): array
    {
        return [
            'sp2_networking_L_into_B010_lab' => ['AMIT2033', ['DFT'], 'L', 'B010', true],
            'sp3_networking_L_into_B006_widen' => ['AMIT2033', ['DFT'], 'L', 'B006', true],
            'sp4_networking_L_into_B005_accepted' => ['AMIT2033', ['DFT'], 'L', 'B005', true],
            // SDD b005-diploma-allowed: the R2 arm was repealed — B005 accepts
            // a D*-cohort networking L when the type row allows (the additive
            // lab widening composes to the same accept).
            // 2026-11-03 user ruling: every subject whose seeded L/T sits in a
            // lab joined the roster (BMIT2013 + the eight kept-as-printed
            // modules). BMCS2053 is the off-roster control that stays denied.
            /* 2026-11-07 user ruling (supersedes the 2026-11-03 "per-subject, not
               per-class" reading; SDD lab-access-type-aware, commit 64cf0bd): the
               roster is now TYPE-AWARE — a module may book a lab only for the
               session types that already sit in a lab in the seeded timetable.
               AMCS1013's T is in B006 but its L is in hall B111; BMIT1173's T is
               in B009 but its L is in B107. So their L is DENIED here, and these
               three rows were stale expectations left behind by that ruling. */
            'sp6_amcs1013_L_into_B006_denied' => ['AMCS1013', ['DSF'], 'L', 'B006', false],
            'sp9_amcs1013_L_into_B010_denied' => ['AMCS1013', ['DSF'], 'L', 'B010', false],
            'sp15_bmit2013_L_into_B009_lab' => ['BMIT2013', ['RSD'], 'L', 'B009', true],
            'sp18_bmit1173_L_into_B009_denied' => ['BMIT1173', ['RSD'], 'L', 'B009', false],
            'sp19_bmcs2053_L_into_B009_denied' => ['BMCS2053', ['RSD'], 'L', 'B009', false],
            'sp20_bmcs2053_L_into_B006_denied' => ['BMCS2053', ['RSD'], 'L', 'B006', false],
        ];
    }

    #[DataProvider('engineRows')]
    public function test_a_new_booking_rides_the_matrix(
        string $moduleCode,
        array $programmeCodes,
        string $type,
        string $venueCode,
        bool $expected,
    ): void {
        $session = $this->networkingSession('L', $programmeCodes);
        // the session under booking is the ONE the caller resolves — patch the
        // session's module facts to the row's module (the fixture swaps the
        // type + venue; the module stay per-row):
        $module = Module::query()->where('module_code', $moduleCode)->first();
        $this->assertNotNull($module);
        $session->module_id = $module->id;
        $session->session_type = $type;
        $session->save();
        $session->load(['module', 'cohorts.programme']);
        $slot = $this->slot($venueCode, $type);

        $engine = app(MatrixIntersectionEngine::class);
        $this->assertSame(
            $expected,
            $engine->validateSlot((int) $slot->id, (int) $session->lecturer_id, (array) $session->cohorts->pluck('id')->all(), $type, 2, 1, $session),
            'engine row: '.$moduleCode.' '.$type.' @ '.$venueCode,
        );
    }

    /** The fail-closed degrade path: an unloaded module = base rows only. */
    public function test_unloaded_relations_degrade_to_base_rows_only(): void
    {
        $session = $this->networkingSession('L');
        $slot = $this->slot('B010', 'L'); // a lab — widened ONLY when the module is known

        $bare = $session->fresh(); // module NOT loaded
        $engine = app(MatrixIntersectionEngine::class);

        $this->assertFalse(
            $engine->validateSlot((int) $slot->id, (int) $session->lecturer_id, (array) $session->cohorts->pluck('id')->all(), 'L', 2, 1, $bare),
        );
        // the action contract (T3): the loaded session admits the SAME slot
        $loaded = $session->fresh(['module', 'cohorts.programme']);
        $this->assertTrue(
            $engine->validateSlot((int) $slot->id, (int) $session->lecturer_id, (array) $session->cohorts->pluck('id')->all(), 'L', 2, 1, $loaded),
        );
    }

    /** The byte-compat row: no session passed = the historic read. */
    public function test_null_session_keeps_the_historic_headcount_type_read(): void
    {
        $session = $this->networkingSession('L');
        $slot = $this->slot('B006', 'L'); // a cisco_lab — the legacy read REJECTS it for an L...

        $this->assertFalse(
            app(MatrixIntersectionEngine::class)->validateSlot(
                (int) $slot->id,
                (int) $session->lecturer_id,
                (array) $session->cohorts->pluck('id')->all(),
                'L',
                2,
                1,
            ),
        );
        // ...same slot, LEGAL hall venue, no session → the legacy read admits:
        $slot = $this->slot('B111', 'L');
        $this->assertTrue(
            app(MatrixIntersectionEngine::class)->validateSlot(
                (int) $slot->id,
                (int) $session->lecturer_id,
                (array) $session->cohorts->pluck('id')->all(),
                'L',
                2,
                1,
            ),
        );
    }
}
