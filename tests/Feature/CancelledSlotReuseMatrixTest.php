<?php

namespace Tests\Feature;

use App\Actions\ApproveRequest;
use App\Actions\SubmitReplacementRequest;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Services\ApprovalValidity;
use App\Services\MatrixIntersectionEngine;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CancelledSlotFixture;
use Tests\TestCase;
use Throwable;

/**
 * SDD cancelled-slot-reuse, tasks A09 + A09b (design D4, spec S09 + S10/S11) —
 * the five eligible reuse combinations and the independent-blocker matrix,
 * both through the REAL actions on COMPLETE original grids:
 * availability (the arrangement engine's green) → submit → approve.
 *
 * Every case runs on the shared fixture world of
 * `Tests\Support\CancelledSlotFixture`: the source occurrence is finally
 * cancelled (audit + exception + released original cells), the released
 * interval is the exact FR 2.17 notice boundary of the frozen clock, and
 * every assertion is date-frozen — nothing depends on the wall clock.
 *
 * RefreshDatabase (per-class migrate:fresh, per-test wrapping transaction) on
 * the disposable `class_replacement_testing` database only (phpunit.xml).
 * This file writes nothing to any demo database.
 */
final class CancelledSlotReuseMatrixTest extends TestCase
{
    use RefreshDatabase;

    private CancelledSlotFixture $world;

