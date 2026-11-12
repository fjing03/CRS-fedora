# Review log — replacement-arrangement-top-zone-round2

## Proposal Round 1 — 2026-11-12

Batch: `proposal.md` (new). Nothing frozen. Reviewed against `explore-brief.md`,
the committed Round1 change, the approved prototype and live baseline `7692d80`.

### 🔴 Fixed

- Replaced the contradictory breakpoint prose with one width × state grid
  matrix (`<=1100`, `1101–1319`, `>=1320`; no summary/summary; expanded,
  conflict rail, details rail, both). The Summary now spans row 2 at
  1101–1319 instead of forcing all four cards into one column.
- Added the wrapper state contract: `has-summary`, `conflict-collapsed`,
  `details-collapsed`; ruled out `hidden` on `#selSummary` because the mobile
  adapter snapshots/restores that attribute.
- Recorded the live-mobile consequence of moving Details: one
  `.ra-class-details` entry in `RELOCATION_PLAN` after `#subjectInfo`, plus the
  mobile heading/chrome hide. This is a compatibility hook, not a mobile
  redesign.
- Added `tests/arrangement-mobile.spec.ts` to the deliberate change list for
  the now-conditional Summary at 769px.
- Split the new card root `#classDetailsCard` from the inner `#details` node
  and named the new `#detailsEmpty` node and the `renderTitleSummary()` empty
  branch.

### 🟡 Addressed

- Replaced the phantom `renderSelectionSummary()` with the live
  `updateSelectionSummary()` / `updateCounter()` names.
- Added the Round1 supersedes list (R1, R4, R9, R10 and the relocation-plan
  ruling).
- Stated that the live `.sel-summary` markup ships, not the prototype's
  compact markup, and added the 280px interior rule.
- Made the venue panel testable: four fixed 150px columns, 10px side padding,
  about 603px total, capped at `calc(100vw - 24px)`; 769–900 keeps the clamped
  panel, `<=768` is mobile.
- Recorded desktop closed = right, citing the prototype's
  `.conflict-collapsed` rule.
- Expanded the ID inventory (`#infoBuilding`, `#submitRequestBtn`, the
  `.sel-summary*` classes) and named the top-zone specs.

### 🔴 Fixed

- Criterion 1 now probes **1360px** for the `>=1320` four-track layout and
  1200px for the row-2 Summary layout; the 1280px contradiction is gone.

### 🟡 Addressed

- Added the explicit "deviation from the prototype's all-stack band" note to
  the explore brief.
- Stated that `#selSummary` itself becomes `.ra-block.ra-selection-summary`,
  drops its old margin/border/radius/shadow, and uses the 12px grid gap.
- Added `#detailsEmpty` mobile suppression via
  `body.arrangement-mobile-mode .ra-class-details:not(.has-slot)`.
- Aligned criterion 8 with the full summary ID set (`#summaryEmpty`,
  `#infoDuration`, `#infoBuilding`, `#submitRequestBtn`).
- Changed the `<=1100px` Details rule from "may wrap" to "wraps as one row of
  whole items."

### 🔴 Outstanding

- None. **PASS** — proposal frozen 2026-11-12.

### Proposal Round 3 — 2026-11-12

Final re-review after the criterion-1 probe fix.

### 🔴 Fixed

- Criterion 1 probes 1360px for `>=1320` and 1200px for row 2.
- Explore brief records the deliberate 1101–1319 deviation from the
  prototype's all-stack band.
- `#selSummary` chrome is `.ra-block.ra-selection-summary` with the old
  margin/border/radius/shadow dropped and the 12px grid gap.
- Mobile empty Class Details is hidden; `#detailsEmpty` never appears in the
  Original class panel.
- Criterion 8 lists the full summary ID set.
- The `<=1100px` Details rule is deterministic.

### 🟡 Addressed Before Freeze

- Empty branch now removes `.has-slot`.
- Explore brief's relocation wording is now "only the one
  `.ra-class-details` entry."
- Criterion 6 asserts visible columns at 150px and all four once a room is
  picked.
- Removed the duplicate `arrangement-mobile.spec.ts` sentence.

### 🔴 Outstanding

- None. **PASS** — proposal frozen 2026-11-12.

## Design Round 1 — 2026-11-12

Batch: `design.md` (new). Frozen proposal passed.

### 🔴 Fixed

- Added §1a rail internals: 12px 8px padding, column-reverse head, centred
  toggle, vertical heading, and the desktop-rail hide for the Conflict
  summary. The <=1100 `is-collapsed` summary remains.
- Added the narrow Class Details body law: stacked on desktop, row-wrap at
  `<=1100px`.
- Added mobile chrome neutralisation and forced the facts visible when a
  desktop manual collapse would otherwise hide them.

### 🟡 Addressed

- `#details` keeps its `hidden` attribute; `#detailsSummary` remains hidden
  and `measureDetailsSummary()` is retained deliberately.
- The Summary root padding uses `.ra-top-zone .ra-selection-summary` so it
  beats `.ra-block`.
- The 150px venue columns are scoped to `@media (min-width: 769px)`.
- `session-arrival-pin-guard.spec.ts` is named for repointing to
  `#classDetailsCard` / `#detailCohorts`.
- Added `tests/top-zone-round2.spec.ts` and the pinned-page precondition for
  rail assertions.
- Fixed the prototype path to `../../../../ui-prototype/…`.
- Added `.ra-empty-guidance` styling.
- Fit function re-runs after each panel click and resets when closed.

### 🔴 Outstanding

- Design re-review pending.

## Design Round 2 — 2026-11-12

### 🔴 Fixed

