# Design — arrangement: always-open Summary, readable slot rows

> **Change:** `arrangement-summary-and-slot-rows` · **Batch 2 (design)**
> **Against:** frozen `proposal.md` (rounds 1–5) + `explore-brief.md`
> **File:** `resources/views/livewire/replacement-arrangement.blade.php` (one page, one file)

## 1. Shape of the change

Two independent edits in one page, no shared file touched:

- **D1 — delete the fold.** Three removals (markup, listener, one dead CSS rule) and nothing else.
- **D2 — restructure the slot row.** One template string becomes a five-line stack; one CSS block
  gains the row rules; the page's icon glyphs get one home so both users read the same strings.

Neither touches `theme.css`, `ui-common.js`, any partial, or the backend.

## 2. D1 — Selection Summary has no fold

### 2.1 What is removed

| Where | What | Why |
|---|---|---|
| blade 2596–2607 | the comment block **and** the `#summaryToggle` button | the control itself |
| blade 7520–7531 | the `#summaryToggle` `click` listener | a listener without its button is dead code and a future trap |
| blade 521 | `.sel-summary.is-collapsed .sel-summary-body { display: none; }` | nothing can add `is-collapsed` to this card any more |

The header keeps `.sel-summary-header` with its title, so the card still reads as a section with a
border under the title. `#summaryBody` stays as it is, never `hidden`.

**Check before deleting the CSS:** `is-collapsed` is also used by `#subjectInfo` (Conflict
Schedule, blade 2025/5595/7507) — that class stays. Only the `.sel-summary`-scoped rule goes.

### 2.2 What must NOT change

- `#conflictCollapse` (2468, listener 7502) and `#detailsToggle` (2522) keep their toggles.
- The `#summaryGrid`, `#summaryEmpty`, totals row, `#summaryTip` and the Submit button are untouched.
- The `.ra-card-toggle` family rules stay in place (two cards still use them).

### 2.3 Behaviour after

The card is always expanded at every width, both themes. Selecting a slot still fills the grid and
the totals; nothing about the flow changes. The existing spec that pinned the fold is **rewritten**
(§6), not deleted.

## 3. D2 — the slot row becomes four labelled rows

### 3.1 Markup (built in the existing template string, blade 6733–6736)

```html
<span class="slot-dd-item-label">
  <span class="slot-dd-row slot-dd-row--head"><svg …week…></svg>(P) Week 8 · Nov 10, 2026</span>
  <span class="slot-dd-row"><svg …clock…></svg>1:00 PM ~ 3:00 PM</span>
  <span class="slot-dd-row"><svg …pin…></svg>B010</span>
  <span class="slot-dd-row slot-dd-row--cohort"><svg …users…></svg>RSD3 (2 cohorts)</span>
  <!-- the cohort row is ABSENT when the session carries no cohorts -->
</span>
<span class="slot-dd-item-badge …">Class Cancelled</span>
```

- The **time separator becomes `~`** in the row. The row is prose, and the repo's date/time rule
  says the tilde is the joiner (the `-` in today's label is the drift, and the trigger above already
  uses `~`). Existing substring-based specs look for `1:00 PM` and `3:00 PM` separately, so both
  survive.
- **Row 1 keeps the month-first spelling** (`Nov 10, 2026`) — parked decision, see proposal §7.
- The status badge stays the last child of `.slot-dd-item`, right-aligned.
- `.slot-dd-item-label` and `.slot-dd-item-cohort` keep their names **and** the cohort span stays
  inside the label, so every existing selector still resolves.
- The click listener, `data-index`, the hover pop-up (`#slotTooltip` + `fullLabel`), the selected
  state and `closeSlotPanel()` are untouched.

### 3.2 The cohort count (user answer A)

The count is the **raw list length**, i.e. `sess.cohorts.length` **before** the first-token de-dupe:

```js
const rawCohorts = (sess && Array.isArray(sess.cohorts)) ? sess.cohorts : [];
const cohortCount = rawCohorts.length;            // A: cohorts underneath, not distinct codes
const cohortCodes = /* first tokens, deduped, joined ' + ' — today's cohortNames */;
const cohortText = cohortCodes.length
    ? cohortCodes.join(' + ') + (cohortCount > 1 ? ` (${cohortCount} cohorts)` : '')
    : '';
```

- `DFT + DSF (2 cohorts)` — two different codes, two cohorts.
- `DSF2 (2 cohorts)` — both cohorts shorten to the same word; the count still tells the truth.
- `RSD3` — one cohort, no bracket.
- `''` — no cohort row at all.

`cohortNames` (the de-duped code list) stays as it is; only the count is added.

### 3.3 The glyphs — one home

Today the four glyph strings live in `icons` **inside** `updateSelectionSummary()` (blade 3653),
which is not in scope where the row is built. Design decision: **hoist them** to one page-level
constant declared before both users, and add the users glyph there:

```js
const RA_ICONS = {
    venue: '<svg …pin…>', week: '<svg …calendar…>', time: '<svg …clock…>',
    duration: '<svg …hourglass…>', cohort: '<svg …users…>'
};
```

- `updateSelectionSummary()` drops its local `icons` object and reads `RA_ICONS`; its rendered
  output must stay **byte-identical** (same four glyphs, same order, same wrappers).
- The slot row uses `RA_ICONS.week` / `.time` / `.venue` / `.cohort` — all 13px, `currentColor`,
  `aria-hidden="true"`, exactly as the page already draws them.
- No new glyph is invented; the users glyph is the one already inline at 6722.

### 3.4 CSS

