# Cancel this Class? — the “Other” reason block

> **Status: READY — items 1–4 are DONE and verified by PI.**
> The two-column reason grid, the heading asterisk, the footer `CANNOT UNDONE`
> strip, and the reason gating are already in place. Only item 5 remains: the
> labelled “Other” block. Do not redo items 1–4.

---

## Section 1 — What the user wants (plain English)

Only the **Other** reason needs work now.

1. **“Other” stays the eighth reason.**
   It remains the last radio in the two-column grid. Do not pull it out of the
   grid or give it a special inline field.

2. **Choosing “Other” reveals a labelled block below the grid.**
   The block is full width and sits below the whole reason grid — never in the
   grid cell beside the radio. It contains:
   - a small uppercase muted label: **`Other reason *`**, with the app’s red
     required asterisk;
   - the existing full-width `.modal-textarea` below it: 3 rows, the
     `Describe the reason for "Other"…` placeholder, vertical resize, and
     `required`.
   The block is hidden for every other reason.

3. **The detail is required.**
   With “Other” chosen and the textarea blank, Yes stays disabled and the
   existing hint `Please describe the reason for "Other".` still appears.
   Typing a non-empty value enables Yes again, and the warning strip follows.

4. **Switching reasons behaves.**
   Choosing another reason hides the block; choosing “Other” again shows it
   with the typed text intact.

5. **Everything PI verified stays untouched.**
   The eight reasons and their values, the two-column grid, the heading
   asterisk, the footer warning, the inline hint, the Yes gating, the success
   screen, and the modal on all three pages
   (`my-timetable-ui`, `cohort-timetable`, `venue-timetable`).

---

## Section 2 — The plan is a guide, not a contract

The route below is a **suggestion**. Class names, the wrapper id, and the CSS
location may change if a cleaner way is found. What must not change is the
visible label + full-width textarea below the grid and the show/hide behaviour.

Guardrails:

- Do **not** redo items 1–4; they are verified.
- Do **not** move the textarea into the reason grid or turn it into a one-line
  input.
- Do **not** edit `../ui-prototype/bulk-cancel-preview.html`.
- Do **not** touch `.sdd/changes/bulk-cancel-class/`.
- No DB/schema changes, no new dependency.
- Keep the shared modal working on all three pages.
- Commit only the files this task touches.
- Playwright stays paused unless the user explicitly asks.

---

## Appendix — Measured anchors and suggested route (advisory)

Measured 2026-11-10 on `fedora-backend` HEAD `21ae301`. Re-verify before editing.

### Current state

| Fact | Where |
|---|---|
| Reason list container | `resources/views/partials/ui-cancel-class-modal.blade.php:38` (`#cancelReasonList`) |
| Bare “Other” textarea | same partial `:39` (`#cancelOtherDetail`), no label/wrapper |
| Reason renderer | `public/js/ui-common.js`, `CancelClassModal._renderReasons()` ~`:4943` |
| “Other” show/hide + Yes gate | `public/js/ui-common.js`, `_refreshGate()` ~`:5024` |
| “Other” required detail | `public/js/ui-common.js`, `ClassCancellation.validate()` ~`:4279` |
| Modal open reset | `public/js/ui-common.js`, `open()` ~`:4919` |
| Existing textarea CSS | `public/css/theme.css`, `.modal-textarea` ~`:4478` |
| Existing reason grid CSS | `public/css/theme.css`, `.cancel-reasons` ~`:1926` |
| Existing asterisk CSS | `public/css/theme.css`, `.cancel-required-mark` ~`:1942` |
| Prototype reference | `../ui-prototype/bulk-cancel-preview.html` `#bcOtherWrap` ~`:446`, `.bc-other-label` ~`:135` |

### Suggested changes

1. **Markup**
   - Wrap `#cancelOtherDetail` in a hidden `#cancelOtherWrap` with
     `margin-top:10px`.
   - Add the label above the textarea:
     `Other reason <span class="cancel-required-mark">*</span>`.
   - Keep the textarea's `rows="3"`, placeholder, and add `required`.
   - Drop the textarea's inline `display:none`; the wrapper owns visibility.

2. **CSS**
   - Add `.cancel-other-label` (block, 11px, 700, letter-spacing .8px,
     uppercase, muted, margin-bottom 5px). Tokens only.

3. **JS**
   - `_refreshGate()`: toggle `#cancelOtherWrap` (`reason === 'other'`) instead
     of the textarea's display.
   - `open()`: hide the wrapper and clear the textarea value.
   - Leave `validate()` and the Yes gating untouched.

4. **Tests**
   - Update `tests/cancel-class.spec.ts` if it asserts the textarea's own
     visibility; point it at the wrapper/label instead.

### Suggested files

- `resources/views/partials/ui-cancel-class-modal.blade.php`
- `public/js/ui-common.js`
- `public/css/theme.css`
- `tests/cancel-class.spec.ts` (only if the assertion needs it)

### Verification

1. `php artisan view:cache` and `php artisan view:clear`.
2. Manual on the three pages:
   - choose “Other” → `Other reason *` + full-width textarea appear below the
     grid;
   - choose another reason → the block hides;
   - back to “Other” → the typed text is still there;
   - “Other” blank → Yes disabled + hint; filled → Yes enabled + warning;
   - success state unchanged.
3. Update the Playwright spec only; do not run it until the user asks.
