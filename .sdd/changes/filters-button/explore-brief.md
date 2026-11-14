# Explore brief — `filters-button`

Facts gathered on 2026-11-14 against the live pages at `http://127.0.0.1:8030` (testing DB), before
any proposal was written. Measurements are real browser reads of the current code.

---

## 1. The three pages, measured

At 390 × 844, scroll at top:

| page | toolbar | chips row | filters end at | share of the phone screen |
|---|---|---|---|---|
| My Replacements | top 134, **265px tall** | 399–519 (**120px**) | **519px** | ~46% |
| Request Approval | top 134, **281px** | 415–541 (**126px**) | **541px** | ~48% |
| Class Replacements (student) | top 161, **94px** | 255–375 (**120px**) | **375px** | ~25% |

At 1440 the same blocks are 65 / 93 / 62px plus a 34px chips row.

So the brief's "fills most of the first screen" is right for the two staff pages (about half of an
844px screen before the first row) and **overstated for the student page** (a quarter). The student
page also only owns two filters, which is why it is the cheapest of the three and the one where the
"one door" matters most (its chips row is currently the only hint that a filter is even on).

## 2. What each page actually has

| | My Replacements | Request Approval | Class Replacements |
|---|---|---|---|
| search | `#searchInput` inside `.search-wrapper` (with magnifier) | `#searchInput` bare | **none** |
| status | `#statusFilter`, values `all/none/pending/approved/rejected/completed` | `#statusFilter`, values `all/Pending/Approved/Rejected/Completed`, defaults **Pending** | **none** |
| week | `#weekFilter` = **Original** slot week | `#weekFilter` = **Requested** replacement week | `#weekFilter` = **Original** slot week |
| completed switch | `#hideCompleted` (checked) | `#hideCompletedToggle` (checked, `onchange`) | `#hideCompleted` (checked) |
| reset | `#resetFilters` → `resetAllFilters()` = page defaults (exclusion **ON**) | `#resetFilters` → `resetFilters()` = **everything off** (status → all) | **none** |
| count | `#resultCount` — "Showing 5 of 5 classes" | `#resultCount` — "Showing 2 of 16 results" | **none** |
| chips builder | `renderFilterChips()` | `renderFilterChips()` | `renderChips()` (delegated `data-remove`) |
| chips text | `Active Filters:` label + `Status: …`, `Original Slot Week: …`, `Search: "…"`, `Exclude COMPLETED Status`, `Clear all` | same shape, `Requested Slot Week: …` | `Original Slot Week: …`, `Exclude COMPLETED Status`, `Clear all` |

Three different ids for the same switch (`hideCompleted` vs `hideCompletedToggle`), three different
chip functions with **two different removal mechanisms** (inline `onclick` on the staff pages, a
delegated `data-remove` handler on the student page), and a count string that differs ("classes" /
"results" / nothing). Any shared piece must therefore **frame** these controls, not own them.

## 3. Nobody re-renders the page — but the JS owns the DOM

- None of the three blades carries a single `wire:` binding; the tables, chips and counts are painted
  by each page's own JS from `MockData`. There is **no Livewire round-trip to survive**, unlike the
  pages that use `wire:ignore`.
- But the DOM is not static either: `renderFilterChips()` / `renderChips()` rewrite `#filterChips`
  after every filter change, and `#resultCount` is rewritten with it. A panel that *moves* the
  controls must leave those ids where the page's own code looks for them — it does, because a move
  keeps the nodes and their ids.

## 4. What already exists to reuse

- **This lane's neighbour** (`summary-cards-button`, archived 2026-11-14) built exactly this shape for
  the summary cards: an overlay div at `z-index: 1200`, a bottom sheet at ≤768, a dim, a dedicated
  scroll-lock class (`summary-sheet-open`), a capture-phase Esc that yields to a dialog opened above it,
  a Tab trap with suspension, and `beforeprint`/`afterprint`. `SummarySheet` in `ui-common.js` is the
  working reference — **but it relocates one node and knows nothing about controls or labels.**
- **The prototype** (`../ui-prototype/my-replacements.html`, `request-approval.html`) is the settled
  look: `.cl-sheet-btn` at every width, `makeSheet('clFilterSheet','Filters','clFilterBody')`,
  `.toolbar` **moved** into the panel body at every width, a new `.cl-status-row` holding
  `[Filters button] [chips] [count]`, Reset moved into the panel head with a circular-arrow icon,
  `.cl-field` labels injected at runtime, status + Hide COMPLETED sharing one line, and the week
  "none" option rewritten to `All Weeks`.
- **`populateWeekSelect(selectId, cfg)`** (`ui-common.js:4122`) already accepts
  `cfg.allLabel` — so the "All Weeks" wording is a **one-string change per page** (home `:880`,
  approval `:1248`, student `:690` all pass `All Original/Requested Slot Weeks` today).
- **Overlay numbers today:** `.notif-overlay` 998, `.notif-panel` 999, `.modal-overlay` 999 (1000 at
  ≤600), this lane's summary sheet 1200, tooltip engine 9999. A filter panel at 1200 sits above the
  bell; the bell's overlay closing on outside click keeps the two from fighting.

