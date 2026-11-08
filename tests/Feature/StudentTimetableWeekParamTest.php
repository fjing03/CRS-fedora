<?php

namespace Tests\Feature;

use App\Concerns\ResolvesTimetableTimeline;
use App\Livewire\StudentMyTimetable;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD `student-side-health-check` S4 + S5 (2026-11-08).
 *
 * S4: a student notification links `/student-my-timetable-ui?week=N`, so the page
 * must open on THAT week. Anything else (no param, junk, out-of-range) keeps the
 * "today" behaviour.
 *
 * S5: the component no longer passes the dead `notifCount` to the layout — this
 * test renders the real page, so a layout that needed it would fail here.
 */
final class StudentTimetableWeekParamTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => 'WP001',
            'label' => 'Week Param Fixture',
            'start_date' => '2026-09-21',   // Monday, week 1 day 0
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);
    }

    /** The week the page would open on with no `?week=` (today's week, 1-based). */
    private function currentWeek(): int
    {
        $timeline = new class
        {
            use ResolvesTimetableTimeline;

            public function index(): int
            {
                return $this->currentTimelineWeekIndex();
            }
        };

        return $timeline->index() + 1;
    }

    public function test_a_week_query_param_opens_that_week(): void
    {
        $this->actingAs(User::factory()->student()->create());

        Livewire::withQueryParams(['week' => 9])
            ->test(StudentMyTimetable::class)
            ->assertSet('week', 9);
    }

    public function test_without_a_param_the_page_opens_on_todays_week(): void
    {
        $this->actingAs(User::factory()->student()->create());

        Livewire::test(StudentMyTimetable::class)->assertSet('week', $this->currentWeek());
    }

    public function test_out_of_range_or_junk_weeks_fall_back_to_todays_week(): void
    {
        $this->actingAs(User::factory()->student()->create());

        foreach (['99', '0', '-3', 'abc', ''] as $junk) {
            Livewire::withQueryParams(['week' => $junk])
                ->test(StudentMyTimetable::class)
                ->assertSet('week', $this->currentWeek());
        }
    }

    public function test_the_last_week_of_the_semester_is_still_a_valid_param(): void
    {
        $this->actingAs(User::factory()->student()->create());

        Livewire::withQueryParams(['week' => $this->semester->week_count])
            ->test(StudentMyTimetable::class)
            ->assertSet('week', 14);
    }
}
