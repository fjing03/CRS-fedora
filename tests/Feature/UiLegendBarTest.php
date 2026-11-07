<?php

namespace Tests\Feature;

use App\Livewire\CohortTimetable;
use App\Livewire\VenueTimetable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * arrangement-slot-ownership-legend — the shared legend bar.
 *
 * The partial is used by three pages. The arrangement grid became the first
 * page whose "others" border is NOT the 0.5px hairline (it is no border at
 * all), so the bar learnt an optional list of hint lines. The two reference
 * pages must keep the sentence they shipped with — a legend that claims a
 * border the page does not draw is the bug this lane exists to prevent.
 */
class UiLegendBarTest extends TestCase
{
    use RefreshDatabase;

    /** The exact sentence the cohort and venue pages ship. */
    private const REFERENCE_HINT = 'thick border (3px) = your classes';

    private const REFERENCE_THIN = "thin border (0.5px) = others'";

    public function test_default_hint_keeps_the_reference_wording_and_both_demos(): void
    {
        $html = view('partials.ui-legend-bar', [
            'ownershipHint' => true,
            'items' => [['class' => 'event-normal', 'label' => 'Normal', 'tip' => 'x']],
        ])->render();

        $plain = preg_replace('/\s+/', ' ', $html);

        $this->assertStringContainsString(self::REFERENCE_HINT, $plain);
        $this->assertStringContainsString(self::REFERENCE_THIN, $plain);
        $this->assertStringContainsString('osd-demo osd-thick', $html);
        $this->assertStringContainsString('osd-demo osd-thin', $html);
        $this->assertStringNotContainsString('no border = others', $plain);
    }

    public function test_custom_hint_list_renders_its_own_lines_and_a_borderless_demo(): void
    {
        $html = view('partials.ui-legend-bar', [
            'ownershipHint' => [
                ['demo' => 'osd-thick', 'text' => 'thick border (3px) = yours'],
                ['demo' => '', 'text' => "no border = others'"],
            ],
            'items' => [['class' => 'cell-available', 'label' => 'Available', 'tip' => 'x']],
        ])->render();

        $plain = html_entity_decode(preg_replace('/\s+/', ' ', $html), ENT_QUOTES);

        $this->assertStringContainsString('thick border (3px) = yours', $plain);
        $this->assertStringContainsString("no border = others'", $plain);
        $this->assertStringContainsString('osd-demo osd-thick', $html);
        $this->assertStringNotContainsString('osd-thin', $html);
        $this->assertStringNotContainsString('thin border (0.5px)', $plain);
        $this->assertSame(1, substr_count($plain, '·'), 'exactly one separator, no leading/trailing one');
    }

    public function test_swatches_carry_the_real_class_so_fill_and_border_show(): void
    {
        $html = view('partials.ui-legend-bar', [
            'items' => [
                ['class' => 'cell-occupied cell-occupied--mine', 'label' => 'Conflict (Yours)', 'tip' => 'x'],
                ['class' => 'cell-reserved', 'label' => 'Pending (Others)', 'tip' => 'y'],
            ],
        ])->render();

        $this->assertStringContainsString('class="legend-swatch cell-occupied cell-occupied--mine"', $html);
        $this->assertStringContainsString('class="legend-swatch cell-reserved"', $html);
        $this->assertStringNotContainsString('ownershipHint', $html);
    }

    public function test_reference_pages_keep_the_unchanged_hint_line(): void
    {
        /* RefreshDatabase migrates an EMPTY testing DB (no seeders), so the
           viewer is a factory lecturer: the legend bar renders either way. */
        $user = User::factory()->lecturer()->create();

        $cohort = Livewire::actingAs($user)->test(CohortTimetable::class)->html();
        $venue = Livewire::actingAs($user)->test(VenueTimetable::class)->html();

        foreach (['cohort-timetable' => $cohort, 'venue-timetable' => $venue] as $page => $html) {
            $plain = preg_replace('/\s+/', ' ', $html);

            $this->assertStringContainsString(self::REFERENCE_HINT, $plain, "{$page} keeps the reference hint");
            $this->assertStringContainsString(self::REFERENCE_THIN, $plain, "{$page} keeps the 0.5px demo wording");
            $this->assertStringNotContainsString("no border = others'", $plain, "{$page} must not adopt the arrangement wording");
        }
    }
}
