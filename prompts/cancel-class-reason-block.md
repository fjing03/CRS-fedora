# Cancel this Class? — Cancellation Reason block

> **Status: WAITING — do not start yet.**
> PI is currently executing two UI updates on `replacement-arrangement`
> (UI Update 1 — Selected Summary Card, then UI Update 2 — Too-Soon Slots).
> PI must finish **and verify** both before this task starts. This task also
> touches `public/css/theme.css`, so running both in the same working tree
> risks sweeping PI's uncommitted edits into our commit. Start only after PI
> reports both updates complete.

---

## Section 1 — What the user wants (plain English)

In the **Cancel this Class?** modal, only the **Cancellation Reason** area
should change.

1. **Remove the shouty line.**
   Delete “You MUST Select a Cancellation Reason”. The section should go
   straight from the divider into the reason list. The heading and the disabled
   Yes button already tell the user that a choice is needed, so do not replace
   the removed line with another instruction or warning paragraph.

2. **Two reasons per row.**
   The eight reasons should sit in two columns (four rows), not eight rows.
   Keep the labels exactly as they are and keep the choice single-select. The
   rows should stay easy to scan, with the same spacing as the rest of the
   modal, and on a narrow screen the list should fall back to one reason per
   row so nothing is cramped.

3. **Mark the heading as required.**
   Put a small red asterisk next to “Cancellation Reason”, the same way other
   required fields in the app are marked. It sits after the heading text and is
   only a visual marker; it should not become part of the label or a new
   sentence.

4. **Move and shorten the warning.**
   The “This action cannot be UNDONE” line should move to the footer, directly
   above the **Yes, Cancel Class** button, and read **`CANNOT UNDONE`**.
   It should be a small, right-aligned line in the warning colour. It should
   only appear when the Yes button is enabled (a valid reason has been chosen).
   It must disappear again if the reason is cleared or “Other” is left blank,
   and it must never show in the success state.

**Everything else stays the same:** the eight reasons and their values, the
“Other” textarea and its required detail, the inline hint for a blank “Other”
or a server refusal, the Yes-button gating, the success screen, and the modal
on all three pages (`my-timetable-ui`, `cohort-timetable`, `venue-timetable`).
The prototype already shows this arrangement, so the real modal should match
that look and feel.

---

## Section 2 — The plan is a guide, not a contract

The detailed route below is a **suggestion**. It does **not** have to be
followed exactly. File placement, class names, the exact CSS location, and the
shape of the JS helper may change if a cleaner way is found. What must not
change is the four user-visible outcomes in Section 1 and the existing
behaviour.

Guardrails:

- Do **not** touch `../ui-prototype/bulk-cancel-preview.html`.
- Do **not** touch `.sdd/changes/bulk-cancel-class/` (another lane).
- No DB/schema changes, no new dependency.
- Keep the shared modal working on all three pages.
- Commit only the files this task touches; do not sweep PI's work.
- Playwright stays paused unless the user explicitly asks to run it.

---

## Appendix — Measured anchors and suggested route (advisory)

Measured 2026-11-10 on `fedora-backend` HEAD `8688a3e`. Re-verify before editing.

### Current state

| Fact | Where |
|---|---|
| Static instruction line | `resources/views/partials/ui-cancel-class-modal.blade.php:30` |
| Irreversible line in the confirm body | same partial, `:41` |
| Title + reasons rendered one per row | `public/js/ui-common.js`, `CancelClassModal._renderReasons()` |
| Yes gating | `public/js/ui-common.js`, `_refreshGate()` |
| Confirm ↔ success swap | `public/js/ui-common.js`, `_swapState()` |
| `.cancel-reason-required` CSS | `public/css/theme.css` (~`:1897`) |
| Shared warning partial | `resources/views/partials/ui-irreversible-line.blade.php` (also used by Cancel Replacement, Approve, Reject) |
| Test pin for the removed line | `tests/cancel-class.spec.ts:318` |

### Suggested changes

1. **Remove the instruction**
   - Delete `<p class="cancel-reason-required" id="cancelClassRequired">…</p>`.
   - Delete the unused `.cancel-reason-required` CSS rule.
   - Update the partial/JS comments that mention it.
   - Keep `#cancelReasonHint` — it still carries the blank-“Other” and
     server-refusal messages.

2. **Two radios per row**
   - In `_renderReasons()`, render a `.cancel-reasons` container inside the
     `detail-group` and append the eight labels there.
   - Suggested CSS:
     `.cancel-reasons { display: grid; grid-template-columns: 1fr 1fr; column-gap: 20px; }`
     and one column under `@media (max-width: 520px)`.

3. **Required asterisk**
   - Title becomes
     `Cancellation Reason <span class="cancel-required-mark">*</span>`
     (title is generated in `_renderReasons()`).
   - Suggested CSS: `.cancel-required-mark { color: var(--color-error); }`

4. **Move + shorten the warning**
   - Add an opt-in `compact` flag to `ui-irreversible-line.blade.php`; the
     default keeps the current sentence byte-identical for the other three
     modals.
   - In the cancel partial, wrap the warning + Yes button in
     `.cancel-confirm-wrap`, with the warning hidden initially.
   - `_refreshGate()` shows the warning only when `check.ok` is true.
   - `_swapState()` hides/shows the wrapper, so the warning never appears in
     the success state.
   - Suggested CSS:
     `.cancel-confirm-wrap { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }`
     `.confirm-warning-compact { margin: 0; font-size: 11px; line-height: 1.2; text-align: right; }`

### Suggested files

- `resources/views/partials/ui-cancel-class-modal.blade.php`
- `resources/views/partials/ui-irreversible-line.blade.php`
- `public/js/ui-common.js`
- `public/css/theme.css`
- `tests/cancel-class.spec.ts` (drop the `#cancelClassRequired` assertion)

### Verification (after PI is done)

1. `php artisan view:cache` (Blade compiles) and `php artisan view:clear`.
2. Manual, on `/my-timetable-ui`, `/cohort-timetable`, `/venue-timetable`:
   - no “You MUST Select…” line;
   - heading has the red `*`;
   - eight reasons in two columns, one column under 520px;
   - Yes disabled → warning hidden; valid reason → warning visible above Yes;
   - “Other” blank → warning hides; filled → shows;
   - success state → warning gone;
   - server-refusal hint still shows.
3. Update the Playwright spec only; do not run it until the user asks.
