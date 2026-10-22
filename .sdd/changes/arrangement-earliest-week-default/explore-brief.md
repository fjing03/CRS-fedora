# Explore brief — arrangement-earliest-week-default

Source: the user's idea (2026-10-20: "make week selector default to the
Earliest Bookable week, while not via the URL") + the grill session
(the Q1/Q2/Q3 decisions below are USER-MADE + recorded) + the grounding
reads of the week-nav families across all four timetable surfaces.

## The grill's resolved decisions (all user-made)

1. **Scope (Q1)**: the `#weekSelector` on `replacement-arrangement`
   ONLY. venue-timetable / my-timetable / cohort-timetable untouched.
2. **Memory priority (Q2 = A)**: a saved week wins when one exists;
   **Earliest Bookable fills the memoryless void** (first visit /
   cleared storage). The saved-week memory stays a living feature
   (its entries = user-arrived OR pinned positions — a pinned arrival
   saves too; the default only fires on the both-keys-empty case).
3. **Affordance (Q3 = A)**: **silent landing** — no new toast (the
   page's single-slot toast bar already auto-fires on intent arrivals —
   the machinery behind the stash-proven C2 noise; a second toast would
   compete). The selector self-documents ("Week N · DD Mon YYYY ~ …").
4. **URL always wins** (the user's own words "while not via the URL"):
   the pinned bridge (`?session=…&week=` → `weekNav._currentWeek` +
   `selectWeek(pinnedIdx)`, boot :3500/:3519) stays untouched and
   ruthlessly first.

## The grounded fact map (reads, 2026-10-20)

- The four surfaces' stand-ups: venue = the real current week (the U2a
  clock-anchor bridge, no memory, no earliest button);
  my-timetable = the server's `$currentWeek` + its own memory; cohort =
  the server's `$currentWeek`; **arrangement = `weekNav.load()`
  (`arrangementWeek` storage, else week 1) + the pinned bridge wins**.
- The arrangement's WeekNavigator ctor (:1217) ALREADY carries
  `weekFilter = weekHasBookableSlot` — unbookable weeks are hidden in
  the select and **resolved away on load** (a saved-but-dead week snaps
  forward via `firstVisibleWeek` — WeekNavigator.load's tail, verified).
- `WeekNavigator.load()` (:read ui-common) migrations: `arrangementWeek`
  → falls back to the legacy `currentWeek` key one-time → clamps →
  filter-resolve. So the honest memoryless probe must read BOTH keys
  **strictly BEFORE `weekNav.load()`** (the migration would promote the
  legacy key during the run).
- The shared truth engine exists: `firstBookableDay(weekData)` /
  `firstBookableDayIn` (skips Sundays + holidays + the lead-time
  too-soon days) — the arrangement's own "Earliest bookable" button is
  built on it. No new rule.
- Boot ordering (verified): `weekNav.load()` :3493 → pinned bridge
  :3500 → `populateWeekSelect(selected: weekNav.currentWeek)` →
  `applyUrlParams()` (the venue param path) → `__bootDone = true`
  → the unconditional `buildTimetable()` ("ensure grid always renders
  on load"). The default must therefore write `weekNav._currentWeek`
  BETWEEN `load()` and `populateWeekSelect(...)` so the selector and the
  grid land on the same week.
- `window.weekData` is exposed on the arrangement (:1206) — the spec can
  assert against `firstBookableDay(window.weekData)` self-consistently.

## Rejected approaches

1. **A ui-common/global default** (change WeekNavigator.load's default
   for every page) — violates the user's page-scoped decision (Q1);
   touches venue/my/cohort landings. REJECTED.
2. **Always-earliest (memory demoted)** (the grill's Q2 option B) — the
   user chose A (the saved week = "your working week", post-T6 it holds
   only click-driven positions).
3. **Landing toast** (Q3 option B) — the user chose A (silent; toast-bar
   collision risk with the intent family).
4. **Replacing the "Earliest bookable" button with the default** — the
   button stays (one click for a deliberate jump; the default = the
   stand-up, the button = the on-demand action).
5. **Hooking `jumpToToday`/`firstVisibleWeek` inside WeekNavigator** —
   page-specific priority chain (pin > saved > earliest) belongs to the
   arrangement boot, not to the shared class (the same separation the
   T6 gate kept).

## Final solution (the priority chain)

`?session=…&week=` pin → **saved week** (`arrangementWeek`, else the
legacy `currentWeek` migration) → **Earliest Bookable**
(`firstBookableDay(weekData).week`, silent) → week 1 (the last fall-
through, unreachable while any bookable week exists). The selection is
written to `weekNav._currentWeek` pre-`populateWeekSelect` (selector +
grid agree); nothing is saved during the boot for the default case (the
memoryless state stays memoryless until the user navigates — by design).

## Key cross-module data flow after

plain no-URL no-memory `/replacement-arrangement` → boot → `hadSavedWeek`
probe (both keys, pre-load) → `weekNav.load()` (their migration/filter
resolve intact) → memoryless branch → `weekNav._currentWeek =
firstBookableDay(weekData).week` → `populateWeekSelect(selected)` →
`buildTimetable()` renders that week → silent. With a pin: the bridge
overwrites as today; with a saved week: `load()` keeps it today.

## Known open questions

None — the grill resolved Q1–Q3; the intent-override nuance (a
`?venue=&date=&time=` memoryless landing's `resolveBookingIntent` jump
wins over the default) is BY-DESIGN existing behavior, recorded in the
design (not an open question — the intent's own landing contract
already owns that path; the noisy C2 family's asserts).
