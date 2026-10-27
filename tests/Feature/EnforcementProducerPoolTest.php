<?php

namespace Tests\Feature;

use App\Livewire\VenueTimetable;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Lecturer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD venue-restrictions-enforcement — the PRODUCER POOL receipts (design
 * D3/D6; spec R4.1): the venue page's Select-a-Session pool (conflicted-only
 * ∩ venue-admitted) gains a CONFLICTED networking L on LAB venues under the
 * new module law, while non-networking L stays absent. Mirrors
 * LtVenueCapacityRuleTest's pool harness (the same conflicted-only seeding
 * class-law, 2026-10-15).
 *
 * On the DEMO deck no networking-L occurrence is conflicted (no exceptions;
 * the W8/W14 holidays miss its days) — the browser-level row is honestly
 * BLOCKED; this testing-DB fixture inserts the exception on the disposable
 * deck, exactly the pattern the house already uses.
 */
final class EnforcementProducerPoolTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private static function networkingLSession(): ClassSession
    {
        return ClassSession::query()
            ->where('session_type', 'L')
            ->whereHas('module', fn ($q) => $q->where('module_code', 'AMIT2033'))
            ->firstOrFail();
    }

    private static function nonNetworkingLSession(): ClassSession
    {
        return ClassSession::query()
            ->where('session_type', 'L')
            ->whereHas('module', fn ($q) => $q->where('module_code', 'AMSE2002'))
            ->firstOrFail();
    }

    private static function poolIdsFor(string $roomCode, ClassSession $conflicted): array
    {
        ClassException::create([
            'class_session_id' => $conflicted->id,
            'week_number' => 3,
            'reason' => 'official_event',
        ]);

        $lecturerUser = User::findOrFail(Lecturer::findOrFail((int) $conflicted->lecturer_id)->user_id);

        $component = Livewire::withQueryParams(['venue' => $roomCode])
            ->actingAs($lecturerUser)
            ->test(VenueTimetable::class);

        $pool = $component->instance()->intentSessionOptions(3);

        return collect($pool['modules'] ?? [])
            ->flatMap(fn ($m) => array_keys($m['sessions'] ?? []))
            ->map(fn ($k): string => (string) $k)
            ->all();
    }

    /** Spec row (a), POSTIVE: the conflicted networking L enters a LAB pool. */
    public function test_conflicted_networking_l_enter_lab_pool(): void
    {
        $session = self::networkingLSession();

        $ids = $this->poolIdsFor('B010', $session); // B010 = lab (cap 28)

        $this->assertContains(
            (string) $session->id,
            $ids,
            'the conflicted AMIT2033 L must enter the B010 lab pool under the additive-labs law',
        );
    }

    /** Spec row (a) negative: a non-networking stale... L stays absent. */
    public function test_conflicted_non_networking_l_stays_absent_from_lab_pool(): void
    {
        $session = self::nonNetworkingLSession();

        $ids = $this->poolIdsFor('B010', $session);

        $this->assertNotContains(
            (string) $session->id,
            $ids,
            'a non-networking L must NOT enter the lab pool',
        );
    }

    /** SDD b005-diploma-allowed: the R2 arm was repealed — B005's pool admits
     *  the same D*-cohort networking L session (the additivity composes). */
    public function test_networking_l_pool_at_b005_carries_the_session(): void
    {
        $session = self::networkingLSession();

        $ids = $this->poolIdsFor('B005', $session); // B005 = lab, the type row admits

        $this->assertContains(
            (string) $session->id,
            $ids,
            'the networking L with DFT (D*) cohorts must ENTER the B005 pool (the repeal receipt)',
        );
    }
}
