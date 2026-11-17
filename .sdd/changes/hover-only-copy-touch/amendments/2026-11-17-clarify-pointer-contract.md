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

`resources/views/partials/ui-legend-bar.blade.php:11` ships **both** strings and `theme.css:2333-2336` swaps
them under `@media (hover: none)`. So the instruction is already honest on the iPad — the swap exists, exactly
as the item's own "fix" line concedes. The claim rests on a desktop screenshot read as if it were an iPad.

## 2. What is actually broken — the promise, not the wording

The copy says **"Tap a colour to learn more."** It cannot be kept:

- `.legend-item` is `<div class="legend-item" data-tip="…">` — **`tabindex` is `null` in every context**
  measured (desktop, iPad, phone).
- The engine has **no touch path** (`mouseenter`/`mouseleave`/`mouseout`/`focusin`/`focusout` only).
  *Same discipline as §3:* this is code-level fact plus Chromium measurement. Browsers fire compatibility
  mouse events on tap, and the engine has a document-level `mouseenter` listener, so the statement "a tap
  summons nothing" is measured-in-Chromium — true there, not asserted for real iOS.
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
| swap `(hover: none)` → `(any-pointer: coarse)` for the legend hint only | `public/css/theme.css` | **free now — the port landed** |
| summary hints | — | **nothing to do:** the plain card's touch sentence swaps by *sheet scope* (`#summarySheetBody …`, `theme.css:2244-2252`), not by media, and the flip variant keeps "Hover or tap" because tap is implemented (`ui-common.js:2396-2401`) |
| help-text wording | `resources/views/livewire/student-my-timetable.blade.php` | **free** |
| focus ring for the new tab stops | `theme.css` | **free now** — travels with the 48px floor |

Note the ordering trap: making the legend items focusable without the swap is safe, but making them focusable
while the copy still says "Tap" on a hybrid is not the bug — the bug is that nothing happens. So the
tabindex work fixes the promise, and the swap work makes the instruction exact.

## 6a. Supersession of frozen AC-6 (required, or the apply contradicts itself)

Frozen proposal **AC-6** reads: *"No change to `ui-common.js`, `theme.css`, or any Livewire class."* This
amendment **edits `theme.css`** — the swap re-key, the 48px floor and the focus ring. Honouring AC-6
literally would skip the floor and **reintroduce round-3's blocker B1**; ignoring it silently would make the
verify pass contradict a frozen criterion.

**AC-6 is therefore amended to:**

> **AC-6 (amended)** `ui-common.js` and the Livewire classes remain untouched. `theme.css` is now in scope —
> three rules only (the legend hint re-key, the `.legend-item` 48px floor under `any-pointer: coarse`, the
> `:focus-visible` ring) — and nothing else in it changes.

This is a decision-level change, already approved with option A (2026-11-17); it is recorded here so the
frozen artifact is not contradicted by the work.

## 7. Acceptance criteria (amendment)

- **A-1** On a touch context, tapping a legend item shows its tip; on desktop, hovering still shows it.
- **A-2** Legend items are reachable by Tab and **announced**, with a carrier that a screen reader will
  actually name: `role="img"` and an `aria-label` carrying **both** the visible word and the meaning
  ("Normal Class: Scheduled class with no issues"). A roleless focusable `<div>` gets a Chromium-computed
  name that Playwright would pass and a real screen reader would not — the false pass this AC exists to
  prevent. The arrangement's tips are multi-line (`\n`), so its label will contain the break — harmless for
  screen readers, and the single-line example above is the timetables' case, not the only one.
- **A-3** With the hybrid media combination *forced* (CDP `Emulation.setEmulatedMedia`), the hint names tap.
  This verifies the **swap wiring**, not real hardware: Playwright cannot produce a true `hover: hover` +
  `any-pointer: coarse` device, and this AC says so rather than posing as a device test.
- **A-4** No hint names a gesture the code does not implement, in any context.
- **A-5** *Guard:* **this amendment** changes no tip string and does not alter the legend's four meanings.
  The proposal's own `HOVER → OPEN` rewrite of the two **arrangement** legend tips is separate, earlier, and
  not undone by this AC — the two must not be read as contradicting each other.
- **A-6** *Guard:* no behaviour change to the timetable grid, the print sheet, or the arrangement — plus the
  **AC-6 supersession below**, which is why this amendment may touch `theme.css` when the frozen proposal's
  AC-6 said it could not.

## 8. Gate

Decision-level amendment to a frozen lane → **round 3 review required**, then the owner's explicit go.
Nothing applied; no system code touched. `theme.css` items wait for the port regardless.

---

## 9. Rev 2 — round 3 found the blocker that decides this lane

Round 3 verified the findings and corrected several citations (§1–§6 above are amended), but it found that
the "free" tabindex work is **not free**, and it is right.

