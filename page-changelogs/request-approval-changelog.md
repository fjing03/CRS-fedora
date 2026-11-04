## 2026-11-04 — the venue line shows the code only

User: drop the room name and the pin icon from **Original Class** and **Requested Replacement**; show
`B010`, nothing else.

Both cells are built by the shared `ui-common.js` builders, which paint venue through `uiVenueLine()`.
That helper is now `uiVenueLine(code, name, opts)` and short-circuits to a bare `<span class="cell-sub">CODE</span>`
when `opts.venueCodeOnly` is set. The page passes `venueCodeOnly: true` at its two call sites
(`:739` classBlock, `:743` replacementBlock). No third argument anywhere else → every other caller is
byte-identical.

**`.cell-sub` is kept** — 12 px, muted. It is what makes the venue read as a sub-line, and keeping it
means the row height does not move.

**One spacing consequence.** The pin icon used to separate the slot-validity badge from the venue, so
removing it gave `✓B010`. `.slot-badge` now carries the 4 px the icon provided → `✓ B010`. Measured gap
4 px, row height still 122 px.

**Verified** against the demo DB in one browser session at 1600 px:

| check | result |
|---|---|
| Original Class text | `Thu, 24 Dec 2026 (Week 14) · 12:00 PM to 1:00 PM (1 hour) · B010` |
| Requested Replacement text | `Fri, 13 Nov 2026 (Week 8) · 1:00 PM to 2:00 PM (1 hour) · ✓ B010` |
| `svg` inside either `.cell-sub` | none |
| `.cell-sub` computed | 12 px, `rgb(158,170,184)` — unchanged |
| row shape | 11 `<td>`, height 122 px (before/after render probe: 81 px / 81 px) |
| **detail modal** | `Venue · B010 — Computer Lab B010` — **unchanged**, as required |
| `/my-replacements` | still pin icon + code — **unchanged** |

## 2026-11-04 — the header is the shared one now

User: this page's header did not look like the rest of the app. It was the last hand-rolled header —
a bare `<h1>` and `<p class="page-desc">` with no `.page-title`, no semester chip, and none of the
partial's spacing.

**Replaced with `@include('partials.ui-page-header', …)`** — the same partial every other page uses.
Title and description content unchanged; the description keeps the `.info-keyword` emphasis from the
previous entry.

**The chip needed one line of script.** The partial always emits `<span class="semester-chip"
id="semesterChip">`, and every other page fills it from `MockData.semester.chipText` — this page never
did, so an empty pill would have appeared. It now fills at init like the others, showing
`202605 · 21-Sep-2026 ~ 27-Dec-2026`.

**Verified against the other pages** by reading the three headers in one browser session at 1440 px:

| | title | chip | description |
|---|---|---|---|
| Request Approval | `.page-title` 22 px / 700 | `202605 · 21-Sep-2026 ~ 27-Dec-2026`, 248 px | `.page-desc` 14 px |
| My Replacements | same | same, 248 px | same |
| My Timetable | same | same, 248 px | same |

No JS errors after the change; no other page touched.

## 2026-11-04 — the page description joins the sweep

The all-pages `.page-desc` update skipped this page because its header is hand-rolled rather than the
shared `ui-page-header` partial — it had a bare `<p>` and no `.page-desc` class at all. Both fixed
here, without touching the header's structure: the paragraph keeps its place, gains the class, and
emphasises its key terms with the same `.info-keyword` used on the other five pages.

`Review and manage **REPLACEMENT REQUESTS** from **LECTURERS IN YOUR PROGRAM**.` — wording unchanged.

## 2026-11-04 — the status filter's label takes Title Case

Minor UI update (user), matching My Replacements. The select's label (its `data-tip`, now also an
`aria-label` so the control has an accessible name) reads **`Filter by Request Status`** instead of
`Filter by request status`. Nothing else changed — same options, same default (`Pending`), same
filter logic.

## 2026-11-04 — the toggle label now reads the same as My Replacements

