# Tasks — responsive modal width

> **Change:** `modal-responsive-width`
> **Apply status:** **FROZEN — NOT STARTED; live apply gated on theme.css ownership**
> Prototype trial: `ui-prototype/my-timetable-mobile-preview.html` (applied,
> measured, user approved "yea good").
> Frozen inputs: `proposal.md`, `design.md`,
> `specs/modal-responsive/spec.md`.

## Apply gate

- [ ] T1. Confirm explicit approval to touch **live** `theme.css` (0.25h)
  - The user's standing instruction is "do not edit my system code file
    first". The prototype is done; live apply waits for a separate word.
  - Record the apply-time HEAD in `review-log.md`.
  - Re-read the three frozen artifacts; do not change decisions during apply.
  - Confirm no other lane is mid-apply on `theme.css`
    (`cancelled-slot-reuse` A19 claims lane styles there).

> **Atomic batch:** T2 → T4 lands in one pass. A partial apply leaves the sheet
> docked at 768 with a stacked footer and no width rule, which is worse than
> today.

## Shared CSS

- [ ] T2. Width + breakpoint (1h)
  - **Placement is load-bearing:** append the new `@media (min-width: 601px)`
    block **after line 4071**, where the shipped `@media (max-width: 768px)`
    phone block ends. Placed near the base `.modal`, the later phone block
    overrides it at 601–768 (see design §1a).
  - Add `@media (min-width: 601px)`: `width: fit-content; min-width: 420px`.
  - Re-declare the four ceilings inside that block (detail 680, cancel 588,
    guide 520, summary 420).
  - Add the **dialog restoration** declarations for 601–768: `margin: auto`,
    symmetric radius, bottom border, `85dvh` cap, `padding-bottom: 0`,
    overlay `align-items: center`, header/body/footer padding and the
    one-row footer with non-stretched wrappers.
  - Move the sheet geometry from `max-width: 768px` to `max-width: 600px`.
  - Keep the unordered `<=768px` phone rules (48px targets, body padding) as
    they are.

- [ ] T3. Footer + dock + close target (0.75h)
  - Sheet footer: `align-items: stretch`; both wrappers
    `flex-direction: column; align-items: stretch; width: 100%`.
  - Add to the full-width set: `btn-close-modal`, `btn-outline`, `btn-danger`,
    `btn-action`, `btn-replace-now`, **`btn-confirm`**, **`btn-primary`**,
    **bare `.btn`** (review R1.2).
  - Stretch `.cancel-confirm-wrap` too — its `align-items: flex-end` blocks
    the destructive button (review R1.4).
  - `.modal-overlay { padding: 0 }` in the sheet range.
  - `.modal-close` 48×48 in the sheet range.
  - Keep `env(safe-area-inset-bottom)` on `.modal`.

- [ ] T3b. Reconcile `#confirmModal` on the arrangement page (0.5h)
  - `replacement-arrangement.blade.php` lines 1718–1760 set a page-local
    `overflow-y: auto` + sticky header under `body.arrangement-mobile-mode`.
    Convert it to the same three-region pinning (design §11 option B) so one
    scrolling model exists, or record the chosen exception in the spec.

- [ ] T4. Pinned regions (0.5h)
  - `.modal { display:flex; flex-direction:column; overflow:hidden }`.
  - Header and footer `flex: 0 0 auto`.
  - Body `flex: 1 1 auto; min-height: 0; overflow-y: auto;
    overscroll-behavior: contain`.
  - **Do not** remove `max-height` from `.modal`.

## Tests

- [ ] T5. Rewrite `tests/Feature/ModalConsistencyTest.php` (0.5h)
  - Replace the `max-width: 680px` regex with the bounded invariant: floor
    420, ceiling 680, `fit-content` present.
  - Keep the "one ceiling, not two" intent for the other shells.
  - Do **not** delete the test.

- [ ] T6. Add `tests/modal-responsive-width.spec.ts` (1.5h)
  - Widths 390 / 600 / 601 / 700 / 768 / 1024 / 1360.
  - R1: dialog narrower than 680 and >= 420; no horizontal clipping.
  - R2: sheet at 390/600, dialog at 601/700/768; radius symmetry.
  - R3: at 390 Close width = Cancel width and both fill the sheet.
  - R4: side gap 0 and bottom gap 0 at 390/600.
  - R5: × is 48×48 at 390/600; smaller at 768/1360.
  - R6: 390×420 + injected filler → body scrolls, header/footer pinned.
  - R1: guide stays at its ceiling (paragraph body does not shrink).

- [ ] T7. Run the existing modal suites (1h)
  - `cancel-class`, `class-modal-affordances`, `session-arrival-pin-guard`,
    `venue-db`, `request-approval`, `replacement-home-qv` and the
    confirmation-dialog specs.
  - Testing lane only: `CRS_MODE=testing CRS_ALLOW_SQL=1
    CRS_BASE=http://127.0.0.1:8030`.
  - Record pre-existing failures honestly; do not relax guards.

## Close-out

- [ ] T8. Changelog + supersession note (0.5h)
  - One shared `page-changelogs/` entry listing the affected pop-ups.
  - State that **680px is now a ceiling, not a fixed width**.
  - Note the `cancelled-slot-reuse` A17 correction; do **not** edit that
    lane's frozen files.
  - `php artisan view:cache`, `git diff --check`.

- [ ] T9. Verify (0.75h)
  - Full spec + the suites in T7 on the testing lane.
  - Manual look at 390 / 600 / 601 / 700 / 768 / 1024 / 1360.
  - Confirm no pop-up is wider than before.
  - Record results in `review-log.md`.

- [ ] T10. Archive (0.25h)
  - Only after verify passes and the user approves.

## Prototype evidence already collected

Applied to `ui-prototype/my-timetable-mobile-preview.html` and measured:

- Details 680 → **420** at 1360/1100/1000/900/769.
- Per tab at 1360: Class Information 420, Schedule 420, Status 478.
- Per variant at 1360: cancel 540, guide 520, summary 420.
- 390: sheet 390 wide, side gap 0, bottom gap 0, × 48×48,
  Close 356 / Cancel 356.
- 600: sheet 600 wide, side gap 0, bottom gap 0, buttons 566 / 566.
- 601/700/768: dialog, symmetric radius, one-row footer.
- 390×420 with filler: body scrolls, header and footer pinned.
