<?php

namespace Tests\Feature;

use App\Actions\CancelClass;
use App\Actions\CancelPendingRequest;
use App\Actions\SubmitReplacementRequest;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\MatrixIntersectionEngine;
use App\Services\OCCValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Slice B flow gate (tasks 2.1): submit winner/loser paths, cancel flows,
 * ownership scoping, D1 partial-unique, NFR 1.1 recalc budget.
 * Cross-connection race coverage lives in OccConcurrencyTest.
 */
final class ReplacementFlowTest extends TestCase
{
    use RefreshDatabase;

    private MatrixIntersectionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");

        $this->seed();

        // Sequences are NOT transactional — later tests (e.g. TimetableWiringTest)
        // assert literal ids, so every test here starts from a pristine counter.
        foreach (['replacement_requests', 'class_exceptions', 'audit_logs'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
        }

        $this->engine = new MatrixIntersectionEngine;
    }

    /**
     * The Nth distinct user (ordered by id) that owns at least one class
     * session and has the lecturer role — the seeded dataset's teaching staff.
     */
    private function lecturerUser(int $nth = 0): User
    {
        $users = User::query()
            ->whereIn('id', ClassSession::query()->select('lecturer_id'))
            ->where('role', 'lecturer')
            ->orderBy('id')
            ->get();

        $user = $users->get($nth) ?? $users->last();

        return $user ?? throw new \RuntimeException('Seeded dataset has no lecturer-owned sessions');
    }

    /**
     * Pick an owned session + an anchor slot where the given lecturer passes
     * the engine pre-check (the same eligibility walk the arrangement page
     * runs). Sessions repeat weekly, so freedom is keyed on (week, day) pairs:
     * scan the session's own venue on a lecturer-free pair (20 candidates).
     *
     * @return array{0: ClassSession, 1: TimeSlot}
     */
    private function eligibleAnchorFor(int $userId): array
    {
        $sessions = ClassSession::query()->where('lecturer_id', $userId)->with('cohorts')->orderBy('id')->get();

        foreach ($sessions as $session) {
            $slot = $this->firstEligibleSlot(
                $userId,
                $session->cohorts->pluck('id')->all(),
                (int) $session->venue_id,
                $session->session_type,
            );

            if ($slot !== null) {
                return [$session, $slot];
            }
        }

        throw new \RuntimeException("No eligible anchor found for user {$userId}");
    }

