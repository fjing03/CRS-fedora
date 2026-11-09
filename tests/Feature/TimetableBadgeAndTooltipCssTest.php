<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Two style laws from 2026-11-09 that a future "tidy-up" could silently undo,
 * both living in the shared stylesheet every timetable page loads:
 *
 *  1. A holiday badge keeps the holiday colour on a day that is ALSO today. The
 *     blue "container" variant (2026-08-13) overrode it; it may now appear only
 *     while the column is hovered, because an offday column turns solid red on
 *     hover and a red badge would disappear into it.
 *  2. The event hover tooltip must clear the sticky hour header and the sticky
 *     day column. The tooltip cannot do that itself — a block's own z-index is
 *     confined to its table cell — so the CELL is raised while its block is
 *     hovered. If that rule goes, the tip hides behind the 08:00/09:00 row again.
 *
 * Source invariants, not rendering: no browser is needed, and the numbers are
 * compared with each other rather than hard-coded, so the sheet may be retuned.
 */
class TimetableBadgeAndTooltipCssTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(base_path('public/css/theme.css'));
    }

    /** The declarations of ONE rule, given its exact selector. */
    private function ruleBody(string $selector): ?string
    {
        $pattern = '/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/';

        return preg_match($pattern, $this->css(), $m) ? $m[1] : null;
    }

    public function test_a_holiday_badge_keeps_its_colour_on_a_today_holiday(): void
    {
        $css = $this->css();

        // no NON-hover rule may paint the badge with the primary-container blue
        preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER);
        $offenders = [];
        foreach ($m as $rule) {
            $selector = trim($rule[1]);
            $body = $rule[2];
            if (! str_contains($selector, 'holiday-badge')) {
                continue;
            }
            if (str_contains($selector, ':hover')) {
                continue;   // the hover chip is allowed (see the next test)
            }
            if (str_contains($body, '--color-primary-container')) {
                $offenders[] = $selector;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'the holiday badge is recoloured blue outside :hover — a Today+PH day would lose its holiday colour',
        );
    }

    public function test_the_blue_variant_survives_only_as_the_column_hover_chip(): void
    {
        $body = $this->ruleBody('.time-col.offday:hover .holiday-badge');

        $this->assertNotNull($body, 'the offday column turns solid red on hover — the badge needs its contrast chip back');
        $this->assertStringContainsString('var(--color-inverse-surface)', $body);
    }

    public function test_the_hovered_cell_is_raised_above_the_sticky_header_and_day_column(): void
    {
        $header = $this->ruleBody('.timetable th');
        $this->assertNotNull($header, 'the sticky header rule is gone');
        $this->assertSame(1, preg_match('/z-index:\s*(\d+)/', $header, $h), 'the header no longer declares a z-index');

        $dayCol = $this->ruleBody('.time-col');
        $this->assertNotNull($dayCol, 'the sticky day-column rule is gone');
        $this->assertSame(1, preg_match('/z-index:\s*(\d+)/', $dayCol, $d), 'the day column no longer declares a z-index');

        $cell = $this->ruleBody('.timetable td.hour-cell:has(.event-block:hover)');
        $this->assertNotNull(
            $cell,
            'the hovered cell must be raised: a block\'s own z-index is confined to its cell, so the tooltip hides behind the hour header without it',
        );
        $this->assertSame(1, preg_match('/z-index:\s*(\d+)/', $cell, $c), 'the raised-cell rule declares no z-index');

        $raised = (int) $c[1];
        $this->assertGreaterThan((int) $h[1], $raised, 'the hovered cell must out-rank the sticky hour header');
        $this->assertGreaterThan((int) $d[1], $raised, 'the hovered cell must out-rank the sticky day column');
    }
}
