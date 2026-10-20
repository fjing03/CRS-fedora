<?php

namespace Tests\Feature;

use App\Actions\CancelPendingRequest;
use App\Actions\SubmitReplacementRequest;
use App\Http\Controllers\Api\ApiReadController;
use App\Livewire\MyRequestHistory;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use App\Services\OCCValidator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SDD full-block-reservation — the frozen T15 lettered suite (verify-stage
 * closure): the span path's end-to-end behaviors, ported 1:1 from the frozen
 * artifact requirements (R7 scenarios).
 *
 * FIXTURE SHAPE: DatabaseMigrations + $this->seed() (house pattern) — each
 * test builds its OWN four-slot contiguous run via `spanFixture()` so the
 * tests pin the CONTRACT, not ids. All writes run inside the test's own
 * transaction (prefixes reset at setval for id determinism).
 */
final class SpanReservationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        // The seeder inserts EXPLICIT ids — push every sequence past max(id) so
        // factory/model creates never collide (unlike the deterministic-1 pattern,
        // which is only safe where the test itself renumbers the table).
        foreach (['users', 'semesters', 'venues', 'modules', 'class_sessions', 'time_slots',
            'replacement_requests', 'replacement_request_slots', 'audit_logs'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
            );
        }
    }

    // ───── fixture ─────

    /**
     * A 2-lecturer span fixture: contiguously 4×30-min rows (14:00..16:00) in
     * one interior week/day, two owned class sessions (the same D1 shape as
     * the demo's drives), two users, and a pending request per winner.
     * Returns [slotIds(4), winnerUser, winnerSession, loserUser, loserSession].
     */
    private function spanFixture(): array
    {
        $winner = User::factory()->create(['role' => 'lecturer', 'name' => 'Span Winner']);
        $loser = User::factory()->create(['role' => 'lecturer', 'name' => 'Span Loser']);

        $module = Module::create([
            'module_code' => 'SPANX'.random_int(100, 999),
            'module_name' => 'Span Fixture Module',
            'allowed_session_types' => 'L,T,P',
        ]);

        $semester = Semester::create([
            'semester_code' => 'S'.random_int(100, 999),
            'label' => 'Span Fixture Semester',
            'start_date' => now()->subMonth()->startOfDay(),
            'end_date' => now()->addMonth()->startOfDay(),
            'week_count' => 14,
        ]);

        $venue = Venue::create([
            'room_code' => 'SPAN'.random_int(10, 99),
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);

        $winnerSession = ClassSession::create([
            'semester_id' => $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $winner->id,
            'day_of_week' => 3,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'T',
        ]);

        $loserSession = ClassSession::create([
            'semester_id' => $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $loser->id,
            'day_of_week' => 4,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'T',
        ]);

        // The engine pre-check derives busy vectors from session_cohorts — it
        // demands a non-empty cohort map, so attach the SEEDED cohort to both
        // sessions (their lecture windows never overlap this span's Wed 14:00).
        $cohortId = (int) Cohort::query()->value('id');
        DB::table('session_cohorts')->insert([
            ['class_session_id' => $winnerSession->id, 'cohort_id' => $cohortId],
            ['class_session_id' => $loserSession->id, 'cohort_id' => $cohortId],
        ]);

        // The span: 4 contiguous 30-min available slots (14:00..16:00) on d3
        // (the winner session's own day, so the engine vector check passes).
        $slotIds = [];
        foreach (range(0, 3) as $k) {
            $st = date('H:i:s', strtotime('14:00:00') + $k * 1800);
            $slotIds[] = TimeSlot::create([
                'semester_id' => $semester->id,
                'class_session_id' => null,
                'week_number' => 5,
                'day_of_week' => 3,
                'start_time' => $st,
                'end_time' => date('H:i:s', strtotime($st) + 1800),
                'venue_id' => $venue->id,
                'status' => 'available',
                'version' => 1,
            ])->id;
        }

        // occurrence-week-pin-truthfulness S8 (the seeder-premise contract):
        // the submits pin the anchor's OWN week — the pair must carry a live
        // conflict fact or the new flat loser fires. official_event at
        // <winnerSession, 5> + <loserSession, 5> mirrors the repaired demo's
        // exact-truthful pair.
        ClassException::create(['class_session_id' => $winnerSession->id, 'week_number' => 5, 'reason' => 'official_event']);
        ClassException::create(['class_session_id' => $loserSession->id, 'week_number' => 5, 'reason' => 'official_event']);

        return [$slotIds, $winner, $winnerSession, $loser, $loserSession];
    }

    /** Creates a pending request + junction receipt for the span (the LIVE receipt shape). */
    private function linkSpan(int $userId, ClassSession $session, array $slotIds, int $week = 5): ReplacementRequest
    {
        $request = ReplacementRequest::create([
            'semester_id' => $session->semester_id,
            'proposer_id' => $userId,
            'class_session_id' => $session->id,
            'week_number' => $week,
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

        return $request;
    }

    /** Flips every span slot to pending via the version-checked OCC UPDATE (the two-phase shape). */
    private function reserveSpan(array $slotIds): void
    {
        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            DB::update(
                'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                ['pending', ((int) $slot->version) + 1, $slot->id, (int) $slot->version]
            );
        }
    }

    // ───── T15a — bounds suite (R7.5, R4.1) ─────

    public function test_bounds_derived_five_rejects_invalid_span_before_any_write(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);

        // a 3h ask derives span 6 → invalid_span (the FOCS 2h cap is a server-side rule).
        $result = $action($winner, $winnerSession, 5, $slotIds[0], null, 6);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_span', $result['conflict_reason']);
        // no write, no audit row
        $this->assertSame(0, ReplacementRequest::query()->where('proposer_id', $winner->id)->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'submitted')->count());
        // both surfaces: the action-level guard — a direct call with span 0 too
        $zero = $action($winner, $winnerSession, 5, $slotIds[0], null, 0);
        $this->assertSame('invalid_span', $zero['conflict_reason']);
    }

    public function test_bounds_span_three_is_legal(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);

        // span 3 (1.5h) is INSIDE the 0.5–2h bound — only the missing slot rows
        // (we seated only 4; a 3rd slot row 14:00-15:30 is 3 slots → all exist)
        $result = $action($winner, $winnerSession, 5, $slotIds[0], null, 3);
        $this->assertTrue($result['ok'], 'span 3 (1.5h) passes the bounds — the design legal case');

        $request = ReplacementRequest::query()->where('proposer_id', $winner->id)->firstOrFail();
        $this->assertSame(3, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
    }

    // ───── T15b — winner + race double-arm (R7.1–2) ─────

    public function test_winner_reserves_four_with_four_junction_rows_and_audits(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($winner, $winnerSession, 5, $slotIds[0], null, 4);

        $this->assertTrue($result['ok']);
        $request = ReplacementRequest::query()->where('proposer_id', $winner->id)->firstOrFail();
        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertSame('pending', (string) $slot->status);
            $this->assertSame(2, (int) $slot->version);
        }

        $this->assertSame(4, AuditLog::query()
            ->where('replacement_request_id', $request->id)
            ->where('occ_validation_result', 'success')
            ->count(), 'the per-slot SUCCESS audit rows (Q4)');
    }

    public function test_span_one_race_loses_with_slot_not_array_byte_mirror(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        // Winner reserves span 1 first.
        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $win = $action($winner, $winnerSession, 5, $slotIds[0], null, 1);
        $this->assertTrue($win['ok']);

        // Loser now targets THE SAME anchor slot (one second shy).
        $result = $action($loser, $loserSession, 5, $slotIds[0], null, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_not_available', $result['conflict_reason'], 'span-1 race: the byte-compat legacy shape');
        $this->assertSame(0, ReplacementRequest::query()->where('proposer_id', $loser->id)->count(), 'D5: loser request deleted');
    }

    // ───── T15d — peri-flip rollback (R7.4) ─────

    public function test_peri_flip_junction_unique_violation_rolls_back_and_surfaces_reserve_interrupted(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        // Pre-seed a phantom junction row on the LOSER's anchor slot so the
        // two-phase's step-5 junction INSERT hits the UNIQUE(time_slot_id)
        // violation AFTER the flips — the injectable peri-flip failure shape.
        $phantom = ReplacementRequest::create([
            'semester_id' => $winnerSession->semester_id,
            'proposer_id' => $loser->id,
            'class_session_id' => $winnerSession->id,
            'week_number' => 6, // a distinct D1 pair (6 ≠ the loser's submit week 5)
            'replacement_time_slot_id' => $slotIds[0],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        ReplacementRequestSlot::create([
            'replacement_request_id' => $phantom->id,
            'time_slot_id' => $slotIds[0],
        ]);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($loser, $loserSession, 5, $slotIds[0], null, 4);

        $this->assertFalse($result['ok']);
        $this->assertSame('reserve_interrupted', $result['conflict_reason'], 'the peri-flip surfaces non-500');

        // the TWO-PHASE rollback: ZERO slots left pending — the anchor stays available
        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertNotSame('pending', (string) $slot->status, 'the partial-flip trap is dead');
        }

        // the loser's request row is compensated away
        $this->assertSame(1, ReplacementRequest::query()->where('proposer_id', $loser->id)->count(), 'only the phantom remains');

        // the conflict audit row SURVIVES with `replacement_request_id` NULLed
        // (FR 4.12) and the `concurrent_reserve_conflict` forensic token.
        $survivor = AuditLog::query()
            ->where('action', 'submitted')
            ->whereNull('replacement_request_id')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($survivor, 'the post-rollback audit row survives');
        $this->assertSame('concurrent_reserve_conflict', (string) ($survivor->details['conflict_reason'] ?? ''), 'the forensic token rides the details JSON');
    }

    // ───── T15e — cancel family + engine (R3.1 + R7.9) ─────

    public function test_cancel_releases_the_whole_span_and_audits_each_flip(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        $winnerPending = ReplacementRequest::create([
            'semester_id' => $winnerSession->semester_id,
            'proposer_id' => $winner->id,
            'class_session_id' => $winnerSession->id,
            'week_number' => 5,
            'replacement_time_slot_id' => $slotIds[0],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            DB::update(
                'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
                ['pending', ((int) $slot->version) + 1, $slot->id, (int) $slot->version]
            );
            ReplacementRequestSlot::create([
                'replacement_request_id' => $winnerPending->id,
                'time_slot_id' => $slotId,
            ]);
        }

        $cancelled = app(CancelPendingRequest::class)($winner, $winnerPending->id);
        $this->assertTrue($cancelled);

        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertSame('available', (string) $slot->status, 'the span releases WHOLE');
            $this->assertSame(3, (int) $slot->version, 'version++ per slot');
        }

        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $winnerPending->id)->count());
        $this->assertSame(4, AuditLog::query()
            ->where('replacement_request_id', $winnerPending->id)
            ->where('action', 'cancelled')
            ->where('old_status', 'pending')
            ->where('new_status', 'available')
            ->count(), 'a per-flip audit row');
    }

    // ───── T15c — steady-state pair + kind discipline (R7.3, D2 step 3) ─────

    public function test_occupied_mid_span_reports_the_named_time_and_kind(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        // Make the TAIL cell occupied (a pre-existing booking) — the action's
        // span pre-resolve sees it BEFORE any write (the steady-state path).
        DB::table('time_slots')->where('id', $slotIds[3])->update(['status' => 'occupied']);

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($winner, $winnerSession, 5, $slotIds[0], null, 4);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_not_available', $result['conflict_reason']);
        $this->assertSame('occupied', $result['failing_kind'] ?? null, 'the discriminator fraction is row-backed occupied');
        $this->assertSame('15:30:00', $result['failing_start'] ?? null, 'the named time is truthful');
        $this->assertSame('16:00:00', $result['failing_end'] ?? null);
        $this->assertSame(0, ReplacementRequest::query()->where('proposer_id', $winner->id)->count());
        $this->assertSame(0, ReplacementRequestSlot::query()->count());
    }

    public function test_missing_mid_span_reports_the_derived_expected_time_and_kind(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        // Delete the 2nd planned cell — no ROW to read → the derived EXPECTED
        // time, kind 'missing', bounds-style copy (times dropped).
        DB::table('time_slots')->where('id', $slotIds[1])->delete();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($winner, $winnerSession, 5, $slotIds[0], null, 4);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_not_available', $result['conflict_reason']);
        $this->assertSame('missing', $result['failing_kind'] ?? null, 'the discriminator is derived-missing');
        $this->assertSame('14:30:00', $result['failing_start'] ?? null, 'the EXPECTED time (30 min after the anchor)');
        $this->assertSame('15:00:00', $result['failing_end'] ?? null);
    }

    // ───── T15e addendum — engine span vectors (R7.9) ─────

    public function test_busy_engine_tail_rejects_the_whole_submit_pre_reserve(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        // The shared cohort already owns a class INSIDE week 5 — a BUSY TAIL
        // (Wed 15:30–16:00, the cohort's lecture window vs the span's 2nd half).
        // Delete irrelevant rows first for a deterministic vector.
        DB::table('class_sessions')->where('id', $winnerSession->id)->update(['day_of_week' => 5]);
        // A cohort lecture occupying the TAIL cell on the span's day:
        // NOTE the fixture's S8 fact row for the loser session would exclude
        // its class from the engine's busy vector (cohortBusyRanges skips
        // week-exception sessions — the cancelled class doesn't meet); the
        // tail test uses that class ONLY as a vector prop, so drop its
        // exception row first (its submit never runs here).
        ClassException::query()
            ->where('class_session_id', $loserSession->id)
            ->where('week_number', 5)
            ->delete();
        DB::table('class_sessions')->where('id', $loserSession->id)->update([
            'day_of_week' => 3, 'start_time' => '15:30:00', 'end_time' => '16:00:00',
        ]);
        // time_slots rows for the loser's lecture venue? The engine's busy
        // vector is cohort-keyed: the loser's class session IS the cohort's
        // 15:30 booking — the span's tail 15:30 row now overlaps it.

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $result = $action($winner, $winnerSession, 5, $slotIds[0], null, 4);

        $this->assertFalse($result['ok'], 'the busy TAIL rejects the WHOLE submit pre-reserve');
        $this->assertSame('engine_precheck_failed', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->where('proposer_id', $winner->id)->count());
        $this->assertSame('available', (string) TimeSlot::find($slotIds[0])->status, 'nothing flipped pre-reserve');
    }

    // ───── T16b — read-model tests, API + history (D7.3–5/R5.3–5) ─────

    public function test_api_my_requests_reports_the_junction_true_range_for_pending(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $this->assertTrue($action($winner, $winnerSession, 5, $slotIds[0], null, 4)['ok']);

        // API myRequests() — the pending row reports the junction's min/max
        // span (14:00 – 16:00), not the anchor's 30 min. (The route itself is
        // orphaned/commented per SDD debt-api-routes — invoke the controller.)
        $this->actingAs($winner);
        $json = app(ApiReadController::class)->myRequests()->getData(true);
        $row = collect($json['requests'])->first(fn ($r) => ($r['status'] ?? '') === 'Pending');
        $this->assertNotNull($row, 'the pending row is in the API list');
        $this->assertSame('14:00 – 16:00', $row['replacementTime'], 'D7.3: the junction TRUE range');
    }

    public function test_history_page_pending_row_shows_the_true_range_and_cancel_reverts_to_anchor(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        $action = new SubmitReplacementRequest(new OCCValidator, new MatrixIntersectionEngine);
        $this->assertTrue($action($winner, $winnerSession, 5, $slotIds[0], null, 4)['ok']);

        $this->actingAs($winner);
        $rows = app(MyRequestHistory::class)->render()->getData()['payload']['requests'];
        $pending = collect($rows)->first(fn ($r) => $r['status'] === 'Pending');
        $this->assertNotNull($pending, 'the pending row renders');
        $this->assertSame('14:00 – 16:00', $pending['replacementTime'], 'D7.5: the junction TRUE range (a 2h pending is a 2h range)');

        // Cancel → the request's bucket flips to Cancelled → the legacy anchor
        // join resumes (the 30-min anchor range, NOT the junction range).
        $request = ReplacementRequest::query()->where('proposer_id', $winner->id)->firstOrFail();
        $this->assertTrue(app(CancelPendingRequest::class)($winner, (int) $request->id));

        $rows2 = app(MyRequestHistory::class)->render()->getData()['payload']['requests'];
        $cancelled = collect($rows2)->first(fn ($r) => $r['status'] === 'Cancelled');
        $this->assertNotNull($cancelled, 'the cancelled row renders');
        $this->assertSame('14:00 – 14:30', $cancelled['replacementTime'], 'the anchor-only read resumes after cancel');
    }

    public function test_legacy_anchor_only_cancel_byte_identical(): void
    {
        [$slotIds, $winner, $winnerSession, $loser, $loserSession] = $this->spanFixture();

        // LEGACY shape: a pending request WITHOUT junction rows (the anchor only).
        $legit = ReplacementRequest::create([
            'semester_id' => $winnerSession->semester_id,
            'proposer_id' => $winner->id,
            'class_session_id' => $winnerSession->id,
            'week_number' => 5,
            'replacement_time_slot_id' => $slotIds[0],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        DB::update(
            'UPDATE time_slots SET status = ?, version = ? WHERE id = ? AND version = ?',
            ['pending', 2, $slotIds[0], 1]
        );

        $cancelled = app(CancelPendingRequest::class)($winner, $legit->id);
        $this->assertTrue($cancelled);

        $this->assertSame('available', (string) TimeSlot::find($slotIds[0])->status);
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $legit->id)->count());
    }
}
