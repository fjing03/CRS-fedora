<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * theme.css integrity (2026-11-10).
 *
 * A single misplaced `*​/` inside a comment block closes it early: the rest of the
 * original comment becomes "CSS", the browser hits a parse error and DROPS the
 * rules that follow. That is exactly what happened on 2026-11-10 — a note added to
 * the `arrangement-cell-state-labels` block swallowed everything after it,
 * including `.today-btn` and the login card's theme rules, so the page looked like
 * "some CSS didn't load" (it had loaded: 200, 148 KB, but was not applied).
 *
 * These are source invariants — cheap to run, and they fail loudly on the next
 * careless edit instead of on a screenshot.
 */
class ThemeCssIntegrityTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(public_path('css/theme.css'));
    }

    /** Walks the file the way the CSS tokeniser does, string-aware. */
    private function scan(string $css): array
    {
        $inComment = false;
        $strayClosers = [];
        $depth = 0;
        $negative = [];
        $lineNo = 1;
        $len = strlen($css);

        for ($i = 0; $i < $len; $i++) {
            $ch = $css[$i];
            $next = $i + 1 < $len ? $css[$i + 1] : '';

            if ($ch === "\n") {
                $lineNo++;
            }

            if ($inComment) {
                if ($ch === '*' && $next === '/') {
                    $inComment = false;
                    $i++;
                }

                continue;
            }

            if ($ch === '/' && $next === '*') {
                $inComment = true;
                $i++;

                continue;
            }

            if ($ch === '*' && $next === '/') {
                $strayClosers[] = $lineNo;
                $i++;

                continue;
            }

            /* strings: url('…'), content: '…' — never treat their braces as syntax */
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $i++;
                while ($i < $len && $css[$i] !== $quote) {
                    if ($css[$i] === '\\') {
                        $i++;
                    }
                    if ($css[$i] === "\n") {
                        $lineNo++;
                    }
                    $i++;
                }

                continue;
            }

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth < 0) {
                    $negative[] = $lineNo;
                    $depth = 0;
                }
            }
        }

        return [
            'stray_closers' => $strayClosers,
            'negative_depth' => $negative,
            'final_depth' => $depth,
            'unclosed_comment' => $inComment,
        ];
    }

    public function test_comment_markers_are_balanced(): void
    {
        $css = $this->css();
        $scan = $this->scan($css);

        $this->assertSame([], $scan['stray_closers'],
            'a `*/` with no open comment — it silently drops every rule that follows; lines: '
            .implode(', ', $scan['stray_closers']));
        $this->assertFalse($scan['unclosed_comment'], 'a comment is never closed — everything after it is dead');

        $this->assertSame(
            substr_count($css, '/*'),
            substr_count($css, '*/'),
            'the file must carry the same number of /* and */ markers',
        );
    }

    public function test_braces_are_balanced(): void
    {
        $scan = $this->scan($this->css());

        $this->assertSame([], $scan['negative_depth'], 'a `}` closes nothing; lines: '.implode(', ', $scan['negative_depth']));
        $this->assertSame(0, $scan['final_depth'], 'the file ends inside an open block');
    }

    /** The rules a parse break must never take with it. */
    public function test_the_shared_controls_keep_their_rules(): void
    {
        $css = $this->css();

        foreach ([
            '.today-btn {' => 'the Today button (week nav)',
            '.filter-select {' => 'the shared select',
            '.badge {' => 'the badge family',
            '.modal {' => 'the modal shell',
            '.summary-card' => 'the summary cards',
            '.cell-content:hover > .cell-state-label' => 'the occupied hover label (kept permanently)',
            '.cell-occupied > .cell-state-label' => 'the occupied label colours',
        ] as $needle => $what) {
            $this->assertStringContainsString($needle, $css, "missing rule for {$what}");
        }
    }
}
