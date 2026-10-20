<?php

namespace Tests\Feature;

use App\Actions\ApproveRequest;
use App\Actions\RejectRequest;
use App\Actions\SubmitReplacementRequest;
use App\Mail\ReplacementApprovalNotice;
use App\Mail\ReplacementDecided;
use App\Mail\ReplacementSubmitted;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * SDD span-aware-approval-flow — T18 (spec notifications R3): the email
 * queue contract + the NFR 1.4 real-worker drain.
 *
 * Two test families live here:
 * 1. the ENQUEUE assertions (`Queue::fake` + `Mail::fake` — recipients per
 *    grill Q7's table);
 * 2. the NFR 1.4 DRAIN test — WITHOUT `Queue::fake` (RR1 🟡4): the real
 *    `jobs` row is inserted by the after-commit hook; `queue:work --once`
 *    must consume + send (Mail::fake holds) within one cycle, elapsed <1 min.
 */
final class EmailQueueTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        foreach (['users', 'semesters', 'venues', 'modules', 'class_sessions', 'time_slots',
            'replacement_requests', 'replacement_request_slots', 'audit_logs',
            'class_exceptions', 'programmes', 'cohorts', 'jobs'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
            );
        }
    }

    private function lecturer(string $name, bool $isPl = false): User
    {
        $user = User::factory()->create(['role' => 'lecturer', 'name' => $name]);
        Lecturer::create([
            'user_id' => $user->id,
            'staff_id' => 'S'.random_int(10000, 99999),
            'dept_id' => 1,
            'is_pl' => $isPl,
        ]);

        return $user;
    }

    /**
     * A pending span-3 request (reserved per the post-submit shape) for the
     * decide-side mail fixtures.
     */
    private function pendingFixture(User $requester, bool $withCohort = true): array
    {
        $semester = DB::table('semesters')->first();

        $venue = Venue::create([
            'room_code' => 'EQ'.random_int(100, 999),
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);
        $module = Module::create([
            'module_code' => 'EQM'.random_int(1000, 9999),
            'module_name' => 'Email Fixture Module',
            'allowed_session_types' => 'L,T,P',
        ]);
        $session = ClassSession::create([
            'semester_id' => (int) $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $requester->id,
            'day_of_week' => 2,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'T',
        ]);

        // attach the seeded cohort (the FR 1.9 leg needs students) — skipped
        // for the DRAIN test's single-job assertion
        if ($withCohort) {
            $cohortId = (int) Cohort::query()->value('id');
            DB::table('session_cohorts')->insert([
                ['class_session_id' => $session->id, 'cohort_id' => $cohortId],
            ]);
        }

        $slotIds = [];
        for ($k = 0; $k < 3; $k++) {
            $st = date('H:i:s', strtotime('14:00:00') + $k * 1800);
            $slotIds[] = TimeSlot::create([
                'semester_id' => (int) $semester->id,
                'class_session_id' => null,
                'week_number' => 8,
                'day_of_week' => 2,
                'start_time' => $st,
                'end_time' => date('H:i:s', strtotime($st) + 1800),
                'venue_id' => $venue->id,
                'status' => 'pending',
                'version' => 2,
            ])->id;
        }

        $request = ReplacementRequest::create([
            'semester_id' => (int) $semester->id,
            'proposer_id' => $requester->id,
            'class_session_id' => $session->id,
            'week_number' => 6,
            'replacement_time_slot_id' => $slotIds[0],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        return [$slotIds, $request];
    }

    // ───── family 1 — enqueue assertions ( Merc fake + Queue fake) ─────

    public function test_approve_queues_decided_and_cohort_notice(): void
    {
        [$slotIds, $request] = $this->pendingFixture($this->lecturer('Email Requester'));
        $pl = $this->lecturer('Email PL', true);

        Mail::fake();
        Queue::fake();

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        // FR 2.13 — the proposer decided-mail
        Mail::assertQueued(ReplacementDecided::class, 1);
        // FR 1.9 — the affected cohort's roster (the FIRST seeded cohort is
        // the fixture's attach target; 23 approved sizes)
        $cohortId = (int) Cohort::query()->value('id');
        $rosterCount = (int) DB::table('students')->where('cohort_id', $cohortId)->count();
        Mail::assertQueued(ReplacementApprovalNotice::class, $rosterCount);
    }

    public function test_reject_queues_only_the_decided_mail(): void
    {
        [$slotIds, $request] = $this->pendingFixture($this->lecturer('Email Requester 2'));
        $pl = $this->lecturer('Email PL 2', true);

        Mail::fake();
        Queue::fake();

        $this->assertTrue(app(RejectRequest::class)($pl, (int) $request->id, 'Venue unavailable')['ok']);

        Mail::assertQueued(ReplacementDecided::class, 1);
        Mail::assertNotQueued(ReplacementApprovalNotice::class);
    }

    public function test_decide_own_request_queues_one_decided_mail(): void
    {
        // Q13 — the PL decides their own submission; the proposer (themselves)
        // still receives exactly ONE decided mail.
        $pl = $this->lecturer('Self PL', true);
        [$slotIds, $request] = $this->pendingFixture($pl);

        Mail::fake();
        Queue::fake();

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        Mail::assertQueued(ReplacementDecided::class, 1);
    }

    public function test_submit_queues_one_mail_per_pl(): void
    {
        $requester = $this->lecturer('Submit Mail Requester');
        $this->lecturer('Submit PL A', true);
        $plB = $this->lecturer('Submit PL B', true);

        $plCount = User::query()->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))->count();

        $semester = DB::table('semesters')->first();
        $venue = Venue::create([
            'room_code' => 'SR'.random_int(100, 999),
            'capacity' => 30,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);
        $module = Module::create([
            'module_code' => 'SRX'.random_int(100, 999),
            'module_name' => 'Submit Mail Module',
            'allowed_session_types' => 'L,T',
        ]);
        $session = ClassSession::create([
            'semester_id' => (int) $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $requester->id,
            'day_of_week' => 4,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'T',
        ]);
        $cohortId = (int) Cohort::query()->value('id');
        DB::table('session_cohorts')->insert([
            ['class_session_id' => $session->id, 'cohort_id' => $cohortId],
        ]);

        $slotIds = [];
        for ($k = 0; $k < 4; $k++) {
            $st = date('H:i:s', strtotime('14:00:00') + $k * 1800);
            $slotIds[] = TimeSlot::create([
                'semester_id' => (int) $semester->id,
                'class_session_id' => null,
                'week_number' => 9,
                'day_of_week' => 4,
                'start_time' => $st,
                'end_time' => date('H:i:s', strtotime($st) + 1800),
                'venue_id' => $venue->id,
                'status' => 'available',
                'version' => 1,
            ])->id;
        }

        // occurrence-week-pin-truthfulness S8: the submit's pinned pair needs a live fact.
        ClassException::create(['class_session_id' => $session->id, 'week_number' => 9, 'reason' => 'official_event']);

        Mail::fake();
        Queue::fake();

        $action = app(SubmitReplacementRequest::class);
        $action($requester, $session, 9, $slotIds[0], null, 4);

        // FR 4.15 — ONE queued copy per PL
        Mail::assertQueued(ReplacementSubmitted::class, $plCount);
    }

    // ───── family 2 — the NFR 1.4 DRAIN (without Queue::fake) ─────

    public function test_queue_worker_drains_the_decided_mail_within_one_minute(): void
    {
        // RR1 🟡4: NO Mail::fake (it would swallow the dispatch — no real row)
        // and NO Queue::fake. phpunit.xml pins QUEUE_CONNECTION=sync — THIS
        // test overrides for the real jobs-table flow (the production
        // posture); the mail itself renders via the `array` driver (nothing
        // sends).
        config(['queue.default' => 'database']);

        [$slotIds, $request] = $this->pendingFixture($this->lecturer('Drain Requester'), withCohort: false);
        $pl = $this->lecturer('Drain PL', true);

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        // FR 4.16: the decision queued a REAL database job carrying the mailable
        $this->assertSame(1, DB::table('jobs')->count());
        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertStringContainsString('ReplacementDecided', $payload);

        $startedAt = microtime(true);

        // `queue:work --once` — the next job is consumed + rendered within this call
        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $elapsed = (microtime(true) - $startedAt);

        $this->assertLessThan(60, $elapsed, 'NFR 1.4: the drain completes within 1 minute');
        $this->assertSame(0, DB::table('jobs')->count(), 'queue:work --once consumed the queued email');
    }
}
