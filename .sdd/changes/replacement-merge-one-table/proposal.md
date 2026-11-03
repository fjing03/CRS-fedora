# Proposal — one replacement table for the lecturer

- **Lane:** `replacement-merge-one-table`
- **Created:** 2026-11-03
- **Source:** `.sdd/changes/replacement-merge-one-table/explore-brief.md` (explore, ~20 review rounds with the user) + the prototype `ui-prototype/replacement-merged-preview.html` (outside the repo, never staged)
- **Prerequisites — both satisfied:**
  - `replacement-home-status-badges` — `a16727c` (feat), `2e58547` (apply/verify)
  - `request-completed-status` — `e2b2f4c` (feat), `7d2bb9a` (apply/verify). Verified before writing this: `ReplacementRequestMapper::effectiveStatus()` returns `completed` and `ReplacementHome.php:139-153` already consumes it.
- **Prototype is a discussion instrument, not a receipt.** Every decision below was confirmed in it; the brief is the authority, the prototype is the picture.

## 1. Why

Two lecturer pages answer one question in two halves:

| Route | Component | One row = | Actually answers |
|---|---|---|---|
| `/replacement-home-ui` | `ReplacementHome` | a conflicted class occurrence | "what must I arrange?" |
| `/my-request-history-ui` | `MyRequestHistory` | a submitted request | "what did I submit, and what happened?" |

The lecturer arranges a replacement on the first page, then **loses sight of it** — the home row
disappears the moment a request exists, and the answer moves to a second page with a second nav
label, a second table, a second filter bar and a second copy of the same JS.

The user's drivers (2026-11-03): one continuous story — *classes → I arranged one → here is its
status, on one screen*; two similar nav labels; duplicated code; a simpler demo.

**What "one table" costs, stated up front:** the merged page keeps the *class* as the subject and
the *request* as its state. A class with three requests is one row, not three. The request's own
trail (every submission, who decided, when) therefore leaves this page — it stays on the request's
record, which is exactly what the user asked for when the HISTORY block was cut.

## 2. Locked decisions (user session, 2026-11-03 — not re-opened)

Numbered **M1–M12** for reference from `design.md` and `tasks.md`; no other lane uses the M prefix.

| # | Decision |
|---|---|
| **M1** | **One table, one row per class occurrence**, keyed `(class_session_id, week_number)`. The row's status is its **newest** request (greatest `submitted_at`, tie-break `id`). No request → the row still appears, as `Need to Arrange`. |
| **M2** | Canonical URL **`/replacement-home-ui`**; **`/my-request-history-ui` keeps resolving** (alias) — notifications (`NotificationEmitter.php:64,88`), `ReplacementArrangement::FROM_LANDING_ROUTES:79`, the post-submit redirect and `?notif-hl=` all point at it. |
| **M3** | Page title and nav label are **My Replacements**; the two lecturer nav items become **one**. "Replacement Arrangement" is left to mean the arranging flow (`/replacement-arrangement`). |
| **M4** | **Six columns:** Course Code & Name · Original Class (date · day (week) · days-left · time (duration) · venue · cohorts) · Conflict Reason · Requested Replacement · Request Status · Action. No checkbox, no separate venue/students/cohorts/days-left columns. |
| **M5** | **Status labels:** `Need to Arrange` (outlined, never filled) · `Pending — Waiting for Approval` · `Approved` · `Rejected` · `Cancelled by YOU` · `Completed — <n> days ago`. The actor survives only where it is not obvious (`Cancelled by YOU`). |
| **M6** | **Action is icon-only, three states:** rotate = arrange (need to arrange, rejected, cancelled) · ban = cancel this pending request (pending, ghost box tinted `--color-error`) · eye = open the pop-up (approved, completed). The **status badge is not clickable** — the row click is the door — but the eye stays as the visible hint that details exist. |
| **M7** | **Modal:** fixed facts strip (cohorts, students, conflict) above three tabs — `Original Class Schedule` · `Proposed Replacement Schedule` (hidden when nothing is proposed) · `Request Status (<status>)`. **No HISTORY block, no child tabs, no timeline.** Two rows only inside the request tab: `Status` badge + `Status Description`, plus `Rejection Reason` (rejected only) and nullable `Remark`. |
| **M8** | `Remark` = `remarks` (nullable, row disappears when empty) and `Rejection Reason` = `rejection_reason` — **two rows, not one field**. `Status Description` keeps its name here; the home page's urgency row gets renamed later. The Completed sentence carries the count (`The replacement took place 1 day ago.`). |
| **M9** | **Five summary cards**, labels: Classes to Arrange · Class Hours to Replace · Pending Requests · **Approved Requests** · **Rejected Requests**. Counts follow the filters, not the page. |
| **M10** | **Reuse, never a second version:** the copy-email contract (`📋`, clickable address, shared toast) · the `data-tip` singleton · the **Confirm Cancellation** modal for every destructive action (row icon and footer button) · the existing `.card-view` on phones · `.filter-chips`, `.rpp-*`, `.pagination-*`, `.week-select`, `.summary-*`, `.modal-tab`, `.detail-row` as they are. |
| **M11** | The merged page says **Course**, like the rest of the app. The `Course` → `Subject` rename was considered on 2026-11-03 and **dropped the same day** — do not re-open it here. |
| **M12** | **Bulk cancel is removed** — no checkbox column, no bulk bar, and the history page's `Quick Cancel` / `Requested At` columns go with the page. Cancelling is one request at a time, through the confirm modal. |

