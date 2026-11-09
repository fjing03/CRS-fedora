# proposal — `arrangement-week-keep-on-subject-switch` (2026-11-08)

**Approval-gated. No code written yet.** Baseline for this proposal is
`explore-brief.md` in the same folder (8 user decisions, measured live on the demo data).

## Why

On the **Replacement Arrangement** page, changing the **Subject** dropdown throws away the week the
user is reading: `applySubjectChange()` auto-pins the new subject's first clashing class **and jumps the
Week dropdown to that class's week**. Measured (staff 5425, demo DB, 2026-11-08):

| Step | Week shown |
|---|---|
| Open for AMSE1003 → page lands on Week 8 | Week 8 |
| User browses to **Week 12** | Week 12 |
| Switch subject → AMSE2003 | **Week 14** (the new subject's clash week) |

The user's brief asks for the week to stay where it is when the kept week is usable for the new
subject, and to move to the **earliest usable** week otherwise — never to the clashing week just
because it clashes.

## What changes (behaviour, not implementation)

1. **On a subject switch, the week no longer jumps to the clash week.** It stays on the week the
   selector is showing at that moment **if that week is bookable for the newly selected subject**.
2. **"Bookable for the subject"** means: the existing date rule (not Sunday, not a holiday, not inside
   the 3-working-day cut-off) **and** at least one free cell in the room the page settles on — the
   pinned class's own room. A week that holds the subject's own clash still counts as bookable when a
   free cell exists.
3. **If the kept week is not bookable**, the page moves to the **earliest bookable** week for the new
   subject — skipping any week with no free cell.
4. **If no week is bookable at all**, the week is left exactly as it is, silently. No toast, no reset.
5. **"The week the selector shows"** means any week — the one restored from the last visit, the one the
   landing rule picked, or the one the user moved to by hand. All three count.
6. **Everything else about a subject switch stays byte-identical**: the earliest clash is still
   auto-pinned, the slot box still names it (measured on the demo run: `Week 14 · Dec 24, 2026 · 9:00 AM ~ 11:00 AM`), it is still
   auto-ticked, the venue still syncs to that class's own room, and the URL truth is unchanged.

## States this change does NOT touch (the contract, not an implication)

| State at switch time | Behaviour after this change |
|---|---|
| No subject selected before (`firstConflictRow === null`) — the new subject has **no clashing occurrence** | **Unchanged** (picker-first: no pin, no jump — the week stays because nothing ever moved it) |
| Pre-boot arrival (`window.__bootDone === false`) — e.g. a `?session=…` landing | **Unchanged**: the landing rule owns the week (earliest bookable), the pin rides the strip |
| A **booking intent** is armed (`?venue&date&time`) | **Unchanged**: the intent keeps owning the week; `consumeBookingIntent()` still runs after this decision and may set the week it was given. This decision must never override an armed intent |
| A pinned arrival with `?session=&week=` | **Unchanged**: `?week=` still does not position the grid (`ARRIVAL-GRID-NO-PIN`) |

## In scope

- `resources/views/livewire/replacement-arrangement.blade.php` — the post-boot subject-switch path only
  (`applySubjectChange()` and the one decision that replaces its `weekNav.selectWeek(...)` call).
- `tests/arrangement-conflict-autoselect.spec.ts` — rewritten (see below).
- The page's changelog entry.

## Explicitly out of scope

- **Every other way the week can move**: pinned arrivals (`?session=…` still lands on the earliest
  bookable week), the booking-intent resolve (`?venue&date&time`), the booking-intent snap-back, the
  saved-block restore, the arrows / dropdown / "Earliest bookable" button, and the pre-boot guard.
- The **auto-pin** and the **auto-commit** of the pinned slot (user decision D1 = keep both).
- The **venue rules**, the submission path, the server payloads, the database and the schema.
- Other pages (My Replacements, the venue and cohort grids) and the shared `ui-common.js` behaviour of
  `weekHasBookableSlot()` / `WeekNavigator` — the new "free cell" test is page-local.
- Any new UI text, label, tooltip or hint.

## Impact scope

| Surface | Effect |
|---|---|
| One Blade view (page-local JS) | the subject-switch week decision |
| Browser only | no server round-trip, no route, no Livewire action |
| Database / schema / migrations | none |
| Dependencies | none (reuses `weekData`, `MockData.busyByWeek`, `MockData.blockedVenues`, `window.__ruleMap`) |
| Other pages | none — the change is confined to this page's script |

## Acceptance criteria (behavioural)

1. Switch subject while the visible week is bookable for the new subject → the week does not change;
   the slot box still names the auto-pinned clash and it stays ticked.
2. Switch subject while the visible week is **not** bookable for the new subject → the week becomes the
   earliest bookable one.
3. Several clashing weeks for the new subject → the search skips any week with no free cell (it is not
   "the first clash's week").
4. No bookable week at all → the week stays put, no message, no error.
5. A visible week that contains the new subject's own clash is kept when it has a free cell.
6. Pinned arrival, booking-intent flows, venue behaviour, slot picking and submit are unchanged.

## Test plan

Rewrite `tests/arrangement-conflict-autoselect.spec.ts` (testing DB only, `:8030`, SQL-guarded): keep
its clash fixtures (session 67 @ week 6, session 23 @ week 9) and its "which class got pinned"
assertions; flip its "the grid jumped to the occurrence's week" assertions to "the week stayed"; add the
four scenarios above. Two-minute manual receipt on the demo DB: open for session 99, move to Week 12
by hand, switch to AMSE2003 → Week 12 must still be shown.

## Risk

Low and reversible: one five-line decision replaces one call, entirely client-side, with no server or
data involvement. The realistic failure mode is a **stale-looking pin** — the slot box naming a week
other than the one on screen (accepted by D1; the box carries its own week label). Second risk: the
free-cell test disagreeing with the grid's own painting — mitigated by reusing the same three inputs the
grid uses (venue matrix, blocked venues, busy vectors).
