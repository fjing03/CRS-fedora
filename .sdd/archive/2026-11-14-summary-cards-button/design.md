# Design — `summary-cards-button`

**Batch 2.** `proposal.md` is **frozen** (review round 1, 2026-11-14); decisions 1–5 are taken
(`proposal.md` §7b). This document pins the behaviour the proposal left open — Esc precedence, trap
suspension, the empty-view trigger, the plain-variant fix — and names every selector, id and function
an implementer must write.

---

## 1. Shape

Two shared partials, one JS module, one CSS block, seven one-line opt-ins, one deletion (done).

```
ui-page-header.blade.php   →  <button id="summaryButton">        (≤768 only, opt-in flag)
        ↓ aria-controls
ui-summary-bar.blade.php   →  <div id="summarySheet"><div id="summarySheetBody"></div></div>
                                     ↑ SummarySheet.sync() moves #summarySection into here at ≤768
ui-common.js               →  var SummarySheet = { init, sync, open, close }
theme.css                  →  .summary-button, .summary-sheet*, layering rules, plain-variant phone fix
```

The cards never change owner: `#summarySection` stays the same node with the same ids, moved (not
cloned) into `#summarySheetBody` at ≤768 and moved back at ≥769 with a placeholder holding its place.

## 2. Decision log (why each thing is where it is)

| decision | why |
|---|---|
| sheet frame emitted by `ui-summary-bar`, not by a separate partial | one copy per page, and a page cannot ship the button without the panel; the partial already owns `#summarySection` and `#summaryExplainModal` |
| button emitted by `ui-page-header` with a `$summaryButton` flag | all seven pages already include it and it already owns the help icon; the flag keeps non-opted pages untouched |
| a **move**, not a re-style | the pages hide/show the section through its **inline** `display` (`syncSummarySection()`, cohort-timetable `:472,:538,:544`); only a move carries that intent into the sheet |
| own scroll-lock class `summary-sheet-open` | `body.summary-locked` is shared with `GuideModal`/`SummaryCards` and has no refcount — closing the ⓘ above the sheet would unlock the page underneath |
| veil at `z-index: 1200`, ⓘ lifted to `1400`, tooltip engine already `9999` | the prototype's proven numbers; the ⓘ keeps its own code path and needs exactly one scoped rule to win |
| no native `<dialog>` | top layer beats every z-index (measured), so the ⓘ could never open above it. Recorded in `explore-brief.md` §4. |
| plain variant fixed here, scoped to the sheet | after this lane the section is only ever *seen* at ≤768 inside the sheet; the fix cannot reach a laptop |

## 3. Breakpoint

`matchMedia('(max-width: 768px)')` in JS; `@media (max-width: 768px)` in CSS. 768 inclusive is phone
(decision 3). One `change` listener re-runs `sync()` on resize, rotation and devtools toggling.

## 4. Markup contract

### 4.1 The button — `partials/ui-page-header.blade.php`

```blade
@php $summaryButton = $summaryButton ?? false; @endphp
...
@if($summaryButton)
    <button type="button" class="summary-button" id="summaryButton"
            aria-haspopup="dialog" aria-controls="summarySheet" aria-expanded="false"
            aria-label="Summary" data-tip="Summary"
            onclick="SummarySheet.open(this)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/>
            <line x1="18" y1="20" x2="18" y2="11"/>
        </svg>
    </button>
@endif
```

- Emitted **before** `.guide-toggle-icon`; the page-header flex row already right-aligns its children,
  and `page-header-main` is `flex: 1 1 auto; min-width: 0`, so a second 44px icon only narrows the
  title column.
- The icon is decoration; the accessible name is `aria-label="Summary"`.

### 4.2 The sheet — `partials/ui-summary-bar.blade.php` (end of file, after the ⓘ modal)

```blade
<div class="summary-sheet-overlay" id="summarySheet" hidden
     onclick="if(event.target===this)SummarySheet.close()">
    <div class="summary-sheet" role="dialog" aria-modal="true"
         aria-labelledby="summarySheetTitle">
        <div class="summary-sheet-head">
            <h2 class="summary-sheet-title" id="summarySheetTitle">Summary</h2>
            <button type="button" class="summary-sheet-close" aria-label="Close Summary"
                    data-tip="Close" onclick="SummarySheet.close()">✕</button>
        </div>
        <div class="summary-sheet-body" id="summarySheetBody"></div>
    </div>
</div>
```

