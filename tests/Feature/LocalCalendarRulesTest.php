<?php

namespace Tests\Feature;

use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Services\ReplacementRequestMapper;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SDD `local-calendar-rules` (option D, 2026-11-08): the few rules that ask
 * "what is TODAY?" must ask the CAMPUS calendar, not the UTC app clock.
 *
 * The incident window: Malaysia is +08, so between 00:00 and 08:00 MYT the UTC
 * clock still says yesterday. Every expectation below is pinned at
 * **Mon 9 Nov 2026, 00:30 MYT** (= Sun 8 Nov 16:30 UTC) — the exact moment the
 * two clocks disagree about the DATE.
 *
 * The STAFF booking rules (`WorkingHours`) are pinned here too: they were
 * already converted to campus time before reading a calendar, and this change
 * must leave their answers byte-identical at the same instant.
 */
final class LocalCalendarRulesTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    /** Mon 9 Nov 2026 00:30 MYT — the UTC clock still reads Sunday 8 Nov. */
    private const FROZEN_UTC = '2026-11-08 16:30:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => 'LC001',
            'label' => 'Local Calendar Fixture',
            'start_date' => '2026-09-21',   // Monday, week 1 day 0
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);
    }

    private function freezeCampusMidnight(): void
    {
        Carbon::setTestNow(Carbon::parse(self::FROZEN_UTC, 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_two_clocks_disagree_about_the_date_at_this_moment(): void
    {
        $this->freezeCampusMidnight();

        // the framework clock (APP timezone, UTC) — the one the bug used
        $this->assertSame('2026-11-08', today()->toDateString(), 'the UTC clock still says Sunday');
        $this->assertSame('2026-11-09', LocalTime::dateString(), 'the campus clock says Monday');
    }

    public function test_days_since_counts_campus_days_not_utc_hours(): void
    {
        $this->freezeCampusMidnight();

        $this->assertSame(49, LocalTime::daysSince('2026-09-21'), '21 Sep → 9 Nov = 49 days');
        $this->assertSame(1, LocalTime::daysSince('2026-11-08'), 'yesterday (campus) is 1 day ago');
        $this->assertSame(0, LocalTime::daysSince('2026-11-09'), 'today (campus) is 0');
    }

    /**
     * The staff-facing proof: `WorkingHours` already converted to campus time, so
     * this change must not move a single deadline. Same instant, same answers.
     */
    public function test_the_staff_working_hours_rules_are_unchanged_at_the_same_instant(): void
    {
        $this->freezeCampusMidnight();

        $hours = new WorkingHours($this->semester);

        // campus Monday 9 Nov 2026 → the 3rd working day is Thursday 12 Nov
        $this->assertSame('2026-11-12', $hours->noticeCutoff()->toDateString());
        $this->assertFalse($hours->isBookableOnDate(Carbon::parse('2026-11-11', LocalTime::TZ)));
        $this->assertTrue($hours->isBookableOnDate(Carbon::parse('2026-11-12', LocalTime::TZ)));

        // the 3-working-hour auto-reject rule reads the same instant either way
        $start = Carbon::parse('2026-11-09 10:00:00', LocalTime::TZ);
        $this->assertTrue($hours->isTooSoon(Carbon::parse(self::FROZEN_UTC, 'UTC'), $start), '2h30m left → too soon');
        $start4h = Carbon::parse('2026-11-09 12:00:00', LocalTime::TZ);
        $this->assertFalse($hours->isTooSoon(Carbon::parse(self::FROZEN_UTC, 'UTC'), $start4h), '4h30m left → fine');
    }

    public function test_a_class_that_already_happened_reads_completed_not_approved(): void
    {
        $this->freezeCampusMidnight();

        $mapper = app(ReplacementRequestMapper::class);
        $request = new ReplacementRequest;
        $request->status = 'approved';

        // the class ran on campus-Sunday 8 Nov; the UTC clock still calls that "today"
        $this->assertSame(
            'completed',
            $mapper->effectiveStatus($request, Carbon::parse('2026-09-21'), '2026-11-08'),
            'a class dated yesterday (campus) is Completed — even before 08:00 MYT',
        );
        $this->assertSame(
            'approved',
            $mapper->effectiveStatus($request, Carbon::parse('2026-09-21'), '2026-11-09'),
            'a class dated today (campus) is still Approved',
        );
        $this->assertStringContainsString('1 day ago', $mapper->statusSentence('completed', '2026-11-08'));
    }

    public function test_the_landing_week_is_the_campus_week_even_early_on_a_monday(): void
    {
        $this->freezeCampusMidnight();

        $timeline = new class
        {
            use ResolvesTimetableTimeline;

            public function weekIndex(): int
            {
                return $this->currentTimelineWeekIndex();
            }
        };

        // 9 Nov 2026 is week 8 of the semester that starts 21 Sep 2026 —
        // the UTC clock would answer 7 (last week).
        $this->assertSame(7, $timeline->weekIndex(), 'index 7 = Week 8');
    }

    public function test_the_active_semester_is_the_campus_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 16:30:00', 'UTC'));   // Sun 20 Sep, 00:30 MYT of the 21st

        // campus date is 21 Sep (the semester's first day) although UTC says 20 Sep
        $this->assertSame('2026-09-21', LocalTime::dateString());
        $this->assertSame($this->semester->id, Semester::active()?->id);
    }
}
