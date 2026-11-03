# Prompt — Replacement Home status badges (SDD lane `replacement-home-status-badges`)

> **You are the implementing session.** Every decision in §3 was made by the user
> on **2026-11-03** in a review session. Do not re-ask them, do not re-open them.
> What remains yours: the SDD artefacts, the code, the tests, the receipts.
>
> The user's instruction that produced this file: *"Review the idea before
> implementation… Do not modify code or the database until I approve the proposed
> approach."* The review is finished and the approach is approved **as described
> here**. Nothing has been implemented yet.

---

## 0. How to use this file

1. Read the repo's own context first, in this order: `prompts/for-ai-to-read/AGENTS.md`,
   `prompts/for-ai-to-read/CodingMAIN.md`, `prompts/for-ai-to-read/my-coding-rules.md`,
   then §0 of `prompts/for-ai-to-read/PI_Chat_Context_Handover.md`.
2. Read this file **in full**.
3. Write the SDD lane (proposal → design → tasks → review) **before** touching code.
   SDD is mandatory for this change (`my-coding-rules.md` §5) — no waiver was given.
4. Then implement, test, and produce the receipts in §10.
5. Stop and ask before archiving anything.

---

## 1. The user's standing communication preference (verbatim)

> Use simple, everyday English and avoid unnecessary technical terms or long
> explanations. When you need me to make a decision, explain it in plain terms,
> list the options with their main risks and benefits, and recommend one if
> appropriate. Ask one clear question at a time, and do not proceed with actions
> that require my approval until I confirm.

Also: phrase questions as **"So the question in plain terms: …"**. This user is a
final-year student preparing an FYP demo; answer honestly, never claim a receipt
you do not have.

---

## 2. What the change is (plain English)

The Replacement Arrangement page (`/replacement-home-ui`) lists the logged-in
lecturer's conflicted class occurrences. Today, as soon as a replacement request
is **pending** or **approved**, the row is **removed** from the list
(`ReplacementHome.php:77–79`). That hide was an agent fix from 2026-10-11, not a
user ruling.

The user wants the opposite: **keep the row**, and turn its Action button into a
status the lecturer can click:

- **Pending** → clickable badge → My Requests, that request highlighted.
- **Approved** → clickable badge → My Requests, that request highlighted.
- **Rejected / Cancelled** → a small muted label above the restored *Arrange
  Replacement* button (the row must be arrangeable again — that already works).
- **Completed** → **out of scope** (see §4).

Today's My Requests page can print a "Completed" badge and offers a "Completed"
filter, but nothing in the app ever writes `completed`
(`RequestApproval.php:23` calls it "an honest empty"). The user decided to
defer completion to its own later lane rather than fake it.

---

## 3. The nine decisions (locked — quote these in the proposal)

| # | Decision |
|---|---|
| **D1** | A row with a **pending** or **approved** request stays in the table (this deliberately reverses the 2026-10-11 hide). Rejected/cancelled rows already return — unchanged |
| **D2** | Action cell: **Pending** / **Approved** as a clickable badge linking to `/my-request-history-ui?notif-hl=r<request_id>`. Rejected/cancelled: a muted **Rejected** / **Cancelled** label **stacked above** the Arrange button; the label is **not** clickable |
| **D3** | **Completed is not in this change.** It has no producer in the app. Do not add a Completed badge, do not build a completion step |
| **D4** | Update exactly three strings on the home page (subtitle, guide item, empty state). **Leave the three summary cards untouched** — they remain conflict totals |
| **D5** | Quick view: for a handled row the footer button becomes **View Request** (same destination as the badge) and the modal gains a real *Replacement Status* row. The mobile card shows the same badge and its tap goes to My Requests |
| **D6** | Highlight: add a **page-jump inside My Requests only**; if an active filter hides the row, show the existing toast instead of doing nothing. **Do not touch `applyNotifHighlight` or the bell** (that lane is in flight) |
| **D7** | Days Left shows **"—"** on handled rows. Sorting still uses the real date, so order does not jump when a row becomes handled |
| **D8** | Sort order: rows that still need action first, handled rows after; the chosen column sorts **within** each group |
| **D9** | A public-holiday row keeps **"Public Holiday"** after approval (the `official_event` stamp is a consequence, not a cause) |

