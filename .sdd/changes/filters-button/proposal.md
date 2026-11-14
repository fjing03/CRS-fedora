# Proposal — `filters-button`

**Status:** draft for review (Batch 1). Nothing frozen yet, no code written.
**Source brief:** `prompts/pi-prompt-filters-button.md` (explore + propose only).
**Facts:** `explore-brief.md` in this folder.
**Task list:** `prompts/for-ai-to-read/my to-do list.md` §7.

---

## 1. Why

The three list pages put every filter on the page in a full-width bar, then repeat what is applied
underneath in a chips strip. On a phone that is up to **half the first screen before the first row**
(measured: My Replacements 265px bar + 120px chips, ending 519px down an 844px screen; Request
Approval 281 + 126 = 541px). On the student page it is a quarter of the screen, and its chips row is
the only signal that a filter is on at all.

The prototype already settled the shape — one labelled **Filters** door, everything behind it in a
panel (popover on a laptop, bottom sheet on a phone), the chips and the row count left on the page —
and this is the **third** page to need it. So the work is two things at once: put the filters behind
the door, and make the door a shared piece instead of a third copy.

## 2. In scope

1. A **Filters** button (funnel glyph + the word) at the **left of the chips row**, at every width,
   opening a panel that holds that page's own filter controls. It is the only way in — no funnel is
   added to the page header (there is none on the live pages today; the prototype removed its own).
2. **Nothing filter-shaped on the page until the panel opens**, search box included.
3. Panel = **popover under the button at ≥769px**, **bottom sheet at ≤768px**; dim, page scroll locked,
   closes on ×, Escape and a tap outside; focus returns to the button. The trigger stays at full colour
   above the dim.
4. Panel head: **Filters** title, then **Reset Filters** (circular-arrow, grey) beside the × **on the
   two staff pages only** — the student page has no Reset (decision 1), so its head is title + close.
5. Each control keeps a **label above it** — Search, Status, Original slot week, Requested slot week,
   Hide COMPLETED; the switch gets a real `<label for>` and shares the status row on its right.
6. Look from `theme.css` tokens: grey fields (`--color-surface-variant`), tinted body (`--color-bg`),
   labels and week arrows in `--color-primary`.
7. Chips stay on the page and say what is applied, **without** the "Active Filters:" prefix (the button
   names the row); the completed chip reads **Completed hidden**, the switch **Hide COMPLETED**.
8. Week "no filter" option reads **All Weeks**.
9. Row count becomes a **pill at the right of the chips row**, **visible at every width**, on the two
   pages that have one ("Showing 5 of 5 classes" / "Showing 2 of 16 results"); the student page has no
   count and gets no pill. The old M15 rule that hid `#resultCount` below 1500px
   (`replacement-home.blade.php:106–110`) is **retired** — decision 1b, answered: follow the prototype.
10. **One shared home** for the frame, the row and the behaviour — not a third hand-rolled copy.
11. **A second week dropdown, as the prototype has it — on all three pages.** My Replacements gains
    **Requested slot week** (it only filters by the Original week today); Request Approval gains
    **Original slot week** (it only filters by the Requested week); Class Replacements gains
    **Requested slot week** too. Adding it is real behaviour, not layout — for each page: a second
    predicate in the filter function, a matching chip with its own removal path, a reset that clears
    both, a saved view that remembers both, and its own tests. The payload carries no requested-week
    field, so that week is **derived from `replacementDate`** — the same way Request Approval's single
    dropdown already works. No backend change.

## 3. Out of scope

- *(The second week dropdown is no longer out of scope: the user's "follow prototype" moved it in. It is
  listed under §2 item 11, on all three pages.)*
