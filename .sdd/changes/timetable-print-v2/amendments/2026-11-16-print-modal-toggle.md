# Amendment 2 — the dialog's switch wears the house toggle (2026-11-16)

> Amendment to a frozen lane, on top of amendment 1. `proposal.md`, `design.md`,
> `specs/` and `tasks.md` stay frozen; amendment 1 stays frozen; this file carries the
> delta and supersedes **A1/A12/A5's "the switch keeps its own component"** wording only
> where stated in §3.

## 1. What the user asked for (2026-11-16)

The switch built for the Print dialog (`<label class="print-modal__switch" for="printNormalToggle">`)
must look like the toggle CRS already uses — **Staff → My Replacements → Filter section**
— including spacing, alignment, colours, sizing and interaction states. Keep the label,
the behaviour and the accessibility attributes. **Do not invent a new toggle design.**

## 2. The reference, as it actually is in this repo

`resources/views/livewire/replacement-history.blade.php:148-155` (the same markup on
`request-approval.blade.php:254` and `replacement-home.blade.php:214`):

```html
<div class="filter-field filter-field--toggle">
  <label class="filter-label" for="hideCompleted">Hide COMPLETED</label>
  <label class="toggle-wrapper" data-tip="…">
    <input type="checkbox" id="hideCompleted" checked>
    <span class="toggle-track"><span class="toggle-thumb"></span></span>
    <span class="toggle-label">Exclude <span class="info-keyword">COMPLETED</span> Status</span>
  </label>
</div>
```

`theme.css:5312-5360` (sanctioned, "Promoted to theme.css — Phase 4"):