**Definition used throughout:** a row is **handled** when its newest request has a
status that is neither `rejected` nor `cancelled`. A row **needs action** when it
has no request, or its newest request is `rejected` / `cancelled`.

---

## 4. Out of scope (say so in the proposal)

- Any `completed` status, completion job, or date-derived "Completed" label.
- Schema changes — **this change needs none**. No migration, no demo-DB write.
- The Request Approval page's payload/filters (`RequestApproval.php:69–72`).
- The notification bell, its partial, or `applyNotifHighlight`.
- The `.sdd/changes/notif-recipient-rulings/` lane and its uncommitted files
  (see §5 trap 1).
- The three summary cards' numbers and any rename of them.

### Rejected alternatives — do not revive them

| Approach | Why it was rejected |
|---|---|
| Fake "Completed" from `approved` + past replacement date | My Requests would still say "Approved" for the same request — two screens disagree in front of the audience |
| Build a real completion step inside this lane | No scheduler runs under `artisan serve`; and `completed` would stop counting as active, while the DB's only duplicate guard is a partial unique index on `pending, approved` — reopening a double-request hole |
| Clickable Rejected/Cancelled label | The backend would have to ship the newest non-active request too, and two click targets in a 150 px cell invite misclicks |
| Hide past handled rows | That is the completion rule in disguise |
| Put the page-jump in the shared highlighter (`c`) | Promote-on-3rd: only two landing pages need it, and the bell lane is in flight |
| Keep the payload's active-only query | It cannot tell a rejected row from a never-arranged one, so the muted label is impossible |

---

## 5. Traps — read before you start

1. **Another lane is dirty in the same working tree.** `git status` will likely
   show `app/Actions/ApproveRequest.php`, `SubmitReplacementRequest.php`,
   `app/Livewire/NavNotifications.php`, `app/Models/Notification.php`,
   `app/Support/Notifications/NotificationEmitter.php`,
   `resources/views/partials/notif-row.blade.php`, plus an untracked
   `.sdd/changes/notif-recipient-rulings/`. **Never stage them.** Commit only the
   files this lane touches.
2. **The one-active-request rule is DB-enforced**, not query-enforced:
   `uq_replacement_requests_active_block` is a partial unique index on
   `(class_session_id, week_number) WHERE status IN ('pending','approved')`
   (`database/migrations/2026_08_24_000001_…php:31–36`). We are only *displaying*
   those rows; do not relax the index or add a duplicate check.
3. **The row is only built when the occurrence is conflicted** (exception or
   holiday). Approval stamps an `official_event` exception when none exists
   (`ApproveRequest.php:252–265`), so an approved row always has a reason. Do not
   invent rows for requests without a conflict; if you meet one in testing,
   report it rather than patching the list semantics.
4. **The notification highlighter strips `notif-hl` at 150 ms**
   (`ui-common.js:320–327`), before My Requests has rendered (its first render is
   behind a 400 ms skeleton). Any page-jump must capture the id **synchronously**
   in the page's own `DOMContentLoaded` handler.
5. **The flash is 1.3 s** (`flashRowPulse`, `ui-common.js:219–224`, class
   `bookable-flash`). A browser assertion must catch it inside that window, or
   assert the navigation instead.
6. **`_display`-style composition is not involved here** — do not copy the
   notification lane's staff-line logic.
7. **The legacy mock template** (`ui-design-templates/replacement-home-UI-design-template.blade.php`)
   calls the same `HtmlBuilder` functions. Keep the new builder options optional
   so the mock still renders its old button.

---

## 6. The payload contract (`ReplacementHome.php`)

Today:

```php
$activeRequests = ReplacementRequest::query()
    ->whereIn('class_session_id', $sessions->pluck('id'))
    ->whereIn('status', ['pending', 'approved'])
    ->get(['class_session_id', 'week_number'])
    ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);
```

and in the loop, `if ($activeRequests->has(...)) { continue; }`.

Change to:

