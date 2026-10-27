# Request Approval — changelog

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