Inside the page's own `<style>` (the block that starts at 313), replacing the `.slot-dd-item-label`
rule (331–335):

```css
.slot-dd-item { align-items: flex-start; }            /* badge lines up with row 1 */
.slot-dd-item-label { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.slot-dd-row { display: flex; align-items: center; gap: 6px; font-size: 12.5px;
               color: var(--color-on-surface-variant); }
.slot-dd-row--head { font-size: 13px; font-weight: 600; color: var(--color-on-surface); }
.slot-dd-row svg { flex: 0 0 auto; opacity: 0.9; }
.slot-dd-row--cohort { /* the existing .slot-dd-item-cohort look: muted, nowrap */ }
```

- Tokens only; no new colour, no new radius.
- `.slot-dd-item` keeps `padding: 10px 14px` and its 44px mobile floor
  (`body.arrangement-mobile-mode … .slot-dd-item { min-height: 44px }`, 1628-1631) — the stack is
  taller than that floor, so nothing shrinks.
- The panel's own `max-height`/scroll (`.slot-dd-panel`) is unchanged; the taller rows simply scroll.

### 3.5 Hover pop-up

`fullLabel` (6724) stays exactly as it is: one line, `@` venue, `~` time. The row is the summary;
the pop-up is the detail. No change to `#slotTooltip`, its positioning or its `mouseenter` logic.

## 4. Where the change lands (all in one file)

| Region | Line (proposal time) | Edit |
|---|---|---|
| CSS: `.slot-dd-item` / `.slot-dd-item-label` | 313–355 | add `align-items: flex-start`, replace the label rule, add the four row rules |
| CSS: `.sel-summary.is-collapsed …` | 521 | delete |
| Markup: `#summaryToggle` | 2596–2607 | delete button + comment |
| JS: `icons` → `RA_ICONS` | 3653–3658 | hoist, add `cohort`, re-point `updateSelectionSummary()` |
| JS: `cohortHtml` | 6721–6723 | rebuild as the cohort row with the count |
| JS: `item.innerHTML` | 6733–6736 | the four-row stack |
| JS: `#summaryToggle` listener | 7520–7531 | delete |

## 5. Rejected alternatives

| Option | Why rejected |
|---|---|
| Hide the toggle with CSS, keep the listener | leaves a focus stop and dead code; the honest removal is three deletions |
| Keep `.sel-summary.is-collapsed` CSS "just in case" | an unreachable rule invites a future re-introduction of the bug the user just removed |
| Duplicate the three glyph strings inside the row builder | a second copy of the same SVG is exactly the drift the promote-on-duplication rule exists to stop |
| One `slot-dd-row` per cohort | triples the row height for multi-cohort classes |
| Reuse `cohortNames.length` for the count | wrong for two cohorts that shorten to the same code — the case the user asked about |
| Swap the time joiner to `-` to match the old label | `~` is the repo standard and the trigger already uses it |

## 6. Tests

| Spec | Action |
|---|---|
| `tests/selection-summary-collapse.spec.ts` (untracked) | **rewritten**: `#summaryToggle` does not exist; `#summaryBody` is visible; the card never gains `is-collapsed`; after picking a slot the grid and the Submit button are visible; the two other cards still expose their toggles (proving the removal was scoped) |
| `tests/rearrangement-toolbar-tooltips.spec.ts` (tracked) | **one assertion rewritten**: `@ B` → the venue as its own row. The `Week N · Mon D, YYYY` regex, the single `.slot-dd-item-cohort` and the star-absence check stay |
| `tests/arrangement-mobile.spec.ts` (tracked, clean) | **untouched** — its helper substring-matches `Week N`, the month-first label date, both times and the venue; all still present |
| `tests/clear-guards-cross-week.spec.ts`, `tests/arr-slot-original-venue-sync.spec.ts` | **untouched** — they click `.slot-dd-item-label`, which still exists and still contains the same tokens |
| `tests/arrangement-conflict-autoselect.spec.ts` | **untouched** — reads the label text for `Week 8`, the venue and a time; all present |
| `tests/confirm-guards.spec.ts` | **untouched** — clicks `.slot-dd-item` |

New assertions to add with the rewrite: four rows present with the expected classes; `RSD3` with no
bracket for a one-cohort session; `(N cohorts)` shown when the session carries more; no cohort row
when the list is empty (asserted only if such a session is reachable on the fixture, otherwise the
branch is covered by a unit-style probe of `cohortText`).

## 7. Verification plan

1. `composer run lint:check` + PHPStan are **not** required (no PHP), but run them anyway if the
   blade's `@php` blocks change — they do not.
2. `php artisan view:clear`; restart the serve lane used for the run.
3. Browser: 390 / 768 / 1360, both themes — Summary always open; four rows; the badge aligned with
   row 1; the pop-up unchanged; a pick still fills the Summary and Submit stays visible.
4. Run the five named specs plus the two rewritten ones on the sanctioned testing lane; record counts.
5. Changelog entry at the top of `page-changelogs/replacement-arrangement-changelog.md`.

## 8. Risks this design closes

| Risk (from proposal) | How |
|---|---|
| A dead focus stop / listener survives | both deleted, and the spec asserts the button's absence |
| Taller rows overflow the panel | the panel already scrolls; rows measured at 390/1360 |
| A spec that clicks the label breaks | the wrapper class and the tokens are preserved |
| Wrong cohort count for same-code cohorts | the count is the raw list length, named in §3.2 |
| A second copy of the glyphs | hoisted to `RA_ICONS`, one home |
| Date spelling churn | parked; row 1 keeps month-first |
