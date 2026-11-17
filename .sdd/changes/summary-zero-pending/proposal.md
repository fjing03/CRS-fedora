# summary-zero-pending — compact proposal, decisions, acceptance and tasks

Status: VERIFIED — independent reviewer PASS (one round), applied and scoped checks PASS. Not archived.
Date: 2026-11-18. User approved: suppress zero pending suffixes across summary cards; preserve positive suffixes; no tooltip/toast changes.

## Scope and decision

Two presentation writers exist: `computeSummary()` → local `setSplit()` in `public/js/ui-common.js` (staff/student/cohort timetables), and Venue Timetable `updateSummary()` → local `split()` in `resources/views/livewire/venue-timetable.blade.php`. Venue uses server-computed confirmed values; computeSummary subtracts pending from its full totals. Preserve those different accounting rules, formatting, ids and classes. In each writer append the existing `.sum-pending` span only when the numeric pending component is >0. Venue accepts numeric strings, including '0' and fractional hours. The zero case must remove a previously rendered tail, not merely skip an update. Keep no-opt-in computeSummary output unchanged. No new helper, dependency, token, count derivation, CSS or DOM relocation. Existing desktop and relocated sheet nodes inherit the same rendering.

## Acceptance

1. Zero pending classes/hours show only the same confirmed number, with no `.sum-pending` element: including all-zero cards and Venue string '0'.
2. Positive pending values retain existing `base<span class="sum-pending">+N</span>` markup and formatting, including fractional pending hours.
3. Positive→zero and zero→positive updates work; no stale suffix remains.
4. Total/replacement/pending/conflict/available and own counts are unchanged; Venue confirmed semantics are not replaced by computeSummary semantics.
5. Staff/student/cohort/venue summary consumers are covered; desktop and real-touch phone sheet checked. No DB fixture writes, PHPUnit or full-suite run on protected data. Synthetic browser DOM/payload checks are explicitly labelled.
6. Update any directly affected receipt that assumes zero tails always exist; do not repair unrelated fixture failures. Existing `venue-status-parity.spec.ts` assumes four tails even when own pending is zero, so replace only that tail invariant with per-card positive-tail assertions. Keep pending Total/Occupied expectations where pending fixtures are required.
7. Update four page changelogs and survey status; do not close the remaining-state critique snapshot, which contains unrelated unresolved issues.

## Tasks

- [x] Independent read-only review of this artifact, one round; actual verdict PASS.
  Read-only pi subprocess PID77939, commandcode/z-ai/glm-5.3-flash; full output
  `/tmp/summary-zero-review.txt`. No blockers. Numeric comparison must handle strings;
  update Venue's obsolete always-+0 comment. Coordinator additionally found nine literal
  zero-tail expectations in the writing `cancelled-slot-reuse.spec.ts`; update their display
  expectations only, and do NOT execute that writing fixture on protected manual data.
- [x] Add positive-pending guards to both writers; correct nearby obsolete comments only.
- [x] Add focused read-only browser receipts for zero/positive/fractional/transitions, opt-out and Venue numeric-string behaviour. Inspect real pages in light/dark at desktop/touch phone sizes. No protected-data mutations.
- [x] Run focused receipts, node syntax check, PHP lint/type checks for Blade touch, and scoped Impeccable detector once. Record pre-existing failures honestly.
- [x] Record verification and update all four page changelogs + survey; stage/commit only this lane. No push; archive only on user's word.

## Verification receipt

- `tests/summary-zero-pending.spec.ts`: 4 expected failures before production edits (zero tails), then4 PASS, plus3 auth setups.
- Combined `summary-zero-pending`, `timetable-responsive`, `cohort-venue-responsive`: **70 PASS**, including3 auth setups; `/tmp/summary-zero-regression.txt`.
- Both positive→zero/zero→positive and fractional +0.5 tested. Opt-out math and Venue confirmed-first/string formatting retained. Browser-only fixture exposure, no DB writes.
- Real visual batch: staff/student/cohort/venue,1440/390touch,light/dark =16 captures; all summaries visible,0 zero-valued tails; `/tmp/summary-zero-visual/measurements.json` and contact sheet `/tmp/summary-zero-contact.png` inspected.
- Browser MCP refused because another browser session owns its profile. Used isolated Playwright Chromium fallback; did not terminate anyone's browser.
- `node --check public/js/ui-common.js`, `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G`: PASS. Existing missing SQLite-extension startup warnings nonblocking; no PHPUnit run.
- One scoped Impeccable detector run:10 pre-existing advisories (8 type-ramp,2 literal shadow colours), none in edited lines. No unrelated repair.
- `venue-status-parity.spec.ts` zero/four-tail invariants updated;9 zero-tail literals updated in writing `cancelled-slot-reuse.spec.ts`. Those fixture-based suites were NOT executed against protected data; focused actual-writer receipts cover the changed presentation.
- Four page changelogs and `docs/ui-drift-2026-11-17.md` §10 synced. Tooltip/toast placement and source totals unchanged. No snapshot closed for unrelated findings. No archive performed.
