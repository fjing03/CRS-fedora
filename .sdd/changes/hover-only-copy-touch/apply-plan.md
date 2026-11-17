# Apply plan — the whole lane (prepared while the port landed, NOT applied)

Exact snippets, so the apply step is paste-and-verify rather than re-derivation. Nothing here is in the tree.

**This plan covers BOTH halves of the lane.** Sections 0a and 0b are the frozen proposal's own work; sections
1–3 are the pointer-contract amendment. An apply that runs only 1–3 ships half the lane (round 4, 🔴-1).

## 0a. `resources/views/livewire/replacement-arrangement.blade.php` — the two legend tips (proposal task 2)

`:2728` and `:2732`, the word `HOVER` → `OPEN` in both, capitals kept (the blade's own rule:

> "Capitals do the emphasis: a data-tip is plain text").

Also in this file, `:2724` includes the shared legend partial — so the amendment's section 1 change lands on
this page too.

## 0b. Same file — `#submitTip` (proposal tasks 3–4)

In `updateCounter()` (~`:3484`), the wrapper's `tabindex` and `aria-label` are set **from `btn.disabled`**, not
from `has`:

```js
/* keyed off the button's ACTUAL disabled state: a slot can be selected while Submit stays disabled
   (the T18 mobile-origin latch), and that state must keep its reason reachable. */
if (submitTip) {
    const tip = has ? 'Send your replacement request for approval'
                    : 'Select a time slot first to enable submission';
    submitTip.setAttribute('data-tip', tip);
    submitTip.setAttribute('aria-label', tip);
    if (btn && btn.disabled) submitTip.setAttribute('tabindex', '0');
    else submitTip.removeAttribute('tabindex');
}
```

Plus the `:focus-visible` ring for `.btn-tip` in the blade's own `<style>` (proposal task 4).

## 1. `resources/views/partials/ui-legend-bar.blade.php` (line 14)

```blade
{{-- The hint promises a tap on touch; without a focus stop that promise cannot be kept — the engine has
     no touch path, and a div with data-tip alone is unsummonable. tabindex makes the tip reachable by tap
     (focus parity) and by Tab. role=img, not a roleless div: a generic computes a name in Chromium and
     announces unevenly in real screen readers, which is a false pass. role=img makes the child spans
     presentational, so the label must carry BOTH the colour name and the meaning. --}}
<div class="legend-item" tabindex="0" role="img"
     @if(!empty($item['tip'])) data-tip="{{ $item['tip'] }}" aria-label="{{ $item['label'] }}: {{ $item['tip'] }}" @endif>
```

**Cost:** four to five new tab stops per legend, on every page that renders one — and, because they are now
controls, the 48px law applies to them (owner chose option A, 2026-11-17).

## 2. `resources/views/livewire/student-my-timetable.blade.php` (line 32)

```blade
['Colours', 'Normal Class, Replacement, Pending and Conflict / Public Holiday. Hover, tap or focus a colour in the legend to see what it means.'],
```

## 3. `public/css/theme.css` (now free — 2333)

```css
/* touch devices tap instead of hover (F-5, ui-legend-bar dual copy).
   `any-pointer: coarse`, not `hover: none`: on a hybrid (Surface, touch laptop, iPad + trackpad) a
   hovering pointer exists, so `hover: none` is false and the legend would keep saying "Hover a colour"
   while a finger is on the glass. Capability, not width — the same law as the touch floor.
   NOTE: this re-key is a TRADE-OFF, not a bug fix (round 3): hover:none asks "can nothing hover?",
   any-pointer:coarse asks "is a coarse pointer present?". Owner approved the exchange. */
@media (any-pointer: coarse) {
    .legend-hint .hint-hover { display: none; }
    .legend-hint .hint-touch { display: inline; }
}

/* Owner decision 2026-11-17 (option A): the legend items are controls now, so they take the law's size
   instead of an exemption. Measured before: 16px tall on all four pages that render a legend.
   Consequence: the legend grows ~32px per row on touch devices only; a mouse device is untouched. */
@media (any-pointer: coarse) {
    .legend-item { min-height: 48px; min-width: 48px; }
}

/* The new tab stops need a visible indicator; the legend is a div, so it has none by default. */
.legend-item:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
    border-radius: var(--radius-sm);
}
```

Verify the existing `(hover: none)` block at 2333 is replaced rather than duplicated — and note the port
added its own `(hover: none)` blocks at 7288 for the cohort/venue hover neutralisers, which are a
different concern (hover *skins*, not copy) and are out of scope here.

## 4. Verification, in order

1. `rm -f storage/framework/views/*.php`.
2. Desktop 1440: hover a legend item → tip; hint reads "Hover a colour".
3. Touch 1024 (`hasTouch`, `isMobile`): hint reads "Tap a colour"; **tap** a legend item → tip; tap a
   non-focusable target → tip hides.
4. Keyboard: Tab reaches each legend item, focus ring visible, tip shows, `aria-label` announced.
5. `scrollWidth` unchanged at 1024 / 1280 / 1440; the four legend meanings unchanged.
6. `npx playwright test tests/timetable-responsive.spec.ts tests/cohort-venue-responsive.spec.ts
   --reporter=line` — the two *are* the 48×48 walks, and they must report zero offenders at 390 and at the
   touch-laptop 1440 case. This is the AC that would have caught the round-3 blocker.
   (`status-tooltips-filter-chips.spec.ts` is **not** in this list: it asserts tooltip and chip behaviour and
   touches neither the legend nor the pointer contract.)
7. **Forced-hybrid wiring test (A-3).** Playwright cannot produce a real `hover: hover` + `any-pointer: coarse`
   device, so force the media combination over CDP (`Emulation.setEmulatedMedia` with those two features) and
   assert the hint reads "Tap a colour". This verifies the **swap wiring only** — say so in the receipt; it is
   not a device test.
8. **The arrangement page** gets both halves (new attributes *and* the tip rewrite), so run
   `tests/arrangement-slot-ownership.spec.ts` (it asserts the legend rows and swatch classes) and
   `phpunit --filter SlotBorderAndReasonBadgeTest`.
9. `/impeccable detect` on the two changed blade files (markup check), then hand to polish if it flags.
10. Print is covered indirectly: `timetable-responsive.spec.ts` R8 asserts the legend does not print
    (`.legend-bar` is `display: none` on paper, `theme.css:7962`), so the 48px floor cannot reach paper.

## 5. Risk

**The A/B/C choice is made** (owner, 2026-11-17: option A, the 48px floor). The residual risk is the one that
choice accepts: four to five new tab stops per legend, and 48px rows on touch devices. Both are covered by
the walks in step 6 rather than assumed.
