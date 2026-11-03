<?php

namespace Tests\Unit;

use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Module;
use App\Models\Programme;
use App\Services\LectureSessionCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SDD venue-restrictions-enforcement (spec R2/R3): the ONE gate's truth
 * table — `sessionAllowedForVenue`'s module-conditioned ALLOW/DISALLOW
 * matrix, fixture-identical to the live DB rooms (room codes B005/B006
 * included — the code-keyed gates). PURE unit: in-memory models, no DB —
 * the relations are set via setRelation exactly as the eager-load paths
 * hand the gate.
 *
 * Capacity is NEVER part of this gate (spec #14; the engine's check).
 */
final class SessionAllowedForVenueMatrixTest extends TestCase
{
    /** @var array<string, Module> keyed by module_code */
    private array $modules = [];

    private function module(string $code): Module
    {
        if (! isset($this->modules[$code])) {
            $this->modules[$code] = Module::factory()->make([
                'module_code' => $code,
                'module_name' => $code.' test module',
                'allowed_session_types' => 'L,T,P',
            ]);
            $this->modules[$code]->id = strlen($code) * 1000;
        }

        return $this->modules[$code];
    }

    private function cohort(string $programmeCode, int $studentCount = 10): Cohort
    {
        $programme = new Programme;
        $programme->forceFill([
            'programme_code' => $programmeCode,
            'programme_name' => $programmeCode.' test programme',
        ]);
        $programme->id = 8000 + strlen($programmeCode);

        $cohort = new Cohort;
        $cohort->forceFill([
            'programme_id' => $programme->id,
            'student_count' => $studentCount,
        ]);
        $cohort->setRelation('programme', $programme);
        $cohort->id = 9001 + crc32($programmeCode) % 100000;

        return $cohort;
    }

    public function matrixSession(string $moduleCode, string $type, array $cohortProgrammes, bool $withModule = true): ClassSession
    {
        $session = new ClassSession;
        $session->session_type = $type;

        $cohorts = array_map(fn (string $p): Cohort => $this->cohort($p), $cohortProgrammes);
        $session->setRelation('cohorts', collect($cohorts));

        if ($withModule) {
            $session->setRelation('module', $this->module($moduleCode));
        }
        // else: relation left UNLOADED — the degradation fixture (fail-closed).

        return $session;
    }

    /** Spec R3 truth table — the whole ledger, data-driven. */
    public static function matrixProvider(): array
    {
        // [id, module, type, cohort programmes, venue, raw type, withModule, expected]
        return [
            'sp1_amit2033L_B111_hall' => [1, 'AMIT2033', 'L', ['DFT'], 'B111', 'lecture_hall', true, true],
            'sp2_amit2033L_B010_lab_additive' => [2, 'AMIT2033', 'L', ['DFT'], 'B010', 'lab', true, true],
            'sp3_amit2033L_B006_widen' => [3, 'AMIT2033', 'L', ['DFT'], 'B006', 'cisco_lab', true, true],
            'sp4_amit2033L_B005_accepted_repeal' => [4, 'AMIT2033', 'L', ['DFT'], 'B005', 'lab', true, true], // SDD b005-diploma-allowed: R2 repealed
            // 2026-11-03 user ruling: BMIS2003 / AMCS1013 / BMIT1173 sit in labs
            // in the seeded timetable, so they are on the roster now (sp5/sp6/
            // sp8/sp18 flipped). BMCS2053 (sp19/sp20) is the off-roster control.
            'sp5_bmis2003L_B010_lab_additive' => [5, 'BMIS2003', 'L', ['RSD'], 'B010', 'lab', true, true],
            'sp6_bmis2003L_B006_widen' => [6, 'BMIS2003', 'L', ['RSD'], 'B006', 'cisco_lab', true, true],
            'sp7_amcs1013P_B006_base_focs' => [7, 'AMCS1013', 'P', ['DSF'], 'B006', 'cisco_lab', true, true],
            'sp8_amcs1013T_B006_widen' => [8, 'AMCS1013', 'T', ['DSF'], 'B006', 'cisco_lab', true, true],
            'sp9_rafcashcohort_B006_banned' => [9, 'AMIT2033', 'P', ['RAF'], 'B006', 'cisco_lab', true, false],
            'sp10_amit2034P_labany' => [10, 'AMIT2034', 'P', ['DSF'], 'B009', 'lab', true, true],
            'sp11_amit2034P_B005_accepted_repeal' => [11, 'AMIT2034', 'P', ['DSF'], 'B005', 'lab', true, true], // the repeal receipts
            'sp12_amit2034T_B005_accepted_repeal' => [12, 'AMIT2034', 'T', ['DSF'], 'B005', 'lab', true, true],
            'sp13_amit2034L_B111_hall' => [13, 'AMIT2034', 'L', ['DSF'], 'B111', 'lecture_hall', true, true],
            'sp14_gate_ignores_capacity' => [14, 'AMIT2033', 'L', ['DSF'], 'SML-01', 'tutorial', true, true],
            // 2026-11-03 user ruling (docs/venue-restrictions.md R3): BMIT2013
            // joins the additive lab roster — its seeded L already runs in
            // B009, so the booking rule must not contradict the timetable.
            'sp15_bmit2013L_B009_lab_additive' => [15, 'BMIT2013', 'L', ['RSD'], 'B009', 'lab', true, true],
            'sp16_bmit2013L_B006_widen' => [16, 'BMIT2013', 'L', ['RSD'], 'B006', 'cisco_lab', true, true],
            'sp17_bmit2013T_B010_lab_additive' => [17, 'BMIT2013', 'T', ['RSD'], 'B010', 'lab', true, true], // the roster covers L/T
            'sp18_bmit1173L_B009_lab_additive' => [18, 'BMIT1173', 'L', ['RSD'], 'B009', 'lab', true, true],
            'sp19_bmcs2053L_B009_denied' => [19, 'BMCS2053', 'L', ['RSD'], 'B009', 'lab', true, false], // not in the timetable's lab-L/T population
            'sp20_bmcs2053L_B006_denied' => [20, 'BMCS2053', 'L', ['RSD'], 'B006', 'cisco_lab', true, false],
        ];
    }

    #[DataProvider('matrixProvider')]
    public function test_the_matrix_truth_table(
        int $rowId,
        string $moduleCode,
        string $type,
        array $cohortProgrammes,
        string $venueCode,
        string $rawRoomType,
        bool $withModule,
        bool $expected,
    ): void {
        $session = $this->matrixSession($moduleCode, $type, $cohortProgrammes, $withModule);

        $this->assertSame(
            $expected,
            LectureSessionCatalog::sessionAllowedForVenue($type, $rawRoomType, $session, $venueCode),
            'spec fixture #'.$rowId.' failed ('.$moduleCode.' '.$type.' @ '.$venueCode.')',
        );
    }

    /** Spec #14's point, spelled out: capacity is NEVER in this gate. */
    public function test_gate_ignores_capacity(): void
    {
        // 50 students vs a 28-seat lab — the gate is still ALLOW (the engine
        // rejects at submit; the seed's own AMIT2034 L data rides this law).
        $session = $this->matrixSession('AMIT2033', 'L', ['DSF', 'DFT', 'DFT', 'DFT', 'DFT']);

        $this->assertTrue(
            LectureSessionCatalog::sessionAllowedForVenue('L', 'lab', $session, 'B010'),
        );
    }

    /** The fail-closed fixture: NO module relation → base rows + gates only. */
    public function test_unloaded_module_degrades_to_base_rows_never_widens(): void
    {
        // a networking L in a lab — widened ONLY when the module is known:
        $session = $this->matrixSession('AMIT2033', 'L', ['DFT'], false);
        $this->assertFalse(
            LectureSessionCatalog::sessionAllowedForVenue('L', 'lab', $session, 'B010'),
        );
        // the base row still admits it to the lecture hall:
        $this->assertTrue(
            LectureSessionCatalog::sessionAllowedForVenue('L', 'lecture_hall', $session, 'B111'),
        );
        // ... and B006's L/T widening + R6 can't verify identity either (deny):
        $this->assertFalse(
            LectureSessionCatalog::sessionAllowedForVenue('L', 'cisco_lab', $session, 'B006'),
        );
    }

    /** The R6 fail-closed cohort fixture: an unloaded cohorts relation denies B006. */
    public function test_unloaded_cohorts_denies_b006_population_check(): void
    {
        $session = $this->matrixSession('AMIT2033', 'P', ['DFT'], true);
        $bare = new ClassSession;
        $bare->session_type = 'P';
        $bare->setRelation('module', $this->module('AMIT2033'));
        // cohorts relation NOT loaded:

        $this->assertFalse(
            LectureSessionCatalog::sessionAllowedForVenue('P', 'cisco_lab', $bare, 'B006'),
        );
        // the SAME session, cohorts loaded → the honest base-row admit:
        $this->assertTrue(
            LectureSessionCatalog::sessionAllowedForVenue('P', 'cisco_lab', $session, 'B006'),
        );
    }

    /**
     * The type rows still bind for a subject OUTSIDE the roster's L/T
     * population. (Renamed from BMIS2003 on 2026-11-03: that module is on the
     * roster now — BMCS2053 is the control.)
     */
    public function test_off_roster_subjects_keep_their_type_rows(): void
    {
        $session = $this->matrixSession('BMCS2053', 'P', ['RSD'], true);
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'lab', $session, 'B009'));
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'cisco_lab', $session, 'B006'));
        $sessionT = $this->matrixSession('BMCS2053', 'T', ['RSD'], true);
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('T', 'tutorial', $sessionT, 'B002'));
        $this->assertFalse(LectureSessionCatalog::sessionAllowedForVenue('T', 'lab', $sessionT, 'B009'));
    }
}
