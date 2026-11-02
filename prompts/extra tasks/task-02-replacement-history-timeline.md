# Extra task 02 — one honest timeline rail for the replacement modals

> **Status: ⏸ PARKED — do not start unless the user names it.**
> Added 2026-11-02, immediately after the investigation that found the fake date.
> **Temporary state already in the code:** the student page's rail is commented
> out (`resources/views/livewire/replacement-history.blade.php:387`, with a note
> pointing here). The timetable class-detail rail is still live and still
> partially hardcoded.

## Why (the finding, verified 2026-11-02)

There are **three** implementations of the same "Submitted → Under Review →
Awaiting Replacement" rail, and none of them reads a real timeline:

| # | Surface | Code | Problem |
|---|---|---|---|
| 1 | Student **Replacement History** modal | `replacement-history.blade.php:387–391` (now commented) | "Submitted" time came from `MockData.cohortTimetable.rsd3g2Flags` (`:329`); the backend ships that map **empty** (`ReplacementHistory.php:59`), so it always fell back to the literal **`01 Sep 2026, 09:15 AM`** — a fake date, while the same modal's *Requested At* row (`:383`) was correct. |
| 2 | Timetable **class-detail** modal | `public/js/ui-common.js:1268–1274` | "Submitted" time is real (`event.requestedAt` ← `ResolvesTimetableTimeline.php:422` ← `submitted_at`, MYT), but "In progress" / "Next" are literal strings, and every dot/state is hardcoded. |
| 3 | Lecturer **My Request History** modal | `my-request-history.blade.php:770–774` | Fully dynamic (label `r.status`, time `reviewedAt`), but step 2's time is the *decision* time, and `reviewedAt` still carries the UTC→MYT bug until the A+B+C fix lands. |

There is **no** timeline table/column in the database — the sequence is invented
in the browser from a single status flag.

## Goal

One rail, one source of truth, honest in every state:

1. **Submitted** — always; time = `submitted_at` (MYT), never a fallback literal.
2. **Under Review** — active while `pending` (time = "Awaiting PL", not a fake
   "In progress" clock); completed once decided, time = `decided_at` (MYT).
3. **Outcome** — the real one: `Approved` / `Rejected` / `Cancelled` (with the
   rejection reason in the tip where one exists), time = `decided_at` (MYT).
   Only an approved request gets the "Awaiting Replacement" step.
4. Nothing is rendered from a mock map; no literal dates anywhere.

## Files that would change

- `resources/views/livewire/replacement-history.blade.php` — restore the rail
  properly (delete the mock `rsd3g2Flags` lookup at `:329`; use `r.requestedAt`
  and a new `r.reviewedAt`/status pair from the component).
- `app/Livewire/ReplacementHistory.php` — add `reviewedAt` + the raw status to
  `historyRow()` (today it collapses status to `pending|replacement`).
- `public/js/ui-common.js` — the class-detail rail (same step vocabulary; keep
  the owner-only gate at `:1184`/`:1268`).
- `resources/views/livewire/my-request-history.blade.php` — align its rail with
  the same vocabulary (it already has the data).
- One changelog entry per touched page.

## Dependency (do that first)

**The A+B+C timestamp fix** (`prompts/pi-bug-notification-requested-at-timezone.md`)
introduces `App\Support\LocalTime::stamp()` and fixes `MyRequestHistory`'s
`reviewedAt`. Build this task on that helper — do not re-invent a second
timezone path.

## Decisions the user must make (ask before coding)

1. **Student page:** show the outcome step for rejected/cancelled requests too,
   or only for approved ones?
2. **Wording:** keep "Under Review / Awaiting Replacement", or use the status
   vocabulary already used by the badges ("Pending / Approved / Rejected /
   Cancelled")?
3. **Class-detail modal:** same rail for every role that can see the pending
   block (owner only today), or only the proposer?
4. **Past replacements:** does the rail still show for a replacement that has
   already taken place (today: no rail at all)?

## Acceptance checklist

- [ ] A pending request shows: Submitted (real MYT time) → Under Review (active) → no outcome yet
- [ ] An approved request shows: Submitted → Under Review (completed, decided time) → Approved → Awaiting Replacement
- [ ] A rejected request shows the rejection outcome and the reason is reachable (tip/row)
- [ ] A cancelled request shows Cancelled, not a stale "Awaiting Replacement"
- [ ] No hardcoded date anywhere (`grep -n "01 Sep 2026"` returns nothing)
- [ ] `rsd3g2Flags` is not consulted by any live page
- [ ] The same status renders the same rail on all three surfaces
- [ ] Dark + light, 390 px, no console errors
- [ ] `composer run lint:check`, phpstan 0, suite green (quote the measured count)

## Landing

Changelog entries for the touched pages, stale-cache restart (two separate
commands), live receipts as student `25DFT0001` + lecturer `5425`/`5770`, one
conventional commit (`ui:`), **no push**, then ask about archiving this file.
