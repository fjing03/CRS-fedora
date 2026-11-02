# Proposal — venue-status-parity (Venue Timetable & Cohort Timetable bug batch)

**Date:** 2026-11-02
**Ask:** `prompts/BUG Investigation — Venue Timetable & Cohort Timetable.md` (4 reported bugs + audit), triaged with the user bug-by-bug.
**Evidence:** the read-only investigation report delivered to the user before any edit (live DOM dumps on `:8000`, read-only SQL, code tracing).

---

## Why

Three pages render the same academic truth. Two of them (`/my-timetable-ui`,
`/cohort-timetable`) read the shared timeline + fact contract, so they tell the truth
about a cancelled class, a live proposal and a public holiday. The venue page
(`/venue-timetable-ui`) hand-rolls its payload and its block HTML, so on the same week
it shows a cancelled class as a live class, hides the date a request was submitted,
**deletes a class entirely** when that class carries a proposal, and labels a block
reference with the wrong id. The cohort page shows a `Replace Now` button for a class
that already has a proposal (a dead end) and hides it for a cancelled class that
actually needs one.

## What (in scope — every item carries the user's decision)

### The four reported bugs

| # | Fix | User ruling |
|---|---|---|
| 1 | Venue pending block shows `(Pending since 02 Nov 2026)` | — (folder into the shared note renderer) |
| 2 | Venue cancelled class renders as a conflict: red block, `(Cancelled)`, tip `CANCELLED`, popup `Status: Cancelled` | — |
| 3 | A class that carries a **pending** proposal is no longer deleted from its conflict week; it renders as red + `(Proposed ⏳)` | the user's report; reading confirmed implicitly by "go" |
| 4 | `Replace Now` visibility = the served conflict fact on **both** pages (hidden when a live proposal handles the pair, shown for a cancelled class) | — |

### Additional bugs (audit) — approved set

| # | Fix | User ruling |
|---|---|---|
| 5 | Venue `Class Ref` shows the class id (`#40`, `#23`), never a slot id (`#34413`); derived blocks gain one too | approved |
| 6 | Venue pending popup gains the shared `View Full Request` action | approved |
| 7 | Venue cancelled popup no longer offers `Cancel Class?` | approved |
| 8 | A pair that is both cancelled **and** on a holiday renders `(Cancelled)` instead of vanishing | approved |
| 9 | `Total Slots` becomes `X+Y` (`118+2`): `X` = settled slots, `Y` = pending-held slots | the user's own `+` idea; tail ALWAYS shown (`+0` included — recommended option A) |
| 10 | Cancelled popup's `Status Description` says something cancelled, not "Scheduled class with no issues" | approved |
| 11 | A cancelled class **keeps counting** as `Occupied` (only the look changes) | **A** |
| 12 | Venue `Replace Now` uses the same shared rule as the other pages (also covers the holiday-day half) | **A** |

### Rulings recorded

- **`Available` stays plain** (`56`) — it is already the *post-hold* bookable count; a `-` would subtract the same slots twice (the user's A).
- **`Total Slots` = `X+Y`** with the tail always visible — same law as the four cohort cards already shipped.
- **No change to which slots are counted** for `Occupied` / `Available` (only `Total`'s display gains the `+Y` tail).

## Non-goals

- **No rewrite** of `VenueTimetable::render()` onto `ResolvesTimetableTimeline` (tempting, but a different lane: the venue grid is a slot grid, not a session grid). Only the *laws* are shared, via the existing `LectureSessionCatalog` contract.
- No booking/lead-time/holiday rule changes. No DB changes. No new dependency.
- `/my-timetable-ui` and `/student-my-timetable` behaviour must stay byte-identical (they are the reference, not the target) — except bug 10's copy, which is shared and wrong on all three pages.
- No change to the `(Proposed ⏳)` / `(Cancelled)` / `(Replaced for …)` wording (single source: `buildReplacementNote`).

## Success criteria

1. Same week → the three pages agree on: status, note text, block colour family.
2. `/venue-timetable-ui?venue=B010` week 14 shows `AMSE2003(P)` as red + `(Proposed ⏳)`; week 7 amber reads `(Pending since 02 Nov 2026)`; `/venue-timetable-ui?venue=B009` week 7 shows `BMSE3153(P)` as red + `(Cancelled)`.
3. Every venue popup's `Class Ref` equals the my-timetable value; the popup offers `View Full Request` (own pending) and never `Cancel Class?` on a cancelled class.
4. `Replace Now`: hidden on cohort W14 `AMSE2003(P)` (proposal exists), visible on cohort W7 `BMSE3153(P)` (cancelled) — matching my-timetable both times.
5. `Total Slots` = `118+2` on B010 week 7; `Available` unchanged (`56`).
6. Full PHPUnit green, PHPStan 0, pint clean; new receipts for every row above.
