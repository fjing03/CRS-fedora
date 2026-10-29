<?php

namespace Tests\Feature;

use App\Livewire\NavNotifications;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\User;
use App\Support\Notifications\NotificationEmitter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD notifications-real-bridge: the emitter's five events (the user's
 * ruling), the per-role link law, the lifecycle (never-delete; disabled +
 * the user's tooltip), the island's ONE-unread-total law and the reminder
 * dedupe/re-arm. All receipts run on the disposable testing DB (the user's
 * strategy B) — the demo DB only ever gets the HAND-RUN seed rows (T6).
 */
final class NotificationsBridgeTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** User 1 (Surayaini) is the class owner; user 2 (Rahmat) a DCIT PL. */
    private function coordinator(): User
    {
        return User::query()->findOrFail(1);
    }

    private function pl(): User
    {
        return User::query()
            ->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))
            ->firstOrFail();
    }

    private function seedRequest(): ReplacementRequest
    {
        return ReplacementRequest::create([
            'semester_id' => 1, 'proposer_id' => 1, 'class_session_id' => 23,
            'week_number' => 8, 'replacement_time_slot_id' => 32612,
            'status' => 'pending', 'submitted_at' => Date::now(),
        ]);
    }

    private function submitEvent(int $requestId, int $week = 8): void
    {
        app(NotificationEmitter::class)->requestSubmitted(
            User::query()->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))->get(),
            'BMSE3153',
            'P',
            'Surayaini Binti Basri',
            $requestId,
            $week,
        );
    }

    public function test_submitted_rows_land_on_every_pl_with_the_frozen_tile_and_link(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        $rows = Notification::query()->where('type', 'submitted')->get();
        $this->assertSame(2, $rows->count(), 'both DCIT PLs');
        $rows->each(function (Notification $row) use ($request): void {
            $this->assertSame($request->id, $row->request_id);
            $this->assertSame('pl', $row->role_view);
            $this->assertSame('/request-approval-ui', $row->link);
            $this->assertNull($row->read_at);
            $this->assertStringContainsString('submitted by Surayaini Binti Basri', $row->body);
        });
    }

    public function test_approved_rows_follow_the_per_role_link_law(): void
    {
        $request = $this->seedRequest();
        $owner = $this->coordinator();
        app(NotificationEmitter::class)->requestDecided(
            $owner, 'approved', 'BMSE3153', 'P', (int) $request->id, 8, 'Mohd Nur Rahmat Bin Mohd Taat'
        );

        $row = Notification::query()->where('type', 'approved')->firstOrFail();
        $this->assertSame($owner->id, $row->user_id);
        $this->assertSame('lecturer', $row->role_view);
        $this->assertSame('/my-request-history-ui', $row->link);
    }

    public function test_class_cancelled_fans_out_to_owner_students_and_dept_pls(): void
    {
        // the 5770/ABCD6767 shape: the owner's lecturer + the cohorts + the dept PLs
        $session = ClassSession::query()
            ->where('lecturer_id', 1)
            ->where('day_of_week', 3)
            ->firstOrFail();
        $studentUserIds = Student::query()
            ->whereIn('cohort_id', $session->cohorts->pluck('id'))
            ->pluck('user_id');

        $this->actingAs($owner = User::findOrFail(1));
        app(NotificationEmitter::class)->classCancelled($owner, $session, 8);

        $cancelRows = Notification::query()->where('type', 'update')->get();
        // the owner + the cohort students + the dept's PLs (Surayaini herself is
        // a PL of DCIT — her cancel row doubles as the PL fan-out; the count is
        // owner(1)+students+PLs(with overlaps deduped only by fan-out law))
        $this->assertGreaterThanOrEqual(3, $cancelRows->count());
        $this->assertTrue($cancelRows->contains(fn (Notification $r) => $r->user_id === 1 && $r->role_view === 'lecturer' && $r->link === '/cohort-timetable'), "the owner's record row links the cohort page");
        $studentRows = $cancelRows->where('role_view', 'student');
        $this->assertTrue($studentRows->count() > 0, 'the cohort students get rows');
        $studentRows->each(fn (Notification $r) => $this->assertSame('/student-my-timetable-ui', $r->link, 'a student row NEVER links the lecturer-gated cohort page'));
    }

    public function test_lifecycle_renders_disabled_with_the_user_tooltip(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);
        // the pending request gets CANCELLED (the lifecycle path)
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'cancelled']);

        $pl = $this->pl();
        $this->actingAs($pl);
        Livewire::withQueryParams([])
            ->test(NavNotifications::class, ['variant' => 'panel', 'pageKey' => 'requestApproval'])
            ->assertSee('This notification is inactive because the request was cancelled.', false)
            ->assertDontSeeHtml('href="/request-approval-ui"');
    }

    public function test_badge_law_one_unread_total_across_the_roles(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        $pl = $this->pl();
        $this->actingAs($pl);
        $component = Livewire::test(NavNotifications::class, ['variant' => 'bell', 'pageKey' => 'requestApproval']);
        $component->assertSee('>1</span>', false);
        $this->assertSame(1, Notification::query()->where('user_id', $pl->id)->whereNull('read_at')->count(), 'the bell = the mailbox unread TOTAL law (the PER-USER total, not the global)');

        Notification::query()->where('user_id', $pl->id)->update(['read_at' => now()]);
        $component2 = Livewire::test(NavNotifications::class, ['variant' => 'bell', 'pageKey' => 'requestApproval']);
        $component2->assertSeeHtml('<span class="notif-badge" id="notifBadge"', false);
        $this->assertSame(0, Notification::query()->where('user_id', $pl->id)->whereNull('read_at')->count(), 'the DB-backed read state survives');
    }

    public function test_reminder_dedupe_until_read_then_rearm(): void
    {
        $request = $this->seedRequest();
        $pls = User::query()->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))->get();
        app(NotificationEmitter::class)->pendingReminder(
            $pls, 'BMSE3153', 'P', 'Surayaini', (int) $request->id, 8
        );
        $this->assertSame(2, Notification::query()->where('type', 'awaiting')->count(), 'one row per PL');

        // the dedupe: an unread identical reminder skips
        app(NotificationEmitter::class)->pendingReminder(
            $pls, 'BMSE3153', 'P', 'Surayaini', (int) $request->id, 8
        );
        $this->assertSame(2, Notification::query()->where('type', 'awaiting')->count());

        // the re-arm: a read reminder re-offers on the next run
        Notification::query()->where('type', 'awaiting')->update(['read_at' => Date::now()]);
        app(NotificationEmitter::class)->pendingReminder(
            $pls, 'BMSE3153', 'P', 'Surayaini', (int) $request->id, 8
        );
        $this->assertSame(4, Notification::query()->where('type', 'awaiting')->count());
    }

    public function test_the_lifecycle_links_stay_alive_for_decided_statuses(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'approved']);

        $pl = $this->pl();
        $this->actingAs($pl);
        Livewire::test(NavNotifications::class, ['variant' => 'panel', 'pageKey' => 'requestApproval'])
            ->assertSeeHtml('href="/request-approval-ui"', false);
    }

    public function test_rejected_rows_reach_the_proposer_with_the_frozen_tile_and_link(): void
    {
        // the rejected event was never receipted in the apply pass — D3/D7 law
        $request = $this->seedRequest();
        $owner = $this->coordinator();
        app(NotificationEmitter::class)->requestDecided(
            $owner, 'rejected', 'BMSE3153', 'P', (int) $request->id, 8, 'Mohd Nur Rahmat Bin Mohd Taat', 'Venue unavailable'
        );

        $row = Notification::query()->where('type', 'rejected')->firstOrFail();
        $this->assertSame($owner->id, $row->user_id);
        $this->assertSame('lecturer', $row->role_view);
        $this->assertSame('/my-request-history-ui', $row->link);
        $this->assertNull($row->read_at);
        $this->assertStringContainsString('rejected by Mohd Nur Rahmat Bin Mohd Taat', $row->body);
        $this->assertStringContainsString('· Venue unavailable', $row->body, 'the mandatory reject reason lands in the body');
    }

    public function test_mark_read_endpoint_is_idempotent_and_auth_scoped(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id); // one unread row per DCIT PL (users 1 + 2)

        $mine = Notification::query()->where('user_id', 1)->where('type', 'submitted')->firstOrFail();
        $other = Notification::query()->where('user_id', 2)->where('type', 'submitted')->firstOrFail();

        // scope: another user's own row never gets touched by MY request
        $this->actingAs(User::query()->findOrFail(2));
        $this->postJson('/notifications/read', ['id' => $mine->id])->assertOk();
        $this->assertNull($mine->fresh()->read_at, 'the endpoint must never mark ANOTHER user’s row');

        $this->postJson('/notifications/read', ['id' => $other->id])->assertOk();
        $firstReadAt = $other->fresh()->read_at;
        $this->assertNotNull($firstReadAt);

        // idempotency: a second POST is a NO-OP (whereNull(read_at) guard) —
        // read_at must NOT advance
        Date::setTestNow(Date::now()->addMinutes(5));
        $this->postJson('/notifications/read', ['id' => $other->id])->assertOk();
        $this->assertTrue($other->fresh()->read_at->equalTo($firstReadAt), 'the second mark-read must not bump read_at');

        // scope: an unknown id stays a harmless ok (the update matches nothing)
        $this->postJson('/notifications/read', ['id' => 999999])->assertOk();

        // a guest gets no bypass — the auth middleware bounces them to login
        $this->app['auth']->guard('web')->forgetUser();
        $this->from('/my-timetable-ui')->post('/notifications/read', ['id' => $other->id])
            ->assertRedirect();
    }

    public function test_the_island_renders_with_exactly_one_replacement_requests_query(): void
    {
        // D4's batch-resolve N+1 law (round-2 🟡): ONE whereIn on
        // replacement_requests per island render — never a per-row find() (+
        // the PI re-verify receipt: the old ->with('request') eager load was a
        // SECOND whereIn and dead work; dropped).
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        $replacementQueries = [];
        \DB::listen(function ($q) use (&$replacementQueries): void {
            if (str_contains($q->sql, 'replacement_requests')) {
                $replacementQueries[] = $q->sql;
            }
        });

        $this->actingAs($this->pl());
        Livewire::test(NavNotifications::class, ['variant' => 'panel', 'pageKey' => 'requestApproval']);

        $this->assertCount(1, $replacementQueries, 'exactly ONE replacement_requests query per render (the batch whereIn), got: '.implode(' | ', $replacementQueries));
        $this->assertStringContainsString(' in (', $replacementQueries[0], 'the ONE query is the batch whereIn');
    }

    /** The AD-2 mapping receipt (SDD notif-ad2-pagekey-tab): the island's
     *  camel pageKey match maps the row filter per PAGE, exactly the
     *  retired NOTIF_ROLE_BY_PAGE law. One user, one row per role_view. */
    public function test_the_page_keys_map_the_right_tab_rows(): void
    {
        Notification::create([
            'user_id' => 1, 'role_view' => 'pl', 'type' => 'submitted',
            'title' => 'PL tab row', 'body' => 'pl', 'link' => '/request-approval-ui', 'week' => 8,
        ]);
        Notification::create([
            'user_id' => 1, 'role_view' => 'lecturer', 'type' => 'approved',
            'title' => 'lecturer tab row', 'body' => 'lecturer', 'link' => '/my-request-history-ui', 'week' => 8,
        ]);
        Notification::create([
            'user_id' => 1, 'role_view' => 'student', 'type' => 'update',
            'title' => 'student tab row', 'body' => 'student', 'link' => '/student-my-timetable-ui', 'week' => 8,
        ]);
        $this->actingAs(User::query()->findOrFail(1));

        // the three mapped keys
        Livewire::test(NavNotifications::class, ['variant' => 'panel', 'pageKey' => 'requestApproval'])
            ->assertSee('PL tab row', false)
            ->assertDontSee('lecturer tab row', false)
            ->assertDontSee('student tab row', false);
        foreach (['studentMyTimetable', 'replacementHistory'] as $studentKey) {
            Livewire::test(NavNotifications::class, ['variant' => 'panel', 'pageKey' => $studentKey])
                ->assertSee('student tab row', false)
                ->assertDontSee('PL tab row', false);
        }

        // every unmapped camel key + the empty default → the lecturer tab
        foreach (['myTimetable', 'cohortTimetable', 'venueTimetable', 'myRequestHistory', 'replacementHome', 'replacementArrangement', ''] as $lecturerKey) {
            Livewire::test(NavNotifications::class, ['variant' => 'panel', 'pageKey' => $lecturerKey])
                ->assertSee('lecturer tab row', false)
                ->assertDontSee('PL tab row', false);
        }
    }
}
