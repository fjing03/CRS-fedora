# Design — My Timetable UI cleanup

> **Change:** `my-timetable-ui-cleanup`
> **Reference:** `ui-prototype/my-timetable-mobile-preview.html`

## 1. Summary cards

The flip-card variant is opted into by six pages: the four timetable pages and
both replacements pages (My Replacements, Request Approval). The remaining
`ui-summary-bar` caller (`replacement-history`) keeps the older card markup
until it is asked for.

### Markup (shared partial)

The prototype's card is the target. Keep every `valueId` and the
`.summary-value` element so page scripts keep working.

```blade
<div class="summary-card {{ $card['class'] }}" tabindex="0" role="group"
     data-summary="{{ $i }}"
     aria-label="{{ $card['label'] }}: hover, tap or press Enter to flip">
    <div class="summary-card-inner">
        <div class="summary-card-front">
            <span class="summary-value" id="{{ $card['valueId'] }}">{{ $card['value'] ?? 0 }}</span>
            <span class="summary-label">{{ $card['label'] }}</span>
        </div>
        <div class="summary-card-back">
            <div class="summary-description">{!! $desc !!}</div>
            <button type="button" class="summary-info"
                    data-summary-info="{{ $i }}"
                    aria-haspopup="dialog"
                    aria-label="{{ $card['label'] }}: full explanation"
                    data-tip="Full explanation">
                {{-- 16px circle-i SVG, currentColor --}}
            </button>
        </div>
    </div>
</div>
```

- Back content must be `ul.summary-points` with at most three `li`. Reuse the
  already-approved three-bullet copy from the prompt §9 rather than the old
  single paragraph.
- `$cards` keeps its shape; add optional `bullets` (array) and fall back to the
  existing `description` string when absent, so non-timetable callers do not
  change.
- The partial gets `data-summary-index` on each card for the controller.

### CSS (theme.css, after the existing summary block)

```css
.summary-card { cursor: pointer; min-height: 96px; }
.summary-card .summary-card-inner { height: 96px; min-height: 96px; }
.summary-card-back { padding: 6px 8px; align-items: flex-start; justify-content: flex-start; text-align: left; }
.summary-card-back .summary-description { width: 100%; font-size: 12px; line-height: 1.15; }
.summary-points { margin: 0; padding-left: 12px; display: grid; gap: 0; }
.summary-points li::marker { color: var(--color-on-surface-variant); }
.summary-info { position: absolute; right: 0; bottom: 0; width: 44px; height: 44px;
    display: grid; place-items: center; padding: 0; border: 0; border-radius: var(--radius-sm);
    background: transparent; color: var(--color-on-surface-variant); cursor: pointer; }
.summary-info:hover, .summary-info:focus-visible {
    color: var(--color-primary); background: var(--color-primary-container); }
.summary-card:not(.is-flipped):not(:hover) .summary-info { visibility: hidden; }
.modal.modal--summary { max-width: 420px; }
```

Rules are additive and token-only. Keep the existing clear-colour map; do not
invent new colours.

### Behaviour (ui-common.js)

One `SummaryCards` controller, initialised on DOMContentLoaded:

- Click toggles `.is-flipped` on the closest `.summary-card`; it also sets
  `aria-expanded`.
- `keydown` Enter/Space toggles the same way and prevents scroll.
- `data-summary-info` click stops propagation, opens `#summaryExplainModal`
  with the full copy, and records the source card + index.
- Closing restores `.is-flipped` on that card and returns focus to its info
  button.
- Hover flip stays CSS-only (`.summary-card:hover .summary-card-inner`); on
  touch devices a tapped class wins because the class is more specific than
  `:hover` and there is no hover state.

The controller must be idempotent: re-running it (Livewire morph) must not
double-bind. Use one delegated listener on `document`.

## 2. Guide modal

### New partial

`resources/views/partials/ui-guide-modal.blade.php`

```blade
<div class="modal-overlay" id="guideModal" style="display:none"
     onclick="if(event.target===this)GuideModal.close()">
    <div class="modal modal--guide" role="dialog" aria-modal="true"
         aria-labelledby="guideModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="guideModalTitle">{{ $guideTitle ?? 'How to use this page' }}</h3>
            <button type="button" class="modal-close" data-tip="Close"
                    onclick="GuideModal.close()" aria-label="Close">✕</button>
        </div>
        <div class="modal-body">
            @if($guideDescriptionHtml)
                <p class="page-desc">{!! $guideDescriptionHtml !!}</p>
            @endif
            @if(count($guideItems ?? []) > 0)
                <ul class="guide-list">
                    @foreach($guideItems as $item)
                        <li>{!! $item !!}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="modal-footer">
            <div class="modal-footer-left"></div>
            <div class="modal-footer-right">
                <button type="button" class="btn-close-modal" onclick="GuideModal.close()">Close</button>
            </div>
        </div>
    </div>
</div>
```

- `GuideModal` exposes `open()` / `close()`; open sets `display:flex`, stores
  the opener, moves focus to the close button, and locks body scroll; close
  restores focus and scroll.
- Escape closes; the overlay closes; the × closes.
- `modal--guide` max-width 520px on desktop; the existing `.modal` phone rules
  make it a bottom sheet at `<=768px` with no extra work.

### Header icon

`ui-page-header`'s `.guide-toggle-icon` keeps its look but changes the action:

```blade
<button class="guide-toggle-icon" type="button"
        aria-haspopup="dialog" aria-controls="guideModal"
        aria-expanded="false" aria-label="{{ $guideLabel }}"
        onclick="GuideModal.open(this)">
```

`GuideModal` sets `aria-expanded` on the opener. No `.guide-block.open` toggle
remains.

### Guide-block partial

The four timetable pages stop including `ui-guide-block`. The partial stays for
any other caller; its `.guide-block--content` CSS stays. Removing the includes
is the only page change for the inline guide.

## 3. Semester chrome removal

For My, Student, Venue:

- Delete the `div.semester-progress#semesterProgress` block (label, track, fill).
- Delete the `div.week-subtitle#weekSubtitle` block.

Cohort has neither today; no change.

Keep `updateProgress()` and `updateWeekSubtitle()` and their call sites. Both
begin with `getElementById(...)` and skip when the node is absent, so the calls
become no-ops. Do not delete the shared CSS while
`replacement-arrangement`'s commented-out progress markup still uses the names.

## 4. Week select surface

`theme.css` `.week-select`:

```css
.week-select {
    background-color: var(--color-surface-variant);
    background-image: /* existing chevron, unchanged */;
}
.week-select:hover {
    border-color: var(--color-primary);
    background-color: var(--color-surface);
}
```

- `option` keeps `background: var(--color-surface)` so the opened list is
  readable.
- No change to padding, height, radius, font, focus ring or `min-width`.
- Applies wherever `.week-select` is used, which is the intended "all week
  select dropdowns" scope; the class is timetable-only by construction.

## Tests

Static and visual only for this pass:

1. Blade compile + `node --check public/js/ui-common.js`.
2. Open the four pages and confirm: 96px cards, flip, info modal, restore focus.
3. Open the guide icon; confirm description + items, Escape/overlay/× close.
4. Confirm no `#semesterProgress` / `#weekSubtitle` on the four pages and no
   console error on a week change.
5. Confirm the week select resting/hover backgrounds and that options are
   readable in both themes.

No test run is required by the user for this pass; these are the manual checks
to perform while applying.

## Promoted to shared

- `ui-guide-modal.blade.php` and the `SummaryCards` controller are shared
  because both are used by 3+ pages (the promote-on-3rd-duplication rule).
