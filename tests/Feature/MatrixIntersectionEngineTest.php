<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MatrixIntersectionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_s17_integration_real_seed_known_scenario(): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");

        $this->seed();

        $semesterId = (int) DB::table('semesters')->value('id');

        $lecturer = User::query()
            ->join('lecturers', 'lecturers.user_id', '=', 'users.id')
            ->where('lecturers.staff_id', '4288')
            ->firstOrFail();

        $cohort = Cohort::query()
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->where('programmes.programme_code', 'DFT')
            ->where('cohorts.current_year', 2)
            ->where('cohorts.semester', 1)
            ->where('cohorts.tutorial_group', 1)
            ->firstOrFail();

        $venueB002 = Venue::query()->where('room_code', 'B002')->firstOrFail();

        $engine = new MatrixIntersectionEngine;

        $start = hrtime(true);
        $results = $engine->findAvailableSlots((int) $lecturer->id, [(int) $cohort->id], $semesterId, 5, 'L', 60);
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        $this->assertNotEmpty($results, 'Expected at least one green window for 4288 / DFT2(S1)G1, week 5');

        foreach ($results as $result) {
            $this->assertNotSame(2, $result['day'], 'No window may fall on holiday day 2 (S-17)');
        }

        $pinned = [
            'day' => 1,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'venue_code' => 'B002',
        ];

        $hasPinnedWindow = false;

        foreach ($results as $result) {
            if ($result['day'] === $pinned['day']
                && $result['start_time'] === $pinned['start_time']
                && $result['end_time'] === $pinned['end_time']
                && $result['venue_code'] === $pinned['venue_code']) {
                $hasPinnedWindow = true;

                break;
            }
        }

        $this->assertTrue(
            $hasPinnedWindow,
            sprintf('Expected pinned window %s (S-17) in results', json_encode($pinned))
        );

        $timeSlotIds = [];

        foreach ($results as $result) {
            foreach ($result['time_slot_ids'] as $timeSlotId) {
                $timeSlotIds[] = $timeSlotId;
            }
        }

        $this->assertSame(
            0,
            DB::table('time_slots')->whereIn('id', $timeSlotIds)->where('status', '!=', 'available')->count(),
            'Every cell of every returned window must still be available (S-17)'
        );

        $this->assertLessThan(
            500,
            $elapsedMs,
            sprintf('Engine call took %.1fms; expected < 500ms (NFR 1.1 / E-15)', $elapsedMs)
        );

        $pinnedSlot = TimeSlot::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', 5)
            ->where('day_of_week', 1)
            ->where('start_time', '10:00:00')
            ->where('venue_id', $venueB002->id)
            ->firstOrFail();

        $this->assertTrue(
            $engine->validateSlot((int) $pinnedSlot->id, (int) $lecturer->id, [(int) $cohort->id], 'L', 5),
            'Pinned slot must validate green (S-13)'
        );

        $holidaySlot = TimeSlot::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', 5)
            ->where('day_of_week', 2)
            ->firstOrFail();

        $this->assertFalse(
            $engine->validateSlot((int) $holidaySlot->id, (int) $lecturer->id, [(int) $cohort->id], 'L', 5),
            'Day-2 slot must not validate (S-10)'
        );
    }
}
