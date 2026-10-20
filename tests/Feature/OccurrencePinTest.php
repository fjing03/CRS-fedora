<?php

namespace Tests\Feature;

use App\Actions\ApproveRequest;
use App\Actions\SubmitReplacementRequest;
use App\Livewire\ReplacementArrangement;
use App\Livewire\VenueTimetable;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
use App\Services\MatrixIntersectionEngine;
use App\Services\OCCValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD occurrence-week-pin-truthfulness — the frozen receipt suite
 * (design D-P8; specs/pin-truthfulness S1–S6, S8 + F5's keys):
 *
 * The orphaned-API debt (SDD debt-api-routes) means submitBlocks is invoked
 * DIRECTLY (the Livewire component call is the same wiring the blade uses —
 * `ReplacementArrangementTest`'s precedent).
 */
final class OccurrencePinTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /** AMSE2003(L) @ B100 — the incident's own class, keyed by SHAPE (never id 67). */
    private static function incidentSession(): ClassSession
    {
        $module = Module::where('module_code', 'AMSE2003')->firstOrFail();
        $lecture = $module->classSessions()->where('session_type', 'L')->firstOrFail();

        return $lecture;
    }

    private static function incidentUser(): User
    {
        $lecturerId = self::incidentSession()->lecturer_id;

        return User::findOrFail(Lecturer::findOrFail($lecturerId)->user_id);
    }

    /**
     * Anchor cells at the makeup week — the SEEDED rows first (the reserve
     * resolves BY start_time within the venue/week/day, so a seeded row wins
     * over a same-key create; asserting on the created row's id skips the
     * flipped one). Falls back to creates only for a missing cell.
     */
    private static function anchorIds(ClassSession $session, int $slotWeek): array
    {
        $ids = [];
        foreach (range(0, 3) as $k) {
            $st = date('H:i:s', strtotime('17:00:00') + $k * 1800);
            $row = TimeSlot::query()
                ->where('semester_id', $session->semester_id)
                ->where('week_number', $slotWeek)
                ->where('venue_id', $session->venue_id)
                ->where('day_of_week', (int) $session->day_of_week)
                ->where('start_time', $st)
                ->first();
            if ($row !== null && $row->status === 'available') {
                $ids[] = (int) $row->id;

                continue;
            }
            if ($row === null) {
                $ids[] = (int) TimeSlot::create([
                    'semester_id' => $session->semester_id,
                    'class_session_id' => null,
                    'week_number' => $slotWeek,
                    'day_of_week' => (int) $session->day_of_week,
                    'start_time' => $st,
                    'end_time' => date('H:i:s', strtotime($st) + 1800),
                    'venue_id' => (int) $session->venue_id,
                    'status' => 'available',
                    'version' => 1,
                ])->id;

                continue;
            }
            $ids[] = (int) $row->id;   // a non-available seeded cell — the action's own loser covers it
        }

        return $ids;
    }

    /** F5 keys (T9): BOTH statuses carry the four original* keys. */
    public function test_both_statuses_carry_the_original_payload_keys(): void
    {
        $session = $this->incidentSession();
        $proposer = $this->incidentUser();
        $venue = $session->venue;

        // the SUBMITTED fixture (one pending) + one APPROVED pair via S3's pin chain
        $anchorIds = $this->anchorIds($session, 3);
        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $this->assertTrue($action($proposer, $session->fresh(), 14, $anchorIds[0], null, 4)['ok']);

        $anchorIds2 = $this->anchorIds($session, 10);
        ClassException::create(['class_session_id' => (int) $session->id, 'week_number' => 6, 'reason' => 'official_event']);
        $this->assertTrue($action($proposer, $session->fresh(), 6, $anchorIds2[0], null, 4)['ok']);

        // the FIRST request approves (the merge-week 3 makeup becomes the
        // live replacement receipt — status 'replacement' in the payload)
        $pl = User::findOrFail(Lecturer::where('is_pl', true)->firstOrFail()->user_id);
        $first = ReplacementRequest::query()->where('week_number', 14)->firstOrFail();
        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $first->id)['ok']);

        $component = Livewire::withQueryParams(['venue' => $venue->room_code])->actingAs($proposer)->test(
            VenueTimetable::class,
        );

        $payload = $component->viewData('eventsByWeek');
        $events = [];
        foreach ((array) $payload as $weekEvents) {
            foreach ((array) $weekEvents as $e) {
                if (is_array($e) && isset($e['status']) && in_array($e['status'], ['pending', 'replacement'], true)) {
                    $events[] = $e;
                }
            }
        }

        $this->assertGreaterThanOrEqual(2, count($events), 'pending + approved rows both exist');
        $statuses = array_map(fn ($e) => $e['status'], $events);
        $this->assertTrue(in_array('pending', $statuses, true) && in_array('replacement', $statuses, true),
            'BOTH statuses asserted — json: '.json_encode($statuses));
        foreach ($events as $e) {
            $this->assertSame((string) $venue->room_code, (string) $e['originalVenue'], 'originalVenue '.json_encode($e['status']));
            $this->assertSame((string) $venue->room_name, (string) $e['originalVenueName']);
            $this->assertSame(substr((string) $session->start_time, 0, 5), (string) $e['originalStartTime'], 'the ORIGINAL clock');
            $this->assertSame(substr((string) $session->end_time, 0, 5), (string) $e['originalEndTime']);
        }
    }

    /** S8's seeder-premise assert FIRST (a future seed edit cannot flip the suite silently). */
    public function test_the_seeded_amse2003_l_pair_carries_the_holiday_fact_at_w14(): void
    {
        $session = $this->incidentSession();
        $fact = app(LectureSessionCatalog::class)->occurrenceFact($session->fresh(), 14);

        $this->assertNotNull($fact, 'the <AMSE2003 L, W14> pair MUST carry a fact');
        $this->assertSame('holiday', $fact['kind']);
        $this->assertStringContainsString('Public Holiday', $fact['reason']);
    }

    /** S2: a pin on a normal week → occurrence_not_conflicted (isolated: bookable slot + valid span). */
    public function test_a_pin_on_a_normal_week_is_rejected_pre_write(): void
    {
        $session = $this->incidentSession();
        $proposer = $this->incidentUser();

        // the anchor lives on a NORMAL week (W3 16:00..) — a bookable slot, valid span
        $anchorIds = $this->anchorIds($session, 3);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($proposer, $session->fresh(), 3, $anchorIds[0], null, 4);

        $this->assertFalse($result['ok']);
        $this->assertSame('occurrence_not_conflicted', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->count(), 'pre-write: no row, no reserve, no audit');
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame('available', (string) TimeSlot::find($anchorIds[0])->status);
    }

    /**
     * S1: no-pin with TWO blocks → the per-block loser lands at its OWN index
     * (the results[i]↔blocks[i] positional contract).
     */
    public function test_blocks_misaligns_never_shift_the_results(): void
    {
        $session = $this->incidentSession();
        $proposer = $this->incidentUser();

        // TWO blocks: the first is unpinned globally (the whole call sends no
        // occurrenceWeek — D-P2's per-block loser hits EVERY block; here we
        // assert the alignment contract: results length == blocks length, and
        // the loser copy rides the RIGHT index).
        $anchors = $this->anchorIds($session, 3);
        $anchors2 = $this->anchorIds($session, 10);

        $testable = Livewire::actingAs($proposer)->test(ReplacementArrangement::class);
        $testable->call('submitBlocks',
            json_encode([
                ['venue' => $session->venue->room_code, 'week' => 2, 'day' => (int) $session->day_of_week, 'startHour' => 18, 'endHour' => 22],
                ['venue' => $session->venue->room_code, 'week' => 9, 'day' => (int) $session->day_of_week, 'startHour' => 18, 'endHour' => 22],
            ]),
            $session->module->module_code,
            json_encode(['day' => (int) $session->day_of_week, 'start' => 2, 'venue' => $session->venue->room_code]),
            // NO occurrenceWeek — a context WITH the sessionId but NO pin key
            // (the direct-caller contract: the pin absent → the per-block loser)
            json_encode(['sessionId' => (int) $session->id]),
        );
        $results = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertCount(2, $results, 'results[i] stays aligned with blocks[i]');
        $this->assertSame('Pick the slot on the grid — the week being replaced must be the conflicted occurrence\'s own.', $results[0]['reason'], 'result[0] = block[0]');
        $this->assertArrayHasKey('reason', $results[1], 'result[1] = block[1] (no shift)');
    }

    /** S3: the holiday-week pin → accepted; the receipt's week = the ORIGINAL 14. */
    public function test_the_holiday_occurrence_books_through_the_fact_gate(): void
    {
        $session = $this->incidentSession();
        $proposer = $this->incidentUser();

        // the makeup lands at W3 (any free week); the PIN = the W14 fact pair
        $anchorIds = $this->anchorIds($session, 3);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($proposer, $session->fresh(), 14, $anchorIds[0], null, 4);

        $this->assertTrue($result['ok'], $result['conflict_reason'] ?? 'free path');

        $request = ReplacementRequest::query()->sole();
        $this->assertSame(14, (int) $request->week_number, 'the pin = the HOLIDAY occurrence (never the slot week)');
        $this->assertSame(3, (int) $request->replacementTimeSlot->week_number, 'the slot lives at the makeup week');
        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        // the POST-SUBMIT reserve state
        foreach ($anchorIds as $id) {
            $this->assertSame('pending', (string) TimeSlot::find($id)->status);
        }
    }

    /** S4: the same submit without a pin → rejected (the fallback's death). */
    public function test_the_pin_is_mandatory_for_the_buggy_week_contract(): void
    {
        $session = $this->incidentSession();
        $proposer = $this->incidentUser();
        $anchorIds = $this->anchorIds($session, 3);

        $testable = Livewire::actingAs($proposer)->test(ReplacementArrangement::class);
        $testable->call('submitBlocks',
            json_encode([['venue' => $session->venue->room_code, 'week' => 2, 'day' => (int) $session->day_of_week, 'startHour' => 18, 'endHour' => 22]]),
            $session->module->module_code,
            json_encode(['day' => (int) $session->day_of_week, 'start' => 16, 'venue' => $session->venue->room_code]),
            json_encode(['sessionId' => (int) $session->id]),   // the class resolves; the occurrenceWeek pin ABSENT
        );
        $results = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertFalse($results[0]['ok']);
        $this->assertSame('Pick the slot on the grid — the week being replaced must be the conflicted occurrence\'s own.', $results[0]['reason']);
        $this->assertSame(0, ReplacementRequest::query()->count());
    }

    /** S6: the approve chain stamps the ORIGINAL week and frees NOTHING (no W14 rows exist). */
    public function test_approving_the_w14_pin_stamps_the_original_pair_and_frees_nothing(): void
    {
        $session = $this->incidentSession();
        $proposer = $this->incidentUser();
        $anchorIds = $this->anchorIds($session, 3);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $this->assertTrue($action($proposer, $session->fresh(), 14, $anchorIds[0], null, 4)['ok']);

        // the PL: the seeded is_pl identity
        $pl = User::findOrFail(Lecturer::where('is_pl', true)->firstOrFail()->user_id);

        $request = ReplacementRequest::query()->sole();
        $result = app(ApproveRequest::class)($pl, (int) $request->id);
        $this->assertTrue($result['ok'], $result['conflict_reason'] ?? '');

        // the D3 stamp keys the ORIGINAL pair (the pin's truth)
        $this->assertDatabaseHas('class_exceptions', [
            'class_session_id' => $session->id,
            'week_number' => 14,
            'reason' => 'official_event',
        ]);

        // the no-free control: the release is IDENTITY-KEYED — no W14 row
        // carries THIS session's id (the holiday import leaves them
        // session-less), so nothing frees even though the seeded W14 grid
        // holds rows. The audit's original_release receipts the empty list.
        $audit = AuditLog::query()->where('action', 'approved')
            ->where('replacement_request_id', (int) $request->id)
            ->whereNotNull('details->original_release')
            ->firstOrFail();
        $this->assertSame([], array_values($audit->details['original_release']['freed_slots']),
            'the release freed NOTHING (identity-keyed)');
        $w14Before = TimeSlot::query()->where('week_number', 14)->count();

        // the makeup block stays the approved live receipt
        foreach ($anchorIds as $id) {
            $this->assertSame('occupied', (string) TimeSlot::find($id)->status);
        }
        $this->assertSame($w14Before, TimeSlot::query()->where('week_number', 14)->count(),
            'the W14 family total is untouched (the release freed nothing)');
    }
}