- Added `id="topZone"` to the `.toolbar-restructure.ra-top-zone` wrapper and
  one closure-level `const topZone` used by the Details, Conflict and Summary
  writers. Removed the guarded/duplicate lookups.
- Specified the Earliest full-width mechanism: full-width column
  `.toolbar-filters`, full-width week nav, full-width centred `#earliestBtn`.
- Added `.ra-class-details` to the `T23_NODES` node-identity list.

### 🟡 Addressed

- §1a now records that the two-line Conflict summary is scoped to `<=1100`,
  matching the prototype's desktop-rail hide.
- `#detailsToggle` initial markup is `aria-expanded="false"` while the body is
  hidden.
- The new `tests/top-zone-round2.spec.ts` is now in the proposal's test list
  and criterion 10. This is a declarative soft-freeze addition; no decision
  changed.

### 🔴 Outstanding

- Design re-review pending (round 3).

## Design Round 3 — 2026-11-12

### 🔴 Fixed

- Added the outer `.ra-replacement-controls` column and
  `.ra-replacement-week { width:100%; margin-left:0 }`, so the inner filters
  column actually resolves to the card width.
- Added the `#earliestBtn` width / stacked-row assertion to the new matrix
  spec.

### 🟡 Addressed

- `#detailsToggle` now maintains its `aria-label` in both the renderer and the
  click handler.
- The Venue row fills the stacked card (`.toolbar-center` 100%, `.venue-dd`
  flex 1).
- Stated that the shared `topZone` constant is declared at the top of the
  closure, above the first consumer.

### 🔴 Outstanding

- Final design re-review pending.

### 🟡 Addressed Before Freeze

- `#detailsEmpty` now ships `hidden` in markup to avoid a first-frame flash.
- Added the note that the existing Venue trigger `max-width: 216px` cap stays;
  only Earliest must reach the card width.

### 🔴 Outstanding

- None. **PASS** — design frozen 2026-11-12.

## Spec Round 1 — 2026-11-12

Batch: `specs/top-zone-round2/spec.md` (new). Frozen proposal + design.

### 🔴 Fixed

- No blockers.

### 🟡 Addressed Before Freeze

- R2 now carries the `.ra-block` chrome invariant and a 1px track tolerance.
- R4 keeps `#detailsSummary` hidden with no second summary line; added the
  empty→filled/manual-collapse scenarios.
- Added the `.ra-eyebrow--muted` rule to R4 and the design.
- R6 defines the Earliest content-box width and tolerance.
- R7 now uses the concrete 1360px/all-four-columns clamp scenario and an
  observable other-page invariant.
- Named the Replacement card selector and aligned the Round1 R9 supersedes
  wording.

### 🔴 Outstanding

- None. **PASS** — spec frozen 2026-11-12.

## Tasks Round 1 — 2026-11-12

Batch: `tasks.md` (new). Frozen proposal/design/spec.

### 🔴 Fixed

- T8 now keys `conflict-collapsed` to the no-Course branch of
  `renderCollapsedSummary()` and `details-collapsed` to the `hasSlot` false
  branch of `renderTitleSummary()`. The Course-without-Slot collapsed state
  remains supported.
- Added the arrow-rotation bullets to T4 (generic pair + desktop left/right
  for both toggles + old `.ra-details.open` scoping).
- Added the atomic apply banner T2 → T13.

### 🟡 Addressed

- T5 no longer duplicates T3's grid placement.
- T2 carries the wrapper file path, all three initial toggle attributes and
  the `#detailsEmpty` copy.
- T13 adds first-paint, clear-last-selection and 280px-overflow assertions,
  plus `#detailsSummary.hidden`.
- T6 records the venue-row fill rules while keeping the 216px trigger cap.
- T1 now checks `7692d80` is an ancestor of HEAD.

### 🔴 Outstanding

- None. **PASS** — tasks frozen 2026-11-12. Apply remains gated on explicit
  user approval.

## Apply verification — 2026-11-12

### 🔴 Fixed during apply

- `#detailsToggle` now has a `[hidden]` display rule; `hidden` alone was losing
  to the author `display:inline-flex`.
- `.ra-class-details .ra-details-body[hidden]` now hides the body on desktop;
  the flex display rule had been overriding the attribute.
- The Details toggle's generic up/down chevron now covers `.ra-details-chevron`
  below 1101px.
- Venue-panel refit listens in the capture phase; the shared item handlers
  `stopPropagation()`, so bubbling never reached the page listener.

### 🟡 Visual-contract correction (user receipt, 2026-11-12)

- The first live pass kept the old 45/55 Course/Slot split and the 216px Venue
  cap. The approved prototype stacks Course over Slot and makes the Venue
  trigger fill the row. Both corrections are now in the design §6, spec R6,
  the matrix spec, and the changelog. No capability changed; this is live
  parity with the approved prototype.

### Verification receipts

- `tests/top-zone-round2.spec.ts`: 12/12.
- `tests/arrangement-mobile.spec.ts` + `tests/session-arrival-pin-guard.spec.ts`:
  51 passed, 3 skipped.
- Remaining named specs: 7 passed; the failures observed were pinned-session
  ownership/data drift (`session=40` redirects to `my-replacements`), no
  span-free cell in the current calendar, and a date-dependent landing (7 vs
  8). None touch the top-zone code paths; recorded as environment data drift.

### Applied and re-verified

- Visual correction re-run: `tests/top-zone-round2.spec.ts` **13/13**.
- Mobile/pin re-run after the correction: **19/19** (`T23b`, `T23c`, `S1–S3`).
- The live 1568px receipt now shows Course over Slot and the full-width Venue
  trigger, matching the prototype.

### 🔴 Outstanding

- None for apply/verify. Archive (T16) remains gated on the user's approval.

