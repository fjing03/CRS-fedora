# Explore brief — merge the two replacement pages into one table

> 2026-11-03. Explore only: **no proposal, no code**. This is the review baseline
> for the proposal and the script for the SV conversation.
>
> **The block is cleared** (2026-11-03). Both prerequisite lanes have landed:
> `replacement-home-status-badges` (`a16727c` feat, `2e58547` apply/verify) and
> `request-completed-status` (`e2b2f4c` feat, `7d2bb9a` apply/verify — the
> `effectiveStatus()` helper, so `completed` really exists in the payload).
> Verified before proposing: `ReplacementRequestMapper::effectiveStatus()`
> returns `completed` and `ReplacementHome.php:139-153` already consumes it.
> Propose is unblocked.

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
The modal explains that status; the request's own trail stays on its own record.**

## 4. Mapping — every dimension

**Row identity:** `(class_session_id, week_number)` — the same key the DB already
uses for its one-active-request guard.

**Status set and precedence** (newest request = greatest `submitted_at`, tie-break `id`):

| Newest request | Status shown | Row group |
|---|---|---|
| none | **Need to Arrange** | needs action |
| `pending` | **Pending — Waiting for Approval** | handled |
| `approved` | **Approved** | handled |
| `rejected` | **Rejected** | needs action |
| `cancelled` | **Cancelled** | needs action |
| `completed` (lane 2) | **Completed** | handled |

The **Action cell never repeats the status** (user decision, 2026-11-03): a
rejected or cancelled row shows only **Arrange Replacement**. Its state is the
Status badge, in the Status column — one fact, one place.

**The status badge is not clickable** (user decision, 2026-11-03). Any click on the
row opens the pop-up, so a badge link was a second door to the same room — the live
page's `badge-link` anchor (a real `href` to the request's record, kept for keyboard
and middle-click) is dropped here. The badge is a plain `<span class="badge …">`;
the row's `tr` carries the click.

The **eye stays** for approved and completed rows anyway (same decision): an empty
Action cell would be honest but reads as broken to a nervous user, and the eye is the
only visible hint that a row has details behind it.

**A pending row's action is Cancel** (user decision, 2026-11-03). Before that it
was the ghost eye — but a pending request has nothing to arrange, and the row click
already opens the same modal, so the eye was a duplicate of the row. Cancel is the
only thing left to do, and it is the only destructive icon in the table, so it keeps
the ghost box (the column stays quiet) and takes `--color-error` on the icon. Hover
tooltip and `aria-label` both read "Cancel this request". Approved and completed
rows keep the ghost eye unchanged.

Risk, stated in the brief on purpose: one click in a dense table can withdraw a
request the lecturer waited days for. The icon must go through the existing confirm
modal, never fire on click. The footer's `Cancel this request` button carries the
same ban mark so the icon and the word are learned together.

`completed` must be handled by the generic "not rejected/cancelled" rule even
while lane 2 is pending, so the code cannot break on first sight of one.

**Columns** (7, proposed — no checkbox, no separate venue/students/days columns):

| # | Column | Source | Notes |
|---|---|---|---|
| 1 | Course code & name | both pages | `.cell-code` + `.cell-name` + type label |
| 2 | **Cohort** | both pages | **its own column** (user decision, 2026-11-03): plain text, **one cohort per line** (`<br>` between them) — `RSD2 (S1) G2` / `RSD2 (S1) G3`. No icon, no separator glyph; identical to what the live home row already prints (`ui-common.js:2503`: `c.cohorts.join('<br>')`). **Not sortable**, which is the live My Requests law (`my-request-history.blade.php:527`: "multi-cohort rows have no single value"). Sits **between** the course and the original class: you read *what* it is, *who* it is for, then *when* it is |
| 3 | Original class | both pages | **one cell**: date · day (Week N) **with Days Left beside it on the same line** (wraps directly under the date when the column is tight) / start–end (duration) / **pin icon + room**. The cohorts line is **gone** from here — it moved to its own column |
| 4 | Conflict Reason | home (extended) | one badge, two shapes: **`Class Cancelled — <reason>`** in neutral grey for every non-holiday reason; **`Public Holiday — <holiday name>`** in error red. The holiday name is the calendar label from the `holidays` rows ("Deepavali Holiday (In Lieu)", "Christmas Day", …) |
| 5 | Requested replacement | history | **mirrors the Original Class design**: date · day (Week N) / start–end (duration) / **pin icon + room**; `—` when there is no request |
| 6 | Request Status | new | badge labels (user decision, 2026-11-03): **`Approved`** · **`Rejected`** · **`Pending — Waiting for Approval`** — the actor was dropped because on this page the reader *is* the lecturer, so "by PL" said the same thing on every row. Unchanged: **`Cancelled by YOU`** (that actor is not obvious — the lecturer can also be cancelled *by* a PL in other flows), **`Completed — <n> days ago`**, and **`Need to Arrange`** (**outlined**, not filled — see below). **See Q3** on whether the badge is also a shortcut |
| 7 | Action | both | **icon-only, three states**: **rotate = arrange** (need to arrange, rejected, cancelled) · **ban = cancel this pending request** (pending only, ghost box tinted `--color-error`) · **eye = open the details pop-up** (approved, completed). Full wording in the tooltip (`data-tip`) plus an `aria-label`. Never repeats the status |

