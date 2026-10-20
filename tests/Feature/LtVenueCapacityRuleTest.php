<?php

namespace Tests\Feature;

use App\Livewire\VenueTimetable;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Lecturer;
use App\Models\User;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
use App\Services\MatrixIntersectionEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD lt-venue-capacity-rule (user ruling 2026-10-19) — "Lecture Hall &
 * Tutorial Rooms are allow to have L/T" — the frozen receipt suite:
 *
 * R1 (the engine law, design D2): eligibleVenues() keys the CANONICAL
 * room-type map + the capacity gate; the CSV column no longer gates.
 * R1b (the producer-pool receipt, design D6.4): the venue page's
 * Select-a-Session pool widens with the map (BOTH halves asserted — the
 * rule AND the component payload); B110's T-absence is DATA, never a law
 * pin (0 T-in-LH ever in the dataset; T-in-LH is now legal going forward).
 */
final class LtVenueCapacityRuleTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private static function vid(string $roomCode): int
    {
        return (int) Venue::where('room_code', $roomCode)->firstOrFail()->id;
    }

    /** A REAL seeded L-in-tutorial class (AMSE2003(L) @ B100 is the audit's own). */
    private static function lTutorialSession(): ClassSession
    {
        $venueId = self::vid('B100');

        return ClassSession::where('session_type', 'L')
            ->where('venue_id', $venueId)
            ->firstOrFail();
    }

    public function test_engine_admits_l_dual_but_t_tutorial_only(): void
    {
        $engine = app(MatrixIntersectionEngine::class);

        // L(20): the B002 tutorial (cap 35) AND the B110 lecture hall (cap 80)
        // admit it; the B005 lab (room_type lab) does not.
        $l = $engine->eligibleVenues(20, 'L');
        $this->assertArrayHasKey(self::vid('B002'), $l);
        $this->assertArrayHasKey(self::vid('B110'), $l);
        $this->assertArrayNotHasKey(self::vid('B005'), $l, 'R1 unchanged: labs stay P-only');

        // T(20): the T row NARROWED — tutorials only; the lecture hall stops
        // admitting T (lh-l-only-revert).
        $t = $engine->eligibleVenues(20, 'T');
        $this->assertArrayHasKey(self::vid('B002'), $t);
        $this->assertArrayNotHasKey(self::vid('B110'), $t, 'lh-l-only-revert: the hall admits L only');

        // P stays lab-only across the whole map (no B006 creep).
        $p = $engine->eligibleVenues(10, 'P');
        $this->assertArrayHasKey(self::vid('B005'), $p);
        $this->assertArrayNotHasKey(self::vid('B002'), $p);
        $this->assertArrayNotHasKey(self::vid('B110'), $p);
    }

    public function test_engine_capacity_gate_and_unknown_type_fail_properly(): void
    {
        $engine = app(MatrixIntersectionEngine::class);

        // capacity violation: 40 students don't fit ANY seeded tutorial (35).
        $this->assertArrayNotHasKey(self::vid('B002'), $engine->eligibleVenues(40, 'L'));
        // unknown session type → no venues (fail-closed, parity with D4).
        $this->assertSame([], $engine->eligibleVenues(1, 'X'));
    }

    public function test_producer_rule_says_l_in_tutorial_is_allowed(): void
    {
        $session = $this->lTutorialSession();

        $this->assertTrue(LectureSessionCatalog::sessionAllowedForVenue(
            'L', 'tutorial', $session, (string) $session->venue->room_code,
        ), 'R1b: the producer pool admits L-in-tutorial under the new law');
    }

    public function test_venue_component_pool_gains_the_l_tutorial_class(): void
    {
        // Payload receipt (D6.4's second half): act as the class's owner, mark
        // the occurrence conflicted (a ClassException week), mount the venue
        // page on that class's tutorial venue, and read the pool.
        $session = $this->lTutorialSession();
        ClassException::create(['class_session_id' => $session->id, 'week_number' => 3, 'reason' => 'official_event']);

        $lecturerUser = User::findOrFail(Lecturer::findOrFail($session->lecturer_id)->user_id);

        $component = Livewire::withQueryParams(['venue' => (string) $session->venue->room_code])
            ->actingAs($lecturerUser)
            ->test(VenueTimetable::class);

        $pool = $component->instance()->intentSessionOptions(5);
        $this->assertNotEmpty($pool['modules'], 'the conflicted session must enter the pool');

        $ids = collect($pool['modules'])->flatMap(fn ($m) => array_keys($m['sessions'] ?? []));
        $this->assertTrue($ids->contains((string) $session->id),
            'the component pool gains the L-in-tutorial class ('.($session->module?->module_code ?? '?').' at '.$session->venue->room_code.')');
    }

    public function test_b110_rejects_t_by_the_law(): void
    {
        // lh-l-only-revert (2026-10-20): the hall is L-ONLY — any T session
        // on it is REJECTED outright (the two-way: the ENGINE row already
        // rebuffs B110 for T; here the DIRECT producer rule armor: whatever
        // T session object a fixture invents, the verdict stays false).
        $venueId = self::vid('B110');
        $session = ClassSession::create([
            'module_id' => ClassSession::firstOrFail()->module_id,
            'lecturer_id' => ClassSession::firstOrFail()->lecturer_id,
            'venue_id' => $venueId,
            'session_type' => 'T',
            'day_of_week' => 2,
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
            'semester_id' => ClassSession::firstOrFail()->semester_id,
        ]);

        $this->assertFalse(LectureSessionCatalog::sessionAllowedForVenue(
            'T', 'lecture_hall', $session->fresh(), 'B110',
        ), 'T-in-lecture_hall is REJECTED by the law (lh-l-only-revert)');
    }
}
