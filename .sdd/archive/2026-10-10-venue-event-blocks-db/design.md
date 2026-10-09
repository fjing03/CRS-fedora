# Design — venue-event-blocks-db

Frozen baseline: proposal.md (R2 PASS) + explore-brief.md. All class names/behaviours reference the merged (f8b35a2) `theme.css` / `ui-common.js` / venue template.

## 1. Rendering strategy: `cellRender` override (upstream-mock pattern)

The merged shared builder (`buildTimetableGrid` in `ui-common.js`) has two modes:

- **Default mode** (no `cfg.cellRender`): paints event blocks itself, passes `isConflict = day.holiday` into `statusClassFn(div, e, isConflict)`, paints empty cells (incl. offday) as plain `.cell-empty` — **no 'PH'/'Sun' markers**, and our blade's green `.cell-empty` tint would wrongly green offday empties.
- **`cellRender` mode**: the page paints every cell; `cfg.cellRender(td, di, hi, day, info)` where `info` = `{event, span}` | `{event:null, occupied:true}` | `null`.

**Decision: the DB venue page adopts `cellRender` mode**, adapted near-verbatim from the upstream venue template's cellRender (it is the frozen reference implementation). Reasons: (a) PH/'Sun' empty cells with text markers are part of the frozen design and unreachable in default mode; (b) the PH-day own-class exception (own → loud red) is expressed in the same code path; (c) the green `.cell-empty` Available tint then applies only to genuinely available cells by construction — the offday-cell bug disappears structurally instead of via a `:not(.offday-slot)` patch.

Consequence: the page's script carries a page-level cellRender (~35 lines, same as the mock template — page-specific override, **not** a promote-on-3rd candidate: only 2 pages use this pattern; no promotion this change).

### 1.1 Cell painting rules (the complete mapping)

| Cell state | Painted as |
|---|---|
| Sunday, empty | `div.cell-sun` (upstream classes/text) |
| Public holiday, empty | `div.cell-ph` with 'PH' text |
| Event on holiday, viewer's OWN | `.event-block` + `event-conflict` (loud red — "your class won't run"; this is the reachable marquee state). **Holiday only** — the upstream guard is `day.holiday`, never Sunday: own events on Sundays hide like everyone else's ("Sundays always empty") |
| Event on holiday/Sunday, others' | empty `PH`/`Sun` cell (others' classes hidden on offdays) — note: `.cell-ph::after`/`.cell-sun::after` markers are **hover-reveal** upstream (opacity 0 until hover); the always-visible offday signal is the day-header "Public Holiday"/"OFF" badge. Spec asserts class presence + no green tint, NOT visible text |
| Event, status normal, mine | `.event-block` + `event-mine` (success fill + 3px border) |
| Event, status normal, others' | `.event-block` + `event-others` (success fill + 0.5px hairline) |
| Event, status pending, mine | `.event-block` + `event-mine-pending` (unreachable from data pre-Slice-B; path built) |
| Event, status pending, others' | `.event-block` + `event-others-pending` (unreachable pre-Slice-B) |
| Event, status conflict, mine | `.event-block` + `event-conflict` (unreachable pre-Slice-B — zero conflict rows) |
| Event, status conflict, others' | `.event-block` + `event-public-holiday` (unreachable pre-Slice-B) |
| Free slot (bookable window) | `div.cell-empty` → existing green Available tint |
| Continuation slot (`occupied`) | `td.style.display='none'` (span handling, as builder default) |

Ownership test = the component's existing `mine` flag on events (viewer's user_id vs lecturer user_id) — unchanged from v1.

### 1.2 Tooltips (upstream-verbatim)

`div.dataset.tip2 = (e.lecturer || '—') + ' · ' + eventStatusLabel(e, day.holiday)` — copied verbatim from the upstream template. The block keeps `dataset.name` (= class name), and theme.css `.event-block::after` renders **`name · lecturer · status`**. The re-pinned Playwright spec asserts the rendered `::after` composition via `data-name` + `data-tip2` attributes and the visible tooltip text.

## 2. Legend + guide + booking honesty

- Legend → `@include('partials.ui-legend-bar', ['ownershipHint' => true, 'items' => [...]])` with exactly 4 items, copied from the upstream venue template: **Available** (colour swatch `--color-success-container`, honest tip noting booking arrives with the replacement workflow and Sunday/holiday/lead-time slots can't be booked), **Normal** (`event-normal`, "replacement sessions fold in here on this page"), **Pending** (`event-pending`), **Conflict / Public Holiday** (`event-conflict`). The 7-item legend block is deleted.
- Ownership hint line renders via the merged partial (`ownershipHint => true`): "thick border (3px) = your classes · thin border (0.5px) = others'".
- Guide block: the "Slot colours" bullet is rewritten to the two-axis language (colour = status, border = ownership; PH-day own-class red); other guide bullets unchanged.
- Booking stays out: the `booking-hint-line` stays ("Slot booking arrives with the replacement workflow (Slice B)"); available cells get NO click-to-book affordance.

## 3. Summary bar semantics (fixes the Available bug; keeps our card set)

Component-side (`VenueTimetable.php`, `$totalsByWeek`) — per-week totals table:

| Card (id) | New semantics |
|---|---|
| Total Slots (`sumTotal`) | unchanged: 120 grid slots for the week (6 days × 20) |
| Available (`sumAvailable`) | **fixed**: free slots EXCLUDING Sunday and public-holiday slots (previously holiday rows counted as Available — contradicted our own legend tip) |
| My Teaching Classes (`sumMyClasses`) | unchanged: sessions the viewer teaches, each class counts separately — counts **visible blocks** (implemented as distinct session rows, deduped by session id), so a viewer's own PH-day class counts (it renders as a red block, not a PH cell). Note: upstream's `updateSummaries` skips offday events from My Teaching — our grid renders own-PH classes as blocks, so counting them is the grid-faithful choice; record as a deviation in the changelog entry |
| My Teaching Hours (`sumMyHours`) | unchanged, same visibility rule as sumMyClasses (slot = 30 min) |
| Occupied (`sumOccupied`) | unchanged: slots occupied by scheduled classes |

Deviation from upstream (frozen): upstream's 5th card is `sumUnavailable`/"Unavailable" (= booked + Sunday + PH). We keep `sumOccupied`/"Occupied" — deliberate, documented in the proposal (read-only page counts what's booked; card set frozen at last merge). Card descriptions adopt upstream's `info-keyword` / `warn-keyword` span styling verbatim where the wording matches.