    /**
     * First available slot at the venue, on a (week, day) pair free for the
     * lecturer, that passes the engine pre-check.
     */
    private function firstEligibleSlot(int $userId, array $cohortIds, int $venueId, string $sessionType): ?TimeSlot
    {
        $lecturerPairs = $this->busyPairs(
            TimeSlot::query()->whereIn('class_session_id', ClassSession::query()->where('lecturer_id', $userId)->select('id')),
        );

        foreach ($this->freePairs($lecturerPairs) as [$week, $day]) {
            $slot = TimeSlot::query()
                ->where('status', 'available')
                ->where('venue_id', $venueId)
                ->where('week_number', $week)
                ->where('day_of_week', $day)
                ->orderBy('id')
                ->get()
                ->first(fn (TimeSlot $candidate): bool => $this->engine->validateSlot(
                    (int) $candidate->id,
                    $userId,
                    $cohortIds,
                    $sessionType,
                    $week,
                ));

            if ($slot !== null) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param  Builder<TimeSlot>  $query
     * @return Collection<int, array{week: int, day: int}>
     */
    private function busyPairs($query): Collection
    {
        return $query->select('week_number', 'day_of_week')->distinct()->get()
            ->map(fn (TimeSlot $slot): array => ['week' => (int) $slot->week_number, 'day' => (int) $slot->day_of_week]);
    }

    /**
     * All (week, day) pairs of the semester that are NOT in the given busy set.
     *
     * @param  Collection<int, array{week: int, day: int}>  $busy
     * @return \Generator<int, array{0: int, 1: int}>
     */
    private function freePairs(Collection $busy): \Generator
    {
        for ($week = 1; $week <= 14; $week++) {
            for ($day = 0; $day <= 5; $day++) {
                if (! $busy->contains(fn (array $pair): bool => $pair['week'] === $week && $pair['day'] === $day)) {
                    yield [$week, $day];
                }
            }
        }
    }

    public function test_submit_winner_creates_request_reserves_slot_and_audits(): void
    {
        $proposer = $this->lecturerUser();

        [$session, $slot] = $this->eligibleAnchorFor((int) $proposer->id);
        $versionBefore = (int) $slot->version;

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);

        $result = $action($proposer, $session, (int) $slot->week_number, (int) $slot->id, 'club event');

        $this->assertTrue($result['ok'], 'Winner submit must succeed, got: '.($result['conflict_reason'] ?? ''));

        $request = ReplacementRequest::query()
            ->where('proposer_id', $proposer->id)
            ->where('class_session_id', $session->id)
            ->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame((int) $slot->id, (int) $request->replacement_time_slot_id);
        $this->assertSame('club event', $request->remarks);

        $slot->refresh();
        $this->assertSame('pending', $slot->status, 'Anchor slot must be pending after a winning submit');
        $this->assertSame($versionBefore + 1, (int) $slot->version, 'D6: winner reserves with exactly one version bump');

        $audit = AuditLog::query()
            ->where('replacement_request_id', $request->id)
            ->where('occ_validation_result', 'success')
            ->firstOrFail();
        $this->assertSame('submitted', $audit->action);
    }

    public function test_submit_rejects_session_not_owned_by_caller(): void
    {
        $owner = $this->lecturerUser();
        $intruder = $this->lecturerUser(1);
        $this->assertNotSame($owner->id, $intruder->id);

        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();
        $slot = TimeSlot::query()->where('status', 'available')->firstOrFail();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($intruder, $session, (int) $slot->week_number, (int) $slot->id, null);

        $this->assertFalse($result['ok'], 'FR 2.11: a lecturer must not submit for another lecturer’s session');
        $this->assertSame('not_session_owner', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->count(), 'No request row may be created');
    }

    public function test_submit_rejects_week_outside_semester(): void
    {
        $proposer = $this->lecturerUser();

        [$session, $slot] = $this->eligibleAnchorFor((int) $proposer->id);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($proposer, $session, 15, (int) $slot->id, null);

        $this->assertFalse($result['ok'], 'FR 2.9: weeks are 1–14');
        $this->assertSame('invalid_week', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->count());
    }

    public function test_submit_rejects_non_available_anchor_without_row(): void
    {
        $proposer = $this->lecturerUser();

        [$session, $slot] = $this->eligibleAnchorFor((int) $proposer->id);

        // The seeder never creates pending slots — flip the eligible anchor to
        // simulate another lecturer having reserved it first.
        DB::update('UPDATE time_slots SET status = ?, version = version + 1 WHERE id = ?', ['pending', $slot->id]);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($proposer, $session, (int) $slot->week_number, (int) $slot->id, null);

        $this->assertFalse($result['ok'], 'Anchor must be available at pre-check time');
        $this->assertSame('slot_not_available', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->count(), 'Pre-check failure must not create a row');
    }

    public function test_d1_partial_unique_one_active_request_per_block(): void
    {
        $proposer = $this->lecturerUser();

        [$session, $slot] = $this->eligibleAnchorFor((int) $proposer->id);

        ReplacementRequest::create([
            'semester_id' => $slot->semester_id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $session->id,
            'week_number' => (int) $slot->week_number,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        ReplacementRequest::create([
            'semester_id' => $slot->semester_id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $session->id,
            'week_number' => (int) $slot->week_number,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function test_cancel_pending_restores_slot_and_audits(): void
    {
        $proposer = $this->lecturerUser();

        [$session, $slot] = $this->eligibleAnchorFor((int) $proposer->id);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($proposer, $session, (int) $slot->week_number, (int) $slot->id, null);
        $this->assertTrue($result['ok']);

        $request = ReplacementRequest::query()->where('proposer_id', $proposer->id)->firstOrFail();
        $slot->refresh();
        $versionBefore = (int) $slot->version;

        $cancelled = (new CancelPendingRequest)($proposer, (int) $request->id);

        $this->assertTrue($cancelled, 'FR 2.10: owner cancels own pending request');

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertNotNull($request->decided_at);

        $slot->refresh();
        $this->assertSame('available', $slot->status, 'FR 2.10: the slot returns to available');
        $this->assertSame($versionBefore + 1, (int) $slot->version, 'D6: release bumps version');

        $audit = AuditLog::query()->where('action', 'cancelled')->firstOrFail();
        $this->assertSame((int) $slot->id, (int) $audit->time_slot_id);
        $this->assertSame('pending', $audit->old_status);
        $this->assertSame('available', $audit->new_status);
    }

    public function test_cancel_denied_for_non_owner_or_non_pending(): void
    {
        $owner = $this->lecturerUser();
        $other = $this->lecturerUser(1);

        [$session, $slot] = $this->eligibleAnchorFor((int) $owner->id);

        (new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine))($owner, $session, (int) $slot->week_number, (int) $slot->id, null);
        $request = ReplacementRequest::query()->where('proposer_id', $owner->id)->firstOrFail();

        $this->assertFalse((new CancelPendingRequest)($other, (int) $request->id), 'FR 2.11: another lecturer cannot cancel');

        $request->refresh();
        $this->assertSame('pending', $request->status, 'Request must survive the denied cancel');
        $slot->refresh();
        $this->assertSame('pending', $slot->status);
    }

    public function test_cancel_class_writes_exception_and_audit(): void
    {
        $owner = $this->lecturerUser();

        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        $cancelled = (new CancelClass)($owner, $session, 7, 'public_holiday');
        $this->assertTrue($cancelled, 'FR 2.16: owner cancels own class with a valid reason');

        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', 7)
            ->firstOrFail();
        $this->assertSame('public_holiday', $exception->reason);

        $audit = AuditLog::query()->where('action', 'class_cancelled')->firstOrFail();
        $this->assertSame((int) $owner->id, (int) $audit->user_id);
        $this->assertSame('public_holiday', $audit->details['reason'] ?? null);
    }

    public function test_cancel_class_rejects_invalid_reason_and_duplicates(): void
    {
        $owner = $this->lecturerUser();
        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        $this->assertFalse(
            (new CancelClass)($owner, $session, 7, 'because_i_said_so'),
            'Reason must be one of the 5 CHECK values'
        );

        $this->assertTrue((new CancelClass)($owner, $session, 7, 'official_event'));
        $this->assertFalse(
            (new CancelClass)($owner, $session, 7, 'emergency_leave'),
            'class_exceptions unique (session, week) must hold'
        );
    }

    public function test_recalc_budget_under_500ms_on_seeded_dataset(): void
    {
        $proposer = $this->lecturerUser();

        [$session, $slot] = $this->eligibleAnchorFor((int) $proposer->id);
        $cohortIds = $session->cohorts->pluck('id')->all();

        $start = hrtime(true);

        $runs = $this->engine->findAvailableSlots(
            (int) $proposer->id,
            $cohortIds,
            (int) $slot->semester_id,
            (int) $slot->week_number,
            $session->session_type,
            $this->durationMinutes($session),
        );

        $elapsedMs = (hrtime(true) - $start) / 1e6;

        $this->assertLessThan(500.0, $elapsedMs, "NFR 1.1: recalc took {$elapsedMs} ms");
        $this->assertIsArray($runs);
    }

    private function durationMinutes(ClassSession $session): int
    {
        return (int) ((strtotime($session->end_time) - strtotime($session->start_time)) / 60);
    }
}
