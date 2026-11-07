# UI Update — Remove the **Change Venue?** confirmation modal

**Page:** `replacement-arrangement` · **Date:** 2026-11-05 · **For:** PI
**Status:** brief only — nothing implemented yet. Line numbers are from today's `HEAD`.

---

## 0. How to work on this (instructions for PI)

- Use **SDD explore → propose → apply only if you actually need to**. This change is small and
  fully mapped below (§4); if your diff matches it, a one-line note saying "SDD not needed —
  single call-site removal, mapped in the brief" is enough. Explore first if you find anything
  the brief does not cover.
- **Stay inside the stated scope.** No refactors, renames, formatting sweeps, or "while I'm
  here" edits. If you believe something outside §5 must change, stop and ask.
- Never push. One conventional commit. Update the page changelog.

---

## 1. What the user asked (verbatim)

> **Page:** `replacement-arrangement`
>
> I want to remove the **Change Venue?** confirmation modal that appears when users change venue
> after selecting slots on the grid.
>
> **Important:** Before removing it, investigate whether this modal shares logic with other
> confirmation and warning modals.
>
> Ensure these existing behaviours remain unaffected: Unsaved Changes warning; Change Subject?
> confirmation; Change Time Slot? confirmation; Browser Back warning; Browser Refresh warning.
>
> **Requirements:** remove only the Change Venue confirmation modal; preserve all other modal
> triggers, messages and functionality; changing venue must still work correctly; check for
> shared event handlers or state that could affect other warnings; verify all listed behaviours
> after the change; avoid unrelated modifications.
>
> **Do not assume the other modals are independent. Investigate before editing.**

---

## 2. The investigation the user asked for — result

**There is no "Change Venue modal".** The pop-up is the page's one shared confirmation shell,
`#confirmModal`, opened by `showConfirmModal(title, bodyHtml, callback, opts)`.
"Removing the Change Venue modal" therefore means **removing one call site**, not deleting any
modal, markup or CSS.

The call site — `resources/views/livewire/replacement-arrangement.blade.php:1971`:

```js
function onVenueChange() {
    if (revertingChange) return;
    const newVenue = venueDropdown ? venueDropdown.getSelected() : 'B103';
    if ((selectedBlock || getGlobalTotal() > 0) && newVenue !== currentVenue) {
        showConfirmModal(
            'Change <span class="warn-keyword">Venue</span>?',
            'You have selected slots on the grid. Changing the <span class="warn-keyword">venue</span> will clear them.<br>Continue?',
            function() {
                hideConfirmModal();
                discardAllSelections();
                applyVenueChange(newVenue);
            },
            { danger: true, confirmLabel: 'Yes, Change', cancelLabel: 'No, Keep My Selection' }
        );
        // Cancel path: snap the dropdown back to the current venue
        const cancelBtn = document.querySelector('#confirmModal .btn-outline');
        if (cancelBtn) {
            cancelBtn.onclick = function(e) {
                hideConfirmModal(e);
                revertingChange = true;
                venueDropdown.select(currentVenue);
                revertingChange = false;
            };
        }
        return;
    }
    applyVenueChange(newVenue);
}
```

### Shared logic — checked, and it is NOT shared with the venue guard

| Thing | Where | Used by the venue guard? |
| --- | --- | --- |
| `showConfirmModal` / `hideConfirmModal` | `:2068` / `:2093` | yes — the generic shell (also used by Clear All, Submit, Unsaved Changes, conflicts) |
| `confirmChangeWithSelection(label, proceed, cancel)` | `:2117` | **no** — subject and time slot use it; the venue guard is a hand-written near-copy of it |
| `revertingChange` | declared `:1271` | **yes** — early-return at `:1972`; also used by subject, slot, and `syncVenueFamily` |
| `discardAllSelections()` | `:3344` | **yes** — the "Yes, Change" action; it also spends the booking pre-fill (`markBookingCancelled()`) |
| `applyVenueChange()` | `:2013` | **yes** — the actual switch |

