# tasks — `arrangement-week-keep-on-subject-switch`

Frozen priors: `proposal.md`, `design.md` (both reviewed). Baseline: `explore-brief.md`.

**Specs batch skipped on purpose** — this is a single-page, single-module client change with no
cross-module contract to pin: the behavioural contract already lives in the proposal's six acceptance
criteria and the design's §2.2 gate table + §3.1 state table. Nothing downstream (no API, no payload,
no schema) needs a capability spec.

Every task is ≤ 2 h. Order matters: T1–T2 are the change, T3 is the honest manual receipt, T4–T5 the
automated receipt, T6–T7 the paperwork and gates.

- [x] **T1 — the predicate + the decision, page-local** (`resources/views/livewire/replacement-arrangement.blade.php`)
  Add, beside `venueEligible()`/`venueMatrixAllows()`: `weekBookableForSubject(weekIdx, row)` and
  `firstBookableWeekForSubject(row)`, and `decideSwitchWeek(row)` returning `{week, why}` with
  `why ∈ {'kept','earliest','none'}`. Implement exactly design §2.2: the **date rule first**
  (`weekHasBookableSlot`, never fail-open), then a span walk (days from `weekData[weekIdx].days`, hours
  from the shared `hours`) where a cell is free when the day is not Sunday/holiday, `!isSlotTooSoon(...)`,
  `venueEligible(venueRecord, courseCtx())`, the room is not in `blockedVenues[sessionKey]`, no
  busy-vector hit (`b[0]===day && hour>=b[1] && hour<b[2]`), and **no `MockData.venueSlots[weekIdx][room]`
  row at that `(day, hour)`** (the payload is a TAKEN register). Build the taken-set once per week.
  Fail-open only the free-cell half (missing payload → treat as free). Expose **all three**
  (`weekBookableForSubject`, `firstBookableWeekForSubject`, `decideSwitchWeek`) on `window.__switchWeek`
  (read-only, next to `window.__venueSelected`) with a comment that it is a receipt/debug surface, not
  page UI — T4's scenario 4/5 call the decision through it.
  **Done when:** the helpers exist, the file parses (`php artisan view:cache`), and no other behaviour
  changed.

- [x] **T2 — swap the jump for the decision** (same file, the `:3652–3658` block)
  Replace the unconditional `weekNav.selectWeek(firstConflictRow.week)` + `currentWeek/window.currentWeek/
  saveCurrentWeek()` with: compute `target = decideSwitchWeek(firstConflictRow)`; call `selectWeek(target.week)`
  and the three assignments **only when** `target.week !== weekNav.currentWeek`. Leave the guard
  (`firstConflictRow && window.__bootDone && typeof weekNav !== 'undefined'`), the tail
  (`selectSlot(...{auto,subjectSwitch})` / `syncVenueFamily`), `consumeBookingIntent()`'s position, and
  the trailing `saveCurrentWeek()`/`buildTimetable()` untouched. Comment the block with the D1/D5/D6
  rulings (keep the pin, no week jump, silent when nothing is bookable).
  **Done when:** a subject switch on the demo page leaves the week alone when it is bookable, and every
  other path (pinned arrival, intent, venue change, slot pick, submit) behaves as before.

- [x] **T3 — the manual receipt (demo DB leg read-only; the unbookable leg is a testing-DB fixture)**
  Staff 5425 on `:8010`: open `?session=99&week=8`, move to **Week 12** by hand, switch to **AMSE2003** →
  the week must stay **Week 12** while the slot box still names `Week 14 · Dec 24, 2026 · 9:00 AM ~ 11:00 AM`
  and that slot stays ticked; the room still settles on the pinned class's room. Repeat with the week on
  a fully-taken room (SQL fixture on the testing DB, not the demo) to see the earliest-bookable move.
  Record both outcomes (numbers + screenshot paths) in the changelog.
  **Done when:** the two outcomes are written down with evidence.

- [x] **T4 — rewrite `tests/arrangement-conflict-autoselect.spec.ts`**
  **Keep verbatim** (do not drop while rewriting): the fixture setup (cs 67 @ week 6, cs 23 @ week 9,
  testing DB only, SQL-guarded, delete-then-insert keyed by `session:week`, swept in `afterAll`), the
  `beforeEach`/`afterEach` re-insert discipline those tests lean on, every "which class got pinned"
  assertion, the **venue-sync witness** (the only automated proof that T2 leaves the venue tail alone),
  the `span-follow-subject-switch` test (its span law is week-independent and survives this change), and
  the hidden-stale-panel quirk test.
  **Flip:** each "the grid jumped to the occurrence's week" assertion → "the week stayed where the user was".
  **Add five scenarios:**
  1. kept week bookable → week stays (session 99 → move to Week 12 → switch to AMSE2003).
  2. kept week not bookable → earliest: fill the **post-switch settled room** (`firstConflictRow.venue` of
     the target) for the kept week with `status='pending'` half-hour rows, sweep after → expect a later week.
  3. the walk skips a clash week with no free cell: keep the user on session 67's clash week (week 6), fill
     the settled room there → expect the week `decideSwitchWeek` returns (record the measured index; do not
     hard-code "week 7" — the seed's cut-off decides), and assert it is **not** week 6.
  4. nothing bookable → `decideSwitchWeek` returns `why:'none'` and the kept week, with the in-page taken
     register stubbed full for **every** week in the settled room (rows per half-hour; an *empty* register
     means maximally free) — **restore the stub afterwards**, since the grid reads the same `MockData.venueSlots`.
  5. **AC5 witness**: kept week **contains the subject's own clash** and has a free cell → the decision says
     `why:'kept'`. Old and new code agree on the resulting week index here, so the discriminating assertion
     is the decision flag (via `window.__switchWeek`), not the index.
  **Done when:** the spec compiles under the repo's Playwright/ESM conventions (no `__dirname`), and each of
  the six proposal acceptance criteria has at least one witness (1→sc1, 2→sc2, 3→sc3, 4→sc4, 5→sc5,
  6→the kept verbatim arrival/intent/venue/submit witnesses).

- [x] **T5 — run the receipt against the testing DB**
  `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`, serve `:8030` via
  `scripts/dev-serve.sh 8030`, then `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030
  npx playwright test tests/arrangement-conflict-autoselect.spec.ts` (no `--no-deps`). Re-run once to prove
  the fixture sweep leaves no residue; stop the server; reseed.
  **Done when:** green, twice, with the pass counts recorded in the changelog.

- [x] **T6 — paperwork**
  `page-changelogs/replacement-arrangement-changelog.md`: a dated entry (the bug, the decision table
  D1–D8 in one line each, the measured before/after, the receipts) including the two honest notes: the
  miss path can move the user **backward** (D5) and `window.__switchWeek` is a debug surface only.
  **Done when:** the entry is at the top of the file and matches the code.

- [x] **T7 — gates**
  `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`,
  `php artisan view:cache`, plus the targeted PHPUnit suites that touch this page
  (`ReplacementArrangementTest`, `MakeUpBusyVectorTest`, `LtVenueCapacityRuleTest`, `VenueTimetableTest`)
  to prove nothing server-side moved.
  **Done when:** all green and recorded; the one commit (`fix(arrangement): …`) stages only this lane's
  files.

## Out of scope reminders (do not "tidy" while implementing)

`WeekNavigator`, `weekHasBookableSlot`, `firstBookableDay`, `ui-common.js`, the arrival/landing rules, the
booking-intent paths, the venue rules, the server payloads, the schema, and any new UI text or tooltip.
