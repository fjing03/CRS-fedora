<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `student-class-replacements` (2026-11-09) — the student page's shape, pinned as
 * source invariants (the table is built in the browser, so there is no server HTML).
 *
 * The eight rulings a later tidy-up could silently undo:
 *  R1 rows are conflicted CLASS OCCURRENCES (the payload's `status: 'none'` exists at all);
 *  R2 one story for every unarranged class — `Awaiting Arrangement`, never the staff
 *     words and never a dead request's state;
 *  R3 the week filter keys on the class's week (`All Original Class Weeks`);
 *  R4 `Exclude COMPLETED Status`, decided by the make-up date;
 *  R5 the page is Class Replacements at /student-class-replacements (alias kept);
 *  R6 read-only, with ONE control — the pop-up's jump, guarded by `requestId`;
 *  R7 five cards, no `Total`;
 *  R8 the hours card sums the class hours of every visible row.
 */
class StudentReplacementHistoryParityTest extends TestCase
{
    private function pageSource(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-history.blade.php'));
    }

    private function componentSource(): string
    {
        return (string) file_get_contents(base_path('app/Livewire/ReplacementHistory.php'));
    }

    public function test_the_six_columns_are_in_the_users_order_and_the_row_number_is_gone(): void
    {
        $src = $this->pageSource();

        $labels = ['Course', 'Lecturer', 'Original Slot', 'Conflict Reason', 'Requested Slot', 'Request Status'];
        $last = -1;
        foreach ($labels as $label) {
            $pos = mb_strpos($src, "label: '".$label."'");
            $this->assertNotFalse($pos, 'the table lost its '.$label.' column');
            $this->assertGreaterThan($last, $pos, $label.' is out of the ruled order');
            $last = $pos;
        }

        $this->assertStringNotContainsString("label: '#'", $src);
        $this->assertStringNotContainsString("label: 'Subject'", $src);
        $this->assertStringNotContainsString("label: 'New Slot'", $src);
    }

    public function test_the_five_cards_and_nothing_else(): void
    {
        $src = $this->pageSource();

        foreach (['sumNone' => 'Awaiting Arrangement', 'sumPending' => 'Pending', 'sumApproved' => 'Approved',
            'sumCompleted' => 'Completed', 'sumHours' => 'Class Hours Affected'] as $id => $label) {
            $this->assertStringContainsString("'valueId' => '".$id."'", $src, 'missing card '.$id);
            $this->assertStringContainsString("'label' => '".$label."'", $src, 'missing card label '.$label);
        }

        $this->assertStringNotContainsString('sumTotal', $src, 'Total was dropped by ruling');
        $this->assertStringNotContainsString("'label' => 'Total'", $src);
        $this->assertStringNotContainsString('sumUpcoming', $src, 'Upcoming was retired with the request model');
        $this->assertStringNotContainsString("'label' => 'Upcoming'", $src);

        // R8 — the hours card sums the CLASS duration of every visible row
        $this->assertStringContainsString(
            'visible.reduce((s, r) => s + (r.orig.duration || 0), 0)',
            $src,
            'Class Hours Affected counts the class hours of every visible row, arranged or not',
        );
    }

    public function test_the_unarranged_state_has_its_own_badge_and_rank(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString("r.status === 'none' ? 'badge badge-outline status-none'", $src);
        $this->assertStringContainsString('({ pending: 0, approved: 1, completed: 2, none: 3 })', $src);
    }

    public function test_the_forbidden_vocabulary_never_appears(): void
    {
        $surface = $this->pageSource().$this->componentSource();

        foreach (['Need to Arrange', 'Cancelled by YOU', 'Rejected', 'Awaiting PL approval'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $surface,
                'the student page must never print "'.$forbidden.'"',
            );
        }

        $this->assertStringContainsString('Awaiting Arrangement', $this->componentSource().$this->pageSource(), 'the words come from the mapper');
    }

    public function test_the_week_filter_keys_on_the_class_week(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString("allLabel: 'All Original Class Weeks'", $src);
        $this->assertStringContainsString('Original Class Week:', $src);
        $this->assertStringNotContainsString('All Requested Slots Weeks', $src, 'that label belonged to the request model');
        $this->assertStringNotContainsString('Requested Replacement Week:', $src);

        // the filter reads the occurrence's own week, and only a derived `completed` row hides
        $this->assertStringContainsString("(all || r.week === w0) && !(hideCompleted && r.status === 'completed')", $src);
    }

    public function test_the_toggle_is_the_staff_control_with_the_staff_default(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString('<input type="checkbox" id="hideCompleted" checked>', $src);
        $this->assertStringContainsString('Exclude <span class="info-keyword">COMPLETED</span> Status', $src);
        $this->assertStringContainsString('data-tip="ON = completed requests are excluded from the list"', $src);
        $this->assertStringNotContainsString('id="showPast"', $src);
    }

    public function test_the_page_is_read_only_and_has_exactly_one_control(): void
    {
        $src = $this->pageSource();

        // no action column, no management verbs anywhere
        /* management-specific strings only — "Approved" is a STATUS word and must stay */
        foreach (['Arrange replacement', 'Cancel this request', 'approveRequest(', 'openRejectModal(', 'CancelPendingRequest'] as $verb) {
            $this->assertStringNotContainsString($verb, $src, 'the page must stay read-only: '.$verb);
        }
        $this->assertStringNotContainsString("label: 'Action'", $src);

        // the one control: the pop-up's jump, guarded by the requestId key
        $this->assertStringContainsString('const jump = (r.requestId !== null', $src);
        $this->assertStringContainsString("'/student-my-timetable-ui'", $src);
        $this->assertStringContainsString("footer.insertAdjacentHTML('afterbegin', jump)", $src);
    }

    public function test_the_route_and_label_are_the_ruled_ones(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));
        $nav = (string) file_get_contents(base_path('resources/views/partials/ui-nav-bar.blade.php'));

        $this->assertStringContainsString("'/student-class-replacements' => [", $routes);
        $this->assertStringContainsString("'/replacement-history-ui' => [", $routes, 'the old address stays alive');
        $this->assertStringContainsString("'label'=>'Class Replacements'", $nav);
        $this->assertStringContainsString("'href'=>'/student-class-replacements'", $nav);
    }
}
