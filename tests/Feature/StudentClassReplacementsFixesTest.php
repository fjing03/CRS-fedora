<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `student-class-replacements` bug fixes (user, 2026-11-10) — the two source-level
 * laws; the third (the lecturer title) is proven on the payload in
 * `ReplacementHistoryRowTest`.
 *
 *  1. the page has NO Today button — the shared week-nav include is told so;
 *  2. an Active-Filters chip removes its own filter, and it does so through ONE
 *     delegated listener: the old inline `onclick=` called `saveView()`, which only
 *     exists inside the page's closure, so the click threw — the toggle turned off
 *     and the chip stayed. Never put a closure-scoped call in an inline handler.
 */
class StudentClassReplacementsFixesTest extends TestCase
{
    /* named pageSource(), never blade() — that collides with Laravel's own
       TestCase::blade() and is a fatal error (learned 2026-11-09, again here) */
    private function pageSource(): string
    {
        return (string) file_get_contents(resource_path('views/livewire/replacement-history.blade.php'));
    }

    public function test_the_page_ships_no_today_button(): void
    {
        $blade = $this->pageSource();

        $this->assertStringContainsString("'showTodayBtn' => false", $blade, 'the week-nav include must opt out of the Today button');
        $this->assertStringNotContainsString("'showTodayBtn' => true", $blade);
        $this->assertStringNotContainsString("getElementById('todayBtn')", $blade, 'its handler must go with the button');
        $this->assertStringNotContainsString('id="todayBtn"', $blade);

        /* the rest of the week nav survives */
        $this->assertStringContainsString("'selectId' => 'weekFilter'", $blade);
        $this->assertStringContainsString("'prevOnclick' => 'prevWeekFilter()'", $blade);
        $this->assertStringContainsString("'nextOnclick' => 'nextWeekFilter()'", $blade);
    }

    public function test_the_filter_chips_use_one_delegated_remover(): void
    {
        $blade = $this->pageSource();

        /* the chips carry data, never an inline handler */
        $this->assertStringContainsString('data-remove="weekFilter"', $blade);
        $this->assertStringContainsString('data-remove="hideCompleted"', $blade);
        $this->assertStringNotContainsString(
            "onclick=\"document.getElementById('hideCompleted').checked=false",
            $blade,
            'the broken inline handler must not come back',
        );
        $this->assertStringNotContainsString(
            "onclick=\"document.getElementById('weekFilter').value='all'",
            $blade,
            'the week chip moves to the same delegate',
        );

        /* ONE listener owns both chips, on the bar itself, and it only calls
           functions the page's own scope can see */
        $this->assertStringContainsString("chipBar.addEventListener('click'", $blade);
        $this->assertStringContainsString("e.target.closest('.filter-chip-remove')", $blade);
        $this->assertStringContainsString("getAttribute('data-remove')", $blade);
    }

    /**
     * 2026-11-10 (user): the student page carries the staff pages' "Clear all" chip —
     * same markup, same tooltip, LAST in the bar, and only while a filter is active.
     * It must also be reachable from its inline `onclick`: this page's script runs
     * inside a DOMContentLoaded closure, so the function needs a window export.
     */
    public function test_the_clear_all_chip_matches_the_staff_pages(): void
    {
        $blade = $this->pageSource();

        $this->assertStringContainsString(
            '<button type="button" class="filter-chip filter-chip-clear-all" data-tip="Remove every active filter" onclick="clearAllFilters()">Clear all</button>',
            $blade,
            'the markup must be the staff pages\' own, byte for byte',
        );

        /* only when something is active — the same guard the staff pages use */
        $this->assertStringContainsString('if (chips.length > 0) {', $blade);
        $this->assertMatchesRegularExpression(
            '/if \(chips\.length > 0\) \{\s*chips\.push\(.<button type="button" class="filter-chip filter-chip-clear-all"/',
            $blade,
            'Clear all is pushed after the chips, never rendered on a clean view',
        );

        /* the closure-wrapped script needs the global */
        $this->assertStringContainsString('window.clearAllFilters = clearAllFilters;', $blade);
    }

    public function test_clear_all_resets_this_page_s_own_filters(): void
    {
        $blade = $this->pageSource();

        $start = strpos($blade, 'function clearAllFilters() {');
        $this->assertNotFalse($start, 'clearAllFilters must exist');
        $body = substr($blade, $start, 900);

        $this->assertStringContainsString("getElementById('weekFilter')", $body, 'it resets the week filter');
        $this->assertStringContainsString("sel.value = 'all'", $body);
        $this->assertStringContainsString("getElementById('hideCompleted')", $body, 'it turns the completed filter off');
        $this->assertStringContainsString('hc.checked = false', $body);
        $this->assertStringContainsString('localStorage.removeItem(VIEW_KEY)', $body, 'the saved view must not resurrect them');
        $this->assertStringContainsString('renderUpcoming();', $body, 'and the table re-renders once');
    }
}
