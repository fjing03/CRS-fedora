# Explore brief — merge the two replacement pages into one table

> 2026-11-03. Explore only: **no proposal, no code**. This is the review baseline
> for the later proposal and the script for the SV conversation.
>
> **Implementation is blocked** on two lanes: `replacement-home-status-badges`
> (approved, not implemented) and `request-completed-status` (brief written,
> depends on the first). Both assume the two-page shape; this merge rewrites it.
> Do not propose until they land.

## 1. Why we are looking at this

Two lecturer pages answer one question in two halves:

| Route | Component | One row = | Actually answers |
|---|---|---|---|
| `/replacement-home-ui` | `ReplacementHome` | a conflicted class occurrence | "what must I arrange?" |
| `/my-request-history-ui` | `MyRequestHistory` | a submitted request | "what did I submit, and what happened?" |

The user's drivers (2026-11-03, verbatim answers): **B** two similar nav labels,
**C** duplicated code, **D** a simpler demo, and above all **A** one continuous
story — *classes → I arranged one → here is its status, on one screen*.

Today the story breaks in a specific, fixable place: the home row **vanishes** the
moment a request exists (`ReplacementHome.php:77–79`). The approved badges lane
fixes that without a merge. The merge is about going further: one table.

## 2. Rejected approaches (do not revive)

