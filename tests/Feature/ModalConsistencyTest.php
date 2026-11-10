<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Modal consistency (user, 2026-11-10) — four laws, pinned so a later edit cannot
 * quietly undo them:
 *
 *  #1 every DETAILS modal is the Replacement Details width (616px), the timeline
 *     rail is gone, and the subtitle names the class type;
 *  #2 Replace Now is a primary (blue) button with an icon;
 *  #3 Class Information leads with the session ID and shows ONE combined subject
 *     row (code — name (type));
 *  #4 the Schedule reads Venue · Week · Day & Date · Time & Duration, and the same
 *     two combined rows are used by Request Details and Replacement Details.
 *
 * Source invariants: this is wording/structure, so the assertions read the files.
 */
class ModalConsistencyTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(base_path($rel));
    }

    public function test_details_modals_share_the_replacement_details_width(): void
    {
        $css = $this->read('public/css/theme.css');
        $this->assertMatchesRegularExpression(
            '/\.modal\.modal--detail \{[^}]*max-width: 616px;/s',
            $css,
            'every details modal is 616px, like the Replacement Details shell',
        );
        $this->assertStringContainsString('max-width:616px', $this->read('resources/views/livewire/replacement-home.blade.php'),
            'the reference width on my-replacements must stay the one the modals match');
    }

    public function test_the_timeline_rail_is_gone(): void
    {
        $css = $this->read('public/css/theme.css');
        $js = $this->read('public/js/ui-common.js');

        $this->assertStringNotContainsString('.modal-timeline {', $css);
        $this->assertStringNotContainsString('.modal-timeline .tl-', $css);
        $this->assertStringNotContainsString('modal-timeline-note', $js);
        $this->assertStringNotContainsString('DetailModal.timeline(cfg.timeline)', $js, 'the renderer must not call the removed method');
        $this->assertStringNotContainsString('timeline(steps)', $js);
        $this->assertStringNotContainsString('timeline: event.status', $js, 'the pending rail config went with it');
    }

    public function test_the_subtitle_carries_the_class_type(): void
    {
        $js = $this->read('public/js/ui-common.js');
        $this->assertStringContainsString("UiLabels.classType(event.type, 'long')", $js);
        $this->assertStringContainsString("subtitle: (event.code || '')", $js);
    }

    public function test_replace_now_is_a_primary_button_with_an_icon(): void
    {
        $css = $this->read('public/css/theme.css');
        $block = substr($css, strpos($css, '.btn-replace-now {'), 400);
        $this->assertStringContainsString('background: var(--color-primary);', $block);
        $this->assertStringContainsString('color: var(--color-on-primary);', $block);

        foreach ([
            'resources/views/livewire/my-timetable.blade.php',
            'resources/views/livewire/cohort-timetable.blade.php',
            'resources/views/livewire/venue-timetable.blade.php',
        ] as $page) {
            $src = $this->read($page);
            $this->assertMatchesRegularExpression(
                '/btn-replace-now[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Replace Now/s',
                $src,
                "{$page}: the Replace Now button must lead with its icon",
            );
        }
    }

    public function test_class_information_leads_with_the_session_id_and_one_subject_row(): void
    {
        $js = $this->read('public/js/ui-common.js');

        $this->assertStringContainsString("{ label: 'Session ID'", $js);
        $this->assertStringContainsString("{ label: 'Subject', value: subjectText }", $js);
        $this->assertStringNotContainsString("{ label: 'Subject Code'", $js);
        $this->assertStringNotContainsString("{ label: 'Subject Name'", $js);
        $this->assertStringNotContainsString("{ label: 'Class Type',", $js);
        $this->assertStringNotContainsString("{ label: 'Class Ref'", $js);

        /* the session id wins over the grid's own row key (a time_slot id on the
           venue page) — the venue-status-parity T6 rule survives the rename */
        $this->assertStringContainsString('event.sessionId != null ? event.sessionId : event.id', $js);
    }

    public function test_the_schedule_is_four_combined_rows(): void
    {
        $js = $this->read('public/js/ui-common.js');

        $this->assertStringContainsString('ModalFacts.venue(event.venue, event.venueName)', $js);
        $this->assertStringContainsString("{ label: 'Week', value: cfg.week ? 'Week ' + cfg.week : '\\u2014' }", $js);
        $this->assertStringContainsString('ModalFacts.dayDate(dayNames[di] || days[di].abbr, days[di].date)', $js);
        $this->assertStringContainsString('ModalFacts.timeDuration(startStr, endStr', $js);
        $this->assertStringNotContainsString("{ label: 'Start Time'", $js);
        $this->assertStringNotContainsString("{ label: 'End Time'", $js);

        /* every page's class modal must hand the week in */
        foreach ([
            'resources/views/livewire/my-timetable.blade.php',
            'resources/views/livewire/cohort-timetable.blade.php',
            'resources/views/livewire/venue-timetable.blade.php',
            'resources/views/livewire/student-my-timetable.blade.php',
        ] as $page) {
            $this->assertStringContainsString('week: window.currentWeek + 1', $this->read($page), "{$page} passes the viewed week");
        }
    }

    public function test_the_other_two_modals_use_the_same_two_rows(): void
    {
        $req = $this->read('resources/views/livewire/request-approval.blade.php');
        $this->assertSame(2, substr_count($req, "DetailModal.row('Day & Date'"));
        $this->assertSame(2, substr_count($req, "DetailModal.row('Time & Duration'"));
        $this->assertStringNotContainsString("DetailModal.row('Day', ", $req);
        $this->assertStringNotContainsString("DetailModal.row('Duration', ", $req);

        $home = $this->read('resources/views/livewire/replacement-home.blade.php');
        $this->assertSame(2, substr_count($home, "DetailModal.row('Day & Date'"));
        $this->assertSame(2, substr_count($home, "DetailModal.row('Time & Duration'"));
        $this->assertStringNotContainsString("DetailModal.row('Duration', ", $home);
    }

    public function test_one_venue_wording_never_repeats_the_code(): void
    {
        $js = $this->read('public/js/ui-common.js');
        $start = strpos($js, 'venue(code, name) {');
        $block = substr($js, $start, 700);
        $this->assertStringContainsString('n.toUpperCase().indexOf(c.toUpperCase()) !== -1', $block,
            'the code is skipped when the room name already carries it');
        $this->assertStringContainsString('window.ModalFacts ? ModalFacts.venue(code, name)', $js,
            'venueLine delegates to the one formulation');
    }

    public function test_a_duration_never_prints_nan(): void
    {
        $js = $this->read('public/js/ui-common.js');
        $this->assertStringContainsString('!isFinite(Number(hours))', substr($js, strpos($js, 'function durationWords')));
        $this->assertStringContainsString('!isFinite(mins)', $this->read('resources/views/livewire/request-approval.blade.php'));
    }

    /**
     * Follow-up (user, 2026-11-10): "standardise on the Close button's size, 34 px.
     * Change size ONLY for now." One exact height for every footer button, scoped to
     * `.modal-footer` so tables, cards and toolbars keep their own sizes.
     */
    public function test_every_modal_footer_button_is_one_size(): void
    {
        $css = $this->read('public/css/theme.css');
        $start = strpos($css, '/* ── ONE footer-button size');
        $this->assertNotFalse($start, 'the footer-size rule must exist');
        $block = substr($css, $start, 1600);

        foreach (['btn-close-modal', 'btn-outline', 'btn-danger', 'btn-approve', 'btn-action', 'btn-replace-now'] as $cls) {
            $this->assertStringContainsString('.modal-footer .'.$cls, $block, "{$cls} must be covered");
        }
        $this->assertStringContainsString('height: 34px;', $block, 'the height is fixed, not a min-height');
        $this->assertStringContainsString('padding: 0 20px;', $block);
        $this->assertStringContainsString('font-size: 13px;', $block);

        /* the rule is scoped: the classes themselves keep their other sizes */
        $this->assertStringNotContainsString('.btn-action {', substr($block, 0, 200), 'no global override');
    }
}
