# Explore brief — `summary-cards-button`

Facts gathered on 2026-11-14 against the live pages at `http://127.0.0.1:8030` (testing DB),
before any proposal was written. Measurements are real browser reads, not estimates.

---

## 1. The brief's premise needs one correction

`prompts/pi-prompt-summary-cards-button.md` says the cards "sit across the top" and on a phone
"fill most of the first screen, so the actual content starts far below the fold".

**They do not sit at the top any more.** On every page that renders them, the cards come *after*
the main content:

| page | what sits between the header and the cards |
|---|---|
| My Timetable (staff) | week nav → **timetable grid** → legend → cards |
| Cohort Timetables | cohort pickers → **timetable grid** → legend → cards |
| Venue Timetable | filters → **venue grid** → legend → cards |
| My Replacements | toolbar → **merged list + pagination** → cards |
| Replacement History (student) | chips/filters → **class list** → cards |
| Request Approval | toolbar → **request list** → cards |
| Student My Timetable | week nav → **timetable grid** → legend → cards |

Measured at 390 × 844 (phone), scrollY = 0:

| page | document height | cards' top | first 844px contains |
|---|---|---|---|
| my-timetable | 2287 | 1687 | header, week nav, the day cards; cards are ~2 screens down |
| cohort-timetable | 2664 | 2064 | pickers, grid |
| venue-timetable | 4511 | 3871 | filters, grid |
| replacement-home | 2001 | 1402 | toolbar, list |
| replacement-history | 2937 | 2514 | chips, list |
| request-approval | 1569 | 970 | toolbar, list |
| student-my-timetable | 2622 | 2022 | header, week nav, day cards |

So the value of this change is **not** "reclaim the first screen". It is:

1. the stats become reachable in **one tap from the header** instead of a scroll to the bottom
   (0–3900px away depending on the page);
2. a phone page gets **~350px shorter** (the section is 348px tall at 390 on flip pages,
   172px on history);
3. the five numbers stop being the one thing a phone user never sees — today they sit between the
   last content block and the footer.

