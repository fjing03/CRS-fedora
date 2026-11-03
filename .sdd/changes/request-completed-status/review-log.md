# Review log — `request-completed-status`

## Round 1 (pre-code) — 2026-11-03

Reviewed `proposal.md` → `design.md` → `specs/` → `tasks.md` against the tree the badges lane
left (`a16727c`), **before** writing code. The frozen baseline is the prompt's §3 (C1–C9) plus its
§6/§7 anchors.

### 🔴 Fixed before coding

- **F1 — the home's narrow select would have made the rule dead on that page.**
  `ReplacementHome` fetched the latest requests with
  `get(['id','class_session_id','week_number','status','submitted_at'])`. `effectiveStatus()`
  resolves the span through the **anchor FK**, so with `replacement_time_slot_id` not selected the
  helper reads "no slot" → `null` date → always `approved`. Design §2.1 now states the required
  column explicitly and the code carries a comment saying why. (Caught by the new feature test,
  which failed on the first run with exactly this shape: expected `completed`, got `approved`.)
- **F2 — the Total card was the one count that bypassed the filter chain.** `updateSummary()` used
  `myConflictedRows().length` (unfiltered) while the two My cards used `currentFiltered`. Prompt
  §7.3.2 requires the counts to be taken **after** the completed filter, or the card promises rows
  that are not on screen. Design §3.2 introduces `visibleBaseRows()` and uses it for the Total card
  **and** for `updateResultCount`'s total; the per-filter cards keep `currentFiltered`.
- **F3 — "the toggle is the sole reason" needed a real predicate.** The first draft of the reveal
  exception only checked `hideCompleted.checked` + `row.status === 'Completed'`, so a user with a
  **status filter** set would have had the toggle silently cleared (the browser receipt failed
  exactly there). The predicate now also requires `status === 'all'`, an empty search box and a
  `weekFilter === 'all'`. That is C5's sentence read literally: *that toggle is the sole reason*.
- **F4 — one existing assertion pins the raw stored status.** The badges lane's
  `test_home_prefers_the_public_holiday_reason_over_the_official_event_stamp` asserts
  `requestStatus === 'approved'` on a **September** week-5 row — precisely the case C2 redefines.
  Only that literal changes (to `completed`); the D9 holiday assertion is untouched. Named in
  proposal §7 so the change is expected, not discovered.

### 🟡 Addressed

- **F5 — auth strategy on the `:8030` lane.** Per-test form logins would spend the Fortify limiter
  (5/min per `login_id`+ip) on top of the setup project's own login. The spec uses
  `storageState: '.auth/staff-5425.json'`, which the setup project regenerates **against
  `CRS_BASE`** — so the file matches whichever host the run targets. Recorded in the spec header.
