# explore-brief — student-class-replacements

**Brief:** "New Idea — Student Replacement History Redesign" (user, 2026-11-09).
**Page:** student-side Replacement History (`/replacement-history-ui`, nav "Replacement History").
**Status:** eight grilling rulings taken; nothing proposed or built yet.

---

## 1. The measured starting point

| | Today | The staff page it is being aligned to |
|---|---|---|
| What a row is | a **request** (pending or approved) touching my cohort | a **class occurrence** (session × week) that is *conflicted* |
| Which classes | only those with a request | every conflicted occurrence — a `class_exceptions` row (annual leave, medical, official event…) **or** a calendar holiday — request or not |
| No-request classes | invisible | `Need to Arrange` |
| Week filter | the **requested slot's** week | the **class's own** week |
| Columns | Course · Lecturer · Original Slot · Conflict Reason · Requested Slot · Request Status | Course · Cohort · Original Class · Conflict Reason · Requested Replacement · Request Status · Action |
| Actions | none (read-only) | arrange · cancel · jump |

**Conflicted-occurrence volumes** (measured on `class_replacement_fresh`, all 14 weeks):

| Cohort | Sessions | Conflicted occurrences | from holidays | from exceptions | Requests that exist |
|---|---|---|---|---|---|
| DFT2(S1)G1 (`24DFT0001`) | 11 | **6** | 6 | 0 | 1 pending |
| DSF2(S1)G1 | 18 | **11** | 10 | 1 | 8 |
| RSD2(S1)G2 (`24RSD0009`) | 16 | **11** | 9 | 2 | 2 (approved + cancelled) |

The decisive fact: on the student side, "all conflicted classes" is **mostly public holidays** — the calendar, not a replacement. The demo has very few `class_exceptions` because lecturers create those for their own sessions.

**The jump mechanism exists and works.** Measured on staff 5425: `goToReplacementInTimetable(27, 8)` → `/my-timetable-ui?repl-hl=27&repl-w=8` → lands on Week 8 (selector value 7) and pulses the block carrying `data-rid="27"`. Blocks get `data-rid` in the shared grid builder (`public/js/ui-common.js:960`) whenever the event carries `requestId`, and the shared trait sets it (`ResolvesTimetableTimeline.php:368`), so the *student* grid already tags its replacement blocks. The only blocker is one hard-coded line in `applyReplacementHighlight()`: it returns unless `location.pathname === '/my-timetable-ui'`.

---

## 2. The eight rulings (all from the user, 2026-11-09)