1. Load the **newest request per occurrence regardless of status** (the badge
   needs to know `rejected`/`cancelled` too):

   ```php
   $latestRequests = ReplacementRequest::query()
       ->whereIn('class_session_id', $sessions->pluck('id'))
       ->orderBy('submitted_at')->orderBy('id')
       ->get(['id', 'class_session_id', 'week_number', 'status', 'submitted_at'])
       ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);
       // last row wins = the newest request for that occurrence
   ```

2. Delete the `continue` — the row is always built when a reason exists.

3. Add two keys to every row:

   ```php
   'requestId'     => $latest?->id,      // int|null
   'requestStatus' => $latest?->status,  // 'pending'|'approved'|'rejected'|'cancelled'|null
   ```

4. Public-holiday precedence (D9). Replace the reason block's order with:

   ```php
   if ($exception !== null) {
       $reason = self::titleCase((string) $exception->reason);
       // D9: the approval flow stamps `official_event` when no exception
       // existed (ApproveRequest::releaseOriginal). On a calendar-holiday
       // occurrence that stamp must not rewrite the cause.
       if ($holiday !== null && (string) $exception->reason === 'official_event') {
           $reason = 'Public Holiday';
       }
   } elseif ($holiday !== null) {
       $reason = 'Public Holiday';
   } else {
       continue;
   }
   ```

   Documented trade-off: a lecturer who *chose* "Official Event" for a week that
   is also a calendar holiday will see "Public Holiday". The two cases are
   indistinguishable in the data (the stamp reuses the same enum value).

5. Keep the existing keys and values byte-identical otherwise. Keep the row
   `id` = `sessionId * 100 + week`.

---

## 7. File-by-file plan

### 7.1 `public/js/ui-common.js` — the two shared builders

`HtmlBuilder.replacementHomeRow(c, opts)` (`:2481–2496`) — add two **optional**
callbacks, defaulting to today's output:

- `opts.isHandled(c)` → boolean. When true, the Days Left cell renders `—`
  (keep `opts.daysLeft` for nothing else; the sort key stays in the page).
- `opts.actionCell(c)` → HTML string for the Action cell. Default (option
  omitted) = today's Arrange button.

`HtmlBuilder.replacementHomeCard(c, opts)` (`:2498–2515`) — add:

- `opts.statusBadge(c)` → HTML inserted into `.rc-header` after the reason badge.
- `opts.isHandled(c)` → the days pill shows `—`.

Use `statusClass()` (`:2866–2875`) for the colour; never hard-code a colour.

### 7.2 `resources/views/livewire/replacement-home.blade.php` — page script

**The three strings (D4):**

- subtitle (`:194`): *"The following classes have conflicts. Arrange a
  replacement for the ones that still need one, or click a status badge to open
  its request."*
- guide item (`:202`): *"<strong>Track progress</strong> — a Pending or Approved
  badge opens that request in My Requests; a Rejected or Cancelled label means
  you can arrange again"*
- empty state (`:243`): *"No classes currently need attention."* / *"Try adjusting
  your search or filter criteria."*
  (wording is yours to polish; keep the meaning.)

**Helpers** (page-local, below `badgeClass`):

```js
function isHandled(c) {
    return !!c.requestStatus && c.requestStatus !== 'rejected' && c.requestStatus !== 'cancelled';
}
function statusLabel(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function requestHref(c) { return '/my-request-history-ui?notif-hl=r' + encodeURIComponent(c.requestId); }

function statusBadge(c) {
    if (!isHandled(c)) return '';
    return '<a class="badge badge-link status-' + c.requestStatus + '" href="' + requestHref(c) +
           '" onclick="event.stopPropagation()" data-tip="Open this request in My Requests">' +
           statusLabel(c.requestStatus) + '</a>';
}

/* Copy the button markup VERBATIM from HtmlBuilder.replacementHomeRow
   (ui-common.js:2494 — the SVG included). Do not retype the icon.
   Extract it once as a template with __SID__ / __WEEK__ placeholders. */
function actionCell(c) {
    if (isHandled(c)) return statusBadge(c);
    var arrange = ARRANGE_BUTTON_HTML
        .replace('__SID__', c.sessionId)
        .replace('__WEEK__', c.week);
    if (isHandled(c)) return statusBadge(c);
    var ended = (c.requestStatus === 'rejected' || c.requestStatus === 'cancelled')
        ? '<span class="badge status-' + c.requestStatus + '">' + statusLabel(c.requestStatus) + '</span>'
        : '';
    return '<span class="action-stack">' + ended + arrange + '</span>';
}
```

