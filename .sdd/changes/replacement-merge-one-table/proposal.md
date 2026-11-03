# Proposal — one replacement table for the lecturer

- **Lane:** `replacement-merge-one-table`
- **Created:** 2026-11-03
- **Source:** `.sdd/changes/replacement-merge-one-table/explore-brief.md` (explore, ~25 review rounds with the user) + the prototype `ui-prototype/replacement-merged-preview.html` (outside the repo, never staged)
- **Prerequisites — both satisfied:**
  - `replacement-home-status-badges` — `a16727c` (feat), `2e58547` (apply/verify)
  - `request-completed-status` — `e2b2f4c` (feat), `7d2bb9a` (apply/verify). Verified: `ReplacementRequestMapper::effectiveStatus()` returns `completed` and `ReplacementHome.php:151-157` already consumes it.
- **Prototype is a discussion instrument, not a receipt.** Every decision below was confirmed in it; the brief is the authority, the prototype is the picture.

## 1. Why

Two lecturer pages answer one question in two halves:

| Route | Component | One row = | Actually answers |
|---|---|---|---|
| `/replacement-home-ui` | `ReplacementHome` | a conflicted class occurrence | "what must I arrange?" |
| `/my-request-history-ui` | `MyRequestHistory` | a submitted request | "what did I submit, and what happened?" |

The lecturer arranges a replacement on the first page, then **has to go looking for it** on the
second: before the badges lane the home row *vanished* the moment a request existed (that lane fixed
it by giving the row a status), but the answer still lives on a second page with a second nav label,
a second table, a second filter bar and a second copy of the same JS.

The user's drivers (2026-11-03): one continuous story — *classes → I arranged one → here is its
status, on one screen*; two similar nav labels; duplicated code; a simpler demo.

**What "one table" costs, stated up front:** the merged page keeps the *class* as the subject and
the *request* as its state. A class with three requests is one row, not three. The request's own
trail (every submission, who decided, when) therefore leaves this page — and, after §3 item 6, **no
lecturer-facing page renders it at all**: the only surface that does today is `MyRequestHistory`,
which retires here, and the other request page is student-only. The trail is not lost — it is in
`replacement_requests` (`submitted_at`, `decided_at`, `approver_id`, `rejection_reason`, `remarks`)
and on the PL's approval page — but until a later lane builds a lecturer-facing record view, this
page's modal is the only place a lecturer reads *this request's* state. That is the trade the user
accepted when the HISTORY block was cut; it is written down here so it cannot be discovered as a
surprise afterwards.

## 2. Locked decisions (user session, 2026-11-03 — not re-opened)

Numbered **M1–M16** for reference from `design.md` and `tasks.md`; no other lane uses the M prefix.

