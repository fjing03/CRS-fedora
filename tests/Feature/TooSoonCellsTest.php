<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UI Update 2 (user, 2026-11-10, ruling C) — the 3-working-day rule outranks the
 * ownership classes on the arrangement grid:
 *
 *   - every date inside the window reads `.cell-content.cell-too-soon`, even when a
 *     class sits in it, so `.cell-occupied` is NOT applied there;
 *   - `.cell-ph` (public holiday) and `.cell-pending` are preserved as they are;
 *   - the hover words are `Min. 3<br>Working Days` (title case, two deliberate lines);
 *   - the blocking reason stays reachable in the same cell's tooltip (ruling C).
 */
class TooSoonCellsTest extends TestCase
{
    private function page(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-arrangement.blade.php'));
    }

    private function css(): string
    {
        return (string) file_get_contents(base_path('public/css/theme.css'));
    }

    public function test_the_lead_time_rule_wins_over_the_ownership_classes(): void
    {
        $src = $this->page();

        $this->assertStringContainsString('const isTooSoon = isSlotTooSoon(weekData, weekNav.currentWeek, di);', $src);
        $this->assertStringContainsString("const keepPending = !!ownershipClass && ownershipClass.indexOf('cell-pending') !== -1;", $src);
        $this->assertStringContainsString('if (isTooSoon && !keepPending) {', $src);

        /* the too-soon branch must come BEFORE the ownership branch, or the red wins */
        $tooSoonAt = strpos($src, 'if (isTooSoon && !keepPending) {');
        $ownershipAt = strpos($src, '} else if (ownershipClass) {');
        $this->assertNotFalse($tooSoonAt);
        $this->assertNotFalse($ownershipAt);
        $this->assertLessThan($ownershipAt, $tooSoonAt, 'too-soon is checked first');
    }

    /**
     * 2026-11-10 ("UI Update — Too-Soon Tooltip", final wording): the standalone
     * banner is gone; the CELL says `Min 3 / Working Days`, the TOOLTIP carries the
     * rule and the earliest bookable date.
     */
    public function test_the_banner_is_gone_and_the_two_wording_surfaces_are_split(): void
    {
        $src = $this->page();

        $this->assertStringNotContainsString('id="leadTimeNote"', $src, 'the banner markup is gone');
        $this->assertStringNotContainsString("renderLeadTimeNote('leadTimeNote'", $src, 'and its render call');

        /* the cell: short, two lines, a real `<br>` */
        $this->assertStringContainsString("label: 'Min 3<br>Working Days',", $src);
        $this->assertStringContainsString('tooSoonLabel.innerHTML = tooSoonTip.label;', $src);

        /* the tooltip: the rule + the earliest bookable date, `\n` because a data-tip
           renders textContent — a literal `<br>` would print as characters */
        $this->assertStringContainsString(
            "text: 'Rule : Only bookable at least 3 working days from today.\\nEarliest Bookable : ' + dateStr + '.'",
            $src
        );
        $this->assertStringNotContainsString('<br>Earliest Bookable', $src, 'never a literal <br> in the data-tip');

        /* the date is the SAME cutoff the grid uses, with the FULL weekday name */
        $this->assertStringContainsString('var cutoff = leadTimeCutoff(weekData);', $src);
        $this->assertStringContainsString("var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];", $src);

        $css = $this->css();
        $this->assertStringContainsString('.cell-too-soon > .cell-state-label {', $css, 'the label has its own skin');
        $this->assertStringContainsString('.cell-too-soon > .cell-state-label { font-size: 9px; line-height: 1.1; padding: 1px; }', $css);
    }

    public function test_the_reason_rides_the_tooltip_but_not_the_state(): void
    {
        $src = $this->page();

        $this->assertStringContainsString(
            "div.setAttribute('data-tip', why ? tooSoonTip.text + '\\n\\n' + why : tooSoonTip.text);",
            $src,
            'ruling C: the blocking reason stays one hover away'
        );
        $this->assertStringContainsString("tooSoonTip.text.replace(/\\n/g, ' ')", $src, 'the aria line says it too');
        /* and the cell is read-only: it never gets the available cell's handlers */
        $this->assertStringNotContainsString("div.className += ' cell-available';\n                            setupAvailableCell(div, di, hi);\n                        } else if (isTooSoon", $src);
    }

    public function test_the_label_never_spills_out_of_its_cell_on_a_phone(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('.cell-content:has(> .cell-state-label) { position: relative; }', $css);
        $this->assertStringContainsString('.cell-too-soon > .cell-state-label { font-size: 9px; line-height: 1.1; padding: 1px; }', $css);

        /* the global `.cell-content` must keep its absolute fill — a global
           `position: relative` collapsed every cell's height once (caught live) */
        $this->assertStringContainsString(".cell-content {\n    position: absolute;", $css);
    }
}