- The Arrange button markup must stay byte-identical to today's
  (`ui-common.js:2494`) — copy it, do not retype the SVG.
- Badges are **anchors**, not buttons: navigation, keyboard, middle-click and the
  URL all come free.
- `event.stopPropagation()` keeps the row's quick-view click from firing.

**Sorting (D8)** — the group key must live **inside** the existing comparator in
`buildTable()`. A separate pre-sort does not work: the column comparator would
then reorder across the groups.

```js
filtered.sort(function (a, b) {
    /* 1) the fixed group order — needs-action first, regardless of asc/desc */
    var na = isHandled(a) ? 1 : 0, nb = isHandled(b) ? 1 : 0;
    if (na !== nb) return na - nb;
    /* 2) the existing column comparator, unchanged, now acting within a group */
    // …the current sortState branches (date / code / daysLeft / venue)…
});
```

**Rows** — pass the callbacks:

```js
HtmlBuilder.replacementHomeRow(c, { …, isHandled: isHandled, actionCell: actionCell });
```

**Cards** — same callbacks, plus:

```js
card.addEventListener('click', function () {
    if (isHandled(c)) { window.location.href = requestHref(c); return; }
    goToReplacementWith(c.sessionId, c.week);
});
```

and mirror it in the keydown handler.

**Quick view (D5)** — in `quickView(c)`:

- add a Status-tab row when `c.requestStatus` exists:
  `DetailModal.row('Replacement Status', c.requestStatus ? '<a href="'+requestHref(c)+'">'+statusLabel(c.requestStatus)+'</a>' : '—')`
  — or plain text; the modal already has a link-free style.
- give the footer label its own span in the blade
  (`<span id="qvArrangeLabel">Arrange Replacement</span>`) so you can set
  `textContent` without destroying the SVG.
- in `quickView()` set the footer for the row:

  ```js
  document.getElementById('qvArrangeLabel').textContent = isHandled(c) ? 'View Request' : 'Arrange Replacement';
  ```

- in `qvArrange()` branch first:

  ```js
  if (qvCurrent && isHandled(qvCurrent)) { window.location.href = requestHref(qvCurrent); return; }
  ```

**CSS** (page `@section('page-styles')`, `.col-action` is 150 px wide):

```css
.badge-link { text-decoration: none; cursor: pointer; }
.action-stack { display: inline-flex; flex-direction: column; align-items: flex-end; gap: 4px; }
```

