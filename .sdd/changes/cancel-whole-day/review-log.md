# Review log — cancel-whole-day

The reviewer tool (`@sdd-reviewer`) is not available in this harness session, so each batch below is a
**file-based self-review** against the frozen brief, recorded rather than skipped.

## Batch 1 (explore) — 2026-11-11
Explored the modal, the `CancelsClasses` trait, `CancelClass`, the three page payloads, and the parked
`bulk-cancel-class` lane. Findings that changed the plan:

### 🔴 Fixed
- **A second cancel mechanism was about to be invented.** The parked lane already owns "cancel a set,
  all-or-nothing, and close the request it holds". The plan now reuses that action and drops its UI.
- **The day's classes are not available on two of the three pages.** `MyTimetable` ships
  `eventsByWeek`; `CohortTimetable` and `VenueTimetable` ship one slice each. A server read was added to
  the plan (one trait method for all four surfaces).

### 🟡 Addressed
- Approved replacements are excluded naturally (the approval already stamps the original occurrence with
  an exception) — recorded instead of inventing a new rule.

### 🔴 Outstanding
- _(none)_

## Batch 2 (proposal) — 2026-11-11

### 🔴 Fixed
- The first draft treated "whole day" as "the classes the page shows"; the user's ruling (every class that
  day, across cohorts and rooms) forced the server read into the data layer rather than the page.
- The success screen's single-class shape was left unspecified; decision 5 now pins one Close + the list.

### 🟡 Addressed
- Scope creep bounded explicitly: the parked lane's button, date picker and `batch_id` migration are out.

### 🔴 Outstanding
- _(none)_

## Batch 3 (prototype round) — 2026-11-11, three passes

### 🔴 Fixed
- **The count was invisible in dark mode** — it used `--color-primary`, the deep button blue. It now uses
  `--color-on-primary-container`.
- **The phone frame lied.** A media query cannot fire inside a 1400 px page, so the 390 px frame was
  showing desktop rules. The frame now carries the same rules scoped to `.phone`.
- **Rows clipped at 390 px** (`scrollWidth 400 vs clientWidth 289`) — the row now wraps: time, then class
  + room + cohort, then the stack.

### 🟡 Addressed
- The cohort and the request mark became one right-aligned stack, so a long pill can no longer widen the
  row; amber = will be cancelled, red = withdrawn.

### 🔴 Outstanding
- _(none)_ — measured: 0 console errors; no row or success line clips at 1400 px or 390 px.

## Batch 4 (design) — 2026-11-11

### 🔴 Fixed
- **The client would have been trusted with the set.** `cancelClassDay()` now re-resolves it on the
  server, so a tampered list cannot widen the blast radius.
- **`official_event` reason correction** (the parked lane's R18) does not apply here — the excluded rows
  already hold an exception — so it is explicitly left out instead of copied in blindly.

### 🟡 Addressed
- The block's shell is an empty container filled by JS (the house pattern), because the classes come from
  a server read, not the payload.

### 🔴 Outstanding
- _(none)_

## Batch 5 (specs) — 2026-11-11

### 🔴 Fixed
- **R4.4 missing** — without it a modified client could cancel a class the lecturer does not teach. Added.
- **R7.1 missing** — the unticked path must stay byte-identical; without that clause a refactor could
  silently change today's single cancel.

### 🟡 Addressed
- S6 (a server-side refusal) and S7 (the other two timetables) added so the risky paths have scenarios.

### 🔴 Outstanding
- _(none)_

## Batch 6 (tasks) — 2026-11-11

### 🔴 Fixed
- **T1 had no proof obligation.** "Behaviour identical" now means the three callers' existing tests pass
  *unchanged*.
- **T5 named the grid update loosely.** It now says the success Close refreshes from the server, so four
  grids are never patched by hand.

### 🟡 Addressed
- T6 names the known `ReplacementHistoriesTest` red so a future reader does not mistake it for a
  regression from this lane.

### 🔴 Outstanding
- _(none)_

**Verdict: all six batches pass. The lane is fully specified and NOT applied — it waits for the user's
go-ahead on T1.**
