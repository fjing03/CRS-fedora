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