Minor UI update (user). The toolbar toggle said `Exclude Completed`; it now says
`Exclude <span class="info-keyword">COMPLETED</span> Status` — the exact wording and markup the merged
My Replacements page uses, with the existing `.info-keyword` styling (no new class, no new colour).

Only the label changed. The checkbox id, its checked-by-default state, `toggleHideCompleted()` and
every other control on the page are untouched.

**Follow-up (same day, user):** the chip that toggle produces in the Active Filters bar now says
**`Exclude COMPLETED Status`** too, so the chip and the control that made it agree. It is plain text
like the My Replacements chip (no `.info-keyword` inside a chip) — verified by reading both pages:
`/request-approval-ui` renders `Status: Pending · Exclude COMPLETED Status · Clear all`, and the
toggle's innerHTML is byte-identical to `/my-replacements`.

## 2026-11-04 — consistency pass, part 2: the cells and the schedule rows (1h, 3E-3)

**1h — the two table cells now read exactly like My Replacements.** `HtmlBuilder.classBlock()` and
`replacementBlock()` gained an opt-in `{ merged: true }` mode: duration in **words** (`(1 hour)`)
and the **pin-icon venue line** (`B010 — Computer Lab B010`), with the Original Class cell carrying a
venue at all for the first time. The PL-only slot-validity badge stays, ahead of the venue in the
Requested Replacement cell. Every frozen template and legacy caller omits the flag and renders
byte-identically — that is why the mode is a flag rather than a rewrite.

The three bits the merged page had page-locally (`hoursToWords`, the range→hours parse, the venue
line) are now shared helpers in `ui-common.js` (`uiHoursWords`, `uiHoursFromRange`, `uiVenueLine`)
instead of a third copy on this page.

**3E-3 — the schedule tabs match the same shape.** `Start Time` + `End Time` collapse into ONE
`Time` row (`9:00 AM ~ 11:00 AM`), so each tab is Week · Day · Date · Time · Duration · Venue (plus
Slot Validity on the replacement tab) — the merged pop-up's order.

**§4 — the reference pass.** With the merged page live, this file was re-read side by side against
it; batches A–C above *are* that diff. Matching today: status vocabulary + tooltips, compact cohort
codes, the icon-led cells with duration in words, the pop-up shell (facts strip + three tabs, 616 px),
the one-`Time`-row schedule, `code — name` venues, the email copy contract, the `data-tip` singleton
and the destructive-action confirm. Kept different on purpose (PL-only): ten columns, the
slot-validity badge, the `Approved/Rejected Requests` cards and the Approve/Reject footer.

**Receipts:** live row — Original `Thu, 24 Dec 2026 (Week 14)` / `12:00 PM to 1:00 PM (1 hour)` /
`B010 — Computer Lab B010`; Requested `Fri, 13 Nov 2026 (Week 8)` / `1:00 PM to 2:00 PM (1 hour)` /
`✓ B010 — Computer Lab B010` · `request-approval.spec.ts` 6/6 (U3-T9d now asserts the single `Time`
row and the absence of `Start Time`/`End Time`) · `status-tooltips-filter-chips.spec.ts` 9/9 ·
`pages-parity.spec.ts` 6/6 · `replacement-home-status-badges.spec.ts` 11/11 (the merged page is
unaffected by the shared-helper change).

## 2026-11-04 — consistency pass, part 2: the pop-up shell (3A, 3D)

