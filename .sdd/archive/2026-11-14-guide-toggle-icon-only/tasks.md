# Tasks — guide toggle as one icon

> Change: `guide-toggle-icon-only`
> Apply status: **APPLIED — verified** · apply-time HEAD `d2d91ae`
> Frozen inputs: `proposal.md`, `design.md`, `specs/guide-icon/spec.md`
> User approval: explicit "proceed … then apply it" (2026-11-12)

## Apply gate

- [x] T1. Confirm explicit user approval before any live edit (0.25h)
  - Do not edit partials, CSS, pages or tests until the user says apply.
  - Confirm no other lane is mid-apply, then record the apply-time HEAD
    (`git rev-parse --short HEAD`) in `review-log.md`.
  - Re-read the frozen artifacts; do not change decisions during apply.

> **Atomic apply batch:** T2 → T7 lands in one pass; a per-task commit would
> leave pages with the icon but no description (or vice versa).

## Partials

- [x] T2. Add the guide icon partial (0.5h)
  - Create `resources/views/partials/ui-guide-icon.blade.php`: one
    question-mark SVG, `$size` default 16, `aria-hidden="true"`.
  - Include it from `ui-page-header` at size 18 and from the legacy full
    `.guide-toggle` at size 16.

- [x] T3. Add the header icon (1h)
  - `ui-page-header`: `$guideToggle = $guideToggle ?? false`;
    `$guideLabel = $guideLabel ?? 'How to use this page'`.
  - Wrap title/chips/description in `.page-header-main`.
  - When `guideToggle`, render one `.guide-toggle-icon` button:
    `type="button"`, `aria-controls="guideBlock"`, `aria-expanded="false"`,
    `aria-label` from `guideLabel`, null-guarded inline toggle that writes
    `aria-expanded`.
  - Keep `description`/`descriptionHtml` support for non-opted callers.

- [x] T4. Add the guide-block flags (1h)
  - `ui-guide-block`: `$showToggle = $showToggle ?? true`;
    `$guideDescriptionHtml = $guideDescriptionHtml ?? null`.
  - Keep `$guideTitle`/`$guideItems` defaults.
  - When `showToggle=false`, omit the full bar but keep `#guideBlock`,
    `.guide-content` and the existing open/close class law.
  - Render `.guide-description` from `guideDescriptionHtml` before the list.
  - Render the `<ul>` only when `count($guideItems) > 0`; keep the raw
    `@foreach`/`{!! $item !!}` loop.
  - Keep the legacy `.guide-icon` wrapper and full bar `onclick`.

## CSS

- [x] T5. Header, icon and content styles (1h)
  - `.page-header` flex row; `.page-header-main` `min-width:0; flex:1 1 auto`.
  - `.guide-toggle-icon` 40px, token border/background, hover/focus-visible,
    `[aria-expanded="true"]` open tint; 44px at `<=768px`.
  - `.guide-block--content` transparent/no bar chrome, `margin:8px 0 12px`;
    `.guide-content` `padding:8px 0 12px`.
  - `.guide-description` 14px/1.5 muted, 10px bottom margin; rich HTML keeps
    `.info-keyword`.
  - Keep `.page-desc` for non-opted callers.

## Pages

- [x] T6. Opt in the six existing guide pages (1.5h)
  - Add `'guideToggle' => true` to the header.
  - Remove `descriptionHtml` from the header.
  - Add `'showToggle' => false`; move the same description to
    `'guideDescriptionHtml'`.
  - Drop the unused `guideTitle` from opted guide blocks (optional cleanup;
    harmless if left because the bar is not rendered).
  - Pages: `my-timetable`, `venue-timetable`, `cohort-timetable`,
    `replacement-home`, `replacement-arrangement` (no description; items
    only), `request-approval`.

- [x] T7. Add the two student description-only guides (1h)
  - `student-my-timetable`: header `guideToggle`; remove `descriptionHtml`;
    add a guide block immediately after the header (before
    `.semester-progress`) with `showToggle=false`, the same description, no
    items.
  - `replacement-history`: same, inserted before the toolbar; preserve the
    trailing BRIDGE comment and update the feature-test pin if the include
    becomes multi-line.
  - Keep the escaped description HTML verbatim.

## Tests

- [x] T8. Update the two named existing specs (1h)
  - `tests/venue-timetable.spec.ts`: replace the `.page-desc` read with
    `open guide → .guide-description` text.
  - `tests/Feature/StudentReplacementHistoryParityTest.php`: accept the new
    header argument; keep the page identity assertion.

- [x] T9. Add `tests/guide-toggle-icon.spec.ts` (2h)
  - Role/address map: lecturer for `/my-timetable-ui`, `/venue-timetable-ui`,
    `/cohort-timetable`, `/replacement-home-ui`, `/replacement-arrangement`;
    PL for `/request-approval-ui`; student for `/student-my-timetable-ui` and
    `/student-class-replacements`.
  - All 8 pages: one `.guide-toggle-icon`, no `.guide-toggle` bar.
  - Click opens `#guideBlock` and flips `aria-expanded`; open icon has the
    tint.
  - 320/390/768/desktop geometry; 44px mobile; no title/chip intersection;
    no clipping.
  - The 7 pages with a description expose it in `.guide-description` only
    when open; the two student pages render no `<ul>`.
  - Blade render fallback for `ui-guide-block` (one `.guide-toggle`, no icon;
    `.guide-icon`, `onclick`, 16px SVG) and `ui-page-header` (no icon, keeps
    `.page-desc`).
  - `.guide-block--content .guide-content` computed top padding ≥8px.
  - One SVG source check: `grep -rn "M9.09 9a3 3" resources/views` returns
    exactly one file (`ui-guide-icon.blade.php`).

## Close-out

- [x] T10. Changelog and static checks (0.5h)
  - Add one shared cross-page changelog entry listing all 8 pages.
  - `php -l`, `php artisan view:cache`/`clear`, `git diff --check`.
  - Restart through `scripts/dev-serve.sh`.
  - No DB/schema/booking change; no push.

- [x] T11. Verify (1h)
  - Start the disposable lane with
    `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030` and run
    with `CRS_BASE=http://127.0.0.1:8030`.
  - Run the new spec plus the named existing specs.
  - Regression set: `nav-identity.spec.ts`, `pages-parity.spec.ts`,
    `request-approval.spec.ts`, `venue-db.spec.ts`, `timetable-wiring.spec.ts`
    and `arrangement-mobile.spec.ts`; they must stay green without assertion
    edits (except the two named updates).
  - Check 320/390/768/desktop, 7 descriptions, student description-only
    guides, open/closed ARIA, non-opted Blade render.
  - Template visual check: boot with `APP_MOCK_FALLBACK=true`, confirm one
    template renders no icon, keeps `.page-desc`, and its header looks
    unchanged; waive only with a recorded reason.
  - Record results in `review-log.md`; do not relax unrelated guards.

- [x] T12. Archive (0.25h)
  - After verify passes and the user approves, archive through
    `~/.config/opencode/tools/sdd/archive-change.sh`.
  - **Done 2026-11-14** — archived to `.sdd/archive/2026-11-14-guide-toggle-icon-only/`.
    The two `venue-timetable.spec.ts` failures were re-checked first and confirmed
    unrelated (see the re-check section in `review-log.md`); TC40's earlier
    description in that log was corrected.
