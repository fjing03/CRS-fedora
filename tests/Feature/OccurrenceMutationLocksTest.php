<?php

namespace Tests\Feature;

use App\Actions\OriginalOccurrenceReleaseException;
use App\Actions\ReleaseRequestSlots;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\OccurrenceMutationLocks;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * SDD cancelled-slot-reuse A05/A07 — `OccurrenceMutationLocks`, the D4 lock
 * boundary, under the strengthened full-claim law:
 *
 *   · the helper refuses to run outside the caller's transaction;
 *   · the union covers the extra requests' landing cells even in ANOTHER
 *     room, and a nested attached release reuses the held locks;
 *   · a stale caller model (the session row moved behind its back) is
 *     membership drift — the fresh plan needs cells the estimate did not
 *     hold, and the helper refuses before any write instead of growing the
 *     lock union out of order.
 *
 * DatabaseMigrations, not RefreshDatabase: the transaction-guard test needs a
 * connection with NO wrapping transaction, and these tests write no audit rows
 * (the widened CHECK's down() only declines on leftover class_cancelled rows).
 */
final class OccurrenceMutationLocksTest extends TestCase
{
    use DatabaseMigrations;

    private const WEEK = 12;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => 'CSR002',
            'label' => 'Mutation Locks Fixture',
            'start_date' => '2026-09-21', // a Monday
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $this->venue = Venue::create([
            'room_code' => 'CS201',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);

        $this->module = Module::create([
            'module_code' => 'CS2002',
            'module_name' => 'Mutation Locks Module',
            'allowed_session_types' => 'L,T,P',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer']);
    }

    private function makeSession(string $start, string $end): ClassSession
    {
        return ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 1,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
    }

    /** @return list<TimeSlot> */
    private function occupyOccurrence(ClassSession $session, int $week): array
    {
        $cells = [];
        $cursor = Carbon::parse((string) $session->start_time);
        $end = Carbon::parse((string) $session->end_time);

        while ($cursor->lessThan($end)) {
            $start = $cursor->format('H:i:s');
            $cells[] = TimeSlot::create([
                'semester_id' => (int) $session->semester_id,
                'class_session_id' => (int) $session->id,
                'week_number' => $week,
                'day_of_week' => (int) $session->day_of_week,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => (int) $session->venue_id,
                'status' => 'occupied',
                'version' => 1,
            ]);
            $cursor->addMinutes(30);
        }

        return $cells;
    }

    public function test_lock_requires_an_active_caller_transaction(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $this->occupyOccurrence($session, self::WEEK);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('active transaction');

        app(OccurrenceMutationLocks::class)->lock([['session' => $session, 'week' => self::WEEK]]);
    }

    public function test_the_union_covers_an_extra_requests_landing_block_in_another_room(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // the extra request's landing block sits in ANOTHER room, another week
        $otherVenue = Venue::create([
            'room_code' => 'CS-LOCK',
            'capacity' => 30,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);
        $landing = TimeSlot::create([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => null,
            'week_number' => self::WEEK + 1,
            'day_of_week' => 4,
            'start_time' => '16:00:00',
            'end_time' => '16:30:00',
            'venue_id' => (int) $otherVenue->id,
            'status' => 'pending',
            'version' => 2,
        ]);
        $extra = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => (int) $landing->id,
            'replacement_span' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        ReplacementRequestSlot::create([
            'replacement_request_id' => $extra->id,
            'time_slot_id' => (int) $landing->id,
        ]);

        $result = DB::transaction(function () use ($session, $extra): array {
            app(OccurrenceMutationLocks::class)->lock(
                [['session' => $session, 'week' => self::WEEK]],
                [(int) $extra->id],
            );

            // the nested attached release reuses the already-held locks — the
            // other-room landing block frees cleanly, no out-of-order lock
            return (new ReleaseRequestSlots)($extra, false, null, true);
        });

        $this->assertSame([(int) $landing->id], $result, 'the other-room block was freed');
        $this->assertSame('available', (string) $landing->refresh()->status);

        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status, 'the originals were not part of this release');
        }
    }

    public function test_a_stale_session_model_is_membership_drift_not_a_grown_union(): void
    {
        $session = $this->makeSession('09:00:00', '10:00:00');
        $cells = $this->occupyOccurrence($session, self::WEEK);

        // another class books the destination room's coordinates — the rows
        // the session's FRESH plan will need once its venue row is moved
        $movedVenue = Venue::create([
            'room_code' => 'CS-MOVED',
            'capacity' => 30,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T,P',
        ]);
        $occupant = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'venue_id' => (int) $movedVenue->id,
            'session_type' => 'L',
        ]);
        $this->occupyOccurrence($occupant, self::WEEK);

        // the caller's model is already stale: the row moved behind its back,
        // so the FRESH plan needs cells the provisional estimate (built from
        // the stale coordinates) never held
        $item = ['session' => $session, 'week' => self::WEEK];
        ClassSession::query()->whereKey($session->id)->update(['venue_id' => (int) $movedVenue->id]);

        try {
            DB::transaction(fn () => app(OccurrenceMutationLocks::class)->lock([$item]));
            $this->fail('a stale model must be drift, never a grown lock union');
        } catch (OriginalOccurrenceReleaseException $e) {
            $this->assertSame('membership_drift', $e->reason);
        }

        foreach ($cells as $cell) {
            $this->assertSame('occupied', (string) $cell->refresh()->status, 'nothing was written');
        }
        $this->assertSame(0, DB::table('class_exceptions')->count());
    }
}
