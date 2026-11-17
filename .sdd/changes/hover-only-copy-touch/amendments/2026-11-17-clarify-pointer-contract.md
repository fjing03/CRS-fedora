# Amendment — the pointer contract (clarify pass, 2026-11-17)

Unfreezes the frozen proposal at decision level: the P2 item asks to "extend the contract" to the legend,
which is new scope, not a wording tweak. Round 3 review is required before apply.

---

## 1. The item's claim, tested

> "the legend literally says **'Hover a colour to learn more'** — an instruction that does nothing on the
> iPad your own rulebook protects"

**Half of that is not true, and it is the same method error withdrawn earlier today.** Measured with touch
emulation on My Timetable:

| Context | `(hover: none)` | `(any-pointer: coarse)` | Copy actually rendered |
|---|---|---|---|
| desktop 1440 | false | false | "Hover a colour to learn more" |
| iPad 1024, touch | **true** | **true** | **"Tap a colour to learn more"** |
| phone 390, touch | **true** | **true** | **"Tap a colour to learn more"** |

`resources/views/partials/ui-legend-bar.blade.php:11` ships **both** strings and `theme.css:2283-2284` swaps
them under `@media (hover: none)`. So the instruction is already honest on the iPad — the swap exists, exactly
as the item's own "fix" line concedes. The claim rests on a desktop screenshot read as if it were an iPad.

## 2. What is actually broken — the promise, not the wording

The copy says **"Tap a colour to learn more."** It cannot be kept:

- `.legend-item` is `<div class="legend-item" data-tip="…">` — **`tabindex` is `null` in every context**
  measured (desktop, iPad, phone).
- The engine has **no touch path** (`mouseenter`/`mouseleave`/`mouseout`/`focusin`/`focusout` only).
- Therefore nothing can summon those four tips on a touch device: not a tap (no handler), not focus (not
  focusable).

So the iPad user reads an instruction to tap, taps, and gets nothing. The word is right; the behaviour behind
it is missing. **That is worse than the original claim** — a wrong instruction is a bug, an instruction that
names the correct gesture and still does nothing is a broken promise.

**Same defect, second location:** `resources/views/livewire/student-my-timetable.blade.php:32` —

> "Colours — Normal Class, Replacement, Pending and Conflict / Public Holiday. **Hover** a colour in the
> legend to see what it means."

One string, no swap, wrong on touch in the help text a first-time user is most likely to open.

## 3. Third finding — the swap is on the weaker signal

The existing swap keys on `@media (hover: none)`. On a **hybrid** device — Surface, a touchscreen laptop, an
iPad with a trackpad — `hover: none` is **false** whenever any hovering pointer exists, so the legend keeps
saying "Hover a colour" while the user's finger is on the screen. The house capability law everywhere else is
`@media (any-pointer: coarse)` (`theme.css:6903`, `:6937`, `:7037`), which is true on hybrids.

Two capability tests, two different answers, for the same question. This is the "extend the contract" the item
asks for, and it is a real inconsistency — just not the one described.

**What is measured, and what is not.** The hybrid behaviour above is measured in Chromium: touch contexts
report `hover: none` **and** `any-pointer: coarse` together, and desktop reports neither. Behaviour on real
iOS Safari or iPadOS with a trackpad is **not** testable in this environment and is therefore not asserted
here — if it matters, it needs a device, not an emulator.

## 4. The contract, stated once

1. **Hover keeps working.** Nothing about the pointer path changes.
2. **The instruction names the gesture the device actually supports** — hover copy on pointer-only devices,
   tap copy where a touch pointer is present (`any-pointer: coarse`, matching the house law).
3. **The named gesture must do something.** A hint may not name an action the code does not implement. This is
   the clause that is broken today.
4. **The same words surface on every input path** — the tip text is one string, whichever path opens it.

## 5. Copy

| Where | Now | After |
|---|---|---|
| `ui-legend-bar.blade.php:11` hover hint | "Hover a colour to learn more" | **unchanged** |
| `ui-legend-bar.blade.php:11` touch hint | "Tap a colour to learn more" | **unchanged — but only once tap works** |
| `student-my-timetable.blade.php:32` help | "…**Hover** a colour in the legend to see what it means." | "…**Hover, tap or focus** a colour in the legend to see what it means." |
| `ui-summary-bar.blade.php:28` | "Hover or tap a card to learn more" | unchanged — already names both |

The help text names all three input paths rather than none: it is a paragraph, not a gesture hint, so it does
not need the media-query swap and is true everywhere. (This is the same reasoning as the lane's legend tips,
which use "OPEN the slot's tooltip" because `data-tip` strings cannot be swapped at all.)

## 6. What this needs, and what is blocked

| Work | File | State |
|---|---|---|
| `tabindex="0"` + `aria-label` on `.legend-item` | `resources/views/partials/ui-legend-bar.blade.php` | **free** |
| swap `(hover: none)` → `(any-pointer: coarse)` for the legend + summary hints | `public/css/theme.css` | **BLOCKED** — held by the Cohort/Venue port |
| help-text wording | `resources/views/livewire/student-my-timetable.blade.php` | **free** |
| focus ring for the new tab stops | `theme.css` or a scoped block | **BLOCKED** with the swap |

Note the ordering trap: making the legend items focusable without the swap is safe, but making them focusable
while the copy still says "Tap" on a hybrid is not the bug — the bug is that nothing happens. So the
tabindex work fixes the promise, and the swap work makes the instruction exact.

## 7. Acceptance criteria (amendment)

- **A-1** On a touch context, tapping a legend item shows its tip; on desktop, hovering still shows it.
- **A-2** Legend items are reachable by Tab and announce their meaning (accessible name = tip text).
- **A-3** On a hybrid (`hover: hover` **and** `any-pointer: coarse`), the hint names tap — not hover.
- **A-4** No hint names a gesture the code does not implement, in any context.
- **A-5** No copy change to the tip strings themselves; the legend's four meanings are untouched.
- **A-6** No behaviour change to the timetable grid, the print sheet, or the arrangement.

## 8. Gate

Decision-level amendment to a frozen lane → **round 3 review required**, then the owner's explicit go.
Nothing applied; no system code touched. `theme.css` items wait for the port regardless.
