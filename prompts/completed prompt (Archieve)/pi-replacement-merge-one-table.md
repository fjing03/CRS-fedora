# Prompt — one replacement table (SDD lane `replacement-merge-one-table`)

> **This was the implementing brief for a lane that is now applied** (see the status below). Every
> decision in §3 was made by the user on
> **2026-11-03**, through ~25 prototype review rounds. Do not re-ask them, do not re-open
> them. What is yours: the code, the tests, the receipts, the changelog — **not** the plan.
>
> **Status 2026-11-04: APPLIED and ARCHIVED.** All four batches passed review, PI landed the
> five commits plus the docs commit (`e70c1ea`, `6856577`, `ab65f9a`, `d3f6f5b`, `c4d0ad3`,
> `008e941`), and the lane now lives at `.sdd/archive/replacement-merge-one-table/`
> (`sdd.yaml`: `archived`). **This file is history — read it for the decisions, not for work.**
>
> | Batch | Rounds | Where |
> |---|---|---|
> | proposal | 8 | `proposal.md` — M1–M17 + §6b data contract |
> | design | 2 | `design.md` — payload, span path, seven columns, modal, tests |
> | specs | 2 | `specs/replacement-page-merge/spec.md` — R1–R17 |
> | tasks | 5 | `tasks.md` — **start here**; five commits, 24 tasks, each with a receipt |
>
> **Do not write a batch again.** If something in the plan looks wrong while you code it,
> stop and ask the user — do not silently re-decide it. Both prerequisites landed:
> `replacement-home-status-badges` (`a16727c`, `2e58547`) and `request-completed-status`
> (`e2b2f4c`, `7d2bb9a`).

---

## 0. How to use this file

1. Read the repo context first, in this order: `prompts/for-ai-to-read/AGENTS.md`,
   `prompts/for-ai-to-read/CodingMAIN.md`, `prompts/for-ai-to-read/my-coding-rules.md`,
   then §0 of `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` (its standing rulings
   apply to you too).
2. Read `.sdd/archive/replacement-merge-one-table/explore-brief.md` (the design authority)
   and `proposal.md` (the frozen scope) **in full**.
3. Look at the picture: `../ui-prototype/replacement-merged-preview.html`. It is a
   discussion instrument, **never staged, never shipped** — but it is what the user
   approved, down to the wording.
4. Then `design.md`, `specs/…/spec.md` and `tasks.md` — **all frozen; read them, do not
   rewrite them.** `tasks.md` is the working list: five commits, in its order.
5. Then implement, test, receipt, changelog — one commit at a time, quoting real numbers.
6. Ask before archiving anything.

---

## 1. The user's standing communication preference (verbatim)

> Use simple, everyday English and avoid unnecessary technical terms or long
> explanations. When you need me to make a decision, explain it in plain terms,
> list the options with their main risks and benefits, and recommend one if
> appropriate. Ask one clear question at a time, and do not proceed with actions
> that require my approval until I confirm.

Phrase questions as **"So the question in plain terms: …"**. Never claim a receipt you do
not have.

---

## 2. What the change is (plain English)