| Approach | Why rejected |
|---|---|
| **Two tabs** ("To arrange" / "My requests") | Keeps both JS engines (so C is *not* fixed — one ~1,300-line blade), and the "one screen" story becomes a tab switch that the badges lane already delivers without a merge. Worst of both. |
| **Merge now**, before the badges/completed lanes | Invalidates two locked briefs (badges D2/D5/D6 and completed's `?notif-hl=`), re-opens an SV-approved frozen design, and touches 23 test files before a viva. |
| **Keep a small read-only "requests" view** | Pays the maintenance twice and contradicts "one table". |
| **One row per occurrence with no request trail** | Loses the rejected → pending history that already exists in the demo DB (session 38 / week 14; session 40 / week 14). |
| **One row per request** (today's history shape) with an "Arrange" column | Classes with no request disappear — the to-do list dies. |

## 3. The decision (2026-11-03)

**One table. One row per class occurrence. The row's status is its newest request.
The full request trail lives in the detail modal.**

## 4. Mapping — every dimension

**Row identity:** `(class_session_id, week_number)` — the same key the DB already
uses for its one-active-request guard.

**Status set and precedence** (newest request = greatest `submitted_at`, tie-break `id`):

| Newest request | Status shown | Row group |
|---|---|---|
| none | **Need to Arrange** | needs action |
| `pending` | **Pending — Waiting PL for Approval** | handled |
| `approved` | **Approved** | handled |
| `rejected` | **Rejected** | needs action |
| `cancelled` | **Cancelled** | needs action |
| `completed` (lane 2) | **Completed** | handled |

The **Action cell never repeats the status** (user decision, 2026-11-03): a
rejected or cancelled row shows only **Arrange Replacement**. Its state is the
Status badge, in the Status column — one fact, one place.

`completed` must be handled by the generic "not rejected/cancelled" rule even
while lane 2 is pending, so the code cannot break on first sight of one.

**Columns** (6, proposed — no checkbox, no separate venue/students/cohorts/days columns):

| # | Column | Source | Notes |
|---|---|---|---|
| 1 | Course code & name | both pages | `.cell-code` + `.cell-name` + type label |
| 2 | Original class | both pages | **one cell**: date · day (Week N) **with Days Left beside it on the same line** (wraps directly under the date when the column is tight) / start–end (duration) / **pin icon + room** / **users icon + cohorts** |
| 3 | Conflict Reason | home (extended) | one badge, two shapes: **`Class Cancelled — <reason>`** in neutral grey for every non-holiday reason; **`Public Holiday — <holiday name>`** in error red. The holiday name is the calendar label from the `holidays` rows ("Deepavali Holiday (In Lieu)", "Christmas Day", …) |
| 4 | Requested replacement | history | **mirrors the Original Class design, minus the cohorts line** (they are the class's own cohorts): date · day (Week N) / start–end (duration) / **pin icon + room**; `—` when there is no request |
| 5 | Request Status | new | badge **with the actor**: `Approved by PL` · `Rejected by PL` · `Cancelled by YOU` · `Pending — Waiting PL for Approval` · `Completed — <n> days ago` · `Need to Arrange` (**outlined**, not filled — see below); **see Q3** on whether it is also a shortcut |
| 6 | Action | both | **icon-only**: rotate = arrange, eye = view request. Full wording in the tooltip (`data-tip`) plus an `aria-label`. Never repeats the status; Cancel stays in the modal |

The column is called **Request Status**, not "Status": on this page "status" alone
is ambiguous (class status vs request status), so the label names the thing. The
badge also names **who decided** — the PL for approved/rejected, `YOU` for a
cancellation (the lecturer cancels their own request). That is not decoration: it
tells the lecturer who to chase. `Pending` has no decider yet, and `Completed` is
the calendar's verdict, so neither carries an actor. `Completed` instead carries a
**three-word time qualifier** — `Completed — 3 days ago`, `Completed — 1 day ago` —
computed from the replacement date, so the badge answers "how long ago did this
run?" without opening anything.

`Need to Arrange` is an **outlined** pill (transparent background, light-primary
text and border), not a filled one. It marks the *absence* of a request, so it must
not read as another request status — and above all it must never be mistaken for
`Rejected by PL`, which is the only other badge that used to be red. Filled =
a request exists; outlined = none does yet.

The Conflict Reason badge changes vocabulary on purpose (user decision, 2026-11-03):
the home page colours each reason separately (`.badge-annual-leave`,
`.badge-medical-leave`, …); the merged page reduces that to **two meanings** — a
cancelled class is neutral grey whatever the reason, and only a public holiday stays
red. The badge also says *which kind* of conflict it is, and a holiday names the
day itself, so the reason is readable without knowing the colour code. Those classes
are page-local CSS today (`replacement-home.blade.php:27–42`), so the merge must
define the new grey and share the holiday red rather than paste a second copy.

Four things left the table on purpose (user decisions, 2026-11-03): **Class Venue**
and **Cohort(s)** fold into the Original Class cell — each on its own icon-led line,
a pin for the room and the users glyph for the cohorts (the users glyph is the one
already used by the arrangement page's slot dropdown; no venue icon existed before,
so the pin is new) — **Days Left** also folds in, rendered exactly as the home page
renders it today: plain coloured text via `urgency-high` / `urgency-mid` /
`urgency-low` with `daysLeftLabel()`, **never a badge**, and positioned **on the
date line, to the right of the date** (it wraps directly below the date when the
column is narrow, so it never crowds the date); and **Students** is dropped
entirely. The student count is not lost: it still lives in the modal's **Class** tab.
Folding the two venues into different cells also makes the class's own room and the
requested replacement's room impossible to mix up.

**Sort:** needs-action group first, then handled; the chosen column sorts **within**
each group (badges lane D8). Default sort: original class date ascending.

**Detail modal** — reuses the app's `DetailModal` shell, with a **fixed facts strip**
between the subtitle and the tab bar, then three tabs:

```
Replacement Details
BMIT2013 · Web-Based Integrated Systems (Lecture)      ← subtitle: code · name (type)
COHORTS RSD2 (S1) G2, RSD2 (S1) G3 · STUDENTS 34 · CONFLICT [Class Cancelled — Medical Leave]
─────────────────────────────────────────────────────────────────────────────
[ Original Class Schedule ] [ Proposed Replacement Schedule ] [ Request Status ]
```

The facts strip is **outside the tabs on purpose**: cohorts, students and the
conflict reason never change when you switch tabs, so repeating them in each panel
was noise. Subject code, name and class type already live in the subtitle, so they
are not repeated.

| Tab | Contents |
|---|---|
| **Original Class Schedule** | **Class ID** · week · day · date · time · duration · venue |
| **Proposed Replacement Schedule** | **Request ID** · week · day · date · time · duration · venue. The tab is **hidden** when nothing is proposed (no request, or a request with no resolvable slot) — an empty tab that says "nothing yet" is a dead end |

Both schedules print the same way (user decision, 2026-11-03):

| Field | Format | Example |
|---|---|---|
| Day | full word | `Saturday` |
| Date | ordinal day, full month | `15th November 2025` |
| Time | one row, 12-hour, `~` between | `10:00 AM ~ 12:00 PM` |
| Duration | words, not `hr` | `1 hour` / `2 hours` |
| Venue | code + short room name | `B010 — Computer Lab` |
| **Request Status** | **CURRENT** subtitle · status badge · a divider · one plain sentence saying what that status means for this class · **HISTORY** subtitle · **one child tab per request** (`Request #R102.2`, `Request #R102.1`), newest first and newest selected, each panel holding Status, **Proposed Replacement**, **Submitted Timestamp**, **Decision Timestamp** and Note |

**Nested tabs, and when they are *not* used.** The history child tabs exist only
when there are **two or more** requests — a tab bar with a single tab is noise, so
one request renders its facts directly under `HISTORY`. This mirrors the app's own
DetailModal law (a tab bar only appears for 2+ categories). The trade-off is real:
it is a second tab bar inside a modal, so it is reserved for the one case where the
alternative — several stacked request blocks — makes the panel long enough to scroll.

Two subtitles carry the panel — `CURRENT` and `HISTORY` — and both are **14 px
(+2 over the 12 px detail labels), 700, uppercase, `on-surface` near-white**, so
they read as structure rather than as another data row.

The old `What to do` row is gone: "Arrange a replacement for this class" repeated
what the badge already said. In its place is one sentence of **description** that
depends on the status — e.g. *"You cancelled this request, so the class is free to
be arranged again."* / *"The proposed replacement is waiting for the PL to approve
or reject it."*

`Decided` became **`Decision Timestamp`** because bare "Decided" could be read as a
verdict ("is it decided?") rather than a time; it now mirrors `Submitted Timestamp`.

The separate **Requests (N)** tab is gone: its trail *is* the request's history, so
it sits inside Request Status where the status it explains is shown. Tabs are named
after their content — `Original Class Schedule` / `Proposed Replacement Schedule`
beat "Class"/"Schedule", which were ambiguous on a merged page. Panels carry no
inner heading; the tab is the heading.

IDs are fabricated in the prototype (`#102` class, `#R102` request) but the shape is
the real one: a class session id and a request id, never the display row number.

The timeline rail is intentionally **not** included —
it is parked as broken (`task-02-replacement-history-timeline.md`).

**Modal footer** (bottom-right) — one action per state, mirroring the row:

| Row state | Footer right |
|---|---|
| no request, rejected, cancelled | **⟳ Arrange Replacement** (primary) |
| pending | **Cancel this request** (danger) |
| approved, completed | nothing — the Requests tab is the whole story |

**Filters:** search (code/name) · week navigator (original-class week) · status
select (All · Need to Arrange · Pending · Approved · Rejected · Cancelled ·
Completed) · **Exclude Completed** toggle, ON by default (completed lane C4) ·
reset. Plus the **Active Filters chip bar** and the **pagination bar**, both
reused from My Requests verbatim, not redesigned:

- **Chips** — `#filterChips` renders *Status / Search / Exclude Completed* chips
  with an × each, plus **Clear all**. Two different "reset" laws are preserved:
  **Reset Filters** restores the page defaults (Exclude Completed back ON), while
  **Clear all** shows everything (toggle turned OFF).
- **The status select wears the week navigator's skin** — `.week-select` (36 px
  high, 13px/600, surface background, custom chevron), not `.filter-select`
  (40 px, 14px/500, surface-variant, native arrow). Both controls then read as one
  toolbar row instead of two different widgets.
- **Pagination** — `Rows:` 10 / 25 / 50 / All, `Showing X-Y of N`, and numbered
  page buttons with ‹ ›. The same markup and classes (`rpp-wrapper`,
  `rpp-select`, `pagination-bar`, `page-btn`).

**Summary cards** (5 — the theme's `.summary-bar` is already a 5-column grid, so no
CSS change): **Classes to Arrange** · **Class Hours to Replace** · **Pending
Requests** · **Requests Approved by PL** · **Requests Rejected by PL**. Counts
follow the filters, not the page. The first two are the classes side (what is still
owed), the last three are the requests side (what happened to them).

**Bulk cancel is removed** (user decision, 2026-11-03): no checkbox column and no
bulk bar. Cancelling happens **one request at a time from the detail modal**, where
the trail shows exactly which request is being cancelled. This also removes the
ambiguity of "what does ticking mean when a class has three requests".

**Page name and nav label:** open (Q1). One nav item replaces two.

## 5. Cross-module data flows

```
class_sessions (own, semester) ─┬─ class_exceptions ─→ reason (FR 2.16 enum)
                                ├─ holiday calendar ─→ 'Public Holiday' (D9 precedence)
                                └─ replacement_requests ─→ newest per occurrence
                                                             │
ReplacementHome::render() ─ payload.conflictedClasses ───────┘
                              (+ requestId / requestStatus after the badges lane)

ReplacementRequest ── ReplacementRequestMapper::classRow / replacementStrings
                   └─ request trail per occurrence (ordered submitted_at desc)

SubmitReplacementRequest ── creates pending ── NotificationEmitter
                                                  └─ link: /my-request-history-ui
ApproveRequest::releaseOriginal ── stamps official_event when no exception exists
applyNotifHighlight (ui-common.js:307, 150 ms) ── ?notif-hl=r<id>
flashRowPulse (:219–224, 1.3 s) ── the arrival pulse
```

**URL contract** (hard): `/my-request-history-ui` must keep resolving — it is used
by `NotificationEmitter.php:64,88`, `replacement-arrangement.blade.php:2241,2245`,
the badges lane (D2/D6) and the completed lane. After the merge it is an **alias**
of the merged page, and `?notif-hl=r<id>` narrows to that occurrence and pulses it.
`/replacement-home-ui` becomes the canonical URL.

**No schema change.** `uq_replacement_requests_active_block` (partial unique on
`(class_session_id, week_number) WHERE status IN ('pending','approved')`) already
guarantees one active request per occurrence, so the row's status is never
ambiguous.

**No demo-DB write.** The prototype uses fake data; nothing here touches a database.

## 6. Open questions for the proposal

1. **Page name / nav label** — "Replacement"? "My Replacements"?
2. **Which four summary cards** — the proposed set, or fewer?
3. **Is the status also a shortcut** to that request's trail (opens the modal at the
   trail), or is Status purely informational and only Action is clickable?
4. **Filter style** — the existing status select, or status chips?
5. **Post-submit redirect** — the merged page with the row highlighted (recommended)
   vs today's jump to My Requests.
6. **The guide block** — both pages have a "How to use this page" panel; keeping one
   is fine, keeping two is noise. Which items survive?
7. **Mobile card content** — which four facts fit the card, and does tapping a
   handled card open the modal or jump to the trail?

## 7. The prototype

`ui-prototype/replacement-merged-preview.html` (repo parent, beside
`notif-manage-preview.html`) — a standalone static mock that links the live
`theme.css`, with 15 fabricated rows (4 of them Completed, with class and
replacement both in the past so the state is honest), a rejected → pending trail,
the tabbed `DetailModal` shell (Status · Class · Schedule · Requests), the
six-column layout (including the reused Conflict Reason badges and the icon-only
actions), the Active Filters chip bar, the full pagination bar, and live
search/status/completed filtering. There is **no checkbox column and no bulk bar**
(your 2026-11-03 call). It is a discussion instrument, **not** app code and not a
receipt.
