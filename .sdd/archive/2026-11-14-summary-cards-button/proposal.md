# Proposal — `summary-cards-button`

**Status:** draft for review (Batch 1). Nothing frozen yet. No code has been written.

Source brief: `prompts/pi-prompt-summary-cards-button.md` (SDD explore + propose only).
Facts: `explore-brief.md` in this folder.
Task-list entry: `prompts/for-ai-to-read/my to-do list.md` §6.

---

## 1. Why

Every staff and student page that shows the statistics cards renders them from one partial
(`partials/ui-summary-bar.blade.php`), but on a phone those cards are effectively invisible: they sit
**after** the page's main content, 970–3871px down (measured at 390×844 — see explore §1), between the
last content block and the footer. The header, meanwhile, has one free slot beside the help icon.

The ask is to make those numbers reachable in one tap on a phone, and to leave a laptop exactly as it
is today:

- at **≤768px** each page's cards move out of the page flow into a **Summary** sheet, opened from a
  bar-chart icon in the page header next to the help "?";
- at **≥769px** nothing changes — same cards, same place, same behaviour.

"Reclaiming the first screen" is **not** the justification (explore §1): the cards are already below
the content. What this buys is reach, a shorter phone page (~170–350px, depending on the card set), and a discoverable entry point
for numbers a phone user presently never reaches.

## 2. In scope

1. One **Summary** button in the shared page header, ≤768px only, on every page that renders the
   cards, opening a bottom sheet that contains that page's own cards.
2. One shared sheet frame (title "Summary", sticky head, ×, scrollable body, dim, scroll lock),
   rendered once per page from the same partial that renders the cards.
3. Closing by ×, dim tap, Esc; focus returns to the button.
4. The cards' wording, colours, counts and 3-line rule are untouched; the existing ⓘ explanation
   dialog keeps working and opens **above** the sheet.
5. Desktop (≥769) visually and behaviourally identical: same cards, same place, same numbers,
   same interactions. Opted pages do gain one `display:none` button and one hidden overlay in the
   desktop DOM — that is markup, not behaviour, and nobody should claim "byte-identical" at verify.

## 3. Out of scope (explicit)

- **The eight card lists and the code that paints their numbers.** Not moved, not merged, not
  refactored, not "while we are here" (brief decision 3).
- The ⓘ dialog's markup, copy or behaviour (only a z-index rule so it can win over the sheet).
- The `ui-design-templates/*` reference pages.
- Filters behind their own button at phone width (the prototype's `.cl-sheet-btn` work) — that is a
  different, larger change; the brief only requires that the new Summary button never *looks* like a
  pressed pair with a Filters control.
- Replacement History's hover-only card backs and its missing ⓘ (pre-existing on touch; recorded in
  explore §6, not fixed here).
- Print (task 8). Paper width is above the breakpoint, so print stays inline and unaffected.
- Any refactor from explore §8 (focus-trap helper, scroll-lock refcount, shared sheet frame partial)
  — listed for a later lane, not touched here.

## 4. Options

### Option A — move the section into a shared sheet (prototype-faithful) ← **recommended**

`ui-summary-bar` also emits the sheet frame (`#summarySheet` → sticky head + `#summarySheetBody`).
A new `SummarySheet` module in `ui-common.js` moves `#summarySection` into that body at ≤768, leaving
a placeholder to put it back at ≥769, and restores focus to the header button on close.

