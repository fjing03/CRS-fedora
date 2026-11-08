<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Student-side health check S7 (2026-11-08, user's option A) — the Replacement
 * History page opens on **All Weeks**.
 *
 * It used to default to the CURRENT week. The week filter hides every other
 * week, so a student whose only replacement sits in a later week saw an empty
 * table and 0 / 0 / 0 summary cards (measured: one approved replacement in week
 * 8, one pending request in week 9 — both pages looked empty on arrival).
 *
 * A source invariant, because the default is a client-side fact: the select has
 * no server-rendered options, its value is chosen by this one call.
 */
final class ReplacementHistoryDefaultWeekTest extends TestCase
{
    /** @return array{0: string, 1: string} [history blade, its populateWeekSelect call] */
    private function weekFilterCall(): array
    {
        $path = __DIR__.'/../../resources/views/livewire/replacement-history.blade.php';
        $blade = (string) file_get_contents($path);

        preg_match("/populateWeekSelect\('weekFilter',\s*(\{[^}]*\})/s", $blade, $m);

        return [$path, $m[1] ?? ''];
    }

    public function test_the_page_opens_on_all_weeks(): void
    {
        [, $call] = $this->weekFilterCall();

        $this->assertNotSame('', $call, 'the page still builds its week filter through the shared helper');
        $this->assertStringContainsString("selected: 'all'", $call, 'the default is All Weeks — never a single week');
        $this->assertStringContainsString('includeAll: true', $call, 'the All Weeks option must exist for the default to resolve');
    }

    public function test_the_current_week_is_no_longer_the_default(): void
    {
        [$path, $call] = $this->weekFilterCall();

        $this->assertStringNotContainsString(
            'currentWeekIndex()',
            $call,
            'the default must not fall back to today\'s week (that is the S7 symptom) in '.$path,
        );
    }
}