### 9.1 The 48 × 48 law applies the moment a legend item can take focus

`[tabindex]:not([tabindex="-1"])` is inside both touch walks:
`tests/timetable-responsive.spec.ts:260-276` (and its `touch laptop @1440` case, `:1382`) and
`tests/cohort-venue-responsive.spec.ts:398` — whose comment says the law's only allowance is the native
`<option>`.

Measured on the live pages, the legend items are **16px tall**:

| Page | items | item box | hint shown at touch |
|---|---|---|---|
| `/my-timetable-ui` | 4 | 105 × **16** | TAP |
| `/cohort-timetable` | 4 | 66 × **16** | TAP |
| `/venue-timetable-ui` | 5 | 77 × **16** | TAP |
| `/replacement-arrangement` | 4 | 77 × **16** | TAP |
| `/my-timetable` (real route) | 0 | — | no legend |
| 390 phone | 0 visible | — | legend lives inside the week summary sheet below 1280 (`theme.css:6496-6511`); the floor costs nothing until the sheet is open, then 48px rows in a scrollable body |

All boxes were measured at **1440** (and the phone row at 390); the measured height is the same 16px in both.

So `tabindex="0"` alone would add four to five R7 offenders per page, at the very contexts the amendment
cites as safe. My §6 claim that the tabindex work is "safe without the swap" was wrong, and this is the
second time this lane has been caught assuming a piece of work was free.

### 9.2 The decision this lane now needs — and it is the owner's

| Option | What it means | Cost | Risk |
|---|---|---|---|
| **A. 48px floor on coarse pointers** | theme.css (free) gives `.legend-item` a 48×48 minimum under `any-pointer: coarse`; then tabindex is legal | the legend grows from ~16px to 48px **per wrapped row** on touch devices only — `.legend-items` is a flex-wrap row, so at 1024 that is one row, about +32px of page height total (not +32px per item: an earlier draft of this table overstated it ~4×) | none to the law; the legend becomes visually heavy on iPad |
| **B. Tap path in the engine** | the shared tip engine gets a `pointerup`-with-`touch` branch: tap shows, next tap / scroll / Escape hides | one shared-JS change; **no** tab stops, so no R7 impact and no size floor | touches a file every page uses; needs a dismissal rule; the lane's "no engine change" stance is reversed |
| **C. Exempt the legend in both walks** | add a documented exception for `.legend-item` | none in layout | contradicts the law's own written allowance ("the only allowance is the native `<option>`") — the one thing this house does not do quietly |

**The amendment does not pick one.** B is the only option that keeps the promised gesture (`Tap a colour`)
without changing what the legend looks like, and it is also the largest blast radius. A is the smallest
change that is fully legal, at the cost of a visibly taller legend on tablets. C is cheapest and worst.

### 9.3 What is ready regardless of that decision

- The **help-text wording** (`student-my-timetable.blade.php:34`) is independent, unblocked, and harmless on
  its own — but "or focus" is only true once the focus stop exists, so if the owner picks B the wording loses
  the word "focus" and keeps "Hover, tap".
- The **swap re-key** (`hover: none` → `any-pointer: coarse`) is now unblocked. Round 3 argues it is a
  *trade-off, not a bug*: `hover: none` answers "can nothing hover?", `any-pointer: coarse` answers "does a
  coarse pointer exist?", and on a hybrid the hover copy is not false — it just does not name the second
  gesture. Recorded as a choice for the owner, with the house's own precedent ("Hover or tap a card") as the
  middle path.
- **A-2's carrier** is decided: `role="img"` + `aria-label` carrying label and meaning.
- **A-3** is a wiring test, A-5/A-6 are guards (marked as such).

### 9.3a Owner's decision (2026-11-17): **A — the 48px floor**

The legend items become real controls, so they get the law's size, not an exemption:

```css
/* Capability, not width: a coarse pointer means a finger, and a finger needs 48px.
   The legend grows on tablets only; a mouse device keeps today's 16px row. */
@media (any-pointer: coarse) {
    .legend-item { min-height: 48px; min-width: 48px; }
}
```

Consequence, stated plainly: on touch devices the legend is 48px per row instead of 16px, roughly +32px of
page height, four to five items. That is the price of keeping the law and satisfying WCAG 1.4.13 at once —
`hover: none` content must also be reachable by keyboard focus, which the tap-path option (B) would not give.

This also settles the wording: **"Hover, tap or focus"** is now true on all three paths, so the help text
keeps all three words.

### 9.4 Gate

Round 3 raised two blockers: A-2's carrier is decided in the text (`role="img"`), and 9.1 is decided by the
owner — **option A**, the 48px floor (§9.3a). Round 4 then found two documentation conflicts, both fixed:
the apply plan now carries the frozen proposal's own scope, and AC-6 is superseded above rather than quietly
broken. Nothing applied; no system code touched.