- **Why it wins:** the pages already hide/show the section by writing its **inline** `display`
  (`syncSummarySection()`, cohort-timetable's empty view). Only a move carries that intent into the
  sheet — a CSS re-style would have to fight it with `!important` and would resurrect a zeroed card
  set the page deliberately hid.
- It is the behaviour already checked on two prototypes at 320/390/768/769/1024/1280/1600, and it
  reuses the sheet frame the prototype proved (sticky head, `z-index: 1200`, `body.cl-sheet-open`).
- **Cost:** DOM moving. On `venue-timetable` both the section's origin (`:415`) and its destination
  (the sheet frame emitted by the same partial) sit **inside** the `wire:ignore` subtree (373–474), so
  the move stays inside it and a future morph cannot reach the moved node either. No
  `wire:model`/`wire:click` exists on any of the eight blades, so nothing morphs today; the test plan
  still re-checks that page after a week/filter change.

### Option B — re-style the section in place (no DOM move)

At ≤768 the section becomes `position: fixed` as the sheet, with the head/× rendered inside it by the
partial; one body class switches it.

- **Pros:** no DOM surgery, so no Livewire/`wire:ignore` question at all.
- **Cons:** must beat the pages' inline `display` with `!important` in both directions, and then has to
  re-implement "the page hid this because it is empty" inside the sheet — the page's decision would be
  lost. Two places would own the section's visibility, which is exactly the tangle this repo keeps
  paying for.

### Option C — native `<dialog>.showModal()` sheet

- **Pros:** focus containment, Esc and `inert` background come free.
- **Cons:** fatal for requirement 5. `.showModal()` renders in the **top layer**, which beats any
  z-index — proved at 390 by injecting a `position: fixed; z-index: 99999` div over an open
  `<dialog>`: `elementFromPoint` still hit the dialog. The existing ⓘ `.modal-overlay` would sit
  **behind** the sheet; fixing that means converting the frozen ⓘ modal to a `<dialog>` too.
  Rejected.

**Option D — a separate shared wrapper partial** (`partials/ui-summary-sheet`) included by each page
next to the cards: the option the source brief named by example. It is dominated by Option A — same
sheet frame, but eight extra include lines and two files to keep in step — so it is listed for
completeness and not taken.

**Recommendation:** Option A.

## 5. Design of record (Option A)

### 5.1 Breakpoint
`768px`, matching the brief and the CSS already in the file (`.summary-section--flip .summary-bar`
turns 2-column at ≤768, 1 column below 390). One `matchMedia('(max-width: 768px)')` in JS, its
`change` event re-syncing on resize/rotate. ≥769: section inline, sheet `hidden`, button not rendered.

### 5.2 The button (in `ui-page-header`)
```blade
@if($summaryButton)
  <button type="button" class="summary-button" id="summaryButton"
          aria-haspopup="dialog" aria-controls="summarySheet" aria-expanded="false"
          aria-label="Summary" data-tip="Summary">
      {{-- bar-chart glyph, 18px, stroke currentColor, aria-hidden --}}
  </button>
@endif
```
- Opt-in flag `$summaryButton = $summaryButton ?? false`, the same shape as `$guideToggle`; a page that
  does not pass it is byte-identical.
- Rendered **before** `.guide-toggle-icon`, and **bare** (transparent background/border; hover fills
  with the surface-variant token), because the prototype made it bare on purpose so it does not read as
  a second filled button beside the boxed help. 44×44 at ≤768, like the help — which means it needs the
  same **explicit opt-out from the global 48px mobile button floor** that `.guide-toggle-icon` already
  carries (`theme.css:1192–1196`), or the two icons overflow the header at 320.
- Hidden at ≥769 by CSS (`.summary-button { display: none }` + `@media (max-width: 768px)`), so no page
  needs JS to hide it, and `aria-expanded` is only ever rendered meaningful at phone widths.

### 5.3 The sheet (in `ui-summary-bar`, i.e. once per page, beside the cards it shows)
```blade
<div class="summary-sheet-overlay" id="summarySheet" hidden
     onclick="if(event.target===this)SummarySheet.close()">
  <div class="summary-sheet" role="dialog" aria-modal="true" aria-labelledby="summarySheetTitle">
    <div class="summary-sheet-head">
      <h2 class="summary-sheet-title" id="summarySheetTitle">Summary</h2>
      <button type="button" class="summary-sheet-close" aria-label="Close Summary"
              data-tip="Close" onclick="SummarySheet.close()">✕</button>
    </div>
    <div class="summary-sheet-body" id="summarySheetBody"></div>
  </div>
</div>
```
- Emitted by the partial that already renders the cards, so no page can ship the button without the
  panel and no extra include line is needed on any of the eight pages.
- `hidden` until JS moves the cards in; the overlay is `position: fixed; inset: 0; z-index: 1200` with
  the dim `rgba(0,0,0,.45)` — a literal, not a token, because it is byte-for-byte the frozen
  `.modal-overlay` value (`theme.css:2039`); the panel a bottom sheet (`border-radius` top only, `max-height:
  88dvh`, `overflow-y: auto` with `overscroll-behavior: contain`), docked flush at ≤768.
- The body inherits the cards' existing phone grid (2 columns at ≤768, 1 below 390) — no new card CSS.

### 5.4 The JS module (`ui-common.js`, next to `SummaryCards`)
`SummarySheet.init()` — binds once on `DOMContentLoaded`: `open()`, `close()`, `sync()`.
- `sync()`: if the media query matches, `relocate(#summarySection → #summarySheetBody)` with a
  placeholder element left in the original spot; else `unrelocate()` and `close()`. Also hides the
  button when the section has no visible cards (explore §7 decision 4, recommended: hide).
- `open()`: `hidden = false`; store the opener; `body.classList.add('summary-sheet-open')`;
  `aria-expanded="true"`; focus the close button (`preventScroll`); trap Tab within
  `[role="dialog"]`; Esc and dim-tap close.
- `close()`: `hidden = true`; drop the lock; `aria-expanded="false"`; restore focus to the button;
  collapse the trap and the Esc listener.
- **Its own scroll-lock class** (`summary-sheet-open`), *not* `body.summary-locked` — that one is shared
  with the guide and ⓘ dialogs and has no refcount, so closing the ⓘ above the sheet would unlock the
  page underneath (explore §4).
- The ⓘ dialog keeps its own code path untouched; one CSS rule lifts it above the sheet — i.e.
  `body.summary-sheet-open #summaryExplainModal { z-index: 1400 }`, the prototype's own number, with the
  sheet at 1200 and the tooltip engine already at 9999. The rule is scoped to the open-sheet state, so
  the ⓘ dialog is unchanged everywhere else.
- **Layering rules the ⓘ above the sheet forces (pin in design.md, they are behaviour, not detail):**
  1. **Esc precedence.** Both the ⓘ's existing document-level Esc handler and the sheet's would fire on
     one keypress. The sheet's handler must **no-op while `#summaryExplainModal` is visible**, so one
     Esc closes the ⓘ only, and the sheet (and the scroll lock) survives.
  2. **Trap suspension.** The Tab trap is suspended while the ⓘ is open, or its close button becomes
     keyboard-unreachable (the ⓘ is outside the trapped container). Resumed on the ⓘ's close.
- **Empty-view trigger.** The button's visibility cannot be decided only inside `sync()` (init +
  media-query change): a page can hide the cards at *runtime* (`syncSummarySection(false)` when a
  filtered week has no classes). `SummarySheet` therefore **wraps the existing global
  `syncSummarySection`** — one function, one call site, no per-page code — and re-checks visibility in
  `open()` as a backstop, so tapping can never open an empty sheet.

