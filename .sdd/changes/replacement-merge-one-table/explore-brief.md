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
| none | **Needs arrangement** | needs action |
| `pending` | **Pending** | handled |
| `approved` | **Approved** | handled |
| `rejected` | **Rejected** | needs action |
| `cancelled` | **Cancelled** | needs action |
| `completed` (lane 2) | **Completed** | handled |

The **Action cell never repeats the status** (user decision, 2026-11-03): a
rejected or cancelled row shows only **Arrange Replacement**. Its state is the
Status badge, in the Status column — one fact, one place.

`completed` must be handled by the generic "not rejected/cancelled" rule even
while lane 2 is pending, so the code cannot break on first sight of one.

**Columns** (7, proposed — no checkbox, no separate venue/students/cohorts columns):

| # | Column | Source | Notes |
|---|---|---|---|
| 1 | Course code & name | both pages | `.cell-code` + `.cell-name` + type label |
| 2 | Original class | both pages | **one cell, four lines**: date · day (Week N) / start–end (duration) / **pin icon + room** / **users icon + cohorts** |
| 3 | Conflict Reason | home | **the home page's reason badges, unchanged** (`.badge-annual-leave`, `.badge-medical-leave`, `.badge-holiday`, `.badge-official-event`), placed immediately right of Original Class |
| 4 | Days left | home | `—` on handled rows (badges lane D7); sort uses the real date |
| 5 | Requested replacement | history | date · time · replacement room, `—` when none |
| 6 | Status | new | badge; **see Q3** on whether it is also a shortcut |
| 7 | Action | both | **icon-only**: rotate = arrange, eye = view request. Full wording in the tooltip (`data-tip`) plus an `aria-label`. Never repeats the status; Cancel stays in the modal |

The reason badges are **page-local CSS today** (`replacement-home.blade.php:27–42`),
not tokens in `theme.css`. The merge must share them (promote to `theme.css` or a
partial) rather than paste a second copy.

Three columns left the table on purpose (user decisions, 2026-11-03): **Class Venue**
and **Cohort(s)** fold into the Original Class cell — each on its own icon-led line,
a pin for the room and the users glyph for the cohorts (the users glyph is the one
already used by the arrangement page's slot dropdown; no venue icon existed before,
so the pin is new) — and **Students** is dropped entirely. The student count is not
lost: it still lives in the modal's **Class** tab. Folding the two venues into
different cells also makes the class's own room and the requested replacement's room
impossible to mix up.

**Sort:** needs-action group first, then handled; the chosen column sorts **within**
each group (badges lane D8). Default sort: original class date ascending.

**Detail modal** — reuses the app's existing `DetailModal` shell (title + subtitle,
`.modal-tabs` bar, one panel per category), exactly like the home quick view and the
history detail modal today:

| Tab | Contents |
|---|---|
| **Status** | status badge · what to do next · conflict reason · the requested replacement (or "none yet") |
| **Class** | subject code/name · class type · cohorts · students |
| **Schedule** | week · day · date · start/end/duration · venue |
| **Requests (N)** | the trail, newest first: one group per request with status, replacement slot, submitted, decided-by/at, note |

That is the home quick view's **Status · Class · Schedule** plus the one tab the
merge adds (**Requests**). The timeline rail is intentionally **not** included —
it is parked as broken (`task-02-replacement-history-timeline.md`). Actions
(Cancel the current pending request) stay in the modal footer.

**Filters:** search (code/name) · week navigator (original-class week) · status
select (All · Needs arrangement · Pending · Approved · Rejected · Cancelled ·
Completed) · **Exclude Completed** toggle, ON by default (completed lane C4) ·
reset. Filter chips are optional (Q4).

**Summary cards** (proposed 4, replacing home's 3 + history's 5):
Needs arrangement · Hours to cover · Pending · Approved. Counts follow the
filters, as both pages already do.

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
`theme.css`, with 8 fabricated rows covering every status, a rejected → pending
trail, the tabbed `DetailModal` shell (Status · Class · Schedule · Requests), the
seven-column layout (including the reused Conflict Reason badges and the icon-only
actions), and live search/status/completed filtering. There is **no
checkbox column and no bulk bar** (your 2026-11-03 call). It is a discussion
instrument, **not** app code and not a receipt.
