# Prompt — "Completed" request status (SDD lane `request-completed-status`)

> **You are the implementing session.** Every decision in §3 was made by the
> user on **2026-11-03** in a grill session. Do not re-ask them, do not re-open
> them. What is yours: the SDD artefacts, the code, the tests, the receipts.
>
> **Hard prerequisite:** the lane `replacement-home-status-badges`
> (`prompts/pi-replacement-home-status-badges.md`) must be **implemented and
> committed first**. This lane builds on the `requestStatus` payload key and the
> home page's status badge that lane introduces. If it is not done yet, say so
> and stop — do not invent a parallel payload.

---

## 0. How to use this file

1. Read the repo context first, in this order: `prompts/for-ai-to-read/AGENTS.md`,
   `prompts/for-ai-to-read/CodingMAIN.md`, `prompts/for-ai-to-read/my-coding-rules.md`,
   then §0 of `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` (ruling 14 is
   yours to respect too).
2. Read this file **in full**, and read `prompts/pi-replacement-home-status-badges.md`
   §6–§7 so you know what that lane already built.
3. Write the SDD lane (proposal → design → tasks → review) **before** code.
   SDD is mandatory; no waiver was given.
4. Then implement, test, receipt, changelog.
5. Ask before archiving anything.

---

## 1. The user's standing communication preference (verbatim)

> Use simple, everyday English and avoid unnecessary technical terms or long
> explanations. When you need me to make a decision, explain it in plain terms,
> list the options with their main risks and benefits, and recommend one if
> appropriate. Ask one clear question at a time, and do not proceed with actions
> that require my approval until I confirm.

Phrase questions as **"So the question in plain terms: …"**. Never claim a
receipt you do not have.

---

## 2. What the change is (plain English)

The database already *permits* the status `completed`
(`replacement_requests_status_check`), My Requests already has a Completed badge,
a Completed filter and a Completed timeline step, and the Request Approval page
has the same. But **nothing in the app ever writes `completed`**, so the status is
an empty set and every "Exclude Completed" toggle has never hidden anything.

The user decided **not** to build a completion workflow. Instead:

> A request that is **approved** and whose **replacement class day has passed**
> is shown as **Completed**.

It is worked out when a page reads the row. **Nothing is written** — not to
`replacement_requests`, not to the audit log. One shared helper feeds every
screen, so no two pages can disagree.

---

## 3. The locked decisions (quote these in the proposal)

| # | Decision |
|---|---|
| **C1** | Completion is **derived at read time**, not stored. No new column, no migration, no audit entry, no backfill. |
| **C2** | The rule: stored status `approved` **and** the replacement class date is **strictly before today** → show **Completed**. (A class on Tuesday reads Completed on Wednesday.) |
| **C3** | It applies on **every screen that prints a request status**: My Requests, Request Approval (PL), the Replacement Home, and the JSON API — all through one shared helper. |
| **C4** | **Exclude Completed stays ON by default** on all three pages, and the Replacement Home gets the same toggle it never had. Same label, same tooltip wording, checked on every load. |
| **C5** | The home's Completed badge **is** a link to My Requests; and My Requests may **untick Exclude Completed for you** when that toggle is the *sole* reason the row is missing, then jump. Any other hiding cause keeps the existing toast. |
| **C6** | The My Requests timeline's final step is timed with the **replacement date**, not the approval timestamp. |
| **C7** | **No notification** for completion. The bell keeps its five events (handover ruling 1). |
| **C8** | Order of work: the **status-badges lane first**, this lane second, both in the same session, two separate SDD lanes and commits. |
| **C9** | **No demo-database write** for this lane. The viva twin already has the needed data (see §10). |

**Definition used throughout:** *effective status* = what the row should say,
which is the stored status except for the single `approved` + past-date case.

---

## 4. Out of scope, and alternatives already rejected

