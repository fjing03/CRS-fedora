<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SDD `student-side-health-check` S1 (2026-11-08): every LIVE page that bridges
 * real data must switch the mock-era "today" anchor OFF.
 *
 * `public/js/mock-data.js` pins `mockNow: new Date(2026, 9, 5)` so the mock-era
 * demo stays inside the lead-time window. Each live page cancels it with
 * `MockData.mockNow = null;` in its DOMContentLoaded bridge — except the Student
 * My Timetable page, which was missed, so its "Today" was 5 Oct 2026 and the
 * Today button jumped to Week 3.
 *
 * This pins the invariant instead of the incident: a live Blade that assigns
 * `MockData.semester = …` (i.e. it IS a real-data bridge) must also clear the
 * anchor. The mock design templates are deliberately NOT scanned — they want the
 * anchor.
 */
final class MockNowClearedTest extends TestCase
{
    /**
     * Plain filesystem access — a data provider runs BEFORE the container is
     * booted, so `resource_path()` / the File facade are not available here.
     *
     * @return array<string, array{0: string}>
     */
    public static function liveBridges(): array
    {
        $dir = dirname(__DIR__, 2).'/resources/views/livewire';
        $out = [];

        foreach (glob($dir.'/*.blade.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);

            if (str_contains($source, 'MockData.semester')) {
                $out[basename($path)] = [basename($path)];
            }
        }

        return $out;
    }

    #[DataProvider('liveBridges')]
    public function test_every_live_data_bridge_switches_the_mock_today_anchor_off(string $blade): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/'.$blade);

        $this->assertStringContainsString(
            'MockData.mockNow = null;',
            $source,
            $blade.' bridges real data but leaves the mock-era "today" anchor on — '
            .'its Today button and today column would use 5 Oct 2026',
        );
    }

    /** The anchor itself stays documented in one place (a sanity anchor for the note above). */
    public function test_the_mock_anchor_is_still_the_documented_october_date(): void
    {
        $mock = (string) file_get_contents(dirname(__DIR__, 2).'/public/js/mock-data.js');

        $this->assertStringContainsString('mockNow: new Date(2026, 9, 5)', $mock);
    }
}
