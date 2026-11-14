<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * guide-toggle-icon-only — the shared partials stay backward compatible for
 * non-opted callers. The live pages are covered in the browser; this pins the
 * Blade-only fallback that no browser route can reach.
 */
class GuideTogglePartialTest extends TestCase
{
    public function test_header_without_opt_in_keeps_description_and_has_no_icon(): void
    {
        $html = Blade::render(
            "@include('partials.ui-page-header', ['title' => 'Example', 'description' => 'Plain copy'])"
        );

        $this->assertStringContainsString('Example', $html);
        $this->assertStringContainsString('page-desc', $html);
        $this->assertStringContainsString('Plain copy', $html);
        $this->assertStringNotContainsString('guide-toggle-icon', $html);
    }

    public function test_header_opt_in_renders_one_icon_and_wraps_the_main_content(): void
    {
        $html = Blade::render(
            "@include('partials.ui-page-header', ['title' => 'Example', 'guideToggle' => true, 'guideLabel' => 'Open guide'])"
        );

        $this->assertStringContainsString('page-header-main', $html);
        $this->assertStringContainsString('guide-toggle-icon', $html);
        $this->assertStringContainsString('aria-controls="guideBlock"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('Open guide', $html);
        $this->assertStringContainsString('width="18"', $html);
    }

    public function test_guide_block_default_keeps_the_legacy_full_bar(): void
    {
        $html = Blade::render(
            "@include('partials.ui-guide-block', ['guideTitle' => 'How to use this page', 'guideItems' => ['One item']])"
        );

        $this->assertStringContainsString('class="guide-block"', $html);
        $this->assertStringContainsString('class="guide-toggle"', $html);
        $this->assertStringContainsString('class="guide-icon"', $html);
        $this->assertStringContainsString('onclick="document.getElementById(\'guideBlock\').classList.toggle(\'open\')"', $html);
        $this->assertStringContainsString('width="16"', $html);
        $this->assertStringContainsString('One item', $html);
        $this->assertStringNotContainsString('guide-toggle-icon', $html);
    }

    public function test_guide_block_content_mode_renders_description_only_without_an_empty_list(): void
    {
        $html = Blade::render(
            "@include('partials.ui-guide-block', ['showToggle' => false, 'guideDescriptionHtml' => '<span class=\"info-keyword\">Rich</span> copy'])"
        );

        $this->assertStringContainsString('guide-block--content', $html);
        $this->assertStringContainsString('guide-description', $html);
        $this->assertStringContainsString('<span class="info-keyword">Rich</span> copy', $html);
        $this->assertStringNotContainsString('class="guide-toggle"', $html);
        $this->assertStringNotContainsString('guide-list', $html);
    }
}
