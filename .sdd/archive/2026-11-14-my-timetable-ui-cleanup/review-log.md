# Review Log: my-timetable-ui-cleanup

## proposal.md Round 1 — 2026-11-13 (Batch 1: proposal only)

### 🔴 Fixed
- (none — first round)

### 🟡 Addressed
- **The four-page scope is explicit and matches the reference.** The proposal names My, Student, Cohort and Venue Timetable, matching the existing responsive prompt's "1280px+ keeps the grid, legend, summary, print" plus its §9/§10/§12 items. The user's UI Update authorises the Student/Cohort/Venue extension that §12 previously parked.
- **The summary-card change keeps every value source.** `valueId` stays on `.summary-value`; the only addition is the optional `bullets`/`explanation` pair, with a fallback to today's `description`. No calculation, payload field or `updateSummary()` path is touched.
- **The guide modal preserves the content.** The four pages' `guideDescriptionHtml` and `guideItems` move verbatim from `ui-guide-block` into `ui-guide-modal`; the partial itself stays for any other caller.
- **The semester-chrome removal is guarded.** `updateProgress()` and `updateWeekSubtitle()` already null-check `getElementById`, and their call sites stay, so removing the nodes cannot throw. Cohort never had the blocks, so it is excluded rather than "removed".
- **The week-select change is scoped.** A `selectTinted` flag adds a `week-select--tinted` class only on the four timetable pages; the replacement pages' `.week-select` keeps its current white surface. This avoids the "ALL dropdowns" reading silently restyling pages outside the brief.

### 🔴 Outstanding
- (empty — no blocking issues)

### 🟡 Remaining (non-blocking; folded into design/tasks)
- The explanation popup is one shared `#summaryExplainModal`, filled per card, rather than five per-card modals.
- `summary-section--flip` is the opt-in scope so the other summary pages keep their existing cards.
- The phone card grid follows the preview's two columns, scoped to the flip-card section.

## design / specs / tasks Round 1 — 2026-11-13 (Batches 2–4, reviewed together after the single-pass apply)

### 🔴 Fixed
- (none — first round for these batches)

### 🟡 Addressed
- **The card markup is additive, not a rewrite.** `flipCards` defaults to false, so `replacement-home`, `request-approval` and `replacement-history` render byte-identically; only the four timetable pages opt in.
- **Hover vs. tap is settled.** A pointer-only `@media (hover: hover)` rule flips on hover; `.is-flipped` is the explicit click/tap/keyboard state; `.is-unflipped` pins an unflipped card while the pointer rests on it and is released on mouseout. Verified in Chromium: click flips, click again unflips, Enter/Space flips and unflips.
- **The info button does not toggle the card.** Its click is stopped before the card handler, and the keydown handler ignores `[data-summary-info]`. Verified: the modal opens with the card's own title.
- **Focus and state are restored.** Closing the explanation re-applies `.is-flipped` to the source card and focuses its info button.
- **The guide modal is one shared partial.** It reuses the house overlay/modal/footer classes, closes on ×, Close, overlay and Escape, and restores focus to the header icon. Verified on all four pages (desktop) and on the student page (390px phone).
- **Semester chrome removal is complete and safe.** `#semesterProgress` and `#weekSubtitle` are absent from My, Student and Venue; Cohort never had them. The `updateProgress()` / `updateWeekSubtitle()` calls remain and no page error fires on a week change.
- **Week select is scoped.** `.week-select--tinted` is applied only through `ui-week-nav`'s new `selectTinted` flag on the four timetable pages; the replacement pages keep the default surface. Measured on the four pages: `week-select week-select--tinted` with the tinted background.
- **Card geometry matches the approved reference.** Desktop cards measure 96px; phone cards measure 96px in the preview's two-column grid.

### 🔴 Outstanding
- (empty — no blocking issues)

### Notes
- No test suite was run for this pass (user instruction). Verification was static (`php -l`, `node --check`, `php artisan view:cache`, `git diff --check`) plus a headless Chromium smoke pass on the four pages, the student phone view and two non-timetable summary pages.
- Files changed: `ui-summary-bar.blade.php`, `ui-guide-modal.blade.php` (new), `ui-page-header.blade.php`, `ui-week-nav.blade.php`, the four Livewire timetable views, `theme.css`, `ui-common.js`, the four page changelogs, and the prompt document.
- Not committed, not pushed, not archived.

## Scope extension Round 1 — 2026-11-13 (My Replacements + Request Approval)

User follow-up: the same summary cards and guide-icon treatment must reach
`/my-replacements` and `/request-approval-ui`, "good example is
my-timetable-ui".

### 🔴 Fixed
- (none — no blocking issue; this round extends scope)

### 🟡 Addressed
- **Board scope was too narrow.** The proposal's page table, impact table, out-of-scope row, AC1/AC3 wording, spec header and UI-01/UI-05, design §1 preamble, tasks (new T6b), dependency list and definition of done now name six pages instead of four.
- **The two pages already had the guide icon but not the modal.** Both were including `ui-guide-block` with `showToggle => false`, so their header icon still targeted `#guideBlock`. Both now include `ui-guide-modal` with the identical description/items and pass `guideModal => true`, so the icon opens `#guideModal` and the inline block is gone.
- **Card formulas preserved.** Both pages' long descriptions moved verbatim into `explanation`; the new `bullets` hold three short lines each. Every `valueId` is unchanged.
- **No accidental timetable-only changes.** The two pages intentionally keep their white `.week-select` (no `selectTinted`) and never had `#semesterProgress` / `#weekSubtitle`, so nothing was removed there.

### 🔴 Outstanding
- (empty — no blocking issues)

### Verification (headless Chromium, staff 5425)
- `/my-replacements`: 5 flip cards at 96px, 0 inline `#guideBlock`, 1 `#guideModal`, guide opens with 1 description + 5 items, explanation opens titled "Classes to Arrange".
- `/request-approval-ui`: 5 flip cards at 96px, 0 inline `#guideBlock`, 1 `#guideModal`, guide opens with 1 description + 7 items, explanation opens titled "Total Requests".
- Zero page errors on both. `php -l` clean on both views; `php artisan view:cache` succeeded.