- The summary cards, page headings, guide dialogs (brief's rule).
- Print (`timetable-print-v2` owns it). This lane only must not make print worse.
- The nine `ui-design-templates/*` reference pages.
- Any **existing** filter's meaning, values or default: none is dropped, renamed or given a new value.
  (The second week dropdown is a new control — §2 item 11 — not an exception to this line.)

## 4. Options

### Option A — shared frame partial + JS relocate + labels in Blade ← **recommended**

- `partials/ui-filter-panel.blade.php` (new): emits the overlay + panel frame (head with title, Reset
  slot, ×, body) **and** the chips row (`[Filters button] [#filterChips] [count slot]`). Included by the
  three pages, right where their `.toolbar` sits today.
- Each page keeps its own `.toolbar` markup. Per page the edits are: a `<label for>` above search, above
  status, and inside a wrapper around the **existing** week include; the switch rebuilt into its labelled
  wrapper (status + switch share a line via CSS, not markup); `#resultCount` moved into the chips row;
  the panel include (plus, per decision 4, the second week field). That is seven touch points on My
  Replacements and Request Approval and five on the student page — small each, but not "3".
- One JS module (`FiltersPanel`) moves the page's `.toolbar` into the panel body at load, opens/closes,
  traps focus, locks scroll, and returns focus — modelled on `SummarySheet`, minus the media-query
  branching (the panel exists at every width; only its **shape** changes at 768).
- **Why it wins:** one copy of the frame and behaviour, controls stay where the pages' own JS expects
  them (ids untouched: `#resetFilters` listeners, `#filterChips` repaint, `#resultCount` writes), and the
  labels are real markup rather than DOM invented at runtime.

### Option B — prototype-faithful JS decoration

Keep the pages' markup exactly as it is and let the module build the labels, the status row, the chips
row and the Reset move at runtime (that is literally what the prototype does).
- **Pros:** smallest diff to the three blades.
- **Cons:** the panel's labels and layout exist only after JS runs; a page that fails to boot shows an
  unlabelled, unstyled bar (not a blank panel — worse to debug). It also means the one shared piece owns
  more DOM than the pages do, which the brief's "do not let one page's version reach another" argues
  against.

### Option C — native `<dialog>` for the panel

- **Pros:** focus containment, Esc and `inert` background free.
- **Cons:** `.showModal()` uses the **top layer**, which beats every z-index (measured last lane): the
  tooltip engine (9999) would paint **under** the panel, so every `data-tip` inside it would vanish —
  and this panel is full of `data-tip` controls. Rejected.

**Recommendation: A.**

## 5. Design of record (Option A)

### 5.1 Breakpoint and shape
One breakpoint, **768px**, the house one. Above it: a popover anchored under the Filters button, clamped
8px inside the viewport (the venue picker already does this). Below it: bottom sheet, same frame as the
summary sheet (`max-height: 88dvh`, head sticky, body scrolls, dim, `overscroll-behavior: contain`).

### 5.2 The shared piece

| file | what |
|---|---|
| `resources/views/partials/ui-filter-panel.blade.php` (new) | overlay + panel frame + head (title / Reset slot / ×) + body container + the chips row with the Filters button and a count slot. Two small Blade vars: `$panelId` (unused by one page each) and nothing else — the controls are not its business. |
| `public/js/ui-common.js` | `FiltersPanel` module: `init()` (per page: locate `.toolbar` + panel, relocate, wire button/×/veil/Esc, restore), `open()`, `close()`, focus trap with suspension while a dialog above is open, `body.filters-panel-open` lock. |
| `public/css/theme.css` | `.filters-btn`, `.filters-panel*`, `.filter-field` (label above control), the status + switch line, the chips row (`.filters-row`), the count pill, the scoped week-arrow/label colours, `#filterChips` losing its `:empty{display:none}`, and the print rule that hides the panel + button. |
| the three blades | `<label for>` per control; a labelled wrapper `<div class="filter-field"><label for="weekFilter">…</label> @include('partials.ui-week-nav', …)</div>` around the **existing** week include; the switch restructured into its labelled wrapper; `#resultCount` moved into the chips row; the panel include. |

**`partials/ui-week-nav.blade.php` is NOT edited.** It is shared byte-identically by five other live
pages (both timetables, cohort, venue, arrangement); this lane wraps it per page. `<label for="weekFilter">`
works even though the `<select>` is emitted inside the partial, and the arrows travel with the moved
`.toolbar` automatically. Their colour inside the panel comes from a **scoped** rule
(`.filters-panel .week-arrow { color: var(--color-primary) }`), never a global change.

### 5.3 Markup + ARIA

```blade
<button type="button" class="filters-btn" id="filtersButton"
        aria-haspopup="dialog" aria-controls="filtersPanel" aria-expanded="false">
    {{-- funnel glyph, 16px, aria-hidden --}} <span>Filters</span>
</button>
```
Panel: `role="dialog" aria-modal="true" aria-labelledby="filtersPanelTitle"`, head `<h2 id="filtersPanelTitle">Filters</h2>`,
Reset `button` with the circular-arrow glyph, close `button aria-label="Close Filters"`.
Labels are real `<label for="searchInput">Search</label>` etc. **The controls that gain a visible label
lose their `aria-label`**: the accessible-name algorithm gives `aria-label` priority over a `<label>`, so
keeping both would announce the old string ("Request Status" / "Filter by Request Status") and quietly
ignore the visible one. Controls that keep no visible label (none, after this lane) keep their
`aria-label`s.

### 5.4 The chips row
`#filterChips` stays the **same node** the pages already repaint (so `renderFilterChips()` keeps
working untouched), just no longer `:empty{display:none}` on its own — the row is always visible because
the button lives in it. Edits: drop the `Active Filters:` prefix in the three builders, rename the
completed chip to `Completed hidden`, and move `#resultCount` beside it.

### 5.5 Reset semantics — two pages, each restoring its own defaults
Reset is a **staff-only** control (decision 1: the student page deliberately gets none — §7.1). Where it
exists it restores that page's **own defaults**, not an empty state (verified in the code):
- My Replacements: `resetAllFilters()` — search empty, status `all`, **both** weeks → All Weeks,
  exclusion **on**.
- Request Approval: `resetFilters()` (`:1256–1263`) — search empty, status **`Pending`**, **both** weeks
  → All Weeks, rows-per-page back to 10, exclusion **on**. ("Everything off" is a *different* function,
  `clearAllFilters()`, behind the chips' Clear-all — the 2026-10-30 ruling. Reset must not become it.)
- Class Replacements: **no Reset**, by decision. Its chips' Clear-all stays as it is.

The shared frame therefore renders the Reset slot **conditionally**: pages that pass a reset handler get
the button; a page that does not gets the title and the close only. The frame must not standardise the
two staff functions either.

### 5.6 `All Weeks` — decided: shorten, and the 10 November ruling gives way
Three `allLabel` strings (`replacement-home:880`, `request-approval:1248`, `replacement-history:690`).
No logic. This **deliberately reverses** the 2026-11-10 wording ruling that changed the option to say
"SLOT" (recorded at `tests/week-filter-preserved.spec.ts:140`): with a visible "Original slot week" /
"Requested slot week" label above it, the option no longer needs to repeat the kind of week.
**Three pinned strings must be amended in the same commit** (they are evidence of the old wording, not
of anything this lane must preserve):
- `tests/status-tooltips-filter-chips.spec.ts:380` — `'All Requested Slot Weeks'`
- `tests/week-filter-preserved.spec.ts:141` — `'All Original Slot Weeks'`
- `tests/Feature/StudentReplacementHistoryParityTest.php` — the whole file, not one line: `:143` pins the
  `allLabel` source string, `:144` pins the `Original Slot Week:` chip text, `:146` asserts the student
  source does **not** contain `Requested Slot Week:` (which this lane makes false), and `:148` pins the
  exact single-week filter predicate. Its docblock (`:15`) and test name also describe the old
  single-week premise. Amending only `:143` would leave a failing, wrongly-scoped test behind.

### 5.7 Locks, layering, print
- Own scroll lock (`filters-panel-open`); never reuse `summary-locked` (shared, unrefcounted).
- `z-index: 1200` for the overlay — above the bell (998/999), below tooltips (9999) and below a dialog
  that must sit on top (the ⓘ at 1400 while its sheet is open — they cannot be open together anyway).
- **One overlay at a time, on the same page too.** Opening the filters closes the bell panel **and** the
  summary sheet (all three pages include `partials/ui-summary-bar`, whose sheet is also `z-index: 1200`);
  opening the summary sheet or the bell closes the filters. Without that rule two 1200 overlays on one
  page would decide the winner by DOM order.
- `@media print` hides the overlay and the button; the page prints without filters, as it effectively
  does today.

### 5.8 Files to touch

`partials/ui-filter-panel.blade.php` (new), `public/js/ui-common.js`, `public/css/theme.css`,
`livewire/replacement-home.blade.php`, `livewire/request-approval.blade.php`,
`livewire/replacement-history.blade.php`, plus a 3-line entry in the three page changelogs.

No new dependency, no migration, no DB work, no backend change.

## 6. Test plan

**Manual, every page, at 320 / 390 / 768 / 769 / 1024 / 1440, light + dark**
1. The page shows a Filters button left of the chips row and **no** filter control anywhere on the page.
2. Button opens: ≥769 a popover under it, ≤768 a bottom sheet; dim + scroll lock; trigger stays bright.
3. Every control has its label above it; the switch sits right of Status on one line; fields are grey,
   body tinted.
4. Change a filter → chips update and remove correctly, including the **new second week** chip; the
   count pill shows and matches **at every width** (320 → 1440). On the two staff pages, Reset returns
   that page to its own default (My Replacements: exclusion on, both weeks All Weeks; Request Approval:
   status **Pending**, both weeks All Weeks, exclusion on). The student page has **no Reset** — its
   Clear-all chip is the only bulk action and must keep working.
5. Close by ×, Escape and a tap outside — each returns focus to the button and unlocks the page.
6. Keyboard: Tab reaches the button, panel traps Tab/Shift+Tab, Esc closes; the bell panel cannot be
   opened underneath it.
7. Tooltips inside the panel show above it and are not clipped at its top edge.
8. Print preview: no panel, no button, page unchanged otherwise.

**Automated:** a new spec pinning (a) no `.toolbar` control is visible before opening, (b) the panel
opens with the right shape at 768 vs 769, (c) `aria-expanded` + focus enter/return, (d) chips repaint
after a filter change with the panel closed, (e) Escape/veil close and unlock, (f) the count pill text on
the two pages that have one, (g) `#filterChips` keeps the same node identity across an open/close,
(h) **the new second week filter, per page**: selecting it narrows the list, its chip appears and removes
only itself, the page's bulk action clears **both** weeks (Reset on the two staff pages, Clear-all on the
student page — it has no Reset), and the saved view restores both after a reload.
Existing specs that touch these pages' toolbars (`pages-parity`, `ui-regression` §7, `nav-identity`,
`venue-*`) must stay green — **except one that this lane amends on purpose**:
three files pin strings this lane changes on purpose and move with it:
- `tests/status-tooltips-filter-chips.spec.ts` — `.filter-chips-label` = `Active Filters:` (`:416`),
  chips reading `Exclude COMPLETED Status` (`:171/:206/:422`), and the week option
  `'All Requested Slot Weeks'` (`:380`)