| Part | Values |
|---|---|
| `.toggle-wrapper` | `inline-flex`, `align-items:center`, `gap:8px`, `cursor:pointer`, `user-select:none`, `white-space:nowrap` |
| `.toggle-wrapper input` | `position:absolute; opacity:0; width:0; height:0; pointer-events:none` |
| `.toggle-track` | `position: relative` (the thumb's absolute placement depends on it), **36 × 20**, `flex-shrink: 0`, `border-radius: pill`, `background: var(--color-outline)`, `transition: background .2s` |
| `.toggle-thumb` | **16 × 16**, `border-radius: 50%`, `top:2px; left:2px`, `background: var(--color-surface)`, `translateX(16px)` when checked, `box-shadow: 0 1px 3px rgba(0,0,0,.2)`, `transition: transform .2s` |
| checked track | `background: var(--color-primary)` |
| `.toggle-label` | `13px`, `500`, `color: var(--color-on-surface-variant)` |
| focus | `.toggle-wrapper:focus-within .toggle-track { outline: 2px solid var(--color-primary); outline-offset: 2px }` |
| filter variant | `.filter-field--toggle .toggle-wrapper { height:38px; padding:0 12px; gap:0; border:1px solid var(--color-outline); border-radius: var(--radius-md); background: var(--color-surface-variant) }` **and `.filter-field--toggle .toggle-label { display: none }`** — in the filter bar the caption is the only visible text |
| filter caption | `.filter-label { margin:0 0 4px 2px; font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color: var(--color-primary) }` |

**The dialog's private switch (`.print-modal__switch` + `.print-modal__switch-label`)
is therefore deleted**, not restyled: a second toggle component beside the sanctioned one
is exactly what the user is asking to stop. The dialog reuses the classes above.

## 3. Decisions

### B1 — Same markup, same classes, no new component

`partials/ui-print-modal.blade.php` renders the house shape — the one the site uses
*outside* the filter panel too (`nav-notifications.blade.php:165`, `:170`), where the
label is simply visible beside the track:

```html
<div class="print-modal__field print-modal__field--switch">
  <label class="toggle-wrapper" id="printNormalSwitch">
    <input type="checkbox" id="printNormalToggle" aria-label="Show normal class only" aria-describedby="printNormalHint">
    <span class="toggle-track"><span class="toggle-thumb"></span></span>
    <span class="toggle-label">Show normal class only</span>
  </label>
  <p class="print-modal__hint" id="printNormalHint">All Weeks prints normal classes only.</p>
</div>
```

- **The label text is unchanged** (`Show normal class only`) and stays in
  `.toggle-label` — the house component's own place for it, rendered exactly as the
  notification toggles render theirs. There is **no caption above it**: the filter bar
  hides `.toggle-label` and lets a `filter-label` carry the words, but that chip+uppercase
  caption belongs to the filter panel's field grid, and copying it here would print the
  same phrase twice or turn a sentence into a 10 px uppercase eyebrow (see B3).
- `id="printNormalToggle"` is unchanged, so `PrintSheet` and every receipt keep working.
- `aria-label` and `aria-describedby` are kept verbatim (A12's promise).
- **No `for=`**: the row is already a `<label>` (implicit labelling), so a second label
  for the same input would announce it twice.

### B2 — Only the disabled state is added, because the reference has none

No disabled toggle exists anywhere in CRS, and A5 requires this one to read
**disabled and ON** for All Weeks. So one rule, in the dialog's scope, using tokens:

```css
.print-modal__field--switch .toggle-wrapper:has(input:disabled) { cursor: not-allowed; }
.print-modal__field--switch .toggle-wrapper:has(input:disabled) .toggle-track { opacity: .55; }
.print-modal__field--switch .toggle-wrapper:has(input:disabled) .toggle-label { opacity: .55; }
```

`.55` is the dimming the lane's old disabled control used on its **label**; the old
track dimmed only to `.8`, so the new rule dims the track further than before. That is
stated as a change, not as continuity: the track keeps its primary fill, so the switch
still reads ON while visibly being unavailable.

### B3 — Where the dialog differs from the filter bar, and why

The reference's toggle sits in a 38 px bordered chip because it lives in a *filter panel*
where every field is a boxed control, **and it hides its own `.toggle-label` there** — the
uppercase `filter-label` above it carries the words. The dialog is a form with one caption
above one control, so:

- the 38 px bordered chip (`.filter-field--toggle .toggle-wrapper`) is **not** copied —
  it is the filter panel's field container, not the toggle, and the dialog's week select
  beside it has no chip either;
- the caption is **not** copied: it would either duplicate the visible label or turn
  "Show normal class only" into a 10 px uppercase eyebrow, which is the filter panel's
  voice, not the dialog's;
- so the visible words are the `.toggle-label`, exactly as the notification toggles show
  them;
- the Week field keeps the label style it already has (`.print-modal__label`, 12 px/600)
  — **this amendment does not touch it**, and the two fields therefore do not claim a
  shared caption style;
- everything that makes the toggle *the* toggle — track, thumb, colours, gap, checked
  fill, focus ring, label type — is the reference's, untouched.

### B4 — Touch target

The old control promised a 48 × 48 hit box (A12). The house track is 36 × 20 and its row
is 20 px tall, which would **fail WCAG 2.5.8** (24 × 24) — so the wrapper takes one
sizing addition, in the dialog's scope:

```css
.print-modal__field--switch .toggle-wrapper { min-height: 24px; }
```

24 px is the smallest value that complies, and it moves nothing visually (the 20 px track
sits centred inside it). A12's 48 × 48 promise is **superseded deliberately**: matching
the sanctioned component is the user's instruction, and 24 px is the standard that governs.
No `any-pointer: coarse` rule is added — the reference has none, and the row is wide.

### B5 — Behaviour untouched, and exactly two test lines move

`PrintSheet._syncSwitch()` still sets `checked` and `disabled` on `#printNormalToggle`;
the preference memory (`_normalPref`), the All-Weeks rule, the hint's show/hide, the
`canPrint` veto and the paper itself are untouched.

Two lines in `tests/timetable-print.spec.ts` are **not** untouched, and both are named here
so the run cannot surprise anyone:

| Line | Today | Why it breaks | Fix |
|---|---|---|---|
| `:360` | `page.locator('.print-modal__switch')` | B1 deletes that class | `.print-modal__field--switch .toggle-wrapper` — scoped, so it can never collide with a future house toggle on these pages |
| `:418` | `page.locator('#printNormalToggle').check()` | the house input is `0 × 0` with `pointer-events: none` (`theme.css:5320-5326`), so Playwright sees no bounding box and times out — the repo already documents this in `tests/helpers/filters-panel-helper.ts:10-11` | click the wrapper: `.print-modal__field--switch .toggle-wrapper`.click() — the same move every notification test makes |

`toBeDisabled()`, `toBeEnabled()` and `toBeChecked()` are state assertions and keep working
on the invisible input.

## 4. Spec delta

| PR | Change |
|---|---|
| **PR-21** | **Declarative.** "the switch" is the house `.toggle-wrapper` component used by the replacement pages' filter bar; its disabled state dims the track and label to 55 %. |
| PR-02 | Unchanged in substance — All Weeks still disables it and it still reads ON. |
| PR-19 | Applies unchanged: no new dependency, tokens only (`.55` is an opacity, not a colour). |

## 5. Tasks

| Task | Work |
|---|---|
| **T34** | Rebuild the modal's switch markup on `.toggle-wrapper` / `.toggle-track` / `.toggle-thumb` / `.toggle-label` (no caption, no `for=`); keep the ids and the two aria attributes |
| **T35** | Delete `.print-modal__switch*` from `theme.css` (including its `any-pointer: coarse` rule); add the disabled rule, the `min-height: 24px`, the `.toggle-label` line spacing **and the hint's indent** — `margin: 2px 0 0 30px` (`theme.css:7264`) was aligned to the old label's 30 px `padding-left`, so it becomes `0`; tokens only |
| **T36** | Re-point the **two** lines in §B5; re-run the print receipts, the four page suites and the two PDFs; re-shoot the dialog in both themes |

**Effort:** ~1 hour. **Risk:** very low — one component swap inside a dialog, no logic.

## 6. Not in scope

- No change to the filter pages themselves, and no edit to `.toggle-wrapper`'s shared
  rules (that would move every existing toggle on the site).
- No change to the dialog's week select, footer, or the paper.
