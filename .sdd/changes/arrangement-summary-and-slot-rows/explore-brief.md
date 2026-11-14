# Explore brief — arrangement: always-open summary + readable slot rows

**Page:** `resources/views/livewire/replacement-arrangement.blade.php` (one file).
**Created:** 2026-11-14 · user asks 1) and 2) in one message, plus one answered question.

## The two asks

1. **"Make the Selection Summary NOT collapse/expandable."** Today the card has a chevron
   button (`#summaryToggle`, blade ~2596) that folds only the body (`#summaryBody`), with the
   click handler at blade 7520. The user wants the card permanently open — no toggle.
2. **Split the slot list row into labelled rows with icons.** Today one `<span class="slot-dd-item-label">`
   holds everything on one wrapping line (blade ~6733):
   `(P) Week 8 · Nov 10, 2026 · 1:00 PM - 2:00 PM @ B009 · <users glyph> RSD3` (the glyph is the
   inline users SVG at blade 6722, not an emoji)
   plus a status badge on the right. Wanted rows:
   a) time range · b) venue · c) cohort code(s) and how many cohorts.
   The type/week/date line stays as the item's first row, mirroring the trigger above the panel.

## Decisions from the user

- **Multi-cohort display = option A:** count the cohorts *underneath*, not the distinct codes.
  `DFT + DSF (2 cohorts)`; `DSF2 (2 cohorts)` even when both cohorts shorten to the same word;
  no bracket when there is only one. (User answered "a", 2026-11-14.)
- Selection Summary: no fold at all (not "open by default").

## Facts measured in the tree (not assumed)

| Fact | Evidence |
|---|---|
| The fold is one button + one listener | blade `2599` (`#summaryToggle`, `.ra-card-toggle`), `7520-7531` (click) |
| Conflict Schedule and Class Details keep their own toggles | `#conflictCollapse` (button :2468, listener :7502) and `#detailsToggle` (:2522) — the `.ra-card-toggle` family is shared. There is no `#conflictToggle`. |
| The Summary is never a 56px rail | the 56px rail rules (blade 1894-1929, 2007-2011) target only the `#conflictCollapse` / `#detailsToggle` cards; no rail rule exists for `.sel-summary` |
| The slot label is built in one template string | `item.innerHTML` at blade `6733-6736`; the label span is line `6734` |
| An icon set already exists on this page | blade `3653-3658`: `icons.venue` / `icons.week` / `icons.time` / `icons.duration`, all 13px, `currentColor`, `aria-hidden` |
| The cohort tag already has its own class + users glyph | `.slot-dd-item-cohort` (CSS 344); `const cohortHtml` starts at `6721`, the span is line `6722`, the `: ''` fallback `6723` |
| Cohort source | `MockData.courses[].sessions[sessionId].cohorts`, shortened to the first token, de-duplicated, joined ` + ` (`6706-6721`) |
| The item's own date is month-first today | `shortDate` = `Nov 10` + `, 2026`; the rule is day-first (`10 Nov 2026`), and the trigger already reads `Tue, 10 Nov 2026`. **Parked — this lane keeps the row's spelling.** |
| Specs that touch the row: two CLICK `.slot-dd-item-label` | `clear-guards-cross-week.spec.ts:212`, `arr-slot-original-venue-sync.spec.ts:27,46,54` (also 65,98,121 — not exhaustive). `tests/confirm-guards.spec.ts` clicks `.slot-dd-item` itself (:156; :146 is a count probe), and the label class is not named in that file |
| Two specs READ the label's text | `tests/arrangement-conflict-autoselect.spec.ts:144` asserts `Week 8`, the venue and a `[-~]` time (survives); `tests/arrangement-mobile.spec.ts:641-654` finds the row by substring across `Week N` + label date + both times + venue (survives, as long as row 1 stays month-first) |
| Two specs pin the old behaviour (one must be rewritten for the fold, one for `@ B`) | `selection-summary-collapse.spec.ts` (fold exists, collapses) and `rearrangement-toolbar-tooltips.spec.ts:103-106` (`Week N · Mon D, YYYY`, `@ B`, one `.slot-dd-item-cohort`; only `@ B` breaks) |

## Rejected approaches and why

- **Keep the toggle but default it open.** The user said not foldable; a control that can hide
  the Submit button is the thing being removed.
- **Hide the toggle with CSS only.** The listener would stay and the button would still be a
  focus stop in the DOM unless removed properly; the honest change is to delete both.
- **A `<dl>` for the rows.** The rest of the page's small fact lists are plain spans
  (`.info-item`), and the tooltip pop-up renders a mini row list the same way. Two markup
  dialects on one card would be worse than a plain row stack.
- **One row per cohort.** Three rows of `👥` for a 3-cohort class would triple the item height;
  the codes read better together.
- **Referencing `icons.week` from the slot-row builder.** That object is declared at blade 3653
  *inside* `updateSelectionSummary()` (3571), so it is not in scope where the slot rows are built.
  The design batch must say how the four glyph strings reach the row builder (copy verbatim, or
  hoist to one page-level constant) instead of leaving the implementer to discover the scope wall.
  Either way nothing new joins the icon vocabulary.
- **Month-first date change.** Aligning the row with the trigger's day-first spelling is correct by the
  date rule but rewrites two specs' text matches for a reason outside the user's asks; parked instead.
- **Leaving the card's fold CSS in place.** `.sel-summary.is-collapsed .sel-summary-body` (blade 521)
  becomes dead once the button goes; the design batch decides whether it is deleted or kept as a
  dormant rule, and says so.

## Open questions

None. The one decision the brief left open (mixed cohorts) is answered above.
