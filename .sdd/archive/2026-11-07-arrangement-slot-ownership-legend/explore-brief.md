# Explore brief — Pending slots, conflict slots & legend on `replacement-arrangement`

Source brief: `prompts/UI Update & Bug Fix — Pending Slots, Conflicts & Legend Styling.md`
(user-supplied, 2026-11-06). Grill answers: **1A 2A 3A 4A 5A 6A 7A 8A** (recorded below,
verbatim intent preserved).

Page: `replacement-arrangement` (`app/Livewire/ReplacementArrangement.php` +
`resources/views/livewire/replacement-arrangement.blade.php`).
Reference pages for the legend: `cohort-timetable`, `venue-timetable` (`.legend-bar`).

---

## 1. The user's law (what must be true after this change)

### 1.1 Pending family — one colour, ownership by border

| Cell type | Fill | Border |
|---|---|---|
| Pending (You) | tertiary container (the amber "awaiting" pair) | **3px** tertiary |
| Reserved by Others (another staff's pending) | same tertiary container | **0px** |

### 1.2 Conflict family — one colour, ownership by border

| Cell type | Fill | Border |
|---|---|---|
| Own conflicts | error container | **3px** error |
| Cohort conflicts | error container | **0px** |
| Venue conflicts (another lecturer's class in the room) | error container | **0px** |

### 1.3 Legend

The `replacement-arrangement` legend becomes visually consistent with the cohort/venue
`.legend-bar`: same bar, same indicator pattern (a swatch that carries the **real** cell
class, so fill *and* border show), same `data-tip` per item, plus the ownership hint line.
It must never claim a style the grid does not render.

### 1.4 Bug fix

Another staff member's pending slot must never render with the "mine" style. Border
thickness is decided by **ownership data**, never by colour alone.

---

## 2. What was measured (evidence, not guesswork)

### 2.1 Two independent arms paint a cell — and only one tests ownership

`cellRender()` in the Blade (≈ lines 1705–1870) decides a cell in this order:

1. Sunday / public holiday → `.cell-ph` / `.cell-sun`
2. **engine arm** — `engineBlocked` = venue ineligible for the session type/headcount
   OR any busy time-vector covers the half-hour → `.cell-pending` when every matching
   vector row has `status === 'pending'`, else `.cell-occupied`
3. **grid arm** — `venueSlotsByWeek[week][venue]` row for that day+half-hour:
   `1` → `.cell-occupied`, `3` → `.cell-pending`, `4` → `.cell-reserved`
4. lead-time rule → `.cell-too-soon`; otherwise `.cell-available`

- The **grid arm tests ownership**: `ReplacementArrangement::venueSlotsByWeekPayload()`
  computes `status = time_slots.status === 'occupied' ? 1 : (proposer_id === auth()->id() ? 3 : 4)`
  (`app/Livewire/ReplacementArrangement.php:605–608`, junction-derived since full-block-reservation). Pinned by
  `tests/Feature/ReplacementArrangementTest.php` (`assertContains([3, 12, 1, null])`,
  "my pending → status 3", "others' pending → status 4").
- The **engine arm has no ownership test at all**. Its busy vectors merge two sources in
  `busyByWeekPayload()`: `lecturerBusyRanges()` (owner-scoped — these *are* mine) and
  `cohortBusyRanges()` (**ownerless by design**: any lecturer's make-up whose request's
  class session carries one of the target cohorts). Both make-up layers emit
  `'status' => 'pending'|'approved'`, which the Blade turns into `cell-pending`.

### 2.2 Reproduced live on the read-only demo DB (`class_replacement_fresh`)

```
MatrixIntersectionEngine::cohortBusyRanges(semester 1, cohorts [4], week 9)
  → 22 ranges; 4 of them carry status "pending":
    {day:0, start:13:30, end:14:00, room:B111, status:pending}
    {day:0, start:14:00, end:14:30, room:B111, status:pending}
    {day:0, start:14:30, end:15:00, room:B111, status:pending}
    {day:0, start:15:00, end:15:30, room:B111, status:pending}
```

Those four half-hours are **request 28 — proposer staff 5770 (user 11)** on session 97
(AMCS2093 lecture, cohorts 2+4), venue B111, week 9, Monday 13:30–15:30.

So: log in as **5425 (user 1)**, select a subject whose session carries **cohort 4**
(e.g. AMSE2003 lecture, session 67) and open **B111 week 9** → the four cells render
`.cell-pending` (the "Pending (You)" amber) even though the request is 5770's. Select a
subject that does **not** share cohort 4 → the engine arm never matches and the same
cells fall to the grid arm and correctly render `.cell-reserved`. That subject-dependence
is the "sometimes" in the bug report.

Note: the busy vector is a **time** vector (no room comparison — a cohort in class is
busy in every venue), so the same mis-styling appears in *every* venue at Mon
13:30–15:30, not only B111.

### 2.3 Legend today vs cohort/venue

| | `replacement-arrangement` | `cohort-timetable` / `venue-timetable` |
|---|---|---|
| Items | 5, all **flat `color`** swatches | 4–5, each swatch carries a **real block class** |
| Ownership hint | absent | `ownershipHint => true` → `thick border (3px) = your classes · thin border (0.5px) = others'` |
| `data-tip` | yes | yes |
| Items' vocabulary | Available / Your Current Selection / Pending (You) / Unavailable / Reserved by Others | Normal / Replacement / Pending / Conflict (+ Available on venue) |

Partial: `resources/views/partials/ui-legend-bar.blade.php` (`items[] = class|color, label, tip`,
`ownershipHint`). CSS: `.legend-bar` / `.legend-items` / `.legend-item` / `.legend-swatch` /
`.legend-ownership-hint .osd-demo|.osd-thick|.osd-thin` (`public/css/theme.css:1686–1700`).

### 2.4 The cells carry no borders today, and two greys collide

- `.cell-available`, `.cell-pending`, `.cell-reserved`, `.cell-occupied` are **background
  only** (`theme.css:2623–2721`). 3px/0px is new; `box-sizing: border-box` is global
  (`theme.css:6`), so a border cannot move the grid geometry.
- `.cell-reserved` today = `--color-surface-variant` grey — **the same fill as
  `.cell-too-soon`** (lead-time cells, `theme.css:2746`). Moving "Reserved" to the amber
  family also removes that collision.
- `.cell-ph` / `.cell-sun` are transparent with a hover label (PH); `.cell-too-soon` is grey.
  These states are not in the legend and stay out of it (see §5 residual risk).
- Precedent for the 3px language already exists: `.event-pending` (3px tertiary),
  `.timetable .event-mine-pending` (3px tertiary), `.event-conflict` (3px error),
  `.timetable .event-others` (0.5px hairline), `.timetable .event-others-pending`.

### 2.5 Ownership signal available to the client today (rejected source)

`holderLabels` build the tip text, and the viewer's own blocks are prefixed `You — `
(the Blade already parses that prefix for the "👤 Own conflict" why-tag). This is a
**display** string, and it is `null` for tip-less rows → rejected as the ownership source
(see §4).

---

## 3. Decisions taken (grill, 2026-11-06)

### Q1 = A — ownership rides the busy tuple

The 6th key of every busy-range tuple becomes `mine: bool`:

| Source | `mine` value |
|---|---|
| `lecturerBusyRanges()` (my own classes + my own make-ups) | `true` |
| `cohortBusyRanges()` → class-side rows | `false` |
| `cohortBusyRanges()` → make-up rows | `replacement_requests.proposer_id === auth()->id()` |

Tuple shape after: `[day, startIdx, endIdx, holderLabel|null, statusKind|null, mine]`.
Additive; the first five keys keep today's meaning. No other page reads `busyByWeek`
(`grep` shows only `replacement-arrangement.blade.php` consumes it).

### Q2 = A — one resolver, not "first arm wins"

For a cell, gather both arms then classify once:

```
pendingMine   = grid.status === 3 OR any(engine match: statusKind === 'pending' && mine)
pendingOthers = grid.status === 4 OR any(engine match: statusKind === 'pending' && !mine)
conflictOwn   = any(engine match: statusKind !== 'pending' && mine)      // my class / my approved make-up
blocked       = engineBlocked OR grid.status === 1                        // any other cause
```

Precedence: `pendingMine` → own-pending class; else `pendingOthers` → others-pending class;
else `conflictOwn` → own-conflict class; else `blocked` → other-conflict class;
else too-soon → available. (A cell that is both mine and someone's pending is mine — the
3px state wins; a red cell is 3px if **any** cause is mine, per Q2A.)

### Q3 = A — the amber `tertiary` family, not `--color-warning`

Pending = `--color-tertiary-container` fill + `--color-tertiary` (3px) border. This is the
"same name, same colour" canon the awaiting family already uses, and it is exactly what
`event-pending` / `event-mine-pending` render on cohort/venue. `--color-warning`
(`#D4880F`, the just-cancelled pulse) is **not** used here.

### Q4 = A — "0px" is literally no border

Others = fill only. The hint line reads `thick border (3px) = yours · no border = others'`.
No 0.5px hairline (that is the cohort/venue `event-others` style and is deliberately not
copied).

### Q5 = A — every red cell is one "Unavailable"

Red covers: my own class; a cohort's class; another lecturer's class in the room;
overlapping blocks; **and** a venue that cannot host the session type/headcount
(`blockedVenues`). One legend row; the border says whose; the existing cell tooltip already
names the cause (👤 Own conflict / 👥 Cohorts conflict / 🏛 Venue conflict).

### Q6 = A — six legend rows, each swatch a real cell class

`Available` · `Your Current Selection` · `Pending (You)` · `Pending (Others)` · `Conflict (Yours)` ·
`Conflict (Others)`, plus the ownership hint line.

### Q7 = A — this page's vocabulary, cohort/venue's visual style

Labels keep this page's meaning (a green cell here is a *free slot*, not a "Normal class").
Style is adopted wholesale: real-class swatches, per-item `data-tip`, ownership hint line.

### Q8 = A — payload feature test **and** one browser receipt

Feature test on the ownership flag (two lecturers, shared cohort, one pending make-up,
assert `mine` true/false per vector) + one Playwright receipt on the **testing DB** that
logs in as both staff and asserts the computed `border-width` and the class on the same
cells. No demo-DB writes.

---

## 4. Rejected approaches (and why)

| Rejected | Why |
|---|---|
| Parse the `You — ` holder-label prefix in JS for ownership (Q1 option B) | Display text as logic: `null` for tip-less rows, and any copy change silently re-breaks the styling. The tuple flag is data. |
| Keep first-arm-wins and only patch the engine arm (Q2 option B) | A cell that is both my class and a cohort's class would be 0px or 3px depending on arm order — exactly the class of bug being fixed. |
| Use `--color-warning` for pending (Q3 option B) | Two different yellows would mean "pending" on two surfaces; breaks the one-name-one-colour canon the awaiting family follows. |
| 0.5px hairline for "others" (Q4 option B) | Contradicts the user's table and its repeated "Expected Visual Rule"; also two thicknesses (0.5 and 0) would be needed for no gain. |
| Split "Conflict" from "not bookable in this venue" into two legend rows (Q5 option B) | The grid cannot tell them apart visually anyway (both are "cannot book"), and it would need a 7th row. The tooltip already explains the cause. |
| 5-row legend relying on the hint line (Q6 option B) | The two amber rows and the two red rows would be indistinguishable at a glance. |
| Copy cohort/venue's labels verbatim (Q7 option B) | "Normal / Replacement" are false meanings on this page (its green = free slot, its blue = my current selection). |
| Recompute ownership in a second server query per cell | The payload already has every fact; a per-cell query would violate the one-read-per-render discipline the page keeps. |
| A server-side reclassification of the grid (new status codes) | The grid arm's 1/3/4 codes are pinned by `ReplacementArrangementTest`; the fix belongs where the wrong colour is chosen (the cell resolver), not in the contract other code reads. |

---

## 5. Cross-module data flow (who calls whom)

```
DB (time_slots, replacement_request_slots, replacement_requests, class_sessions, session_cohorts)
 ├─ MatrixIntersectionEngine::lecturerBusyRanges(semester, lecturerId, week)  → ranges (owner-scoped)
 │      └─ makeUpRangesForLecturer(): joins rr chain WHERE class_sessions.lecturer_id = me
 └─ MatrixIntersectionEngine::cohortBusyRanges(semester, cohortIds, week)    → ranges (ownerless)
        ├─ classSessionRangesForCohorts(): the cohorts' classes
        └─ makeUpRangesForCohorts(): ANY lecturer's rr for a session of those cohorts   ← bug source
                    ↓ both gain 'mine' (Q1A)
ReplacementArrangement::busyByWeekPayload()  → MockData.busyByWeek[sessionId|code][weekIdx] = [tuples]
ReplacementArrangement::venueSlotsByWeekPayload() → MockData.venueSlotsByWeek[weekIdx][venueCode] = [[day, start, 1|3|4, holderIds|null]]
                    ↓ page payload → Blade
cellRender(td, di, hi, day)  → classify (Q2A resolver) → one of 6 classes → CSS
ui-legend-bar partial        → 6 swatches carrying those same classes + hint line
```

## 6. The mapping tables (the completeness checklist)

### 6.1 Cell class set (this page)

| # | Class | Fill | Border | Meaning |
|---|---|---|---|---|
| 1 | `.cell-available` | `--color-success-container` | none | free + bookable |
| 2 | `.event-selection` (block overlay) | `--color-primary-container` | 2px `--color-primary` | my current selection |
| 3 | `.cell-pending` (own) | `--color-tertiary-container` | **3px `--color-tertiary`** | my pending request |
| 4 | `.cell-reserved` (others' pending) | `--color-tertiary-container` | **none** | another staff's pending request |
| 5 | `.cell-occupied--mine` (new modifier or a second class) | `--color-error-container` | **3px `--color-error`** | a conflict that involves me |
| 6 | `.cell-occupied` | `--color-error-container` | **none** | a conflict that does not involve me |
| — | `.cell-too-soon` | `--color-surface-variant` | none | inside the 3-working-day window |
| — | `.cell-ph` / `.cell-sun` | transparent | none | holiday / Sunday (hover label only) |

Naming of rows 5/6 is a design decision (design.md): either a modifier
`.cell-occupied--mine` (keeps `document.querySelectorAll('.cell-occupied')` counting
working) or a new class `.cell-conflict-mine`. The counter at
`replacement-arrangement.blade.php:1344-1355` counts `.cell-pending` / `.cell-reserved` /
`.cell-occupied` for the summary cards — the choice must keep those counts honest.

### 6.2 Legend rows (final)

| # | Swatch class | Label | Tip (draft) |
|---|---|---|---|
| 1 | `cell-available` | Available | Free slot — click to select as the replacement (slots inside the 3-working-day window are read-only) |
| 2 | `event-selection` | Your Current Selection | The slot(s) you have SELECTED for the replacement |
| 3 | `cell-pending` | Pending (You) | Your replacement request for this slot is WAITING for approval — thick border (3px) |
| 4 | `cell-reserved` | Pending (Others) | Another staff member's replacement request is WAITING for approval here — no border, you CANNOT BOOK it |
| 5 | `cell-occupied--mine` (or the chosen name) | Conflict (Yours) | CANNOT BOOK — your own Normal class or Replacement class is in this slot — thick border (3px) |
| 6 | `cell-occupied` | Conflict (Others) | CANNOT BOOK — VENUE CONFLICT (this slot is used by another class in this room),\nCOHORT CONFLICT (the related cohorts have another class at that time) |

Ownership hint line (this page only):
`thick border (3px) = yours · no border = others'`

### 6.3 Ownership of a red cell — exactly one cause is 3px

| Blocking row | `mine` | Border |
|---|---|---|
| my own occupied class / my own approved make-up (`lecturerBusyRanges`) | `true` | **3px** |
| a cohort's class / a cohort's make-up owned by another lecturer | `false` | none |
| another lecturer's class in the room (grid arm `status 1`) | — | none |
| venue cannot host the session type/headcount (`blockedVenues`) | — | none |
| my own pending request (pending family, not red) | `true` | 3px, in the amber family |

### 6.4 The shared hint line needs a third demo swatch

`ui-legend-bar.blade.php` hardcodes `osd-thick` (3px) + `osd-thin` (0.5px) demos around fixed text.
This page's hint says `no border = others'`, so it needs a borderless demo (`osd-none`) and its own
wording — passed as an optional input whose default is today's markup, so cohort/venue render
byte-identically.

### 6.5 Dimensions that must all be covered

- two families × two ownerships = 4 styled states (+ available, selection, too-soon, PH/Sun);
- light **and** dark theme (`--color-tertiary`/`--color-error` are tokens, so this should
  follow, but both must be looked at);
- whole-file half-hour cells, 1–8 slot spans (the resolver runs per half-hour cell);
- engine arm, grid arm, and both together (Q2A);
- my own pending via the grid arm (3) **and** via the engine arm (own make-up);
- another's pending via the grid arm (4) and via the cohort vector;
- summary-card counters (`#sumTotal`, `#sumPending`, `#sumUnavailable`) must not change
  meaning: an "others' pending" cell still counts as unavailable, not pending-mine;
- the two reference legends must stay byte-identical (the partial is shared).

## 7. Residual risks / open questions

1. **Too-soon cells are not in the 6-row legend.** They stay grey with a hover label and are
   explained by the page's own lead-time banner; the "Available" tip now says so. If the
   user later wants a 7th row, it is additive.
2. **PH / Sunday cells** are transparent with a hover label and are not in the legend either
   (today's "Unavailable" tip claimed them by text; the new tips do not, because they are
   not red).
3. **`blockedVenues`** (room cannot host this class type) is a whole-venue red with no
   "someone is in the way" cause. It is row 6 ("Conflict (Others)") by Q5A, but **the legend tip no
   longer names it** (user, 2026-11-06: "VENUE CONFLICT means occupied, not unsuitable") — those cells
   are explained by the cell tooltip only. Recorded as a deliberate gap, not a missing requirement.
4. **Summary-card labels** (`sumPending` = "Pending") are not part of this brief and do not
   change.
5. **Demo-DB data is untouched**; the receipt uses the testing DB only.