- `tests/week-filter-preserved.spec.ts:141` — `'All Original Slot Weeks'`
- `tests/Feature/StudentReplacementHistoryParityTest.php` — the whole file, not one line: `:143` pins the
  `allLabel` source string, `:144` pins the `Original Slot Week:` chip text, `:146` asserts the student
  source does **not** contain `Requested Slot Week:` (which this lane makes false), and `:148` pins the
  exact single-week filter predicate. Its docblock (`:15`) and test name also describe the old
  single-week premise. Amending only `:143` would leave a failing, wrongly-scoped test behind.

Leaving any of them would either fail the run or force the implementer to abandon the brief's rename.
Two comments in those files record the old wording as a ruling (`week-filter-preserved.spec.ts:137–140`,
`status-tooltips-filter-chips.spec.ts:115`) and are updated with the assertions, so no file testifies to a
ruling that no longer stands.

## 7. Decisions needed before apply

1. **Student reset — ANSWERED (user, 2026-11-14): NO.** The student page has no Reset today and gets
   none: its panel head carries the title and the close only. Brief item 4 ("Reset Filters in the head")
   therefore applies to the **two staff pages only**; the shared frame must make Reset optional, not
   mandatory.

1b. **My Replacements' count pill — ANSWERED (user, 2026-11-14): follow the prototype, option (a).**
    The M15 rule (`replacement-home.blade.php:106–110`) is **retired**: `#resultCount` is a pill in the
    chips row and is visible at every width. Accepted trade: on a screen wider than 1500px the
    pagination bar reports the same number again.
