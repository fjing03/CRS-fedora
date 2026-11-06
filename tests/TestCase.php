<?php

namespace Tests;

use App\Models\Semester;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * FR 2.17's lead-time gate is SERVER side since 2026-11-06, so a fixture may
     * only propose a slot at least 3 working days ahead. Scanning fixtures (which
     * walk week/day pairs looking for a free one) use this instead of 1..14, so
     * they can never hand the submit action a slot it must refuse.
     *
     * Date-derived, not a fixed range: the list shrinks as the semester runs,
     * exactly like the product.
     *
     * @return array<int, array{0: int, 1: int}> [week, day] pairs whose DATE is bookable today
     */
    protected function bookablePairs(?Semester $semester = null): array
    {
        $semester ??= Semester::active();

        if ($semester === null) {
            return [];
        }

        $calendar = new WorkingHours($semester);
        $start = Carbon::parse((string) $semester->start_date);
        $pairs = [];

        for ($week = 1; $week <= (int) $semester->week_count; $week++) {
            for ($day = 0; $day <= 5; $day++) {
                $date = $start->copy()
                    ->startOfDay()
                    ->addWeeks($week - 1)
                    ->addDays($day);

                if ($calendar->isBookableOnDate($date)) {
                    $pairs[] = [$week, $day];
                }
            }
        }

        return $pairs;
    }
}
