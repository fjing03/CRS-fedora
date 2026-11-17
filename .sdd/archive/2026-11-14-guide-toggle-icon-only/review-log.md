# Review log — guide-toggle-icon-only

## Proposal Round 1 — 2026-11-12

Batch: `proposal.md` (new). Nothing frozen. Reviewed against the explore brief,
the shared header/guide partials, `theme.css`, all live page includes and the
reference image.

### 🔴 Fixed

- Replaced absolute positioning/right-padding with a normal-flow flex header
  (`.page-header-main` + icon), so the empty `replacement-arrangement` header
  cannot overlap or float over the guide.
- Reviewed the empty-header page explicitly; the icon row is valid and does not
  rely on a title.

### 🟡 Addressed

- Added an open-state icon style (`[aria-expanded="true"]`).
- Switched the negative `hideToggle` to positive `showToggle` (default true).
- Extracted one `ui-guide-icon` partial and one `guideLabel` owner.
- Added 320/390 bounding-box criteria and non-opted/header-only guards.
- Named the two existing specs that need updates.
- Documented the Livewire client-only open state.

### 🔴 Outstanding

- Re-review pending.

## Proposal Round 2 — 2026-11-12

### 🔴 Fixed

- None outstanding.

### 🟡 Addressed Before Freeze

- Criterion 2 says **7 of 8** descriptions (Replacement Arrangement has none).
- "No extra element" is now "no guide icon; wrapper/flex layout unchanged".
- Empty `guideItems` omits the `<ul>`.
- `.page-header-main` gets `min-width:0; flex:1 1 auto`.
- Named `tests/venue-timetable.spec.ts` and
  `tests/Feature/StudentReplacementHistoryParityTest.php`.
- Design templates inherit the flex header but no icon; visual check noted.
- Added the accepted trade-off risk (description hidden until the guide opens).

### 🔴 Outstanding

- None. **PASS** — proposal frozen 2026-11-12.

## Design Round 1 — 2026-11-12

Batch: `design.md` (new). Frozen proposal.

### 🔴 Fixed

- Restored all four `@php` defaults (`guideTitle`, `guideItems`,
  `showToggle`, `guideDescriptionHtml`); the student description-only guides
  cannot hit an undefined `guideItems`.
- Kept the legacy `.guide-icon` wrapper and the full bar's `onclick`; only the
  SVG is shared.

### 🟡 Addressed

- Non-opted path is a Blade render test; the template visual run is optional
  under `APP_MOCK_FALLBACK`.
- Added the role/address map for the 8-page test loop.
- `guide-block--content` restores 8px top padding.
- Recorded the plain `guideDescription` variant as intentionally dropped.
- Dropped the unused `page-header--guide` modifier.
- Added the null guard to the inline handler.
- Named the student-page insertion points and the guide-list hairline note.

### 🔴 Outstanding

- None. **PASS** — design frozen 2026-11-12.

## Spec Round 1 — 2026-11-12

Batch: `specs/guide-icon/spec.md` (new).

### 🔴 Fixed

- None.

### 🟡 Addressed Before Freeze

- R1 scenarios now cover 320/390/768/desktop and the 8px content top padding.
- R4 Blade render test covers both `ui-guide-block` and `ui-page-header`,
  including the legacy `.guide-icon`, `onclick` and 16px SVG.
- Added the existing live-page regression set to Acceptance.
- `type="button"` frozen in R3.

### 🔴 Outstanding

- None. **PASS** — spec frozen 2026-11-12.

## Tasks Round 1 — 2026-11-12

Batch: `tasks.md` (new). Frozen proposal/design/spec.

### 🔴 Fixed

- T1 now records the apply-time HEAD instead of borrowing the Round2
  `7692d80` baseline.

### 🟡 Addressed

- T11 names the live-page regression set and the blessed `:8030` invocation.
- T11 adds the `APP_MOCK_FALLBACK=true` template visual check.
- T9 adds the one-SVG-source grep check.
- T6 marks dropping `guideTitle` as optional cleanup.
- The atomic batch is T2 → T7, covering all 8 pages.

### 🔴 Outstanding

- None. **PASS** — tasks frozen 2026-11-12. Apply remains gated on explicit
  user approval.



## Apply — 2026-11-12

