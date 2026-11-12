<?php

namespace Tests\Feature;

use App\Actions\CancelClassBatch;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SDD cancel-whole-day, task T2 — `CancelClassBatch`.
 *
 * The laws this file pins (design F2):
 *   · every item is re-checked first, and ONE bad item writes NOTHING;
 *   · the day's classes are written in one transaction, each with an audit row;
 *   · a pending request on a cancelled occurrence is CLOSED with it — status,
 *     `decided_at`, its slots released, its junction rows gone;
 *   · the day path enforces the 2-working-day window, the holiday ban and the
 *     ownership scope (the single-class path passes `enforceWindow: false`, which
 *     `ReplacementFlowTest` keeps proving).
 */
final class CancelClassBatchTest extends TestCase
{
    /* RefreshDatabase, not DatabaseMigrations: a test here leaves `class_cancelled`
       audit rows behind, and the per-test migrate:rollback that DatabaseMigrations
       runs cannot narrow the widened CHECK back (the 2026-10-27 migration's down()
       declines for exactly that reason) — the rollback then fails on the leftover
       row. A wrapping transaction never needs that rollback. */
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        foreach (['users', 'semesters', 'venues', 'modules', 'class_sessions', 'time_slots',
            'replacement_requests', 'replacement_request_slots', 'audit_logs', 'class_exceptions', 'holidays'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
            );
        }
    }

    /** A week whose class date is comfortably in the future (inside the semester). */
    private const WEEK = 12;

    private function owner(): User
    {
        return User::query()->whereIn('id', ClassSession::query()->select('lecturer_id'))->firstOrFail();
    }

    /** A second session of the same lecturer, same weekday, one hour later. */
    private function secondSession(User $owner, ClassSession $first): ClassSession
    {
        $start = date('H:i:s', strtotime((string) $first->start_time) + 3600);

        return ClassSession::create([
            'semester_id' => $first->semester_id,
            'module_id' => $first->module_id,
            'lecturer_id' => $owner->id,
            'day_of_week' => $first->day_of_week,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 3600),
            'venue_id' => $first->venue_id,
            'session_type' => $first->session_type,
        ]);
    }

    /** A pending request holding a 2-cell block on that occurrence. */
    private function pendingRequest(ClassSession $session, int $week): ReplacementRequest
    {
        $slotIds = [];
        for ($k = 0; $k < 2; $k++) {
            $start = date('H:i:s', strtotime('15:00:00') + $k * 1800);
            $slotIds[] = (int) TimeSlot::create([
                'semester_id' => (int) $session->semester_id,
                'class_session_id' => null,
                'week_number' => $week + 1,
                'day_of_week' => 4,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => $session->venue_id,
                'status' => 'pending',
                'version' => 2,
            ])->id;
        }

        $request = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
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

        $request->setAttribute('fixture_slot_ids', $slotIds);

        return $request;
    }

    public function test_the_day_is_cancelled_in_one_go_with_one_audit_row_each(): void
    {
        $owner = $this->owner();
        $first = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();
        $second = $this->secondSession($owner, $first);

        $result = (new CancelClassBatch)($owner, [
            ['session' => $first, 'week' => self::WEEK],
            ['session' => $second, 'week' => self::WEEK],
        ], 'medical_leave');

        $this->assertTrue($result['ok']);
        $this->assertSame(2, count($result['cancelled']));
        $this->assertSame(0, $result['requestsClosed']);

        foreach ([$first, $second] as $session) {
            $this->assertSame(1, ClassException::query()
                ->where('class_session_id', $session->id)
                ->where('week_number', self::WEEK)->count(), 'one exception per class');
        }

        $this->assertSame(2, AuditLog::query()->where('action', 'class_cancelled')->count());
    }

    public function test_a_pending_request_is_closed_with_its_class(): void
    {
        $owner = $this->owner();
        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();
        $request = $this->pendingRequest($session, self::WEEK);
        $slotIds = $request->getAttribute('fixture_slot_ids');

        $result = (new CancelClassBatch)($owner, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['requestsClosed'], 'the day cancel reports what it withdrew');

        $request->refresh();
        $this->assertSame('cancelled', (string) $request->status);
        $this->assertNotNull($request->decided_at);

        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertSame('available', (string) $slot->status, 'the block is released');
            $this->assertSame(3, (int) $slot->version);
        }

        $this->assertSame(0, ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)->count(), 'the receipt is deleted');

        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $session->id)->where('week_number', self::WEEK)->count());
    }

    public function test_one_old_class_refuses_the_whole_day_and_writes_nothing(): void
    {
        $owner = $this->owner();
        $first = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();
        $second = $this->secondSession($owner, $first);

        // week 2 is far behind the 2-working-day window
        $result = (new CancelClassBatch)($owner, [
            ['session' => $first, 'week' => self::WEEK],
            ['session' => $second, 'week' => 2],
        ], 'annual_leave');

        $this->assertFalse($result['ok']);
        $this->assertSame('too_old', $result['refused']);
        $this->assertSame(0, ClassException::query()->count(), 'all-or-nothing: the valid class was NOT cancelled');
    }

    public function test_another_lecturers_class_refuses_the_whole_day(): void
    {
        $owner = $this->owner();
        $mine = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();
        $theirs = ClassSession::query()->where('lecturer_id', '!=', $owner->id)->firstOrFail();

        $result = (new CancelClassBatch)($owner, [
            ['session' => $mine, 'week' => self::WEEK],
            ['session' => $theirs, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertFalse($result['ok']);
        $this->assertSame('not_owner', $result['refused']);
        $this->assertSame(0, ClassException::query()->count());
    }

    public function test_a_holiday_refuses_the_day(): void
    {
        $owner = $this->owner();
        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        Holiday::create([
            'semester_id' => (int) $session->semester_id,
            'week_number' => self::WEEK,
            'day_of_week' => (int) $session->day_of_week,
            'label' => 'Batch Holiday',
        ]);

        $result = (new CancelClassBatch)($owner, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertFalse($result['ok']);
        $this->assertSame('holiday', $result['refused']);
        $this->assertSame(0, ClassException::query()->count());
    }

    public function test_an_occurrence_that_is_already_cancelled_is_refused(): void
    {
        $owner = $this->owner();
        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        ClassException::create([
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'reason' => 'medical_leave',
        ]);

        $result = (new CancelClassBatch)($owner, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'annual_leave');

        $this->assertFalse($result['ok']);
        $this->assertSame('already_cancelled', $result['refused']);
    }

    public function test_an_invalid_reason_is_refused_before_anything_is_written(): void
    {
        $owner = $this->owner();
        $session = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        $result = (new CancelClassBatch)($owner, [
            ['session' => $session, 'week' => self::WEEK],
        ], 'because_i_said_so');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_reason', $result['refused']);
        $this->assertSame(0, ClassException::query()->count());
    }
}
