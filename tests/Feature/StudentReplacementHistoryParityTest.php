<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 2026-11-09 (user brief "Student Side UI Update") — the student Replacement
 * History is aligned with the staff My Replacements table. Fourteen rulings went
 * into it; these are the ones a later tidy-up could silently undo, asserted
 * against the page source (the table is built in the browser, so there is no
 * server HTML to read).
 *
 *  1. six columns, in the user's order, and no row-number column;
 *  2. the staff toggle: `Exclude COMPLETED Status`, ON by default, same tip;
 *  3. the All-Weeks option names the date it filters on;
 *  4. the summary card says Completed (one word for one state);
 *  5. the status badge is the staff `status-*` design with the server's label;
 *  6. the requested slot's countdown rides only a PENDING request;
 *  7. a conflict reason never carries a staff member's leave type.
 */
class StudentReplacementHistoryParityTest extends TestCase
{
    private function page(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-history.blade.php'));
    }

    public function test_the_six_columns_are_in_the_users_order_and_the_row_number_is_gone(): void
    {
        $src = $this->page();

        $labels = ['Course', 'Lecturer', 'Original Slot', 'Conflict Reason', 'Requested Slot', 'Request Status'];
        $last = -1;
        foreach ($labels as $label) {
            $pos = mb_strpos($src, "label: '".$label."'");
            $this->assertNotFalse($pos, 'the table lost its '.$label.' column');
            $this->assertGreaterThan($last, $pos, $label.' is out of the ruled order');
            $last = $pos;
        }

        $this->assertStringNotContainsString("label: '#'", $src, 'the row-number column was dropped by ruling');
        $this->assertStringNotContainsString("label: 'Subject'", $src, 'the column is Course now, not Subject');
        $this->assertStringNotContainsString("label: 'New Slot'", $src, 'the column is Requested Slot now');
    }

    public function test_the_toggle_is_the_staff_control_with_the_staff_default(): void
    {
        $src = $this->page();

        $this->assertStringContainsString('<input type="checkbox" id="hideCompleted" checked>', $src, 'ON by default, like staff');
        $this->assertStringContainsString('Exclude <span class="info-keyword">COMPLETED</span> Status', $src);
        $this->assertStringContainsString('data-tip="ON = completed requests are excluded from the list"', $src);
        $this->assertStringNotContainsString('id="showPast"', $src, 'the inverted Show Past control is gone');

        // the filter reads the derived status, exactly as the badge prints it
        $this->assertStringContainsString("r.status === 'completed'", $src);
    }

    public function test_the_all_weeks_option_names_the_date_it_filters_on(): void
    {
        $src = $this->page();

        $this->assertStringContainsString("allLabel: 'All Requested Replacement Weeks'", $src);
        $this->assertStringContainsString('Requested Replacement Week:', $src, 'the active-filter chip uses the same words');
    }

    public function test_the_summary_card_says_completed(): void
    {
        $src = $this->page();

        $this->assertStringContainsString("'valueId' => 'sumCompleted'", $src);
        $this->assertStringContainsString("'label' => 'Completed'", $src);
        $this->assertStringNotContainsString('sumPast', $src, 'Past and Completed are the same state — one word');
    }

    public function test_the_status_badge_is_the_staff_badge_with_the_server_label(): void
    {
        $src = $this->page();

        $this->assertStringContainsString("'<span class=\"badge status-' + r.status + '\" data-tip=\"' + tip + '\">' + r.statusLabel + '</span>'", $src);
        $this->assertStringNotContainsString('badge-past', $src, 'the student-only Past chip is gone — Completed says it');
        $this->assertStringNotContainsString("r.status === 'replacement'", $src, 'the student-only status vocabulary is gone');
    }

    public function test_the_requested_slot_only_counts_down_while_pending(): void
    {
        $src = $this->page();

        $this->assertStringContainsString("const pending = r.status === 'pending';", $src);
        $this->assertStringContainsString('slotBlock(r.orig, true)', $src, 'the original slot always carries its countdown');
        $this->assertStringContainsString('slotBlock(r.req, pending)', $src, 'the requested slot carries it only while pending');
    }

    public function test_a_conflict_reason_never_carries_a_leave_type(): void
    {
        $src = $this->page();

        $this->assertStringContainsString("r.conflictKind === 'Public Holiday'", $src);
        $this->assertStringContainsString("'Class Cancelled'", $src);
        $this->assertStringNotContainsString('r.conflictReason', $src, 'the page must not print the raw reason (leave types)');

        $component = (string) file_get_contents(base_path('app/Livewire/ReplacementHistory.php'));
        $this->assertStringContainsString('$conflictKind = $rawReason === null', $component);
        $this->assertStringContainsString("? 'Public Holiday' : 'Class Cancelled'", $component);
    }
}