The **timeline is gone** from this pop-up, the old *Request Info* tab is absorbed by a fixed **facts
strip** (the merged page's M7 shell), and the tabs are now `Original Class Schedule` ·
`Proposed Replacement Schedule` (absent when nothing is proposed) · `Request Status (<short>)`, with
the status **last**.

**Facts strip** — Staff (Proposer) `Name (staffId)` with the shared copy-email contract · Cohorts
(compact, joined ` + `) · Students (`A + B = total` through the shared `cohortBreakdown`) · and,
while the request is pending, the slot **Conflict** badge. Those are the four things a PL decides
with; the old tab's subject code/name already live in the pop-up subtitle and the requested
timestamp moved into the status tab, so nothing was lost.

**Status tab** — the badge plus the shared one-sentence description, then `Requested At`,
`Rejection Reason` (rejected only), `Remark`, and `Reviewed By` / `Reviewed At` when decided (the old
*Review* tab folded in — metadata, not a history rail).

**Width** — the shared `.modal` base is 420 px, which cannot fit three tabs; this one pop-up is now
**616 px**, the same shell as My Replacements (phones are unaffected — `width:100%` still wins below
the max-width).

**Receipts:** `request-approval.spec.ts` 6/6 (U3-T9c now asserts the strip, the three tabs and the
absence of `.modal-timeline`; U3-T9d follows the renamed tab) ·
`status-tooltips-filter-chips.spec.ts` 9/9 · live on `:8000`: a pending row shows
`Staff / Cohorts / Students / Conflict` and the three tabs above; a rejected row drops the Conflict
fact and its status tab carries `Rejection Reason` + `Reviewed By/At`.

## 2026-11-04 — consistency pass, part 2 begins: the status vocabulary and the room names (1f, 3B, 3C)

Source: `prompts/request-approval-ui CONSISTENCY.md` (part 2, batch A). **SDD waived** —
display-only, one page plus one additive payload key set, no schema and no behaviour change.

| # | Change |
|---|---|
| **1f** | The queue badge, its tooltip, the pop-up row and the mobile card now read the **shared M5 vocabulary** (`ReplacementRequestMapper::statusLabel/Short/Sentence/Tip`, carried on the row as `requestStatusLabel/Short/Sentence/Tip`). The page-local `statusTooltip()` **3-key map** and the local `statusDescMap` are deleted. |
| **3B** | The pop-up's *Status* row prints the same label (`Pending — Waiting for Approval`), and *Status Description* prints the shared sentence instead of the local map. |
| **3C** | Both schedule tabs print **`code — room name`** (`B010 — Computer Lab B010`): `toRow()`/`classRow()` now carry `replacementVenueName` and `venueName`, and `ApiReadController::myRequests()` strips both so `/api/requests/my` keeps its pinned key set. |

**The bug 1f closes:** the old 3-key map covered Pending / Approved / Rejected only, so a
**Completed** (or Cancelled) badge rendered with **no tooltip at all**. All five decided states now
carry their copy, and `statusTip()` gained an `$audience` parameter for this one reason — M5's strings
are written for the lecturer ("your request"), while the PL reviews the same states from the other
side of the decision. The lecturer's strings are byte-identical to the approved M5 text; the PL voice
keeps the copy the page already shipped ("You have APPROVED this request.").

**The API guard this needed:** nothing pinned `/api/requests/my`'s key set — `SpanReservationTest`
asserts `replacementTime`'s *value* and the e2e spec reads values, so a leaked `replacementVenueName`
would have passed every existing test. `tests/Feature/MyRequestsApiShapeTest.php` now pins the exact
23-key row and proves the two view-only keys are stripped.

**Receipts:** `MyRequestsApiShapeTest` 2/2 (new) · `MyReplacementsPayloadTest` 12/12 · live on `:8000`
— badge `Pending — Waiting for Approval` + tip `This request is AWAITING your review and decision
(Approve or Reject).`, pop-up `Status / Status Description` from the payload, both tabs
`B010 — Computer Lab B010` · `status-tooltips-filter-chips.spec.ts` 9/9 (RA-1 rewritten: no
`[data-tip]` filter, so a tip-less badge fails) · `request-approval.spec.ts` 6/6.

## 2026-11-03 — consistency pass, part 1 (table, cards, schedule rows) — display only

Source: `prompts/request-approval-ui CONSISTENCY.md`. **SDD waived** by the user (display-only,
one page, no schema, no business logic, no payload change). Part 2 — everything that has to match
the merged **My Replacements** page — waits for the `replacement-merge-one-table` lane; the split
and the reasons are listed at the end.

**Table**

| # | Change |
|---|---|
| 1a | **Students column removed** from the table (the student count stays in the pop-up's *Original Class* tab and in the Approve/Reject context card) |
| 1b | **Cohort column added** immediately before *Original Class* — one **compact code per line** (`RSD3(S1)G2`, `RSD2(S1)G2 / RSD2(S1)G1`), not sortable (a multi-cohort row has no single value) |
| 1c | `Lecturer` → **Staff (Proposer)** |
| 1d | `#` → **Request ID**, and the cell now prints the **real request id** (`#26`) instead of the row counter — the header would otherwise be a lie |
| 1e | The urgency badge now carries the level **and** the remaining days: `Normal — 52 days left`, `Urgent — 2 days left`, `Urgent — today`, `Overdue — 5 days past`. Same category, same badge styling; the tooltip keeps its fuller sentence, and the Requested-age tooltip keeps the bare word |
| 1g | `Proposed Replacement` → **Requested Replacement** (header + the sort hint line) |

**Summary cards** — `Approved` → **Approved Requests**, `Rejected` → **Rejected Requests** (ids
unchanged, so every count and filter still works).

**Request detail modal**

- **Week row** added to *Original Class* and *Replacement Class* (`Week 14` / `Week 7`), derived
  from the date + semester start — no new payload key.
- **Duration** now also on the *Replacement Class* tab, in words (`1 hour`, `2 hours`) from the
  requested time range. The *Original Class* tab already printed words.
- **Cohort(s)** in the modal is the **same compact codes joined with ` + `**
  (`RSD3(S1)G2 + RSD3(S1)G3`) — the table stacks them, the modal joins them (the user's own
  format ruling 2026-11-03).
- **Footer**: the **Close** button is gone (the header ✕, the overlay click and Esc still close),
  and **✕ Reject** now sits **bottom-left** with Approve bottom-right.

**Not applied yet — they depend on `replacement-merge-one-table` (do not lose this list):**
1f status-badge wording (Pending/Completed) · 1h the two schedule cells matching *My Replacements* ·
3A replacing the timeline with the facts strip · 3B modal status wording · 3C the one-row schedule
block (Date · Time · Venue `B103 — Tutorial Room`) · 3D the three-tab order · 3E's remaining half.
All seven need the merged page's vocabulary (`ReplacementRequestMapper` status helpers, M5) or its
modal shell (M7), which that lane is building right now. Doing them early would create a second
copy that has to be deleted.

**Measured side effect (honest):** the table is ~150 px wider (Cohort column + the longer urgency
text). At 1400 px it already scrolled before this change; at **1600 px it used to fit exactly and
now needs ~40 px of horizontal scroll**. `.grid-scroll` does scroll, so every column stays
reachable (verified by scrolling to the Actions buttons).

**Tests** — `tests/request-approval.spec.ts` U3-T9c now asserts the header set (no *Students*, no
*Lecturer*, no *Proposed Replacement*; Cohort directly before Original Class) and that the Request-ID
cell prints `#<id>` and every cohort cell holds compact codes (9/9 green);
`tests/status-tooltips-filter-chips.spec.ts` RA-2 follows the new badge text (9/9 green);
`tests/ui-regression.spec.ts` + `tests/nav-identity.spec.ts` 16/16 green. No PHP file changed.

## 2026-11-03 — the approved row can read **Completed** (no code change on this page)

SDD lane `.sdd/changes/request-completed-status/` (C1/C2/C3). Nothing in
`request-approval.blade.php` was edited — this page reads `r.status` straight from
`ReplacementRequestMapper::toRow()`, which now returns the **effective** status.

What changes on screen, by itself:

| Place | Before | After (a request approved for a class that has already happened) |
|---|---|---|
| Row badge | `Approved` | `Completed` (dot stays `dot-success`) |
| Status tooltip | "You have APPROVED this request." | "Replacement conducted" (already mapped) |
| **Approved** summary card | counted it | does **not** count it |
| **Reviewed** summary card | counted it | still counts it (that card matches `Approved`, `Rejected` **and** `Completed`) |
| Exclude Completed toggle | never hid anything | hides it when ticked (default state unchanged) |
| Status filter | never matched a row | `Completed` now matches |

The derived status is **not** stored: the row's `status` column stays `approved`, so the approval
and rejection paths, the queue buckets and the one-active-request index are untouched.

Verified (no edit): the `Completed` option in `#statusFilter` (:259), the toggle markup
(:268–270) and its filter (:655), the `Approved`/`Reviewed` counts (:756–758), and the status dot
(:953). `ApprovalWorkflowTest` gained `test_mapper_reads_a_past_approved_request_as_completed`
(the row reads `Completed` while its bucket stays `queue`) and
`test_mapper_never_guesses_completed_without_a_readable_date` (an unreadable replacement date keeps
`approved`).

# Request Approval — changelog

## 2026-11-02 — visible demo reminder above the timeline rail

The shared `DetailModal.timeline()` now prints one line of plain text directly
above every rail: **"the timeline function got error -- ask SV does this
useful/helpful for Them"**. On this page that rail is the one with the
permanently empty **Viewed** step (no `viewedAt` source exists) — see
`prompts/extra tasks/task-02-replacement-history-timeline.md`. The note is a
demo aid, not product copy; styled by `.modal-timeline-note` (muted token,
italic). Delete it with the rail rebuild.

## 2026-10-30 (SDD `status-tooltips-filter-chips`) — status/urgency tooltips, the real Overdue badge, filter-bar upgrade

**a) Status tooltips** (the user's frozen wording; shared `data-tip`,
**ABOVE** the element): page-local `statusTooltip()` map + `statusTipAttr()`
tag the queue-table badge (keeps its `onclick`), the detail-modal Status
row, and the **mobile card** (new `opts.statusTip` into the shared
`HtmlBuilder.requestCard` — absent opt byte-identical). Tips (the 3
statuses this queue renders): Pending → "This request is AWAITING your
review and decision (Approve or Reject)." / Approved → "You have APPROVED
this request." / Rejected → "You have REJECTED this request."

**b) The Overdue urgency level (the user's sanctioned badge-logic change —
presentation only; status logic/DB/queue untouched).** `urgencyLevel()`
now consumes `urgencyDays()` (`d < 0 → 'overdue'`); `urgencyClass` →
`urgency-overdue`, `urgencyLabel` → **Overdue**; new solid-red pill
`.urgency-overdue { background: var(--color-error); color:
var(--color-on-error) }` (tokens only — same red family, one severity
step up from Urgent's translucent container); overdue rows keep the
`row-urgent` highlight; **the Urgent filter still matches overdue rows**
(new `urgencyMatches()` — the silent-drop law; the all/urgent/normal
button set NOT extended; Normal filtering unchanged). Dynamic urgency
tooltips via `urgencyTip(r)` (N computed at render, singular "day", N=0 →
"Urgent — the Proposed Class is TODAY.") on the confirm-modal badge, the
table cell, and the mobile card (`opts.urgencyTip`); the Urgency
column-header tip synced to the three-level copy ("past = Overdue (solid
red), ≤3 days = Urgent (red), 4+ days = Normal") so it stays truthful.
Age chips flow the new label ("… · Urgency: Overdue" — reads correctly).

**c) Filter bar** — `#clearFilters` → **`#resetFilters`** (behaviour
unchanged) with the tip "Reset all filters to DEFAULT"; the bar label
`Filters:` → **`Active Filters:`** (now identical to my-request-history);
a **Clear all** chip (`filter-chip filter-chip-clear-all`, neutral dashed
pill in theme.css, tip "Remove every active filter") rides the chips
innerHTML rebuild while any chip exists and triggers the EXISTING
`resetFilters()` verbatim (one function, two triggers — real reset, never
a visual hide; the default `Status: Pending` + `Exclude Completed` chips
legitimately remain). Individual ✕ chip removes untouched. Verified:
pint + phpstan(1G) + `tests/status-tooltips-filter-chips.spec.ts` (see the
change-level log).