## 3. In scope

| # | Surface | Change |
|---|---|---|
| 1 | `app/Livewire/ReplacementHome.php` | becomes the merged page: keeps its conflicted-class query, gains the newest request's replacement/remark/rejection fields in the payload |
| 2 | `app/Services/ReplacementRequestMapper.php` | one row-shaping path for the merged payload (replacement slot, `remarks`, `rejection_reason`, venue **name**); `effectiveStatus()` reused, not re-derived |
| 3 | `resources/views/livewire/replacement-home.blade.php` | rewritten table (6 columns), the modal (facts strip + 3 tabs), the Action cell, the cards, the toolbar; keeps `Data-tip`, `DetailModal` shell, chips, pagination |
| 4 | `routes/web.php` + `app/Livewire/MyRequestHistory.php` | `/my-request-history-ui` resolves to the merged page (alias), `MyRequestHistory` retires or becomes a thin forwarder |
| 5 | `resources/views/livewire/my-request-history.blade.php` | retired with the page it draws |
| 6 | `resources/views/partials/ui-nav-bar.blade.php` | two lecturer items → one (`My Replacements`, key `replacement-arrangement`) |
| 7 | `app/Support/Notifications/NotificationEmitter.php`, `ReplacementArrangement.php` | unchanged targets — the alias must keep working, so this is verification, not editing |
| 8 | CSS | `theme.css` gains the one missing component: an icon-only button (`.btn-icon` exists only inside the mobile 48 px rule) |
| 9 | tests | update the specs that pin history-only affordances; add the merged-page matrix; the 25/34 URL-level specs must stay green **through the alias** |

## 4. Out of scope (and why)

- **The request trail, timeline, per-request panels** — removed by M7/M12; the request's record keeps them.
- **Any DB write, migration, seed or demo-data change.** This lane reads only. The demo DBs are not touched (standing rule).
- **Any `Course` → `Subject` rename** (M11) — dropped by the user; the app's word is Course.
- **Bulk cancel anywhere else** — the PL side keeps its own.
- **Notifications, emails, bell rows** — no new event, no new copy.
- **The organisational-card / `.filter-select` / other lanes' work** in the tree.
- **Redesigning the mobile card** — M10 reuses `replacementHomeCard`; the field choice is a `design.md` decision, not a new component.
- **A new icon-only button style per state** — one component, three icons.

## 5. Rejected alternatives (from the explore brief — do not revive)

| Approach | Why rejected |
|---|---|
| Two tabs ("To arrange" / "My requests") | Keeps both JS engines, so the duplication driver is not fixed; the story becomes a tab switch the badges lane already delivers without a merge |
| Merge before the badges/completed lanes | Invalidated two locked briefs and would freeze against a payload that did not exist — now moot: both landed |
| Keep a small read-only "requests" view | Pays maintenance twice, contradicts "one table" |
| One row per request | Classes with no request disappear — the to-do list dies |
| Request-per-row with an Arrange column | Same failure from the other side: the class is the subject, not the request |
| Keep the HISTORY block in the modal | Duplicates a page that still exists, and makes the panel scroll; cut by the user |

## 6. Risks

| Risk | Mitigation |
|---|---|
| The alias silently breaks a notification or a redirect | `NotificationEmitter.php:64,88` and `FROM_LANDING_ROUTES:79` are verification targets in `tasks.md`; a feature test asserts both URLs render the merged page |
| 25 + 34 test files reference the two URLs | The alias keeps URL-level specs green; the history-only ones (bulk cancel, quick-cancel column, timeline) are enumerated in `design.md` and updated with the lane, never silently deleted |
| The payload grows a second source of truth (mapper vs component) | One shaping path (item 2); the component only selects and orders |
| `completed` and `requestStatus` are computed in two places | Reuse `effectiveStatus()`; no second rule |
| A destructive icon one click from a pending request | M10: the existing Confirm Cancellation modal, never a bare click |
| `pv-*` prototype classes leaking into the app | `design.md` maps every prototype class to a real token/class or to the one new component |
| Scope creep into the queued `Course` rename | M11 keeps it out; the list is in the brief |
| Demo-data temptation to "make Completed rows exist" | This lane reads only; the completed lane already derived it with no writes |

## 7. Sequencing

1. This lane: payload + mapper, then the view, then the route alias and nav, then tests.
2. Nothing else. The `Subject` rename is dead (M11).
3. The request-trail page (if the user ever wants it back) is a separate lane; nothing here blocks it.

## 8. Open questions carried into `design.md`

| # | Question | Working answer |
|---|---|---|
| Q2 | 5 cards or fewer? | 5 — the user reviewed and renamed two of them (M9) |
| Q4 | Status select or chips? | The existing status select in the week navigator's skin; chips are already the *Active Filters* bar |
| Q5 | Post-submit redirect target | The merged page with the row highlighted (`?notif-hl=` machinery already exists) |
| Q6 | Which guide-block items survive | One block, merged; decided in `design.md` |
| Q7 | Which facts fit the mobile card | Reuse `replacementHomeCard`; field list in `design.md` |
