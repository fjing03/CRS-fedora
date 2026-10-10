# Proposal — all-pages-design-parity

## Why

User directive: **every page must use the updated (frozen) UI design from the other PC** — not just venue. The frozen design landed via the `upstream/fjing` merges in the **shared** files (legend partial defaults, grid builder, `theme.css`) and via two venue-specific SDD changes — but each DB-backed page carries its own page-level glue (legend include args, summary cards, tooltip config) written **before** the two-axis language existed. Result: inconsistent UI across the page family.

Verified audit (working tree `5a069fe` vs `upstream/fjing` `f8b35a2`):

### Page inventory & status

| Page (route) | Serves | Frozen design status |
|---|---|---|
| `/venue-timetable-ui` | `VenueTimetable` (real) | ✅ **done** — `venue-event-blocks-db` + `venue-block-span-coalescing` |
| `/cohort-timetable-ui` | `CohortTimetable` (real) | ❌ **behind** — see C1–C4 |
| `/my-timetable-ui` | `MyTimetable` (real) | ⚠️ **partial** — see M1–M3 |
| `/student-my-timetable-ui` | `StudentMyTimetable` (real) | ⚠️ **partial** — see S1–S2 |
| `/replacement-arrangement`, `/replacement-history-ui`, `/my-request-history-ui`, `/request-approval-ui`, `/replacement-home-ui` | **mock fallback** (no component classes exist — routes serve the merged templates) | ✅ **already frozen** — they literally render the merged templates; they get real backends in Wave 3b and will be born from these frozen templates |
| login / layouts / nav / shared partials | merged | ✅ auto-frozen via merge |

