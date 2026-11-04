# Spec — replacement page merge (`replacement-merge-one-table`)

Capability: **replacement-page-merge**.  
**Frozen:** 2026-11-04, round 2 PASS (2 blockers, 16 should-fix, 5 residuals folded). Requirement deltas for this lane only; every other behaviour
of the two pages it replaces is either carried over unchanged or explicitly retired below.

The user's decisions M1–M17 (`proposal.md` §2) are the authority; `design.md` says how. This file
turns them into checkable requirements (`R`) and scenarios (`S`), in the user's language. A scenario
is a fact the apply round must be able to demonstrate from outside the page — not a test file name
(those are `design.md` §8).

---

## R1 — One table, one row per class occurrence (M1)

A lecturer's replacement page lists **classes**, not requests. There is exactly one row per
`(class_session_id, week_number)`, and it appears whether or not a request exists for it.

- The row set is the lecturer's conflicted occurrences exactly as the current home page defines
  them: an exception or a calendar holiday for that cell. A request never adds a row and never
  removes one.
- Two requests on the same occurrence are still **one** row.

**S1.1** an occurrence with no request is listed, with the status `Need to Arrange`
**S1.2** an occurrence with three requests (rejected → cancelled → pending) is listed once, and its
badge reads `Pending — Waiting for Approval` (the newest wins)
**S1.3** two different weeks of the same class are two rows
**S1.4** a request on an occurrence that has no exception/holiday produces no row (the row set is
closed by R1's first bullet; recorded so it is a property, not a surprise)

## R2 — The row's status is its newest request, and every state has one label (M1, M5)

The row's status is the **newest** request for that occurrence (greatest `submitted_at`, tie-break
`id`), whichever status it has. Six states exist, with exactly these labels:

| State | Label |
|---|---|
| no request | `Need to Arrange` — **outlined**, never filled |
| pending | `Pending — Waiting for Approval` |
| approved | `Approved` |
| rejected | `Rejected` |
| cancelled | `Cancelled by YOU` |
| completed | `Completed — <n> days ago` |

