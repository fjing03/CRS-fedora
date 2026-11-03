# Spec — replacement page merge (`replacement-merge-one-table`)

Capability: **replacement-page-merge**. Requirement deltas for this lane only; every other behaviour
of the two pages it replaces is either carried over unchanged or explicitly retired below.

The user's decisions M1–M17 (`proposal.md` §2) are the authority; `design.md` says how. This file
turns them into checkable requirements (`R`) and scenarios (`S`), in the user's language. A scenario
is a fact the apply round must be able to demonstrate — not a test file name (those are `design.md`
§8).

---

## R1 — One table, one row per class occurrence (M1)

A lecturer's replacement page lists **classes**, not requests. There is exactly one row per
`(class_session_id, week_number)`, and it appears whether or not a request exists for it.

- The row set is the lecturer's conflicted occurrences exactly as the current home page defines
  them: an exception or a calendar holiday for that cell. A request never adds a row and never
  removes one.
- Two requests on the same occurrence are still **one** row.

**S1.1** an occurrence with no request is listed, with the status `Need to Arrange`
**S1.2** an occurrence with three requests (rejected → cancelled → pending) is listed once
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

**S2.1** a rejected-then-pending occurrence shows `Pending — Waiting for Approval`
**S2.2** a completed row reads `Completed — 1 day ago` for a replacement yesterday
**S2.3** an approved row whose replacement is **today** reads `Approved` (not Completed)
**S2.4** the stored status is still `approved` after the page renders (nothing is written)

## R3 — One canonical address, two that still work (M2)

The page's address is **`/my-replacements`**. `/replacement-home-ui` and `/my-request-history-ui`
stay as **extra addresses for the same page** — not redirects, so a query string survives.

- Every live link in the app points at the canonical address.
- Stored notification links do **not** move: they are compared byte for byte, and they keep working
  through the aliases.

**S3.1** all three addresses render the same page with the same rows
**S3.2** `?notif-hl=r12&view=request` survives on all three (no redirect, no lost query)
**S3.3** a stored notification link that still says `/my-request-history-ui` opens the page and
highlights the right row
**S3.4** no live link in `app/`, `resources/` or `public/js/` names an old URL (the frozen
notification emitter and the off-by-default mock template excepted, by design)

## R4 — One nav item, one name (M3)

The page is called **My Replacements** — in the browser title, the page heading and the navigation.
The lecturer's two replacement items in the nav become **one**.

**S4.1** the browser title matches `/My Replacements/`
**S4.2** the heading reads `My Replacements`
**S4.3** the lecturer nav has one item labelled `My Replacements`, pointing at `/my-replacements`
**S4.4** the arranging page still highlights that same nav item

## R5 — Seven columns, in this order (M4)

`Course Code & Name` · `Cohort` · `Original Class` · `Conflict Reason` · `Requested Replacement` ·
`Request Status` · `Action`.

- **Cohort is its own column**, one cohort per line (a `<br>` between them), and it is **not
  sortable** — a row with several cohorts has no single value to sort by.
- **Original Class** carries the date, the day, the week, how many days are left, the time with its
  duration, and the venue.
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

**S6.1** a pending row shows the ban icon and no arrange button
**S6.2** an approved row shows the eye, and clicking it opens the detail pop-up
**S6.3** clicking the `Approved` badge itself does **not** navigate anywhere
**S6.4** clicking the row (not an icon) opens the detail pop-up
**S6.5** the badge element is not an `<a>`

## R7 — The pop-up: fixed facts, then three tabs (M7, M8)

Clicking a row opens **one** pop-up (`#quickViewModal`) showing:

- a fixed strip of facts — cohorts, students, conflict — always visible;
- **Original Class Schedule** — the class as timetabled;
- **Proposed Replacement Schedule** — the requested slot. **This tab is absent when nothing was
  proposed**;
- **Request Status (<short status>)** — the status badge, one plain sentence, the **Rejection
  Reason** (only when rejected), and the **Remark** (only when there is one).

`Remark` and `Rejection Reason` are **two different rows** (`remarks` and `rejection_reason`). There
is no history block, no timeline, no per-request panel.

- Both schedule tabs print `room_code — room_name`, falling back to the code when a venue has no
  name.
- Both print time the same way (12-hour, `~`) and duration in words.

**S7.1** a never-arranged row's pop-up has two tabs (no Proposed tab) and tab 2 reads
`No replacement has been proposed for this class yet.`
**S7.2** a pending row's pop-up has three tabs, and the third is `Request Status (Pending)`
**S7.3** a rejected row with both fields shows a Rejection Reason row **and** a Remark row
**S7.4** an approved row with no remark shows no empty Remark row
**S7.5** no pop-up contains a history block or a timeline

## R8 — Five cards, following the filters (M9)

`Classes to Arrange` · `Class Hours to Replace` · `Pending Requests` · `Approved Requests` ·
`Rejected Requests`. The values follow the filters, not the whole page.