## 5. What breaks if the bar moves into a panel

| risk | detail |
|---|---|
| **Reset + Clear all wiring** | home/approval attach `#resetFilters` listeners **by id** at load (`replacement-home:1419`); the student page has no reset at all. A move keeps the ids, but Reset's *meaning* differs per page and the student page would need one written (week → All, switch → on, forget the saved view). |
| **Chips** | three builders, two removal mechanisms. Dropping the `Active Filters:` label and renaming the completed chip (`Completed hidden`) is three small string edits, not a shared function — but if the shared frame rewrote `#filterChips` the pages' own repaint would undo it on the next filter change. |
| **Keyboard order** | today the controls sit between header and table. Behind a door they leave the tab order; the button must carry `aria-expanded`/`aria-controls`, the panel `role="dialog"` + a trap, and focus must return to the button — the same rules the summary sheet already implements. |
| **Notification panel** | bell panel z 999, filter panel 1200: the filter panel is on top, so an open bell would be covered rather than fought. Worth closing the bell when the filter panel opens. |
| **`data-tip` inside the panel** | the tooltip engine is document-level at 9999 and positions from the anchor's rect, so it stays above the panel — but a control near the panel's top edge needs the tooltip to flip below (the engine already does that). |
| **Print** | there is no print rule for `.toolbar` or `#filterChips` today; with the bar behind a door a printout simply loses the controls. Print is a later lane (`timetable-print-v2`), so the requirement here is only "do not make print worse than it is". |
| **Sort hint** | Request Approval prints a `.sort-hint` line ("Click column headers to sort…") between the toolbar and the chips. It is not a filter; it explains the table and should stay on the page, not enter the panel. |
| **Search styling** | only My Replacements wraps its input in `.search-wrapper` with a magnifier; inside a panel with a visible "Search" label the glyph is decoration. Dropping it is a look change on one page (needs the user's word per the brief's rule). |

## 6. Two things the prototype added — priced

1. **`All Weeks` wording.** One string per page (`cfg.allLabel`). Trivial, no logic. **In.**
2. **A second week dropdown** (My Replacements gains Requested; Request Approval gains Original; the
   student page would gain Requested). This is **not** a layout change: it needs
   - a second filter predicate in each page's filter function AND in `saveFilters`/`restoreFilters`,
   - a new chip ("Requested Slot Week: …") wired to its own removal path,
   - a reset that clears both weeks, and a saved-view key that can carry two,
   - the payload already has both weeks (`originalWeek` / `requestedWeek` per row) so no backend work.
   That is a small feature, not a panel. **Out of this lane; recommend its own.** The prototype added it
   because the demo wanted both; nothing in the brief's 10 asks *requires* it.

## 7. What is genuinely identical across the three (what one shared piece would be)

Identical: the door (funnel + word, every width), the panel frame (head with title + Reset + ×, tinted
body, popover above 768 / bottom sheet below), the dim + scroll lock + Esc/veil/× + focus return, the
chips **row** (button left, chips middle, count pill right), the "field = label above control" wrapper,
and the status+switch on one line. Different: the controls themselves, the chips strings, the count
text, and the reset semantics.

So the shared piece is a **frame + row + wrapping rules**, and each page keeps its own controls. That is
one Blade partial (frame + row), one CSS block, one JS module — not a shared toolbar.

## 8. Open questions carried into propose

- Does the student page get a Reset in the panel head (it has none today)? It is the only way item 4
  can be true on all three pages.
- Does the magnifier stay in My Replacements' Search field once the label says "Search"?
- `#resultCount` currently lives in `.toolbar-right`; the brief wants it as a pill at the right of the
  chips row. Moving that node is a per-page markup change (3 lines), not shared logic.
- Chips: keep the existing `#filterChips` node exactly as-is (the pages repaint it) and only drop the
  `Active Filters:` prefix and rename the completed chip — or rebuild the row around it?

## 9. Correction (2026-11-14, caught in proposal review)

The earlier §6 note said "the payload already has both weeks (`originalWeek` / `requestedWeek`)". That
is **wrong** — no such fields exist. Each row carries the original occurrence `week` and a
`replacementDate`; the requested week is **derived** from that date in the page code (Request Approval's
single week dropdown already filters that way). The conclusion is unchanged — a second week dropdown is
still its own lane, and still needs no backend work — but the reason is "derive the requested week from
`replacementDate`", not "read a field that is already there".

Two more facts confirmed while reviewing:

- **Request Approval's Reset is page defaults, not an empty state.** `resetFilters()`
  (`:1256–1263`) sets status back to **Pending** and the exclusion back **on**; "everything off" is a
  different function (`clearAllFilters()`) behind the chips. My Replacements' Reset is the same shape.
  The student page has no Reset at all.
- **My Replacements hides `#resultCount` below 1500px** (M15, `:106–110`: "the pagination bar still
  reports it"). A count pill in the chips row would inherit that hide unless the rule is retargeted or
  retired — raised as a decision, not assumed.
