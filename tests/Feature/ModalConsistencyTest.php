<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Modal consistency (user, 2026-11-10) — four laws, pinned so a later edit cannot
 * quietly undo them:
 *
 *  #1 every DETAILS modal shares one CEILING (680px) and one FLOOR (420px) and
 *     sizes itself with `fit-content` above phone width — since 2026-11-14
 *     (SDD modal-responsive-width) the width is no longer pinned at 680;
 *  #2 the Arrange Replacement action is a primary (blue) button with an icon;
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

    /**
     * 2026-11-14 (SDD modal-responsive-width, user decision R8): 680px is now a
     * CEILING, not a fixed width. Above 600px every pop-up is
     * `width: fit-content; min-width: 420px` under a per-variant ceiling, and the
     * three page-local 680px pins (my-replacements inline, request-approval's ID
     * selector and its 1025+ media block) were REMOVED so the two widest pages
     * shrink with their content like every other one.
     *
     * The guard therefore pins the BOUNDED invariant: floor + ceiling + fit-content
     * present, and no page re-pinning a second width. It still fails if someone
     * restores a fixed 680px with no floor, or raises the ceiling above 680.
     */
    public function test_details_modals_share_one_bounded_width(): void
    {
        $css = $this->read('public/css/theme.css');

        /* the shared rule: shrink-to-content, floored at today's base */
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 601px\) \{[\s\S]*?\.modal \{[^}]*width: fit-content;[^}]*min-width: 420px;/s',
            $css,
            'above phone width a modal sizes to its content and never gets narrower than today',
        );

        /* the ceiling survives, and it is still 680 */
        $this->assertMatchesRegularExpression(
            '/\.modal\.modal--detail \{[^}]*max-width: 680px;/s',
            $css,
            'every details modal is still capped at 680px',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.modal\.modal--detail \{[^}]*max-width: (7\d\d|69\d|68[1-9])px;/s',
            $css,
            'the details ceiling must not be raised above 680',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.modal\.modal--detail \{[^}]*(?<!max-)width: 680px;/s',
            $css,
            'a fixed 680px width would undo the fit-content rule',
        );

        /* the sheet is phones only — that is what makes 601–768 a dialog */
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 600px\) \{[\s\S]*?\.modal-overlay \{[^}]*padding: 0;/s',
            $css,
            'the bottom sheet is the <=600 range, and it docks flush',
        );

        /* no page pins a second width on its DETAILS shell any more (the 2026-11-03
           split is gone). Other pop-ups may still carry a maximum of their own — the
           Confirm Cancellation shell legitimately does (modal-facts-fit-content) —
           so the assertion targets the details shells, not the whole file. */
        $home = $this->read('resources/views/livewire/replacement-home.blade.php');
        $req = $this->read('resources/views/livewire/request-approval.blade.php');
        $this->assertMatchesRegularExpression('/id="quickViewModal"[^>]*>\s*<div class="modal">/s', $home,
            'the Replacement Details shell must carry no page-local width');
        foreach (['max-width: 680px', 'max-width:680px'] as $pin) {
            $this->assertStringNotContainsString($pin, $req,
                'request-approval must not re-pin the shared ceiling');
        }
        $this->assertStringNotContainsString('#modalOverlay .modal { max-width', $req,
            'the ID-specificity pin is the one the fit-content rule cannot outrank');
        foreach (['max-width: 616px', 'max-width:616px'] as $old) {
            $this->assertStringNotContainsString($old, $req, 'a second shell left at 616 would split the one width in two');
        }
    }

    /**
     * 2026-11-14 (SDD modal-facts-fit-content, user: "fix. allow wrap"): the grey
     * facts strip must not WIDEN its pop-up. Its one-line max-content (~900px) used
     * to push every details pop-up to the 680 ceiling, leaving a dead area beside
     * the short rows. `width: 0` takes it out of the pop-up's intrinsic size;
     * `min-width: 100%` keeps it painted edge to edge, wrapping its own items.
     */
    public function test_the_facts_strip_does_not_widen_the_pop_up(): void
    {
        $css = $this->read('public/css/theme.css');
        $this->assertMatchesRegularExpression(
            '/\.modal \.detail-facts \{\s*width: 0;\s*min-width: 100%;\s*\}/s',
            $css,
            'the facts strip spans the pop-up without sizing it',
        );

        /* the other half of the user's ruling: the two approved maxima */
        $home = $this->read('resources/views/livewire/replacement-home.blade.php');
        $this->assertStringContainsString('<div class="modal" style="max-width:680px">', $home,
            'Confirm Cancellation carries the shared 680 ceiling');
        $this->assertStringNotContainsString('max-width:635px', $home, 'the old 635 maximum is gone');

        /* Select a Session is NOT the 560 shell — it already uses the details class */
        $intent = $this->read('resources/views/partials/ui-intent-session-modal.blade.php');
        $this->assertMatchesRegularExpression('/id="intentSessionModal"[^>]*>\s*<div class="modal modal--detail">/s', $intent,
            'Select a Session already shares the 680 details ceiling');
        $this->assertStringContainsString('<div class="modal" style="max-width:680px">', $intent,
            'Slot Too Short carries the shared 680 ceiling too (user, 2026-11-14)');
        $this->assertStringNotContainsString('max-width:560px', $intent, 'the old 560 maximum is gone');
    }

    /**
     * 2026-11-11 (user): the Replacement Details pop-up prints its venue as PLAIN
     * text — `B010 — Computer Lab`, the same row Request Details prints — with no
     * house icon and none of the table's muted `.cell-sub` skin. The TABLES keep
     * their icon: only the pop-up's two schedule tabs were asked to change.
     */
    public function test_the_replacement_details_venue_row_is_plain_text(): void
    {
        $home = $this->read('resources/views/livewire/replacement-home.blade.php');

        $this->assertSame(2, substr_count($home, "DetailModal.row('Venue', qvVenue("),
            'both schedule tabs read the plain-text venue');
        $this->assertStringNotContainsString("DetailModal.row('Venue', venueLine(", $home,
            "the pop-up must not wear the table's icon/muted skin any more");
        $this->assertMatchesRegularExpression(
            '/function qvVenue\(code, name\) \{\s*if \(!code\) return null;/s',
            $home,
            'no room ⇒ no row, exactly like Request Details',
        );

        /* the table keeps the icon reader — this change must not touch it */
        $this->assertStringContainsString("'<br>' + venueLine(c.venue, null)", $home);
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
        /* the ONE wording is `subjectText`, built with the type and then handed to
           the subtitle (ruling A) — it is no longer spelled out twice */
        $this->assertStringContainsString('subtitle: subjectText,', $js);
        $this->assertSame(1, substr_count($js, "UiLabels.classType(event.type, 'long')"),
            'the subject line is built once, not once per consumer');
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
                '/btn-replace-now[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Arrange Replacement/s',
                $src,
                "{$page}: the action must lead with its icon and read 'Arrange Replacement'",
            );
        }
    }

    public function test_class_information_leads_with_the_session_id_and_one_subject_row(): void
    {
        $js = $this->read('public/js/ui-common.js');

        $this->assertStringContainsString("{ label: 'Session ID'", $js);
        /* ruling A (user, 2026-11-10): the subject is the SUBTITLE's job — the row
           that repeated it is gone, exactly like the Room Name row */
        $this->assertStringNotContainsString("{ label: 'Subject',", $js);
        $this->assertStringContainsString('subtitle: subjectText,', $js);
        $this->assertStringContainsString("{ label: 'Lecturer', value: event.lecturer }", $js);
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

        $this->assertStringContainsString('ModalFacts.venue(event.venue, event.venueName, cfg.venueSeats)', $js);
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

    /**
     * User ruling B (2026-11-10): `B002 — Tutorial Room (35 seats)`.
     * The code LEADS; the name loses the copy of the code it carries — every room
     * in this dataset has one ("Tutorial Room B002", "Computer Lab B005") — and the
     * seat count is optional, because only the venue page knows it.
     */
    public function test_one_venue_wording_leads_with_the_code_and_never_repeats_it(): void
    {
        $js = $this->read('public/js/ui-common.js');
        $start = strpos($js, 'roomName(code, name) {');
        $this->assertNotFalse($start, 'roomName() is the one code-stripper');
        $block = substr($js, $start, 900);
        $this->assertStringContainsString("n.replace(new RegExp('\\\\s*[\\\\u2013\\\\u2014-]?\\\\s*' + esc, 'ig'), ' ')", $block,
            'the name loses the copy of the code it carries');

        $start2 = strpos($js, 'venue(code, name, seats) {');
        $this->assertNotFalse($start2, 'venue() takes an optional seat count');
        $block2 = substr($js, $start2, 700);
        $this->assertStringContainsString('var n = this.roomName(c, name);', $block2,
            'venue() reuses roomName — one stripper, not two');
        $this->assertStringContainsString("var base = n ? c + ' \\u2014 ' + n : c;", $block2,
            'the code leads, the em dash joins it to the trimmed name');
        $this->assertStringContainsString("seatsN === 1 ? ' seat' : ' seats'", $block, 'one seat reads in the singular');
        $this->assertStringContainsString('window.ModalFacts ? ModalFacts.venue(code, name)', $js,
            'venueLine delegates to the one formulation');

        /* the venue page hands the seat count in, the other pages do not */
        $this->assertStringContainsString('venueSeats: venueInfo.capacity || null',
            $this->read('resources/views/livewire/venue-timetable.blade.php'));
    }

    /**
     * The other half of ruling B (user, 2026-11-10): the room is already the
     * Schedule's Venue row, so Slot Details stopped printing it twice — and the
     * seats moved onto the Venue row instead of living in Class Information.
     */
    public function test_slot_details_does_not_print_the_room_twice(): void
    {
        $page = $this->read('resources/views/livewire/venue-timetable.blade.php');

        $this->assertStringNotContainsString("{ label: 'Room Name'", $page, 'the duplicated Room Name row is gone');
        $this->assertStringNotContainsString("{ label: 'Capacity'", $page, 'Capacity rides on the Venue row now');
        /* the capacity is still read by the page's own venue badge (3 sites) and by
           the new hand-off (1) — nothing else */
        $this->assertSame(4, substr_count($page, 'venueInfo.capacity'), 'the badge + the one hand-off, nothing new');
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

    /**
     * Ruling B7 (user, 2026-11-10): the slot-validity badge PAINTS the glyph — a
     * green tick or a red warning circle — so the words beside it must not repeat
     * it. Before this, the row read "\u2713 \u2713 Slot available".
     */
    public function test_the_slot_validity_row_prints_one_glyph(): void
    {
        $page = $this->read('resources/views/livewire/request-approval.blade.php');

        $this->assertStringNotContainsString("'\u2713 Slot available'", $page, 'no second tick');
        $this->assertStringNotContainsString("'\u26a0 This make-up slot is NO LONGER FREE'", $page, 'no second warning glyph');
        $this->assertStringContainsString(": 'Slot available';", $page, 'the words carry no glyph');

        /* the badge itself still paints it */
        $this->assertStringContainsString("(r.slotValidity === 'conflict' ? '⚠' : '✓') + '</span>'", $page,
            'the badge keeps its glyph');
    }

    /**
     * Ruling B4 (user, 2026-11-10): ONE separator for a modal subtitle — the em dash.
     * `AMSE1003 — Software Engineering (Lecture)` everywhere; Replacement Details was
     * the odd one out with a middle dot.
     */
    public function test_every_modal_subtitle_joins_code_and_name_with_an_em_dash(): void
    {
        $this->assertStringContainsString("subtitle: c.code + ' \\u2014 ' + c.name",
            $this->read('resources/views/livewire/replacement-home.blade.php'));
        $this->assertStringContainsString("subtitle: r.courseCode + ' \u2014 ' + r.courseName",
            $this->read('resources/views/livewire/request-approval.blade.php'));

        /* the class modal's one wording, and no middle dot left in any subtitle */
        $js = $this->read('public/js/ui-common.js');
        $this->assertStringContainsString("(event.name ? ' \\u2014 ' + event.name : '')", $js);
        foreach (['resources/views/livewire/replacement-home.blade.php', 'resources/views/livewire/request-approval.blade.php'] as $f) {
            $this->assertStringNotContainsString('\\u00b7', $this->read($f), "{$f}: no middle dot in a subtitle");
        }
    }
}
