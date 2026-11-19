# Light-mode Summary Card label contrast (C7)

Status: applied and verified; owner approved C7 scope. The quote lane shipped separately, and neither `public/css/theme.css` nor the shared Summary Card partial has staged or unstaged overlap. The owner explicitly waived independent review for C7 after the reviewer timed out without a verdict.

## Scope and acceptance

- Change only the front label on the tinted `card-total` variant of the shared Summary Cards, in light mode. Use the existing neutral `--color-on-surface` token instead of `--color-on-surface-variant` on that fill. Keep other labels, dark mode, card values and suffixes, sizes, fills, copy, gestures and counts unchanged. No Blade/JS or business data changes.
- Confirm at least 4.5:1 label contrast on actual rendered card fills at 1440px mouse and 390px genuine touch across all seven pages; ensure non-target labels do not change. Include a dark-mode isolation spot check and inspect rendered samples. No new dependencies.

## Baseline and candidate

Measured 70 labels across seven pages × two viewports using the protected testing server. The tinted Total labels on staff/student My Timetable, Cohort Timetable and Venue Timetable measured **3.68:1 desktop / 4.03:1 phone**. Request Approval’s **Total Requests** and **Total Reviewed** use the same `card-total` variant and ratios; only that page used the browser-only existing empty-summary shell. My Replacements and student Class Replacements already passed in both layouts (minimum 5.64:1 and 4.58:1 respectively). Other labels passed. The current palette's neutral `--color-on-surface` measured **9.74:1 desktop / 10.66:1 phone** on the affected fills in a browser-only probe. Keep the existing muted token everywhere else.

Evidence: `/tmp/crs-summary-label-baseline.jsonl`, `/tmp/crs-summary-label-baseline.cjs` and `/tmp/crs-c7-*.png`.

## Verification

Compared 140 label samples across both themes, 1440px mouse and 390px genuine tap, and all seven pages. The 12 targeted light labels reached 9.74:1 desktop / 10.66:1 touch; the other 58 light labels and all 70 dark labels were unchanged. Card fills, label sizes and number colours were unchanged. Request Approval used the existing browser-only empty shell. Inspected rendered captures; no database changes. Evidence: `/tmp/crs-summary-label/`.
