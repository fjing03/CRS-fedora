<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Full-block reservation T1/T2 mechanics: the junction table is the LIVE
 * receipt — cascade on request delete, `time_slot_id` UNIQUE (one live link
 * per slot), and the model relation reads it. (The demo DB carries the same
 * additive table with ZERO rows — verified 2026-10-16; standing no-touch.)
 */
final class ReplacementRequestSlotTest extends TestCase
{
    use DatabaseMigrations;


    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        // Sequence reset (OccConcurrencyTest pattern) — non-transactional ids.
        foreach (['replacement_requests', 'replacement_request_slots', 'audit_logs'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
        }
    }

    /** A seeded available slot + a seeded class (the factories' FK graphs don't match the seed's users). */
    private function seededPair(): array
    {
        $slot = TimeSlot::query()->where('status', 'available')->firstOrFail();
        $request = ReplacementRequest::factory()->create([
            'class_session_id' => $slot->class_session_id ?? ClassSession::query()->firstOrFail()->id,
            'week_number' => (int) $slot->week_number,
            'replacement_time_slot_id' => $slot->id,
        ]);

        return [$slot, $request];
    }

    public function test_rows_cascade_on_request_delete(): void
    {
        [$slot, $request] = $this->seededPair();

        $junction = ReplacementRequestSlot::create([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot->id,
        ]);
        $this->assertDatabaseHas('replacement_request_slots', ['id' => $junction->id]);

        $request->delete();

        $this->assertDatabaseMissing('replacement_request_slots', ['id' => $junction->id]);
    }

    public function test_time_slot_id_is_unique_across_requests(): void
    {
        [$slot, $request] = $this->seededPair();
        ReplacementRequestSlot::create([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot->id,
        ]);

        // the second request targets ANOTHER slot in a different week (D1's partial unique
        // is (class_session, week) — the uniqueness under test is the JUNCTION's slot rule)
        $otherSlot = TimeSlot::query()
            ->where('status', 'available')
            ->whereNot('week_number', $slot->week_number)
            ->firstOrFail();
        $other = ReplacementRequest::factory()->create([
            'class_session_id' => $request->class_session_id,
            'week_number' => (int) $otherSlot->week_number,
            'replacement_time_slot_id' => $otherSlot->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        ReplacementRequestSlot::create([
            'replacement_request_id' => $other->id,
            'time_slot_id' => $slot->id,
        ]);
    }

    public function test_request_reads_the_live_receipt(): void
    {
        [$slot, $request] = $this->seededPair();
        ReplacementRequestSlot::create([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot->id,
        ]);

        $request->refresh();
        $this->assertSame(1, $request->requestSlots()->count());
        $this->assertSame($slot->id, $request->requestSlots()->first()->time_slot_id);
    }
}
