# Proposal — arrangement-earliest-week-default

## Why (user idea 2026-10-20 + the grill decisions Q1–Q3)

On `/replacement-arrangement` (no URL week), the `#weekSelector` stands
up on `weekNav.load()`'s saved position — or **Week 1** when the storage
is empty (the boot's own comment: "restore the previously saved week
(localStorage), else falls back to week 1"). A fresh visitor then has to
hand-hunt for any week they can actually book in — while the page already
carries the exact "Earliest Bookable" truth internally
(`firstBookableDay(weekData)`, engine behind the page's own button, and
`weekHasBookableSlot` already filters the selector's list).

The ask: when landing **without a URL week**, stand up on the **Earliest
Bookable week**. The grill resolved the shape:

- **Q1**: this page only (`#weekSelector` on replacement-arrangement).
- **Q2 (A)**: a **saved week wins** when one exists; Earliest Bookable
  fills the memoryless void (the working-week promise intact — post-T6
  the storage holds only user-arrived or pinned positions).
- **Q3 (A)**: **silent landing** — no toast (the single-slot toast bar
  already serves the intent family; no new copy to freeze).
- **URL always wins**: the pinned bridge stays untouched and first.

## In scope

- One boot edit in `replacement-arrangement.blade.php`: probe
  `localStorage['arrangementWeek']` (and the legacy `currentWeek` key)
  strictly BEFORE `weekNav.load()`; when there is NO URL pin AND no
  saved week, set `weekNav._currentWeek = firstBookableDay(weekData).week`.
- A Playwright spec locking the priority chain (memoryless default /
  saved-week wins / URL-pin wins) + the signed changelog entry.

## Explicitly NOT in scope

- Any other page (venue / my-timetable / cohort — Q1).
- The saved-week memory's removal or demotion (Q2 = A keeps it honored).
- Any new toast/copy (Q3 = A silent).
- The pinned bridge (URL wins; unchanged) and the boot's tail anchor
  (`#slotTriggerText`, the pinned trigger note — untouched).
- The intent-landing contract: `?venue=&date=&time=` memoryless
  landings may still auto-jump via `resolveBookingIntent` when the
  booked date lives in another week — that path's own (existing, by-
  design) contract; this change only sets the stand-up BEFORE such a
  jump, never after it.
- WeekNavigator/ui-common internals (load's migration + filter-resolve
  untouched — the "saved-but-dead week snaps forward" fluency remains).

## Impact scope

| File | Change |
|---|---|
| `resources/views/livewire/replacement-arrangement.blade.php` | 1 edit: the pre-load memory probe + the memoryless default write (design D1) |
| `tests/arrangement-earliest-week-default.spec.ts` (NEW) | the 3-row priority chain |
| `page-changelogs/replacement-arrangement-changelog.md` | dated entry |

## Success criteria

- Fresh storage + plain landing → the selector (and the grid) stand
  on `firstBookableDay(window.weekData).week` — the same week the page's
  own "Earliest bookable" button would jump to.
- A saved `arrangementWeek` + plain landing → that saved week stands
  (the working-week promise, unchanged).
- A pinned arrival (`?session=…&week=14`) → the pinned week stands
  (13), default never applied (regression: `session-arrival-pin-guard`
  S1's selector + `arrangementWeek` asserts stay green).
- Silent: no toast on the memoryless plain landing.