**Search covers cohorts too** (user decision, 2026-11-03): the one search box matches
course code, course name **and any cohort code on the row**, so a lecturer can type a
group and get its classes without a second filter. The comparison ignores the app's
spaced cohort convention — `RSD3(S1)G2`, `rsd3 (s1) g2` and `RSD3` all find
`RSD3 (S1) G2` — because the spaces are cosmetic, not data.

**Sort arrows are reused, not invented** (user decision, 2026-11-03): the active
column's header gets the app's own `<span class="sort-arrow">` — `▲` ascending, `▼`
descending, `.sort-arrow` from `theme.css:1137`, emitted by the same `innerHTML =
label + arrow` shape as `ui-common.js:1586-1592`. Only the active column shows one;
a non-sortable column (Cohort, Action) never does. Direction never reorders across the
needs-action group boundary — the group split is applied first, direction sorts inside
each group (badges lane D8).

The column is called **Request Status**, not "Status": on this page "status" alone
is ambiguous (class status vs request status), so the label names the thing.

The badge had to say **who decided** in earlier drafts (`Approved by PL`,
`Rejected by PL`, `Cancelled by YOU`). That actor is now dropped from `Approved`
and `Rejected` (user decision, 2026-11-03): the reader of this page *is* the
lecturer, so "by PL" was the same two words on every row and earned nothing.
`Cancelled by YOU` keeps its actor, because there the actor is not obvious — a
cancellation can be the lecturer's own or forced by a PL elsewhere. `Completed`
carries a **three-word time qualifier** instead — `Completed — 3 days ago`,
`Completed — 1 day ago` — computed from the replacement date, so the badge answers
"how long ago did this run?" without opening anything.

