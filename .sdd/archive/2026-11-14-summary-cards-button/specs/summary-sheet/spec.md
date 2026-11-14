# Summary sheet requirements

Frozen `proposal.md` and `design.md` are the authority. Scope: the **seven opted Livewire pages** that
render `partials/ui-summary-bar` — My Timetable, Cohort Timetables, Venue Timetable, My Replacements,
Replacement History, Request Approval, Student My Timetable. `student-class-replacements` keeps the plain
card variant and is fixed only inside the sheet. Replacement Arrangement is out of scope (its dead cards
block was deleted 2026-11-14). Nine `ui-design-templates/*` reference pages also include the partial (eight of them render
it; the arrangement template's include sits inside a Blade comment). They are not opt-ins and are never
edited.

## R1 — the Summary button is a phone-only control in the shared header
- On each of the seven pages the page header MUST render one additional icon button, id `summaryButton`,
  before the guide icon, with `aria-haspopup="dialog"`, `aria-controls="summarySheet"`,
  `aria-expanded="false"` and accessible name `Summary`, plus the tip `Summary`.
- Its glyph MUST be a bar chart (three vertical bars, `currentColor`, 18px, `aria-hidden`), so the icon
  carries no text of its own.
- The button MUST be present in the DOM on every opted page and MUST be `display: none` and
  unfocusable at ≥769px. A page that does not opt in MUST be unchanged (no button, no attribute).
- On a page that also shows a Filters control in the chips row, the Summary button and that control MUST
  NOT read as one pressed pair (no shared frame, no matching pressed fill at rest).
- At ≤768px the button MUST be a bare 44×44 target (transparent surface, primary glyph, hover
  `--color-surface-variant`, `focus-visible` 2px primary ring) — it MUST NOT read as a filled second
  button beside the boxed guide icon.
- The button MUST be visible only when the page has not hidden the summary section; it MUST be hidden
  whenever the section's own display is `none` (including a runtime hide, not only at load).
- Clicking it MUST open the sheet. `Enter`/`Space` on it MUST behave the same as a click.

## R2 — the sheet shows that page's own cards, at ≤768px only
- The sheet MUST be one shared frame (id `summarySheet`) rendered once per page by the same partial
  that renders the cards: overlay + panel with a sticky head, a title `Summary` (`#summarySheetTitle`),
  a close control named `Close Summary`, and a scrollable body `#summarySheetBody`.
- At ≤768px the page's own `#summarySection` MUST be moved (not copied) into `#summarySheetBody` on
  load, keeping every node, id and value intact, and MUST NOT remain in the page flow.
- At ≥769px the section MUST be inline at its original DOM position, and the sheet MUST be `hidden`,
  `display: none` and outside the tab order.
- Crossing the breakpoint in either direction MUST relocate the section accordingly; a resize to ≥769
  while the sheet is open MUST close it, and crossing back MUST relocate it again.
- Opening MUST show the overlay with the dim (`rgba(0,0,0,.45)`) and a bottom-docked panel at ≤768px,
  lock page scroll with a class of its own (`summary-sheet-open`), set `aria-expanded="true"` on the
  button and move focus into the panel.
- Focus MUST be contained in the panel while the sheet is open (Tab and Shift+Tab cycle).
- Closing MUST happen by the close control, by tapping the dim area, and by `Escape`; each MUST hide the
  sheet, release the scroll lock, set `aria-expanded="false"` and return focus to the button.

## R3 — the cards keep their contract
- Every card MUST keep its ids (`sumTotal`, `sumHours`, `sumReplacement`, `sumPending`, `sumConflict`,
  `sumAvailable`, `sumMyClasses`, `sumMyHours`, `sumNone`, `sumApproved`, `sumRejected`,
  `sumCompleted`, `summaryTotal`, …), its class, colour, label, wording, ordering and the 3-line rule;
  no card array and no paint code may be edited.
- The numbers MUST keep updating after a filter/week change exactly as today, both when the cards are
  inline and after they have been relocated.
- On the six flip-card pages the flip behaviour MUST be unchanged outside the sheet and MUST work by
  tap/Enter/Space inside it; the per-card ⓘ MUST still open `#summaryExplainModal` and MUST NOT flip the
  card.
- With the sheet open, the ⓘ dialog MUST paint above the sheet (sheet `z-index: 1200`, the ⓘ lifted to
  `1400` only while `body.summary-sheet-open`), and MUST keep its own behaviour outside the sheet.

## R4 — layering, Esc and scroll lock do not leak
- One `Escape` with the ⓘ open above the sheet MUST close only the ⓘ; the sheet MUST stay open and the
  page MUST stay locked.
- With the ⓘ open, Tab MUST be able to reach the ⓘ's controls (the sheet trap MUST be suspended), and
  MUST return to containment when the ⓘ closes.
- The sheet's scroll lock MUST NOT be released by any dialog that uses `body.summary-locked`.
- Tooltips (`data-tip`) inside the sheet MUST remain readable and MUST NOT be clipped by the sheet's
  scrolling body.

## R5 — the plain variant is usable inside the sheet (student page only)
- Inside the sheet at ≤768px the plain variant MUST render **two** card columns, and **one** column
  below 390px; at 769–1024px it MUST keep today's three columns (asserted at 1024 as well as 769, since
  the tablet block is exactly the one this lane must not disturb).