### 5.5 Files to touch (exact)

| # | file | change |
|---|---|---|
| 1 | `resources/views/partials/ui-page-header.blade.php` | `$summaryButton` flag + the button markup (7 lines) |
| 2 | `resources/views/partials/ui-summary-bar.blade.php` | the sheet frame markup only (no card changes) |
| 3 | `public/js/ui-common.js` | new `SummarySheet` module (`relocate`/`unrelocate`, open/close/sync, trap, Esc, lock) |
| 4 | `public/css/theme.css` | `.summary-button`, `.summary-sheet*`, the z-lift rule, `body.summary-sheet-open` |
| 5–11 | `livewire/{my-timetable, cohort-timetable, venue-timetable, replacement-home, request-approval, student-my-timetable, replacement-history}.blade.php` | one flag on the existing `ui-page-header` include: `'summaryButton' => true` |
| 12 | `page-changelogs/*.md` | a 3-line entry in each affected page's changelog |

No new dependency, no migration, no schema change, no demo-DB work. Colours/sizes from `theme.css`
tokens only.

### 5.6 Data flow
`ui-summary-bar` → cards (`#sumTotal`, `#sumHours`, … the frozen ids) → `SummarySheet.sync()` moves the section into
`#summarySheetBody` at ≤768 → the page's own paint code keeps writing the same ids wherever they now
sit (it looks them up by id, not by path — no page code changes). Nothing re-paints while the sheet is
open, because every control that repaints (filters, week nav, pickers) is outside it.

