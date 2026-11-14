# Tasks — `summary-cards-button`

**Batch 4.** `proposal.md`, `design.md` and `specs/summary-sheet/spec.md` are frozen. Every task below is
≤ 2 hours and names the artifacts it must satisfy. Order matters: T1–T3 build the shell, T4–T6 make it
behave, T7–T9 make the plain variant honest, T10–T12 prove it.

**Rule for every task:** no card array and no paint code may be edited on any page. If a task seems to
need that, stop — it is out of scope (proposal §3).

---

## Batch A — the shell

- [x] **T1 — the button (`partials/ui-page-header.blade.php`).** Add `$summaryButton = $summaryButton ?? false`,
  then the `#summaryButton` markup exactly as design §4.1 (bar-chart SVG, `aria-haspopup="dialog"`,
  `aria-controls="summarySheet"`, `aria-expanded="false"`, `aria-label="Summary"`, `data-tip="Summary"`,
  `onclick="SummarySheet.open(this)"`), rendered **before** `.guide-toggle-icon`. No other change to the
  partial. *Satisfies R1.*

- [x] **T2 — the sheet frame (`partials/ui-summary-bar.blade.php`).** Append the `#summarySheet` overlay +
  panel + head + `#summarySheetBody` exactly as design §4.2, after the existing `#summaryExplainModal`
  block and outside the flip/plain conditional, so both variants emit it. Touch nothing else in the file.
  *Satisfies R2.*

- [x] **T3 — seven opt-ins.** Add `'summaryButton' => true,` to the existing `ui-page-header` include on
  `my-timetable`, `cohort-timetable`, `venue-timetable`, `replacement-home`, `request-approval`,
  `student-my-timetable`, `replacement-history`. One line each; nothing else on those pages.
  *Satisfies R1 (seven pages), R7 (arrangement + templates untouched).*

## Batch B — the behaviour

- [x] **T4 — `SummarySheet` module skeleton (`public/js/ui-common.js`).** New section after `SummaryCards`:
  the object shell, `_mq = matchMedia('(max-width: 768px)')`, `init()` on `DOMContentLoaded` (change
  listener + a first relocation `sync()` — **the observer and the `syncButton()` tail both belong to
  T7, not here; ship the first `sync()` as the move/un-move half only**), and the `relocate()` /
  `unrelocate()` pair with the placeholder,
  the idempotence guard, the a11y attributes + `data-sheet-a11y` marker for plain cards, and the state-class
  strip on the way back (design §6). No open/close yet. *Satisfies R2 (relocation), R5 (attribute strip).*

- [x] **T5 — `open()` / `close()`.** Overlay `hidden` toggle, `body.summary-sheet-open`, `aria-expanded`,
  focus in and back (`preventScroll`), the dedicated lock, and the no-op focus note for a resize-up close.
  *Satisfies R2 (open/close/lock/focus).*

- [x] **T6 — Esc, trap, and the ⓘ. ** Capture-phase Esc listener that no-ops while `#summaryExplainModal`
  is visible and otherwise closes the sheet; the Tab/Shift+Tab trap over the panel with the same live
  suspension; nothing added to the ⓘ path. *Satisfies R4.*

- [x] **T7 — `syncButton()` + the observer.** The `getComputedStyle(section).display === 'none'` predicate
  (never `offsetParent`), the `MutationObserver` on `style`/`class`, the re-check inside `open()`, and the
  `beforeprint` / `afterprint` handlers (design §5, §6.1, §6.3). *Satisfies R6 (empty view, print).*

## Batch C — the plain variant (decision 5)

- [x] **T8 — scope the sheet's own CSS.** `.summary-button` (44px, bare, `focus-visible` ring, hidden by
  default and shown at ≤768), `.summary-sheet*`, `body.summary-sheet-open`,
  `body.summary-sheet-open #summaryExplainModal { z-index: 1400 }`, and the print block — all as design §5.
  *Satisfies R1, R2, R3 (layering), R6 (print).*

- [x] **T9 — the plain variant inside the sheet (`replacement-history` — the page served at
  `/student-class-replacements`).** The `#summarySheetBody`-scoped 2-column / 1-column grid,
  the 148px / 132px card heights (**re-measure the 132px at 320 before locking it**), the
  `#summarySheetBody .summary-card.is-flipped` rule, and the hover-parity replacement of `theme.css:1704`
  with the `@media (hover:hover)` + `:not(.is-unflipped)` idiom (design §5.1). *Satisfies R5.*

