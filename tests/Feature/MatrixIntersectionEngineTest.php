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

        // Derivation (dataset/import CSVs + DATASET-NOTES): 4288 teaches Thu
        // 09:00–11:00 only; DFT2(S1)G1 teaches Thu 09:00–11:00 and 13:30–14:30;
        // B002's only weekly block is Mon 14:00–15:00. So Thu 11:00–13:30 is
        // green for lecturer AND cohort with B002 free — the pinned window is
        // its first hour. Week 5 carries no canonical holiday.
        $pinned = [
            'day' => 3,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
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
            sprintf('Expected pinned window %s (re-pinned to real 202505 data) in results', json_encode($pinned))
        );

        // Canonical holidays (W8 Mon, W14 Thu, W14 Fri): no returned window may
        // fall on a holiday day of its week, and the week must still offer
        // windows on the holiday-free days (guards a vacuous all-empty pass).
        foreach ([[8, [0]], [14, [3, 4]]] as [$holidayWeek, $holidayDays]) {
            $weekResults = $engine->findAvailableSlots((int) $lecturer->id, [(int) $cohort->id], $semesterId, $holidayWeek, 'L', 60);
            $this->assertNotEmpty($weekResults, "Week {$holidayWeek} must still offer green windows off the holiday day(s).");
            foreach ($weekResults as $result) {
                $this->assertNotContains(
                    $result['day'],
                    $holidayDays,
                    "No window may fall on holiday W{$holidayWeek} day {$result['day']} (canonical holidays).",
                );
            }
        }

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
            ->where('day_of_week', 3)
            ->where('start_time', '11:00:00')
            ->where('venue_id', $venueB002->id)
            ->firstOrFail();

        $this->assertTrue(
            $engine->validateSlot((int) $pinnedSlot->id, (int) $lecturer->id, [(int) $cohort->id], 'L', 5),
            'Pinned slot must validate green (re-pinned to real 202505 data)'
        );

        $holidaySlot = TimeSlot::query()
            ->where('semester_id', $semesterId)
            ->where('week_number', 8)
            ->where('day_of_week', 0)
            ->firstOrFail();

        $this->assertFalse(
            $engine->validateSlot((int) $holidaySlot->id, (int) $lecturer->id, [(int) $cohort->id], 'L', 8),
            'W8-Monday slot must not validate (canonical holiday, replaces the old W5-Wednesday fixture)'
        );
    }
}
