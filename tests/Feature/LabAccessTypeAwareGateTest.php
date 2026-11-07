<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SDD `lab-access-type-aware` (user ruling 2026-11-07): the runtime receipt for
 * the type-aware lab roster — the gate run against the REAL seeded timetable,
 * not in-memory fixtures (the matrix test's job).
 *
 * The law: a listed module's L/T may book a lab only when THAT type's own
 * seeded session already sits in a lab. AMSE1003 is the cleanest witness — its
 * L is in Lecture Hall B111 and its T is in B009 (a lab) — so the L must lose
 * every lab while the T keeps them. AMCS1013 is the partial case: its T is in
 * B006, its L is not in any lab.
 */
final class LabAccessTypeAwareGateTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const LAB_CODES = ['B005', 'B006', 'B009', 'B010', 'B011'];

    private function seedDeck(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    /** @return list<ClassSession> */
    private function sessionsOf(string $moduleCode, string $type): array
    {
        return ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->whereHas('module', fn ($q) => $q->where('module_code', $moduleCode))
            ->where('session_type', $type)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function venue(string $roomCode): Venue
    {
        return Venue::query()->where('room_code', $roomCode)->firstOrFail();
    }

    /** The core receipt: one (module, type) against every seeded lab. */
    private function verdicts(string $moduleCode, string $type): array
    {
        $out = [];
        $sessions = $this->sessionsOf($moduleCode, $type);
        $this->assertNotEmpty($sessions, $moduleCode.' '.$type.' must exist in the seed');

        foreach (self::LAB_CODES as $roomCode) {
            $venue = $this->venue($roomCode);
            // one verdict per seeded session of that type; they must agree
            $perSession = array_map(
                fn (ClassSession $s): bool => LectureSessionCatalog::sessionAllowedForVenue(
                    $type,
                    (string) $venue->room_type,
                    $s,
                    $roomCode,
                ),
                $sessions,
            );
            $out[$roomCode] = $perSession;
        }

        return $out;
    }

    public function test_amse1003_lecture_loses_every_lab_while_its_tutorial_keeps_them(): void
    {
        $this->seedDeck();

        // its L sits in Lecture Hall B111 → no lab may be booked with it
        foreach ($this->verdicts('AMSE1003', 'L') as $roomCode => $perSession) {
            foreach ($perSession as $allowed) {
                $this->assertFalse($allowed, 'AMSE1003 L must NOT be allowed into '.$roomCode);
            }
        }

        // its T really runs in B009 → the roster keeps the labs for the T
        foreach ($this->verdicts('AMSE1003', 'T') as $roomCode => $perSession) {
            foreach ($perSession as $allowed) {
                $this->assertTrue($allowed, 'AMSE1003 T must keep lab access (checked '.$roomCode.')');
            }
        }

        // losing the labs must not cost the base R1 rows
        $lecture = $this->sessionsOf('AMSE1003', 'L')[0];
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('L', 'lecture_hall', $lecture, 'B111'));
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue('L', 'tutorial', $lecture, 'B014'));
    }

    public function test_the_partial_case_amcs1013_keeps_b006_for_its_tutorial_only(): void
    {
        $this->seedDeck();

        // T: in B006 in the seed → allowed, and the roster admits any lab for it
        foreach ($this->verdicts('AMCS1013', 'T') as $roomCode => $perSession) {
            foreach ($perSession as $allowed) {
                $this->assertTrue($allowed, 'AMCS1013 T must be allowed into '.$roomCode);
            }
        }

        // L: in Lecture Hall B111 → B006 (and every other lab) denies
        foreach ($this->verdicts('AMCS1013', 'L') as $roomCode => $perSession) {
            foreach ($perSession as $allowed) {
                $this->assertFalse($allowed, 'AMCS1013 L must NOT be allowed into '.$roomCode.' (the B006 resident is its T)');
            }
        }
    }

    public function test_the_six_over_grant_modules_all_deny_on_their_lecture(): void
    {
        $this->seedDeck();

        // the audit's over-grant list, 2026-11-07; after this change it must be empty
        $overGrant = ['AMSE1003', 'AMCS1043', 'BMCS3033', 'BMIT1173', 'AMCS1013', 'AMIS1003'];

        foreach ($overGrant as $code) {
            foreach ($this->verdicts($code, 'L') as $roomCode => $perSession) {
                foreach ($perSession as $allowed) {
                    $this->assertFalse($allowed, $code.' L must NOT be allowed into '.$roomCode);
                }
            }
        }

        // …and the three with a seeded lab T keep it (BMIT1173's T is in B009,
        // AMCS1043's in B011, BMCS3033's in B011, AMIS1003's in B010)
        foreach (['AMCS1043', 'BMCS3033', 'BMIT1173', 'AMIS1003'] as $code) {
            $perSession = $this->verdicts($code, 'T')['B009'];
            foreach ($perSession as $allowed) {
                $this->assertTrue($allowed, $code.' T must keep lab access');
            }
        }
    }

    public function test_the_seven_lecture_and_ten_tutorial_modules_behave_as_before(): void
    {
        $this->seedDeck();

        $lectureInLab = ['AMIT2033', 'AMIT2034', 'BMIS2003', 'BMIT1723', 'BMIT2013', 'BMIT2154', 'BMIT3084'];
        $tutorialInLab = ['AMIT2033', 'AMIT2034', 'BMIT2154', 'BMIT3084', 'AMCS1043', 'AMIS1003', 'AMSE1003', 'BMCS3033', 'BMIT1173', 'AMCS1013'];

        foreach ($lectureInLab as $code) {
            foreach ($this->verdicts($code, 'L')['B009'] as $allowed) {
                $this->assertTrue($allowed, $code.' L must keep lab access');
            }
        }

        foreach ($tutorialInLab as $code) {
            foreach ($this->verdicts($code, 'T')['B009'] as $allowed) {
                $this->assertTrue($allowed, $code.' T must keep lab access');
            }
        }

        // P is untouched by this change: the base R1 row still admits every lab
        $practical = ClassSession::query()
            ->with(['module', 'cohorts.programme'])
            ->where('session_type', 'P')
            ->firstOrFail();
        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue(
            'P',
            'lab',
            $practical,
            'B009',
        ), 'P keeps its base R1 row (labs) — untouched by the roster');
    }
}
