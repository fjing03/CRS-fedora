# review-log — `arrangement-week-keep-on-subject-switch`

## proposal Round 1 — 2026-11-08 17:16

**Reviewer:** separate read-only `pi` subprocess (`commandcode/z-ai/glm-5.3-flash`,
`--tools read,grep,find,ls`), baseline `explore-brief.md`.
**Verdict:** "Ready to proceed, with the 🟡 items fixed first." No 🔴.

### 🔴 Fixed
 - (none — no blocking issue found)

### 🟡 Addressed
 - **Clash-less subject switch was only implied, not contracted** — when the new subject has no
   conflicted occurrence there is no pinned class, no settled room and no jump today. Added a
   "States this change does NOT touch" table to `proposal.md` covering `firstConflictRow === null`,
   the pre-boot arrival, an armed booking intent, and the `?session=&week=` arrival (brief §5.3).
 - **A "measured" slot-box label quoted the code comment's format example, not the measurement** —
   corrected to the live value from brief §1 (`Week 14 · Dec 24, 2026 · 9:00 AM ~ 11:00 AM`).
 - **The armed-intent ordering must not be inverted during design** — named in the new table as
   "this decision must never override an armed intent", and carried to design.md as a required
   question (`consumeBookingIntent()` runs after this decision).

### 💡 Taken
 - Added the two-minute manual receipt (session 99 → Week 12 → switch to AMSE2003 → Week 12 kept) to
   the test plan.

### 🔴 Outstanding
 - (none) — proposal.md is **frozen** after this round.

## design Round 1 — 2026-11-08 17:28

**Reviewer:** separate read-only `pi` subprocess. Baseline: frozen `proposal.md` + `explore-brief.md`.
**Verdict:** "Not ready for `/sdd-apply` — one blocking fix round needed."

### 🔴 Fixed
 - **The free-cell rule was built on an inverted model of `MockData.venueSlots`.** I had written "iterate
   the room's slot rows and return `true` on the first row the grid would paint available". Verified the
   reviewer's correction in `ReplacementArrangement::venueSlotsByWeekPayload()`: the payload emits rows
   **only** for `time_slots` with `status IN ('occupied','pending')`, so a row means *taken* — a cell with
   no row is the free one (the grid's `classifyState()` → `null` → `.cell-available`). Rewrote §2.2 as a
   **span walk** (6 × 22, day/hour gates + no occupancy row) with a per-week taken-set, and re-derived
   §5 scenario 2 so the fixture reserves the **settled room** and the expectation is now satisfiable.

### 🟡 Addressed
 - **§2.3 over-claimed where the venue settles** — added the honest exception: `syncVenueFamily` falls
   back to `filtered[0]` when the pinned room is ineligible (measured unreachable per brief fact 4, but
   now stated rather than implied).
 - **Fail-open could have violated D5** — scoped it: the **date rule is evaluated first and is never
   fail-open**; only the free-cell half fails open.
 - **§5 scenario 2 named an ambiguous room** — now explicitly the **post-switch settled room**
   (`firstConflictRow.venue` of the target), not the currently shown subject's room.
 - **§5 scenario 3 did not exercise the skip** — rebuilt: keep the user on session 67's own clash week
   (week 6), fill the settled room there, expect the walk to pass **over** week 6 to week 7.

### 💡 Taken
 - Documented the `venueEligible()` capacity / `blockedVenues` duplication as intentional (keep, comment,
   never "fix" into drift).
 - Added **Risk 3** — the miss path can move the user *backward* (follows D5; will be noted in the page
   changelog).
 - Stated that `window.__switchWeek` is a receipt/debug surface only (house pattern `window.classifyCell`).

### 🔴 Outstanding
 - (none) — design.md is **frozen** after this round.

## design Round 2 — 2026-11-08 17:34

**Reviewer:** separate read-only `pi` subprocess. Verdict: "design.md is decision-level correct and ready
to freeze" — **no 🔴**, round 1's blocker verified resolved against source.

### 🔴 Fixed
 - (none this round — the round-1 blocker was confirmed resolved: the payload really is a TAKEN register,
   `time_slots` rows are per half-hour, and the eight gates match the grid line-by-line.)

### 🟡 Addressed
 - **§5 scenario 4's fixture direction was inverted** (an *empty* taken register means maximally free, so
   the `'none'` branch would never run). Re-derived: the stub now fills the taken register for **every**
   week in the settled room, then asserts `why: 'none'` with the kept week returned.

