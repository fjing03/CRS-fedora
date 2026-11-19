# Cohort and Venue Timetable: working touch summaries

Status: applied and verified locally; independent review explicitly waived by the owner. Owner requested the P1 fix. Scope is these two pages only; the remaining summary critique findings are out of scope.

Review attempt: the independent pi reviewer returned HTTP 429 (weekly provider limit), so no review verdict was produced.

## Problem and decision

At 390px coarse touch both timetables render the old header Summary icon. `SummarySheet.init()` deliberately skips `.tt-responsive`, so that icon is not wired; the separate `#ttSummaryButton` opens `#ttSheet`. A real tap sets `.is-flipped`, but both pages' local `(hover: none)` rules give synthetic `:hover` a more specific `transform: none`, exposing the front over the explanation button. The shared summary hover flip already requires `(hover: hover) and (pointer: fine)`.

Remove the header's `summaryButton` opt-in on both pages and remove their redundant card-inner hover overrides. Keep the existing Week summary opener, details dialog and scripts. The restored tap path exposed the explanation at z-index 1000 behind the sheet at 1200. Reuse the existing 1400 explanation layer while the Week summary is open, scoped to these two pages only. No changes to card text, colour, data, breakpoints, print or other controls.

## Acceptance checks and tasks

- [x] Check the existing source and test contracts; add a focused read-only receipt on both pages at 390px with `hasTouch` and `isMobile`. Require no old header button, a working Week summary tap, and a real tap on the first card's info button that opens an explanation over the still-open sheet. Confirm closing the explanation leaves the sheet open. Run the receipt before production edits and confirm it rejects the baseline.
- [x] Drop the two obsolete opt-ins and two hover overrides. At desktop width the existing inline cards still render with no visible sheet opener. No forced clicks or DOM mutations to prove the touch path.
- [x] Retarget stale legacy SummarySheet-only tests from Venue to My Replacements, which still uses that module. Keep the Venue wire-ignore assertion but exercise the actual `#ttSheetBody` path. Do not broaden this to unrelated test cleanup.
- [x] Run the scoped read-only browser receipts on the protected testing lane (no business data writes or fixtures). Inspect at least one rendered card back per touched page; record results and add dated page changelog entries.

## Verification

Both new Chromium touch tests rejected the baseline (dead icon present; card front intercepted the explanation tap). With the fix, the two read-only specs passed together: **46 passed**, including desktop inline/print checks. Separate 390px touch captures in both themes confirmed the card back renders and the explanation can be opened and closed without closing the sheet. Pint and PHPStan passed. No business data changed. Raw receipts and screenshots: `/tmp/crs-summary-touch/`.
