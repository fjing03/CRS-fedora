<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SDD change `wire-existing-backend` — real identity + role-aware nav in the
 * ui-nav-bar partial (frozen design §5).
 *
 * Seed precondition via the suite-proven explicit `$this->seed()` pattern
 * (run inside RefreshDatabase's per-test transaction — the `$seed = true`
 * property path is deliberately NOT used; see TimetableSeedInvariantsTest's
 * docblock for the RefreshDatabaseState pitfall).
 *
 * Pages are pinned to each actor's OWN page: negative assertions (dontSee
 * other identities / lecturer-only hrefs) are only safe there — cohort pages
 * legitimately render other lecturers' seeded sessions.
 */
final class NavIdentityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the full demo database before every test; RefreshDatabase rolls
     * the seed back with the per-test transaction.
     */
    private function seedFreshDemoDatabase(): void
    {
        // nextval() is not transactional in PostgreSQL: an earlier test in
        // this process leaves the sequence advanced after its rollback, which
        // shifts the position-identified class ids the fixture seeders pin
        // (ClassExceptionsSeeder's class_session_id 1 — FK violation).
        DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");

        $this->seed(DatabaseSeeder::class);
    }

    private function actingAsStaff(string $staffId): User
    {
        $user = User::where('role', 'lecturer')
            ->whereHas('lecturer', fn ($q) => $q->where('staff_id', $staffId))
            ->firstOrFail();

        $this->actingAs($user);

        return $user;
    }

    private function actingAsStudent(string $studentId): User
    {
        $user = User::where('role', 'student')
            ->whereHas('student', fn ($q) => $q->where('student_id', $studentId))
            ->firstOrFail();

        $this->actingAs($user);

        return $user;
    }

    public function test_lecturer_panel_shows_real_identity(): void
    {
        $this->seedFreshDemoDatabase();
        // 5425 = Pn. Surayaini Binti Basri, is_pl = true (DatabaseSeeder).
        $this->actingAsStaff('5425');

        $response = $this->get('/my-timetable-ui');
        $response->assertOk();

        $response->assertSee('Pn. Surayaini Binti Basri', false);
        $response->assertSee('5425', false);
        $response->assertSee('Lecturer (PL)', false);
        // PL nav carries the approval link; lecturer items present.
        $response->assertSee('/request-approval-ui', false);
        $response->assertSee('/venue-timetable-ui', false);

        // The mock identity must be gone from the rendered page.
        $response->assertDontSee('>5770<', false);
        $response->assertDontSee('LJZ', false);
        $response->assertDontSee('En. Lim Jia Zheng', false);
    }

    public function test_student_panel_and_student_nav(): void
    {
        $this->seedFreshDemoDatabase();
        $this->actingAsStudent('25RSD0001');

        $response = $this->get('/student-my-timetable-ui');
        $response->assertOk();

        $response->assertSee('Student 25RSD0001', false);
        $response->assertSee('25RSD0001', false);
        $response->assertSee('Student', false);

        // Positive: the student whitelist carries both student links
        // (student-nav-remove-cohort: Cohort Timetables removed from the student side).
        $response->assertSee('/student-my-timetable-ui', false);
        $response->assertSee('/replacement-history-ui', false);

        // Negative: cohort timetable page removed from the student side (nav + route).
        $response->assertDontSee('href="/cohort-timetable-ui"', false);

        // Negative: lecturer-only links absent from the student's HTML.
        $response->assertDontSee('href="/my-timetable-ui"', false);
        $response->assertDontSee('href="/venue-timetable-ui"', false);
        $response->assertDontSee('href="/replacement-home-ui"', false);
        $response->assertDontSee('href="/my-request-history-ui"', false);
        $response->assertDontSee('href="/request-approval-ui"', false);
    }

    public function test_plain_lecturer_has_no_approval_link(): void
    {
        $this->seedFreshDemoDatabase();
        // 5770 = En. Lim Jia Zheng, is_pl = false (DatabaseSeeder) — the
        // plain-lecturer case proves the approval link is PL-gated, not
        // lecturer-gated.
        $this->actingAsStaff('5770');

        $response = $this->get('/my-timetable-ui');
        $response->assertOk();

        $response->assertSee('En. Lim Jia Zheng', false);
        $response->assertSee('5770', false);
        $response->assertSee('>Lecturer<', false);
        $response->assertDontSee('Lecturer (PL)', false);
        $response->assertDontSee('href="/request-approval-ui"', false);
    }
}
