# Design — arrangement venue picker: collapsible category headers

> **Status:** draft · 2026-11-13
> **Proposal:** `proposal.md`
> **Reference implementation:** `resources/views/livewire/venue-timetable.blade.php`
> CSS lines 229–276, JS lines 655–707

## 1. Shape of the change

A near-verbatim port of a shipped, user-approved pattern. There is no new
mechanism here: the venue page already decided the bar geometry, the state model,
the search rule and the chevron. This lane renames it and applies it to the
arrangement sheet.

That is the whole design. Everything below is the mapping plus the handful of
places where the two pages genuinely differ.

## 2. Where the code lives

**Page-local `<style>` in `replacement-arrangement.blade.php`**, next to the
existing `#arrangementMobile` rules — not `theme.css`.

| Reason | Evidence |
|---|---|
| The venue page already does this | `grep -c "venue-picker-group" public/css/theme.css` → **0** |
| `theme.css` is contended | `cancelled-slot-reuse` A19 claims it; it was written to minutes ago |
| Scope is one sheet on one page | no other page has this sheet |

Duplicating keeps the change inside one file. This is the **2nd** copy of the
pattern; the **3rd** must be promoted to `theme.css` per the repo's
promote-on-3rd-duplication rule. Recorded so the promotion is not forgotten.

## 3. Selector mapping

The arrangement page already owns the base class name, so only the new children
need names. Scope prefix stays `#arrangementMobile` (matching its neighbours),
rather than the venue page's `dialog#venueTimetablePicker` — the style block is
page-local, so the shorter prefix is unambiguous.

| Venue page | Arrangement page | Status |
|---|---|---|
| `.venue-picker-group` (`<button>`) | `.arrangement-mobile-venue-group` (`<button>`) | **class kept, element changes** |
| `.venue-picker-group-label` | `.arrangement-mobile-venue-group-label` | new |
| `.venue-picker-group-chevron` | `.arrangement-mobile-venue-group-chevron` | new |
| `.venue-picker-group-items` | `.arrangement-mobile-venue-group-items` | new |
| `#venuePickerGroup<N>` | `#arrangementPickerGroup<N>` | new |
| `data-venue-group` | `data-venue-group` | unchanged name |
| `.venue-picker-item` | `.arrangement-mobile-venue-item` | **untouched, exists** |
| `.venue-picker-body` | `.arrangement-mobile-venue-body` | **untouched, exists** |

### Property values copied exactly

```
position: sticky; top: 0; z-index: 1;
display: flex; align-items: center; justify-content: space-between; gap: 8px;
width: 100%; margin: 8px 0 2px; padding: 10px 10px 10px 12px;
border: 1px solid var(--color-outline); border-radius: var(--radius-sm);
background: var(--color-surface-variant); color: var(--color-on-surface);
font: inherit; font-size: 11px; font-weight: 700; letter-spacing: 0.6px;
text-transform: uppercase; text-align: left; cursor: pointer;
```

hover / `:focus-visible` → `border-color: var(--color-outline-strong)`;
`:focus-visible` → `outline: 2px solid var(--color-primary); outline-offset: 1px`.

Label: `min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap`.
Chevron: `flex: 0 0 auto; width/height: 14px; color: var(--color-on-surface-variant); transition: transform 0.15s ease`.
Collapsed: `[aria-expanded="false"] .…-chevron { transform: rotate(-90deg) }`.
Hidden items: `.…-items[hidden] { display: none }`.

**Only difference from the reference:** the arrangement sheet's scroll container
pads `8px 12px` where the venue one pads `8px 16px`. That padding is not part of
this change — left as is, so the bar sits 4px wider on each side than on the
venue page. Cosmetically irrelevant; recorded so it is not read as a mistake.

## 4. DOM before and after

```html
<!-- now -->
<div data-arrangement-mobile-venue-list>          <!-- .arrangement-mobile-venue-body, scrolls -->
  <div class="arrangement-mobile-venue-group">Block A · Floor 1</div>
  <button class="arrangement-mobile-venue-item">…</button>
  <button class="arrangement-mobile-venue-item">…</button>
  <div class="arrangement-mobile-venue-group">Block A · Floor 2</div>
  <button class="arrangement-mobile-venue-item">…</button>
</div>

<!-- after -->
<div data-arrangement-mobile-venue-list>          <!-- unchanged -->
  <button class="arrangement-mobile-venue-group" aria-expanded="true"
          aria-controls="arrangementPickerGroup0" data-venue-group="A|1">
    <span class="arrangement-mobile-venue-group-label">Block A · Floor 1</span>
    <svg class="arrangement-mobile-venue-group-chevron" aria-hidden="true" …/>
  </button>
  <div class="arrangement-mobile-venue-group-items" id="arrangementPickerGroup0">
    <button class="arrangement-mobile-venue-item">…</button>   <!-- moved in here -->
  </div>
  <button class="arrangement-mobile-venue-group" aria-expanded="false" …>…</button>
  <div class="arrangement-mobile-venue-group-items" id="arrangementPickerGroup1" hidden>…</div>
</div>
```