## 6. Test plan

**Manual, per width** (320, 390, 768, 769, 1024, 1440; light + dark; all pages in scope):
1. ≤768: no cards in the page flow, one bare chart icon beside the help; tap → bottom sheet titled
   Summary with that page's own cards (same labels/colours/counts as desktop); dim + scroll locked.
2. Close by ×, by dim, by Esc — each returns focus to the button; nothing scrolls behind.
3. ≥769: cards exactly where they are today, **no** button, sheet absent from the tab order.
4. Open the sheet at 390, then resize to 800, then back to 390: the sheet closes on the way up, cards
   return to their own place, the
   button appears/disappears, no duplicated cards.
5. ⓘ on a card inside the sheet opens **above** the sheet; closing it returns to the card and the page
   behind stays locked; a `data-tip` tooltip near the sheet's top edge is not clipped.
6. Flip cards still flip by tap and by Enter/Space inside the sheet; the ⓘ never flips its card.
7. Filters button (where a page has one) and the Summary icon do not read as a pressed pair.
8. Empty-view page (cohort page with a week that has no classes): cards hidden → Summary button
   hidden too (the recommended answer to explore §7.4), no empty sheet.
9. Header at 320/390: title + semester chip + two 44px icons, no wrap, no overflow.

**Keyboard / SR:** Tab reaches the button (visible 2px primary ring); Enter/Space opens; focus lands on
the close button; Tab cycles inside the sheet only; Shift+Tab wraps; Esc closes; `aria-expanded` flips;
the dialog is announced with its title; the moved cards still read in the same order.

**Automated:** a new spec pinning (a) the button **hidden** at 769 and **visible** at 768 on two
representative pages — visibility, never `toHaveCount`, because the button is Blade-rendered and only
`display:none` above the breakpoint, (b) card ids visible inline at 1440 and hidden at 390, (c) after opening: ids visible inside
`#summarySheetBody`, `aria-expanded="true"`, `body.summary-sheet-open`, (d) Esc/dim/× all restore focus
and the inline position, (e) `#summaryExplainModal` has a higher computed `z-index` than the sheet while
open. Existing specs that assert the frozen ids (`pages-parity.spec.ts:69,100`,
`ui-regression.spec.ts:387`, `venue-timetable.spec.ts:329-337`, `cancelled-slot-layout.spec.ts:245-250`)
run at ≥1280 and must stay green — **except two that already read the cards at phone width** and must be
left alone: `venue-timetable.spec.ts` TC58 (`:548–551`, 375×667, counts `.summary-bar .summary-card` = 5)
and `cancelled-slot-reuse.spec.ts:259–260` (reads the values). Both count/read attached nodes rather
than visibility, so the move keeps them green and they are the accidental canaries for this lane:
nobody may later "tighten" them into visibility assertions.

## 7. Decisions needed before apply