## 2026-10-25 (the user's part 2) — the Exclude Completed chip + hover hints on EVERY toolbar element

**a) Chip parity with my-request-history:** `renderFilterChips()` now pushes
the **Exclude Completed** chip while the toggle is on — same label, same
position (last), same ✕-remove behaviour (the ✕ unchecks
`hideCompletedToggle` and calls `toggleHideCompleted()`, which refreshes
state + table + summary + saved filters). The chip-row label became
conditional (`chips.length > 0` … like my-request-history) so no orphan
"Filters:" renders when every filter is off.

**b) Hints on every toolbar element** — the shared `data-tip` attribute
(`initDataTipTooltips` renders it FIXED **ABOVE** the element; existing UI
design, no new mechanism): search → "Search by course code, name, or
lecturer" (this page's haystack = `courseCode + courseName + lecturer` ✓
honest), status select → "Filter by request status", urgency chips →
"Show requests of any urgency" / "Show only urgent requests" / "Show only
normal requests", week-nav → "Previous week" / "Pick the Original Class week to display" /
"Next week" (via NEW optional `prevTip`/`selectTip`/`nextTip` params on the
shared `partials.ui-week-nav` — **null by default, so every other page
renders byte-identical**), toggle → "ON = completed requests are excluded
from the list", Reset Filters → "Reset all filters" (verbatim
my-request-history's), result count → "Rows matching the current filters".


