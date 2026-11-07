# Notes — Remove the “Change Venue?” confirmation modal (parked 2026-11-07)

> **Status: parked, not approved, nothing implemented.** This is the plan talk that
> went with the brief, kept so the thinking is not lost. The brief itself sits next
> to this file. Nothing here is an instruction to build anything.

## What the brief asks

Remove the **Change Venue?** pop-up on `replacement-arrangement`. The brief's own
investigation (still correct, re-checked 2026-11-07) is that there is **no separate
venue modal**: the pop-up is the page's one shared confirmation window, opened from a
single action (`onVenueChange()`, `resources/views/livewire/replacement-arrangement.blade.php`
~:1971). So “remove the modal” = remove **one trigger**, not any markup or styles.
The five other warnings (Change Subject?, Change Time Slot?, Unsaved Changes,
browser back/refresh, Clear All, Submit) must keep working untouched.

## Decisions taken (user, 2026-11-07 grill)

| # | Question | Answer |
|---|---|---|
| 1 | What happens to the picked slots when the venue changes? | **Unresolved** — see below |
| 3 | The shared confirmation window and the other five warnings | **A** — change nothing; remove only the venue trigger |
| 5 | The four automated checks that expect the pop-up | **A** — rewrite them to expect “no pop-up” (never delete) |
| 2, 4, 6, 7 | Same-venue re-pick; the subject/slot helper; feedback after a clear; the frozen mock-up page | Not answered — re-ask these before any work |

## The open question (why this is parked)

A picked slot remembers its own room. Checked in the code
(`ReplacementArrangement::submitBlocks()` → `$block['venue']`): **Submit books the room
carried by each picked block**, not the room on screen.

Real case: class **AMSE2003 (Lecture)**, 4 half-hour slots. Pick **B100 Mon 10:00–12:00**,
then switch the venue to **B110**:

- **A — picks move into the new room.** Same day/time kept, inside B110; a pick B110
  cannot take is dropped with a message. Safe, but it is a **new feature**, and the
  brief explicitly says not to attempt it in this task.
- **B — picks stay pointing at B100.** Nothing is lost, but the screen shows B110 while
  Submit would file the request for **B100** — a wrong-room booking. It also checks
  only B100's availability, so it cannot warn that B110 is busy. Safe only if we also
  keep the picks visible (“your picks are in B100”) and guard Submit.
- **C — clear silently.** Exactly what “Yes, Change” did today; smallest change, one
  room one selection, no mismatch. Recommended by the brief.

User leaned to **B**, then parked the whole task before deciding.

## If it is picked up again

1. Settle the A / B / C question above first — everything else depends on it.
2. Re-verify the anchors (line numbers in the brief are from 2026-11-05 and have drifted:
   `onVenueChange()` still starts at ~:1971 on 2026-11-07, `syncVenueFamily()`'s note ~:3981).
3. The remaining unasked questions: same-venue re-pick; leave the subject/slot helper and
   the “skip” flag alone; any feedback after a silent clear; whether the frozen mock-up
   page (`resources/views/ui-design-templates/…`) is synced in the same task.
4. Only then: the one-function change, the four rewritten checks, the changelog entry,
   and the manual checklist from the brief (§7).
