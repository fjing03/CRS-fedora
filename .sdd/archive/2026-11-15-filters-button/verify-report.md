# Verify — `filters-button`

**2026-11-15.** Independent read-only verification (separate pi subprocess, `commandcode/z-ai/glm-5.3-flash`,
no edit tools) against the four frozen batches, in three rounds. Verdict: **PASS**.

## Round 1 — FAIL (2 blockers)

1. **"The evidence is arithmetically impossible."** The reviewer counted 20 `test()` declarations against
   the receipts' 23 results and concluded three tests had vanished after the last recorded run.
   *Resolution:* the arithmetic was the reviewer's, not the evidence's — `playwright test --list` reports
   **23 tests in 2 files = 20 in `filters-button.spec.ts` + 3 `auth.setup.ts` logins**, and the receipt's
   "21 passed / 2 skipped" decomposes as 18 spec passes + 3 setup passes + the 2 data-derived skips. The
   claim was refuted; the *apply-log sentence* written to close it was then wrong twice in a row (first a
   phantom "three-page loop generates tests", which the reviewer disproved by showing the loops sit inside
   test bodies; then a stale commit hash). Both are fixed in the shipped text.
2. **An out-of-scope edit rode in the commit.** Request Approval's `#statusFilter` sits inside the toolbar
   this lane rewrote, so another session's uncommitted removal of the `Cancelled` option was swept into the
   commit — out of scope on two counts (proposal §3 "none is dropped", spec R3 "keeps its options").
   *Fixed:* the commit keeps the option exactly as HEAD had it; the removal is back to being that session's
   uncommitted change (recorded as apply-log §3.6).

## Round 2 — FAIL (1 narrowed blocker)

The evidence substance was confirmed sound (the true decomposition above), but the apply-log paragraph
closing it still stated a false in-file count. Also closed in this round: `my-replacements-after-submit`'s
single red is now in apply-log §4; the three page changelogs no longer claim "22 passed, 1 skipped".

## Round 3 — PASS, after one doc line

Round 2's only blocker was a commit hash cited in the apply log that stopped existing the moment the commit
was amended. The citation is gone (the text now says "this lane's single commit" and explains why no hash is
quoted). Everything else verified unchanged: 5 files differ from round 2 — the apply log, the three
changelogs' count line, and a comment date in the parity test. No code, no test logic, no behaviour.

## Verified dimensions

| dimension | verdict |
|---|---|
| **Tasks** | 23/23 ticked, and every task's delivered change was located in the commit |
| **Scope** | all 11 in-scope proposal items present; `ui-week-nav.blade.php` byte-identical (empty diff); no template touched; no migration, no new dependency, no DB write; summary cards / headers / guides untouched |
| **Design fidelity** | move-not-rebuild with ids intact and `wire:ignore` on host + body; `.filters-ready` reveal last; card clamp + sheet geometry; staff-only Reset; one-overlay-at-a-time hooks in all three places; print relocation; capture-phase Escape with dialog suspension; own scroll-lock class; z 1200 / trigger 1300 — all located at file:line |
| **Spec R1–R8** | R1–R7 IMPLEMENTED with code and test citations; R8 satisfied (helper, four rewired specs, the e2e pair, the parity test's precise amendment, three changelogs) |
| **Deviations** | the three recorded in apply-log §3 are implemented as described and compatible with the frozen design (the `position:fixed` one resolves a contradiction between design §3 and design §5's snippet in §3's favour) |

## Known reds that are not this lane

- `summary-cards-button` T11a — the testing DB has no mixed statuses, so "filter by Need to Arrange" cannot change any number.
- `my-replacements-after-submit` — its precondition cell is fenced in the testing DB (`cell-reserved`); the spec's own header asks for a re-seed between runs.
- `request-approval.spec` (6) — the testing DB holds no pending request.
- `StudentReplacementHistoryParityTest::test_the_route_and_label_are_the_ruled_ones` — pins the old page title; the newer title is another session's uncommitted work.
- `ui-regression` TC-3.x / TC-7.2 and the arrangement receipts — long-documented parked reds.

## Lane evidence

`tests/filters-button.spec.ts` 21 passed / 2 skipped on five runs; the four rewired specs 11 / 6 / 6 / 12;
`pages-parity`, `nav-identity`, `replacement-home-status-badges` green. Measured at 390×844: first card
519 → 214px (My Replacements), 541 → 226px (Request Approval), 375 → 247px (Class Replacements); chips row
120 → 72px; 34px at 1440. Screenshots in `/tmp/filters-apply/shots/`.

**Ready to archive.**