- `completed` is derived, never stored: stored `approved` **and** the replacement's date strictly
  before today (§6b, the completed lane's law). The page never decides it; the mapper does.
- `<n>` uses the server's own day, so the label and the status cannot disagree.
- Every state also has exactly **one sentence** for the pop-up, looked up by the **status**, not by
  the label. A completed row's sentence carries the count: `The replacement took place 1 day ago.`
  (or `today.`).

**S2.1** a rejected-then-pending occurrence shows `Pending — Waiting for Approval`
**S2.2** a completed row reads `Completed — 1 day ago` for a replacement yesterday, and its sentence
reads `The replacement took place 1 day ago.`
**S2.3** an approved row whose replacement is **today** reads `Approved` (not Completed)
**S2.4** the stored status is still `approved` after the page renders (nothing is written)
**S2.5** each of the six states shows its own badge text **and** a non-empty sentence — including
`Rejected` and `Need to Arrange`, neither of which may print an empty row

## R3 — One canonical address, two that still work (M2)

The page's address is **`/my-replacements`**. `/replacement-home-ui` and `/my-request-history-ui`
stay as **extra addresses for the same page** — not redirects, so a query string survives.

- Every live **navigation producer** points at the canonical address: link `href`s, `location`
  assignments, the shared `NotificationRoutes` values, the nav hrefs, the toast links **and their
  labels**, and the **server-side landing/redirect targets** — the arranging page's
  `FROM_LANDING_ROUTES` values and its `?? '/replacement-home-ui'` fallback, which is where a
  post-submit redirect and the blocked-request toast land. The home page's old `requestHref()` is
  deleted outright.
- **Deliberately frozen — do not "fix" these:** the seven `link:` fixtures in
  `public/js/mock-data.js`, the strict `===` on the stored link in
  `resources/views/partials/notif-row.blade.php:96`, `NotificationEmitter.php:64,88`, and the
  off-by-default `ui-design-templates` mock copy. A stored notification keeps working **through the
  alias**; changing any of those is a failing change, not a passing one.

**S3.1** all three addresses render the same page with the same rows
**S3.2** `?notif-hl=r12&view=request` survives on all three (no redirect, no lost query)
**S3.3** a stored notification link that still says `/my-request-history-ui` opens the page and
highlights the right row
**S3.4** every navigation producer listed above resolves to `/my-replacements`, and the four frozen
consumers are **unchanged** (they still say the old URL and still work)

## R4 — One nav item, one name (M3)

The page is called **My Replacements** — in the browser title, the page heading and the navigation.
The lecturer's two replacement items in the nav become **one**.

**S4.1** the browser title matches `/My Replacements/`
**S4.2** the heading reads `My Replacements`
**S4.3** the lecturer nav has one item labelled `My Replacements`, pointing at `/my-replacements`
**S4.4** the arranging page still highlights that same nav item

## R5 — Seven columns, in this order (M4, M11)

`Course Code & Name` · `Cohort` · `Original Class` · `Conflict Reason` · `Requested Replacement` ·
`Request Status` · `Action`.

- **Cohort is its own column**, one cohort per line (a `<br>` between them), and it is **not
  sortable** — a row with several cohorts has no single value to sort by.
- **Original Class** carries the date, the day, the week, how many days are left, the time with its
  duration, and the venue.
- The page says **Course**, never Subject (M11).
- There is no checkbox column, no separate venue column, no separate students column.

**S5.1** the table has exactly seven headers, in that order
**S5.2** a two-cohort row shows both codes on two lines in the Cohort column
**S5.3** clicking the Cohort header sorts nothing (no arrow, no reorder)
**S5.4** no row carries a checkbox

## R6 — The Action cell is one icon, and the badge is not a door (M6)

Three states, icon only:

| Row state | Icon | Meaning |
|---|---|---|
| needs arranging (none / rejected / cancelled) | rotate | arrange a replacement |
| pending | ban (tinted `--color-error`) | cancel this request |
| approved / completed | eye | open the details |

- The **status badge is not clickable**; the whole row is the door.
- The eye stays as the visible hint that details exist.
- Every icon carries a tooltip and an accessible name.

**S6.1** a pending row shows the ban icon and its pop-up offers **no** arrange button
**S6.2** an approved row shows the eye, and clicking it opens the detail pop-up
**S6.3** clicking the `Approved` badge does not leave the page (it opens the pop-up, like the row)
**S6.4** clicking the row (not an icon) opens the detail pop-up
**S6.5** the badge element is not an `<a>`
**S6.6** an unarranged row's rotate icon opens the arranging page for **that** class and week
(`from=replacement-home`)

## R7 — The pop-up: fixed facts, then three tabs (M7, M8)

Clicking a row opens **one** pop-up (`#quickViewModal`) showing:

- a fixed strip of facts — cohorts, students, conflict — always visible, above the tabs;
- **Original Class Schedule** — the class as timetabled;
- **Proposed Replacement Schedule** — the requested slot. **This tab is absent when nothing was
  proposed**;
- **Request Status (<short status>)** — the status badge, one plain sentence, the **Rejection
  Reason** (only when rejected), and the **Remark** (only when there is one).

`Remark` and `Rejection Reason` are **two different rows** (`remarks` and `rejection_reason`). There
is no history block, no timeline, no per-request panel.

- Both schedule tabs print `room_code — room_name`, and when a venue has **no** name they print the
  code **alone** — never `CODE — ` with nothing after it.
- Both print time the same way (12-hour, `~`) and duration in words.

**S7.1** a never-arranged row's pop-up has two tabs (no Proposed tab), and the status tab reads
`No replacement has been proposed for this class yet.`
**S7.2** a pending row's pop-up has three tabs, and the third is `Request Status (Pending)`
**S7.3** a rejected row with both fields shows a Rejection Reason row **and** a Remark row
**S7.4** an approved row with no remark shows no empty Remark row
**S7.5** the status tab contains exactly: the badge, one sentence, optionally Rejection Reason,
optionally Remark — and nothing else (no per-request list, no timeline, no history)
**S7.6** the fixed strip shows the row's cohort codes, its total student count and its conflict badge
**S7.7** a venue whose `room_name` is empty prints its code alone in both schedule tabs

## R8 — Five cards, following the filters (M9)

`Classes to Arrange` · `Class Hours to Replace` · `Pending Requests` · `Approved Requests` ·
`Rejected Requests`. The values follow the filters, not the whole page.

**S8.1** the five cards exist with those labels
**S8.2** typing a search narrows every card at once
**S8.3** a filter that leaves 2 needs-action rows and 1 handled row shows: `Classes to Arrange = 2`,
`Class Hours = those 2 rows' durations`, `Pending/Approved/Rejected = their own counts`, and 3 rows
in the table — one countable fact per card, from one filtered set
**S8.4** `Class Hours to Replace` excludes the hours of **handled** rows (pending, approved,
completed) and **includes** rejected and cancelled ones — those still need arranging

## R9 — Reuse, never a second version (M10)

The page uses the app's existing pieces for: the `data-tip` singleton, the **Confirm Cancellation**
pop-up for every destructive action, the phone card view, the filter chips, the rows-per-page control,
the pagination bar, the week selector, the summary cards, the pop-up tabs and the detail rows.

- The **copy-email** affordance (`📋`, clickable address, shared toast) is part of the same rule even
  though **this page prints no email** (there is no requester block). The shared contract is not
  modified, and no second copy of it may be introduced here.

**S9.1** the phone card view shows the same rows as the table and opens the same pop-up
**S9.2** a cancel from the row icon and a cancel from the pop-up footer both show the same Confirm
Cancellation pop-up
**S9.3** the new view contains no email-copy implementation of its own, and no second tooltip
singleton, confirm pop-up, chip, pagination or summary component

## R10 — Cancelling is one request at a time, through the one write path (M12)

Bulk cancel is gone: no checkbox, no bulk bar, no "Cancel Selected". A pending request is cancelled
from its row icon or its pop-up, always after the confirmation, and always through
`CancelPendingRequest` — never a local status write.

- The **Cancel Request** footer button appears **only** while the row is pending.

**S10.1** a pending row can be cancelled after confirming; the row stays and reads `Cancelled by YOU`
**S10.2** an approved / completed row offers no Cancel button anywhere
**S10.3** after a cancel the row's Requested Replacement shows what the database now knows — the
anchor's 30-minute range after the slot release — not an assumed value
**S10.4** the cancel writes one audit row and releases the slot (the existing action's behaviour,
unchanged)

## R11 — Search finds cohort codes too (M13)

One search box matches the course code, the course name **and** the cohort codes — ignoring the
spaced convention, so `RSD3(S1)G2` finds `RSD3 (S1) G2`.

**S11.1** searching `BMIT` finds the course
**S11.2** searching `RSD3(S1)G2` finds the row showing `RSD3 (S1) G2`

## R12 — Sorting shows one arrow, and never breaks the groups (M14)

Clicking a sortable header sorts that column and shows `▲`/`▼` on **that** header only. The order
inside the needs-action group is sorted; the group itself stays first.

**S12.1** at load, `Original Class` is the active sort (▲) and no other header carries an arrow; after
clicking `Conflict Reason`, only that header carries one
**S12.2** clicking `Original Class` puts the **latest** needs-action date first; clicking it again puts
the **earliest** first — the comparator really runs
**S12.3** descending order does not move an arranged row above an unarranged one

## R13 — The toolbar count yields below 1500 px (M15)

`Showing N of M classes` is hidden below 1500 px because the pagination bar already reports it. The
element is **hidden, not removed**: it is present in the page at both widths.

**S13.1** at 1440 px the count is not visible, and the pagination bar still says `Showing 1-10 of N`
**S13.2** at 1600 px the count is visible

## R14 — Arriving from a notification shows the row (M16)

Opening the page with `?notif-hl=r<id>`:

- if the row is hidden **only** by `Exclude COMPLETED Status`, the toggle is unticked and the row is
  shown;
- if any other filter hides it, nothing changes and the page says the row is hidden by the current
  filters;
- if it is visible, the page goes to it and it pulses once.

`&view=request` (the "View Full Request" button) additionally **opens** that row's pop-up — looked up
in the page's data, not in the visible table.

**S14.1** arriving for a completed row with the toggle on shows the row and unticks the toggle
**S14.2** arriving for a row hidden by a status filter changes nothing and toasts
**S14.3** the bell's arrival only jumps and pulses (no pop-up)
**S14.4** "View Full Request" opens the pop-up **even when a saved filter currently hides that row**

## R15 — The filters, and two reset laws (M17)

Search · week navigator · status select (`All` · `Need to Arrange` · `Pending` · `Approved` ·
`Rejected` · `Cancelled` · `Completed`) · `Exclude COMPLETED Status` (on by default) ·
`Reset Filters`.

- **Reset Filters** restores the page defaults — the exclusion goes back **on**.
- **Clear all** (in the chip bar) shows everything — the exclusion goes **off**.
- Rows group needs-action first, then handled.
- The chosen filters are remembered across a reload, so an arrival behaves the same next time.

**S15.1** Reset after clearing everything puts the exclusion back on
**S15.2** Clear all leaves the exclusion off and lists completed rows
**S15.3** the chip bar names every active filter and removes each one
**S15.4** setting a status filter, then reloading, leaves it applied

## R16 — Nothing on the page is wrong, and nothing is invented (the user's bar)

Every value on the page has exactly one source (§6b), and the page never shows an absence that is
not real.

- The status is derived in one place. A request whose slot was released (cancelled or rejected)
  still prints the **anchor's** range — the only surviving evidence — never a dash.
- A request's remark and its rejection reason are separate values; neither is printed when empty,
  and neither is printed as `null`.
- The page's reads do not grow with the number of rows (no per-row query).
- After a cancel, the row shows what the database says, because the page re-reads it.
- A row with no request still has a complete, non-empty status description.
- The JSON API and the badge agree, because both come from the same helper.

**S16.1** a **rejected** 2-hour arrangement prints the anchor's range (`14:00 – 14:30`), never `—`
**S16.2** a rejected request with a rejection reason and no remark shows the rejection reason and
**no Remark row** (the Status Description is still there, as S16.5 requires)
**S16.3** rendering the page with 1 request and with 4 **approved** requests that have junction spans,
placed on 4 of the same occurrences (so the row count is equal and only the request count differs),
issues the same number of queries, and the junction table is read **exactly once**
**S16.4** after a cancel, the badge, the Requested Replacement and the available action all match
the database's new state
**S16.5** every row's pop-up has a non-empty Status Description, including a never-arranged row
**S16.6** the API's `status` for a request matches the status behind that row's badge (same helper,
one derivation)

---

## Retired behaviour (must be gone, not hidden)

- the request-history table, its bulk action bar, its `Quick Cancel` and `Requested At` columns;
- the home page's badge link (`requestHref()`), the `#modalOverlay` shell, the urgency column, the
  venue and students columns;
- `MyRequestHistory` as a component and `my-request-history.blade.php` as a view;
- the page title `Replacement Arrangement` and the nav item `Request History`;
- the description sentence "click a status badge to open its request" — **in the header and in the
  tips block** (`replacement-home.blade.php:194` and `:202`).

**S17.1** `/my-request-history-ui` does not render a request-history table (it renders the merged
page)
**S17.2** no page in the app offers a bulk cancel for replacement requests
**S17.3** the nav has no `Request History` item for a lecturer
**S17.4** the page has no `#modalOverlay` element
**S17.5** neither the header nor the tips block contains the phrase "opens that request" or
"click a status badge"