Items move from `host` to the group's `-items` div. **The items themselves are
not modified** — same classes, same `data-venue-code`, same `aria-pressed`, same
click handler.

## 5. State model

`aria-expanded` on the button is the **only** state. There is no JS variable.

The current callback is `forEach(function (group) {` (blade 5372) and receives
**no index**, yet every attribute below needs one — the signature must become
`function (group, groupIndex) {`. `groupIndex` is the position in the sorted
group list, so it is stable within one render and restarts at 0 on each rebuild.

```js
heading.addEventListener('click', function () {
    const isOpen = heading.getAttribute('aria-expanded') === 'true';
    heading.setAttribute('aria-expanded', String(!isOpen));
    items.hidden = isOpen;      // was open → now hidden
});
```

Toggle recomputes from the DOM, so the button and the container cannot drift.
Same approach as the venue page.

## 6. Re-render semantics — deliberate reset

`openArrangementVenuePicker()` calls `renderArrangementVenuePicker('')` every
time the sheet opens, and the search `input` handler re-renders on every
keystroke. The DOM is rebuilt from scratch, so **any manual expand/collapse is
lost**:

- reopen the sheet → back to "first group expanded";
- type in search → every group with a hit is expanded (rule 2 below).

This is identical to the venue page and is intended, not a defect. It also means
no state has to survive a re-render, which is why no `expandedGroups` set is
kept.

## 7. The three behaviour rules

1. **Default** — `const expanded = q ? true : groupIndex === 0;`
   First group in *sorted* order (block, then floor) is open; the rest closed.
2. **Search opens everything that matches** — the `q ? true :` branch.
   This matters only when a search spans **two or more groups**: those extra
   groups would otherwise start collapsed and their matches would be invisible.
   **It is not covered by the existing tests.** A search that resolves to a
   single venue yields a single group at `groupIndex === 0`, which opens with or
   without the branch — so the shipped `B010` assertion passes either way.
   The test is therefore specified in §12.6: search `tutorial` (matched through
   `humanizeType` → `'Tutorial Room'`, spanning several blocks/floors), assert
   the group count is **> 1 first**, then assert **every** matching group is
   expanded. The count guard is what stops the test decaying into the vacuous
   single-group case.
   Note `arrangementVenueParts` takes the **first digit** (`/\d/`, blade 5322),
   so `B010` groups under floor `'0'` — not "Floor 1". Do not repeat the venue
   page's "B100 sits on Floor 1" comment here; it does not hold.
3. **Selection** — the item's click handler still calls
   `closeArrangementVenuePicker()` then `venueDropdown.select(v.code)`. Guards,
   chips and the selected state are untouched.

## 8. Chevron: `createElementNS`, not `createElement`

```js
const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
chevron.setAttribute('class', 'arrangement-mobile-venue-group-chevron');
// …width/height 14, viewBox 0 0 24 24, fill none, stroke currentColor,
//    stroke-width 2, stroke-linecap/linejoin round, aria-hidden true
const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
path.setAttribute('d', 'm6 9 6 6 6-6');
```

`document.createElement('svg')` produces an **HTML** unknown element. Its `<path>`
then lives in the HTML namespace and never paints — `stroke` computes to `none`.
The venue page hit exactly this bug during its own apply; the note is carried
here so it is not rediscovered.

## 9. Sticky mechanics

The sticky bar is inside `.arrangement-mobile-venue-body`, which is the scroll
container (`flex: 1 1 auto; min-height: 0; overflow-y: auto`). So `top: 0` pins
the bar to the top of **that** box, and the bar scrolls away with its group once
the group leaves.

The search input sits *outside* the scroll container, in
`.arrangement-mobile-venue-search` with its own `border-bottom`. So the pinned
bar and the search box never overlap — no `z-index` stacking conflict, no
double-sticky band.

`overscroll-behavior: contain` on the body already stops scroll chaining.

## 10. Accessibility