(Stacking is the user's chosen layout: the muted label sits above the button.)

### 7.3 `resources/views/livewire/my-request-history.blade.php` — the page-jump (D6)

- At the very top of the existing `DOMContentLoaded` handler (`:321`) capture the
  id **synchronously** (trap 4):

  ```js
  var pendingHl = null;
  var hlRaw = new URLSearchParams(location.search).get('notif-hl');
  if (hlRaw && hlRaw.charAt(0) === 'r') pendingHl = hlRaw.slice(1);
  ```

- After the first render — i.e. inside the `SkeletonLoader.with(...)` callback at
  `:915`, **after** `renderTable()` — call the jump:

  ```js
  function jumpToRequestRow(id) {
      if (!id) return;
      var idx = currentFiltered.findIndex(function (r) { return String(r.id) === String(id); });
      if (idx < 0) { toast.show('That request is hidden by your current filters.', null); return; }
      var page = Math.floor(idx / rowsPerPage) + 1;
      if (page !== pageState.currentPage) { pageState.currentPage = page; renderTable(); }
  }
  ```

- Do **not** change filter state — the user's own filters are theirs. The toast is
  the honest fallback.
- Do **not** touch `ui-common.js`'s `applyNotifHighlight` (D6).
- `rowsPerPage` is the page's variable (`:338`); `currentFiltered`, `pageState`
  and `toast` already exist.

---

## 8. Acceptance matrix (use it as the test oracle)

| Newest request status | Action cell | Days Left | Sorted | Click destination |
|---|---|---|---|---|
| none | Arrange Replacement | real | first group | arrangement page |
| `pending` | **Pending** badge (link) | `—` | handled group | MRH, `?notif-hl=r<id>` |
| `approved` | **Approved** badge (link) | `—` | handled group | MRH, `?notif-hl=r<id>` |
| `rejected` | muted **Rejected** label + Arrange button | real | first group | button → arrangement page |
| `cancelled` | muted **Cancelled** label + Arrange button | real | first group | button → arrangement page |

Newest = the request with the greatest `submitted_at` (tie-break `id`). A
`completed` request must be handled by the generic "not rejected/cancelled" rule
so the code does not break if one ever appears — but do **not** advertise it.

---

## 9. Tests

### Feature (`tests/Feature/ReplacementHomeTest.php`)

- **Rename and rewrite** `test_home_hides_occurrences_with_an_active_request`
  (`:208`) → `test_home_keeps_occurrences_with_an_active_request_and_attaches_its_status`.
  Assert the week-5 row is **present** and carries `requestId` + `requestStatus
  === 'pending'`; then set the rows `rejected` and assert `requestStatus ===
  'rejected'`.
- Add `test_home_prefers_the_public_holiday_reason_over_the_official_event_stamp`:
  an `official_event` exception + a holiday on the same week/day + an approved
  request → `conflictReason === 'Public Holiday'`.
- Add `test_home_reports_the_newest_request_for_the_occurrence`: one rejected then
  one pending on the same occurrence → `requestStatus === 'pending'` and the
  pending id.
- Assert `requestId`/`requestStatus` are `null` on a row with no request.

### Browser (a new spec, e.g. `tests/replacement-home-status-badges.spec.ts`)

Follow the existing spec harness (`CRS_BASE`, the guard helpers, fresh in-flow
login). Do **not** write to the demo DBs.

1. A row with a pending request renders a `Pending` link and **no** Arrange button.
2. Clicking it lands on `/my-request-history-ui` and the matching `tr[data-id]`
   receives `bookable-flash` within the 1.3 s window (trap 5).
3. An approved row shows `—` in Days Left.
4. Handled rows appear after rows that need action.
5. Narrow the viewport → the card shows the badge and tapping navigates to MRH.
6. Open the quick view on a handled row → footer says **View Request** and a
   *Replacement Status* row exists.

**Page-jump receipt:** it needs more than 10 rows for that lecturer. If your
fixtures cannot produce that, say so plainly in the changelog and verify the jump
manually (screenshot of page 2 after clicking a badge) — do not claim an
automated receipt you do not have.

---

## 10. Docs and receipts

- `page-changelogs/replacement-home-changelog.md` — dated entry: the D1 reversal,
  the badge/label matrix, the days/sort changes, the copy strings, the holiday
  precedence + its trade-off, and the exact tests run.
- `page-changelogs/my-request-history-changelog.md` — the page-jump.
- `.sdd/changes/replacement-home-status-badges/` — proposal, design, tasks,
  review-log (archive only on the user's word).
- **No** `docs/demo-db-data-changes.md` entry: this change writes no demo rows.
  If you find yourself needing one, stop — you are off-plan.

---

## 11. SDD flow (mandatory)

1. `proposal.md` — quote the nine decisions, the acceptance matrix, in/out of
   scope, risks. One page.
2. `design.md` — the payload contract, the builder options, the page-jump, the
   traps in §5, the trade-offs (holiday precedence, "approved rows stay forever",
   the completion deferral).
3. `tasks.md` — T1…Tn mapped to §7's file list; each task with its receipt.
4. `review-log.md` — one round before code, one after.
5. Implement → verify → changelog → ask the user before archiving.

Quality gates before finishing (the repo law): `composer run lint:check` and
`vendor/bin/phpstan analyse --memory-limit=1G`; **never** `composer run
types:check`.

---

## 12. Definition of done

- [ ] The nine decisions are implemented exactly, with no extra status invented.
- [ ] No schema change, no migration, no demo-DB write.
- [ ] No file from the notification lane staged or committed.
- [ ] Feature tests + browser spec green (or a named, honest manual receipt).
- [ ] Changelog + SDD lane written.
- [ ] The user asked before any archive, and **nothing pushed**.