This correction does not weaken the ask (decision 2 in the brief — "reachable **only** through the
Summary button" — still holds), but the proposal must not claim a fold saving that does not exist.

## 2. How the eight pages feed the partial

All eight include `resources/views/partials/ui-summary-bar.blade.php`. Nothing else is shared: each
page hand-writes its `cards` array, and each page's own JS paints the numbers.

| page | blade | `flipCards` | cards (label · class) | ⓘ per card |
|---|---|---|---|---|
| My Timetable | `livewire/my-timetable.blade.php:55` | yes | Total Classes, Teaching Hours, Replacements, Pending, Conflicts | 5 |
| Cohort Timetables | `livewire/cohort-timetable.blade.php:141` | yes | Total Classes, Teaching Hours, My Teaching Classes, My Teaching Hours, Conflicts | 5 |
| Venue Timetable | `livewire/venue-timetable.blade.php:415` | yes | Total Slots, Available, My Teaching Classes, My Teaching Hours, Occupied | 5 |
| My Replacements | `livewire/replacement-home.blade.php:243` | yes | Classes to Arrange, Class Hours to Replace, Pending, Approved, Rejected | 5 |
| Replacement History | `livewire/replacement-history.blade.php:154` | **no** | Classes Awaiting Arrangement, Hours Awaiting Arrangement, Pending, Approved, Completed | **0** |
| Request Approval | `livewire/request-approval.blade.php:287` | yes | Total Requests, Pending, Approved, Rejected, Total Reviewed | 5 |
| Student My Timetable | `livewire/student-my-timetable.blade.php:40` | yes | Total Classes, Class Hours, Replacements, Pending, Conflicts | 5 |
| Replacement Arrangement | `livewire/replacement-arrangement.blade.php:2718` | — | **the include is commented out — this page renders no stats cards at all** | 0 |

Two consequences:

- `#summaryExplainModal` (the ⓘ dialog) is rendered **only by the flip variant**. Six pages have it;
  Replacement History has none, and the arrangement page has nothing to explain.
- Replacement Arrangement also has a *different* thing called "Selection Summary" (`#summaryBody`,
  `.sel-summary-card`) — the picked-slot panel, not stats. It is not this change's business and must
  not be confused with it (its fold was removed on 2026-11-14, commit `c2fe630`).

**Replacement Arrangement therefore has no cards to put behind a button.** Options are in §7.

Card ids are frozen names the other pages' JS and several specs rely on: `sumTotal`, `sumHours`,
`sumReplacement`, `sumPending`, `sumConflict`, `sumAvailable`, `sumMyClasses`, `sumMyHours`,
`sumNone`, `sumApproved`, `sumRejected`, `summaryTotal`, … — whatever we build must not rename,
duplicate or re-render them.

## 2b. Student `student-class-replacements` is the odd one out (found 2026-11-14, after the brief)

It is the **plain** card variant (`flipCards` not passed), and it differs from the other seven in four
ways at phone width — measured at 390 × 844, dark:

| | flip pages (6) | `student-class-replacements` |
|---|---|---|
| columns at 390 | **2** (`.summary-section--flip .summary-bar`, theme.css:1947) | **3** — `114px 114px 114px` |
| card height | 96px | 60px |
| labels | title case, 2 lines max ("Total Classes") | self-caps, 3 wrapped lines ("CLASSES AWAITING ARRANGEMENT") |
| ⓘ explanation | one per card (5), opens `#summaryExplainModal` | **none (0)** |
| how the back is read | tap / Enter / Space (`SummaryCards.toggle`) | **`:hover` only** — `.summary-card:hover .summary-card-inner` (theme.css:1704), so on a touch screen the five descriptions are unreachable |
| hint line | "Hover **or tap** a card to learn more." | "**Hover** a card to learn more." — wrong on a phone, and it would be wrong *inside* the sheet |

**And there is a real CSS defect behind the 3 columns:** the phone rule
`@media (max-width: 768px) { .summary-bar { grid-template-columns: 1fr } }` (theme.css:4358) is
**dead** — the later `@media (max-width: 1024px) { .summary-bar { grid-template-columns: repeat(3, 1fr) } }`
(:4616) wins at 390 on source order. Only the `min-height: 60px` from the 768 block survives, because
nothing re-declares it. So the plain variant's intended phone layout has never rendered.

Consequence for this lane: a Summary sheet on that page would faithfully show **that** — three cramped
columns, no ⓘ, and a hint telling a touch user to hover. That is a look decision, not something this
lane may take on its own (see proposal decision 5).

## 3. Where the button must live — and it is already shared

`resources/views/partials/ui-page-header.blade.php` is included by **all eight** pages and already
renders the help "?" (`guideToggle => true` on every one of the eight). Its right-hand slot is a
single `.guide-toggle-icon` (40×40, 44×44 at ≤768, token colours, `aria-expanded` synced by
`GuideModal`). The button belongs here, as an opt-in sibling:

- `$summaryButton = $summaryButton ?? false` (same shape as `$guideToggle`), so pages that never
  opt in are byte-identical.
- Placed **before** the guide icon (the prototype's order).
- Bare: the prototype made it transparent (`border-color: transparent; background: transparent`)
  precisely so it does not read as a second filled button beside the white-boxed help. That decision
  survives into this lane; the guide keeps its box.

Header at 390 today: title + semester chip stacked left, one 44px icon right. A second icon costs
44 + 12(gap) = 56px of the main column. Checked widths: `Cohort Timetables` at 22px bold ≈ 200px
against ~250px available at 390, and the long semester chip (`202605 · 21 Sep 2026 ~ 27 Dec 2026`)
already wraps to its own line at 390. At 320 it is tighter — the 320 check stays in the test plan.

## 4. Existing patterns to reuse (and the one to refuse)

- **Sheet overlay:** `.modal-overlay` (`position: fixed; inset: 0; z-index: 999`; at ≤600 `z-index:
  1000`, `align-items: flex-end`, blurred dim). Used by the ⓘ dialog and the guide dialog.
- **Scroll lock:** `body.summary-locked { overflow: hidden }`, added by `SummaryCards.open()` and
  `GuideModal.open()`, removed by their `close()`. **It is a single shared class with no refcount** —
  if the new sheet also uses it, closing the ⓘ (which is on top of the sheet) removes the lock while
  the sheet is still open, and the page scrolls behind it. The sheet needs its **own** class.
- **Focus return + Esc:** both dialogs do it by hand (`_lastTrigger.focus({preventScroll:true})`, a
  `keydown` listener). There is **no shared focus-trap helper** anywhere in `ui-common.js` — the
  house has never trapped Tab. Anything we add for the sheet is new code (see §8, "safe to share").
- **Tooltips:** the tooltip engine paints at `z-index: 9999`, so `data-tip` inside the sheet is
  already above any overlay we build.
- **Native `<dialog>`:** the arrangement page's phone info/venue sheets use
  `<dialog>.showModal()` — focus containment, Esc and `inert` background for free. **Refused for this
  change**, with evidence: `.showModal()` puts the dialog in the **top layer**, which beats *any*
  z-index. Probe at 390: opened `#arrangementMobileVenue`, then injected a div with
  `position: fixed; inset: 0; z-index: 99999` — `document.elementFromPoint(195, 400)` still returned
  the dialog. So a dialog-based sheet would put the existing ⓘ `.modal-overlay` **behind** it, and
  satisfying "the ⓘ opens above the sheet" would force converting that frozen modal to a dialog too.
- **The prototype** (`ui-prototype/my-replacements.html`, `request-approval.html`) is the
  behaviour of record for this job. Its numbers: sheet overlay `z-index: 1200`, `.cl-sheet` bottom
  sheet with sticky head (title + ×), `body.cl-sheet-open` scroll lock, and
  `#summaryExplainModal { z-index: 1400 }` — one rule to lift the ⓘ above the sheet. It also
  **moves** `#summarySection` into the sheet body at ≤768 (`moveIntoSheet`/`moveOutOfSheet`, with a
  placeholder to put it back), and returns focus to its button.

## 5. The contract that decides the approach: pages toggle the section's own `display`

```
public/js/ui-common.js:2190   syncSummarySection(visible) → s.style.display = visible ? '' : 'none'
cohort-timetable.blade.php:472,538 →  'none'   (no timetable shown: "cards full of zeros are noise")
cohort-timetable.blade.php:544    →  ''       (timetable back)
```

An **inline** `display` beats a media query, so a CSS-only "hide the section at ≤768" cannot win
without `!important`, and overriding it inside the sheet would resurrect a zeroed card set the page
deliberately hid. The page's own intent has to travel with the cards. This is the single strongest
argument for moving the node rather than re-styling it in place.

## 6. What could break

| risk | detail |
|---|---|
| inline `display` toggles | §5 — the section is hidden/shown by page JS; a move preserves it, a CSS media query fights it |
| ⓘ dialog layering | must paint above the sheet; needs the prototype's z-lift (sheet 1200 / ⓘ 1400) |
| scroll-lock collision | `body.summary-locked` is shared and unrefcounted (§4); the sheet needs its own class |
| card flip | `.summary-section--flip` cards flip on click/tap via `SummaryCards.toggle`; the sheet must not swallow those taps, and the card's own ⓘ must still win (it already `stopPropagation`s) |
| `data-tip` tooltips | engine is document-wide and z 9999 → fine, but a tooltip anchored to a card near the sheet's top edge must not be clipped by the sheet's `overflow-y: auto` — same rule as the session-picker (list inside a pop-up is never clipped) |
| Livewire morph | none of the eight blades uses `wire:model`/`wire:click`; `venue-timetable` wraps its JS surface in `wire:ignore` (lines 373–474, the summary bar sits inside it). Moving that node out of the `wire:ignore` subtree is the one place a later Livewire re-render could touch it |
| Replacement History's non-flip backs | that page has **no** ⓘ and its card backs are revealed by `:hover` only (`.summary-card:hover .summary-card-inner`), so on touch its explanations are already unreachable today, inline or in a sheet. Not this lane's fix; recorded so nobody thinks the sheet broke it |
| specs | `pages-parity.spec.ts:69,100` and `ui-regression.spec.ts:387` assert the frozen ids `toBeVisible()`; `venue-timetable.spec.ts:329-337` counts `.summary-bar .summary-card`; `cancelled-slot-layout.spec.ts:245-250` asserts `toHaveText` on the values. All of them run at ≥1280, where nothing changes — but any new spec must pin that the ids are *hidden* at ≤768 and present in the sheet |
| print | print is task 8 and not shipped; today nothing in `@media print` hides the section, and paper width (≈794px) is above the breakpoint, so print stays inline and unchanged |
| mock/design templates | `resources/views/ui-design-templates/*` carry their own copies of the include; they are reference only and must not be edited |

## 7. Decisions this lane cannot take alone

1. **Replacement Arrangement.** Its cards are commented out (`:2718`), so a Summary button there
   would open an empty sheet. Choose: (a) leave the page out of this lane (7 pages), (b) re-enable
   the commented block as part of this lane (that also means wiring its four numbers, which is more
   than "button + panel"), or (c) ship the button and show the Selection Summary there instead
   (mixes two different panels — not recommended).
2. **The hint line.** `.summary-hint` ("Stats reflect the **selected period** … Hover or tap a card
   to learn more.") is part of the section. It moves into the sheet with the cards unless told
   otherwise; on a phone it is arguably the useful part (it says the numbers follow the filters).
3. **Where the sheet stops.** Decision 1 in the brief is "≤768 only, cards inline above that". That
   means a 768×1024 tablet gets the button and no inline cards. Confirm that is intended (it is what
   the prototype does).
4. **Empty state.** When a page hides the section because there is nothing to show (cohort page's
   zeroed-empty view), should the Summary button also hide? Recommended: yes — a button that opens
   an empty sheet is worse than no button; needs one shared helper, not per-page code.

## 8. Genuinely common, safe to share later (listed, NOT changed here)

- **A focus-trap helper** (`trapFocus(container, {onEscape})`) — the guide dialog, the ⓘ dialog and
  this new sheet all hand-roll focus return and none traps Tab. One helper would fix four openers.
- **`body.summary-locked` refcount** — two classes in use today (`summary-locked`, and the prototype's
  `cl-sheet-open`); a tiny `lockScroll(key)`/`unlockScroll(key)` would end the "closing A unlocks under
  B" class of bug.
- **A sheet-frame partial** — the arrangement phone sheets (`#arrangementMobileInfo`,
  `#arrangementMobileVenue`) and this new Summary sheet would then share head/×/body/footer.
- Replacement History and the arrangement page could opt into `flipCards` for consistency — a
  visual decision, not a cleanup.

## 9. Open questions carried into propose

- Does the sheet survive a page's own re-paint while open? (Nothing re-paints while it is open,
  because every control that triggers a repaint sits outside it. Confirm in the test plan.)
- Does the moved section keep its `2-col` phone grid inside the sheet? (Yes — `.summary-section--flip
  .summary-bar` is already 2 columns at ≤768 and 1 column below 390; both were measured.)
- At 320 the header holds title + chip + two 44px icons? (Test plan: screenshot + no-wrap check.)
