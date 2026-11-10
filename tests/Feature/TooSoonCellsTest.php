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

    public function test_the_label_is_two_lines_and_no_longer_the_long_sentence(): void
    {
        $this->assertStringContainsString("tooSoonLabel.innerHTML = 'Min. 3<br>Working Days';", $this->page());

        $css = $this->css();
        $this->assertStringContainsString("--hover-label: 'Min. 3 Working Days';", $css);
        /* the old sentence survives only inside the explanatory comment */
        $this->assertStringNotContainsString("--hover-label: 'Min. 3 working days ahead';", $css,
            'no declaration still holds the old sentence');
        $this->assertStringContainsString('.cell-too-soon > .cell-state-label {', $css, 'the label has its own skin');
    }

    public function test_the_reason_rides_the_tooltip_but_not_the_state(): void
    {
        $src = $this->page();

        $this->assertStringContainsString(
            "div.setAttribute('data-tip', why ? 'Min. 3 Working Days\\n\\n' + why : 'Min. 3 Working Days');",
            $src,
            'ruling C: the blocking reason stays one hover away'
        );
        $this->assertStringContainsString('Too soon — minimum 3 working days ahead', $src, 'the aria line says it too');
        /* and the cell is read-only: it never gets the available cell's handlers */
        $this->assertStringNotContainsString("div.className += ' cell-available';\n                            setupAvailableCell(div, di, hi);\n                        } else if (isTooSoon", $src);
    }

    public function test_the_label_never_spills_out_of_its_cell_on_a_phone(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('.cell-content:has(> .cell-state-label) { position: relative; }', $css);
        $this->assertStringContainsString('.cell-too-soon > .cell-state-label { font-size: 9px; }', $css);

        /* the global `.cell-content` must keep its absolute fill — a global
           `position: relative` collapsed every cell's height once (caught live) */
        $this->assertStringContainsString(".cell-content {\n    position: absolute;", $css);
    }
}
