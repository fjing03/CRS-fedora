<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 2026-11-09 (user briefs, same day, two passes) — the COURSE column on Request
 * Approval and My Replacements.
 *
 * The laws as they now stand:
 *  1. the header label is `Course` (it was `Course Code & Name`, a label that
 *     promised two things the cell already shows);
 *  2. the column is explained ONCE, on its header: `Subject or Course affected by
 *     the conflict`;
 *  3. the CELLS carry NO hover text. The first pass put the row's own code, full
 *     name and class type on every cell; the user removed it the same day — the
 *     cell already prints code and name, so the hover only repeated itself.
 *
 * The sentence lives in ONE place (`UiLabels.courseTipSubject`), so the two pages
 * cannot drift apart. Source invariants: the tables are built in the browser, so
 * there is no server HTML to assert against — no database, no browser needed here.
 */
class CourseColumnTipTest extends TestCase
{
    private function source(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    private const SENTENCE = 'Subject or Course affected by the conflict';

    public function test_both_pages_label_the_column_course(): void
    {
        foreach ([
            'resources/views/livewire/request-approval.blade.php',
            'resources/views/livewire/replacement-home.blade.php',
        ] as $path) {
            $src = $this->source($path);

            $this->assertMatchesRegularExpression(
                "/label:\s*'Course',/",
                $src,
                basename($path).' must label the column Course',
            );
            $this->assertStringNotContainsString(
                'Course Code &amp; Name',
                $src,
                basename($path).' still carries the old two-part label',
            );
            $this->assertStringNotContainsString(
                "label: 'Course Code & Name'",
                $src,
                basename($path).' still carries the old two-part label',
            );
        }
    }

    public function test_both_headers_take_the_hint_from_the_one_sentence(): void
    {
        foreach ([
            'resources/views/livewire/request-approval.blade.php',
            'resources/views/livewire/replacement-home.blade.php',
        ] as $path) {
            $src = $this->source($path);

            $this->assertStringContainsString(
                'tip: UiLabels.courseTipSubject',
                $src,
                basename($path).': the header hint must come from the shared sentence, not a local copy',
            );
            $this->assertStringNotContainsString(
                self::SENTENCE,
                $src,
                basename($path).': the sentence is typed into this page — it belongs in UiLabels only',
            );
        }
    }

    public function test_the_course_cells_carry_no_hover_text(): void
    {
        // Request Approval composes the cell in the page; only the header may carry a tip.
        $approval = $this->source('resources/views/livewire/request-approval.blade.php');
        $this->assertStringNotContainsString(
            'UiLabels.courseTip(',
            $approval,
            'Request Approval: the row cell must not build a hover text — the header explains the column',
        );
        $this->assertMatchesRegularExpression(
            '/html \+= \'<td><div class="cell-code">\' \+ r\.courseCode/',
            $approval,
            'Request Approval: the course cell must stay a plain <td> with no data-tip',
        );

        // My Replacements' cell comes from the shared builder, which must emit no tip key.
        $js = $this->source('public/js/ui-common.js');
        $this->assertStringNotContainsString(
            'tip: UiLabels.courseTip(',
            $js,
            'replacementHomeRow must not attach a course tip to the merged table cell',
        );
        $this->assertMatchesRegularExpression(
            "/cls: 'col-code'\s*\n\s*\},/",
            $js,
            'replacementHomeRow: the course cell ends at its class — no tip key',
        );

        // and nothing applies cell tips on the My Replacements row anymore
        $home = $this->source('resources/views/livewire/replacement-home.blade.php');
        $this->assertStringNotContainsString(
            "td.setAttribute('data-tip'",
            $home,
            'the My Replacements row loop must not look for a per-cell tip',
        );
    }

    public function test_the_sentence_lives_in_exactly_one_place(): void
    {
        $js = $this->source('public/js/ui-common.js');

        $this->assertSame(
            1,
            substr_count($js, self::SENTENCE),
            'the description sentence appears more than once in ui-common.js',
        );
        $this->assertStringContainsString(
            "courseTipSubject: '".self::SENTENCE."',",
            $js,
            'UiLabels.courseTipSubject is the one home of the description sentence',
        );
    }
}
