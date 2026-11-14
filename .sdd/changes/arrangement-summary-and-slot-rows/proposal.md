# Proposal — arrangement: always-open Selection Summary, readable slot rows

> **Change:** `arrangement-summary-and-slot-rows`
> **Page:** `resources/views/livewire/replacement-arrangement.blade.php` (one file, plus tests and its changelog)
> **Created:** 2026-11-14 · **Batch:** 1 (proposal)

## 1. Why

Two small things on the Replacement Arrangement page cost the user real steps.

**a. The Selection Summary can hide itself.** The card carries a chevron button that folds its
body away. With slots selected and the card folded, the chosen slots, the totals and the
**Submit Request** button all disappear behind a chevron the user has to remember to press. The
user asked for it to be permanently open.

**b. One slot row tries to say everything at once.** Each row in the slot list is a single
wrapping sentence:

```
(P) Week 8 · Nov 10, 2026 · 1:00 PM - 2:00 PM @ B009 · 👥 RSD3     [Class Cancelled]
```

which breaks mid-phrase across two lines, repeats `@` as punctuation, and gives no anchor for the
eye when comparing three candidate slots. The user asked for it split into labelled rows with
icons: time, venue, cohort.

## 2. What changes

### 2.1 Selection Summary — no fold at all

- Remove the `#summaryToggle` button from the card header. The title stays.
- Remove its click listener. The body is never given `hidden` and the card never gets
  `is-collapsed`.
- The **other two** cards (Conflict Schedule, Class Details) keep their toggles exactly as they
  are. Only this card loses its control.
- Nothing else about the card changes: the same empty state, slot cards, totals row, Tip trigger
  and Submit button.

### 2.2 Slot list row — four readable rows

Each row becomes a small stack inside the existing `.slot-dd-item-label` wrapper:

| Row | Content | Icon |
|---|---|---|
| 1 | `(P) Week 8 · Nov 10, 2026` — **spelling unchanged**, plus the status badge on the right | week glyph (reused) |
| 2 | `1:00 PM ~ 2:00 PM` | clock glyph (reused) |
| 3 | `B010` | map-pin glyph (reused) |
| 4 | `RSD3 (2 cohorts)` — and **no cohort row at all** when the session carries none | users glyph (reused) |

- **Row 1 keeps today's month-first spelling** (`Nov 10, 2026`). Aligning it with the trigger's
  day-first `Tue, 10 Nov 2026` would be right by the date rule, but it would change what two
  existing specs match on (`rearrangement-toolbar-tooltips.spec.ts` regex,
  `arrangement-mobile.spec.ts` label search) for a reason the user did not ask for. It is
  **parked** as a separate item, noted at the end of this proposal.
- **Row 4 counts the cohorts underneath** (user's answer A): the distinct short codes joined
  ` + `, then `(N cohorts)` where N is how many cohorts the session actually carries —
  `DFT + DSF (2 cohorts)`, `DSF2 (2 cohorts)` when two cohorts shorten to the same word, and a
  bare `RSD3` when there is only one.
- The status badge keeps its place on the right and its `data-tip` reason.
- The row is still one click target, and `.slot-dd-item-label` / `.slot-dd-item-cohort` keep
  their names, so every spec and every internal caller that clicks or counts them keeps working.
- The hover pop-up (`#slotTooltip`) keeps its own single-line text: it is the detail view, and
  the row is now the summary.

## 3. Out of scope

- Conflict Schedule and Class Details toggles, rails and collapse behaviour.
- The slot trigger's own two-line text (it already reads well).
- The mobile sheet's slot list beyond inheriting the new row stack (its 44px floor stays).
- Any other page, any backend/DB change, any new dependency, any new icon vocabulary.
- The `#slotTooltip` hover pop-up's content and position.

## 4. Constraints

- **Token-only colours.** No hex/rgb; the row icons use `currentColor` like the existing set.
- **Reuse the page's own icon set** (`icons.week` / `icons.time` / `icons.venue` and the users
  glyph already used by `.slot-dd-item-cohort`). Do not draw new icons.
