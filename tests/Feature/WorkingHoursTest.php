<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Semester;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The auto-reject rule's clock (user brief "Auto-Reject Pending Requests",
 * 2026-11-06). TARUMT Sabah works Mon–Fri 08:30–17:30; evenings, weekends and
 * the semester's own `holidays` rows contribute nothing.
 *
 * The four rows of the brief's example table are cases 1–4 verbatim: they are
 * the contract, not an illustration.
 */
final class WorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-09-21 is a MONDAY — week 1 day 0, the timetable's own anchor.
        $this->semester = Semester::create([
            'semester_code' => 'WH001',
            'label' => 'Working Hours Fixture',
            'start_date' => '2026-09-21',
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);
    }

    /** A display-clock instant (MYT), never the UTC app clock. */
    private function at(string $stamp): Carbon
    {
        return Carbon::parse($stamp, LocalTime::TZ);
    }

    private function calendar(): WorkingHours
    {
        return new WorkingHours($this->semester);
    }

    /** A holiday on a real date, written the way the seeder writes it (week + day). */
    private function holiday(string $date, string $label = 'Fixture Holiday'): void
    {
        $start = Carbon::parse((string) $this->semester->start_date)->startOfDay();
        $days = (int) $start->diffInDays(Carbon::parse($date)->startOfDay());

        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => (int) floor($days / 7) + 1,
            'day_of_week' => $days % 7,
            'label' => $label,
        ]);
    }

    // ── the brief's four examples ─────────────────────────────────────────

    public function test_the_briefs_example_table_is_the_contract(): void
    {
        $calendar = $this->calendar();

        // Fri 6 Nov 11:30 → Fri 6 Nov 14:30 = 3 hours → AUTO-REJECT
        $this->assertSame(180, $calendar->gapMinutes($this->at('2026-11-06 11:30'), $this->at('2026-11-06 14:30')));
        $this->assertTrue($calendar->isTooSoon($this->at('2026-11-06 11:30'), $this->at('2026-11-06 14:30')));

        // Fri 6 Nov 11:30 → Fri 6 Nov 15:00 = 3.5 hours → remains PENDING
        $this->assertSame(210, $calendar->gapMinutes($this->at('2026-11-06 11:30'), $this->at('2026-11-06 15:00')));
        $this->assertFalse($calendar->isTooSoon($this->at('2026-11-06 11:30'), $this->at('2026-11-06 15:00')));

        // Fri 6 Nov 16:30 → Mon 9 Nov 09:30 = 1 h (Fri) + 1 h (Mon) = 2 hours → AUTO-REJECT
        $this->assertSame(120, $calendar->gapMinutes($this->at('2026-11-06 16:30'), $this->at('2026-11-09 09:30')));
        $this->assertTrue($calendar->isTooSoon($this->at('2026-11-06 16:30'), $this->at('2026-11-09 09:30')));

        // Fri 6 Nov 16:30 → Mon 9 Nov 11:00 = 1 h + 2.5 h = 3.5 hours → remains PENDING
        $this->assertSame(210, $calendar->gapMinutes($this->at('2026-11-06 16:30'), $this->at('2026-11-09 11:00')));
        $this->assertFalse($calendar->isTooSoon($this->at('2026-11-06 16:30'), $this->at('2026-11-09 11:00')));
    }

    // ── the calendar's own laws ───────────────────────────────────────────

    public function test_evenings_weekends_and_the_outside_hours_are_not_working_time(): void
    {
        $calendar = $this->calendar();

        // outside the bell: 17:30 → 08:30 next morning = 0 working minutes
        $this->assertSame(0, $calendar->gapMinutes($this->at('2026-11-05 17:30'), $this->at('2026-11-06 08:30')));

        // a whole weekend contributes nothing: Fri 17:30 → Mon 08:30 = 0
        $this->assertSame(0, $calendar->gapMinutes($this->at('2026-11-06 17:30'), $this->at('2026-11-09 08:30')));

        // Saturday is not a working day, so a Saturday proposal has no gap at all
        $this->assertFalse($calendar->isWorkingDay($this->at('2026-11-07 10:00')));
        $this->assertSame(0, $calendar->gapMinutes($this->at('2026-11-06 17:30'), $this->at('2026-11-07 12:00')));

        // a full working day is 9 hours (08:30–17:30)
        $this->assertSame(540, $calendar->gapMinutes($this->at('2026-11-06 08:30'), $this->at('2026-11-06 17:30')));
    }

    public function test_a_semester_holiday_is_skipped_like_a_weekend(): void
    {
        $this->holiday('2026-11-09', 'Deepavali');
        $calendar = $this->calendar();

        $this->assertFalse($calendar->isWorkingDay($this->at('2026-11-09 10:00')));
        $this->assertSame('Deepavali', $calendar->holidayLabel($this->at('2026-11-09 10:00')));

        // Fri 16:30 → Tue 09:30 = 1 h (Fri tail) + 1 h (Tue morning). Without the
        // holiday Monday's whole 9 hours would count, so 120 proves the skip.
        $this->assertSame(120, $calendar->gapMinutes($this->at('2026-11-06 16:30'), $this->at('2026-11-10 09:30')));

        // and the deadline walk skips the same day backwards
        $this->assertSame(
            '2026-11-06 16:30',
            $calendar->subtractWorkingMinutes($this->at('2026-11-10 08:30'), 60)->format('Y-m-d H:i'),
            'one hour before Tue 08:30 is Fri 16:30 — Monday is a holiday',
        );
    }

    public function test_the_deadline_is_the_inverse_of_the_gap(): void
    {
        $calendar = $this->calendar();
        $start = $this->at('2026-11-09 10:00');

        $deadline = $calendar->earliestApproval($start);

        // Mon 10:00 − 180 min: Monday opens at 08:30, so only 90 min exist before
        // it; the remaining 90 min come from Friday's tail (17:30 back to 16:00).
        $this->assertSame('2026-11-06 16:00', $deadline->format('Y-m-d H:i'));

        // ...and walking forward from that deadline returns the same 3 hours.
        $this->assertSame(WorkingHours::MIN_GAP_MINUTES, $calendar->gapMinutes($deadline, $start));
    }

    public function test_the_deadline_never_lands_outside_the_working_day(): void
    {
        $calendar = $this->calendar();

        // a same-day start inside the hours walks back inside the hours
        $this->assertSame(
            '2026-11-06 11:30',
            $calendar->earliestApproval($this->at('2026-11-06 14:30'))->format('Y-m-d H:i'),
        );

        // a start just after opening borrows from the previous working day's tail
        $this->assertSame(
            '2026-11-05 15:00',
            $calendar->earliestApproval($this->at('2026-11-06 09:00'))->format('Y-m-d H:i'),
        );
    }

    public function test_a_proposal_in_the_past_has_no_working_time_left(): void
    {
        $calendar = $this->calendar();

        $this->assertSame(0, $calendar->gapMinutes($this->at('2026-11-06 12:00'), $this->at('2026-11-06 09:00')));
        $this->assertTrue($calendar->isTooSoon($this->at('2026-11-06 12:00'), $this->at('2026-11-06 09:00')));
    }

    public function test_a_calendar_without_a_semester_counts_every_weekday(): void
    {
        $calendar = new WorkingHours(null);

        // no holiday list → Monday counts like any working day
        $this->assertTrue($calendar->isWorkingDay($this->at('2026-11-09 10:00')));
        $this->assertSame(180, $calendar->gapMinutes($this->at('2026-11-09 08:30'), $this->at('2026-11-09 11:30')));
    }
}
