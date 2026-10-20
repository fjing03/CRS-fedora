<?php

namespace Tests\Feature;

use App\Actions\ApproveRequest;
use App\Actions\RejectRequest;
use App\Actions\SubmitReplacementRequest;
use App\Mail\ReplacementApprovalNotice;
use App\Mail\ReplacementDecided;
use App\Mail\ReplacementSubmitted;
use App\Models\AuditLog;
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
use App\Services\ApprovalValidity;
use App\Services\ReplacementRequestMapper;
use App\Services\ValidityResult;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * SDD span-aware-approval-flow — the decision-flow contract (spec
 * decision-flow + display-reads + notifications; grill Q1–Q13 rulings).
 *
 * FIXTURE SHAPE: DatabaseMigrations + $this->seed() + max(id)+1 setval — same
 * pattern as SpanReservationTest. Roles: 5425-style PL = lecturer + is_pl.
 */
final class ApprovalWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        foreach (['users', 'semesters', 'venues', 'modules', 'class_sessions', 'time_slots',
            'replacement_requests', 'replacement_request_slots', 'audit_logs',
            'class_exceptions', 'programmes', 'cohorts'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
            );
        }
    }

    // ───── fixture ─────

    /**
     * Craft $span AVAILABLE slots WITHOUT a request row (the submit-mail
     * test needs a clean anchor the submit action itself can reserve).
     *
     * @return array<int, int>
     */
    private function craftSlotsOnly(ClassSession $session, int $span, int $slotWeek): array
    {
        $slotIds = [];
        for ($k = 0; $k < $span; $k++) {
            $st = date('H:i:s', strtotime('14:00:00') + $k * 1800);
            $slotIds[] = TimeSlot::create([
                'semester_id' => $session->semester_id,
                'class_session_id' => null,
                'week_number' => $slotWeek,
                'day_of_week' => $session->day_of_week,
                'start_time' => $st,
                'end_time' => date('H:i:s', strtotime($st) + 1800),
                'venue_id' => $session->venue_id,
                'status' => 'available',
                'version' => 1,
            ])->id;
        }

        return $slotIds;
    }

    /** A second PL for the race/world-decide fixtures. */
    private function makePl(string $name): User
    {
        return $this->lecturer($name, true);
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
     * A pending span request + its decidability context:
     * [slotIds(span), requester, pl, request]. The requester's session owns a
     * SEEDED cohort (the mail leg needs students; FR 1.9).
     */
    private function pendingFixture(int $span = 4): array
    {
        $pl = $this->makePl('Approval PL');
        $requester = $this->lecturer('Approval Requester');

        $semester = DB::table('semesters')->first();

        $venue = Venue::create([
            'room_code' => 'AWF'.random_int(100, 999),
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);
        $module = Module::create([
            'module_code' => 'APW'.random_int(1000, 9999),
            'module_name' => 'Approval Fixture Module',
            'allowed_session_types' => 'L,T,P',
        ]);
        $session = ClassSession::create([
            'semester_id' => (int) $semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $requester->id,
            'day_of_week' => 3,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $venue->id,
            'session_type' => 'T',
        ]);

        $cohortId = (int) Cohort::query()->value('id');
        DB::table('session_cohorts')->insert([
            ['class_session_id' => $session->id, 'cohort_id' => $cohortId],
        ]);

        [$slotIds, $request] = $this->craftSpan($requester, $session, $span);

        return [$slotIds, $requester, $pl, $request];
    }

    /**
     * A contiguous span of $span available slots (14:00..) + the pending row
     * + its junction receipt.
     *
     * @return array{0: array<int, int>, 1: ReplacementRequest}
     */
    private function craftSpan(User $owner, ClassSession $session, int $span, int $slotWeek = 7, int $requestWeek = 6): array
    {
        ClassException::create(['class_session_id' => (int) $session->id, 'week_number' => (int) $requestWeek, 'reason' => 'official_event']);

        $slotIds = [];
        for ($k = 0; $k < $span; $k++) {
            $st = date('H:i:s', strtotime('14:00:00') + $k * 1800);
            $slotIds[] = TimeSlot::create([
                'semester_id' => $session->semester_id,
                'class_session_id' => null,
                'week_number' => $slotWeek,
                'day_of_week' => $session->day_of_week,
                'start_time' => $st,
                'end_time' => date('H:i:s', strtotime($st) + 1800),
                'venue_id' => $session->venue_id,
                'status' => 'available',
                'version' => 1,
            ])->id;
        }

        $request = ReplacementRequest::create([
            'semester_id' => $session->semester_id,
            'proposer_id' => $owner->id,
            'class_session_id' => $session->id,
            'week_number' => $requestWeek,
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

        // the POST-SUBMIT state: a pending request RESERVED its whole span —
        // the slots sit `pending` (version++) exactly as the submit path left them
        foreach ($slotIds as $slotId) {
            DB::table('time_slots')->where('id', $slotId)->update(['status' => 'pending', 'version' => 2]);
        }

        return [$slotIds, $request];
    }

    // ───── decision-flow R1: the approve contract ─────

    public function test_happy_path_approve_flips_span_and_stamps_slots(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['conflict_reason']);

        $r = $request->refresh();
        $this->assertSame('approved', (string) $r->status);
        $this->assertSame($pl->id, (int) $r->approver_id);
        $this->assertNotNull($r->decided_at);

        foreach ($slotIds as $i => $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertSame('occupied', (string) $slot->status);
            $this->assertSame(3, (int) $slot->version, "slot #$i: 1 +2(reserve) +1(decide flip)");
        }

        // the junction rows STAY (the LIVE receipt)
        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        // the audit count contract: ONE summary (occ_validation_result pinned)
        // + FOUR per-slot flip rows = 5 total
        $this->assertSame(1, AuditLog::query()->where('replacement_request_id', $r->id)
            ->where('action', 'approved')->where('time_slot_id', $slotIds[0])
            ->where('old_status', 'pending')->where('new_status', 'occupied')
            ->where('occ_validation_result', 'success')->count(), 'the summary row');
        $this->assertSame(5, AuditLog::query()->where('replacement_request_id', $r->id)
            ->where('action', 'approved')->count(), 'summary + per-slot');

        // the LEGAL action token (a 'decided' token would CHECK-fail the txn)
        $this->assertSame(0, AuditLog::query()->where('action', 'decided')->count());
    }

    public function test_approve_note_rides_the_summary_audit_details(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $result = app(ApproveRequest::class)($pl, (int) $request->id, '  matches venue policy  ');

        $this->assertTrue($result['ok']);
        $audit = AuditLog::query()->where('replacement_request_id', $request->id)
            ->where('action', 'approved')
            ->where('occ_validation_result', 'success')
            ->firstOrFail();

        $this->assertSame('matches venue policy', $audit->details['approver_note']);
        $this->assertArrayHasKey('slots', $audit->details);
    }

    public function test_approve_without_a_note_has_no_approvers_note_key(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        $audit = AuditLog::query()->where('replacement_request_id', $request->id)
            ->where('action', 'approved')
            ->where('occ_validation_result', 'success')
            ->firstOrFail();

        $this->assertArrayNotHasKey('approver_note', $audit->details, 'blank note fabricates nothing');
    }

    // ───── decision-flow R2: reject ─────

    public function test_reject_releases_the_span_and_stamps_reason(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $session = $request->classSession;

        $result = app(RejectRequest::class)($pl, (int) $request->id, 'Slot conflict');

        $this->assertTrue($result['ok']);

        $r = $request->refresh();
        $this->assertSame('rejected', (string) $r->status);
        $this->assertSame($pl->id, (int) $r->approver_id, 'one identity slot for decide-rows');
        $this->assertSame('Slot conflict', (string) $r->rejection_reason);
        $this->assertNotNull($r->decided_at);

        foreach ($slotIds as $i => $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertSame('available', (string) $slot->status);
            $this->assertSame(3, (int) $slot->version, "slot #$i: 1 +2(reserve) +1(release)");
        }

        // the junction rows DELETE (the release family's flip+DELETE shape)
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());

        // summary + per-flip audits (5 rows), no stamp of any exception
        $this->assertSame(5, AuditLog::query()->where('replacement_request_id', $r->id)
            ->where('action', 'rejected')->count());
        $this->assertSame(0, ClassException::query()->whereNot('class_session_id', (int) $session->id)->count(), 'no OTHER class was stamped');
    }

    public function test_reject_without_a_reason_is_blocked_server_side(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $machine = app(RejectRequest::class)($pl, (int) $request->id, '   ');

        $this->assertFalse($machine['ok']);
        $this->assertSame('A rejection reason is required.', $machine['conflict_reason']);
        // nothing released
        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(4, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
    }

    public function test_reject_other_free_text_is_stored_verbatim(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $typed = 'Policy: the maker-up must land before week 9 — rebook.';
        $this->assertTrue(app(RejectRequest::class)($pl, (int) $request->id, $typed)['ok']);

        $this->assertSame($typed, (string) $request->refresh()->rejection_reason, 'verbatim — no server preset list');
    }

    // ───── the validity contract (R4) ─────

    public function test_double_decide_races_to_exactly_one_winner(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $pl2 = $this->makePl('Second PL');

        $first = app(ApproveRequest::class)($pl, (int) $request->id);
        $second = app(ApproveRequest::class)($pl2, (int) $request->id);

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok']);
        $this->assertSame(ApprovalValidity::ALREADY_DECIDED, $second['conflict_reason']);
        $this->assertSame('approved', (string) $request->refresh()->status);
        $this->assertSame($pl->id, (int) $request->approver_id);
    }

    public function test_deciding_a_moved_row_surfaces_already_decided(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $request->forceFill(['status' => 'cancelled'])->save();

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($result['ok']);
        $this->assertSame(ApprovalValidity::ALREADY_DECIDED, $result['conflict_reason']);
    }

    public function test_a_drifted_span_rejects_the_whole_submit(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        // a racing actor flips the TAIL slot before the decision
        DB::table('time_slots')->where('id', $slotIds[3])->update(['status' => 'occupied', 'version' => 5]);

        $result = app(ApproveRequest::class)($pl, (int) $request->id);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('The block runs into an existing booking at', (string) $result['conflict_reason']);
        // ZERO flips survived (the two-phase discipline)
        $this->assertSame('pending', (string) TimeSlot::find($slotIds[0])->status);
        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->where('replacement_request_id', $request->id)->count());
    }

    // ───── decision-flow R3: the original-release (D7/T5) ─────

    public function test_approve_stamps_official_event_and_frees_the_original_rows(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $session = $request->classSession;

        // the original occurrence's OWN booked rows (any earlier week)
        $originalIds = [];
        foreach ([1, 2] as $di) {
            $originalIds[] = TimeSlot::create([
                'semester_id' => $session->semester_id,
                'class_session_id' => $session->id,
                'week_number' => 6,
                'day_of_week' => $session->day_of_week,
                'start_time' => date('H:i:s', strtotime('08:00:00') + $di * 1800),
                'end_time' => date('H:i:s', strtotime('08:30:00') + $di * 1800),
                'venue_id' => $session->venue_id,
                'status' => 'occupied',
                'version' => 1,
            ])->id;
        }

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        // the exception stamp
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', 6)
            ->where('reason', 'official_event')
            ->count(), 'the stamp rides the approve txn');

        // the original rows freed
        foreach ($originalIds as $i => $id) {
            $slot = TimeSlot::find($id);
            $this->assertSame('available', (string) $slot->status, "original #$i freed");
        }

        // the release rider inside the SUMMARY audit (no separate row)
        $summary = AuditLog::query()->where('replacement_request_id', $request->id)
            ->where('old_status', 'pending')->where('action', 'approved')
            ->where('occ_validation_result', 'success')
            ->firstOrFail();
        $this->assertSame(
            ['class_session_id' => (int) $session->id, 'week_number' => 6,
                'exception_stamped' => true, 'freed_slots' => $originalIds],
            $summary->details['original_release'],
        );
    }

    public function test_approve_never_duplicates_a_preexisting_exception(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $session = $request->classSession;

        // a sick-leave exception already exists for (session, original week) —
        // the S8 fixture fact IS it (this test creates NO second row).
        ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', 6)
            ->update(['reason' => 'medical_leave']);

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', 6)->count(), 'the guard prevents a second write');
        $this->assertSame(0, ClassException::query()->where('reason', 'official_event')->count());
    }

    public function test_approve_frees_nothing_of_another_class(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $session = $request->classSession;

        // another class's rows share the room/time BUT a different session id
        $foreign = TimeSlot::create([
            'semester_id' => $session->semester_id,
            'class_session_id' => null, // a replacement-reserved row
            'week_number' => 6,
            'day_of_week' => $session->day_of_week,
            'start_time' => '08:00:00',
            'end_time' => '08:30:00',
            'venue_id' => $session->venue_id,
            'status' => 'occupied',
            'version' => 1,
        ]);

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        $this->assertSame('occupied', (string) TimeSlot::find($foreign->id)->status,
            'identity-pinned release never frees another holder');
    }

    public function test_reject_does_not_stamp_or_release(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $session = $request->classSession;

        TimeSlot::create([
            'semester_id' => $session->semester_id,
            'class_session_id' => $session->id,
            'week_number' => 6,
            'day_of_week' => $session->day_of_week,
            'start_time' => '08:00:00',
            'end_time' => '08:30:00',
            'venue_id' => $session->venue_id,
            'status' => 'occupied',
            'version' => 1,
        ]);

        $this->assertTrue(app(RejectRequest::class)($pl, (int) $request->id, 'Venue unavailable')['ok']);

        $this->assertSame(0, ClassException::query()->whereNot('class_session_id', (int) $session->id)->count(), 'no OTHER class was stamped');
        $this->assertSame('occupied', (string) TimeSlot::query()
            ->where('class_session_id', $session->id)->where('week_number', 6)->firstOrFail()->status);
    }

    // ───── data-driven mapper riders (T1's pins) ─────

    public function test_mapper_falls_back_data_driven_for_any_bucket(): void
    {
        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();
        $mapper = app(ReplacementRequestMapper::class);
        $start = Carbon::parse(now()->toDateString());

        // junction-present pending row → the junction's TRUE range
        $row = $mapper->toRow($request, $start, 'queue');
        $this->assertSame('14:00 – 16:00', $row['replacementTime']);
        $this->assertSame('valid', $row['slotValidity']);

        // decided rows: the badge is single-sourced 'valid' regardless
        $request->forceFill(['status' => 'rejected', 'approver_id' => null])->save();
        $row2 = $mapper->toRow($request, $start, 'history');
        $this->assertSame('valid', $row2['slotValidity']);
        $this->assertNull($row2['conflictReason']);

        // a legacy pending anchor-only row (no junction) → the anchor fallback
        $request2 = ReplacementRequest::create([
            'semester_id' => $request->semester_id,
            'proposer_id' => $requester->id,
            'class_session_id' => $request->class_session_id,
            'week_number' => 6,
            'replacement_time_slot_id' => $slotIds[0],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        $row3 = $mapper->toRow($request2, $start, 'history');
        $this->assertSame('14:00 – 14:30', $row3['replacementTime'], 'data-driven fallback, any bucket');
    }

    // ───── notifications R3 — the email queue contract ─────

    public function test_submit_queues_the_pl_mail(): void
    {
        [$anchorIds, $requester, $pl, $anchorRequest] = $this->pendingFixture();
        $session = $anchorRequest->classSession;

        Mail::fake();

        // a fresh span at week 9 (the active-block unique: no request row yet)
        ClassException::create(['class_session_id' => (int) $session->id, 'week_number' => 9, 'reason' => 'official_event']);
        $freshIds = $this->craftSlotsOnly($session, 4, 9);

        $action = app(SubmitReplacementRequest::class);
        $action($requester, $session, 9, $freshIds[0], null, 4);

        // FR 4.15: EVERY is_pl PL gets one queued copy (the seeder plants
        // multiple PLs; the count asserts ONE per PL).
        $plCount = User::query()->whereHas('lecturer', fn ($q) => $q->where('is_pl', true))->count();
        Mail::assertQueued(ReplacementSubmitted::class, $plCount);
    }

    public function test_approve_queues_decided_and_cohort_mails(): void
    {
        Mail::fake();

        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $this->assertTrue(app(ApproveRequest::class)($pl, (int) $request->id)['ok']);

        Mail::assertQueued(ReplacementDecided::class, 1);
        // the cohort leg (ReplacementApprovalNotice) asserts in the T18 suite
    }

    public function test_reject_queues_only_decided(): void
    {
        Mail::fake();

        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        $this->assertTrue(app(RejectRequest::class)($pl, (int) $request->id, 'Slot conflict')['ok']);

        Mail::assertQueued(ReplacementDecided::class, 1);
        Mail::assertNotQueued(ReplacementApprovalNotice::class);
    }

    public function test_q13_decide_own_request_still_notifies(): void
    {
        Mail::fake();

        [$slotIds, $requester, $pl, $request] = $this->pendingFixture();

        // the PL IS the proposer (user 5425 wrote their own request)
        $ownSlot = TimeSlot::create([
            'semester_id' => $request->semester_id,
            'class_session_id' => null,
            'week_number' => 7,
            'day_of_week' => $request->classSession->day_of_week,
            'start_time' => '20:00:00',
            'end_time' => '20:30:00',
            'venue_id' => $request->classSession->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);
        $ownRequest = ReplacementRequest::create([
            'semester_id' => $request->semester_id,
            'proposer_id' => $pl->id,
            'class_session_id' => $request->class_session_id,
            'week_number' => 7,
            'replacement_time_slot_id' => (int) $ownSlot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        ReplacementRequestSlot::create([
            'replacement_request_id' => $ownRequest->id,
            'time_slot_id' => (int) $ownSlot->id,
        ]);

        $this->assertTrue(app(RejectRequest::class)($pl, (int) $ownRequest->id, 'Insufficient notice')['ok']);

        Mail::assertQueued(ReplacementDecided::class, 1);
    }

    // ───── the validity class itself (T2's unit pins) ─────

    public function test_validity_result_value_object(): void
    {
        $valid = ValidityResult::valid();
        $this->assertTrue($valid->valid);
        $this->assertNull($valid->reason);

        $conflict = ValidityResult::conflict('x');
        $this->assertFalse($conflict->valid);
        $this->assertSame('x', $conflict->reason);
    }
}