- [x] **T10 — tap and keyboard for plain cards (same page as T9).** Widen the three `SummaryCards` delegations (click,
  keydown, `mouseout`) to `#summarySheetBody .summary-card`, and split the hint markup per design §4.3
  (pointer span always, touch span only for `@unless($flipCards)`, inside the existing `.summary-hint`
  container) with its two CSS rules. *Satisfies R3 (flip), R5 (hint + tap).*

## Batch D — evidence

- [x] **T11a — the new spec, part 1: relocation, repaint, empty view.** `tests/summary-cards-button.spec.ts`
  pinning:
  **(a)** button hidden at 769 / visible at 768 on two representative pages (visibility, never
  `toHaveCount`);
  **(b)** card ids inline-visible at 1440 and not inline at 390;
  **(c)** after opening — ids inside `#summarySheetBody`, `aria-expanded="true"`, `body.summary-sheet-open`;
  **(h) repaint after relocation** (the lane's one structural risk): on venue-timetable at 390, open the
  sheet, close it, change the week/filter, then assert the ids inside `#summarySheetBody` carry the page's
  **updated** values — and the same change at 1440 leaves the inline cards updated;
  **(k)** the empty-view button hiding, including a **runtime** hide (change the filter on a page that hides
  the section, then assert the button is gone).
  *Satisfies R1 (button), R2 (relocation), R3 (repaint after relocation), R6 (empty view).*

- [x] **T11b — the new spec, part 2: closing, layering, plain variant, print.** The same file, pinning:
  **(d)** × / dim / Esc each restore focus and the inline position;
  **(e)** one Esc with the ⓘ open closes **only** the ⓘ (the body stays locked);
  **(f)** the ⓘ's computed `z-index` exceeds the sheet's while the sheet is open;
  **(g) trap suspension** — while the ⓘ is open, `Tab` reaches the ⓘ's close button, and containment
  resumes after it closes;
  **(i)** the plain page (`replacement-history`, served at `/student-class-replacements`) two-column sheet
  at 390, one column at 320, its tap-to-flip, its added `role="group"` + label, and the stripped
  `is-flipped`/`is-unflipped`/`data-sheet-a11y` after a resize to 900 (any ≥769 value proves the strip);
  **(j) no card back overflows its card** at 320, 390 and 768 — every `.summary-section .summary-card-back`,
  both variants, `scrollHeight <= clientHeight + 1`;
  **(l)** print via `page.emulateMedia({ media: 'print' })`, then back — the cards print inline and the ids
  return inside `#summarySheetBody` at 390 afterwards.
  Widths 320/390/768/769/1024/1440, both themes, across both representative pages.
  *Satisfies R2 (close), R3, R4, R5, R6 (print).*

- [x] **T12 — run the existing inventory.** `pages-parity`, `ui-regression`, `venue-timetable` (TC36/TC37
  **and TC58 at 375×667**), `cancelled-slot-reuse`, `cancelled-slot-layout`, `selection-summary-collapse`,
  `top-zone-round2`, `guide-toggle-icon`, `modal-responsive-width` — plus **never** TC70 (real demo-DB
  write). Record the counts honestly, including any failure that predates this lane.
  *Satisfies R7.*

- [x] **T13 — look at it.** Full matrix (320/390/768/769/1024/1440, light + dark) on **two representative
  pages** — one flip page (my-timetable) and the plain page (replacement-history) — showing the header with
  two icons (no wrap, no overflow at 320), the sheet with that page's own cards, the ⓘ above it, and the
  plain page's taller cards with no clipped back text. The other five pages get **390 + 1440 only**
  (spec R7's own screenshot requirement), each with its sheet open. Include one `data-tip` near the sheet's
  top edge to show it is readable, not clipped by the sheet body. Zero page errors on all seven.
  *Satisfies R1–R6.*

- [x] **T14 — records.** A 3-line entry in each affected page's `page-changelogs/*.md`; a line for the
  arrangement page's comment removal (done 2026-11-14 outside SDD, recorded in `proposal.md` §7b); update
  `.sdd/changes/summary-cards-button/sdd.yaml` to `status: applied` with the evidence paths. Do **not**
  expect an apply-log entry to substitute for the changelogs. *Satisfies R7.*

---

## Not tasks (recorded so nobody adds them)

- The `updateSummaryStats()` dead function on the arrangement page — offered to the user, **not taken**.
- Focus-trap / scroll-lock / shared-sheet-frame refactors (`explore-brief.md` §8) — a later lane.
- Filters behind their own button; print v2; any change to the nine reference templates.

---

## Definition of done

Every R1–R7 requirement has a passing assertion or a recorded manual receipt; the seven pages are clean
in both themes at all six widths; the frozen specs are unchanged; one commit, no push unless asked.