- Always emitted (the partial has no "is this page phone-sized" knowledge); `hidden` keeps it out of
  layout and out of the tab order until JS moves the cards in. Desktop pays for one `hidden` div.
- `role="dialog"` + `aria-modal="true"` are static here — the node is only ever shown by the module,
  never inline, so the role is honest.
- **The `ui-design-templates/*` reference pages include this partial too and will now carry the same
  hidden overlay.** That is accepted: they are reference pages, unedited, and the overlay stays hidden
  because they have no module and no button.

### 4.3 The hint line (decision 2 + decision 5)

Today the partial prints one sentence, and for the plain variant it says **"Hover a card to learn
more."** — wrong on a touch screen. Split it into a pointer sentence and a touch sentence, both
server-rendered, one hidden by CSS:

```blade
<span class="summary-hint-pointer">Stats reflect the <strong>selected period</strong> and any active
filters. @if($flipCards)Hover or tap a card to learn more.@else Hover a card to learn more.@endif</span>
@unless($flipCards)
    <span class="summary-hint-touch">Stats reflect the <strong>selected period</strong> and any active
    filters. Tap a card to see what it counts.</span>
@endunless
```

```css
.summary-hint-touch { display: none; }
/* plain variant only — the six flip pages keep their sentence inside the sheet too */
#summarySheetBody .summary-section:not(.summary-section--flip) .summary-hint-pointer { display: none; }
#summarySheetBody .summary-hint-touch { display: inline; }
```

The touch sentence exists **only on the plain variant** — the six flip pages already say "Hover **or
tap** a card to learn more.", which is true on a touch screen, so their copy is untouched even inside
the sheet (frozen decision 2). Nothing here is JS-driven and the desktop sentence stays exactly what it
is today.

### 4.4 The seven opt-ins

`livewire/{my-timetable, cohort-timetable, venue-timetable, replacement-home, request-approval,
student-my-timetable, replacement-history}.blade.php` — add `'summaryButton' => true,` to the existing
`ui-page-header` include. Nothing else on those pages changes: not the card arrays, not the paint code.

## 5. CSS (`public/css/theme.css`)

All values from tokens; the one literal is the veil, reused byte-for-byte from `.modal-overlay`.

```css
/* the Summary opener — phone only, bare, 44px (opt out of the global 48px floor) */
.summary-button { display: none; }
@media (max-width: 768px) {
    .summary-button {
        flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center;
        width: 44px; height: 44px; min-width: 44px; min-height: 44px; padding: 0;
        border: 1px solid transparent; background: transparent;
        color: var(--color-primary); border-radius: var(--radius-md); cursor: pointer;
    }
    .summary-button:hover { background: var(--color-surface-variant); }
    .summary-button:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
    .summary-button[aria-expanded="true"] {
        background: var(--color-primary-container); color: var(--color-on-primary-container);
    }
}

/* the sheet */
.summary-sheet-overlay {
    position: fixed; inset: 0; z-index: 1200; display: flex;
    align-items: flex-end; justify-content: center;
    background: rgba(0, 0, 0, .45);          /* same colour as .modal-overlay:2039 (rgba(0,0,0,0.45)), deliberately literal */
}
.summary-sheet-overlay[hidden] { display: none; }
.summary-sheet {
    width: 100%; max-height: 88dvh; display: flex; flex-direction: column;
    background: var(--color-surface); color: var(--color-on-surface);
    border: 1px solid var(--color-outline); border-bottom: 0;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.summary-sheet-head {
    position: sticky; top: 0; z-index: 2; display: flex; align-items: center;
    justify-content: space-between; gap: 8px; padding: 10px 14px;
    background: var(--color-surface); border-bottom: 1px solid var(--color-outline);
}
.summary-sheet-title { margin: 0; font-size: 14px; font-weight: 700; }
.summary-sheet-close {
    border: 0; background: transparent; color: inherit; font-size: 22px; line-height: 1;
    width: 44px; height: 44px; cursor: pointer; border-radius: var(--radius-sm);
}
.summary-sheet-close:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }
.summary-sheet-body { padding: 12px 12px 16px; overflow-y: auto; overscroll-behavior: contain; }
.summary-sheet-body .summary-section { margin-top: 0; }
body.summary-sheet-open { overflow: hidden; }

/* the ⓘ must win over the sheet — one scoped rule, the prototype's number */
body.summary-sheet-open #summaryExplainModal { z-index: 1400; }

/* printing never captures a sheet, and never inherits a scroll lock */
@media print {
    .summary-sheet-overlay, .summary-button { display: none !important; }
    body.summary-sheet-open { overflow: visible; }
}
```

