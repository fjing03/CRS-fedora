<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 2026-11-09 (user brief) — the COURSE column on Request Approval and My
 * Replacements.
 *
 * Two laws:
 *  1. the header label is `Course` (it was `Course Code & Name`, a label that
 *     promised two things the cell already shows);
 *  2. its hover text carries the row's REAL course code, full course name and
 *     class type, then the one description sentence:
 *     `AMSE2003, Software Testing and Quality Assurance (L) — Subject or Course
 *     affected by the conflict`.
 *
 * The sentence lives in ONE place (`UiLabels.courseTipSubject`), so the two pages
 * cannot drift apart, and both row builders must ask it rather than re-typing it.
 * Source invariants: the tables are built in the browser, so there is no server
 * HTML to assert against — no database, no browser needed here.
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
        $approval = $this->source('resources/views/livewire/request-approval.blade.php');
        $home = $this->source('resources/views/livewire/replacement-home.blade.php');

        foreach (['Request Approval' => $approval, 'My Replacements' => $home] as $page => $src) {
            $this->assertMatchesRegularExpression(
                "/label:\s*'Course',/",
                $src,
                $page.' must label the column Course',
            );
            $this->assertStringNotContainsString(
                'Course Code &amp; Name',
                $src,
                $page.' still carries the old two-part label',
            );
            $this->assertStringNotContainsString(
                "label: 'Course Code & Name'",
                $src,
                $page.' still carries the old two-part label',
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

    public function test_both_row_builders_ask_the_shared_tip(): void
    {
        // Request Approval composes its own cell (page-local), keyed on the mapper's
        // `courseCode` / `courseName` / `classType` keys.
        $approval = $this->source('resources/views/livewire/request-approval.blade.php');
        $this->assertMatchesRegularExpression(
            '/data-tip="\'\s*\+\s*escHtml\(UiLabels\.courseTip\(r\.courseCode, r\.courseName, r\.classType\)\)/',
            $approval,
            'the Request Approval course cell must carry the shared tip built from the row\'s own data',
        );

        // My Replacements' cell comes from the shared builder, which must emit the tip
        // key; the page then applies it to the cell.
        $js = $this->source('public/js/ui-common.js');
        $this->assertStringContainsString(
            'tip: UiLabels.courseTip(c.code, c.name, c.type)',
            $js,
            'replacementHomeRow must emit the course tip for the merged table',
        );

        $home = $this->source('resources/views/livewire/replacement-home.blade.php');
        $this->assertMatchesRegularExpression(
            "/if \(cell\.tip\) \{ td\.setAttribute\('data-tip', cell\.tip\); \}/",
            $home,
            'the My Replacements row loop must put the cell tip on the <td>',
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

        // the shape: `code, name (Type)` then an em dash then the sentence
        $this->assertStringContainsString(
            "' (' + this.classType(type, 'code') + ')'",
            $js,
            'the tip must print the class type in the (L)/(T)/(P) form the cells use',
        );
        $this->assertStringContainsString(
            "' \\u2014 ' + this.courseTipSubject",
            $js,
            'code+name+type and the description must be joined by an em dash',
        );
    }
}