**S8.1** the five cards exist with those labels
**S8.2** typing a search narrows every card at once
**S8.3** `Class Hours to Replace` counts only the classes that still need arranging
**S8.4** the card values and the table never disagree (one filtered list feeds both)

## R9 — Reuse, never a second version (M10)

The page uses the app's existing pieces for: the copy-email affordance (`📋`, clickable address,
shared toast), the `data-tip` singleton, the **Confirm Cancellation** pop-up for every destructive
action, the phone card view, the filter chips, the rows-per-page control, the pagination bar, the
week selector, the summary cards, the pop-up tabs and the detail rows.

**S9.1** the phone card view shows the same rows as the table and opens the same pop-up
**S9.2** a cancel from the row icon and a cancel from the pop-up footer both show the same Confirm
Cancellation pop-up
**S9.3** no second copy of the copy-email behaviour, the tooltip singleton or the confirm pop-up
exists in the new view

## R10 — Cancelling is one request at a time, through the one write path (M12)

Bulk cancel is gone: no checkbox, no bulk bar, no "Cancel Selected". A pending request is cancelled
from its row icon or its pop-up, always after the confirmation, and always through
`CancelPendingRequest` — never a local status write.

- The **Cancel Request** footer button appears **only** while the row is pending.

**S10.1** a pending row can be cancelled after confirming; the row stays and reads `Cancelled by YOU`
**S10.2** an approved / completed row offers no Cancel button anywhere
**S10.3** after a cancel the row's Requested Replacement shows what the database now knows (the
anchor-only range after the junction release), not an assumed value
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

**S12.1** after clicking `Original Class`, only that header carries an arrow
**S12.2** descending order does not move an arranged row above an unarranged one

## R13 — The toolbar count yields below 1500 px (M15)

`Showing N of M classes` is hidden below 1500 px because the pagination bar already reports it. It
stays in the page (hidden, not removed) above and below.

**S13.1** at 1440 px the count is not visible, and the pagination bar still says `Showing 1-10 of N`
**S13.2** at 1600 px the count is visible

## R14 — Arriving from a notification shows the row (M16)

Opening the page with `?notif-hl=r<id>`:

- if the row is hidden **only** by `Exclude COMPLETED Status`, the toggle is unticked and the row is
  shown;
- if any other filter hides it, nothing changes and the page says the row is hidden by the current
  filters;
- if it is visible, the page goes to it and it pulses once.

`&view=request` (the "View Full Request" button) additionally **opens** that row's pop-up.

**S14.1** arriving for a completed row with the toggle on shows the row and unticks the toggle
**S14.2** arriving for a row hidden by a status filter changes nothing and toasts
**S14.3** the bell's arrival only jumps and pulses (no pop-up)
**S14.4** "View Full Request" opens the pop-up

## R15 — The filters, and two reset laws (M17)

Search · week navigator · status select (`All` · `Need to Arrange` · `Pending` · `Approved` ·
`Rejected` · `Cancelled` · `Completed`) · `Exclude COMPLETED Status` (on by default) ·
`Reset Filters`.

- **Reset Filters** restores the page defaults — the exclusion goes back **on**.
- **Clear all** (in the chip bar) shows everything — the exclusion goes **off**.
- Rows group needs-action first, then handled.

**S15.1** Reset after clearing everything puts the exclusion back on
**S15.2** Clear all leaves the exclusion off and lists completed rows
**S15.3** the chip bar names every active filter and removes each one

## R16 — Nothing on the page is wrong, and nothing is invented (the user's bar)

Every value on the page has exactly one source (§6b), and the page never shows an absence that is
not real.

- The status is derived in one place; a request that has no slot **never** prints a dash if it did
  have one (a cancelled or rejected request still shows its slot from its anchor).
- A request's remark and its rejection reason are separate values; neither is printed when empty,
  and neither is printed as `null`.
- The page's reads do not grow with the number of rows (no per-row query).
- After a cancel, the row shows what the database says, because the page re-reads it.
- A row with no request still has a complete, non-empty status description.

**S16.1** a cancelled 2-hour arrangement shows its range, not a dash
**S16.2** a rejected request with a rejection reason and no remark shows only the rejection reason
**S16.3** rendering the page with 1 request and with 4 requests issues the same number of queries
**S16.4** after a cancel, the badge, the Requested Replacement and the Available action all match
the database's new state
**S16.5** every row's pop-up has a non-empty Status Description, including a never-arranged row

---

## Retired behaviour (must be gone, not hidden)

- the request-history table, its bulk action bar, its `Quick Cancel` and `Requested At` columns;
- the home page's badge link (`requestHref()`), `#modalOverlay`, the urgency column, the venue and
  students columns, the keyboard-shortcuts pop-up;
- `MyRequestHistory` as a component and `my-request-history.blade.php` as a view;
- the page title `Replacement Arrangement` and the nav item `Request History`;
- the description sentence "click a status badge to open its request".

**S17.1** `/my-request-history-ui` does not render a request-history table (it renders the merged
page)
**S17.2** no page in the app offers a bulk cancel for replacement requests
**S17.3** the nav has no `Request History` item for a lecturer