### Cohort (`C1–C5`) — all verified against frozen template + working blade
- **C1 — dead legend**: pre-two-axis 5-item **colour-swatch** legend ("Your Classes"/"Others' Classes"/"Your|Others' Pending"/"Conflict") — flat colours that no longer match the actual block rendering. Frozen: **3 status chips with real block classes** (`event-normal`/`event-pending`/`event-conflict`) + `ownershipHint => true`.
- **C2 — old cards**: working = `sumTotal, sumHours, sumReplacement, sumPending, sumConflict`. Frozen = **5 cards: `sumTotal, sumHours, sumMyClasses, sumMyHours, sumConflict`** (Replacements + Pending are replaced by the two My-Teaching cards; **Conflicts stays**, `warn-keyword` description) + `info-keyword`/`warn-keyword` spans.
- **C3 — tooltip duplication bug (post-merge regression)**: page's `tooltipExtra` returns `e.lecturer` while the merged builder appends `e.lecturer` again → tooltip renders **"Dr. X · Dr. X · Scheduled"**. **Frozen template passes NO `tooltipExtra` at all** → frozen tooltip = 2 segments `lecturer · status`. Parity fix = **remove `tooltipExtra`**, not supply a different context.
- **C4 — PH exception**: all holiday events → `event-public-holiday`, including the viewer's own. Frozen: the viewer's OWN holiday classes render loud red `event-conflict` (venue + my-timetable precedent).
- **C5 — ownership axis is broken (audit-added)**: the blade tests `e.isMine === true`, but **no payload ever sets `isMine`** (neither `CohortTimetable.php` nor the shared trait's `baseEvent()`) → every block renders as "others'" and `replacementNoteFn`'s ownership check suppresses all notes. Frozen idiom: `e.lecturer === MockData.currentUser.name` — requires the page to set `MockData.currentUser` (verify during design; my-timetable L114 shows the payload pattern). C1's ownership hint is meaningless until C5 lands.

### My timetable (`M1–M3`)
- **M1 — legend**: uses the partial's defaults → **already frozen** post-merge (4 block-class chips). No work.
- **M2 — cards**: working **already has all 5 frozen cards** (`sumTotal, sumHours, sumReplacement, sumPending, sumConflict`) — the only delta is **description markup**: working uses `<strong>` where frozen uses `info-keyword`/`warn-keyword` spans (+ minor wording).
- **M3 — conflict/PH modal condition**: working `isConflict = day.holiday` **only**; frozen = `day.holiday || event.status === 'conflict'` → a B005-conflicted class currently gets **no Replace Now button** on the live page. Align to frozen. PH/statusClassFn rendering verified against frozen template during design (personal page: everything is "mine" — loud red on PH).

### Student my timetable (`S1–S3`)
- **S1 — legend**: defaults ✓ frozen. **Cards**: align to frozen 5 (`sumTotal, sumHours, sumReplacement, sumPending, sumConflict`) + keyword spans.
- **S2 — tooltip duplication**: `tooltipExtra` returns `e.lecturer` → same double-lecturer bug. Frozen student template passes no `tooltipExtra` → **remove it** (frozen tooltip = `lecturer · status`).
- **S3 — real-data modal guard**: the student page never sets `MockData.currentUser`, but the real payload emits `requestId`/`requestedBy` on pending events — the shared `openClassModal` evaluates `event.requestedBy === MockData.currentUser.name` → **TypeError risk** when a student opens a pending class modal (real-data-only; the mock never hits it). Set `MockData.currentUser` (student shape) or guard, per frozen template idiom.

## Scope

- **S1**: Cohort page — C1–C5 (legend, cards, tooltip removal, PH exception, ownership fix + `MockData.currentUser` if absent).
- **S2**: My-timetable — M2 description spans + M3 modal `isConflict` alignment (+ any design-confirmed PH/statusClassFn drift).
- **S3**: Student page — S1–S3 (cards, tooltip removal, `MockData.currentUser`/modal guard).
- **S4 — page-glue sweep (all three pages)**: `showPrint => true` on `ui-week-nav` (frozen templates pass it; partial defaults false — print stub missing on all real pages); guide-block text aligned to frozen wording; `computeSummary` aligned to frozen (filter `status !== 'cancelled'` — future-proofing). **Deliberate divergences recorded in design, not silent**: cancel-class modal omitted (backend write action — Slice B); cohort week-persistence storage key; cohort week-nav `disabled` staying dynamic (`$pinnedCohortId === null`) as a sensible real-data adaptation.
- **S5**: Per-page Playwright pin updates where assertions exist + Feature test updates for changed card ids (**never assert "always 0" for `sumPending`/`sumReplacement`** — the payload can emit `pending`/`replacement` statuses; derive expectations from data). Changelogs per page (AGENTS rule).
- **S6**: Gates (lint, phpstan, phpunit, Playwright gates + any new pins), records-intact, verify, archive. **No push without authorization.**

## Standing constraints (unchanged)

- Real-data honesty: `sumPending`/`sumReplacement` cards show **real state** (0 today, live once Slice B data exists — never fake rows). `sumConflict` is live now (B005).
- No backend logic changes beyond ownership/status payload alignment if C5 requires it (setting the same fields the frozen idiom consumes); no migrations; `/api/v1` stays parked; booking stays out (Slice B).
- Shared files (theme.css, ui-common.js, partials) are **already frozen** — page-level glue only. Any apparent need to touch shared files = stop and reassess.

## Out of scope

- Wave 3b pages (arrangement/approval/history/home backends) — mock-fallback pages already frozen.
- Twin-merge/coalescing changes (done/unchanged).
- New dependencies, layout redesigns beyond the frozen templates.

## Success criteria

- Every existing page's legend, cards, and tooltips match its frozen counterpart template; the cohort/student double-lecturer tooltip is gone; the cohort ownership axis works (own classes actually render thick-bordered); PH own-class exception consistent everywhere the frozen design has it; B005-conflicted class shows Replace Now on my-timetable; student pending modal opens without TypeError.
- All gates green; records intact; archived; nothing pushed without authorization.