So: the **shell** is shared (keep it), the **helper** is not (leave `confirmChangeWithSelection`
alone), and the **state** (`revertingChange`, `discardAllSelections`) is shared and must keep
working exactly as it does.

---

## 3. Where the guard fires today

- **Only** from `onVenueChange()`, which is the venue dropdown widget's `onSelect` callback
  (`VenueDropdown`) and is also reached by programmatic `venueDropdown.select(...)`.
- `syncVenueFamily()` (`:3987`) already wraps its `venueDropdown.select(target)` in the
  `revertingChange` pair **specifically to skip this modal** ("no mid-switch Change Venue?
  confirm — the slot-change consent already ran"). After the removal that suppression is no
  longer *needed*, but it is still correct and must stay (it also prevents a double
  `applyVenueChange` during a subject/slot re-sync).
- No other page uses `onVenueChange`.

---

## 4. The exact change (one function)

Replace the body of `onVenueChange()` with the "Yes, Change" path, run immediately:

```js
function onVenueChange() {
    if (revertingChange) return;
    const newVenue = venueDropdown ? venueDropdown.getSelected() : 'B103';
    /* 2026-11-05 (user): the Change Venue? confirm is retired — switching
       venue clears the selection without asking, exactly what "Yes, Change"
       did; every other guard keeps its own modal. */
    if ((selectedBlock || getGlobalTotal() > 0) && newVenue !== currentVenue) {
        discardAllSelections();
    }
    applyVenueChange(newVenue);
}
```

That is deliberately the smallest possible diff:

- the condition is unchanged, so a same-venue re-select still rebuilds the grid exactly as today;
- `discardAllSelections()` is unchanged, so the booking pre-fill is still spent
  (`markBookingCancelled`) and the whole cross-week store is wiped — **identical to pressing
  "Yes, Change" today**;
- the bespoke cancel handler (the dropdown snap-back) disappears with the modal;
- `revertingChange`, `applyVenueChange()` and the dropdown widget are untouched.

**Assumption (state it in your report):** the selection is still cleared, silently. The old modal's
only promise was "changing the venue will clear them", and the user asked to remove the question,
not to change what happens. If the user later wants selections kept across a venue switch, that is
a separate feature — do not attempt it here.

---

## 5. What must NOT change (this is the acceptance surface)

1. **Change Subject?** — `onSubjectChange()` `:2973` → `confirmChangeWithSelection('subject', …)`,
   title `Change Subject?`, danger styling, cancel restores the previous subject.
2. **Change Time Slot?** — `selectSlot()` `:3869` → `confirmChangeWithSelection('time slot', …)`,
   including the same-slot no-confirm rule and the picker snap-back on cancel.
3. **Unsaved Changes (in-page)** — `navigateTo()` `:2376` and `goBack()` `:2396`, labels
   "Yes, Leave Page" / "Yes, Go Back" and "Stay".
4. **Browser Back / Refresh / close** — `beforeunload` `:2421` with `allowUnload`; the Confirm
   paths of 3 must keep setting `allowUnload = true` so there is no double prompt.
5. **Clear All Selections** — `clearAll()` `:2343` (danger, undo toast).
6. **Submit Request** and the **submission conflict** modal — unchanged callers of
   `showConfirmModal`.
7. **The shared shell** — `#confirmModal` markup, `showConfirmModal` / `hideConfirmModal`,
   `danger` / `errorStyle` classes, the `confirmCallback` state. Do not touch.
8. **`confirmChangeWithSelection`** — do not delete, rename or "simplify" it because the venue
   guard was its near-copy. Two callers still depend on it.
9. **`syncVenueFamily()`'s `revertingChange` pair** — keep it, even though the modal it was
   written for is gone.

---

## 6. Tests to change (and the ones that must stay green)

Playwright — update, do not delete the file:

- `tests/confirm-guards.spec.ts:70` — *"venue change with selection pops confirmation; cancel
  restores"* — the cancel path no longer exists. Rewrite it as: pick a slot, switch venue,
  **no** `#confirmModal` appears, and the grid shows the new venue.
- `tests/confirm-guards.spec.ts:81` — *"venue change confirm clears selection and applies"* —
  remove the `#modalConfirmBtn` click; assert directly that the selection clears
  (`.cell-content.cell-selected` gone) and the venue applies.
- `tests/clear-guards-cross-week.spec.ts:141` — *"BUG-08 venue: … Yes clears honestly; No
  restores"* — drop the `Change Venue?` title assertion and the "No restores" half; keep the
  cross-week store receipt assertion (`4 of 4 slots`) for the hop, and assert the venue switch
  now clears it without any modal.
- `tests/clear-guards-cross-week.spec.ts:220` — *"BUG-08 baseline: no-hop venue change still pops
  the modal"* — invert it: no-hop venue change **does not** pop a modal.

Must stay untouched and green (the proof of no collateral damage):

- the subject and slot guard tests in `tests/confirm-guards.spec.ts`;
- `tests/clear-guards-cross-week.spec.ts:126` (*"the hop itself stays silent"*) and the
  subject/slot parts of the BUG-08 file;
- any test asserting `Unsaved Changes`, the beforeunload guard or Clear All.

Run at minimum:

```bash
npx playwright test tests/confirm-guards.spec.ts tests/clear-guards-cross-week.spec.ts
php artisan view:cache && php artisan view:clear
```

(`view:cache` proves the Blade still compiles; these specs are read-only UI tests — no demo-DB
writes.)

---

## 7. Manual verification (the user's own checklist)

On `/replacement-arrangement-ui`:

1. Select a slot, change the venue → **no pop-up**, the new venue's grid appears, the selection
   is gone, the strip reads `0 of N`.
2. Select a slot, change the **subject** → the `Change Subject?` pop-up still appears; "No"
   restores the old subject and the selection.
3. Select a slot, pick a **different time slot** → `Change Time Slot?` still appears; "No"
   restores the picker and the selection.
4. Select a slot, click **Back** → `Unsaved Changes` still appears; "Stay" keeps you there.
5. Select a slot, press **Refresh** → the browser's own warning still appears.
6. Select a slot, **Clear All** → its modal and undo toast still work.
7. No console errors at any step.

---

## 8. Scope guardrails

- Touch **only** `resources/views/livewire/replacement-arrangement.blade.php` and the two spec
  files, plus the changelog.
- Do **not** edit `public/js/ui-common.js`, the shared partials, `theme.css`, or the frozen
  design template (`resources/views/ui-design-templates/replacement-arrangement-UIdesign-template.blade.php`).
- The only comment worth updating is the `syncVenueFamily()` note at `:3977` that mentions the
  "Change Venue?" confirm — reword it, do not rewire the code.
- No new toast, no undo for the silent clear, no other UX "improvements".

---

## 9. Delivery

- Changelog entry in `page-changelogs/replacement-arrangement-changelog.md`: what was removed, the
  four guards re-verified, and the "selection still clears, silently" note from §4.
- Commit message shape:
  `ui(replacement-arrangement): changing venue no longer asks for confirmation`
- Never push.

---

## 10. Acceptance checklist

- [ ] Changing venue with a selection shows no confirmation, applies the venue and clears the
      selection (same end state as the old "Yes, Change").
- [ ] Changing venue with no selection behaves exactly as before.
- [ ] Changing venue to the **same** venue still rebuilds the grid as before.
- [ ] Change Subject?, Change Time Slot?, Unsaved Changes (back and nav), browser
      back/refresh, Clear All and Submit each still show their own modal with unchanged copy.
- [ ] `confirmChangeWithSelection` and `showConfirmModal` are untouched.
- [ ] Updated specs pass; the subject/slot guard specs are unmodified and still pass.
- [ ] No unrelated files in the diff; nothing pushed.