- `<button type="button">` gives Enter/Space and the correct role for free.
- `aria-expanded` announces the state; `aria-controls` points at the group.
- The chevron is `aria-hidden="true"` — it is decoration, the label carries the
  name.
- `:focus-visible` gives a 2px primary outline, so keyboard users see the bar.
- Reduced motion: **already handled, no work needed.** The blade has its own
  block at `replacement-arrangement.blade.php` **1811–1827** that sets
  `transition-duration: 0.01ms !important` on
  `body.arrangement-mobile-mode #arrangementMobile *` (and `::before`/`::after`),
  so the chevron's 0.15s transform collapses to nothing under reduced motion.
  `theme.css:4273` only disables `.skeleton` — reading that alone gives the wrong
  answer. The test must not assert `transform` while reduced motion is emulated.

## 11. What could break, and why it does not

| Risk | Check |
|---|---|
| A test asserts the header is a `<div>` | `grep -rn "arrangement-mobile-venue-group" tests/` → **0 hits** |
| Item queries break when items move into a wrapper | selectors are descendant-based: `#arrangementMobileVenue .arrangement-mobile-venue-item` and `dialog.locator('.arrangement-mobile-venue-item')` both still match |
| `data-venue-code` lookup breaks | attribute is on the item, untouched |
| Desktop dropdown changes | rules are all `#arrangementMobile …`; `venue-dd-panel` untouched |
| Empty state breaks | the "No venues match that search." early return happens **before** grouping; no header is rendered |
| Shared file collision | none touched — no `theme.css`, no `ui-common.js`, no partial |

## 12. Test design

New test in `tests/venue-dropdown-responsive.spec.ts`, beside the existing
mobile-sheet test. It must:

1. assert first group `aria-expanded="true"` and later groups `"false"`;
2. assert header background ≠ item background ≠ sheet body background (the bar
   must read as chrome, not as a row);
3. assert each header is a `BUTTON` with exactly one
   `svg.arrangement-mobile-venue-group-chevron` and a resolving `aria-controls`;
4. click header 1 → its `-items` becomes hidden; click header 2 → it opens;
5. **poll** the chevron `transform` after collapse — a bare read races the
   0.15s transition and flakes;
6. **prove rule 2** — search a term matching venues in **more than one group**
   (`tutorial`, via `humanizeType` → `'Tutorial Room'`). Assert the group count is
   **> 1** first — that guard is the whole test: without it a data change could
   reduce the search to one group and the assertion would pass vacuously, which is
   exactly the hole that hid this rule originally. Then assert **every** matching
   group has `aria-expanded="true"`. A single-venue search (`B010`, `B100`)
   resolves to one group at index 0 and opens with or without the branch.

Regression re-runs: `venue-dropdown-responsive.spec.ts` (the sheet) and
`arrangement-mobile.spec.ts` (its `t22DifferentVenue` helper).

## 13. Apply order

**Step 0 — the gate.** Do not start step 1 until `cancelled-slot-reuse` **B02
and B03 are both ticked**. Step 1 captures the rollback oracle; captured before
B02 lands, it holds the pre-B02 function and a rollback would revert B02's edits
(`proposal.md` §Rollback summary, `tasks.md` §Before anything else).

1. Record the real `git status --porcelain` and `git diff --stat` for the three
   files, and locate the two edit regions by selector/function name (line
   numbers drift as `cancelled-slot-reuse` writes). No whole-file backup — the
   rollback is a region restore — no git command works here (`proposal.md`
   §Rollback summary).
2. CSS block at ~1257–1264 (through its closing brace) → bar geometry +
   children.
3. JS `renderArrangementVenuePicker` (5332–5412) → button + items wrapper +
   `groupIndex` in the callback signature.
4. New test.
5. Changelog: a new `##` entry at the top; do not touch the uncommitted `###`
   block at 3753–3786.
6. **Restart the blade** — `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`
   — or the stale-view trap serves the old markup and the new test fails for a
   phantom reason.
7. Run both specs with the guard satisfied:
   `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test tests/venue-dropdown-responsive.spec.ts tests/arrangement-mobile.spec.ts`

Step 7 is not optional formatting: `tests/arrangement-mobile.spec.ts:72` calls
`abortUnlessTestingTarget(..., { requireAllowSql: true })` and aborts at
collection without it, and `playwright.config.ts:10` otherwise falls back to
`http://localhost:8000` — the **demo DB**. `tests/venue-dropdown-responsive.spec.ts`
carries **no** guard of its own, so a bare `npx playwright test` would drive the
demo database.

