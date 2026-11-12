<?php

namespace Tests\Feature;

use App\Actions\ReleaseDriftException;
use App\Actions\ReleaseRequestSlots;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SDD cancel-whole-day, task T1 — the ONE slot-release implementation.
 *
 * The three shipped callers (`CancelPendingRequest`, `RejectRequest`,
 * `AutoRejectRequest`) keep their own tests as the proof that nothing changed.
 * This file proves the helper itself, including the policy switch that made a
 * single implementation possible at all:
 *
 *   strict     a drifted slot throws (the caller's transaction rolls back)
 *   non-strict a drifted slot is skipped — no flip, and no row returned for it
 */
final class ReleaseRequestSlotsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        foreach (['users', 'semesters', 'venues', 'modules', 'class_sessions', 'time_slots',
            'replacement_requests', 'replacement_request_slots', 'audit_logs', 'class_exceptions'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
            );
        }
    }

    /** A pending request holding a 2-cell block for the class that needs it. */
    private function fixtureRequest(): array
    {
        $session = ClassSession::query()->firstOrFail();
        $semesterId = (int) $session->semester_id;

        $slotIds = [];
        for ($k = 0; $k < 2; $k++) {
            $start = date('H:i:s', strtotime('14:00:00') + $k * 1800);
            $slotIds[] = (int) TimeSlot::create([
                'semester_id' => $semesterId,
                'class_session_id' => null,
                'week_number' => 9,
                'day_of_week' => 3,
                'start_time' => $start,
                'end_time' => date('H:i:s', strtotime($start) + 1800),
                'venue_id' => $session->venue_id,
                'status' => 'pending',
                'version' => 2,
            ])->id;
        }

        $request = ReplacementRequest::create([
            'semester_id' => $semesterId,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
            'week_number' => 5,
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

        return [$request, $slotIds];
    }

    public function test_the_happy_path_frees_the_whole_block_and_deletes_the_receipt(): void
    {
        [$request, $slotIds] = $this->fixtureRequest();

        /* an ArrayObject, not an array: the hook is called from inside the action
           and an array captured with `use (&$x)` inside an arrow fn does not travel
           back out — the object does. */
        $audited = new \ArrayObject;
        $freed = DB::transaction(fn (): array => (new ReleaseRequestSlots)(
            $request,
            true,
            function (TimeSlot $slot) use ($audited): void {
                $audited[] = (int) $slot->id;
            },
        ));

        $this->assertSame($slotIds, $freed, 'every cell of the block, in lock order');
        $this->assertSame($slotIds, $audited->getArrayCopy(), 'the audit hook runs once per FREED slot');

        foreach ($slotIds as $slotId) {
            $slot = TimeSlot::find($slotId);
            $this->assertSame('available', (string) $slot->status);
            $this->assertSame(3, (int) $slot->version, '2 (reserved) + 1 (released)');
        }

        $this->assertSame(0, ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)->count(), 'the junction rows are gone');
    }

    public function test_strict_mode_throws_on_a_drifted_slot_and_frees_nothing(): void
    {
        [$request, $slotIds] = $this->fixtureRequest();

        // someone else took the second cell first
        TimeSlot::query()->whereKey($slotIds[1])->update(['status' => 'occupied']);

        try {
            DB::transaction(fn () => (new ReleaseRequestSlots)($request, true));
            $this->fail('strict mode must throw when a member is no longer pending');
        } catch (ReleaseDriftException $e) {
            $this->assertNotNull($e->slot, 'the failing slot travels with the refusal');
            $this->assertSame($slotIds[1], (int) $e->slot->id);
        }

        /* the transaction rolled back: the first cell is untouched and the receipt
           still stands — nothing was half-released */
        $this->assertSame('pending', (string) TimeSlot::find($slotIds[0])->status);
        $this->assertSame(2, (int) TimeSlot::find($slotIds[0])->version);
        $this->assertSame(2, ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)->count());
    }

    public function test_non_strict_mode_skips_the_drifted_slot_and_frees_the_rest(): void
    {
        [$request, $slotIds] = $this->fixtureRequest();

        TimeSlot::query()->whereKey($slotIds[1])->update(['status' => 'occupied']);

        $audited = new \ArrayObject;
        $freed = DB::transaction(fn (): array => (new ReleaseRequestSlots)(
            $request,
            false,
            function (TimeSlot $slot) use ($audited): void {
                $audited[] = (int) $slot->id;
            },
        ));

        $this->assertSame([$slotIds[0]], $freed, 'only the cell it actually freed');
        $this->assertSame([$slotIds[0]], $audited->getArrayCopy(), 'no audit row for the skipped cell');
        $this->assertSame('available', (string) TimeSlot::find($slotIds[0])->status);
        $this->assertSame('occupied', (string) TimeSlot::find($slotIds[1])->status, 'the other booking is left alone');
        $this->assertSame(2, (int) TimeSlot::find($slotIds[1])->version, 'and its version is untouched');
    }

    public function test_a_legacy_anchor_only_request_still_releases(): void
    {
        [$request, $slotIds] = $this->fixtureRequest();

        // pre-span requests carry no junction rows
        ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->delete();

        $freed = DB::transaction(fn (): array => (new ReleaseRequestSlots)($request, true));

        $this->assertSame([$slotIds[0]], $freed, 'the anchor is the fallback release set');
        $this->assertSame('available', (string) TimeSlot::find($slotIds[0])->status);
        $this->assertSame('pending', (string) TimeSlot::find($slotIds[1])->status, 'the sibling cell was never claimed');
    }
}
