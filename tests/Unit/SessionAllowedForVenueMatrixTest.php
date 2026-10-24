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
            'sp4_amit2033L_B005_dstar_denied' => [4, 'AMIT2033', 'L', ['DFT'], 'B005', 'lab', true, false],
            'sp5_bmis2003L_B010_denied' => [5, 'BMIS2003', 'L', ['RSD'], 'B010', 'lab', true, false],
            'sp6_bmis2003L_B006_denied' => [6, 'BMIS2003', 'L', ['RSD'], 'B006', 'cisco_lab', true, false],
            'sp7_amcs1013P_B006_base_focs' => [7, 'AMCS1013', 'P', ['DSF'], 'B006', 'cisco_lab', true, true],
            'sp8_amcs1013T_B006_denied' => [8, 'AMCS1013', 'T', ['DSF'], 'B006', 'cisco_lab', true, false],
            'sp9_rafcashcohort_B006_banned' => [9, 'AMIT2033', 'P', ['RAF'], 'B006', 'cisco_lab', true, false],
            'sp10_amit2034P_labany' => [10, 'AMIT2034', 'P', ['DSF'], 'B009', 'lab', true, true],
            'sp11_amit2034P_B005_r2_denial' => [11, 'AMIT2034', 'P', ['DSF'], 'B005', 'lab', true, false],
            'sp12_amit2034T_B005_r2_denial' => [12, 'AMIT2034', 'T', ['DSF'], 'B005', 'lab', true, false],
            'sp13_amit2034L_B111_hall' => [13, 'AMIT2034', 'L', ['DSF'], 'B111', 'lecture_hall', true, true],
            'sp14_gate_ignores_capacity' => [14, 'AMIT2033', 'L', ['DSF'], 'SML-01', 'tutorial', true, true],
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

    /** The non-networking keep-rules: BMIS2003's L sits nowhere in labs. */
    public function test_non_networking_type_rows_untouched(): void
    {
        $session = $this->matrixSession('BMIS2003', 'P', ['RSD'], true);
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'lab', $session, 'B009'));
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('P', 'cisco_lab', $session, 'B006'));
        $sessionT = $this->matrixSession('BMIS2003', 'T', ['RSD'], true);
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('T', 'tutorial', $sessionT, 'B002'));
        $this->assertFalse(LectureSessionCatalog::sessionAllowedForVenue('T', 'lab', $sessionT, 'B009'));
    }
}