- **Two specs pin the old behaviour and must be rewritten honestly, not deleted:**
  `tests/selection-summary-collapse.spec.ts` (asserts the fold exists and collapses) and the label
  assertions in `tests/rearrangement-toolbar-tooltips.spec.ts` (asserts `Week N · Mon D, YYYY`,
  `@ B`, and one cohort tag). Their replacements assert the NEW truth: no toggle, body always
  visible, and the four labelled rows with the cohort count.
- **One spec reads the row by substring and must keep passing untouched:**
  `tests/arrangement-mobile.spec.ts` (its helper at 641-654 finds the row by `Week N`, the label date
  `Dec 24, 2026`, both times and the venue, all as substrings of the whitespace-collapsed
  `textContent`). The row stack keeps every one of those tokens, and row 1 keeps the month-first
  spelling, so no edit is needed there.
- **Four more spec files read or click the row** and must keep passing untouched:
  `clear-guards-cross-week.spec.ts:212` and `arr-slot-original-venue-sync.spec.ts:27,46,54` click
  `.slot-dd-item-label`; `arrangement-conflict-autoselect.spec.ts:144` reads its **text** and asserts
  `Week 8`, the venue and a `-`/`~` time; `confirm-guards.spec.ts` clicks the `.slot-dd-item` itself.
  The row keeps the class, the click target and all three facts — the layout around them changes.
- **Tree state at proposal time:** the Blade is **clean** (its last commit is `bd4a8e8`), so this
  lane can commit its own hunks normally. What is *not* clean is the spec folder: of the specs this lane touches,
  `selection-summary-collapse.spec.ts` is **untracked**; the other one it rewrites
  (`rearrangement-toolbar-tooltips.spec.ts`) and the must-keep `arrangement-mobile.spec.ts` are
  tracked and clean. Measured `git status tests/` at proposal time: **modified** =
  `top-zone-round2.spec.ts`, `venue-timetable.spec.ts`; **untracked** = `selection-summary-collapse.spec.ts`,
  `venue-selector-responsive.spec.ts`. The two modified files belong to other work and must not be
  swept into this lane's commit; the one untracked file this lane adds is its own spec.
- Changelog: `page-changelogs/replacement-arrangement-changelog.md`, newest entry at the top.

## 5. Risks

| Risk | Mitigation |
|---|---|
| Removing the toggle leaves a focus stop or a dead listener | remove the button and the listener; the rewritten spec asserts the button does not exist and the body is visible |
| The taller row pushes the panel over the viewport | measure the panel at 390 / 768 / 1360 with the longest realistic content and cap the panel's own scroll, as it does today |
| A spec that clicks `.slot-dd-item-label` breaks because the text changed | the wrapper class and its click handler stay; only its children change |
| The cohort count is wrong for a class with two cohorts of the same programme | the count is the length of the cohort list, not of the distinct codes (option A) |
| The row stack changes what a spec's text match finds | row 1 keeps month-first; every token an existing substring match needs is still present; the one assertion that depended on `@` (the venue inline) is rewritten to the venue row |

## 6. Done looks like

- The Summary card has no chevron; its body, slots, totals, Tip and Submit are always visible at
  every width, both themes.
- Each slot row shows four labelled rows with icons; a one-cohort class reads `RSD3`, a two-cohort
  class reads `RSD3 (2 cohorts)`, two different codes read `DFT + DSF (2 cohorts)`.
- Selecting a slot still updates the Summary immediately; nothing about the flow changes.
- The two rewritten specs assert the new truth; the three click-based specs pass untouched; no
  other page's geometry moves.

## 7. Parked (not this lane)

The slot row prints `Nov 10, 2026` while the trigger directly above it prints `Tue, 10 Nov 2026`.
The date rule says day-first, so the row is the one that drifts. Aligning it changes two specs'
text matches and is not part of the user's two asks — recorded here so it is not lost, and it
waits for its own word.
