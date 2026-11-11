<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UI update (user, 2026-11-11) — two rulings, one receipt each:
 *
 *  A. the arrangement grid's 3px ownership ring is GONE from both the conflict
 *     and the pending family, so colour alone says "cannot book" and the legend
 *     stops claiming a border the page does not paint;
 *  B. the Conflict Reason badge names only the KIND ("Public Holiday" /
 *     "Class Cancelled") and the detail rides the badge's hover — on the staff
 *     page (holiday name or cancellation reason) and on the student page
 *     (holiday name only: a leave reason is the lecturer's own business).
 */
class SlotBorderAndReasonBadgeTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(base_path('public/css/theme.css'));
    }

    private function arrangement(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-arrangement.blade.php'));
    }

    private function staff(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-home.blade.php'));
    }

    private function student(): string
    {
        return (string) file_get_contents(base_path('resources/views/livewire/replacement-history.blade.php'));
    }

    /** ── A. the ring is gone, the classes that carry the meaning stay ── */
    public function test_the_ownership_ring_is_gone_from_both_slot_families(): void
    {
        $css = $this->css();

        /* the two rules that used to paint it — read the DECLARATION BLOCK, not the
           whole file (the 3px error line exists elsewhere, e.g. the .osd demo
           chips the other pages still use) */
        $block = function (string $selector) use ($css): string {
            $at = strpos($css, $selector.' {');
            $this->assertNotFalse($at, $selector.' still exists');

            return substr($css, $at, strpos($css, '}', $at) - $at);
        };

        $this->assertStringNotContainsString('border:', $block('.cell-occupied--mine'), 'the conflict ring is gone');
        $this->assertStringNotContainsString('border:', $block('.cell-pending'), 'the pending ring is gone');
        /* and the colour lives on — this was never a colour change */
        $this->assertStringContainsString('background: var(--color-tertiary-container)', $block('.cell-pending'));
    }

    /** The legend must show what the grid draws — four looks, no ownership hint. */
    public function test_the_arrangement_legend_drops_the_ownership_hint_line(): void
    {
        $src = $this->arrangement();

        $this->assertStringNotContainsString("'ownershipHint' => [", $src, 'the "thick = yours" line is deleted, not re-worded');
        $this->assertStringNotContainsString('Thick border (3px) = Yours', $src);
        $this->assertStringContainsString("['class' => 'cell-pending', 'label' => 'Pending'", $src);
        $this->assertStringContainsString("['class' => 'cell-occupied', 'label' => 'Conflict'", $src);
        /* the conflict swatch is the PLAIN class now — it must not borrow the
           old "mine" ring style to look different from the grid */
        $this->assertStringNotContainsString("'class' => 'cell-occupied cell-occupied--mine', 'label' => 'Conflict'", $src);

        /* and no legend tip still explains a border */
        $this->assertStringNotContainsString('thick border (3px)\\n', $src);
        $this->assertStringNotContainsString('no border, you CANNOT BOOK', $src);
    }

    /** ── B. staff: short kind + the detail as the hover ── */
    public function test_the_staff_badge_names_the_kind_and_hovers_to_the_detail(): void
    {
        $src = $this->staff();

        $this->assertStringContainsString(
            "return c.conflictReason === 'Public Holiday' ? 'Public Holiday' : 'Class Cancelled';",
            $src,
        );
        $this->assertStringContainsString('function reasonDetail(c) {', $src);
        /* the holiday's own name, else the cancellation reason label */
        $this->assertStringContainsString("return c.holidayName || 'Semester holiday';", $src);
        /* the badge carries it, and the phone card passes the same hook */
        $this->assertStringContainsString("(detail ? ' data-tip=\"' + detail.replace(/\"/g, '&quot;') + '\"' : '')", $src);
        $this->assertStringContainsString('reasonTip: reasonDetail', $src);
    }

    /** ── B. student: same short kinds; only the holiday name travels ── */
    public function test_the_student_badge_hovers_the_holiday_name_only(): void
    {
        $src = $this->student();

        $this->assertStringContainsString("const text = isHoliday ? 'Public Holiday' : 'Class Cancelled';", $src);
        /* the holiday name is public, so the student gets it… */
        $this->assertStringContainsString("const detail = isHoliday ? (r.holidayName || 'Semester holiday') : '';", $src);
        /* …and a cancellation keeps NO tip: the payload never carries the reason */
        $this->assertStringNotContainsString('Class Cancelled \\u2014', $src);
        $this->assertStringNotContainsString("return 'Class Cancelled', '", $src);
    }

    /** The shared phone-card builder honours the tip hook (one badge, two layouts). */
    public function test_the_shared_card_badge_carries_the_same_tip(): void
    {
        $js = (string) file_get_contents(base_path('public/js/ui-common.js'));

        $this->assertStringContainsString('(opts.reasonTip && opts.reasonTip(c))', $js);
        $this->assertStringContainsString('opts.reasonText ? opts.reasonText(c) : c.conflictReason', $js);
    }
}
