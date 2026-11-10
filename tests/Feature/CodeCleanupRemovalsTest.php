<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Code cleanup — three standing decisions, pinned so a later "tidy-up" cannot
 * quietly undo them (user, 2026-11-10):
 *
 *  1. the Logout Modal (JS + partial + listeners) is PERMANENTLY REMOVED;
 *  2. the Venue "Add to Favourite" feature is PERMANENTLY REMOVED — including the
 *     star that used to appear beside a room in the venue dropdown;
 *  3. the `.cell-content.cell-occupied` hover tooltip on Replacement Arrangement
 *     STAYS — the earlier "remove it before the viva" request is REVOKED.
 *
 * These are source invariants (no DB, no HTTP): the point is absence/presence of
 * code, which is exactly what a future edit would break.
 */
class CodeCleanupRemovalsTest extends TestCase
{
    private function root(): string
    {
        return dirname(base_path());
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    /* ────────────────────────── 1. the Logout Modal ────────────────────────── */

    public function test_the_logout_modal_js_and_partial_are_gone(): void
    {
        $this->assertFileDoesNotExist(base_path('public/js/logout-modal.js'), 'the logout modal JS must stay deleted');
        $this->assertFileDoesNotExist(
            base_path('resources/views/partials/ui-logout-modal.blade.php'),
            'the logout modal shell must stay deleted',
        );
    }

    public function test_nothing_still_points_at_the_logout_modal(): void
    {
        $layout = $this->read('resources/views/layouts/ui-template.blade.php');
        $nav = $this->read('resources/views/partials/ui-nav-bar.blade.php');

        $this->assertStringNotContainsString('logout-modal', $layout, 'the layout must not load or include it');
        $this->assertStringNotContainsString('showLogoutModal', $layout);
        $this->assertStringNotContainsString('showLogoutModal', $nav, 'the navbar comment must be gone too');
        $this->assertStringNotContainsString('logoutModal', $nav);
        $this->assertStringNotContainsString('logout_no_confirm', $nav, 'its localStorage flag died with it');

        /* the logout itself still works the plain way — the form submit */
        $this->assertStringContainsString("getElementById('logout-form').submit()", $nav);
        $this->assertStringContainsString('id="logout-form"', $nav);
    }

    /* ────────────────────── 2. the venue favourite feature ─────────────────── */

    public function test_the_shared_dropdown_has_no_favourite_machinery_left(): void
    {
        $js = $this->read('public/js/ui-common.js');

        foreach (['getFavourites', 'toggleFavourite', 'updateFavStar', 'refreshFavourites', 'venueFavourites', 'maxFavourites'] as $gone) {
            $this->assertStringNotContainsString($gone, $js, "`{$gone}` belongs to the removed favourite feature");
        }

        /* the room row keeps its Recent clock icon, never a star */
        $this->assertStringContainsString("icon.textContent = '\u{1F550}'", $js, 'the Recent clock icon must survive');
        $this->assertStringNotContainsString("'\u{2B50}'", $js, 'no star may be painted in a venue row');
        $this->assertStringNotContainsString("groupType === 'favourite'", $js);
    }

    public function test_the_arrangement_page_has_no_favourite_call_sites(): void
    {
        $blade = $this->read('resources/views/livewire/replacement-arrangement.blade.php');

        foreach (['updateFavStar', 'toggleFavourite', 'favStar', 'fav-btn'] as $gone) {
            $this->assertStringNotContainsString($gone, $blade, "`{$gone}` was removed with the favourite feature");
        }
    }

    public function test_no_live_view_or_design_template_ships_a_favourite_star(): void
    {
        /* the live pages + the partials: no star glyph, no favourite wording-as-markup */
        $live = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php') || str_contains($path, 'ui-design-templates')) {
                continue;
            }
            $live[$path] = (string) file_get_contents($path);
        }

        foreach ($live as $path => $contents) {
            $this->assertStringNotContainsString('⭐', $contents, 'star glyph in '.basename($path));
            $this->assertStringNotContainsString('Add to Favourites', $contents, 'favourite button in '.basename($path));
        }
    }

    /* ─────────────────── 3. the occupied hover tooltip STAYS ──────────────── */

    public function test_the_occupied_cell_hover_tooltip_is_permanently_kept(): void
    {
        $css = $this->read('public/css/theme.css');

        $this->assertStringContainsString('.cell-content:hover > .cell-state-label', $css,
            'the hover reveal of the cell label must stay (user ruling 2026-11-10)');
        $this->assertStringContainsString('.cell-occupied > .cell-state-label', $css,
            'the occupied cell keeps its own label styling');
        $this->assertStringContainsString(".cell-content:has(> .cell-state-label)::after {\n    content: none;\n}", $css,
            'the span replaces the CSS overlay — it must not be double-drawn');

        $this->assertStringContainsString('REVOKED', $css,
            'the "remove before the viva" instruction must be recorded as revoked');
        $this->assertStringNotContainsString('.cell-occupied::after {\n    content: none;', $css,
            'the occupied overlay itself must not be switched off globally');

        $blade = $this->read('resources/views/livewire/replacement-arrangement.blade.php');
        $this->assertStringContainsString("stateLabel.className = 'cell-state-label'", $blade,
            'the arrangement page must keep rendering the label span');
        $this->assertStringContainsString('PERMANENT (user 2026-11-10)', $blade);
    }
}