    private MatrixIntersectionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        CancelledSlotFixture::freezeClock();
        $this->engine = app(MatrixIntersectionEngine::class);
        $this->world = new CancelledSlotFixture;
        $this->world->build();
    }

    protected function tearDown(): void
    {
        try {
            CancelledSlotFixture::unfreezeClock();

            // the actions' own finally-releases own the happy paths; this
            // sweep only guarantees a failing test cannot poison the shared
            // PHPUnit process connection for later tests
            try {
                DB::statement('SELECT pg_advisory_unlock_all()');
            } catch (Throwable) {
                // n/a
            }
        } finally {
            parent::tearDown();
        }
    }

    // ── A09: the five S09 reuse combinations ─────────────────────────────

    public static function reuseCases(): array
    {
        // each dataset is ONE array argument (the PHPUnit positional convention)
        return [
            'same room, different cohort' => [
                ['proposer' => 'b', 'session' => 'b1', 'landing' => 'released'],
            ],
            'different room, different cohort' => [
                ['proposer' => 'b', 'session' => 'b1', 'landing' => 'altRoom'],
            ],
            'different room, same cohort' => [
                ['proposer' => 'c', 'session' => 'c1', 'landing' => 'altRoom'],
            ],
            'same lecturer, different course and cohort' => [
                ['proposer' => 'a', 'session' => 'a2', 'landing' => 'released'],
            ],
            'same cancelled class, exact original slot' => [
                ['proposer' => 'a', 'session' => 'src', 'landing' => 'released', 'exact' => true],
            ],
        ];
    }

    #[DataProvider('reuseCases')]
    public function test_a_released_occurrence_books_through_availability_submit_and_approve(array $case): void
    {
        $world = $this->world;

        // 1. the final cancellation, then the release facts it must leave behind
        $world->cancelSource();
        $this->assertReleasedSource();

        // 2. availability — the released target reads ordinary green
        $proposer = $world->users[$case['proposer']];
        $session = $world->sessions[$case['session']];
        $anchor = $world->slots[$case['landing']][0];
        $span = CancelledSlotFixture::SOURCE_SPAN;

        foreach ($world->slots[$case['landing']] as $cell) {
            $this->assertSame('available', (string) $cell->refresh()->status, 'every planned cell is available before the submit');
        }

        $this->assertTrue(
            $this->engine->validateSlot(
                (int) $anchor->id,
                (int) $proposer->id,
                $session->cohorts->pluck('id')->all(),
                (string) $session->session_type,
                (int) $anchor->week_number,
                $span,
                $session,
            ),
            'the arrangement engine reads the target as green across the full span',
        );

        // 3. submit — a real pending full-span request with real junctions
        $result = app(SubmitReplacementRequest::class)(
            $proposer,
            $session,
            (int) $session->id === (int) $world->sessions['src']->id
                ? CancelledSlotFixture::SOURCE_WEEK   // the same cancelled class pins its own occurrence
                : CancelledSlotFixture::PIN_WEEK,     // every other requester pins its own week-5 occurrence
            (int) $anchor->id,
            null,
            $span,
        );

        $this->assertTrue($result['ok'], 'the released interval accepts the submit, got: '.var_export($result['conflict_reason'], true));
        $this->assertNotNull($result['request_id']);

        $request = ReplacementRequest::query()->findOrFail($result['request_id']);
        $this->assertSame('pending', (string) $request->status);
        $this->assertSame($span, (int) $request->replacement_span, 'the request keeps its own length');
        $this->assertSame((int) $session->id, (int) $request->class_session_id);

        $landingIds = array_map(fn (TimeSlot $cell): int => (int) $cell->id, $world->slots[$case['landing']]);
        $junctionIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $this->assertSame(count($landingIds), count($junctionIds), 'the live reservation covers the whole span');
        $this->assertEqualsCanonicalizing($landingIds, $junctionIds, 'the junctions are the real span cells');

        foreach ($landingIds as $slotId) {
            $this->assertSame('pending', (string) TimeSlot::find($slotId)->status, 'the reservation holds every cell');
        }

        // 4. the PL queue's own validity agrees, then the approval lands
        $validity = app(ApprovalValidity::class)->evaluate($request);
        $this->assertTrue($validity->valid, 'approval validity agrees with the green availability');

        $decision = app(ApproveRequest::class)($world->users['pl'], (int) $request->id);
        $this->assertTrue($decision['ok'], 'the approval succeeds, got: '.var_export($decision['conflict_reason'], true));

        $request->refresh();
        $this->assertSame('approved', (string) $request->status);
        $this->assertSame((int) $world->users['pl']->id, (int) $request->approver_id);
        $this->assertNotNull($request->decided_at);

        $this->assertSame(count($landingIds), ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)->count(), 'approval never deletes its landing receipt');

        foreach ($landingIds as $slotId) {
            $this->assertSame('occupied', (string) TimeSlot::find($slotId)->status, 'approval never releases its new landing');
        }

        $this->assertSame(1, ReplacementRequest::query()->count(), 'exactly one request exists at the end');

        // 5. the original side of the approval
        if (! empty($case['exact'])) {
            $this->assertExactReuseKeepsItsOwnLanding($world, $landingIds);
        } else {
            $this->assertRequesterOriginalReleased($world, $session);
            $this->assertSourceIntervalFinalState($world, $case['landing'] === 'released');
        }
    }

    // ── A09b: the independent blockers all still refuse ──────────────────

    public static function blockers(): array
    {
        // each dataset is ONE array argument (the PHPUnit positional convention)
        return [
            'another booking already holds a released cell' => [['case' => 'room']],
            'the submitting lecturer teaches an overlapping class' => [['case' => 'lecturer']],
            "the submitting session's cohort is busy" => [['case' => 'cohort']],
            'the room is too small for the cohort' => [['case' => 'capacity']],
            'the module type is not allowed in the room' => [['case' => 'rule']],
            'the landing day is a public holiday' => [['case' => 'holiday']],
            'the landing is inside the 3-working-day notice window' => [['case' => 'notice']],
            'the source already carries an active request' => [['case' => 'active-request']],
            'the uncancelled occurrence in another week still blocks' => [['case' => 'uncancelled-week']],
            "the lecturer's own pending make-up still blocks" => [['case' => 'makeup']],
        ];
    }

    #[DataProvider('blockers')]
    public function test_an_independent_blocker_still_refuses_after_the_release(array $case): void
    {
        $key = $case['case'];
        $world = $this->world;

        $world->cancelSource();
        $this->assertReleasedSource();

        [$proposer, $session, $pinWeek, $anchor, $span, $expected] = $this->blockerTwist($key);

        // a refused submit may never add a request row — the count the twist
        // already created (the room/active-request twists own real requests)
        // must survive the refusal unchanged
        $requestsBefore = ReplacementRequest::query()->count();

        $result = app(SubmitReplacementRequest::class)(
            $proposer,
            $session,
            $pinWeek,
            (int) $anchor->id,
            null,
            $span,
        );

        $this->assertFalse($result['ok'], "the [$key] blocker must refuse, got ok");
        $this->assertSame($expected, $result['conflict_reason']);
        $this->assertNull($result['request_id']);
        $this->assertSame($requestsBefore, ReplacementRequest::query()->count(),
            "the [$key] blocker books nothing");

        if ($key === 'room') {
            $this->assertSame('10:30:00', $result['failing_start'], 'the failing cell of the released span is named');
            $this->assertSame('11:00:00', $result['failing_end']);
            $this->assertSame('occupied', $result['failing_kind']);

            // the holder's own cell and the untouched cells around it
            $this->assertSame('pending', (string) $world->slots['released'][1]->refresh()->status, 'the first holder keeps the 10:30 cell');
            $this->assertSame('available', (string) $world->slots['released'][0]->refresh()->status);
        }

        if ($key === 'uncancelled-week') {
            foreach ($world->slots['uncancelled'] as $cell) {
                $this->assertSame('occupied', (string) $cell->refresh()->status, 'the uncancelled week never releases');
                $this->assertSame((int) $world->sessions['src']->id, (int) $cell->class_session_id);
                $this->assertSame(1, (int) $cell->version, 'no release touched the other week');
            }

            foreach ($world->slots['released'] as $cell) {
                $this->assertSame('available', (string) $cell->refresh()->status, 'source and target week stay separate');
            }
        }
    }

    /**
     * The Sunday arm of the blocker matrix: the grid encoding carries no
     * Sunday rows at all (day_of_week 0–5 = Mon–Sat), so no Sunday cell can
     * ever be offered — while the FREED interval itself is genuinely offered
     * by the arrangement engine.
     */
    public function test_a_sunday_never_carries_a_bookable_grid_row(): void
    {
        $world = $this->world;

        $world->cancelSource();
        $this->assertReleasedSource();

        $this->assertSame(0, TimeSlot::query()
            ->where('semester_id', (int) $world->semester->id)
            ->where('day_of_week', 6)
            ->count(), 'the encoding carries no Sunday rows');

        $calendar = new WorkingHours($world->semester);
        $this->assertFalse(
            $calendar->isWorkingDay(Carbon::parse('2027-02-14', LocalTime::TZ)),
            'the Sunday inside the released week is not a working day',
        );

        $offers = $this->engine->findAvailableSlots(
            (int) $world->users['a']->id,
            [$world->cohortA->id],
            (int) $world->semester->id,
            CancelledSlotFixture::SOURCE_WEEK,
            'L',
            60,
            (int) $world->sourceVenue->id,
        );

        $this->assertNotEmpty($offers, 'the freed interval is offered by the real engine');
        foreach ($offers as $offer) {
            $this->assertContains((int) $offer['day'], [0, 1, 2, 3, 4, 5], 'no Sunday cell is ever offered');
        }

        $freed = false;
        foreach ($offers as $offer) {
            if ((int) $offer['day'] === CancelledSlotFixture::SOURCE_DAY
                && (string) $offer['start_time'] === CancelledSlotFixture::SOURCE_START) {
                $freed = true;
            }
        }
        $this->assertTrue($freed, 'the freed 2 h original-room interval is bookable');
    }

    // ── shared assertions ────────────────────────────────────────────────

    /**
     * What the final source cancellation MUST leave behind (D4 + S09's
     * precondition): four released cells with ordinary available facts, the
     * exception, the audited original_release receipt, the other week
     * untouched and the underlying lecturer/cohort vectors freed.
     */
    private function assertReleasedSource(): void
    {
        $world = $this->world;
        $src = $world->sessions['src'];

        foreach ($world->slots['released'] as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the released cell is ordinary available');
            $this->assertNull($cell->class_session_id, 'the released cell loses its original owner');
            $this->assertSame(2, (int) $cell->version, 'the released cell gains exactly one version');
        }

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $src->id)
            ->where('week_number', CancelledSlotFixture::SOURCE_WEEK)
            ->where('reason', CancelledSlotFixture::SOURCE_REASON)
            ->count(), 'the cancellation exception exists');

        $audit = AuditLog::query()
            ->where('action', 'class_cancelled')
            ->where('details->class_session_id', (string) $src->id)
            ->where('details->week_number', (string) CancelledSlotFixture::SOURCE_WEEK)
            ->first();
        $this->assertNotNull($audit, 'the final cancellation audit exists');
        $this->assertSame(CancelledSlotFixture::SOURCE_REASON, (string) ($audit->details['reason'] ?? ''));

        $receipts = $this->flipReceipts($audit->details);
        $releasedIds = array_map(fn (TimeSlot $cell): int => (int) $cell->id, $world->slots['released']);
        $this->assertCount(count($releasedIds), $receipts, 'the original_release receipt records every changed cell');
        $this->assertEqualsCanonicalizing(
            $releasedIds,
            array_map(fn (array $receipt): int => (int) $receipt['slot_id'], $receipts),
        );
        foreach ($receipts as $receipt) {
            $this->assertSame('occupied', (string) $receipt['before']['status']);
            $this->assertSame((int) $src->id, (int) $receipt['before']['class_session_id']);
            $this->assertSame(1, (int) $receipt['before']['version']);
            $this->assertSame('available', (string) $receipt['after']['status']);
            $this->assertNull($receipt['after']['class_session_id']);
            $this->assertSame(2, (int) $receipt['after']['version']);
        }

        foreach ($world->slots['uncancelled'] as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status, 'the uncancelled week stays occupied');
            $this->assertSame((int) $src->id, (int) $cell->class_session_id);
            $this->assertSame(1, (int) $cell->version);
        }

        $busyLecturer = $this->engine->lecturerBusyRanges(
            (int) $world->semester->id,
            (int) $world->users['a']->id,
            CancelledSlotFixture::SOURCE_WEEK,
        );
        $this->assertFalse($this->coversInterval($busyLecturer, CancelledSlotFixture::SOURCE_DAY,
            CancelledSlotFixture::SOURCE_START, '12:00:00'), 'the cancellation frees the underlying lecturer vector');

        $busyCohort = $this->engine->cohortBusyRanges(
            (int) $world->semester->id,
            [$world->cohortA->id],
            CancelledSlotFixture::SOURCE_WEEK,
        );
        $this->assertFalse($this->coversInterval($busyCohort, CancelledSlotFixture::SOURCE_DAY,
            CancelledSlotFixture::SOURCE_START, '12:00:00'), 'the cancellation frees the underlying cohort vector');
    }

    /**
     * S11 — the exact same-class reuse: approval keeps its own occupied
     * landing and every junction, and the user's cancellation evidence is
     * preserved, never replaced by an approval stamp.
     *
     * @param  list<int>  $landingIds
     */
    private function assertExactReuseKeepsItsOwnLanding(CancelledSlotFixture $world, array $landingIds): void
    {
        foreach ($landingIds as $slotId) {
            $cell = TimeSlot::find($slotId);
            $this->assertSame('occupied', (string) $cell->status);
            $this->assertSame(4, (int) $cell->version, 'available(2) → pending(3) → occupied(4): the original release never re-freed its own landing');
        }

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $world->sessions['src']->id)
            ->where('week_number', CancelledSlotFixture::SOURCE_WEEK)
            ->count(), 'no second exception was stamped');
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $world->sessions['src']->id)
            ->where('week_number', CancelledSlotFixture::SOURCE_WEEK)
            ->where('reason', CancelledSlotFixture::SOURCE_REASON)
            ->count(), "the user's cancellation reason is preserved, never replaced by official_event");

        $audit = AuditLog::query()
            ->where('action', 'class_cancelled')
            ->where('details->class_session_id', (string) $world->sessions['src']->id)
            ->where('details->week_number', (string) CancelledSlotFixture::SOURCE_WEEK)
            ->first();
        $this->assertNotNull($audit, 'the original cancellation audit survives the approval');
        $this->assertSame(CancelledSlotFixture::SOURCE_REASON, (string) ($audit->details['reason'] ?? ''));
    }

    /** The requester's own week-5 occurrence is released by its approval. */
    private function assertRequesterOriginalReleased(CancelledSlotFixture $world, ClassSession $session): void
    {
        foreach ($world->slots[$this->sessionKey($world, $session)] as $own) {
            $own->refresh();
            $this->assertSame('available', (string) $own->status, 'the approval releases the requester original');
            $this->assertSame(2, (int) $own->version);
        }

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', (int) $session->id)
            ->where('week_number', CancelledSlotFixture::PIN_WEEK)
            ->count(), 'the pin exception exists exactly once (stamp-if-absent, never replaced)');
    }

    /**
     * The source's released interval after a NON-exact reuse landed elsewhere:
     * it stays available at its released version, untouched by the approval.
     */
    private function assertSourceIntervalFinalState(CancelledSlotFixture $world, bool $landingIsTheInterval): void
    {
        if ($landingIsTheInterval) {
            return; // the landing assertions already covered the occupied interval
        }

        foreach ($world->slots['released'] as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the released interval is untouched by a foreign approval');
            $this->assertSame(2, (int) $cell->version);
        }
    }

    /** @return list<array<string, mixed>> every before/after flip receipt inside a details tree */
    private function flipReceipts(mixed $node): array
    {
        $out = [];

        if (is_array($node)) {
            if (isset($node['before'], $node['after'])
                && is_array($node['before'])
                && is_array($node['after'])
                && array_key_exists('slot_id', $node)) {
                $out[] = $node;
            }

            foreach ($node as $child) {
                $out = array_merge($out, $this->flipReceipts($child));
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $busy
     */
    private function coversInterval(array $busy, int $day, string $start, string $end): bool
    {
        foreach ($busy as $row) {
            if ((int) $row['day'] === $day
                && (string) $row['start'] < $end
                && (string) $row['end'] > $start) {
                return true;
            }
        }

        return false;
    }

    private function sessionKey(CancelledSlotFixture $world, ClassSession $session): string
    {
        foreach ($world->sessions as $key => $candidate) {
            if ((int) $candidate->id === (int) $session->id) {
                return $key;
            }
        }

        throw new \RuntimeException('session is not part of the fixture world');
    }

    /**
     * Build the blocker twist and return its submit facts:
     * [proposer, session, pinWeek, anchor, span, expectedReason].
     *
     * @return array{0: User, 1: ClassSession, 2: int, 3: TimeSlot, 4: int, 5: string}
     */
    private function blockerTwist(string $key): array
    {
        $world = $this->world;
        $released = $world->slots['released'];

        return match ($key) {
            // a real pending booking takes the 10:30 cell of the released span first
            'room' => (function () use ($world, $released) {
                $holder = $world->createSession('', 'd', $world->altModule, 2, '13:00:00', '15:00:00',
                    $world->altVenue, 'L', [$world->cohortC->id], CancelledSlotFixture::UNCANCELLED_WEEK);
                // the holder OCC-reserves the EXISTING 10:30 cell (no duplicate row)
                $world->occupyCellWithPendingRequest($holder, CancelledSlotFixture::UNCANCELLED_WEEK, $released[1]);

                return [$world->users['b'], $world->sessions['b1'], CancelledSlotFixture::PIN_WEEK, $released[0], CancelledSlotFixture::SOURCE_SPAN, 'slot_not_available'];
            })(),

            // the submitting lecturer's own class overlaps the released time
            'lecturer' => (function () use ($world, $released) {
                $world->createSession('', 'b', $world->sourceModule, CancelledSlotFixture::SOURCE_DAY, '09:00:00', '13:00:00',
                    $world->altVenue, 'L', [$world->cohortC->id], null);

                return [$world->users['b'], $world->sessions['b1'], CancelledSlotFixture::PIN_WEEK, $released[0], CancelledSlotFixture::SOURCE_SPAN, 'engine_precheck_failed'];
            })(),

            // the submitting session's cohort is busy at the released time
            'cohort' => (function () use ($world, $released) {
                $world->createSession('', 'c', $world->sourceModule, CancelledSlotFixture::SOURCE_DAY, '10:00:00', '12:00:00',
                    $world->altVenue, 'L', [$world->cohortB->id], null);

                return [$world->users['b'], $world->sessions['b1'], CancelledSlotFixture::PIN_WEEK, $released[0], CancelledSlotFixture::SOURCE_SPAN, 'engine_precheck_failed'];
            })(),

            // ten seats against a twenty-student cohort
            'capacity' => [$world->users['b'], $world->sessions['b1'], CancelledSlotFixture::PIN_WEEK,
                $world->slots['smallRoom'][0], 1, 'engine_precheck_failed'],

            // a practical session cannot land in a tutorial room
            'rule' => (function () use ($world, $released) {
                $session = $world->createSession('practical', 'b', $world->practicalModule, 0, '13:00:00', '15:00:00',
                    $world->sourceVenue, 'P', [$world->cohortB->id], CancelledSlotFixture::PIN_WEEK);

                return [$world->users['b'], $session, CancelledSlotFixture::PIN_WEEK, $released[0], 1, 'engine_precheck_failed'];
            })(),

            // the landing day is a holiday (a later week, so the notice window stays clean)
            'holiday' => (function () use ($world) {
                $world->createHoliday(CancelledSlotFixture::UNCANCELLED_WEEK, CancelledSlotFixture::SOURCE_DAY, 'Fixture Holiday');
                $cells = $world->createCells($world->altVenue, CancelledSlotFixture::UNCANCELLED_WEEK,
                    CancelledSlotFixture::SOURCE_DAY, '10:00:00', 1, 'available', null);

                return [$world->users['b'], $world->sessions['b1'], CancelledSlotFixture::PIN_WEEK, $cells[0], 1, 'holiday'];
            })(),

            // Tuesday 9 Feb is two working days ahead of the frozen Monday
            'notice' => [$world->users['b'], $world->sessions['b1'], CancelledSlotFixture::PIN_WEEK,
                $world->slots['tooSoon'][0], 1, 'slot_too_soon'],

            // a live request already carries the (src, week 2) pair
            'active-request' => (function () use ($world, $released) {
                $world->createPendingRequest($world->sessions['src'], CancelledSlotFixture::SOURCE_WEEK,
                    $world->altVenue, CancelledSlotFixture::SOURCE_WEEK, CancelledSlotFixture::SOURCE_DAY, '09:00:00', 1);

                return [$world->users['a'], $world->sessions['src'], CancelledSlotFixture::SOURCE_WEEK, $released[0], 1, 'occurrence_not_conflicted'];
            })(),

            // week 3's own occurrence is still running at the same coordinates
            'uncancelled-week' => [$world->users['a'], $world->sessions['src'], CancelledSlotFixture::SOURCE_WEEK,
                $world->slots['uncancelled'][0], 1, 'slot_not_available'],

            // the owner's own pending make-up overlaps the released interval
            'makeup' => (function () use ($world, $released) {
                // a2's live make-up OCC-reserves the 10:00 cell of the alt grid
                $world->occupyCellWithPendingRequest($world->sessions['a2'], CancelledSlotFixture::PIN_WEEK, $world->slots['altRoom'][1]);
                ClassException::create([
                    'class_session_id' => (int) $world->sessions['a2']->id,
                    'week_number' => 6,
                    'reason' => 'official_event',
                ]);

                return [$world->users['a'], $world->sessions['a2'], 6, $released[0], CancelledSlotFixture::SOURCE_SPAN, 'engine_precheck_failed'];
            })(),

            default => throw new \RuntimeException("unknown blocker case [$key]"),
        };
    }
}
