# Apply plan — the pointer contract (prepared while the port landed, NOT applied)

Exact snippets, so the apply step is paste-and-verify rather than re-derivation. Nothing here is in the
tree.

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
   tests/status-tooltips-filter-chips.spec.ts --reporter=line` — the first two *are* the 48×48 walks, and
   they must report zero offenders at 390 and at the touch-laptop 1440 case. This is the AC that would
   have caught the round-3 blocker.
7. `/impeccable detect` on the two changed blade files (markup check), then hand to polish if it flags.

## 5. Risk

Four new tab stops on five pages is the real cost, and it is the opposite of the keyboard audit's finding
that the nav already costs eight stops. If that trade is unwelcome, the alternative is a tap handler in
the engine (the B option from the proposal) — one shared-JS change, no tab stops — which is why the
choice deserves the owner's word rather than mine.
