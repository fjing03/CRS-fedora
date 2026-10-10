# Design — all-pages-design-parity

Frozen baseline: proposal.md (R2 PASS). Reference implementations: the frozen templates at `upstream/fjing` `f8b35a2` — parity means **copying the frozen template's page-glue verbatim**, adapted only where real data demands (divergences listed in §4). NO shared-file edits.

## 1. Cohort page (`cohort-timetable.blade.php`)

| # | Change | From → To |
|---|---|---|
| C1 | Legend | 5-item colour-swatch include → frozen 3-item include **verbatim** (`ownershipHint => true`; items `event-normal` "Normal" / `event-pending` "Pending" / `event-conflict` "Conflict / Public Holiday" with the frozen tips) |
| C2 | Summary cards | `sumTotal, sumHours, sumReplacement, sumPending, sumConflict` → frozen 5 **verbatim**: `sumTotal` "Total Classes" / `sumHours` "Teaching Hours" / `sumMyClasses` "My Teaching Classes" / `sumMyHours` "My Teaching Hours" / `sumConflict` "Conflicts" (`warn-keyword` description) + keyword spans. Values come from the page's summary call following the **frozen template's own script pattern** (incl. its null-guard zero-fill and `filter(e => e.status !== 'cancelled')`); if the shared `computeSummary` already fills the frozen ids, no page-side counter is added — verify against `ui-common.js` during apply, and if a counter IS needed, mirror the frozen template's compute code (page-level, not shared) |
| C3 | Tooltip | **Delete `tooltipExtra`** from the `buildTimetableGrid` call → builder renders 2-segment `lecturer · status` (frozen template passes none) |
| C4 | PH exception | `statusClassFn`: `isConflict`/conflict/pending branches owner-gate via the frozen idiom — own → `event-conflict` (loud, incl. holidays: "your class won't run"), others → `event-public-holiday` / `event-others-pending`; mirror the frozen cohort template's `statusClassFn` verbatim |
| C5 | Ownership | Replace `e.isMine === true` tests (statusClassFn + `replacementNoteFn.checkOwnership`) with `e.lecturer === MockData.currentUser.name` (frozen idiom). **No payload change** — the blade already sets `MockData.currentUser` (L101). Do NOT add an isMine payload field |
| G1 | Page glue | `showPrint => true` on `ui-week-nav`; guide-block items aligned to frozen wording; summary call gains the cancelled filter |

## 2. My timetable (`my-timetable.blade.php`)

| # | Change | From → To |
|---|---|---|
| M2 | Cards | Ids/labels already frozen — **descriptions only**: `<strong>` → `info-keyword`/`warn-keyword` spans + frozen wording, copied verbatim from the frozen MyTimetable template |
| M3 | Modal conflict condition | `isConflict = days[event.di] && days[event.di].holiday` → frozen `day.holiday \|\| event.status === 'conflict'` (B005-conflicted class regains Replace Now) |
| M3b | PH/status rendering | **Closed — no drift**: working blade and frozen template both pass no `statusClassFn`; both use the frozen shared default (personal pages: loud `event-conflict` on PH/conflict). No work. |
| G1 | Page glue | `showPrint => true`; guide text aligned (the "Approved (blue), Rejected (grey)" line the frozen template dropped, goes); cancelled filter on summary call |

## 3. Student my timetable (`student-my-timetable.blade.php`)

| # | Change | From → To |
|---|---|---|
| S1 | Cards | Align to frozen 5 (`sumTotal, sumHours, sumReplacement, sumPending, sumConflict`) + keyword spans, verbatim; summary values per frozen template's script pattern |
| S2 | Tooltip | **Delete `tooltipExtra`** (same regression as cohort) → `lecturer · status` |
| S3 | Modal guard | Set `MockData.currentUser` from the authenticated student (`@json(['name' => auth()->user()?->name ?? ''])` — mirror my-timetable's payload pattern) so `openClassModal`'s `event.requestedBy === MockData.currentUser.name` comparison is defined; a student opening their own pending request then sees the frozen own-request behaviour, others' requests render the frozen others' behaviour |
| G1 | Page glue | `showPrint => true`; guide text aligned; cancelled filter |

## 4. Deliberate divergences (real-data adaptations — recorded, kept)

1. **No cancel-class modal** on cohort/my-timetable despite the frozen templates including `ui-cancel-class-modal` + `CancelClass.renderButton` — backend write action, Slice B. Read-only honesty.
2. **Cohort week-nav `disabled` stays dynamic** (`$pinnedCohortId === null`) — real cohorts are selectable; the frozen mock hardcodes `true`.
3. **Week persistence key**: adopt the frozen storage key (`cohortTimetableWeek`) on cohort for parity if the current derived key differs — cosmetic, zero risk.
4. **`sumPending`/`sumReplacement` show real zeros** today — the frozen cards displaying 0 is correct real-data behaviour, not a bug to fake away.
5. **No faculty/cohort selection persistence (`cohortTimetableState`)** on the real cohort page: the real page's cohort selection is server-driven (`?cohort=` URL param + `$pinnedCohortId`), so client-side state restore would fight the server-driven path. Week persistence (item 3) covers the useful part. (Legacy non-gate `ui-regression.spec.ts` expects the state key — mock-era expectation, out of gates.)
6. **My-timetable tooltip fallback** aligns to frozen: `e.cohort || ''` (working blade's extra `|| e.venue || '—'` would render a spurious third segment on cohort-less events).
7. **My-timetable modal multi-cohort branch**: payload emits single `cohort`/`studentCount`; the frozen template's multi-cohort join/sum is a mock-shape nicety the real payload cannot reproduce — recorded, kept as-is.

## 5. Promoted to shared

**None.** All shared surfaces (partials, builder, theme.css) are already frozen; every change here is page-level glue. If an apply step seems to require editing a shared file → stop and reassess (proposal constraint).

## 6. Tests

- **Feature**: payload shapes are unchanged by this change (blade/JS glue) — existing Feature tests must stay green unchanged; add none unless apply reveals a payload gap (then propose, don't silently extend).
- **Playwright**: extend the existing gate specs minimally, respecting ≤5 logins/min/ID:
  - cohort: 3-item legend + ownership hint present; a block owned by the logged-in viewer renders `event-mine` (ownership axis works — C5); tooltip `data-tip2` has NO doubled lecturer (`lecturer · status` shape, 2 segments).
  - my-timetable: B005 owner (5652, one login) sees Replace Now on the conflicted class (M3).
  - student: pin the **value** of `MockData.currentUser.name` after login as the seeded student (the `25DFT0001` login used by timetable-wiring) — NOT merely "is set": `mock-data.js` ships a global default persona (`En. Lim Jia Zheng`) that would pass vacuously; the real-data fix is that the page overrides it with the actual student.
  - card ids: spot-assert the new cohort card ids render values (values DB-derived or ≥ 0).
- **Gates**: lint, phpstan, phpunit full, `venue-db` + `timetable-wiring` + `nav-identity`, records-intact. **"Playwright green" is scoped to the gate set** — legacy non-gate specs (`ui-regression.spec.ts`, `cancel-class.spec.ts`) stay parked. Changelogs: cohort/my/student page-changelogs updated (AGENTS rule).

## 7. Apply order

Cohort (largest) → my-timetable → student → glue sweep → tests → gates. Each page: edit blade → stale-cache restart → quick manual 200 check → next.
