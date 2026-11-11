<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Bug report (user, 2026-11-11) — the `#earliestBtn` tooltip on the arrangement
 * page read "Sunday, 16 Nov 2026 (Week 9)" while 16 Nov 2026 is a MONDAY.
 *
 * Cause: the weekday NAME was taken from a Sunday-first array indexed by
 * `weekData.days`' own index, and that array runs MONDAY → SUNDAY (the server's
 * `ResolvesTimetableTimeline::weekData()` builds `['Mon','Tue',…,'Sun']`). So every
 * name printed one day early — a wrong label, never a wrong date.
 *
 * Fix: the name is derived from the date string the tooltip prints beside it, via
 * the one shared helper `DateHelper.weekdayName()`. This test is the receipt: the
 * shared helper exists and is used, and the Sunday-first indexing cannot come back.
 */
class EarliestBookableWeekdayTest extends TestCase
{
    private function js(): string
    {
        return (string) file_get_contents(base_path('public/js/ui-common.js'));
    }

    private function arrangement(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-arrangement.blade.php'));
    }

    private function venue(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/venue-timetable.blade.php'));
    }

    private function template(): string
    {
        return (string) file_get_contents(base_path('resources/views/ui-design-templates/replacement-arrangement-UIdesign-template.blade.php'));
    }

    /** The name comes from the DATE, never from a day index. */
    public function test_the_weekday_name_is_derived_from_the_date(): void
    {
        $js = $this->js();

        $this->assertStringContainsString('static weekdayName(dateStr) {', $js);
        /* both shapes the app prints: the grid's "16 Nov 2026" and an ISO date */
        $this->assertStringContainsString('var iso = s.match(/^(\\d{4})-(\\d{1,2})-(\\d{1,2})$/);', $js);
        $this->assertStringContainsString('var dmy = s.match(/^(\\d{1,2})[\\s-]([A-Za-z]{3,})[\\s-](\\d{4})$/);', $js);
        /* one rule, one place — the older ISO reader now delegates instead of repeating it */
        $this->assertStringContainsString("static isoDayName(iso) {\n        return DateHelper.weekdayName(iso);", $js);
    }

    /** Every page that owns the button reads the name through it. */
    public function test_the_earliest_tooltip_uses_the_shared_weekday_helper(): void
    {
        foreach ([$this->arrangement(), $this->venue(), $this->template()] as $src) {
            $this->assertStringContainsString('DateHelper.weekdayName(', $src);
        }

        /* the arrangement page's tooltip: name + the SAME date string it prints */
        $this->assertStringContainsString('const eday = DateHelper.weekdayName(edate.date);', $this->arrangement());
        $this->assertStringContainsString("(eday ? eday + ', ' : '') + edate.date + ' (Week '", $this->arrangement());

        /* the venue page's copy of the same action */
        $this->assertStringContainsString('const dayName = DateHelper.weekdayName(dayFacts.date);', $this->venue());
        $this->assertStringContainsString("(dayName ? dayName + ', ' : '') + dayFacts.date + ' (Week '", $this->venue());
    }

    /** The Sunday-first array that caused it is gone from every earliest tooltip. */
    public function test_no_earliest_tooltip_indexes_a_sunday_first_array(): void
    {
        foreach ([$this->arrangement(), $this->venue(), $this->template()] as $src) {
            $this->assertStringNotContainsString('dayNames[earliest.day]', $src);
            $this->assertStringNotContainsString('dayNames[target.day]', $src);
        }
    }
}