## 4. What arrives free via the merge (no page code)

- `ui-common.js`: `eventStatusLabel(e, isConflict)`; VenueDropdown `_typeOf` CiscoLab→'Lab' (B006 groups under Lab; typeOrder Tutorial/LectureHall/Lab) and room-row `data-tip` full names — verified in spec only, no code.
- `theme.css`: all `event-*` block classes, two-axis borders, `.legend-ownership-hint` + `osd-*` demo swatches, `.cell-ph`/`.cell-sun`.
- `ui-legend-bar.blade.php`: real-block-class swatches + `ownershipHint` support.

## 5. Component (VenueTimetable.php) changes — minimal

- `$totalsByWeek` computation: the Available exclusion fix (§3). Everything else stays: payload shape (`eventsByWeek`, `totalsByWeek`, `venuesJs`, `venue`, `semesterJs`, `holidaysJs`), twin-merge (`mergeTwinEvents`/`reduceTwins` — combined-lecture rows still fold; no twins exist in v1 data, defensive), auth, `?venue=` navigation.
- No new queries; no migration; no model change.

## 6. Tests

### 6.1 `tests/venue-db.spec.ts` re-pin (Playwright, ≤5 logins/min — 4 tests, one login each, unchanged)
- Legend: 4 items with exact labels/order; ownership hint text present.
- Grid: event blocks present with `event-mine` / `event-others` classes (login user's venue page); `.vt-cell-*` assertions deleted.
- Tooltip: block exposes `data-name` + `data-tip2` (`lecturer · status` shape); rendered `::after` tooltip = `name · lecturer · status`.
- PH cells: for the seeded test week, offday empty cells show `cell-ph`/`cell-sun` and no green tint (if the displayed window includes a holiday — conditional assertion, skipped cleanly otherwise; the Feature test in 6.2 guarantees the state regardless).
- Summary cards: same 5 ids; **Available value changes** — assert the new value on a known venue/week (recomputed from seed data) including that holiday slots are excluded.
- Venue dropdown: B006 reachable under Lab (cascade path), room option carries `data-tip` full name — mirrors upstream TC23b idiom, adapted to auth. **The existing 4-type-group assertion incl. standalone `data-type="CiscoLab"` (spec L79–81) is DELETED/replaced by the 3-category cascade** — `_typeOf` folds CiscoLab under Lab post-merge; an additive re-pin leaves a guaranteed-red gate otherwise.

### 6.2 `tests/Feature/VenueTimetableTest.php`
- Existing twin-merge crafted-row reflection test: stays as-is.
- **New crafted-row PH test** (guarantees the marquee criterion regardless of live data, per proposal S3): seed/craft a session for the test lecturer starting on an imported holiday date → assert `eventsByWeek` still contains the event, `holidaysJs` exposes the date, and totals: the viewer's own PH class counts in `sumMyClasses`/`sumMyHours`, the PH slot is excluded from `sumAvailable`, counted in `sumTotal`. (Client-side loud-red rendering is pinned by 6.1's PH assertion when data allows; the payload contract is what the Feature layer owns.)
- Payload-level assertions updated to the new totals semantics; the Playwright layer owns class assertions (the Feature layer is payload-only — no cell/block-class assertions exist or belong here).

### 6.3 Gates (one verify pass, per proposal S4)
`lint:check`, `types:check`, `php artisan test`, Playwright `venue-db` + `timetable-wiring` + `nav-identity`; `venue-timetable.spec.ts` / `confirm-guards.spec.ts` / `warning-keywords.spec.ts` excluded (auth-free mocks); records-intact re-check after merge.

## 7. Merge mechanics (S1)

1. Re-verify clean tree → `git merge upstream/fjing` (no-commit first, inspect).
2. Resolve the single conflict `page-changelogs/replacement-arrangement-changelog.md`: keep both sides' entries in chronological order (ours 2026-10-09 top entries + upstream's appended batch entries).
3. Spot-check the 5 auto-merged changelogs for doubled headings.
4. Records-intact check against `/tmp/opencode/records-pre-merge2.txt` (before AND after).
5. Merge commit (conventional: `merge:` style used previously — "Merge remote-tracking branch 'upstream/fjing' into fedora-backend").

## 8. Changelog

`page-changelogs/venue-timetable-ui-changelog.md`: new entry (2026-10-10) — event-blocks rewrite, two-axis language, 4-item legend + ownership hint, PH own-class exception, Available-semantics fix, Occupied deviation note, **My-Teaching-counts-own-PH-blocks deviation (vs upstream's offday-skip) note**, booking still Slice B.

## 9. Promoted to shared

**None.** All shared pieces (classes, builder features, partial capabilities) arrive via the upstream merge; the cellRender override is page-specific (2nd usage — 3rd would trigger the promotion rule).
