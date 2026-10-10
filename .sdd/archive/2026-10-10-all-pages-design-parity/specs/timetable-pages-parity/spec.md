# Spec — timetable-pages-parity (cohort / my-timetable / student)

Scope: the three remaining real-data timetable pages after the parity rewrite. Frozen reference: the templates at `upstream/fjing` `f8b35a2`. Venue page is NOT covered (done, archived). Mock-fallback pages are NOT covered (already frozen).

## R1 — Cohort page renders the frozen glue

- Legend = exactly 3 items (`Normal` / `Pending` / `Conflict / Public Holiday`) rendered via real block-class swatches + the ownership hint line ("thick border (3px) = your classes · thin border (0.5px) = others'"). No colour-swatch legend remains.
- Summary cards = frozen 5: `sumTotal` "Total Classes", `sumHours` "Teaching Hours", `sumMyClasses` "My Teaching Classes", `sumMyHours` "My Teaching Hours", `sumConflict` "Conflicts". No `sumReplacement`/`sumPending` cards. Descriptions use keyword spans.
- **Scenario (payload, Feature):** `CohortTimetable` payload for a cohort week lists one event per class session (duration spans preserved); no payload change from v1 beyond none.
- **Scenario (rendering, Playwright):** logged-in lecturer viewing a cohort they teach sees their own blocks with class `event-mine` (thick border); blocks they don't own carry `event-others`. The BMIT2154-style same-module L/T adjacent sessions never fuse (venue-page invariant does not apply here — cohort builds session-duration events — but the assertion "own block exists" proves the ownership axis works).

## R2 — Tooltips are 2-segment on cohort + student

`data-tip2` = `lecturer · status` exactly (2 segments — no duplicated lecturer, no spurious `—` third segment). The rendered `::after` tooltip = `name · lecturer · status` via `data-name` composition.

**Scenario (Playwright):** on cohort and student pages, an event block's `data-tip2` matches `^[^·]+ · [^·]+$` (exactly one separator).

## R3 — PH-day + conflict ownership (cohort)

Viewer's OWN classes on public-holiday days or with derived conflicts render loud `event-conflict`; others' render `event-public-holiday`. Own pending (post-Slice-B data) → `event-mine-pending`; others' → `event-others-pending`. The ownership test everywhere on the page (statusClassFn AND `replacementNoteFn.checkOwnership`) is the frozen idiom `lecturer === MockData.currentUser.name` — no `isMine` field test survives.

**Scenario:** for the B005-style derived conflict the owner sees `event-conflict`; any non-owner viewing the same cohort sees `event-public-holiday` for that block. (Holiday own-class red follows the same owner-gate.)

## R4 — My-timetable conflict affordance + card markup

The class-detail modal's Replace Now button shows when `day.holiday || event.status === 'conflict'` — a B005-conflicted class (its owner viewing) shows the button. Card descriptions use `info-keyword`/`warn-keyword` spans per the frozen template (ids/labels unchanged).

**Scenario (Playwright):** B005 owner (5652) opens own conflicted class modal → replace button visible.

## R5 — Student page identity + cards

- `MockData.currentUser.name` on the student page equals the **logged-in student's real name** (overrides the mock-data default persona) — pinned by value, not presence.
- Summary cards = frozen 5 (`sumTotal, sumHours, sumReplacement, sumPending, sumConflict`) with keyword-span descriptions; real zeros are valid values.

## R6 — Page glue (all three pages)

`showPrint => true` on week-nav (print stub present); guide-block text matches frozen wording; summary computation filters `status !== 'cancelled'`.

## R7 — Deliberate divergences (documented, not "bugs")

No cancel-class modal (Slice B); cohort week-nav `disabled` stays dynamic; no client-side faculty/cohort state persistence (`cohortTimetableState`) — server-driven selection; cohort week persistence uses the frozen `cohortTimetableWeek` key; my-timetable modal keeps single-cohort payload shape; my-timetable tooltip fallback is `e.cohort || ''` (frozen form — no `venue`/`—` third segment); `sumPending`/`sumReplacement` may show real zeros.

## R8 — Invariants

Payload shapes unchanged (existing Feature tests stay green unchanged); no shared-file edits (theme.css / ui-common.js / partials); no migrations; auth gating unchanged; `/api/v1` stays parked; venue page untouched.