### 💡 Taken
 - §2.3's exception now also names the `:4072–4075` nuance (an ineligible pinned room settles on the
   *current* venue when that one is still eligible, else `filtered[0]`).
 - §7's cost note re-worded to the actual shape (taken-set built once per week, then a span walk).

### 🔴 Outstanding
 - (none) — design.md is **frozen** after this round.

## tasks Round 1 — 2026-11-08 17:44

**Reviewer:** separate read-only `pi` subprocess. Verdict: "Ready to proceed to `/sdd-apply` once the four
tasks-level fixes are made" — **no 🔴**; the `specs/` skip judged **defensible** (single page, no
cross-module contract).

### 🔴 Fixed
 - (none)

### 🟡 Addressed
 - **AC5 had no witness, and the done-when claimed all six criteria** — added scenario 5 (kept week
   contains the subject's own clash → `why:'kept'`), whose discriminating witness is the decision flag,
   not the week index (old and new code agree on that index).
 - **`decideSwitchWeek` was needed by the spec but not exposed** — T1 now exposes all three helpers on
   `window.__switchWeek`; T4 calls the decision through it instead of re-implementing it.
 - **T4's keep-list was incomplete** — now names verbatim what must survive: the fixture setup +
   `beforeEach`/`afterEach` discipline, the pin assertions, the **venue-sync witness**, the
   `span-follow-subject-switch` test, and the hidden-stale-panel quirk test.

### 💡 Taken
 - T3's header now distinguishes the read-only demo leg from the testing-DB fixture leg.
 - T7 names real suites (`LtVenueCapacityRuleTest`, `VenueTimetableTest`).
 - T4 scenario 3 records the measured landing week instead of hard-coding week 7.
 - T4 scenario 4 restores the stubbed taken register afterwards.

### 🔴 Outstanding
 - (none) — tasks.md is **frozen** after this round.

## verification — 2026-11-08 (post-apply)

**Method:** artifacts vs the working tree (no reviewer subprocess — this is the apply-gate check).

| Check | Result |
|---|---|
| Tasks | 7/7 ticked, 0 open |
| Scope | blade + spec + changelog only; `git diff --stat` on `public/js/ui-common.js`, `app/`, `resources/views/partials/` is **empty** (no shared helper, no server, no partial changed) |
| Design §2.2 gates | all six present in the implemented order (date rule → fail-open guards → blocked-venue → `venueEligible` → taken-set span walk with `isSlotTooSoon` + busy hit); `taken` built from the room's occupancy rows |
| Design §2.3 settled room | predicate reads `row.venue` (= `firstConflictRow.venue`) |
| Design §3 | `decideSwitchWeek` returns `{week, why}`; `selectWeek` called **only** when the target differs; the three assignments stay inside that branch |
| Design §6 byte-identical | auto-pin (:3615), tail `selectSlot(... {auto, subjectSwitch})` (:3723), `consumeBookingIntent()` (:3670, still after the decision), `saveCurrentWeek()/buildTimetable()` (:3674) all untouched |
| AC1–AC6 | spec 10/10 green (3rd run on the final tree), residue 0 after each run; red-check (old jump restored) fails the "week stays" assertion |
| Gates | pint passed, phpstan 0, `view:cache` OK, targeted PHPUnit 49/49 |

**💡 declarative drift:** design §1 row 3 still says "the two helpers"; the tasks round-1 fix and the
implementation expose **three** (`bookable`, `earliest`, `decide`). No decision changes — noted only.

**Verdict:** PASS.