1. **Replacement Arrangement** renders no stats cards today (its include is commented out at
   `replacement-arrangement.blade.php:2718`). Pick one:
   **(a)** leave that page out of this lane (7 pages ship), **(b)** re-enable the commented block in
   this lane (also means wiring four numbers — bigger than "button + panel"), or **(c)** show its
   Selection Summary in the button instead (mixes two different panels — not recommended).
   *Recommended: (a), then its own small lane if the stats are wanted back.*
2. **The hint line** (`.summary-hint`) moves into the sheet with the cards. Keep it (it explains that
   the numbers follow the filters) or hide it inside the sheet? *Recommended: keep.*
3. **768 is phone.** A 768×1024 tablet gets the button and no inline cards. Confirm (it is what the
   prototype does).
4. **Empty view** — hide the Summary button when the page has hidden the cards. Confirm. *Recommended:
   yes.*

5. **`student-class-replacements` is the plain card variant** (explore §2b): 3 cramped columns at 390
   (`114px` each, 60px tall), **no ⓘ**, backs reachable by `:hover` only, and a hint that says "Hover a
   card to learn more." — on a touch screen. The sheet would faithfully reproduce that. Pick one:
   **(a)** include the page as-is (the sheet shows the cramped variant, and the hover hint goes into a
   phone sheet); **(b)** leave this page out of this lane and give it its own small lane; **(c)** fix the
   variant as part of this lane — 2 columns at ≤768 (the dead phone rule at `theme.css:4358`, see
   explore §2b), tap-to-flip, and a hint that speaks to touch. *(c) is the only option that makes the
   page's sheet as good as the other six, but it changes a live page's look, which the brief says needs
   your word first. **Recommended: (c)** — the CSS defect is real and one ordering fix is all the
   columns need; the tap-to-flip is the same three lines `SummaryCards.toggle` already uses.*

## 7b. Decisions taken (user, 2026-11-14) — do not reopen

1. **Replacement Arrangement: leave the page out of this lane (option a) *and* delete its dead
   commented-out `ui-summary-bar` block.** Done at the user's instruction on 2026-11-14 **outside SDD**
   (15 lines removed at the old `:2718`, verified 200 + 0 page errors at 1360 and 390, 154 grid cells
   unchanged). Scope is **seven pages**; the arrangement page gets no Summary button.

   Left behind on that page and **not** touched: `updateSummaryStats()` (`:3514`) and its single call
   (`:3509`) now have no sink — the ids they wrote (`#sumTotal`, `#sumAvailable`, `#sumPending`,
   `#sumUnavailable`) only ever existed in the deleted block. The function is guarded (`if (sumTotal)`)
   so it no-ops safely; removing it is a separate two-line cleanup, offered to the user rather than
   taken.
2. **Keep the hint line** (`.summary-hint`) — it moves into the sheet with the cards.
3. **768 is phone: confirmed.** ≤768 gets the button and no inline cards; ≥769 unchanged, including a
   768×1024 tablet in portrait which therefore gets the button.
4. **Hide the Summary button on an empty view: yes.**
5. **Fix the plain card variant here (option c).** Inside the sheet the student page's cards get
   2 columns at ≤768 (1 below 390), tap-to-flip, and a hint that speaks to touch. Scope note: the
   variant's grid rule is live CSS for a section that, after this lane, is only ever seen inside the
   sheet at ≤768 — so the fix changes nothing a laptop sees and nothing outside the sheet.

## 8. Risks

- The DOM move is the only structural risk, and it is smaller than it first looks: on
  `venue-timetable` the section and the sheet frame it moves into are both inside the same
  `wire:ignore` subtree (373–474). No Livewire re-render exists on these pages today; the spec
  re-checks the venue page after a week/filter change to prove the cards survive a repaint both inline
  and moved.
- Scroll-lock collision is avoided by a dedicated class (§5.4); the ⓘ above the sheet is one z-index
  rule, so no change to that frozen dialog.
- A phone user loses the bottom-of-page cards only — decision 2 in the brief already accepted that,
  and they are one tap away.
