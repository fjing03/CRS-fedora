<?php

namespace Tests\Feature;

use App\Actions\OriginalOccurrenceReleaseException;
use App\Actions\OriginalOccurrenceReleaseReceipt;
use App\Actions\ReleaseOriginalOccurrenceSlots;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\OriginalOccurrencePlan;
use App\Services\OriginalOccurrenceReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The original-occurrence release action (SDD cancelled-slot-reuse, design
 * D3/D4, task A03): consumes a locked/revalidated plan, flips only the
 * `original` subset under exact before identity/status/owner/version
 * conditions, and refuses atomically on any refusal or stale member.
 *
 * The caller transaction is simulated with `DB::transaction` exactly as the
 * integration (cancellation/approval) will hold it: a throw rolls the whole
 * batch back, so a forced stale member can never leave a partial release.
 */
final class ReleaseOriginalOccurrenceSlotsTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private User $lecturer;

    private ClassSession $session;

    private OriginalOccurrenceReservation $planner;

    private ReleaseOriginalOccurrenceSlots $release;

    private const WEEK = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => 'ROR001',
            'label' => 'Release Receipt Fixture',
            'start_date' => '2026-09-21',
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $this->venue = Venue::create([
            'room_code' => 'OR201',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $this->module = Module::create([
            'module_code' => 'OR2001',
            'module_name' => 'Release Receipt Module',
            'allowed_session_types' => 'L,T',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer']);

        $this->session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);

        $this->planner = new OriginalOccurrenceReservation;
        $this->release = new ReleaseOriginalOccurrenceSlots;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cell(string $start, int $week = self::WEEK, array $overrides = []): TimeSlot
    {
        return TimeSlot::create(array_merge([
            'semester_id' => (int) $this->session->semester_id,
            'class_session_id' => (int) $this->session->id,
            'week_number' => $week,
            'day_of_week' => (int) $this->session->day_of_week,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => (int) $this->session->venue_id,
            'status' => 'occupied',
            'version' => 1,
        ], $overrides));
    }

    private function originalCells(): array
    {
        return [
            $this->cell('09:00:00', self::WEEK, ['version' => 2]),
            $this->cell('09:30:00', self::WEEK, ['version' => 5]),
            $this->cell('10:00:00', self::WEEK, ['version' => 3]),
            $this->cell('10:30:00', self::WEEK, ['version' => 9]),
        ];
    }

    /**
     * @param  list<int>  $slotIds
     * @param  array<string, mixed>  $overrides
     */
    private function requestHolding(array $slotIds, array $overrides = []): ReplacementRequest
    {
        $request = ReplacementRequest::create(array_merge([
            'semester_id' => (int) $this->session->semester_id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => (int) $this->session->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => $slotIds[0],
            'replacement_span' => count($slotIds),
            'status' => 'pending',
            'submitted_at' => now(),
        ], $overrides));

        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        return $request;
    }

    private function plan(): OriginalOccurrencePlan
    {
        return $this->planner->plan($this->session, self::WEEK);
    }

    // ------------------------------------------------------------------ S01

    public function test_it_releases_the_original_cells_with_receipt_versions_and_a_cleared_owner(): void
    {
        $cells = $this->originalCells();
        $otherWeek = $this->cell('09:00:00', 6); // the same session's next week stays untouched

        $receipt = DB::transaction(fn (): OriginalOccurrenceReleaseReceipt => ($this->release)($this->plan()));

        $this->assertCount(4, $receipt->changed);
        $this->assertSame([], $receipt->noOps);
        $this->assertFalse($receipt->isNoOp());
        $this->assertSame((int) $this->session->id, $receipt->classSessionId);
        $this->assertSame(self::WEEK, $receipt->weekNumber);

        // The flips follow the caller's lock order (slot id ascending).
        $ids = array_column($receipt->changed, 'slot_id');
        $expected = array_column($cells, 'id');
        sort($expected);
        $this->assertSame($expected, $ids);

        foreach ($cells as $index => $cell) {
            $flipped = $receipt->changed[$index];

            $this->assertSame($cell->start_time, $flipped['start_time']);
            $this->assertSame('occupied', $flipped['before']['status']);
            $this->assertSame((int) $this->session->id, $flipped['before']['class_session_id']);
            $this->assertSame($cell->version, $flipped['before']['version']);
            $this->assertSame('available', $flipped['after']['status']);
            $this->assertNull($flipped['after']['class_session_id']);
            $this->assertSame($cell->version + 1, $flipped['after']['version']);

            $row = TimeSlot::find($cell->id);
            $this->assertSame('available', (string) $row->status);
            $this->assertNull($row->class_session_id, 'the original owner is cleared ONLY on changed cells');
            $this->assertSame($cell->version + 1, (int) $row->version);
        }

        $otherWeekRow = TimeSlot::find($otherWeek->id);
        $this->assertSame('occupied', (string) $otherWeekRow->status, 'another week is never touched');
        $this->assertSame((int) $this->session->id, (int) $otherWeekRow->class_session_id);
        $this->assertSame(1, (int) $otherWeekRow->version);
    }

    public function test_a_sixty_minute_class_releases_its_two_cells(): void
    {
        $hour = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 3,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);

        $cells = [
            TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $hour->id,
                'week_number' => self::WEEK,
                'day_of_week' => 3,
                'start_time' => '14:00:00',
                'end_time' => '14:30:00',
                'venue_id' => $this->venue->id,
                'status' => 'occupied',
                'version' => 4,
            ]),
            TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $hour->id,
                'week_number' => self::WEEK,
                'day_of_week' => 3,
                'start_time' => '14:30:00',
                'end_time' => '15:00:00',
                'venue_id' => $this->venue->id,
                'status' => 'occupied',
                'version' => 6,
            ]),
        ];

        $receipt = DB::transaction(fn () => ($this->release)($this->planner->plan($hour, self::WEEK)));

        $this->assertSame([$cells[0]->id, $cells[1]->id], array_column($receipt->changed, 'slot_id'));
        $this->assertSame(5, (int) TimeSlot::find($cells[0]->id)->version);
        $this->assertSame(7, (int) TimeSlot::find($cells[1]->id)->version);
    }

    // -------------------------------------------------------- S02/S03 no-ops

    public function test_available_and_request_held_cells_are_no_ops_with_their_reasons(): void
    {
        $original = $this->cell('09:00:00', self::WEEK, ['version' => 4]);
        $availableOne = $this->cell('09:30:00', self::WEEK, ['status' => 'available', 'class_session_id' => null]);
        $availableDuplicate = $this->cell('09:30:00', self::WEEK, ['status' => 'available']);
        $held = $this->cell('10:00:00', self::WEEK, ['status' => 'pending', 'class_session_id' => null]);
        $availableTwo = $this->cell('10:30:00', self::WEEK, ['status' => 'available']);

        $request = $this->requestHolding([$held->id]);

        $receipt = DB::transaction(fn () => ($this->release)($this->plan()));

        $this->assertSame([$original->id], array_column($receipt->changed, 'slot_id'), 'only the original subset flips');
        $this->assertCount(3, $receipt->noOps);

        $noOpBySlot = array_column($receipt->noOps, null, 'slot_id');

        $ninthirty = $noOpBySlot[$availableOne->id];
        $this->assertSame('already_available', $ninthirty['reason']);
        $this->assertSame([$availableOne->id, $availableDuplicate->id], $ninthirty['duplicate_slot_ids'], 'duplicates are reported, never repaired');
        $this->assertNull($ninthirty['held_by_request_id']);

        $tenOclock = $noOpBySlot[$held->id];
        $this->assertSame('request_held', $tenOclock['reason']);
        $this->assertSame((int) $request->id, $tenOclock['held_by_request_id']);
        $this->assertSame([], $tenOclock['duplicate_slot_ids']);

        $tenthirty = $noOpBySlot[$availableTwo->id];
        $this->assertSame('already_available', $tenthirty['reason']);

        // The no-op rows are byte-identical: stale ids, statuses and versions stay.
        $this->assertSame('pending', (string) TimeSlot::find($held->id)->status);
        $this->assertSame(1, ReplacementRequestSlot::query()->where('time_slot_id', $held->id)->count(), 'the live receipt survives');
        $this->assertSame('available', (string) TimeSlot::find($availableDuplicate->id)->status);
        $this->assertSame((int) $this->session->id, (int) TimeSlot::find($availableDuplicate->id)->class_session_id, 'the stale id is not tidied away');
        $this->assertSame(1, (int) TimeSlot::find($availableDuplicate->id)->version);

        // And the one original cell flipped normally.
        $this->assertSame('available', (string) TimeSlot::find($original->id)->status);
        $this->assertNull(TimeSlot::find($original->id)->class_session_id);
        $this->assertSame(5, (int) TimeSlot::find($original->id)->version);
    }

    // ------------------------------------------------- S04/S08 atomic refusals

    public function test_a_refusing_plan_throws_and_the_caller_transaction_writes_nothing(): void
    {
        $cell = $this->cell('09:00:00', self::WEEK, ['version' => 3]);
        // 09:30, 10:00, 10:30 missing → the plan refuses.

        try {
            DB::transaction(fn () => ($this->release)($this->plan()));
            $this->fail('a refusing plan must stop the release');
        } catch (OriginalOccurrenceReleaseException $e) {
            $this->assertSame('missing_row', $e->reason);
            $this->assertNull($e->entry);
        }

        $row = TimeSlot::find($cell->id);
        $this->assertSame('occupied', (string) $row->status);
        $this->assertSame(3, (int) $row->version);
    }

    public function test_a_stale_member_refuses_the_whole_release_and_the_rollback_erases_the_batch(): void
    {
        $cells = $this->originalCells();
        $plan = $this->plan();

        // A concurrent flip lands between planning and the release: row 3's
        // version moved behind the plan's back.
        TimeSlot::query()->whereKey($cells[2]->id)->update(['version' => 99]);

        // Snapshot the externally drifted state: the rollback must return the
        // rows to EXACTLY this, including the row that was changed outside.
        $drifted = [];
        foreach (TimeSlot::query()->whereIn('id', array_column($cells, 'id'))->get() as $row) {
            $drifted[$row->id] = ['status' => (string) $row->status, 'version' => (int) $row->version];
        }

        try {
            DB::transaction(fn () => ($this->release)($plan));
            $this->fail('a stale member must refuse the whole release');
        } catch (OriginalOccurrenceReleaseException $e) {
            $this->assertSame('stale_member', $e->reason);
            $this->assertNotNull($e->entry);
            $this->assertSame($cells[2]->id, $e->entry->slotId);
        }

        // The two flips that already ran inside the transaction were rolled
        // back with it — nothing is half-released, and the externally changed
        // row keeps its injected drift (the rollback only erases the batch).
        foreach ($cells as $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame($drifted[$cell->id]['status'], (string) $row->status, 'the status is exactly where the external drift left it');
            $this->assertSame($drifted[$cell->id]['version'], (int) $row->version, 'the version is exactly where the external drift left it');
            $this->assertSame((int) $this->session->id, (int) $row->class_session_id);
        }
    }

    public function test_an_owner_or_status_drift_between_planning_and_release_refuses_too(): void
    {
        $cells = $this->originalCells();
        $plan = $this->plan();

        // The row lost its original owner after the plan was taken.
        TimeSlot::query()->whereKey($cells[1]->id)->update(['class_session_id' => null]);

        try {
            DB::transaction(fn () => ($this->release)($plan));
            $this->fail('a drifted owner must refuse the release');
        } catch (OriginalOccurrenceReleaseException $e) {
            $this->assertSame('stale_member', $e->reason);
        }

        foreach ($cells as $cell) {
            $this->assertSame('occupied', (string) TimeSlot::find($cell->id)->status);
        }
    }

    // ------------------------------------------------------------- repeatability

    public function test_a_repeat_release_after_success_is_a_structured_no_op(): void
    {
        $this->originalCells();

        $first = DB::transaction(fn () => ($this->release)($this->plan()));

        // Measure the real post-first-release versions — the repeat release
        // must not move them again.
        $firstVersions = [];
        foreach (TimeSlot::query()->where('week_number', self::WEEK)->where('day_of_week', 1)->get() as $row) {
            $firstVersions[$row->id] = (int) $row->version;
        }
        $this->assertSame([3, 6, 4, 10], array_values($firstVersions), 'the fixture versions were bumped exactly once');

        // Re-plan against the released rows: everything is already available.
        $second = DB::transaction(fn () => ($this->release)($this->plan()));

        $this->assertTrue($second->isNoOp());
        $this->assertSame([], $second->changed);
        $this->assertCount(4, $second->noOps);

        foreach ($second->noOps as $noOp) {
            $this->assertSame('already_available', $noOp['reason']);
        }

        foreach (TimeSlot::query()->where('week_number', self::WEEK)->where('day_of_week', 1)->orderBy('start_time')->get() as $row) {
            $this->assertSame('available', (string) $row->status);
            $this->assertNull($row->class_session_id);
            $this->assertSame($firstVersions[$row->id], (int) $row->version, 'a repeat release never bumps a version');
        }
    }

    // ------------------------------------------------- transaction / geometry guards

    public function test_a_release_outside_a_database_transaction_is_refused(): void
    {
        $cells = $this->originalCells();
        $plan = $this->plan();

        // RefreshDatabase itself runs the whole test inside a transaction, so
        // invoking release on the default connection never exercises the
        // no-transaction guard. Swap in a REAL connection whose transaction
        // level is 0: clone the default pgsql config (same testing DB) under a
        // new name and make it the default only while the guard is invoked.
        $originalConnection = DB::getDefaultConnection();
        config([
            'database.connections.releaseguard' => config("database.connections.{$originalConnection}"),
        ]);

        try {
            DB::setDefaultConnection('releaseguard');

            try {
                ($this->release)($plan);
                $this->fail('the release must refuse to run outside an active transaction');
            } catch (OriginalOccurrenceReleaseException $e) {
                $this->assertSame('transaction_required', $e->reason);
                $this->assertNull($e->entry);
            }
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('releaseguard');
        }

        // The guard threw before any query or flip ran: assert on the restored
        // default connection (inside the framework transaction) that every
        // fixture cell is byte-identical.
        foreach ($cells as $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('occupied', (string) $row->status, 'a refused release never flips anything');
            $this->assertSame($cell->version, (int) $row->version);
            $this->assertSame((int) $this->session->id, (int) $row->class_session_id);
        }
    }

    public function test_geometry_drift_without_a_version_bump_refuses_the_flip(): void
    {
        $cells = $this->originalCells();
        $otherVenue = Venue::create([
            'room_code' => 'OR202',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $plan = $this->plan();

        // The row's venue moved WITHOUT a version bump: the plan's complete
        // original identity coordinates no longer match, so the flip must
        // refuse even though the version and owner still line up.
        TimeSlot::query()->whereKey($cells[1]->id)->update(['venue_id' => $otherVenue->id]);

        try {
            DB::transaction(fn () => ($this->release)($plan));
            $this->fail('geometry drift without a version bump must refuse the release');
        } catch (OriginalOccurrenceReleaseException $e) {
            $this->assertSame('stale_member', $e->reason);
            $this->assertSame($cells[1]->id, $e->entry->slotId);
        }

        foreach ($cells as $cell) {
            $row = TimeSlot::find($cell->id);
            $this->assertSame('occupied', (string) $row->status);
            $this->assertSame($cell->version, (int) $row->version, 'the whole batch was rolled back');
            $this->assertSame((int) $this->session->id, (int) $row->class_session_id);
        }
    }
}