`Need to Arrange` is an **outlined** pill (transparent background, light-primary
text and border), not a filled one. It marks the *absence* of a request, so it must
not read as another request status — and above all it must never be mistaken for
`Rejected`, which is the only other badge that used to be red. Filled =
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
COHORTS RSD2 (S1) G2, RSD2 (S1) G3 · STUDENTS 34
CONFLICT       [Class Cancelled — Medical Leave]
─────────────────────────────────────────────────────────────────────────────
[ Original Class Schedule ] [ Proposed Replacement Schedule ] [ Request Status (Pending) ]
```

The facts strip is **outside the tabs on purpose**: cohorts, students and the
conflict reason never change when you switch tabs, so repeating them in each panel
was noise. Course code, name and class type already live in the subtitle, so they
are not repeated.

**The status appears twice on purpose, in two jobs** (user decision, 2026-11-03):

- the **tab label** — `Request Status (Pending)`, `(Approved)`, `(Rejected)`,
  `(Cancelled)`, `(Completed)`, `(Need to Arrange)` — keeps the state readable from
  the other two tabs, because the tab bar never scrolls away;
- the **`Status` row inside the tab**, as a coloured badge, is where the
  red/amber/green actually lives — a label is monochrome, and the colour is what
  answers "do I need to do something?" at a glance.

The facts strip lost its `CURRENT STATUS` line, which was the third copy. The
parenthesis uses the **short** word: the badge's long form
(`Pending — Waiting for Approval`, `Completed — 3 days ago`) overflows the 594 px
tab bar, and the long text is in the table's badge where it matters.

| Tab | Contents |
|---|---|
| **Original Class Schedule** | **Class ID** · week · day · date · time · duration · venue |
| **Proposed Replacement Schedule** | **Request ID** · week · day · date · time · duration · venue. The tab is **hidden** when nothing is proposed (no request, or a request with no resolvable slot) — an empty tab that says "nothing yet" is a dead end |
| **Request Status (…)** | labelled with the status itself. Contains the **Status** badge · a **Status Description** row (one plain sentence about that status) · a **Rejection Reason** row, **only when the row is rejected** · a nullable **Remark** row. **Nothing else** — no timeline, no history block, no per-request panels |

Both schedules print the same way (user decision, 2026-11-03):

| Field | Format | Example |
|---|---|---|
| Day | full word | `Saturday` |
| Date | ordinal day, full month | `15th November 2025` |
| Time | one row, 12-hour, `~` between | `10:00 AM ~ 12:00 PM` |
| Duration | words, not `hr` | `1 hour` / `2 hours` |
| Venue | code + short room name | `B010 — Computer Lab` |

**The history block was removed outright** (user decision, 2026-11-03). Earlier
drafts carried a `HISTORY` section under `CURRENT`: one child tab per request
(`Request #R102.3`, `#R102.2`, `#R102.1`), newest first, each holding that request's
own rows (`Request ID`, `Status`, `Status Description`, `Proposed Replacement
Schedule`, `Your Submitted Timestamp`, `Decision by PL Timestamp`, `PL who Decided`,
`Remark`). All of it is gone — the child tabs, the per-request panel, the second tab
bar, the `CURRENT`/`HISTORY` subtitles, the divider, and the copy-email button that
sat beside the decider's address.

The reasoning: this page answers **"what is the state of this class"**, not "what
happened to this request". A request's own record already holds the trail, the
timestamps and who decided; repeating a growing list inside a table's modal made the
panel scroll and re-told a page that exists. What remains is one answer per tab.

The cost, stated plainly: **the modal no longer shows when a request was submitted,
when it was decided, or which PL decided it.** For an approved or completed class the
lecturer reads `Approved` / `Completed — 1 day ago` and nothing about who approved
it. If that proves to be the wrong trade, the cheap repair is to add `Your Submitted
Timestamp` and `Decision by PL Timestamp` back as rows of `Request Status` — not the
whole trail.