- **F6 — the toggle input is visually hidden.** `.uncheck()/.check()` time out ("element is not
  visible"); the styled `.toggle-track` is the click target, and the assertions use
  `toBeChecked()` (the property, which works on a hidden input).
- **F7 — the row locator.** `page.locator('#tableBody tr', { has: badge })` where `badge` itself
  starts with `#tableBody` never matches: a `has` locator resolves *inside* the row. The row
  filter now uses `a.badge-link[href$="r<id>"]`.
- **F8 — `toRow()`'s validity branch must stay keyed on the STORED status.** Verified: the
  `$r->status === 'pending'` branch and the `slotValidity`/`conflictReason` decision read the raw
  column, so a completed row is treated as a **decided** row (`valid` + no conflict text). Only
  `$prettyStatus` is derived — no change needed, and no risk of a past row getting pending conflict
  text.
- **F9 — trap 4 is unreachable through the anchor.** `replacement_time_slot_id` is `NOT NULL` with
  `restrictOnDelete`, and `time_slots.venue_id` is an FK, so a real row always resolves a date.
  Rather than fake a schema state, the guard is tested at the helper level with an in-memory
  `TimeSlot` whose `venue_id` dangles (`999999`) — `replacementStrings()` returns `date => null`
  and the status stays `approved`. Stated in the changelog as a guard test, not a scenario test.
- **F10 — no HTTP-level API test exists** for `/api/requests`, so the API's derived status is
  covered **structurally**: `ApiReadController::myRequests()` maps through `toRow()`, whose
  `status` key is asserted directly in `ApprovalWorkflowTest`. No API suite is built (prompt §9).

### 🔴 Outstanding

None. Batch frozen; implementation proceeds.

---

## Round 2 (post-code verify) — 2026-11-03 · PASS

Commit `e2b2f4c`. Verified against the frozen `proposal.md` + `design.md` +
`specs/request-status-display/spec.md`, requirement by requirement, on the tree (not on intent).

### Verify — requirement by requirement

| Req | Verified in the code | Receipt |
|---|---|---|
| **R1** derived, never stored | `ReplacementRequestMapper::effectiveStatus()` (:85) — non-`approved` short-circuit, `null` date short-circuit, `startOfDay()` comparison | `ReplacementHistoriesTest` (past → Completed, stored status still `approved`, **0 audit rows**); `ReplacementHomeTest` matrix (yesterday/today/tomorrow); `ApprovalWorkflowTest` guard test |
| **R2** one helper, three producers | `grep 'completed'` in `app/` → exactly one definition (:100); three call sites: `toRow()` :131, `MyRequestHistory` :86, `ReplacementHome` :151 | the three filtered test runs |
| **R3** toggle on by default + home gains it | `resources/views/livewire/replacement-home.blade.php` `#completedToggle` + `#hideCompleted checked`, `completedHidden()`, `visibleBaseRows()`, filter clause :448, `updateResultCount` total :588, `updateSummary` total :667 | browser receipt 1 (checked on load → row absent → untick → badge → re-tick → gone) |
| **R4** the badge is a link | no new badge code — the badges lane's `statusBadge()` renders `completed` as `status-completed` (theme.css:3647) with `?notif-hl=r<id>` | browser receipt 1 (href asserted) + receipt 2 (click lands on the row) |
| **R5** reveal exception, sole cause only | `jumpToRequestRow()`: `otherFiltersActive` (status/search/week) blocks the un-tick; else visible un-tick + `saveFilters()` + `renderTable()` + recomputed index | browser receipt 2 (revealed, toggle un-ticked, `excludeCompleted:false` persisted) + receipt 3 (Pending filter → toast, toggle untouched) |
| **R6** final step timed by the class | `openModalById()` `completedTime` → `completedTime \|\| reviewTime \|\| '—'`, via `formatDate()` (string split) | code receipt + the modal-opening demo specs green |
| **R7** nothing else changes | no migration, no CSS, no notification; `toRow()`'s `slotValidity`/`conflictReason` branch still keyed on the **stored** status | `git show --stat` (18 files, none of them migrations/theme.css) + demo/viva row counts unchanged |

### 🔴 Fixed during apply

- **The home's narrow select omitted `replacement_time_slot_id`.** `effectiveStatus()` resolves the
  span through the anchor FK, so with the column unselected the helper saw "no slot" → `null` date
  → always `approved` — the rule would have been silently dead on the page that links to it. Caught
  by the new matrix test on its first run (expected `completed`, got `approved`); the select list
  and a why-comment now include it. Recorded as F1 in round 1 — the test proved it.
- **The reveal exception's first predicate was too weak.** It only required
  `status === 'Completed'` + the toggle, so a user with a **status filter** also had the toggle
  silently cleared. Browser receipt 3 failed on exactly that; the predicate now also requires
  `statusFilter === 'all'`, an empty search and `weekFilter === 'all'` (F3 in round 1).
- **Two Playwright mechanics** learned and fixed: the toggle input is visually hidden (click the
  `.toggle-track`, assert with `toBeChecked()`), and a `has:` locator must not start with
  `#tableBody` (it resolves *inside* the row).

### 🟡 Addressed

- The mapper instance is hoisted out of the row loop in `ReplacementHome` (`app()` per row was
  needless).
- `$latest->setRelation('classSession', $session)` reuses the session already loaded by the loop
  instead of lazy-loading a second copy per handled row.
- Auth for the `:8030` spec rides the setup project's `storageState` (regenerated against
  `CRS_BASE`), so no per-test logins fight the Fortify limiter.

### Receipts (measured)

| Receipt | Result |
|---|---|
| `composer run lint:check` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | **0 errors** |
| full suite (`DB_DATABASE=class_replacement_testing`) | **320 passed / 1723 assertions** (baseline 315 + 5) |
| `npx playwright test tests/request-completed-status.spec.ts` (`:8030`) | **3/3** (fixture planted, then swept: 0 requests / 0 exceptions afterwards) |
| demo read-only: `status-tooltips-filter-chips` + `request-approval` + `replacement-home-status-badges` (`:8000`) | **22/22** |
| demo DB before/after | 4 requests (max id 27), 1 exception, 19 audit rows — **unchanged**; viva DB 7 requests / 2 exceptions — **unchanged** |

**A note on the run itself:** one intermediate full-suite run was poisoned because a filtered test
run was started while it was in flight (both hit `class_replacement_testing`); it reported 309/320
with migration-collision errors that named the cause. The suite was re-run cleanly after a reseed —
the number quoted above is the clean run.

### 🔴 Outstanding

None. Lane `applied`. **Nothing archived** (the user asked to stop before archiving); nothing pushed.

### Honest gaps (carried into the changelogs)

1. The viva twin's request 27 reads **Approved today (3 Nov 2026)** and **Completed from 4 Nov** —
   C2 is a strict "before today". The live viva receipt today is the boundary half; the past-date
   half is receipted on the disposable testing DB.
2. The reveal exception's *other-cause* branches are code receipts + browser receipt 3 (status
   filter) — the demo DB cannot produce a Completed row at all.
3. The API's derived status is structural (`toRow()` is shared by the PL page and the API); no
   HTTP-level API test exists and none was built (prompt §9).
4. `effectiveStatus()` costs 2 extra queries per **approved** row (junction + venue). Measured and
   accepted; the alternative signature would have forked the call sites by page.