The lecturer has two pages that answer one question in halves: **Replacement Home** ("what
must I arrange?") and **My Request History** ("what did I submit, and what happened?").
Arranging a replacement means losing sight of it and switching pages.

The merged page is **one table, one row per class**, where the row's status is the newest
request made for that class. The page is called **My Replacements** and lives at
**`/my-replacements`**. `/replacement-home-ui` and `/my-request-history-ui` keep working
**as extra route entries for the same page** (never redirects), because stored notification
links, the post-submit redirect and `?notif-hl=` all still point at the old ones.

What the merge deliberately gives up — and the user accepted — is the per-request trail
inside the modal: no HISTORY block, no child tabs, no timeline. The request's record keeps
that data, but **no lecturer-facing page renders it after this lane** until a later lane
builds one. That is written in the proposal §1 so it is not discovered as a surprise.

---

## 3. The locked decisions (M1–M17, quoted from the frozen proposal)

| # | Decision |
|---|---|
| **M1** | One table, one row per class occurrence, keyed `(class_session_id, week_number)`. Row status = newest request (greatest `submitted_at`, tie-break `id`). No request → the row still appears as `Need to Arrange`. |
| **M2** | Canonical **`/my-replacements`**. Both old URLs (`/replacement-home-ui`, `/my-request-history-ui`) stay as **extra route entries for the same component — never redirects** — so query strings survive. Eleven live call sites move to the canonical in one commit; stored notification links and their `mock-data.js` fixtures do not. |
| **M3** | Title and nav label **My Replacements**; two lecturer nav items become one (key `replacement-arrangement`). Leave `'nav' => 'replacement-history'` on the alias entry alone. |
| **M4** | **Seven columns:** Course Code & Name · **Cohort** · Original Class · Conflict Reason · Requested Replacement · Request Status · Action. Cohort = one per line (`<br>`), not sortable. |
| **M5** | Labels: `Need to Arrange` (outlined) · `Pending — Waiting for Approval` · `Approved` · `Rejected` · `Cancelled by YOU` · `Completed — <n> days ago`. |
| **M6** | Action is icon-only: rotate = arrange · ban = cancel (pending, `--color-error` tint) · eye = open the pop-up. **The badge is not clickable** and its anchor (`requestHref()`) is deleted — this supersedes the badges lane's D2 receipt. |
| **M7** | Modal = **`#quickViewModal`** (not `#modalOverlay`), fixed facts strip (cohorts, students, conflict) + three tabs: `Original Class Schedule` · `Proposed Replacement Schedule` (hidden when nothing is proposed) · `Request Status (<status>)`. No HISTORY, no child tabs, no timeline. |
| **M8** | `Remark` (`remarks`) and `Rejection Reason` (`rejection_reason`) are **two rows**. `Status Description` keeps its name. Completed's sentence carries the count. |
| **M9** | Five cards: Classes to Arrange · Class Hours to Replace · Pending Requests · **Approved Requests** · **Rejected Requests**. |
| **M10** | Reuse, never a second version: copy-email contract (`📋`, clickable address, shared toast) · `data-tip` singleton · **Confirm Cancellation** modal · `.card-view` on phones · `.filter-chips`, `.rpp-*`, `.pagination-*`, `.week-select`, `.summary-*`, `.modal-tab`, `.detail-row`. |
| **M11** | The page says **Course**, not Subject. That rename was made and reversed the same day — do not re-open it. |
| **M12** | Bulk cancel removed (no checkbox column, no bulk bar; `Quick Cancel` / `Requested At` columns go with the page). Cancelling is one at a time through the confirm modal, **always through `CancelPendingRequest`**. |
| **M13** | Search also matches cohort codes, ignoring the spaced convention (`RSD3(S1)G2` finds `RSD3 (S1) G2`). |
| **M14** | Sort arrows reuse `.sort-arrow` (`▲`/`▼`, `theme.css:1137`, emitted like `ui-common.js:1586-1592`), only on the active column; direction sorts **inside** the needs-action group. |
| **M15** | The toolbar's `Showing N of M classes` hides **below 1500 px** (the pagination bar still reports it). |
| **M16** | The arrival-reveal law is inherited: hidden **only** by `Exclude COMPLETED Status` → untick and show; hidden by anything else → keep the toggle and toast. Reconcile the `mrh-filters` key. |
| **M17** | Filters carried as they are: search · week navigator · status select · `Exclude COMPLETED Status` ON by default · **Reset** (defaults, exclusion ON) vs **Clear all** (everything, exclusion OFF) · needs-action-first grouping, sort inside groups. |

---

## 4. The one write path (do not re-invent it)

The lane is read-only **except** cancelling a request, and that must delegate:

```php
// task T5 — the array is design §1.7's [D]: the row comes back from the server
public function cancelRequest(int $id): array
{
    $ok = app(CancelPendingRequest::class)(auth()->user(), $id);

    return ['ok' => $ok, 'row' => $ok ? $this->rowFor($id) : null];
}
```

`rowFor()` re-reads the occurrence through the same `buildRow()` the page uses. **Never patch
the row from an assumption** (`status = 'cancelled'`): the action deletes the request's extra
slots, so the row's time changes too — the server's answer is the only true one.

`app/Actions/CancelPendingRequest.php` carries the ownership guard, the pending guard, the
D6 slot release and the audit row. A local `update(['status' => 'cancelled'])` bypasses all
four — that is the incident this line prevents.

The confirm overlay (`#cancelConfirmOverlay`, `openCancelConfirm()`/`closeCancelConfirm()`,
the `pendingCancelId` double-submit guard) moves out of
`resources/views/livewire/my-request-history.blade.php:275,892-901,695-697`. Its **success
path is not a cut-and-paste**: the history version splices `mockRequests` and calls
`renderTable()`, while the merged table is built client-side from
`payload.conflictedClasses` — update the row, repaint, close, toast.

---

## 5. Where the work lands (from the frozen proposal §3)

| Surface | Change |
|---|---|
| `app/Livewire/ReplacementHome.php` | merged payload + `cancelRequest()` delegate |
| `public/js/ui-common.js` | `HtmlBuilder.replacementHomeRow` (`:2481-2506`) reshaped to 7 columns; `applyNotifHighlight` (`:320-342`) gains a **second selector, with `data-request-id` tried first** |
| `app/Services/ReplacementRequestMapper.php` | replacement slot, `remarks`, `rejection_reason`, venue **name**; `effectiveStatus()` reused |
| `resources/views/livewire/replacement-home.blade.php` | 7-column table, modal, action cell, cards, toolbar, the moved confirm overlay |
| `routes/web.php` | `/my-replacements` + both old URLs as aliases (same middleware, query string preserved) |
| `ui-common.js` (links) | `BackNavigator.#routes` values + default (`:3071,3072,3079` — **keys untouched**), entry-guard toast **bodies** (`:4419,4422`) and **labels** (`:4420,4423`), "View Full Request" (`:1292`, gains `?notif-hl=r<id>&view=request`) |
| `ReplacementArrangement.php` | `FROM_LANDING_ROUTES` + fallback (`:78,79,201`) → canonical; the duplicate-request message (`:1214`) |
| `replacement-arrangement.blade.php:2241,2245` | post-submit toast link + redirect → canonical |
| `MyRequestHistory.php` + its blade | **deleted** (design §2.2 — no forwarder, no caller) |
| `partials/ui-nav-bar.blade.php` | two lecturer items → one |
| `partials/ui-page-header.blade.php` | `descriptionHtml` via `{!! !!}` so the H1 keeps `CLASS` / `REQUEST` / `ONE LIST` |
| `theme.css` | five token-only definitions: `.btn-icon` (+ 3 modifiers), `.badge-reason` + the moved `.badge-holiday`, `.badge-outline`, `.cell-sub`/`.cell-dash`, `.detail-facts`/`.df-*` |
| tests | see §6 of the proposal — buckets A / A′ / B / C / D |

**DOM contract** (group-A specs read these): `#tableHead`, `#tableBody`, `#searchInput`,
`#resultCount`, `#cardView`, `#timetable`, `#toastBar`, `.col-code`, `.cell-code`,
`.cell-name`, `.cell-type-label`, `#quickViewModal` (+ `.show`), `#qvBody`, `#qvArrangeBtn`,
`#qvArrangeLabel`.

---

## 5b. Where to start (the five commits of `tasks.md`)

| # | Commit | Tasks |
|---|---|---|
| 1 | `feat(my-replacements): … payload, batch span path, six-state vocabulary` | T1–T6 |
| 2 | `ui(my-replacements): one table, seven columns, tabbed modal` | T7–T13 (+ the page identity, §2.1b/2.1c) |
| 3 | `refactor(my-replacements): canonical URL, three routes, retired page` | T14–T17 — **one commit, nothing split** |
| 4 | `test(my-replacements): the specs the merge reverses` | T18–T20 |
| 5 | `docs(my-replacements): changelogs + handover` | T21–T22 |

Guardrails that are easy to lose:

- **Never push.** Stage only this lane's files; the tree carries other lanes' work.
- **The four frozen URL consumers stay frozen**: the seven `mock-data.js` fixtures,
  `notif-row.blade.php:96`'s strict compare, `NotificationEmitter.php:64,88`, and the
  `ui-design-templates` copy. Changing one breaks stored notifications.
- **`toRow()` is not touched** — the API and the PL page read it, and §6b law 5 depends on it.
  Adding a key there is a different lane (3C of the approval brief), and it needs its own
  `unset()` in `ApiReadController::myRequests()`.
- **Two known red windows, both named in `tasks.md`**: `ReplacementArrangementTest` and
  `arrangement-entry-guard` are red from commit 3 until commit 4. Never green them by
  reverting a URL or a toast — that is the failure the lane exists to prevent.
- **Line numbers are a snapshot at `bcab5f5`.** Another session has since refactored
  `ReplacementRequestMapper`'s timezone handling; the structure is unchanged, but if a cited
  line looks off by a few, trust the name, not the number.

---

## 6. Out of scope — do not drift

- Any schema, migration, seed or demo-data write.
- The request trail / timeline / per-request panels (cut by the user).
- Bulk cancel anywhere on this page.
- The `Subject` rename (dead).
- Notifications, emails, bell rows — the alias keeps existing links working.
- The mobile card's field choice beyond reusing `replacementHomeCard`.
- Other lanes' dirty files in the tree.

---

## 7. Ways of working that this lane has already been bitten by

1. **The test inventory is measured, not estimated** — `grep -rl` gives 28 home code files,
   19 My-Requests code files, 11 in both, union **36**. The proposal's §6 buckets every one.
   Bucket D is the one an alias-driven green run hides: the URL works, the *intent* is
   reversed (`request-cancel-pending` expects the row to vanish).
2. **The alias is a route entry, not a redirect** — a redirect silently drops `?notif-hl=`
   and `?blocked=`. A feature test must assert both URLs render the page **with the query
   string intact**.
3. **The prototype is not a receipt.** If the prototype and the brief disagree, the brief
   wins and the prototype gets fixed.
4. **Two modal shells exist today** — pick `#quickViewModal` and retire `#modalOverlay`;
   never both.
5. **`ui-page-header` escapes its description** — the H1 emphasis needs `{!! !!}` or it
   prints literally.
6. Prototype-only classes (`pv-*`) must never reach the app; every one maps to a real
   token/class or to the three new theme definitions.

---

## 8. Definition of done

- `tasks.md`'s five commits landed **in its order**, each with the receipts it names.
- Both URLs render the merged page; the notifications' links still highlight their row.
- The 7 columns, the labels, the modal and the cards match this file — checked against the
  brief, not against memory.
- The cancel path goes through `CancelPendingRequest`, and the confirm modal is the
  existing one.
- `composer run lint:check`, `phpstan`, full phpunit, `node --check` — all green.
- Browser receipts for: the merged table, the modal's three tabs, a cancel, the alias with
  `?notif-hl=`, and a phone-width card.
- Changelogs under `page-changelogs/` for both pages and the nav.
- The guard toast reads **once**: body is the fact alone, the link says `My Replacements`
  (user decision 2026-11-04 — the state word is in caps: `APPROVED` / `PENDING`).
- The commit names the lane, and **nothing is pushed**.