| # | Decision |
|---|---|
| **M1** | **One table, one row per class occurrence**, keyed `(class_session_id, week_number)`. The row's status is its **newest** request (greatest `submitted_at`, tie-break `id`). No request → the row still appears, as `Need to Arrange`. |
| **M2** | Canonical URL **`/my-replacements`** (the page's own name). **Both old URLs keep resolving** — `/replacement-home-ui` and `/my-request-history-ui` become **extra URIs for the same component in the route table, not redirects**, so query strings survive (`?notif-hl=r<id>`, `?blocked=request-pending`, `?id=`). Every live link moves to the canonical; **stored** notification links do not (`NotificationEmitter.php:64,88`, `partials/notif-row.blade.php:96` compares them byte for byte, and the seven `mock-data.js` fixtures mirror them). `?id=` is **not** carried over: My Requests reads it today (`my-request-history.blade.php:1043-1057`) and that reader retires with the page, so its only producer moves to `?notif-hl=` (7b). Full sweep in the brief's URL contract. |
| **M3** | Page title and nav label are **My Replacements**; the two lecturer nav items become **one** (key `replacement-arrangement`). "Replacement Arrangement" is left to mean the arranging flow (`/replacement-arrangement`). `routes/web.php` may keep `'nav' => 'replacement-history'` on the alias entry — harmless, deliberately not "fixed" into a second highlight. |
| **M4** | **Seven columns, in this order:** Course Code & Name · **Cohort** · Original Class (date · day (week) · days-left · time (duration) · venue) · Conflict Reason · Requested Replacement · Request Status · Action. No checkbox, no separate venue/students/days-left columns. Cohort is its own column, **one cohort per line** (`<br>`, exactly what `ui-common.js:2503` already prints), not sortable (the live law: a multi-cohort row has no single value). |
| **M5** | **Status labels:** `Need to Arrange` (outlined, never filled) · `Pending — Waiting for Approval` · `Approved` · `Rejected` · `Cancelled by YOU` · `Completed — <n> days ago`. The actor survives only where it is not obvious (`Cancelled by YOU`). |
| **M6** | **Action is icon-only, three states:** rotate = arrange (need to arrange, rejected, cancelled) · ban = cancel this pending request (pending, ghost box tinted `--color-error`) · eye = open the pop-up (approved, completed). The **status badge is not clickable** — the row click is the door — but the eye stays as the visible hint that details exist. **The badge anchor (`requestHref()`) is therefore deleted**, which supersedes the badges lane's D2 receipt (see §6). |
| **M7** | **Modal:** the surviving shell is **`#quickViewModal`** (home's), not `#modalOverlay`; the phone card opens the same one. Fixed facts strip (cohorts, students, conflict) above three tabs — `Original Class Schedule` · `Proposed Replacement Schedule` (hidden when nothing is proposed) · `Request Status (<status>)`. **No HISTORY block, no child tabs, no timeline.** |
| **M8** | `Remark` = `remarks` (nullable, row disappears when empty) and `Rejection Reason` = `rejection_reason` — **two rows, not one field**. `Status Description` keeps its name here; the home page's urgency row gets renamed later. The Completed sentence carries the count (`The replacement took place 1 day ago.`). |
| **M9** | **Five summary cards**, labels: Classes to Arrange · Class Hours to Replace · Pending Requests · **Approved Requests** · **Rejected Requests**. Counts follow the filters, not the page. |
| **M10** | **Reuse, never a second version:** the copy-email contract (`📋`, clickable address, shared toast) · the `data-tip` singleton · the **Confirm Cancellation** modal for every destructive action (row icon and footer button) · the existing `.card-view` on phones · `.filter-chips`, `.rpp-*`, `.pagination-*`, `.week-select`, `.summary-*`, `.modal-tab`, `.detail-row` as they are. |
| **M11** | The merged page says **Course**, like the rest of the app. The `Course` → `Subject` rename was considered on 2026-11-03 and **dropped the same day** — do not re-open it here. |
| **M12** | **Bulk cancel is removed** — no checkbox column, no bulk bar, and the history page's `Quick Cancel` / `Requested At` columns go with the page. Cancelling is one request at a time, through the confirm modal, **always through `CancelPendingRequest`**. |
| **M13** | **Search matches cohort codes** as well as course code and name, ignoring the spaced cohort convention (`RSD3(S1)G2` finds `RSD3 (S1) G2`). One search box, no second filter. |
| **M14** | **Sort arrows reuse the app's own mark**: `<span class="sort-arrow">` with `▲`/`▼`, only on the active column, `.sort-arrow` from `theme.css:1137`, emitted the same way `ui-common.js:1586-1592` does. Direction sorts **inside** the needs-action group, never across it. |
| **M15** | The toolbar's `Showing N of M classes` count is **hidden below 1500 px** — the pagination bar already reports it, and the longer exclusion label needs the room. Above 1500 px it stays, because the live pages carry it. |
| **M16** | **The arrival-reveal law is inherited** (completed lane R5), because this page becomes the `?notif-hl=` destination: when the arriving row is hidden **only** by `Exclude COMPLETED Status`, untick it and show the row; when another filter hides it, keep the toggle and toast "hidden by your current filters". The saved-filter key (`mrh-filters`) must be reconciled with this page's own, or a stale saved filter is exactly how an arrival silently skips. |
| **M17** | **The filter/reset block is carried, not re-invented:** search · week navigator · status select (All · Need to Arrange · Pending · Approved · Rejected · Cancelled · Completed) · **`Exclude COMPLETED Status`** ON by default (completed lane C4) · **Reset Filters**. Two different reset laws stay distinct — **Reset** restores page defaults (exclusion back ON), **Clear all** shows everything (exclusion OFF). Rows group **needs-action first, then handled**; the chosen sort applies inside each group. |

## 3. In scope

| # | Surface | Change |
|---|---|---|
| 1 | `app/Livewire/ReplacementHome.php` | becomes the merged page: keeps its conflicted-class query, gains the newest request's replacement/remark/rejection fields in the payload, **and gains `cancelRequest(int $id): bool` that delegates to `app/Actions/CancelPendingRequest.php` — never a local `update(['status' => 'cancelled'])`**, because that action is what carries ownership + pending-ness guarding, the D6 slot release and the audit row. |
| 2 | `app/Actions/CancelPendingRequest.php` | **unchanged** — the merged page calls it exactly as `MyRequestHistory::cancelRequest` (`:117-120`) does today |
| 2b | `public/js/ui-common.js` — `HtmlBuilder.replacementHomeRow` (`:2481-2506`) | the 7-column reshape lands **here**, not in the blade: the blade delegates the whole row to this builder (`replacement-home.blade.php:518`). It keeps today's output as the fallback so the frozen `ui-design-templates` copies still render (`mock_fallback` is off by default, `config/app.php:29`) |
| 3 | `app/Services/ReplacementRequestMapper.php` | one row-shaping path for the merged payload (replacement slot, `remarks`, `rejection_reason`, venue **name**); `effectiveStatus()` reused, not re-derived |
| 3b | the modal footer's destructive button | `#cancelRequestBtn` keeps its id **and** its gate: rendered only when the row's status is `pending`, exactly as today (`my-request-history.blade.php:876`). `CancelPendingRequest` refuses anything else, so an ungated button could only ever fail |
| 4 | `resources/views/livewire/replacement-home.blade.php` | rewritten table (7 columns), the modal (facts strip + 3 tabs), the Action cell, the cards, the toolbar; **`#cancelConfirmOverlay` moves here from `my-request-history.blade.php:275`** (markup, `openCancelConfirm()`/`closeCancelConfirm()` and the double-submit guard); keeps `Data-tip`, `DetailModal` shell, chips, pagination |
| 4b | **DOM contract the rebuild must keep** | the table and its toolbar are **reshaped, not renamed**: `#tableHead` / `#tableBody`, `#searchInput`, `#resultCount`, `#cardView`, `#timetable`, `#toastBar`, `.col-code`, `.cell-code`, `.cell-name`, `.cell-type-label`; the modal's `#quickViewModal` (and its open state `.show`) / `#qvBody` / `#qvArrangeBtn` / `#qvArrangeLabel` / **`#cancelRequestBtn`** (the footer's destructive button — two specs click it by id). These are what the group-A specs read; keeping them is cheaper than re-learning them in a rewrite |
| 5 | `routes/web.php` | **three entries for one component**. Each carries `component => ReplacementHome::class`, an explicit `legacy` (the old home template — there is no merged legacy view) and a `nav` key: `replacement-arrangement` for the canonical, `replacement-history` for the alias. Same middleware, query string preserved, **not redirects**. **Trap:** `routes/web.php:88-93` falls back to `view($page['legacy'])` when `class_exists($page['component'])` is false — so if item 6 deletes `MyRequestHistory` while an entry still names it, the page **silently renders the frozen old view** (no 404), and the alias's specs would pass against it. All three entries are repointed in the same commit as item 6 |
| 6 | `app/Livewire/MyRequestHistory.php` + `resources/views/livewire/my-request-history.blade.php` | retired with the page they draw. If anything still needs the component, it stays as a forwarder that keeps `payload['requests']` and both cancel methods; that decision is `design.md` §2, but **no caller is left without an owner** |
| 7 | `resources/views/partials/ui-nav-bar.blade.php` | two lecturer items → one (`My Replacements`, href `/my-replacements`, key `replacement-arrangement`) |
| 7b | every other live navigation to the old URLs — **6 live files, 16 line occurrences** (+2 frozen consumers, `notif-row.blade.php:96` and `NotificationEmitter.php:64,88`, which are **not** touched) | updated to the canonical, in the same commit: `ReplacementArrangement.php:78,79,201` (return-after-arrange + fallback), `replacement-arrangement.blade.php:2241,2245` (post-submit toast + redirect), `ui-common.js:3071,3072,3079` (**the two URL values and the default only** — the `#routes` **keys** and every `from=` producer stay, because ~14 specs pass `from=replacement-home` and a renamed key would silently fall through to the default), `ui-common.js:4420,4423` (entry-guard toast links **and labels**), `ui-common.js:1292` ("View Full Request" — its `?id=` reader retires with My Requests, so the producer moves to `?notif-hl=r<id>`), `requestHref()` (deleted by M6). **Deliberately frozen:** `ui-design-templates/replacement-arrangement-UIdesign-template.blade.php:1916` keeps the old URL — it is the `mock_fallback` copy, off by default (`config/app.php:29`) |
| 7c | copy that names the old page | `ReplacementArrangement.php:1214` and `ui-common.js:4419,4422` say "My Request History"; they say **My Replacements** after this lane |
| 8 | `resources/views/partials/ui-page-header.blade.php` | gains `descriptionHtml` rendered with `{!! !!}` (or an equivalent escape-free path) so the locked H1 description keeps its `CLASS` / `REQUEST` / `ONE LIST` emphasis — today `:22` escapes it and the spans would print literally |
| 9 | CSS (`public/css/theme.css`) | **three** definitions, not one: (a) the **icon-only button** (`.btn-icon` exists only inside the mobile 48 px rule, `:3322-3332`); (b) a **neutral-grey conflict badge** replacing the ten page-local classes (`replacement-home.blade.php:27-59`); (c) an **outlined** `Need to Arrange` pill — `theme.css:1161-1183` has only filled variants |
| 10 | `?notif-hl=` targeting | the merged row is keyed by occurrence, the stored link carries `r<requestId>`: the row emits **both** ids (`data-id` occurrence, `data-request-id` request) and the highlight matcher accepts either — otherwise the pulse silently skips (`ui-common.js:330,356`). **`?notif-hl=` has two producers and they want different arrivals**: the bell appends it from `data-notif-target` (`ui-common.js:2135-2143`) and only jumps, while "View Full Request" opened the request's modal today (`my-request-history.blade.php:1043-1057`). One param cannot tell them apart, so "View Full Request" carries a second marker (`&view=request`) and the page opens the row's modal only when it is present. The bell keeps today's jump-and-pulse, so M16's toast is never answered by a modal showing the same record. **Precedence matters**: `tryFind` uses one `querySelector` (`:349`) and a selector list resolves by document order, so the matcher must try `tr[data-request-id]` **first**, then `tr[data-id]` — an occurrence id (`session*100+week`, `ReplacementHome.php:118`) could otherwise collide with a small request id |
| 10b | `public/js/ui-common.js` | **one additive change**: `applyNotifHighlight` (`:320-342`) gains the second selector. It is shared with `/request-approval-ui` and `/my-timetable-ui`, so the edit must leave both existing laws byte-for-byte — same match, same 14×250 ms retry, same silent skip |
| 10c | the merged view's cancel success path | the moved handler cannot be a cut-and-paste: the history version splices `mockRequests` and calls `renderTable()` (`my-request-history.blade.php:690-714`), while the merged table is built from `payload.conflictedClasses`. On success the page **re-reads the row's state from the server** (a Livewire re-render, or the row re-fetched), repaints the table, closes the overlay and toasts. It must **not** assume `cancelled` locally — the badge has to show what the database now says (§6b law 4) |
| 11 | tests | see §6 — a measured inventory, five buckets. `RouteGateMatrixTest.php:29-51` gains `/my-replacements` (and its docblock `:16`), or the URL the nav now points at is gated by nothing |
| 12 | changelogs | `page-changelogs/` entries for both pages + the nav |

## 4. Out of scope (and why)

- **The request trail, timeline, per-request panels** — removed by M7/M12; the request's record keeps them.
- **Any schema, migration, seed or demo-data change.** No column, no backfill, no write to the demo DBs. **This lane is read-only except one existing write path:** the lecturer cancelling their own pending request through `CancelPendingRequest` (§3 item 1) — that action already exists, already audits, and is called unchanged.
- **Bulking that write** — M12 removes the bulk bar and `cancelRequests()` with it.
- **The `Course` → `Subject` rename** (M11) — dropped by the user.
- **Notifications, emails, bell rows** — no new event, no new copy; the alias keeps the existing links working.
- **The organisational-card / `.filter-select` / other lanes' work** in the tree.
- **Redesigning the mobile card** — M10 reuses `replacementHomeCard`; the field choice is a `design.md` decision.
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
| Re-implement cancel inside the merged component | Bypasses `CancelPendingRequest`'s ownership/pending guard, D6 release and audit — the one write path cannot be re-invented |

## 6. Test impact (measured, then classified file by file)

Measured with `grep -rl` over `tests/`:

| Pattern | Code files (`.ts`/`.js`/`.php`) | All files |
|---|---|---|
| `replacement-home` | **28** | 32 |
| `my-request-history` | **19** | 23 |
| in both | 11 | 11 |

The "all files" column is larger only because of the parked `tests/Browser/*.py` pytest suites and
`MANUAL-TEST-CASES.md`; the pytest suites are legacy by standing decision and are not in scope.
Union = 28 + 19 − 11 = **36 code files**, and every one of them appears below.

Nothing here is "unchanged by default". Every file below is bucketed, and a bucket that is not A
needs work **in this lane**.

**A — URL / navigation only; green through the alias** (they visit a URL, or assert a route/nav/notif
fact, and never touch the elements M6/M7/M12 delete):

`arrangement-conflict-autoselect.spec.ts` · `arrival-grid-no-pin.spec.ts` ·
`arr-slot-original-venue-sync.spec.ts` · `cancel-class.spec.ts` · `clear-guards-cross-week.spec.ts` ·
`default-pin.spec.ts` · `e2e/api-wiring.spec.js` · `e2e/home.spec.js` ·
`e2e/smoke-replacement-home.spec.js` · `Feature/ReplacementHomeTest.php` · `Feature/RouteGateMatrixTest.php` (gains `/my-replacements`, §3 item 11) · `Feature/NavIdentityTest.php` ·
`Feature/NotificationsBridgeTest.php` · `nav-identity.spec.ts` ·
`notifications-hide-manage.spec.ts` · `notifications-island.spec.ts` ·
`notifications-island-testing-db.spec.ts` · `rearrangement-toolbar-tooltips.spec.ts` ·
`selection-budget-original-duration.spec.ts` · `session-arrival-pin-guard.spec.ts` ·
`type-labels.spec.ts` (green **provided** no requested replacement repeats the venue/time it pins —
it selects rows by `hasText(venue)` + `hasText(time)` `:32-36`, and M4 puts a second venue/time on the
same row) · `replacement-strip-anchor.spec.ts` (`from=replacement-home` `:12`, then arrangement-page
ids only) · `e2e/full-suite.spec.js:593-603` (it only waits for `#searchInput` and reads
`#resultCount` `textContent()`, which still returns text while M15 merely hides the node — **green, on
that caveat**)

**A″ — URL assertions the canonical rename reverses; the expected string is edited in this lane.**
The alias keeps a *link-followed* navigation green; it does **not** keep green an assertion whose
expected value is the URL that §3 item 7b moves:

| File:line | Asserts today | Moved by | Becomes |
|---|---|---|---|
| `Feature/ReplacementArrangementTest.php:643` | `assertRedirect('/replacement-home-ui?blocked=request-approved')` | `ReplacementArrangement.php:201` fallback | `/my-replacements?blocked=request-approved` |
| `Feature/ReplacementArrangementTest.php:723` | `assertReturned('/replacement-home-ui?blocked=request-pending')` | same | `/my-replacements?blocked=request-pending` |
| `arrangement-entry-guard.spec.ts:134` | toast link `href === '/my-request-history-ui'` | `ui-common.js:4420` | `/my-replacements` |
| `arrangement-entry-guard.spec.ts:144` | URL contains `/replacement-home-ui?blocked=request-approved` | `ReplacementArrangement.php:201` | `/my-replacements?blocked=request-approved` |
| `arrangement-entry-guard.spec.ts:231` | `waitForURL(/my-request-history-ui/)` | `replacement-arrangement.blade.php:2245` | `/my-replacements/` |
| `submission-conflict-refresh.spec.ts:84` | `waitForURL(/my-request-history-ui/)` | same | `/my-replacements/` |
| `submission-conflict-refresh.spec.ts:111` | `toHaveURL(/replacement-home-ui\?blocked=request-pending/)` | `FROM_LANDING_ROUTES['replacement-home']` | `/my-replacements\?blocked=request-pending/` |

`Feature/ReplacementArrangementTest.php` is the one that bites: it runs on **every** PHPUnit pass (no
testing-DB gate), so the first apply run goes red unless these two strings are edited with the lane —
and the cheapest way to make it green is to revert `:201`, which quietly defeats 7b. The two
Playwright files are testing-DB gated, so on a bare run they *skip* — green that is really absence of
proof. `request-cancel-pending.spec.ts:65` and `request-completed-status.spec.ts:130` assert the same
moved URLs but already live in D and B; they are not double-counted here.

**A′ — green only if the DOM contract in §3 item 4b holds:** `replacement-home-qv.spec.ts` plays the
home page, clicks `#tableBody tr:not(.skeleton-row)` (`:20`, `:25`), requires `#quickViewModal.show`
(`:27`), reads `window.PAGE_DATA.conflictedClasses` (`:31-33`), clicks `#qvArrangeBtn` (`:38`) and
asserts `from=replacement-home` (`:45`). It is kept, not rewritten — but it is the one caveat in the
set: it correlates the **first rendered row** with the **first payload row**, so it stays green only
if `payload.conflictedClasses` keeps its key, its `lecturer` / `sessionId` / `week` fields **and** its
array order (the default sort decides which row renders first).

**B — receipts reversed by M6 (the badge anchor is deleted).** These are **rewrites of the
assertions**, not line edits:

| File | What it pins that no longer exists |
|---|---|
| `replacement-home-status-badges.spec.ts` | the whole file classifies rows by `a.badge-link` in its shared extractor (`:34-35`) and reads `.col-urgency` (`:32`), so receipts 3–7 (`:71-85`, `:87-93`, `:95-108`, `:110-124`, `:126-135`) break with D2 (`:41-64`) — plus `.col-action .btn-action` and `#qvArrangeLabel` = "View Request" / "Arrange Replacement", all of which the icon-only cell replaces |
| `request-completed-status.spec.ts` | receipt 1 (`:85-118`) asserts the badge anchor and `.col-urgency`; receipt 2 (`:120-141`) re-targets `tr[data-id="<requestId>"]`, which becomes the **occurrence** id (§3 item 10); receipt 3 (`:143-159`) drives `#statusFilter` + the "hidden by your current filters" toast + `localStorage 'mrh-filters'` (M16/M17) |
| `status-tooltips-filter-chips.spec.ts` | (a) `MRH_MAP` is keyed on the five My-Requests labels (`:18-24`) while M5 brings six, one of them dynamic (`Completed — <n> days ago`) and so impossible as a static key; (b) `window.statusTooltip` must gain entries (`:87-93`); (c) `#modalOverlay` (`:160`) retires with M7; (d) `.request-card` (`:168`) becomes `replacementHomeCard` / `.replacement-card` (M10). It breaks for these, **not** for a badge anchor — My Requests' badge is a `span` with an `onclick` (`my-request-history.blade.php:576`); the anchor D2 added lives on the home page (`replacement-home.blade.php:383`) |

**C — class-level and page identity; the alias cannot save them:**

- `Feature/ReplacementHistoriesTest.php:178-190, 206-229, 250-290, 346-380` — `Livewire::test(MyRequestHistory::class)`, `payload['requests']`, `cancelRequest`, `cancelRequests`, the completed readings
- `Feature/SpanReservationTest.php:454-476` — `app(MyRequestHistory::class)->render()`
- `e2e/history.spec.js:6`, `e2e/smoke-my-request-history.spec.js:6` — `#timetable` + title `My Request History`
- `e2e/auth-wiring.spec.js:294`, `e2e/full-suite.spec.js:453-472, 645, 692` — the same identity

**D — the URL works but the *intent* is reversed by M1/M12** (the trap an alias-driven green run hides):

| File | Why it cannot simply stay green |
|---|---|
| `request-cancel-pending.spec.ts:68-89` | it expects the cancelled request's row to **disappear** (`:82` `toHaveCount(0)`); M1 keeps the class as one row, now `Cancelled by YOU`. It also submits, then cancels the *first* row (`:68-70`) — grouping puts needs-action first |
| `warning-keywords.spec.ts:13-24` | opens the modal from `.badge:has-text("Pending")` and requires `#cancelRequestBtn` inside it; whether the merged modal keeps that id is §3 item 4's decision, and it must be stated there |
| `ui-regression.spec.ts:151` | pins `#modalOverlay` for the MRH URL, which M7 retires — this one would go **vacuous rather than red** (its click target is `.replacement-card, .request-row`, hidden at desktop), which is worse than failing |

**Treatment:** A stays green by inspection against the contract in §3 item 4b (nothing has been
implemented, so no run can be claimed yet); A′ is the one to watch. B and D are updated **with this lane**,
because the behaviour they pin is exactly what M6/M12/M15 change. C is re-pointed to the merged
component and the new H1, or deleted with a named replacement — `design.md` §2 names each one line by
line. Nothing is silently dropped, and no bucket is described as "nothing breaks".

## 6b. Data contract — every value on the page, and where it comes from

The user's bar for this lane: **nothing on the page may be wrong, and nothing on the page may
be invented.** So the payload is specified value by value, and every one has exactly one
source.

| Page value | Source (single) | Rule |
|---|---|---|
| course code · name · type | `class_sessions → modules` | already in the payload; unchanged |
| date · day · week | the occurrence (`start_date + (week-1) weeks + day_of_week`) | never recomputed client-side. **Not** the date the status is judged against — see the row-status row |
| time · duration | `class_sessions.start_time/end_time` | duration = minutes / 60, integer |
| venue (table column) | `venues.room_code` | the column prints the **code** only |
| cohorts | `class_sessions → cohorts` (`spacedCohortLabel`) | one per line, order as loaded |
| students | `sum(cohorts.student_count)` | the number the count must use, no separate query |
| conflict reason | `class_exceptions.reason` through `ReasonLabel::from()`, holiday precedence D9 | the existing rule, untouched |
| row status | `ReplacementRequestMapper::effectiveStatus()` | **the only place `completed` is decided**, and it judges the **replacement** date (not the occurrence date): approved + replacement day already past → completed. The browser never decides a status |
| "Completed — n days ago" | display only, from the replacement date | relative-label rendering may use the browser clock; it must never change the status or a filter |
| newest request | `ReplacementRequest` ordered `submitted_at, id`, last one wins per occurrence | tie-break documented. `submitted_at` is **NOT NULL**, so there is no null case to defend; if that ever changes, the order must say `nulls last` explicitly (pgsql puts NULLs **last** on ASC but **first** on DESC — the `keyBy` would then take an undated row as newest) |
| requested replacement (date · time · venue) | **the newest request's span** through `ReplacementRequestMapper::replacementStrings()` → `stringsFromSpan()`: the junction `replacement_request_slots` read min→max, and the anchor `replacement_time_slot_id` **only as the fallback** when the junction is empty | `—` when there is no request. **Never read the anchor directly**: it holds the first 30-minute cell, so a 2-hour arrangement would print `09:00 – 09:30`. The anchor is correct only *after a cancel or a rejection*, because `CancelPendingRequest` **and** `RejectRequest` delete the junction rows — which is exactly why the mapper has the two-step rule. The batch path must keep that second step (law 3) |
| request id / request status | the same newest request: `id`, `effectiveStatus()` | one request object feeds the column, the modal tab and the Action cell |
| remark | `replacement_requests.remarks` | nullable → the row is omitted, never printed as "null" |
| rejection reason | `replacement_requests.rejection_reason` | shown **only** when the row's status is `rejected`; independent of `remarks` |
| status description | generated from the row's status — **keyed by the status, not by the badge label** | must resolve for **all six** states. Today `statusDesc()` covers five keys and returns `''` for anything else (`my-request-history.blade.php:782`), which would print an empty row for `Need to Arrange` and for the `Completed — <n> days ago` label if it were used as the key. The merged map adds the no-request sentence (*"No replacement has been proposed for this class yet."*) and is looked up **before** the display label is built. The home quick view's *urgency* sentence is not carried over — same label, different meaning; renaming that one is a later lane |
| venue, per schedule (the modal's two tabs) | Original Class Schedule = the occurrence's venue; Proposed Replacement Schedule = the span's venue | **the one place the name is rendered**: both print `room_code — room_name`, and `room_name` is nullable → **fall back to the code**, never an empty string |

**Correctness laws**

1. **One row set.** The table, the five cards, the chips' counts and the pagination all derive
   from **one** filtered array in one render pass. No card may query the database on its own,
   and no two of them may count differently.
2. **One status rule.** `effectiveStatus()` stays the only implementation. The merged page
   must not re-derive `completed` in PHP, in Blade or in JS — a second rule is how "Approved"
   and "Completed" end up disagreeing on the same screen.
3. **No N+1 — and it needs an additive entry point, because today there are already more than
   two queries.** The payload runs **three** base queries (`sessions` with module/venue/cohorts,
   `class_exceptions`, `latestRequests`), and `effectiveStatus()` adds **two per approved row**
   (`span()`'s junction read + the `TimeSlot → venue` lazy load in `stringsFromSpan()`). New
   columns cannot fix that — they are not columns of `replacement_requests`. So the merged
   payload uses a **batch** path: `spansForRequests()` for every row's span in one query, with
   venues preloaded, and that span object passed into the status/strings helpers. **The batch
   read never returns a span for a junction-less request** — `spansForRequests()` reads
   `replacement_request_slots` only, and the junction is DELETEd by `CancelPendingRequest` and by
   `RejectRequest` — so for any request id **absent from that map** the payload builds the same
   span shape from `$r->replacementTimeSlot` (already eager-loaded, `ReplacementHome.php:74`).
   That is `span()`'s step 2, not a second rule: junction first, anchor second, in that order.
   Skipping the fallback would print `—` for every cancelled and rejected request that *did* have
   a slot — inventing absence, which law 6 forbids. Widening the narrow select stays additive;
   the FK is never dropped.
4. **After a cancel, re-read.** The row's status is whatever the database now says — the view
   must not assume `cancelled` locally. `CancelPendingRequest` is the write; the repaint uses
   its result.
5. **The API agrees.** `ReplacementRequestMapper::toRow()` / `ApiReadController` are untouched,
   so the JSON `status` and the page's badge come from the same helper. If they ever disagree,
   the helper is wrong, not the page.
6. **Nulls are honest.** An empty venue name, an absent request, an empty remark: each renders
   as the designed "nothing here" state (an em dash or an omitted row), never as an empty gap
   or the string "null".

## 7. Risks

| Risk | Mitigation |
|---|---|
| The alias silently breaks a notification or a redirect | M2 defines both as route entries (query string preserved); `NotificationEmitter.php:64,88` and `notif-row.blade.php:96` are verification targets; a feature test asserts **all three** URLs render the merged page **with the query string intact** |
| A link is updated before its route exists, so it 404s | the route entries and the **six live files** land in **one** apply commit; the frozen three (`NotificationEmitter`, `notif-row`, the seven `mock-data.js` fixtures) stay untouched. There is a **second** coupling edge in that same commit: the `?notif-hl=` **producer** (`ui-common.js:1292`) and the merged row's `data-request-id` **consumer** (item 10) must land together, or the arrival pulse silently skips — the exact failure M2 exists to prevent |
| The destructive icon loses its guard when the component moves | §3 item 1: one delegate to `CancelPendingRequest`, no local write; the class-level cancel tests in group C keep covering it |
| The payload grows a second source of truth (mapper vs component) | One shaping path (item 3); the component only selects and orders |
| `completed` / `requestStatus` computed in two places | Reuse `effectiveStatus()`; no second rule |
| The H1 description ships with literal `<span>` | §3 item 8 — `descriptionHtml` + `{!! !!}`, following `ui-summary-bar`'s precedent |
| Two modal shells for one row | M7 names `#quickViewModal`; `#modalOverlay` retires with the page |
| `pv-*` prototype classes leaking into the app | `design.md` maps every prototype class to a real token/class or to the three new definitions in §3 item 9 |
| Scope creep into the dropped `Course` rename | M11 keeps it out |
| Another lane's dirty files slipping into the commit | explicit `git add` paths, per-lane commit |

## 8. Sequencing

1. This lane: payload + mapper, then the view, then **the route (canonical + two aliases) and every link that names an old URL, in one commit**, then tests.
2. Nothing else. The `Subject` rename is dead (M11); the request-trail page (if ever wanted) is a separate lane.
