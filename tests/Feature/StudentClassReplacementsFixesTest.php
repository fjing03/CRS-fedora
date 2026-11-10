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
}