**Out of scope**
- Any button, permission, scheduler or job that marks a request completed.
- Any write to `replacement_requests` / `audit_logs` on read.
- Changing the **stored** status anywhere; `whereIn('status', ['pending','approved'])`
  queries keep their literal meaning (the DB's active-request index is unchanged).
- Notifications, emails, bell rows.
- The cross-semester display base (see trap 6).
- Demo-data changes (C9).

**Rejected — do not revive**

| Approach | Why it was rejected |
|---|---|
| A "Mark completed" button (lecturer or PL) | Needs a permission, an action, an audit row and a column, and nothing would ever show Completed until someone clicked |
| Writing the status on page load | A read mutating the DB — worst possible behaviour on a demo database, and dedup becomes a second problem |
| Deriving it only on the home page | The exact contradiction that deferred this feature in the first place |
| Hiding completed rows permanently | The user asked for a toggle, consistent with the other two pages |
| Notifying on completion | Nothing runs on a timer; the "event" is a calendar boundary, not an action |
| Retiming the completed step to the approval time | Plainly false, and the modal is where an examiner looks |

---

## 5. Traps — read before you start

1. **The other lanes are dirty in this tree.** Before the badges lane is merged,
   the notification lane's files are modified and untracked. Check
   `git status --short` first and **never stage another lane's files**.
2. **Date-grain, not time-grain.** Compare **dates**, not datetimes:
   `Carbon::parse($date)->startOfDay()->lt(today()->startOfDay())`. Never
   `->lt(now())` on a parsed `Y-m-d` (that is midnight, and it would flip at the
   wrong moment every day).
3. **Never use the JS demo clock for this.** The server decides. The real-data
   pages run with `MockData.mockNow = null`; do not read `mockNow` /
   `getTodayMs()` to decide completion.
4. **Unreadable replacement date → do not claim Completed.** `replacementStrings()`
   returns `date => null` when the slot/venue cannot be resolved
   (`ReplacementRequestMapper::displaySlot()` returns null). In that case the
   effective status stays `Approved`. A confidence bug here would mark nothing,
   but a cavalier default of "null means past" would mark everything.
5. **Derive from the same date the row prints.** The effective status must use
   the `$span['date']` the page displays, so badge and date column can never
   contradict each other.
6. **Cross-semester display base is a pre-existing issue, not yours.**
   `MyRequestHistory::requestRow()` and `RequestApproval::rowsPayload()` pass the
   *page's* timeline semester start to the mapper for every row, so a request from
   another semester would already print a wrong replacement date. There is one
   semester in every database (`202605`), so this is latent. Do **not** fix it
   here; leave a comment in the design's known-issues list.
7. **The Completed badge styling already exists** — `.status-completed`
   (`theme.css:3647`) and `statusClass('Completed')` (`ui-common.js:2872`). Do not
   add colours.
8. **Two status producers, not one.** `ReplacementRequestMapper::toRow()` (`:93`)
   feeds the PL page **and** the API (`ApiReadController:156`);
   `MyRequestHistory::requestRow()` (`:84`) feeds the lecturer page. Both must call
   the one helper. The home payload is the third caller.
9. **Don't touch `applyNotifHighlight`** or the bell partial.

---

## 6. The rule and the helper contract

Put it in `app/Services/ReplacementRequestMapper.php` — it already owns the
replacement date (`replacementStrings()` `:227`, `stringsFromSpan()` `:259`) and is
the shared mapper for the PL page and the API.

```php
/**
 * C1/C2 — the effective status ('pending'|'approved'|'rejected'|'cancelled'|'completed').
 * Derived, never stored: an approved request whose replacement class DATE is
 * strictly before today reads 'completed'. Falls back to the stored status when
 * the replacement date cannot be resolved (never guesses Completed).
 */
public function effectiveStatus(ReplacementRequest $r, Carbon $semesterStart): string
{
    $stored = (string) $r->status;

    if ($stored !== 'approved') {
        return $stored;
    }

    $date = $this->replacementStrings($r, $semesterStart)['date'];

    if ($date === null) {
        return $stored;                 // trap 4
    }

    return Carbon::parse($date)->startOfDay()->lt(today()->startOfDay())
        ? 'completed'
        : 'approved';
}
```

Call sites (all three, nothing else):

| Caller | Change |
|---|---|
| `ReplacementRequestMapper::toRow()` `:93` | `$prettyStatus = ucfirst($this->effectiveStatus($r, $semesterStart));` |
| `MyRequestHistory::requestRow()` `:84` | `'status' => ucfirst($mapper->effectiveStatus($r, $semesterStart))` |
| `ReplacementHome` payload | `'requestStatus' => $effectiveStatus` (raw, lowercase — the badges lane's badge helper expects raw) |
| `ApiReadController` `:156` | none — it rides `toRow()` |

Keep the returned values **lowercase**; every consumer already applies `ucfirst()`
where it needs the pretty form.

---

## 7. File-by-file plan

### 7.1 My Requests — `resources/views/livewire/my-request-history.blade.php`

1. **Timeline (C6)** — `:769–773`. The last step's `time` becomes the replacement
   date when the row is Completed:

   ```js
   var completedTime = (r.status === 'Completed' && r.replacementDate) ? fmtDate(r.replacementDate) : null;
   ...
   { label: r.status, time: completedTime || reviewTime || '—', ... }
   ```

   `r.replacementDate` is an ISO `Y-m-d` string. **Do not** hand it to
   `new Date(...)` — that parses as UTC midnight and can render the day before in
   this timezone. Use the page's existing date formatting path, or add a
   `replacementDateDisplay` key from the mapper (formatted in `DISPLAY_TZ`).
   Whichever you choose, keep the other consumers' keys pinned.

2. **Reveal exception (C5)** — the badges lane added `jumpToRequestRow()`.
   Amend its not-found branch:

   ```js
   if (idx < 0) {
       var all = mockRequests.find(function (r) { return String(r.id) === String(id); });
       var onlyCompletedHidesIt = all && all.status === 'Completed'
           && document.getElementById('hideCompleted').checked;
       if (onlyCompletedHidesIt) {
           document.getElementById('hideCompleted').checked = false;  // visible flip
           saveFilters();                                             // persist what the user now sees
           renderTable();
           // recompute idx from currentFiltered and jump to its page
       } else {
           toast.show('That request is hidden by your current filters.', null);
       }
       return;
   }
   ```

   Everything else in that function stays as the badges lane wrote it.

3. **No other change.** The status filter, the toggle, the reset and the sort all
   already exist and start working by themselves.

### 7.2 Request Approval — `resources/views/livewire/request-approval.blade.php`

**No code change is expected.** The page reads `r.status` from `toRow()`, so the
derived value flows through. Verify only:
- the `Approved` summary count (`:756`) drops for past rows and `Reviewed` (`:758`)
  includes them (both match `'Completed'`);
- the status filter and the "Exclude Completed" toggle already list Completed;
- the modal renders the badge and its description.

If any of those breaks, fix the *page*, never the helper.

### 7.3 Replacement Home — `resources/views/livewire/replacement-home.blade.php`

1. **The toggle (C4)** — copy the markup from My Requests (`:200–204`) verbatim:

   ```html
   <label class="toggle-wrapper" id="completedToggle" data-tip="ON = completed requests are excluded from the list">
       <input type="checkbox" id="hideCompleted" checked>
       <span class="toggle-track"><span class="toggle-thumb"></span></span>
       <span class="toggle-label">Exclude Completed</span>
   </label>
   ```

   Place it in the toolbar next to the search box. **No localStorage** — this page
   has no saved filters, no Reset button, and the user chose "checked on every
   load".

2. **The filter** — in `buildTable()`'s filter chain, before the sort and before
   the summary counts:

   ```js
   if (document.getElementById('hideCompleted').checked && c.requestStatus === 'completed') return false;
   ```

   The counts must be taken **after** this filter, or the summary will promise rows
   that are not on screen.

3. **The badge** — no new code: the badges lane's `statusBadge()` already renders
   any status that is not rejected/cancelled, and `statusClass('Completed')`
   resolves. Confirm the row shows a **Completed** link to
   `/my-request-history-ui?notif-hl=r<id>`.

4. **The payload** — swap the raw stored status for the effective one (§6). This
   is a one-line change inside the badges lane's row builder.

### 7.4 CSS

None. Do not add colours, do not touch `theme.css` beyond reading it.

---

## 8. Behaviour matrix (the test oracle)

| Stored | Replacement date | Effective | Badge | Excluded by default |
|---|---|---|---|---|
| approved | tomorrow | approved | Approved (link) | no |
| approved | today | approved | Approved (link) | no |
| approved | yesterday | **completed** | **Completed (link)** | **yes** |
| approved | unreadable (no slot/venue) | approved | Approved (link) | no |
| pending | any | pending | Pending (link) | no |
| rejected / cancelled | any | unchanged | muted label + Arrange | no |

And the two cross-page rules:

- On Request Approval, the same request reads **Completed**, and its `Approved`
  count no longer includes it.
- The API's `status` field reads `Completed` for it.

---

## 9. Tests

### Feature

- `tests/Feature/ReplacementHistoriesTest.php` — an approved request whose
  replacement date is **yesterday** reads `Completed`; the same request dated
  **today** reads `Approved`; a request with no resolvable slot reads `Approved`
  (trap 4).
- `tests/Feature/ApprovalWorkflowTest.php` — the PL payload carries the same
  derived value, and the decided bucket still contains the row (its `bucket` is
  `queue`; only the *displayed* status changes).
- API: whichever test covers `ApiReadController::requests` — assert the `status`
  string. If no such test exists, add one narrow one; do not build an API suite.
- `tests/Feature/ReplacementHomeTest.php` — the home payload's `requestStatus`
  for a past approved row is `completed`.

Use the testing database only: `DB_DATABASE=class_replacement_testing`, or the
disposable `:8030` lane. **Never** `--env=testing` (the trap: it silently boots
`.env`).

### Browser

- `tests/request-approval.spec.ts` and
  `tests/status-tooltips-filter-chips.spec.ts` — they already exercise the
  Completed filter/tooltip; make sure they still pass with a real derived row.
- A new spec (e.g. `tests/request-completed-status.spec.ts`) for the home page:
  toggle checked by default → no completed row; untick → the Completed badge
  appears; click it → My Requests lands on the row (the flash is
  `bookable-flash`, 1.3 s — assert inside that window or assert the page change).
- The reveal exception needs a completed row hidden *only* by the toggle. If the
  fixtures cannot produce one, say so plainly in the changelog rather than
  claiming an automated receipt.

---

## 10. What the data gives you (checked 2026-11-03)

- `class_replacement_viva` (`:8001`, the viva twin): request **27**, `approved`,
  week 7, replacement **3 Nov 2026, 10:00–11:00** — so it reads **Completed from
  4 Nov 2026 onward**. This is your live receipt, and it will be true on the viva
  date.
- `class_replacement_fresh` (`:8000`): three requests — rejected, cancelled,
  pending. **No approved request**, so nothing there will ever read Completed
  without new data, which the user chose to defer (C9).
- `class_replacement_demo`: no requests at all.
- One semester exists in every database (`202605`, start 2026-09-21), which is why
  trap 6 stays latent.

---

## 11. Docs and receipts

- `page-changelogs/replacement-home-changelog.md` — the new toggle, its default,
  the row filter, and the badge link.
- `page-changelogs/my-request-history-changelog.md` — the timeline retime and the
  reveal exception.
- `page-changelogs/request-approval-changelog.md` — the derived status and the
  shifted counts.
- `.sdd/changes/request-completed-status/` — proposal, design (incl. trap 6 as a
  known issue), tasks, review-log. Archive only on the user's word.
- **No** `docs/demo-db-data-changes.md` entry: this lane writes nothing.

---

## 12. SDD flow and quality gates

1. `proposal.md` — the nine decisions, the behaviour matrix, in/out of scope.
2. `design.md` — the helper contract, the three call sites, the reveal exception,
   the timeline retime, the traps, the known cross-semester issue.
3. `tasks.md` — T1…Tn mapped to §7.
4. `review-log.md` — one round before code, one after.
5. Implement → verify → changelog → ask before archiving.

Gates before finishing: `composer run lint:check` and
`vendor/bin/phpstan analyse --memory-limit=1G`. **Never** `composer run
types:check` (it crashes at the default memory limit).

---

## 13. Definition of done

- [ ] One helper; the three callers use it; nothing writes the status.
- [ ] Approved + yesterday reads Completed on **all four** surfaces; today reads
      Approved; unreadable date reads Approved.
- [ ] The home has the Exclude Completed toggle, checked on every load, no
      storage, and the counts respect it.
- [ ] Clicking a Completed badge lands on its row in My Requests, un-ticking the
      toggle only when that is the sole cause.
- [ ] The timeline's Completed step is timed with the replacement date.
- [ ] No notification, no schema change, no demo-DB write, nothing pushed.
- [ ] No file from another lane staged or committed.