**R1 — Row population (user: "Cancelled Class & PH").** A row is a **conflicted class occurrence**: an exception (cancelled class) **or** a calendar holiday, **whether or not a request exists**. Rejected option: holidays only when a request exists (hides real cancellations); requests-only (today's model — contradicts the brief).

**R2 — Status vocabulary (option B).** One story for every unarranged class: **`Awaiting Arrangement`** covers *no request*, *cancelled request* and *rejected request*. Arranged rows keep the staff labels: `Pending — Waiting for Approval`, `Approved`, `Completed — 1 day ago`. Rejected options: staff's `Need to Arrange` (reads as an instruction to someone who cannot act); `Rejected` on the student page (re-opens the 2026-11-08 ruling and invites unanswerable questions).

**R3 — Week filter basis (option A).** The filter keys on the **class's own week**, and the All-option reads **`All Original Class Weeks`**. This retires today's `All Requested Slots Weeks` (correct for the old model, ambiguous for the new one: an arranged row's request can sit in another week entirely). Rejected: keep filtering on the requested week (two meanings in one dropdown); two dropdowns (doubles the toolbar for a read-only page).

**R4 — The one toggle (option C).** Keep the staff control **`Exclude COMPLETED Status`**, and "completed" is decided by the **make-up (requested) date only** — exactly the staff rule. Accepted consequence, stated to the user: a class with **no** make-up never counts as past, so an unarranged cancelled/holiday class stays in the list for the whole term. Rejected: class-week basis (a row would hide or not depending on which date you look at); broadening to "past weeks" (that was option B in the grill, not chosen).

**R5 — Page identity.** Nav label **"Class Replacements"**, address **`/student-class-replacements`**. House practice keeps the old address `/replacement-history-ui` alive as a second entry pointing at the same component (query strings survive; no redirect). Nothing stored points at the old URL (checked: zero notification links), so the move is clean. Files that name it: `routes/web.php`, `resources/views/partials/ui-nav-bar.blade.php`, `tests/Feature/NavIdentityTest.php`, `tests/Feature/RouteGateMatrixTest.php`, `tests/nav-identity.spec.ts`, plus the component's `activeNav` key.

**R6 — Read-only affordances (option B).** Row click keeps the detail pop-up. The pop-up carries a **"Go to My Timetable"** button whose behaviour is the staff one: jump to the student timetable **and flash the block**. No action column, no arrange/cancel anywhere.

**R7 — Summary strip (option A + the Hours card).** Five cards: **`Awaiting Arrangement` · `Pending` · `Approved` · `Completed` · `Class Hours Affected`**. `Total` is dropped; `Hours` is renamed because it was ambiguous.

**R8 — What the hours card counts (option C).** `Class Hours Affected` = the **class hours of every visible row**, arranged or not ("how much of my teaching was hit?"), never only the arranged ones.

---

## 3. Final shape (the completeness checklist)

**Rows:** for the logged-in student's cohort, every (session × week 1..N) occurrence where an exception row exists **or** a holiday row lands on the session's day. One row per occurrence, carrying: course code/name/type, lecturer, the class's own slot (week, date, day, start, end, duration, venue), the latest request's slot when one exists (span-based, anchor fallback), the conflict kind (holiday vs cancelled) + holiday name, and the derived status.

**Status mapping (R2):**

| Stored request state | Row status text | Badge class |
|---|---|---|
| no request | `Awaiting Arrangement` | `status-none` + outline (staff's no-request shape) |
| `pending` | `Pending — Waiting for Approval` | `status-pending` |
| `approved`, make-up date ahead | `Approved` | `status-approved` |
| `approved`, make-up date passed | `Completed — n days ago` | `status-completed` |
| `cancelled` | `Awaiting Arrangement` | as no-request |
| `rejected` | `Awaiting Arrangement` | as no-request |

**Filters:** week = the class's own week (`All Original Class Weeks` default, as today's default is All Weeks), plus `Exclude COMPLETED Status` ON by default (R4). Active-filter chips follow (the bar exists since 2026-11-09).

**Columns (unchanged from today's 6, in the ruled order):** `COURSE` · `LECTURER` · `ORIGINAL SLOT` · `CONFLICT REASON` · `REQUESTED SLOT` · `REQUEST STATUS`. An unarranged row's REQUESTED SLOT cell prints an em dash (never "Awaiting PL approval" — that wording dies).

**Cards:** `Awaiting Arrangement` · `Pending` · `Approved` · `Completed` · `Class Hours Affected`, all counted from the visible set except that the hours card sums class durations (R8).

**Pop-up:** Course / Original Slot / Conflict Reason / Requested Slot / Request Status groups as today, plus a **Go to My Timetable** button (R6) that is disabled/absent when the row has no requested slot (nothing to flash).

---

## 4. Data flow (who asks whom)

- `App\Livewire\ReplacementHistory` today: requests touching my cohort → `historyRow()`. It **becomes**: my cohort's sessions × weeks × (exceptions ∪ holidays) → one row each, with the latest request per (session, week) folded in.
- Reuse, do not re-derive: `App\Support\LocalTime` (dates), `ReplacementRequestMapper` (`effectiveStatus`, `span`/`spansForRequests`, `statusLabel/statusShort/statusTip`, `approvalDeadlineLabel`), `ResolvesTimetableTimeline` (`timelineSemester`, `timelineWeeks`, `holidaysForJs`, `slotIndex`, `cohortCode`). The staff page's `buildRow()` is the reference implementation of the conflict test (exception → title-cased reason; `official_event` on a holiday occurrence reads as the holiday; holiday alone → `Public Holiday`).
- Conflict-reason wording stays the student-guarded one from 2026-11-09 (Q1=B): holidays keep their real name, everything else reads `Class Cancelled` — no leave type ever reaches a student.
- Timetable jump: extend `applyReplacementHighlight()` to accept `/student-my-timetable-ui` (it already reads `repl-hl` / `repl-w`, and the student grid already emits `data-rid`). `goToReplacementInTimetable()` builds a staff URL today — it needs a page-aware target, kept in one place.

## 5. Rejected approaches, and why

- **Requests-only rows** (today's model): the brief explicitly asks for classes that *still need* arrangements, which by definition have no request yet.
- **Holidays only when a request exists**: hides a cancellation the lecturer has already made.
- **`Need to Arrange` / `Rejected` verbatim**: addressed to someone who cannot act; re-opens the 2026-11-08 student-history ruling.
- **Requested-week filter retained**: ambiguous once a row is a class occurrence.
- **A second dropdown for the requested week**: toolbar bloat on a read-only page; staff has no precedent.
- **Adding an action column with the jump button** (the staff shape): the page must *look* read-only, so the button lives in the pop-up.
- **Six summary cards**: the strip gets crowded on a phone; `Total` was the least useful of the six.
- **Counting only arranged hours**: a page full of unarranged classes would read `0`.

## 6. Known open questions (for the propose stage)

1. **The `Completed` card still reads 0 by default** — R4's toggle hides exactly those rows. Decision needed: explain it in the card's hover text (the parked lane's recommendation), count it from the unfiltered set, or accept the zero. (This is finding 3 of `.sdd/changes/student-history-copy-polish/`, which this lane absorbs.)
2. **Empty-state and page-description wording** — both are rewritten by this lane anyway; findings 1 and 2 of the parked lane die with the redesign.
3. **Where the student vocabulary lives** — a `student` arm in `ReplacementRequestMapper::statusLabel()`/`statusShort()` (one place, audience-aware like `statusTip`), or composed in the component. Recommend the mapper, so the two pages cannot disagree about a state — only about the words for it.
4. **Sort order default** — staff sorts needing-action first, then date ascending. The student page has no "needing action" concept for the reader; recommend class date ascending (the natural reading of a tracker), with the existing sortable headers unchanged.
5. **Mobile card** — the 2026-11-09 stacked-slot card still fits (original above requested); an unarranged row must render its requested block as an em dash without collapsing the layout.
6. **Does `Exclude COMPLETED Status` still deserve its staff wording** now that unarranged rows can never be "completed"? Recommend keeping the exact staff label (one vocabulary) and letting the hover text explain the make-up-date rule.
7. **Old-address alias** — keep `/replacement-history-ui` pointing at the same component (recommended, house practice) or remove it.

## 7. Scope guard

**In:** the page's data model, columns, statuses, filters, cards, pop-up, its name/route, and the one-line extension of the timetable highlight arm so the pop-up's button works like staff's.
**Out:** any write action for students; the staff pages; the notification system; the timetable grid's own behaviour; the three parked copy bugs as separate work (absorbed here).