**Approval:** explicit user instruction to sync/update this SDD and apply it.
**Apply-time HEAD:** `d2d91ae` (Round 2 top zone already committed).
**Baseline sync:** no design decision changed. The frozen artifacts were
refreshed with two implementation pins:

1. `.page-header` flex is scoped with `:has(> .page-header-main)` so the frozen
   raw `request-approval` design template keeps its old block flow.
2. The mobile icon explicitly sets `min-width/min-height: 44px` to opt out of
   theme.css's global 48px mobile button floor.

T6/T7 landed as one atomic batch (partials + CSS + 8 pages + spec update).
`StudentReplacementHistoryParityTest` needed no edit: the header include stayed
one line and its pinned substring still matches.

### Verification receipts

- `tests/guide-toggle-icon.spec.ts` — **8/8 passed**: 5 lecturer pages, PL page,
  student description-only pages, and the 320/390/768/1360 geometry case.
- `tests/venue-timetable.spec.ts` — **64 passed / 2 failed** (TC38b
  `#sumMyClasses` reads `0+0`; TC40 finds no booked cell). Both are the known
  testing-DB/date drift, not this change: no guide/header assertion failed.
- `tests/top-zone-round2.spec.ts` — **13/13 passed** (Round 2 unaffected).
- `tests/venue-dropdown-responsive.spec.ts` — **3/3 passed** for the three
  responsive UI fixes landed first in this session.
- `tests/arrangement-mobile.spec.ts` targeted — **12/12 passed** (T22a venue
  change now drives the mobile sheet; all T23b geometry/desktop-parity cases).
- `tests/pages-parity.spec.ts` and `tests/timetable-wiring.spec.ts` — passed.
- `request-approval.spec.ts` / `nav-identity.spec.ts` — failures are the known
  testing-DB drift (no pending requests → no rows and only 2 approval links
  instead of 3). The guide icon itself passes on `/request-approval-ui`.
- `venue-db.spec.ts` — 2 failures are data drift (`BMIS2003` vs the stale
  `BMIT2154` expectation; a login-limiter timeout). No header/guide assertion.
- `tests/Feature/GuideTogglePartialTest.php` +
  `StudentReplacementHistoryParityTest.php` — **13 passed**, 94 assertions.
- `php -l` clean on the three partials and 8 pages; `php artisan view:cache`
  succeeds; `git diff --check` clean.

**Status:** applied and verified; T12 archive remains open pending user request.

## Failure re-check before archiving — 2026-11-14

The two `venue-timetable.spec.ts` failures recorded above were re-examined at the
user's request before this lane was archived. Both were confirmed unrelated to
this change, but **one was mis-described in this log** and is corrected here.

- **TC38b — correction of cause.** The earlier note called this testing-DB *date*
  drift. It is **data ownership**, not dates. Querying `class_replacement_fresh`:
  B110 holds `AMCS2093` Mon 11:00–13:00, lecturer `user 11` (Lim Jia Zheng), and
  `AMIS1012` Tue 09:00–11:00, lecturer `user 7` (Ellis Chieng). The session is
  logged in as `staff 5425` = `user 1` (Surayaini Binti Basri), who teaches neither.
  `#sumMyClasses` reading `0+0` is therefore **correct behaviour**; the test's
  fixture assumption is stale. Reproduced live: 2 event blocks render in B110 while
  `sumMyClasses`/`sumMyHours` read `0+0` and `sumTotal`/`sumOccupied` read normally,
  so the card wiring itself is intact.
- **TC40 — the earlier description was wrong.** This log said "finds no booked cell".
  That is false: a booked cell exists and the modal opens. The actual cause is that
  TC40 asserts a **`Subject Code`** row, and the live modal has no such row — its
  rows are `Session ID`, `Lecturer`, `Cohort`, `Total Students`, `Venue`, `Week`,
  `Day & Date`, `Time & Duration`, `Status`, `Status Description`, `Remarks`. The
  string `Subject Code` exists only under `resources/views/ui-design-templates/`
  (the retired mock UI), never in a live partial. TC40 therefore fails whenever any
  event block is present, regardless of this lane.

Neither failure involves the guide icon, the guide modal, or the page header — the
only header/guide assertion in that spec (`.guide-toggle-icon` opens `#guideModal`
with the venue purpose copy) passes.

**Follow-up, not fixed here:** TC40 is a permanently failing assertion against the
live UI and needs its own correction. It is left untouched so this lane's archive
stays scope-clean.