- The nine `ui-design-templates/*` reference pages MUST be visually and behaviourally unaffected at
  every width. The **inherited invisible deltas** they get from the shared partial are accepted and
  expected: the `hidden` overlay div (design §4.2) and the plain-variant hint spans (design §4.3, and
  every template renders the plain variant because none passes `flipCards`). Nothing else in their DOM
  or styling may change.
- A plain card MUST flip to its explanation on tap and on Enter/Space inside the sheet only; outside the
  sheet (≥769px) its behaviour MUST remain hover-only, unchanged.
- Plain cards MUST be tall enough for their longest back at 320, 390 and 768: no back text may overflow
  or clip its card.
- A card left flipped or pinned in the sheet MUST NOT carry `is-flipped` / `is-unflipped` after a
  relocation back to ≥769px, and the added a11y attributes and marker MUST be removed with it.
- Inside the sheet the plain variant's hint MUST speak to touch ("… Tap a card to see what it counts.");
  the six flip pages' hint MUST read exactly as it does today, inside and outside the sheet.

## R6 — empty view, desktop and print
- When a page hides the summary section because the filtered view is empty, the Summary button MUST be
  hidden too, at any time during the session (not only at load), and opening MUST be impossible then.
- At ≥769px nothing about the cards, their position, look, hover or numbers may change. The only
  **visible** additions are none; the accepted invisible markup deltas are: the `display: none` button,
  the `hidden` overlay, and the hint split described in R5.
- The hint split MUST happen **inside** the existing `.summary-hint` container, keeping that class, its
  info glyph, its layout and its desktop sentence — the container may not be replaced or restyled.
- Printing MUST never capture the sheet: the overlay and button MUST be hidden in print, the cards MUST
  print inline at paper width, an open sheet MUST NOT suppress the printout, and `body.summary-sheet-open`
  MUST NOT keep `overflow: hidden` in a print context.
- After printing from a window at ≤768px the section MUST return into the sheet (cards are not left
  inline while the sheet stays closed).

## R7 — safety and evidence
- No new dependency, no migration, no schema change, no demo-DB write, no reseed; colours, sizes and
  spacing from `theme.css` tokens (the veil literal is the existing `.modal-overlay` value).
- The implementation MUST be one revertable change across the shared partials, `ui-common.js`,
  `theme.css`, the seven page opt-ins and the page changelogs; the arrangement page and the design
  templates MUST NOT be edited.
- Evidence MUST include: the accessible facts — the button announced as **Summary** with an expanded/
  collapsed state, the dialog labelled **Summary**, the close control named **Close Summary**, and the
  plain cards' `role="group"` + label inside the sheet; the header at 320 holding title + semester chip
  + two 44px icons with no wrap and no horizontal overflow; a Playwright spec pinning R1/R2/R5/R6 at
  320/390/768/769/1024/1440 in both themes, including (a) the ⓘ's computed `z-index` exceeding the
  sheet's while the sheet is open, and (b) one `Escape` with the ⓘ open closing only the ⓘ and leaving
  the body locked; the existing summary-id specs still green
  (`pages-parity`, `ui-regression`, `venue-timetable` TC36/TC37/TC58, `cancelled-slot-reuse`,
  `cancelled-slot-layout`), zero page errors on all seven pages, and screenshots at 390 and 1440.