2. **Magnifier glyph — carried into `design.md` with the stated answer** (drop it inside the panel: the
   visible "Search" label does the work; the input is otherwise identical). Flagged rather than decided
   because it changes one page's look, which the brief reserves for you.
3. **Sort hint on Request Approval — carried into `design.md` with the stated answer** (it explains the
   table, not the filters, so it stays on the page above the chips row). Same flag as 2.
4. **Second week dropdown — ANSWERED (user, 2026-11-14): FOLLOW THE PROTOTYPE, so it is IN, on all
   three pages.** My Replacements gains *Requested slot week*, Request Approval gains *Original slot
   week*, and Class Replacements gains *Requested slot week*. The lane is therefore no longer
   layout-only: it carries one new filter per page (predicate + chip + reset + remembered state), with
   the requested week derived from the replacement date. No backend change.

   Consequence for the panel: every page now shows **two labelled week fields**, so the panel's height
   and its field order (Search · Status + Hide COMPLETED · Original slot week · Requested slot week)
   must be designed for that, not for the one-field bar that exists today.

## 8. Risks

- The panel is a **move**, so a page must keep its ids: `#resetFilters` listeners are bound by id at
  load, `#filterChips` is rewritten by the page's own code, `#resultCount` is written by it. A move
  keeps all three; any "tidy-up" that renames them breaks a page silently.
- Desktop popover positioning needs the same clamp-and-resize discipline as the venue picker (measured
  lesson: a one-shot fit runs before the panel grows).
- Three pages, three Reset meanings — the shared frame must not standardise them.