## 2026-10-25 (the user's consistency fix) — the toggle says "Exclude Completed"

The completed-exclusion toggle now speaks the SAME words as
`/my-request-history-ui` (identical function → identical label): the visible
label `Hide Completed` → **`Exclude Completed`** — in the live toolbar
(`request-approval.blade.php`) AND its design-template mirror
(`request-approval-UI-design-template.blade.php`, so template ↔ live stay in
sync). Internal identifiers (`hideCompletedToggle`, `toggleHideCompleted()`,
the `hideCompleted` state) are not user-visible and were left alone. No test
locks either wording. Noted, NOT part of this fix: my-request-history also
renders an `Exclude Completed` filter chip when the toggle is active —
request-approval's chip row has no chip for this toggle (possible follow-up).


## 2026-10-18 — SDD `span-aware-approval-flow` slice C+D (the REAL page)

The route `/request-approval-ui` stops serving the D10 mock the moment
`App\Livewire\RequestApproval` exists (`routes/web.php`'s `class_exists`
branch — no route edit). The REAL page is a byte-for-byte port of the frozen
template with only the documented BRIDGE marks:

- **One page, real rows** — FCFS pending (`submitted_at` ASC, FR 3.2) + the
  decided bucket (approved/rejected; the `Completed` filter stays an honest
  EMPTY state — the out-of-scope leg). `reviewedBy = approver.name`,
  `reviewedAt = decided_at` (the decided row's detail modal).
- **ONE shared validity predicate** — the queue's 🟢/⚠️ badge and the
  approve/reject actions read the same `ApprovalValidity::evaluate` (FR 3.4;
  2-case map: 'already decided' / the existing slot_not_available named-time
  copy).
- **The §10.0.6 promotion**: the request→row mapper is now ONE shared
  `App\Services\ReplacementRequestMapper` (was: ApiReadController's
  `myRequests()` closure + MyRequestHistory's `requestRow()` — the third
  consumer is the PL queue). Its span read is DATA-DRIVEN (junction min/max
  for pending AND approved, legacy anchor fallback for junction-empty rows of
  ANY bucket — never bucket-keyed (spec RR1).
- **Approved-bucket junction switch (Q3=A)** — the approved display reads the
  junction's true range everywhere: timeline replacement block,
  MyRequestHistory, API `myRequests()`, the venue-slots API marker block
  (full-run state 4), the PL queue's decided bucket, and student
  `ReplacementHistory` rows. span-1 rows stay byte-identical.
- **The decide surface** — Approve (optional note → `details.approver_note`)
  / Reject (reason mandatory SERVER-side; the frozen chips are UI-only;
  `Other` = any typed text stored verbatim). **NO undo** (user ruling
  2026-10-17): decisions are final; the confirm popups carry the frozen
  copy. Bulk = sequential FCFS via the same real actions; a stale row fails
  politely (its conflict toast) while the rest decide; ONE shared reject
  reason covers every ticked row.
- **The emails** — ReplacementSubmitted (submit-side, FR 4.15) /
  ReplacementDecided (FR 2.13, dispose + reason) / ReplacementApprovalNotice
  (FR 1.9, cohort students) — ALL queued (FR 4.16, database connection),
  capture-at-queue-time (the mail body is a snapshot; Rejected rows never
  re-read a deleted junction). Dev/demo = `MAIL_MAILER=log` — NOTHING really
  sends (user ruling A-now/B-later on the in-app panel: the frozen 🔔 panel
  STAYS mock-fed; its real-data wiring is a planned follow-up mini-SDD).
- **The D7 original-release** — approve stamps
  `class_exceptions(official_event)` for the original occurrence (guard: no
  write when an exception exists — the unique pickets it; a racing stamp
  CONTINUES via savepoint) + frees the original occurrence's OWN booked
  venue rows (`available`, version-checked, identity-pinned — the old
  room+time becomes bookable by others per Objective-3's letter). Visible
  on week pages only post-holiday (the W14 case is a holiday week).

## 2026-10-18 — T18/T19 postscript (the email infrastructure)

- `tests/Feature/EmailQueueTest.php` — T18's two families: the enqueue
  assertions (per-PL submitted copies, decided + herteroés cohort-notice with
  the roster count, decided-only for reject, the Q13 decide-own case) + the
  NFR 1.4 REAL-worker drain (NO Mail::fake / NO Queue::fake; the jobs-table
  row is REAL, `queue:work --once` consumes it inside one cycle, elapsed
  <1 min) — the drain fixture drops the cohort attach so exactly ONE job
  runs.
- `docs/queue-and-emails.md` — the runbook (dev = log mailer, jobs persist
  until the worker claims them, failed_jobs stock behavior, the SMTP flip being
  a `.env` change only).

## 2026-10-18 (user report fix) — STUDENTS "undefined" + the dead detail modal

The live page reported two issues, same root: the queue bucket's rows lacked
the COHORT METADATA (`totalStudents` / `cohorts` / `cohortCounts`) —
`renderRow` printed `undefined` in the STUDENTS column and
`openModalById` threw on `cohorts.join()` inside the detail-modal builder
(silently deadening the click → popup). **Fix:** the shared mapper's
`toRow` now carries the queue bucket's cohort trio (SPACED labels +
values-array counts + the summed total — `ReplacementHistoriesTest`-pinned
byte shapes are untouched for the other buckets). Smoke: new
`U3-T9c` pins STUDENTS numeric + row-click → modal opens/closes.

## 2026-10-18 (user request) — Request Details modal copy pass

The modal's Original/Replacement tabs now print:
- CONSISTENT HUMAN DATES — '24 Dec 2026' (never the raw ISO; the
  "17 Oct 2026, 9:02 AM" pattern family);
- hours in WORDS — '1 hour' / '2 hours' (was '1h');
- the time SPLIT — Start Time and End Time are separate rows on BOTH tabs
  (the replacement range "12:00 PM to 1:00 PM" split, 12h form);
- the Slot Validity ✓ with its FULL sentence ('✓ Slot available' / '⚠ …' +
  the conflict reason when stale) — the tooltip-only description is gone.

## 2026-10-18 (user request 2) — DAY row in the Replacement Class tab

The Replacement Class tab now carries a **Day** row (Date / Day / Start Time /
End Time / Venue / Slot Validity — the same order as the Original Class tab),
the weekday derived from the replacement's ISO date. `U3-T9d` pins it.

## 2026-10-18 (user report) — the PL nav badge was 1 HERE, 8 EVERYWHERE ELSE

The badge (`window.navPendingBadge`) had TWO sources disagreeing: the D10-era
`MockData.approvalRequests` mock seed carried **8 fake pending rows** (so
every untouched page's `updateNavBadge()` printed 8), while the REAL page
bridged the payload → **1** (the demo's true pending count is 1 — request
#8). Fix (the shared nav partial is the single source now):

- `partials/ui-nav-bar.blade.php` renders the badge count SERVER-side from
  `ReplacementRequest::where('status','pending')->count()` (PL-only; hidden
  when 0) AND emits `window.PENDING_NAV_COUNT = <n>;` (the `{!! !!}` form —
  the first cut's `{{ }}` HTML-escaped it, the browser never executed it).
- `ui-common.js::updateNavBadge(count)` reads the REAL global first (the
  queue's fresh count after a decision), then the layout's server value, and
  only falls back to the mock-era count for pages that never get the
  partial's global — so the fake 8 can no longer reach a PL navbar.
- The approval page re-derives the count from each wire response
  (`applyDecisionResponse`), so the badge tracks the live queue after
  approve/reject/bulk.
`U3-T9e` pins the accuracy + cross-page consistency (approval page vs
my-timetable page = the same real number).

## 2026-10-27 · Detail modal Total Students = A+B=total (BMSE3153-class-info)
The Original Class tab's "Total Students" row now renders the shared
`cohortBreakdown(r)` (ui-common.js) — multi-cohort rows show `A + B = total` from
the payload's positional `cohortCounts` (ReplacementRequestMapper already emitted
that field; mock `requests` carried it too — no payload work needed). Single-cohort
rows keep the plain count. List-table "Students" column stays the plain number
(compact column, not a modal row).

## 2026-10-30 (lane `ra-result-count-showing-of`) — the result counter matches the house format

The result counter rendered a bare runtime count (`X result(s)`) via a
page-local `updateResultCount()` shadow — every other list page uses the
shared `updateResultCount(cfg)` helper's `Showing X of Y results` format.
The user ruled: match my-request-history.

- FIX: the page-local function DELETED; the single renderTable() call site
  calls the SHARED helper with the frozen cfg
  (`elId: 'resultCount', data: currentFiltered, total:
  MockData.approvalRequests.length, label: 'results'`) — the identical
  wording/spacing. The static `#resultCount` span stays EMPTY (server-driven
  data; no baked default — renderTable() fills it on first paint).
- The Y truth: the span-aware payload's pending + decided rows
  (unscoped, the frozen span-aware behavior); Cancelled/Completed filter
  selections honestly show 'Showing 0 of Y results'; approve/reject flips a
  row INTO the decided bucket and renders a fresh payload — Y recomputed at
  call time.
- Receipt: the structural (the Playwright receipt waived by the user this
  session — recorded in the lane's review-log): the local def gone, ONE
  shared call with the frozen cfg, ui-common.js untouched; suite 277/277;
  pint/phpstan green.

## 2026-10-30 (lane `filter-clear-all-off`) — Clear all takes Status: Pending OFF too

Request Approval's Clear all previously ran `resetFilters()` verbatim — the
page default = Status: Pending + hideCompleted on, so the two default
exclusion chips SURVIVED the click (Active Filters: stayed). The new
`clearAllFilters()` = the resetFilters body with `statusFilter = 'all'` +
BOTH hideCompleted statements flipped (the variable the chip branch reads +
the checkbox the ✕ sets) — the bar renders EMPTY, the controls at OFF. The
Reset Filters button keeps the DEFAULT law (Status: Pending + hideCompleted
on — the tip 'Reset all filters to DEFAULT') byte-identical. Same receipts
as the sibling page (the status-tooltips spec 9/9, pint + phpstan 0, 277/277).