**`Remark` and `Rejection Reason` are two different rows** (user decision,
2026-11-03). The database has both: `remarks` (a free note anyone can leave) and
`rejection_reason` (the PL's words). A rejected row therefore shows *both* when both
exist — `Rejection Reason` in strong weight because it is the reason the class still
has to be arranged, `Remark` in normal weight because it is a note. This is also what
the live My Requests modal already does. Every other status shows `Remark` alone,
and only when it is filled (nullable column).

`Status Description` is generated from the status, so it never says the same thing as
either note: *"The proposed replacement is waiting for the PL to approve or reject
it."* / *"You cancelled this request, so the class is free to be arranged again."*

**Completed carries the count in its sentence** (user decision, 2026-11-03):
*"The replacement took place 1 day ago."* / *"… 3 days ago."* / *"… today."* — from
the replacement date, through the same `relativeDays()` the badge uses, so the two
can never disagree. It repeats the badge deliberately: the badge is a pill you skim,
the sentence is what you read, and the count is the whole point of the state.
It keeps its name — the home page's quick view uses the same label for *urgency*
prose, which is a separate page's problem: **rename that one later** (e.g. `Urgency`)
rather than inventing a second word here.

The old `What to do` row is gone: "Arrange a replacement for this class" repeated
what the badge already said.

The separate **Requests (N)** tab is gone. Tabs are named after their content —
`Original Class Schedule` / `Proposed Replacement Schedule` beat "Class"/"Schedule",
which were ambiguous on a merged page. Panels carry no inner heading; the tab is the
heading.

IDs are fabricated in the prototype (`#102` class, `#R102` request) but the shape is
the real one: a class session id and a request id, never the display row number.

The timeline rail is intentionally **not** included —
it is parked as broken (`task-02-replacement-history-timeline.md`).

**Modal footer** (bottom-right) — one action per state, mirroring the row:

| Row state | Footer right |
|---|---|
| no request, rejected, cancelled | **⟳ Arrange Replacement** (primary) |
| pending | **Cancel this request** (danger) |
| approved, completed | nothing — `Request Status` and `Proposed Replacement Schedule` are the whole story |

**Filters:** search (code/name) · week navigator (original-class week) · status
select (All · Need to Arrange · Pending · Approved · Rejected · Cancelled ·
Completed) · **Exclude `COMPLETED` Status** toggle, ON by default (completed lane
C4). The label is `Exclude <span class="info-keyword">Completed</span> Status`
(user decision, 2026-11-03) — `COMPLETED` is bold **and** uppercase through the
shared `.info-keyword` class, so it reads as the one word that matters in a toolbar
of four controls. The live pages still say `Exclude Completed`; the merged page is
the first to take the new label ·
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

**Sort hint** — under the toolbar, the live page's sentence with one word changed:
*Click **Course Code & Name** or **Original Class** to sort* (`.sort-hint`, italic
12 px, `on-surface-variant`, the two column names in `<strong>`). It is kept exactly as the
live page has it, even though the merged table makes **five** columns sortable —
naming two is a hint, not a contract, and the wording is already learned. The
alternative, if a reviewer minds the understatement, is one line:
*Click any column header to sort*.

**Summary cards** (5 — the theme's `.summary-bar` is already a 5-column grid, so no
CSS change). Labels render uppercase through `.summary-label`'s `text-transform`, so
the source copy stays natural-case:

| # | Label | Shown as |
|---|---|---|
| 1 | Classes to Arrange | `CLASSES TO ARRANGE` |
| 2 | Class Hours to Replace | `CLASS HOURS TO REPLACE` |
| 3 | Pending Requests | `PENDING REQUESTS` |
| 4 | **Approved Requests** | `APPROVED REQUESTS` |
| 5 | **Rejected Requests** | `REJECTED REQUESTS` |

Cards 4 and 5 lost `by PL` (user decision, 2026-11-03) for the same reason the
badges did: on a lecturer's own page the decider is always the PL, so the words
earned nothing and cost a line of label width. Counts follow the filters, not the
page. The first two are the classes side (what is still owed), the last three are
the requests side (what happened to them).

**Bulk cancel is removed** (user decision, 2026-11-03): no checkbox column and no
bulk bar. Cancelling happens **one request at a time**, from the row's ban icon or the
modal footer, and a pending row has exactly one request to cancel, so what is being
cancelled is never ambiguous. This also removes the
ambiguity of "what does ticking mean when a class has three requests".

**Page name and nav label:** **My Replacements** (user decision, 2026-11-03),
replacing the two lecturer nav items `Replacement Arrangement` (`/replacement-home-ui`)
and `Request History` (`/my-request-history-ui`) with one. The name matches
"My Timetable" in the same bar and says whose list it is; "Replacement Arrangement"
is left to mean the arranging flow at `/replacement-arrangement`, which this page
only links to. The nav bar loses an item rather than gaining one.

| | Value |
|---|---|
| Page title (H1) | **My Replacements** |
| Nav label | **My Replacements** |
| Canonical URL | `/replacement-home-ui` |
| Alias URL | `/my-request-history-ui` (must keep resolving) |
| `<title>` | `My Replacements — Class Replacement System` |

Description under the H1 (user's own wording, emphasis added):

> Every **CLASS** that needs a replacement, and every **REQUEST** you have made for
> it — in **ONE LIST**.

The emphasis uses the existing `.info-keyword` class — bold **and** uppercase from
`text-transform`, so the source copy stays natural-case and existing text assertions
still pass. No new CSS, no new colour.

**Implementation note (blocker, found while writing this down):** `ui-page-header`
renders the description with `{{ }}`, so HTML in it is **escaped** and would print as
literal `<span …>`. The page's own header therefore needs either an
`descriptionHtml` variable rendered with `{!! !!}` for this page only, or a switch of
the shared partial — `ui-summary-bar` already renders its descriptions raw, so the
pattern exists in the codebase.

### Reuse rules — do not build a second version of anything (review, 2026-11-03)

These were checked control by control against the live pages. Each one must reuse
the existing contract; the prototype got them wrong and is being corrected.

| Control | Reuse this | Never |
|---|---|---|
| Copy an email address | the `request-approval` contract: `📋` glyph after the address, **the address itself is clickable**, `data-tip="Click to copy email"`, feedback through the shared `toast.show('Email copied: …', null, 2000)` | a bordered icon button, an SVG glyph, or a tooltip that flips to "Copied" |
| Any hover hint | `data-tip` only — one shared `initDataTipTooltips()` singleton, viewport-clamped, supports `data-tip-pos="left"` and multi-line text | a per-element `::after` tooltip (clips inside `overflow:auto` grids and duplicates the singleton) |
| Column headers, ✕ close, ✕ chips | the live wording, and `data-tip="Close"` on the modal ✕ | silent headers / unlabelled ✕ |
| Toolbar count | the live `.result-count` line, "Showing 4 of 4 classes" — **hidden below 1500 px**, where the longer toggle label needs the room and the pagination bar already says `Showing 1-10 of 15` | a second, differently-worded counter |
| Sort hint | the live wording: *Click Course Code & Name or Original Class to sort* | inventing new hint text |
| "Nothing here" dash | a class backed by `--color-on-surface-variant` | a hand-written `style="color:…;opacity:.6"` |
| Confirmation of a destructive action | the existing **"Confirm Cancellation"** modal (`#cancelConfirmOverlay`, "… This action cannot be undone.") | a one-click cancel, or a browser `confirm()` |
| Mobile | the existing `.card-view` + `replacementHomeCard` shape, adapted to the merged fields (code+name · conflict badge · status badge · original class line · days left · cohorts) | a second card design |
| Icon-only Action column | **new** — there is no icon-only button component in `theme.css` (`.btn-icon` exists only in the mobile 48 px rule), so this column needs one real definition, used by both the rotate/ban/eye buttons | four one-off button styles, or inline width/height per button |

### `Course` stays — the `Subject` rename was considered and dropped (2026-11-03)

For about an hour this page said **Subject** (`Subject Code & Name`, `Search by
subject code or name…`, `Click Subject Code & Name …`). The user reversed it the
same day: **the app's word is Course**, everywhere, and the merged page follows.

Do **not** re-rename it. The argument for Subject was real — the detail modals
already print `Subject Code` / `Subject Name` (`my-request-history.blade.php:835`,
`request-approval.blade.php:968`), so the tables and the modals disagree — but the
tables win on volume: Course appears in nine user-facing places across five pages
plus `ui-common.js:2451`, and the merged page is a table. If the mismatch is ever
worth fixing it is the **modals** that change, in their own lane, with their own
brief — not this one.

**Icon-only Action is the one deliberate new thing.** The live Action cell is a text
button (`.btn-action` — "Arrange Replacement"). The merged page replaces it with
three icon buttons because six columns do not leave room for a 150 px text button
per row. That is the user's call (2026-11-03); everything else on this page reuses.

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

1. ~~**Page name / nav label**~~ — **decided: My Replacements** (2026-11-03).
2. **Which four summary cards** — the proposed set, or fewer?
3. **Is the status also a shortcut** to the modal, or is Status purely informational
   and only Action is clickable?
4. **Filter style** — the existing status select, or status chips?
5. **Post-submit redirect** — the merged page with the row highlighted (recommended)
   vs today's jump to My Requests.
6. **The guide block** — both pages have a "How to use this page" panel; keeping one
   is fine, keeping two is noise. Which items survive?
7. **Mobile card content** — which four facts fit the card, and does tapping a
   handled card open the modal or the request's own record?

## 7. The prototype

`ui-prototype/replacement-merged-preview.html` (repo parent, beside
`notif-manage-preview.html`) — a standalone static mock that links the live
`theme.css`, with 15 fabricated rows (4 of them Completed, with class and
replacement both in the past so the state is honest), a three-request trail in the
data that the modal no longer renders, the tabbed `DetailModal` shell (Original
Class Schedule · Proposed Replacement Schedule · Request Status), the
six-column layout (including the reused Conflict Reason badges and the icon-only
actions), the Active Filters chip bar, the full pagination bar, and live
search/status/completed filtering. There is **no checkbox column and no bulk bar**
(your 2026-11-03 call). It is a discussion instrument, **not** app code and not a
receipt.
