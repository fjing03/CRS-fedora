<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Student-side health check S6 (2026-11-08) — the top-bar logo stays in the app.
 *
 * `navigateHome()` sends the browser to `window.PAGE_HOME`, which the layout
 * fills from the `homeUrl` the page hands it (layouts/ui-template.blade.php:54).
 * Four pages never passed one — the staff My Timetable, Cohort, Venue and the
 * student My Timetable — so their logo fell back to '/', the public welcome page
 * (whose only signed-in control is a bare /dashboard placeholder). The other
 * four pages (My Replacements, Arrangement, Approval, History) already pass
 * their role home.
 *
 * A source invariant, because this is a one-argument fact on each page's layout
 * call and a page that forgets it silently loses its logo link.
 */
final class LogoHomeUrlTest extends TestCase
{
    /** @return array<string, string> [component file => its extends() argument array] */
    private function pageLayoutCalls(): array
    {
        $pages = [];
        foreach ((array) glob(app_path('Livewire/*.php')) as $file) {
            $source = (string) file_get_contents((string) $file);
            if (! str_contains($source, "extends('layouts.ui-template'")) {
                continue;
            }
            preg_match("/extends\('layouts\.ui-template',\s*\[(.*?)\]/s", $source, $m);
            $pages[basename((string) $file)] = $m[1] ?? '';
        }

        return $pages;
    }

    public function test_every_page_hands_the_layout_a_home_url(): void
    {
        $pages = $this->pageLayoutCalls();

        $this->assertGreaterThanOrEqual(8, count($pages), 'the scan finds every page that uses the shared layout');

        foreach ($pages as $file => $args) {
            $this->assertNotSame('', $args, $file.' still calls the shared layout');
            $this->assertStringContainsString(
                "'homeUrl'",
                $args,
                $file.' must pass homeUrl — without it the logo leaves the app for the welcome page',
            );
        }
    }

    public function test_the_timetable_pages_point_at_their_own_role_home(): void
    {
        $pages = $this->pageLayoutCalls();

        foreach (['MyTimetable.php', 'CohortTimetable.php', 'VenueTimetable.php'] as $staffPage) {
            $this->assertStringContainsString("'homeUrl' => '/my-timetable-ui'", $pages[$staffPage], $staffPage);
        }

        $this->assertStringContainsString(
            "'homeUrl' => '/student-my-timetable-ui'",
            $pages['StudentMyTimetable.php'],
            'the student page must never send a student to the staff timetable',
        );
    }
}
