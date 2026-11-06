<?php

namespace App\Support;

use App\Models\Semester;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * THE office-hours clock (user brief "Auto-Reject Pending Requests", 2026-11-06).
 *
 * TARUMT Sabah works Monday–Friday, 08:30–17:30. Everything this class answers
 * is measured in that clock ONLY: evenings, Saturdays, Sundays and the
 * semester's `holidays` rows contribute nothing.
 *
 * Two questions, one calendar:
 *  - `gapMinutes($from, $to)` — how much WORKING time sits between two instants
 *    (the auto-reject rule: `<= 180` minutes means the PL can no longer decide
 *    in time). A brand-new gap never invents time: an instant inside a weekend
 *    starts counting at the next opening bell.
 *  - `subtractWorkingMinutes($end, $n)` — walk BACKWARDS n working minutes (the
 *    badge tooltip's "Earliest Approval Time" = proposed start − 3 working
 *    hours). The inverse of the gap, so a deadline computed here and a gap
 *    computed there always agree.
 *
 * Holidays are the semester's own `holidays` rows (week_number + day_of_week +
 * label), resolved to real dates through `semesters.start_date` — the same
 * week/day arithmetic the timetable uses, so a "public holiday" here is exactly
 * the day the grid greys out. No second holiday list is introduced.
 */
final class WorkingHours
{
    /** The rule's threshold: 3 working hours (the boundary itself rejects). */
    public const MIN_GAP_MINUTES = 180;

    /** Opening / closing bell, minutes after midnight. */
    public const OPEN_MINUTES = 8 * 60 + 30;   // 08:30

    public const CLOSE_MINUTES = 17 * 60 + 30; // 17:30

    /** ISO weekdays that work: Monday(1) … Friday(5). */
    private const WORKING_WEEKDAYS = [1, 2, 3, 4, 5];

    /** Guard rails: the loops below never walk further than this. */
    private const MAX_DAYS = 400;

    /** @var array<string, string>|null 'Y-m-d' => holiday label, built once per semester */
    private ?array $holidayMap = null;

    public function __construct(private readonly ?Semester $semester = null) {}

    /** Clock time in the display timezone (Asia/Kuala_Lumpur), never the UTC app clock. */
    public static function at(CarbonInterface $instant): Carbon
    {
        return Carbon::instance($instant->toDateTime())->setTimezone(LocalTime::TZ);
    }

    /** The deadline: the latest instant a PL can still approve (start − N working minutes). */
    public function earliestApproval(CarbonInterface $proposedStart, int $minutes = self::MIN_GAP_MINUTES): Carbon
    {
        return $this->subtractWorkingMinutes($proposedStart, $minutes);
    }

    /** Does the rule reject a proposed start that is `$proposedStart` away from `$now`? */
    public function isTooSoon(CarbonInterface $now, CarbonInterface $proposedStart, int $minutes = self::MIN_GAP_MINUTES): bool
    {
        return $this->gapMinutes($now, $proposedStart) <= $minutes;
    }

    /**
     * Working minutes between two instants. Zero when the target is not later
     * than the origin, so a proposal in the past is "no time left" rather than
     * a negative number.
     */
    public function gapMinutes(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = self::at($from);
        $to = self::at($to);

        if ($to->lte($from)) {
            return 0;
        }

        $total = 0;
        $day = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();

        for ($guard = 0; $guard < self::MAX_DAYS && $day->lte($last); $guard++) {
            if ($this->isWorkingDay($day)) {
                $open = $day->copy()->setTime(intdiv(self::OPEN_MINUTES, 60), self::OPEN_MINUTES % 60);
                $close = $day->copy()->setTime(intdiv(self::CLOSE_MINUTES, 60), self::CLOSE_MINUTES % 60);

                $begin = $from->gt($open) ? $from->copy() : $open;
                $finish = $to->lt($close) ? $to->copy() : $close;

                if ($finish->gt($begin)) {
                    $total += (int) $begin->diffInMinutes($finish);
                }
            }

            $day = $day->copy()->addDay();
        }

        return $total;
    }

    /**
     * Walk BACKWARDS `$minutes` working minutes from `$end`: the inverse of
     * `gapMinutes()`. Used for the badge tooltip's deadline.
     */
    public function subtractWorkingMinutes(CarbonInterface $end, int $minutes): Carbon
    {
        $cursor = self::at($end);
        $left = max(0, $minutes);

        for ($guard = 0; $guard < self::MAX_DAYS; $guard++) {
            $dayStart = $cursor->copy()->startOfDay();

            if ($this->isWorkingDay($dayStart)) {
                $open = $dayStart->copy()->setTime(intdiv(self::OPEN_MINUTES, 60), self::OPEN_MINUTES % 60);
                $close = $dayStart->copy()->setTime(intdiv(self::CLOSE_MINUTES, 60), self::CLOSE_MINUTES % 60);

                // Never count past the closing bell, and never before opening.
                $finish = $cursor->lt($close) ? $cursor->copy() : $close;

                if ($finish->gt($open)) {
                    $available = (int) $open->diffInMinutes($finish);

                    if ($available >= $left) {
                        return $finish->subMinutes($left);
                    }

                    $left -= $available;
                }
            }

            // Just before this day → the previous day's closing bell (or earlier).
            $cursor = $dayStart->copy()->subMinute();

            if ($left === 0) {
                return $cursor;
            }
        }

        return self::at($end); // unreachable with real dates; never invents a time
    }

    /** Monday–Friday and not a semester holiday. */
    public function isWorkingDay(CarbonInterface $day): bool
    {
        $local = self::at($day);

        if (! in_array($local->isoWeekday(), self::WORKING_WEEKDAYS, true)) {
            return false;
        }

        return ! array_key_exists($local->toDateString(), $this->holidays());
    }

    /** The holiday label for a date, or null — lets callers explain a skipped day. */
    public function holidayLabel(CarbonInterface $day): ?string
    {
        return $this->holidays()[self::at($day)->toDateString()] ?? null;
    }

    /**
     * The semester's `holidays` rows resolved to real dates.
     *
     * @return array<string, string>
     */
    public function holidays(): array
    {
        if ($this->holidayMap !== null) {
            return $this->holidayMap;
        }

        $this->holidayMap = [];

        if ($this->semester === null) {
            return $this->holidayMap;
        }

        $start = Carbon::parse((string) $this->semester->start_date, LocalTime::TZ)->startOfDay();

        foreach ($this->semester->holidays()->get(['week_number', 'day_of_week', 'label']) as $holiday) {
            $date = $start->copy()
                ->addWeeks((int) $holiday->week_number - 1)
                ->addDays((int) $holiday->day_of_week);

            $this->holidayMap[$date->toDateString()] = (string) $holiday->label;
        }

        return $this->holidayMap;
    }
}
