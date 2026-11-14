# Independent reviews

## Proposal Round 1 — 2026-11-14
PASS. Reviewer read exploration/proposal, old test, current component and archived design. Confirmed exact cohort boundary, occurrence keys, pending proposed slot, local clock and testing-only gates. Nonblocking request: design should name exact fields; design pins orig/req, identities, statusShort and public conflictKind. No outstanding blockers. Receipt: /tmp/crs-history-test-fix/proposal-review.txt.

## Design Round 1 — 2026-11-14
PASS. Independent review checked explicit date arithmetic, callback clock restoration, orig/req and identity distinction, public status vocabulary and strengthened foreign/private absence. No outstanding blockers. Receipt: /tmp/crs-history-test-fix/design-review.txt.

## Tasks Round 1 — 2026-11-14
PASS. Independent review validated actual failing baseline/current_database receipts, <=2h units, complete proposal acceptance mapping, no weaker privacy, and limited implementation/main-check split. No outstanding blockers. Receipt: /tmp/crs-history-test-fix/tasks-review.txt.

## Implementation / scoped Verify Round 1 — 2026-11-14
PASS for the requested repair. Independent reviewer compared the complete test file to original HEAD: only the obsolete method changed, helpers/seven sibling methods byte-identical. Verified occurrence/request identities, independent date/geometry assertions, pending proposal distinction, stronger privacy and callback clock restoration. Read main's 1/1 (37 assertions), 49/49 related (323 assertions), lint/PHPStan receipts; reviewer ran no tests. Whole file remains 7/8 due a separately reproduced pre-existing Saturday→Sunday fixture error in an unchanged sibling method. No full-suite clean claim; stop/report before additional scope. Receipt: /tmp/crs-history-test-fix/implementation-review.txt. Details: verify-report.md.

## User-approved scope extension / Proposal Round 2 — 2026-11-14
User approved the second boundary method's date setup. Full-chain unfreeze recorded before edits. Proposal Round 2 PASS: only that method, legitimate in-semester Thu/Fri, existing status assertions/helpers/schema preserved, no product/commit scope creep. Verbatim method name and exact date pinned as nonblocking clarifications. Receipt: /tmp/crs-history-test-fix/proposal-review-r2.txt.

## Design Round 2 — 2026-11-14
PASS. Verified fixed Thu/Fri Nov12–13 week11/day3–4, same UTC/campus date and campus17:00 after today's16:00 slot (date-only status remains approved), callback restore, unchanged assertions/helpers/first repair and no product scope. Receipt: /tmp/crs-history-test-fix/design-review-r2.txt.

## Tasks Round 2 — 2026-11-14
PASS. T4/T5 align with approved clock/coordinates and unchanged assertion scope; actual six-suite receipt, serial guarded testing DB, independent review and preservation, optional full run bounded by original timebox. No blockers. Receipt: /tmp/crs-history-test-fix/tasks-review-r2.txt.

## Implementation / scoped Verify Round 2 — 2026-11-14
PASS. Independent read-only reviewer checked exact callback date/cell coordinates, preserved requests and both old status assertions, earlier repair byte-identical, no helper/schema/app edits or clock leak. Main's actual receipts: both focused2/2 (48 assertions), file8/8 (97), related49/49 (323), lint/PHPStan PASS. Reviewer did not run tests. Receipt: /tmp/crs-history-test-fix/implementation-review-r2.txt.

Main subsequently completed full backend suite: **721 tests, 719 passed, 6,066 assertions, 2 errors**, 587.263sec. Both remaining failures are Saturday→Sunday fixtures in unchanged MyReplacementsPayloadTest and ReplacementHomeTest; both independently reproduce in an isolated separate PHPUnit process. No edits to those files, no new full-suite-green or cancelled-slot-reuse Verify claim. See verify-report.md and full-suite.txt / other-date-errors-isolated.txt. Stop/report for further scope approval.

## Third approval / Proposal Round 3 — 2026-11-14
User explicitly approved only the two remaining named date fixtures. Full-chain unfreeze before decision edits. Independent proposal Round3 PASS: exact methods, local weekday clock, all old assertions/no-write preserved, relative semester requires derived Thursday to avoid future aging, no other scope. Receipt: /tmp/crs-history-test-fix/proposal-review-r3.txt.

## Design Round 3 — 2026-11-14
PASS. Relative-term derived Thu week4/day3 and fixed-term Nov12 week11/day3, adjacent Wed/Fri coordinates and semester bounds verified; 09UTC=17MYT same calendar date; all old assertions/helpers/previous repairs preserved, callback restore. Receipt: /tmp/crs-history-test-fix/design-review-r3.txt.

## Tasks Round 3 — 2026-11-14
PASS. Exact two-method T6/T7 scope, relative/fixed clocks, coordinate guards, retained assertions/no-write/helpers/earlier repairs, guarded serial checks and bounded full-suite rerun verified. Receipt: /tmp/crs-history-test-fix/tasks-review-r3.txt.

## Implementation / scoped Verify Round 3 — 2026-11-14
PASS. Independent read-only reviewer verified both callback freezes, relative-semester safety, all six coordinate guards, intact status/label/no-write assertions, unchanged helpers/setUp/first repairs, no clock leak or production/schema edits. Focused pair2/2 (33 assertions), three full files31/31 (308), lint/PHPStan passed. Reviewer ran no tests. Receipt: /tmp/crs-history-test-fix/implementation-review-r3.txt.

Main's subsequent FINAL full backend run: **721/721 PASS, 6,090 assertions, 571.751sec**, /tmp/crs-history-test-fix/full-suite-r2.txt. All four approved test repairs complete; no outstanding blocker in this narrow change. Earlier failed full-run and intermediate failure receipts remain historical, superseded by this pass. This does not close the separate cancelled-slot-reuse whole-feature/browser acceptance Verify. No commit/push or demo work. Ready for user-authorised commit/archive.