**Print is guarded, not assumed.** Browsers usually re-evaluate media queries for print preview, but a
design must not lean on "usually": while a sheet is open, `beforeprint` calls `SummarySheet.unrelocate()`
so the cards are back inline for the printout (paper is ~794px wide, above the breakpoint, where inline
is the intended look), and `afterprint` re-runs `sync()` so a phone-sized window moves them back into
the sheet. Together with the `@media print` rule above, printing can never produce a blank summary. The
spec asserts the print media state with `page.emulateMedia({ media: 'print' })`.

### 5.1 The plain-variant fix (decision 5)

**a. The dead phone rule.** `@media (max-width: 768px) { .summary-bar { grid-template-columns: 1fr } }`
(`:4358`) is overridden by the later, same-specificity
`@media (max-width: 1024px) { .summary-bar { grid-template-columns: repeat(3, 1fr) } }` (`:4616`).
Fix by giving the phone rule specificity instead of moving blocks (moving would silently change what
the `1024` block's `.summary-value`/`.summary-label` font rules cover):

```css
#summarySheetBody .summary-section:not(.summary-section--flip) .summary-bar {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
@media (max-width: 389px) {
    #summarySheetBody .summary-section:not(.summary-section--flip) .summary-bar { grid-template-columns: 1fr; }
}
```

Two columns, mirroring the flip variant at ≤768 and its 1-column rule below 390, so the student page's
sheet reads like the other six instead of `114px × 3`. **Both rules are scoped to `#summarySheetBody`**,
so nothing outside the sheet changes — including the eight `ui-design-templates/*` reference pages,
which include the partial but never move it. The tablet block keeps 3 columns for 769–1024.

**The plain card needs a height budget too.** Its faces are absolutely positioned over a 60px inner
(the `min-height: 60px` that survives from the dead phone block, `theme.css:4362`), and its backs are
full sentences, not three bullets. Measured in the live page at 390 (the five real descriptions, at the
card's own `11.5px/1.4`): the longest back needs **142px at a 175px card** (2 columns) and **162px at
150px**. So, inside the sheet only:

```css
#summarySheetBody .summary-section:not(.summary-section--flip) .summary-card,
#summarySheetBody .summary-section:not(.summary-section--flip) .summary-card-inner {
    min-height: 148px; height: 148px;
}
@media (max-width: 389px) {
    #summarySheetBody .summary-section:not(.summary-section--flip) .summary-card,
    #summarySheetBody .summary-section:not(.summary-section--flip) .summary-card-inner {
        min-height: 132px; height: 132px;
    }
}
```

148px = the measured 142px plus 6px of slack at a 175px card (the **narrow** end of the 2-column band,
which is the worst case). 132px is the same discipline applied to the single-column band: it must be
budgeted at **320**, the narrowest viewport the lane asserts, where the card is ~272px and the longest
back wraps to ~7–8 lines (~115–130px at `11.5px/1.4`) — not at 389, where the same text needs less.
Because the faces are absolutely positioned, the height is fixed, so an under-budget clips rather than
grows: the spec asserts no card back overflows its card at **320**, 390 and 768 for both variants, and
the implementer re-measures at 320 before locking these two numbers.

**b. Tap-to-flip inside the sheet.** Plain cards have no `.is-flipped` rule and no click handler today.

```css
#summarySheetBody .summary-card.is-flipped .summary-card-inner { transform: rotateY(180deg); }
#summarySheetBody .summary-card { cursor: pointer; }
```

(id + 3 classes beats `.summary-card:hover .summary-card-inner` (0,3,0), so a tapped card stays flipped
under the pointer; the rule is scoped to the sheet, so nothing changes on a laptop.)

**c. Hover parity.** Bring the plain variant's hover rule in line with the flip variant's so an
unpinned card is not re-flipped by a mouse after a tap:

```css
@media (hover: hover) and (pointer: fine) {
    .summary-card:not(.is-unflipped):hover .summary-card-inner { transform: rotateY(180deg); }
}
```

and remove the unconditional `.summary-card:hover .summary-card-inner` (`:1704`) — replacing it with
this one, the same idiom the flip variant already uses (`:1790`), so no complex `:not()` argument is
needed and the pinned state is honoured for both variants. Leave the flip variant's own
`.summary-section--flip .summary-card:not(.is-unflipped):hover` rule alone: the new unscoped rule
subsumes it, but deleting it is a cleanup nobody asked for (a comment on the new rule says so). On a mouse this is behaviourally identical
(hover still flips, one rule, same `.is-unflipped` release on `mouseout`); on touch it removes a hover
that never fires; after a tap the pinned state wins.

## 6. JS (`public/js/ui-common.js`, a new section after `SummaryCards`)

```js
var SummarySheet = {
    _mq: null, _opener: null, _placeholder: null, _observer: null, _trapBound: false,
    init(),    // once on DOMContentLoaded: _mq, change listener, MutationObserver, initial sync()
    sync(),    // move / un-move + button visibility
    open(btn), // show, lock, focus, trap, Esc
    close()    // hide, unlock, restore focus, unwrap
};
```

- **`sync()`** — if `_mq.matches`: `relocate(#summarySection → #summarySheetBody)`; else
  `unrelocate()` and `close()` if open. Then `syncButton()`.
- **`relocate()`** — insert a `<span class="summary-section-placeholder" hidden>` before the section,
  move the section into `#summarySheetBody`, and — **only for cards without `data-summary`** (the plain
  variant) — set `tabindex="0"`, `role="group"`, `aria-expanded="false"` and
  `aria-label="<card label>: tap to show what it counts"`. Records that it did so on the node
  (`data-sheet-a11y="1"`) so `unrelocate()` strips exactly those attributes — marker included — and
nothing else, so repeated relocate/unrelocate cycles cannot accumulate stale markers.
- **`unrelocate()`** — move the section back before the placeholder, remove the placeholder, and strip
  **everything** `relocate()` added: the a11y attributes, and — on plain cards — the tap state classes
  `is-flipped` / `is-unflipped`. Without that, a card left pinned in the sheet would carry
  `is-unflipped` inline at ≥769, where the widened delegations no longer match plain cards, so nothing
  could ever clear it and the new `:not(.is-unflipped)` hover rule would leave that desktop card
  hover-dead for the rest of the session.
- **`relocate()` is idempotent** — if the section is already inside `#summarySheetBody` it returns
  early (guards against a second `sync()` from a resize storm).
- **`syncButton()`** — the button is shown only when `_mq.matches` **and** the page has not hidden the
  section. The predicate is the section's **own** display — `getComputedStyle(el).display === 'none'` —
  **never `offsetParent`**: at ≤768 the section lives inside the `hidden` overlay, so `offsetParent` is
  `null` in the normal state and would hide the button on every phone page, defeating the whole module.
  (Every writer sets the section's inline `display` — `syncSummarySection` `:2193`, cohort
  `:472,:538,:544` — and a child's computed `display` stays `block` inside a hidden ancestor, so this
  distinguishes "the page hid the cards" from "the cards are in a closed sheet".) Runs from `sync()`,
  from the observer, and again inside `open()` as a backstop.
- **`open(btn)`** — guard: if the button is hidden or the section is hidden, do nothing (belt and
  braces with `syncButton()`); `#summarySheet.hidden = false`; `body.classList.add('summary-sheet-open')`;
  `btn.setAttribute('aria-expanded','true')`; focus the close button (`preventScroll: true`); bind the
  trap; remember `_opener`.
- **`close()`** — reverse all of the above: hide the overlay, drop `summary-sheet-open`, clear
  `aria-expanded`, unbind the trap **and the capture-phase Esc listener**, then
  `_opener.focus({preventScroll:true})`. Note for verify: when a resize-up closes the sheet, that focus
  call targets a button that is `display:none` at ≥769 — a deliberate silent no-op, not a bug.

### 6.1 Empty-view trigger (the reviewer's 🟡2)

The button's visibility cannot be decided only at init and on resize: a page hides the section at
**runtime** (`syncSummarySection(false)` in `ui-common.js`, and cohort-timetable writing
`style.display` directly at `:472,:538,:544`). Wrapping the global helper would miss the direct writers,
so:

```js
this._observer = new MutationObserver(function () { SummarySheet.syncButton(); });
this._observer.observe(section, { attributes: true, attributeFilter: ['style', 'class'] });
```

One observer per page, on the one node, catching every writer — plus `open()`'s own guard, so tapping
can never open an empty sheet.

### 6.2 Esc precedence and trap suspension (the reviewer's 🟡1)

- The sheet's Esc listener is registered **in the capture phase** on `document` while the sheet is open.
  On Esc it first checks `#summaryExplainModal`: if that modal is visible it **returns without
  `stopPropagation`**, so the ⓘ's own existing bubble handler (`ui-common.js:2221`) closes the ⓘ and
  nothing else happens — one keypress, one layer.
- Otherwise it closes the sheet and stops propagation.
- The **Tab trap is suspended** while `#summaryExplainModal` is visible: the trap's keydown returns
  early in that state, so the ⓘ's own close button is reachable. It resumes on the ⓘ's close because
  the state is read live, not cached.
- Capture-phase registration is what makes this deterministic regardless of listener registration order.

### 6.3 Tap/keyboard for plain cards (decision 5)

`SummaryCards`' click and keydown delegations currently require `.summary-section--flip .summary-card`.
Widen **all three** delegations — click, keydown and the `mouseout` release (`:2229–2234`) — to
`.summary-section--flip .summary-card, #summarySheetBody .summary-card`, so a plain card flips on
tap/Enter/Space **inside the sheet only**, and its pinned state is released when the pointer leaves.
The keydown handler needs one extra guard for plain cards: without `data-summary-info` they have no ⓘ,
so the existing short-circuit is simply false and does no harm. Everything else in `SummaryCards` (the
ⓘ short-circuit, `is-unflipped` pinning, focus restore) is untouched and now also serves plain cards
where it previously never ran.

### 6.4 The one-frame flash

At ≤768 the cards render inline for the first paint and are moved into the sheet on `DOMContentLoaded`.
The prototype has the same behaviour and it is accepted: the section sits below the content, so the
flash is off-screen on every one of the seven pages. Not fixed here (a CSS `visibility` pre-hide would
need the module to run before first paint, which is not worth a new inline script).

## 7. Behaviour table (the contract to verify)

| state | ≤768 | ≥769 |
|---|---|---|
| cards | not in the page flow; inside `#summarySheetBody` (moved on load) | inline, original DOM position, original look |
| button | visible (unless the page hid the cards) | not rendered visible; `display: none`, not focusable |
| sheet | `hidden` until opened | never shown; `hidden` and `display: none` |
| open | veil + bottom sheet, `body.summary-sheet-open`, focus on × | n/a |
| close | ×, veil tap, Esc → focus back to the button | n/a |
| ⓘ | opens above the sheet (`z-index: 1400` vs `1200`); its Esc closes only it; trap suspended | unchanged |
| scroll lock | `summary-sheet-open` only; closing the ⓘ never unlocks the page | unchanged |
| cards' numbers | same ids, same paint code, live | same |
| resize across 768 | moving up closes the sheet and puts the cards back; moving down moves them in again | — |

## 8. Failure modes

| failure | effect | guard |
|---|---|---|
| `#summarySection` missing (a page drops the partial) | no button, no sheet | `sync()` no-ops on `null`; the flag is only passed where the cards exist |
| section hidden at runtime | button hidden → no empty sheet | MutationObserver + `open()` guard |
| ⓘ opened, then the sheet's Esc | only the ⓘ closes | capture-phase check (§6.2) |
| resize while open | sheet closes, cards return | `sync()` on `_mq.change` |
| page re-paints while the sheet is open | nothing to re-paint (every control that repaints sits outside the sheet) | stated, and asserted in the spec by opening the sheet, then changing a filter on close |
| venue page's `wire:ignore` | section and sheet are both inside it, so the move stays inside the subtree | §2, and a post-repaint check in the spec |
| print from a narrow window | sheet/button hidden | `@media print` rule |

## 9. Out of scope, confirmed

The card arrays and paint code of the seven pages; the ⓘ dialog's markup/behaviour (one z-index rule);
`ui-design-templates/*`; the arrangement page (cards deleted, no button); filters-behind-a-button; the
focus-trap/scroll-lock/shared-frame refactors from `explore-brief.md` §8; print (task 8).

## 10. Rollback

One revert. Nothing persists, no schema, no data, no new dependency. If only the CSS block were rolled
back, the button and sheet would render as unstyled `<div>`s and the module would still move the cards
— so the revert must take all four files together, which is what a single `git revert` does.
