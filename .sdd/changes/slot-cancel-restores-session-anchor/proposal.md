# Proposal — slot-cancel-restores-session-anchor

## Bug (user-reported 2026-10-28, staff 5425, `?session=68&week=14`)

After selecting a grid slot and choosing a DIFFERENT dropdown occurrence,
clicking **"No, Keep My Selection"** resets `#slotTriggerText` to
`Select a slot to replace` while the Conflict-Schedule strip and
`window.pinnedOccurrence` stay on the pinned occurrence — the trigger and
the strip disagree.

## Root cause (verified — the investigation receipts stand in review-log)

On `?session=` handoff landings the pinned slot is deliberately NEVER
committed through `selectSlot` (the legacy pick branch is gated off by
`!urlParams.session`; `applyDefaultSlotSelection` early-returns; the boot
tail writes the trigger **cosmetically** from `anchorSlotFromPinned()`,
blade ~:4042–4056, *"no selectSlot, no confirm modal"*). So
`lastSlotIndex === null` stands by design. The "No, Keep My Selection"
cancel arm (`selectSlot` ~:3501–3511) reads `lastSlotIndex === null` as
"nothing was ever selected" and runs its else arm —
`selectedOriginalSlot = null; slotTriggerText = 'Select a slot to
replace'` — blanking the trigger while the pinned anchor still stands.

The ELSE arm's original semantic (fresh, anchor-less states) is CORRECT;
only its trigger line wrongly swallows the handoff state.

## The fix (one helper, two call sites)

1. A local helper reusing the ONE shared format (`slotTriggerLabel`,
   the trigger's own "ONE format for the two writers" law):
   ```js
   function writeSlotTriggerFromAnchor() {
       const a = anchorSlotFromPinned();
       if (!a) return false;
       document.getElementById('slotTriggerText').textContent
           = slotTriggerLabel({ week: a.week, start: a.start, end: a.end, date: a.date });
       return true;
   }
   ```
2. **Boot tail** (~:4049): the inline format builder is REPLACED by the
   helper (kills the duplicated format — a third writer without sharing
   would drift again).
3. **Cancel else arm** (`selectSlot` ~:3504): after
   `selectedOriginalSlot = null` + the `.slot-dd-item` de-selection,
   call the helper; keep `Select a slot to replace` ONLY when the helper
   returns false (a genuinely anchor-less state — the original else-arm
   semantics preserved).

## Behaviour table (the frozen contract)

| State | Trigger after "No, Keep My Selection" |
|---|---|
| Session handoff (pinned anchor stands) | re-written to the pinned occurrence's label: `slotTriggerLabel(anchor)` |
| Non-handoff, anchor-less | `Select a slot to replace` (unchanged) |
| `selectedOriginalSlot` | null in BOTH cases (the pre-modal truth) |
| `.slot-dd-item` classes | de-selected (unchanged) |

## Out of scope

- The "Yes, Change" path (explicit discard → commit) — untouched.
- `discardAllSelections()` / `lastSlotIndex` lifecycle — never reset by
  anything but a pick (grep-frozen); no second path exists.
- Strip/pinnedOccurrence writers; the guard itself; the modal machinery.

## Success criteria

- Your exact flow: after "No, Keep My Selection" the trigger equals the
  landing label (`W14 · Dec 24 · 11:00 AM–12:00 PM`), the strip and the
  badge community unchanged; a real panel pick after the cancel still
  routes the full selectSlot path.
- The non-handoff reset byte-identical to today (probe receipt).
- Gates: `php -l`, lint:check + phpstan 0 (Blade-only), suite ≥266/266,
  changelog entry, ONE commit (no push — user pushes manually).
