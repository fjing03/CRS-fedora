<?php

namespace Tests\Feature;

use App\Livewire\NavNotifications;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Holiday;
use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\Notifications\NotificationEmitter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

    private function seedRequest(int $proposerId = 1, int $sessionId = 23, int $week = 8): ReplacementRequest
    {
        return ReplacementRequest::create([
            'semester_id' => 1, 'proposer_id' => $proposerId, 'class_session_id' => $sessionId,
            'week_number' => $week, 'replacement_time_slot_id' => 32612,
            'status' => 'pending', 'submitted_at' => Date::now(),
        ]);
    }

    /** Staff 5770 Lim Jia Zheng — the non-PL proposer in the R1/R5 receipts. */
    private function nonPl(): User
    {
        return User::query()
            ->whereHas('lecturer', fn ($q) => $q->where('staff_id', '5770'))
            ->firstOrFail();
    }

    private function pls(): Collection
    {
        return User::query()->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))->get();
    }

    /**
     * Every R5 subtitle span in order — counted on the SPANS, never on the raw
     * page text: a row's tooltip legitimately repeats the name (`Staff (Proposer): … (5770)`).
     *
     * @return array<int, string>
     */
    private function subtitleSpans(string $html): array
    {
        preg_match_all('/<span class="notif-row-subtitle">([^<]*)<\/span>/', $html, $m);

        return $m[1];
    }

    private function submitEvent(int $requestId, int $week = 8): void
    {
        app(NotificationEmitter::class)->requestSubmitted(
            $this->coordinator(),   // user 1 IS a PL → R1 adds no second row
            $this->pls(),
            'BMSE3153',
            'P',
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
        // R3 (notif-recipient-rulings): ONE merged row for the owner, pointing at
        // the timetable that changed and carrying BOTH ids.
        $request = $this->seedRequest();
        $owner = $this->coordinator();
        $session = ClassSession::query()->findOrFail(23);

        app(NotificationEmitter::class)->replacementConfirmed(
            $owner, $session, (int) $request->id, 8, 'Mohd Nur Rahmat Bin Mohd Taat'
        );

        $this->assertSame(1, Notification::query()->where('type', 'approved')->count(), 'exactly ONE merged row — never one per cohort');
        $row = Notification::query()->where('type', 'approved')->firstOrFail();
        $this->assertSame($owner->id, $row->user_id);
        $this->assertSame('lecturer', $row->role_view);
        /* notif-click-behaviour (user, 2026-11-04): class news for the LECTURER
           now points at the merged page — the row it is about lives there, so the
           click can flash that row instead of a timetable cell. */
        $this->assertSame('/my-replacements', $row->link);
        $this->assertSame('Replacement class updated', $row->title);
        $this->assertSame((int) $request->id, $row->request_id);
        $this->assertSame((int) $session->id, $row->class_session_id);
        $this->assertStringContainsString('approved by Mohd Nur Rahmat Bin Mohd Taat', $row->body);
        $this->assertStringContainsString('your timetable is updated', $row->body);
    }

    public function test_class_cancelled_fans_out_to_owner_and_students(): void
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
        $this->assertTrue($cancelRows->contains(fn (Notification $r) => $r->user_id === 1 && $r->role_view === 'lecturer' && $r->link === '/my-replacements'), "the owner's record row links the merged page (notif-click-behaviour, 2026-11-04) — the cancelled class's own row, flashed on arrival");
        $studentRows = $cancelRows->where('role_view', 'student');
        $this->assertTrue($studentRows->count() > 0, 'the cohort students get rows');
        $studentRows->each(fn (Notification $r) => $this->assertSame('/student-my-timetable-ui', $r->link, 'a student row NEVER links the lecturer-gated cohort page'));

        // R4 (notif-recipient-rulings, 2026-11-03) REVERSES the 2026-10-30
        // three-fan-out ruling: the department-PL copies are gone entirely, so
        // the OTHER dept PL (user 2) must hold NO row for this cancellation.
        $this->assertSame(0, $cancelRows->where('role_view', 'pl')->count(), 'NO PL gets a cancellation row any more — not even the owner department');
        $this->assertFalse($cancelRows->contains(fn (Notification $r) => $r->user_id === 2), 'user 2 (the other DCIT PL) is absent');
    }

    /**
     * 2026-11-04 (the user's live report): staff 5425 — a PL — held a
     * "Scheduled class cancelled" row for staff 5770's class. The RULE is R4
     * (owner + cohort students only, 2026-11-03); that row was pre-R4 legacy
     * data, and rows are never deleted, so it has to be dropped at READ time.
     *
     * This pins the guard: a viewer who does not own the class never sees the
     * row (inbox, unread view or badge), while the owner's own row and the
     * cohort students' rows survive untouched.
     */
    public function test_a_pl_never_sees_another_lecturers_cancellation(): void
    {
        $viewer = $this->coordinator();                 // user 1 — the PL from the report
        $foreignSession = ClassSession::query()
            ->where('lecturer_id', '!=', (int) $viewer->id)
            ->firstOrFail();                            // a class that is NOT theirs
        $ownSession = ClassSession::query()
            ->where('lecturer_id', (int) $viewer->id)
            ->firstOrFail();

        // the legacy shape exactly: a department-PL copy (role_view 'pl')
        Notification::create([
            'user_id' => (int) $viewer->id, 'role_view' => 'pl', 'type' => 'update',
            'title' => 'Scheduled class cancelled',
            'body' => 'LEGACYFOREIGN (L) · W7 · Tue · B009 · cancelled',
            'link' => '/my-timetable-ui', 'request_id' => null,
            'class_session_id' => (int) $foreignSession->id, 'week' => 7,
        ]);
        // the cancellation that made these rows is LIVE (the CancelClass action
        // always writes the exception first) — without it the row is an
        // UNDONE-cancellation (2026-11-05) and correctly renders inactive
        ClassException::create([
            'class_session_id' => (int) $ownSession->id,
            'week_number' => 7,
            'reason' => 'medical_leave',
        ]);

        // the viewer's OWN cancellation — this one must stay
        Notification::create([
            'user_id' => (int) $viewer->id, 'role_view' => 'lecturer', 'type' => 'update',
            'title' => 'Scheduled class cancelled',
            'body' => 'OWNCLASS (L) · W7 · Tue · B010 · cancelled',
            'link' => '/my-replacements', 'request_id' => null,
            'class_session_id' => (int) $ownSession->id, 'week' => 7,
        ]);

        $this->actingAs($viewer);
        $panel = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'myTimetable', 'unreadOnly' => false, 'activeOnly' => false])
            ->assertDontSee('LEGACYFOREIGN', false)
            ->assertSee('OWNCLASS', false)
            ->assertViewHas('unreadTotal', 1);   // the foreign UNREAD row never counts

        // the unread view (the badge's own predicate) hides nothing extra
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'myTimetable'])
            ->assertDontSee('LEGACYFOREIGN', false)
            ->assertSee('OWNCLASS', false);

        // and the student half is untouched: a cohort student's own row renders
        $student = $foreignSession->cohorts
            ->flatMap(fn ($c) => $c->students()->with('user')->get())
            ->first()?->user;
        $this->assertNotNull($student);

        Notification::create([
            'user_id' => (int) $student->id, 'role_view' => 'student', 'type' => 'update',
            'title' => 'Scheduled class cancelled',
            'body' => 'STUDENTCOPY (L) · W7 · Tue · B009 · cancelled',
            'link' => '/student-my-timetable-ui', 'request_id' => null,
            'class_session_id' => (int) $foreignSession->id, 'week' => 7,
        ]);

        $this->actingAs($student);
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'studentTimetable', 'unreadOnly' => false, 'activeOnly' => false])
            ->assertSee('STUDENTCOPY', false);
    }

    public function test_lifecycle_renders_disabled_with_the_user_tooltip(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);
        // the pending request gets CANCELLED (the lifecycle path)
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'cancelled']);

        $pl = $this->pl();
        $this->actingAs($pl);
        // SDD notif-new-request-ux D5: inactive ⇒ the READ category —
        // never in the unread filter, so the D1 row shows under unread-only=false.
        Livewire::withQueryParams([])
            ->test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])
            ->assertSee('This notification is inactive because the request was cancelled by the requester.', false)
            ->assertDontSeeHtml('href="/request-approval-ui"');
        // the unread-only default view hides it entirely (the actionable law)
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval'])
            ->assertDontSee('This notification is inactive', false);
    }

    public function test_badge_law_one_unread_total_across_the_roles(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        $pl = $this->pl();
        $this->actingAs($pl);
        $component = Livewire::test(NavNotifications::class, ['island' => 'bell', 'pageKey' => 'requestApproval']);
        $component->assertSee('>1</span>', false);
        $this->assertSame(1, Notification::query()->where('user_id', $pl->id)->whereNull('read_at')->count(), 'the bell = the mailbox unread TOTAL law (the PER-USER total, not the global)');

        Notification::query()->where('user_id', $pl->id)->update(['read_at' => now()]);
        $component2 = Livewire::test(NavNotifications::class, ['island' => 'bell', 'pageKey' => 'requestApproval']);
        $component2->assertSeeHtml('<span class="notif-badge" id="notifBadge"', false);
        $this->assertSame(0, Notification::query()->where('user_id', $pl->id)->whereNull('read_at')->count(), 'the DB-backed read state survives');
    }

    /**
     * 2026-11-04 regression — the row-click contract (D4 + D3) lives on the
     * anchor's `data-id`, and the click-highlight handoff on `data-notif-target`.
     * Both were silently suppressed 2026-11-02 → 2026-11-04: the island's
     * public `$variant` property was INHERITED by `partials/notif-row` through
     * `@include` (Blade shares the parent scope), so the partial's
     * `$variant === null` guard never passed and every inbox row rendered
     * without the hooks — clicking navigated but never marked read, and never
     * flashed the record. Pinned here so the trap cannot return unnoticed.
     */
    public function test_active_inbox_rows_carry_the_mark_read_and_highlight_hooks(): void
    {
        $request = $this->seedRequest();
        $pl = $this->pl();
        $this->actingAs($pl);
        $this->submitEvent($request->id);

        $row = Notification::query()
            ->where('user_id', $pl->id)
            ->where('type', 'submitted')
            ->firstOrFail();

        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval'])
            ->assertSeeHtml('data-id="'.$row->id.'"', false)
            ->assertSeeHtml('data-notif-target="r'.$request->id.'"', false)
            ->assertSeeHtml('href="/request-approval-ui"', false);

        /* The other direction of the same law: an inactive row must carry
           NEITHER hook — the durable POST and the anchor are for clickable
           rows only (the D15 disabled branch). */
        $request->update(['status' => 'cancelled']);
        $html = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])->html();

        $this->assertStringNotContainsString('data-id="'.$row->id.'"', $html, 'an inactive row is never click-markable');
        $this->assertStringNotContainsString('data-notif-target="r'.$request->id.'"', $html);
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
        // SDD notif-new-request-ux D3/D4 amendment: the real-bridge links-stay-alive
        // law is OVERTURNED for `submitted` rows — an approved request's row
        // renders INACTIVE (no href, the D15-approved reason tip; unread ⇒
        // excluded from the badge/pill). The actionable-unread law.
        $request = $this->seedRequest();
        $this->submitEvent($request->id);
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'approved']);

        $pl = $this->pl();
        $this->actingAs($pl);
        $component = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false]);
        $component
            ->assertSee('This notification is inactive because the request was approved.', false)
            ->assertDontSeeHtml('href="/request-approval-ui"');
        $this->assertSame(0, $component->viewData('unreadTotal'), 'the approved-submitted row is no longer actionable');
        $component->assertSeeHtml('notif-row--inactive');
    }

    /** SDD notif-new-request-ux: the composer receipts (the T6 NEW block). */
    public function test_submitted_row_renders_the_ux_composition(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        $pl = $this->pl();
        $this->actingAs($pl);
        $component = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false]);
        $html = $component->html();

        // 1) the title-with-ID — Title Case (user spec 2026-11-04 §1)
        $this->assertStringContainsString('New Replacement Request (ID #'.(int) $request->id.')', $html);
        // 2) the subtitle: the full name + the staff id
        $this->assertStringContainsString('Surayaini Binti Basri (', $html);
        // 3) the desc: the week/day/time/venue at a glance — the RUNTIME truth
        //    of the request's anchor slot (seed 32612 = W6 Fri 13:30–14:00 B010)
        $this->assertStringContainsString('Week 6 · Friday · 13:30–14:00 · B010', $html);
        // 4) the tip's fact lines, in the spec's order and format (§2/§3):
        //    Staff (Proposer) / Requested At / Subject & Cohort / Conflict
        //    Reason (absent here — no exception row) / Original / Proposed
        $this->assertStringContainsString('Staff (Proposer): Surayaini Binti Basri (5425)', $html);
        $this->assertStringContainsString('Requested At: ', $html);
        $this->assertStringContainsString('Subject &amp; Cohort: BMSE3153 (', $html);
        $this->assertStringContainsString(' · RSD3', $html);   /* deduped: one label, not two */
        // the ORIGINAL reads the class's own week (8 here), the PROPOSED the
        // request's slot week (6) — two independent reads, never a copy
        $this->assertStringContainsString('Original Class Schedule: Week 8 · ', $html);
        $this->assertStringContainsString(' @ B010', $html);
        $this->assertStringContainsString('Proposed Replacement Schedule: Week 6 · ', $html);
        // the old formats are gone (one format, never two)
        $this->assertStringNotContainsString('Proposed Venue: ', $html);
        $this->assertStringNotContainsString('Requested at: ', $html);
        // the badge = the actionable unread (the submitted-pending = unread ⇒ 1)
        $this->assertSame(1, $component->viewData('unreadTotal'));
    }

    /** 2026-11-06 (user UI update, options (a)/(G6)): the conflict reason no longer
     *  has its own line — it rides the ORIGINAL schedule line's parentheses, in the
     *  `ReasonLabel` vocabulary, and a public holiday WINS over the stamped
     *  exception. No row at all → no parentheses, never an invented reason. */
    public function test_the_original_line_carries_the_reason_in_parentheses(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);
        $this->actingAs($this->pl());

        $html = fn () => Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false,
        ])->html();

        $this->assertStringNotContainsString('Conflict Reason:', $html(), 'the separate line is gone');
        $this->assertStringNotContainsString('(Medical/Sick Leave)', $html(), 'no exception row → no reason');

        ClassException::create([
            'class_session_id' => (int) $request->class_session_id,
            'week_number' => 8,
            'reason' => 'medical_leave',
        ]);

        $this->assertStringContainsString('(Medical/Sick Leave)', $html(), 'the ONE label law (ReasonLabel) owns the reason text');

        /* the holiday WINS (user option A): the approval flow stamps
           `official_event`, but the calendar knows the day */
        Holiday::create([
            'semester_id' => (int) $request->semester_id,
            'week_number' => 8,
            'day_of_week' => 1,
            'label' => 'Fixture Holiday',
        ]);

        $this->assertStringContainsString('(Public Holiday — Fixture Holiday)', $html());
        $this->assertStringNotContainsString('(Official Event)', $html());
    }

    /**
     * 2026-11-05 (the user's bug report, option B): a "Scheduled class cancelled"
     * notice whose cancellation was UNDONE is a dead click — the class is not on
     * the merged page any more. The row must derive INACTIVE at read time (no
     * data edit), on BOTH resolveRequests exits: the request-bearing mailbox and
     * the class-only inbox (a student's whole feed).
     */
    public function test_cancel_notice_goes_inactive_when_the_cancellation_is_undone(): void
    {
        $session = ClassSession::query()
            ->where('lecturer_id', 1)
            ->where('day_of_week', 3)
            ->firstOrFail();
        $owner = User::findOrFail(1);

        $this->actingAs($owner);
        $exception = ClassException::create([
            'class_session_id' => (int) $session->id,
            'week_number' => 8,
            'reason' => 'medical_leave',
        ]);
        app(NotificationEmitter::class)->classCancelled($owner, $session, 8);

        $ownerRow = fn () => Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false,
        ])->html();

        // the class IS cancelled → the notice is a live anchor with its target
        $html = $ownerRow();
        $this->assertStringContainsString('data-notif-target="o'.(int) $session->id.'"', $html);
        $this->assertStringNotContainsString('because the cancellation was undone', $html);

        // the UNDO lands: the exception row is deleted, the notification is NOT
        $exception->delete();

        $html = $ownerRow();
        $this->assertStringContainsString('This notification is inactive because the cancellation was undone.', $html);
        $this->assertStringNotContainsString('data-notif-target="o'.(int) $session->id.'"', $html, 'an undone cancellation keeps NO target');

        // the CLASS-ONLY exit: a student's whole feed is these rows, and their
        // badges must stop counting a cancellation that no longer exists
        $student = Student::query()
            ->whereIn('cohort_id', $session->cohorts->pluck('id'))
            ->whereNotNull('user_id')
            ->firstOrFail()
            ->user;
        $this->assertNotNull($student);
        $this->actingAs($student);
        $studentComponent = Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false,
        ]);
        $this->assertStringContainsString('because the cancellation was undone', $studentComponent->html());
        $this->assertSame(0, $studentComponent->viewData('unreadTotal'), 'a stale cancellation must not sit in the badge');
    }

    /**
     * 2026-11-05 (the user's bug report, option B): request #24 was rejected, then
     * RE-PROPOSED as #26 on the same class+week. The row now belongs to #26, so the
     * old notice's id can never match — it must derive INACTIVE (and a decision row
     * on the NEWEST request must stay live).
     */
    public function test_decision_notice_goes_inactive_when_a_newer_request_replaced_it(): void
    {
        $owner = User::findOrFail(1);
        $old = $this->seedRequest();
        ReplacementRequest::query()->whereKey($old->id)->update(['status' => 'rejected']);

        $this->actingAs($owner);
        app(NotificationEmitter::class)->requestDecided(
            $owner, 'rejected', 'BMSE3153', 'P', (int) $old->id, 8, 'Surayaini', 'Venue unavailable',
        );

        $render = fn () => Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false,
        ])->html();

        // no newer request yet → the decision row is a live record
        $this->assertStringNotContainsString('because a newer request replaced this one', $render());

        // the RE-PROPOSAL: a newer request on the same class + week
        $newer = $this->seedRequest();
        ReplacementRequest::query()->whereKey($newer->id)->update(['status' => 'approved']);
        app(NotificationEmitter::class)->requestDecided(
            $owner, 'approved', 'BMSE3153', 'P', (int) $newer->id, 8, 'Surayaini',
        );

        $html = $render();
        $this->assertStringContainsString('This notification is inactive because a newer request replaced this one.', $html);
        $this->assertStringNotContainsString('data-notif-target="r'.(int) $old->id.'"', $html, 'the superseded request keeps NO target');
        // …and the NEWEST request's own decision row is untouched
        $this->assertStringContainsString('data-notif-target="r'.(int) $newer->id.'"', $html);
    }

    /** SDD notif-new-request-ux: the fail-open matrix (D6). */
    public function test_submitted_row_fail_open_matrix(): void
    {
        // 1) the missing-proposer shape: the notification's request row instead of
        //    the eager family — the submit event stamps request_id on the row, so
        //    resolve is fine; the fail-open = the request itself missing.
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        // (a) the DECIDED-AWAITING side-effect (the deliberate D2): an awaiting
        // reminder of a later-APPROVED request renders inactive + the D15-approved.
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'approved']);
        $pls = User::query()->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))->get();
        app(NotificationEmitter::class)->pendingReminder(
            $pls, 'BMSE3153', 'P', 'Surayaini', (int) $request->id, 8
        );
        $pl = $this->pl();
        $this->actingAs($pl);
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])
            ->assertSee('This notification is inactive because the request was approved.', false);

        // (b) the 2026-11-04 ruling: a READ submitted row whose request is
        //     STILL pending stays ACTIVE — clickable (data-id + the highlight
        //     target), composed (the title keeps the ID), and never carrying
        //     the retired read sentence. Only the row's LIFE changed: the badge
        //     still stops counting it (D5's predicate reads read_at) and the
        //     hide gate still allows it, so neither needed touching.
        // a fresh pending request on a DIFFERENT occurrence (the active-block
        // unique law: one pending per class×week) — anchor slot = W3's Thursday
        $anchorSlot = TimeSlot::query()->where('week_number', 3)->where('day_of_week', 3)
            ->where('status', 'available')->firstOrFail();
        $fresh = ReplacementRequest::create([
            'semester_id' => 1, 'proposer_id' => 1, 'class_session_id' => 67,
            'week_number' => 3, 'replacement_time_slot_id' => (int) $anchorSlot->id,
            'status' => 'pending', 'submitted_at' => Date::now(),
        ]);
        app(NotificationEmitter::class)->requestSubmitted(
            $this->coordinator(),   // a PL proposer → no extra confirmation row
            $this->pls(),
            'AMSE2003', 'L', (int) $fresh->id, 3
        );
        Notification::query()->where('user_id', $pl->id)
            ->where('type', 'submitted')->where('request_id', (int) $fresh->id)
            ->update(['read_at' => Date::now()]);

        $readRow = Notification::query()->where('user_id', $pl->id)
            ->where('type', 'submitted')->where('request_id', (int) $fresh->id)
            ->firstOrFail();
        $this->assertNotNull($readRow->read_at, 'the receipt row really is read');

        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])
            ->assertDontSee('This notification is inactive because you have already read this request.', false)
            ->assertSeeHtml('data-id="'.$readRow->id.'"', false)
            ->assertSeeHtml('data-notif-target="r'.$fresh->id.'"', false)
            ->assertSee('New Replacement Request (ID #'.$fresh->id.')', false);

        // "Active only" = not yet decided: the read-but-pending row survives it
        // (the same row is shown), while a decided row is filtered out.
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => true])
            ->assertSeeHtml('data-id="'.$readRow->id.'"', false)
            ->assertDontSee('This notification is inactive because the request was approved.', false);
        // (c) the missing-row edge = defensive ONLY — the FK nulls request_id
        //     on delete, so real writes never reach the branch; asserted here
        //     documentarily via the classifier's shape (no test can write it).
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
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval']);

        $this->assertCount(1, $replacementQueries, 'exactly ONE replacement_requests query per render (the batch whereIn), got: '.implode(' | ', $replacementQueries));
        $this->assertStringContainsString(' in (', $replacementQueries[0], 'the ONE query is the batch whereIn');
    }

    /** The unified-feed receipt (SDD notif-unified-feed — the 2026-10-29
     *  one-feed ruling, superseding the AD-2 page-category tabs): the same
     *  recipient sees the SAME full row set on every page, whatever the
     *  pageKey. One user, one row per role_view. */
    public function test_the_panel_shows_one_unified_feed_for_the_recipient(): void
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

        // the SAME full feed on every page shape — pl rows, lecturer rows and
        // student rows ALL render, whatever page key the mount receives
        foreach (['requestApproval', 'myTimetable', 'studentMyTimetable', 'replacementHistory', ''] as $pageKey) {
            Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => $pageKey])
                ->assertSee('PL tab row', false)
                ->assertSee('lecturer tab row', false)
                ->assertSee('student tab row', false);
        }

        // recipient scoping: nobody else's rows ever appear
        Notification::create([
            'user_id' => 2, 'role_view' => 'pl', 'type' => 'submitted',
            'title' => 'other user row', 'body' => 'other', 'link' => '/request-approval-ui', 'week' => 8,
        ]);
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval'])
            ->assertSee('PL tab row', false)
            ->assertDontSee('other user row', false);
    }

    /** The 2026-11-01 UI request (SDD-waived): the Active-only toggle + the
     *  type dropdown — server state, composable with unread-only, and the
     *  badge NEVER moves (the D5/F-2 law). */
    public function test_panel_view_filters_active_only_and_type(): void
    {
        // the island renders the AUTHENTICATED user's mailbox (render() reads
        // Auth::id()) — one-feed scoping applies to the filters too
        $this->actingAs($this->coordinator());
        $request = $this->seedRequest();
        $this->submitEvent($request->id);

        // the actor's 'update' record row — the same 5770 shape the cancel
        // test uses (cs 23's lecturer is NOT necessarily user 1's own class)
        $session = ClassSession::query()
            ->where('lecturer_id', 1)
            ->where('day_of_week', 3)
            ->firstOrFail();
        ClassException::create([      // the live cancellation to match the notice
            'class_session_id' => (int) $session->id,
            'week_number' => 8,
            'reason' => 'medical_leave',
        ]);
        app(NotificationEmitter::class)->classCancelled(
            $this->coordinator(), $session, 8
        );

        $view = fn (array $extra = []) => Livewire::test(NavNotifications::class, array_merge(
            ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false],
            $extra,
        ));

        // type dropdown: only the stored type survives the row filter
        $view(['typeFilter' => 'update'])
            ->assertSee('Scheduled class cancelled', false)
            ->assertDontSee('New Replacement Request');
        $view(['typeFilter' => 'submitted'])
            ->assertSee('New Replacement Request (ID #'.$request->id.')')
            ->assertDontSee('Scheduled class cancelled', false);
        $view(['typeFilter' => 'rejected'])
            ->assertDontSeeHtml('notif-row');

        // ACTIVE ONLY: decide the request server-side → the submitted row
        // rides the D15-rejected arm and disappears from the active view,
        // while the update row stays (never classified, never disabled)
        // — and the badge law holds through every filter state.
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'rejected']);

        $view(['activeOnly' => true, 'typeFilter' => 'submitted'])
            ->assertDontSeeHtml('notif-row');
        $view(['activeOnly' => true])
            ->assertSee('Scheduled class cancelled', false)
            ->assertDontSee('New Replacement Request');

        // the derived actionable-unread total is filter-INDEPENDENT (D5)
        $component = Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'pageKey' => 'requestApproval',
            'unreadOnly' => false, 'activeOnly' => true, 'typeFilter' => 'approved',
        ]);
        $this->assertSame(1, $component->viewData('unreadTotal'),
            'the badge stays the actionable unread (the update row) — not the filtered view');
    }

    /* ── the ROW TIPS (user UI update 2026-11-06) ───────────────────────────
       "Organise Notification Row Hover Tooltips": ONE label vocabulary for every
       live kind — labels, order and format come from `NotificationTip`, the facts
       from the batches the island already loads. A `data-tip` is TEXT: lines are
       joined with "\n", so the receipts read the decoded attribute line by line. */

    /** The panel HTML for the given viewer, all rows shown. */
    private function panelHtml(User $user): string
    {
        $this->actingAs($user);

        return Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false,
        ])->html();
    }

    /**
     * Every decoded `data-tip` whose text contains the needle.
     *
     * @return list<string>
     */
    private function tipsOf(string $html, string $needle): array
    {
        preg_match_all('/data-tip="([^"]*)"/s', $html, $matches);

        $found = [];
        foreach ($matches[1] as $raw) {
            $tip = html_entity_decode($raw, ENT_QUOTES);
            if (str_contains($tip, $needle)) {
                $found[] = $tip;
            }
        }

        return $found;
    }

    /** A decision notice exactly as the emitter writes it. */
    private function decisionRow(User $user, int $requestId, string $decision, int $week, string $title): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'role_view' => 'lecturer',
            'type' => $decision,
            'title' => $title,
            'body' => 'BMSE3153 (P) · W'.$week.' · generic stored body',
            'link' => $decision === 'rejected' ? '/my-request-history-ui' : '/my-replacements',
            'request_id' => $requestId,
            'class_session_id' => 23,
            'week' => $week,
        ]);
    }

    public function test_the_new_request_tip_reads_one_vocabulary(): void
    {
        $request = $this->seedRequest();
        $this->submitEvent($request->id);
        $html = $this->panelHtml($this->pl());

        $tips = $this->tipsOf($html, 'Staff (Proposer)');
        $this->assertCount(1, $tips);
        $lines = explode("\n", $tips[0]);

        $this->assertSame('Staff (Proposer): Surayaini Binti Basri (5425)', $lines[0]);
        $this->assertStringStartsWith('Requested At: ', $lines[1], 'the live clock rides line 2, its shape pinned');
        $this->assertSame('Subject & Cohort: BMSE3153 (Practical) — Software Project Management · RSD3', $lines[2]);
        $this->assertSame('Original Class Schedule: Week 8 · Tue, 10 Nov 2026 · 1:00 PM ~ 2:00 PM @ B009', $lines[3]);
        $this->assertSame('Proposed Replacement Schedule: Week 6 · Fri, 30 Oct 2026 · 1:30 PM ~ 2:00 PM @ B010', $lines[4]);
        $this->assertSame('Request ID: #'.(int) $request->id, $lines[5]);
        $this->assertCount(6, $lines, 'the six facts, nothing else — no Conflict Reason, no venue-name line');
    }

    public function test_the_rejected_tip_names_the_reason_above_both_schedules(): void
    {
        $request = $this->seedRequest();
        $request->update(['status' => 'rejected', 'rejection_reason' => 'venue unavailable', 'approver_id' => $this->coordinator()->id]);
        $this->decisionRow($this->coordinator(), (int) $request->id, 'rejected', 8, 'Replacement rejected');

        $html = $this->panelHtml($this->coordinator());
        $tips = $this->tipsOf($html, 'Rejection Reason:');

        $this->assertCount(1, $tips);
        $this->assertSame(
            implode("\n", [
                'Subject: BMSE3153 (Practical) — Software Project Management',
                'Rejection Reason: Venue Unavailable',
                'Original Class Schedule: Week 8 · Tue, 10 Nov 2026 · 1:00 PM ~ 2:00 PM @ B009',
                'Proposed Replacement Schedule: Week 6 · Fri, 30 Oct 2026 · 1:30 PM ~ 2:00 PM @ B010',
                'Request ID: #'.(int) $request->id,
            ]),
            $tips[0],
            'the reason sits between the subject and the schedules, Title Cased',
        );
        $this->assertStringNotContainsString('Staff (Proposer)', $tips[0], 'a decision row carries no Staff line');
    }

    /**
     * SDD replacement-span-persistence (user report 2026-11-07, request #38): a
     * REJECTED request has no junction rows any more — the release action deleted
     * them — so the tip falls back to the anchor. That fallback used to assume ONE
     * 30-minute cell (a second copy of `spanFromAnchor()`'s old shape), and a
     * 2-hour proposal therefore told the reader `11:00 AM ~ 11:30 AM`. It now reads
     * the width the request itself stores.
     */
    public function test_a_rejected_tip_reads_the_stored_width_when_the_junction_is_gone(): void
    {
        $request = $this->seedRequest();
        // the submit reserved 2 cells; the rejection released + deleted them
        $second = TimeSlot::query()
            ->where('week_number', 6)->where('day_of_week', 4)
            ->where('start_time', '14:00:00')->where('venue_id', 21)
            ->firstOrFail();
        DB::table('replacement_request_slots')->insert([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $second->id,
        ]);
        $request->update([
            'status' => 'rejected',
            'rejection_reason' => 'venue unavailable',
            'approver_id' => $this->coordinator()->id,
            'replacement_span' => 2,
        ]);
        DB::table('replacement_request_slots')->where('replacement_request_id', $request->id)->delete();
        $this->decisionRow($this->coordinator(), (int) $request->id, 'rejected', 8, 'Replacement rejected');

        $html = $this->panelHtml($this->coordinator());
        $tips = $this->tipsOf($html, 'Rejection Reason:');

        $this->assertCount(1, $tips);
        $this->assertSame(
            'Proposed Replacement Schedule: Week 6 · Fri, 30 Oct 2026 · 1:30 PM ~ 2:30 PM @ B010',
            explode("\n", $tips[0])[3],
            'the anchor is one cell; the stored width is what makes it an hour'
        );
    }

    public function test_an_automatic_rejection_reads_automatically_rejected_by_system(): void
    {
        $request = $this->seedRequest();
        /* the system's own shape: rejected with NO approver (no human decided) */
        $request->update(['status' => 'rejected', 'rejection_reason' => 'Automatically rejected — fewer than 3 working hours remained.', 'approver_id' => null]);
        $this->decisionRow($this->coordinator(), (int) $request->id, 'rejected', 8, 'Replacement rejected');

        $tips = $this->tipsOf($this->panelHtml($this->coordinator()), 'Rejection Reason:');

        $this->assertCount(1, $tips);
        $this->assertStringContainsString('Rejection Reason: Automatically Rejected by System', $tips[0]);
        $this->assertStringNotContainsString('fewer than 3 working hours', $tips[0], 'the stored reason never leaks');
    }

    public function test_the_class_cancelled_tip_dates_the_class_and_names_the_reason(): void
    {
        /* a LIVE cancellation: the exception row is what keeps the notice active */
        ClassException::create(['class_session_id' => 23, 'week_number' => 7, 'reason' => 'annual_leave']);
        Notification::create([
            'user_id' => $this->coordinator()->id, 'role_view' => 'lecturer', 'type' => 'update',
            'title' => 'Scheduled class cancelled', 'body' => 'BMSE3153 (P) · W7 · Tue · B009 · cancelled',
            'link' => '/my-replacements', 'class_session_id' => 23, 'week' => 7,
        ]);

        $tips = $this->tipsOf($this->panelHtml($this->coordinator()), 'Cancelled Class Schedule:');

        $this->assertCount(1, $tips);
        $this->assertSame(
            implode("\n", [
                'Subject: BMSE3153 (Practical) — Software Project Management',
                'Cancelled Class Schedule: Week 7 · Tue, 3 Nov 2026 · 1:00 PM ~ 2:00 PM @ B009',
                'Reason: Annual Leave',
            ]),
            $tips[0],
            'a cancelled class has no request: Subject + the class itself + why',
        );
        $this->assertStringNotContainsString('Request ID', $tips[0], 'there is no request to name');
        $this->assertStringNotContainsString('Requested At', $tips[0], 'a cancellation has no submission time');
    }

    public function test_the_class_updated_and_approved_notice_share_one_layout(): void
    {
        $request = $this->seedRequest();
        $request->update(['status' => 'approved', 'approver_id' => $this->coordinator()->id]);
        $owner = $this->coordinator();

        /* both titles the `approved` tile carries */
        $this->decisionRow($owner, (int) $request->id, 'approved', 8, 'Replacement class updated');
        $this->decisionRow($owner, (int) $request->id, 'approved', 8, 'Replacement approved');

        $tips = $this->tipsOf($this->panelHtml($owner), 'Approved Replacement Schedule:');

        $expected = 'Subject: BMSE3153 (Practical) — Software Project Management'
            ."\n".'Original Class Schedule: Week 8 · Tue, 10 Nov 2026 · 1:00 PM ~ 2:00 PM @ B009'
            ."\n".'Approved Replacement Schedule: Week 6 · Fri, 30 Oct 2026 · 1:30 PM ~ 2:00 PM @ B010'
            ."\n".'Request ID: #'.(int) $request->id;

        $this->assertCount(2, $tips, 'the proposer notice and the timetable notice');
        $this->assertSame($expected, $tips[0]);
        $this->assertSame($expected, $tips[1], 'both print the SAME four lines');
    }

    public function test_the_submitted_copy_and_the_awaiting_reminder_carry_the_request_lines(): void
    {
        $request = $this->seedRequest(1, 23, 8);

        Notification::create([
            'user_id' => $this->coordinator()->id, 'role_view' => 'lecturer', 'type' => 'update',
            'title' => 'Replacement request submitted', 'body' => 'BMSE3153 (P) · W8 · awaiting PL approval',
            'link' => '/my-request-history-ui', 'request_id' => $request->id, 'week' => 8,
        ]);
        Notification::create([
            'user_id' => $this->coordinator()->id, 'role_view' => 'pl', 'type' => 'awaiting',
            'title' => 'Approval still pending', 'body' => 'BMSE3153 (P) · W8 · submitted by Surayaini · awaiting your decision',
            'link' => '/request-approval-ui', 'request_id' => $request->id, 'week' => 8,
        ]);

        /* user 1 is a PL *and* the proposer, so one panel shows both rows — the
           two shapes are what the assertion is about */
        $tips = $this->tipsOf($this->panelHtml($this->coordinator()), 'Subject & Cohort:');
        $this->assertCount(2, $tips);

        $own = array_values(array_filter($tips, fn (string $t): bool => str_starts_with($t, 'Requested At: ')));
        $reminder = array_values(array_filter($tips, fn (string $t): bool => str_starts_with($t, 'Staff (Proposer): ')));

        $this->assertCount(1, $own, "the proposer's own copy leads with Requested At");
        $this->assertStringNotContainsString('Staff (Proposer)', $own[0]);
        $this->assertStringContainsString('Proposed Replacement Schedule: Week 6 · Fri, 30 Oct 2026', $own[0]);
        $this->assertStringContainsString('Request ID: #'.(int) $request->id, $own[0]);

        $this->assertCount(1, $reminder, 'the PL reminder names who submitted it');
        $this->assertStringContainsString('Staff (Proposer): Surayaini Binti Basri (5425)', $reminder[0]);
        $this->assertStringContainsString('Proposed Replacement Schedule: Week 6 · Fri, 30 Oct 2026', $reminder[0]);
    }

    public function test_a_student_row_drops_the_staff_line(): void
    {
        $request = $this->seedRequest();
        $request->update(['status' => 'approved', 'approver_id' => $this->coordinator()->id]);
        $student = User::query()->where('role', 'student')->firstOrFail();

        Notification::create([
            'user_id' => $student->id, 'role_view' => 'student', 'type' => 'approved',
            'title' => 'Replacement class updated', 'body' => 'BMSE3153 (P) · W8 · your timetable is updated',
            'link' => '/student-my-timetable-ui', 'request_id' => $request->id, 'class_session_id' => 23, 'week' => 8,
        ]);

        $tips = $this->tipsOf($this->panelHtml($student), 'Approved Replacement Schedule:');

        $this->assertCount(1, $tips);
        $this->assertStringContainsString('Approved Replacement Schedule: Week 6 · Fri, 30 Oct 2026', $tips[0]);
        $this->assertStringNotContainsString('Staff (Proposer)', $tips[0]);
        $this->assertStringNotContainsString('Requested At', $tips[0]);
    }

    public function test_an_unknown_kind_keeps_the_legacy_fallback(): void
    {
        Notification::create([
            'user_id' => $this->coordinator()->id, 'role_view' => 'lecturer', 'type' => 'update',
            'title' => 'Something else entirely', 'body' => 'legacy body',
            'link' => '/my-replacements', 'read_at' => null,
        ]);

        $this->assertStringContainsString(
            'data-tip="Something else entirely — legacy body"',
            $this->panelHtml($this->coordinator()),
        );
    }

    /* ── notif-manage-hide (2026-11-02, the user's approved design) ─────────
       The hide is a SOFT flag: `hidden_at` stamped on hide, cleared on unhide,
       NEVER a delete. The gate is the badge predicate's exact complement
       (read_or_inactive), re-validated SERVER-SIDE on every posted id. */

    /** A hideable row (read) for the given user. */
    private function readRow(User $user, string $title): Notification
    {
        return Notification::create([
            'user_id' => $user->id, 'role_view' => 'lecturer', 'type' => 'update',
            'title' => $title, 'body' => 'fixture body', 'link' => '/my-request-history-ui',
            'read_at' => Date::now(),
        ]);
    }

    /** A NOT-hideable row (unread + never classified) for the given user. */
    private function liveRow(User $user, string $title): Notification
    {
        return Notification::create([
            'user_id' => $user->id, 'role_view' => 'lecturer', 'type' => 'update',
            'title' => $title, 'body' => 'fixture body', 'link' => '/my-request-history-ui',
            'read_at' => null,
        ]);
    }

    public function test_manage_mode_pre_ticks_only_the_hideable_rows(): void
    {
        $user = $this->coordinator();
        $this->actingAs($user);
        $readable = $this->readRow($user, 'Hideable read row');
        $live = $this->liveRow($user, 'Still needs you row');

        $component = Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'unreadOnly' => false, 'activeOnly' => false,
        ])->call('enterManage');

        $selected = array_map('intval', $component->get('selectedIds'));
        $this->assertContains((int) $readable->id, $selected, 'a read row is hideable');
        $this->assertNotContains((int) $live->id, $selected, 'an unread, still-actionable row is NOT hideable');

        // the manage list renders ONLY the hideable rows — and the caption says why
        $component->assertSee('Hideable read row')
            ->assertDontSee('Still needs you row')
            ->assertSee("Notifications that still need you can't be hidden.", false);
    }

    public function test_hide_is_reversible_and_never_deletes(): void
    {
        $user = $this->coordinator();
        $this->actingAs($user);
        $row = $this->readRow($user, 'Row to put away');

        $component = Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'unreadOnly' => false, 'activeOnly' => false,
        ])->call('enterManage');

        // TAMPER: post an ineligible id alongside the eligible one — the gate
        // is server-side, so the ineligible one is silently dropped
        $live = $this->liveRow($user, 'Not allowed row');
        $component->set('selectedIds', [(int) $row->id, (int) $live->id])->call('hideSelected');

        $this->assertNotNull($row->fresh()->hidden_at, 'the read row is hidden');
        $this->assertNull($live->fresh()->hidden_at, 'the still-actionable row was rejected by the gate');
        $this->assertSame([(int) $row->id], array_map('intval', $component->get('undoIds')));
        $this->assertFalse($component->get('manageMode'), 'hiding leaves manage mode');

        // the toast copy + the find-back link
        $component->assertSee('1 notification hidden · Undo restores them', false)
            ->assertSee('Hidden (1)', false)
            ->assertDontSee('Row to put away');

        // rows are NEVER deleted — the row still exists, only flagged
        $this->assertDatabaseHas('notifications', ['id' => $row->id]);

        // UNDO restores it to the inbox
        $component->call('undoHide');
        $this->assertNull($row->fresh()->hidden_at);
        $component->assertDontSee('Hidden (1)', false);
    }

    public function test_hiding_never_moves_the_badge_and_hidden_rows_leave_the_inbox(): void
    {
        $user = $this->coordinator();
        $this->actingAs($user);
        $live = $this->liveRow($user, 'The badge row');
        $hideable = $this->readRow($user, 'A read row');

        $before = Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'unreadOnly' => false, 'activeOnly' => false,
        ]);
        $badgeBefore = $before->viewData('unreadTotal');
        $this->assertGreaterThan(0, $badgeBefore, 'the unread live row counts');

        // D1: hideable ∩ badge-counted = ∅ — so hiding cannot move the badge
        $before->call('enterManage')->call('hideSelected');
        $this->assertNotNull($hideable->fresh()->hidden_at);
        $this->assertNull($live->fresh()->hidden_at);

        $after = Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'unreadOnly' => false, 'activeOnly' => false,
        ]);
        $this->assertSame($badgeBefore, $after->viewData('unreadTotal'), 'the badge law is untouched by hiding');
        $after->assertDontSee('A read row')->assertSee('The badge row');
        $this->assertSame(1, $after->viewData('hiddenCount'));

        // the shelf shows it and Unhide all brings it back
        $after->call('showHidden')->assertSee('A read row')->assertSee('1 hidden', false);
        $after->call('unhideAll')->assertSee('0 hidden', false)->assertSee('Nothing hidden', false);
        $this->assertNull($hideable->fresh()->hidden_at);
        $this->assertSame(0, Livewire::test(NavNotifications::class, [
            'island' => 'panel', 'unreadOnly' => false, 'activeOnly' => false,
        ])->viewData('hiddenCount'));
    }

    public function test_hide_paths_are_authorisation_scoped(): void
    {
        // another user's row is untouchable through the gate
        $other = User::query()->findOrFail(2);
        $foreignRow = $this->readRow($other, 'Someone else\'s row');

        $this->actingAs($this->coordinator());
        Livewire::test(NavNotifications::class, ['island' => 'panel', 'unreadOnly' => false, 'activeOnly' => false])
            ->set('selectedIds', [(int) $foreignRow->id])
            ->call('hideSelected')
            ->call('unhide', (int) $foreignRow->id)
            ->call('unhideAll');

        $this->assertNull($foreignRow->fresh()->hidden_at, 'a foreign id is never written');
    }

    // ───── R1/R2/R5 receipts (notif-recipient-rulings, 2026-11-03) ─────

    public function test_a_non_pl_proposer_gets_one_confirmation_row_and_the_pl_rows_are_untouched(): void
    {
        $nonPl = $this->nonPl();
        $request = $this->seedRequest((int) $nonPl->id);

        app(NotificationEmitter::class)->requestSubmitted(
            $nonPl, $this->pls(), 'AMSE2003', 'P', (int) $request->id, 7
        );

        $mine = Notification::query()->where('user_id', $nonPl->id)->get();
        $this->assertCount(1, $mine, 'R1: exactly ONE confirmation row — a PL fan-out copy must never double it');

        $row = $mine->first();
        $this->assertSame('update', $row->type, 'the frozen tile key');
        $this->assertSame('lecturer', $row->role_view);
        $this->assertSame('Replacement request submitted', $row->title);
        $this->assertSame('AMSE2003 (P) · W7 · awaiting PL approval', $row->body);
        $this->assertSame('/my-request-history-ui', $row->link, 'the proposer tracks the request, not the approval queue');
        $this->assertSame((int) $request->id, $row->request_id);

        // the PL fan-out is additive-only: two PL queue rows, untouched
        $plRows = Notification::query()->where('type', 'submitted')->get();
        $this->assertCount(2, $plRows);
        $plRows->each(fn (Notification $r) => $this->assertSame('/request-approval-ui', $r->link));
    }

    public function test_a_pl_proposer_keeps_only_the_queue_row(): void
    {
        // R1's "do not add a second row" half — the proposer here IS a PL.
        $pl = $this->coordinator();
        $request = $this->seedRequest((int) $pl->id);

        app(NotificationEmitter::class)->requestSubmitted(
            $pl, $this->pls(), 'AMSE2003', 'P', (int) $request->id, 7
        );

        $mine = Notification::query()->where('user_id', $pl->id)->get();
        $this->assertCount(1, $mine, 'the PL proposer holds their queue row and nothing else');
        $this->assertSame('submitted', $mine->first()->type);
        $this->assertSame(0, Notification::query()->where('type', 'update')->count());
    }

    public function test_the_staff_line_follows_the_row_contract(): void
    {
        $nonPl = $this->nonPl();
        $session = ClassSession::query()->findOrFail(23);
        $request = $this->seedRequest((int) $nonPl->id);

        // (a) a PL reading the queue row → the proposer, with the staff id
        app(NotificationEmitter::class)->requestSubmitted(
            $nonPl, $this->pls(), 'AMSE2003', 'P', (int) $request->id, 7
        );
        $this->actingAs($this->coordinator());
        /* notif-inactive-hide-sender (2026-11-05, spec R3): the subtitle is the
           SENDER line and reads `by <Name (staff_id)>` / `by YOU`. */
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans(
            Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])->html()
        ), 'a PL sees who proposed, with the staff id');

        // (b) a student reading the approval notice → the proposer. The emitter
        // takes an ELOQUENT collection (the action hands it one per cohort), and
        // `Student`'s primary key IS `user_id`, so key on that.
        $student = $session->cohorts->flatMap(fn ($c) => $c->students()->with('user')->get())->first();
        $this->assertNotNull($student?->user);
        app(NotificationEmitter::class)->approvalNotice(
            $session, Student::query()->whereKey((int) $student->user_id)->with('user')->get(), (int) $request->id, 8
        );
        $this->actingAs($student->user);
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans(
            Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'studentTimetable', 'unreadOnly' => false, 'activeOnly' => false])->html()
        ));

        // (c) the owner reading their OWN rows → the PL who acted. Both self rows
        // take the actor branch: the R1 confirmation row upgrades once the
        // decision stamps `approver_id`, and the merged R3 row joins it.
        ReplacementRequest::query()->whereKey($request->id)->update(['approver_id' => (int) $this->coordinator()->id]);
        app(NotificationEmitter::class)->replacementConfirmed(
            $nonPl, $session->fresh(), (int) $request->id, 8, 'Surayaini Binti Basri'
        );
        $this->actingAs($nonPl);
        $spans = $this->subtitleSpans(
            Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'myTimetable', 'unreadOnly' => false, 'activeOnly' => false])->html()
        );
        $this->assertNotEmpty($spans, 'the owner rows do carry a line');
        $this->assertSame(['by Surayaini Binti Basri (5425)'], array_values(array_unique($spans)), 'a self row names the PL who acted — never the reader');
    }

    public function test_the_staff_line_is_omitted_on_a_self_only_event(): void
    {
        // (d) the owner cancelling their OWN class → no line for them, the line
        // still names the class owner for a student (the fresh DB keeps this row
        // the only one in each mailbox).
        $nonPl = $this->nonPl();
        ClassSession::query()->whereKey(23)->update(['lecturer_id' => (int) $nonPl->id]);
        $session = ClassSession::query()->findOrFail(23);
        $student = $session->cohorts->flatMap(fn ($c) => $c->students()->with('user')->get())->first();
        $this->assertNotNull($student?->user);

        app(NotificationEmitter::class)->classCancelled($nonPl, $session, 8);

        $this->actingAs($nonPl);
        $own = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'myTimetable', 'unreadOnly' => false, 'activeOnly' => false])->html();
        /* notif-inactive-hide-sender (2026-11-05): the panel reads Title Case, and
           the SELF-owned cancellation says "Your" and carries `by YOU` — the old
           "omit the line on a self-only event" rule is superseded by the brief. */
        $this->assertStringContainsString('Your Scheduled Class Cancelled', $own);
        $this->assertSame(['by YOU'], array_values(array_unique($this->subtitleSpans($own))), 'a self-only event says who did it: YOU');

        $this->actingAs($student->user);
        $studentHtml = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'studentTimetable', 'unreadOnly' => false, 'activeOnly' => false])->html();
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans($studentHtml), 'the student still sees whose class it was');
    }

    public function test_a_row_never_shows_two_staff_lines_in_any_state(): void
    {
        // user refinement 2 (2026-11-03): count the SPANS on one row in the four
        // states, so a duplicate can never creep back silently.
        $nonPl = $this->nonPl();
        $request = $this->seedRequest((int) $nonPl->id);
        app(NotificationEmitter::class)->requestSubmitted(
            $nonPl, $this->pls(), 'AMSE2003', 'P', (int) $request->id, 7
        );

        $pl = $this->coordinator();
        $this->actingAs($pl);
        $row = Notification::query()->where('user_id', $pl->id)->where('type', 'submitted')->firstOrFail();

        $panel = fn () => Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])->html();

        // The line is the SENDER line since notif-inactive-hide-sender (2026-11-05),
        // so every state reads `by <Name (staff_id)>` — one span, always.
        // 1. unread — the composer's subtitle and the sender agree → ONE span
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans($panel()), 'unread');

        // 2. read — `_display` is gone; the computed line must take over, once
        Notification::query()->whereKey($row->id)->update(['read_at' => Date::now()]);
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans($panel()), 'read');

        // 3. decided — the request is approved, the row goes inactive
        ReplacementRequest::query()->whereKey($request->id)->update(['status' => 'approved']);
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans($panel()), 'decided');

        // 4. hidden — the same row on the shelf
        Notification::query()->whereKey($row->id)->update(['hidden_at' => Date::now()]);
        $shelf = Livewire::test(NavNotifications::class, ['island' => 'panel', 'pageKey' => 'requestApproval', 'unreadOnly' => false, 'activeOnly' => false])
            ->call('showHidden')->html();
        $this->assertSame(['by Lim Jia Zheng (5770)'], $this->subtitleSpans($shelf), 'hidden');
    }
}
