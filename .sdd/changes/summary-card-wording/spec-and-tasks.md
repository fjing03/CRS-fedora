# Summary Cards: wording only

Status: applied and verified locally. The independent reviewer returned HTTP 429; the owner explicitly waived independent review for this batch.

## Scope and decisions

- Seven live Summary Card surfaces opt into two scopes in `ui-summary-bar`: **Counts match the filtered list.** for My Replacements, Request Approval and Class Replacements; **Counts for the displayed week.** for the four live timetables. Unopted legacy design-template callers keep their current wording. The scoped hint says **Tap a card to see what it counts.** on `(hover: none)` and **Hover or click a card to see what it counts.** otherwise. Do not change card interaction, summary visibility or relocation.
- Venue renames only the `sumAvailable` card to **Bookable Slots**. Its back and full explanation clarify that free slots too soon to book, Sundays and public holidays do not count. Do not promise every green cell is bookable. Keep slot math, `sumAvailable`, the legend, status terminology, venue rules, card styling and contrast unchanged.

## Tasks and acceptance

- [x] Apply the scoped hint and the Venue card wording without editing prototypes or legacy design templates. Record changes in affected page changelogs; stage only this lane's hunks.
- [x] On the testing lane, verify the exact hint on all seven live callers, with pointer desktop and phone touch; check populated Venue front, back and full explanation with no clipping and unchanged count/legend. If Request Approval has no rows, verify its empty behavior and inspect the already-rendered shell without creating data.
- [x] Run the focused read-only browser receipt and lint/static checks. No database fixtures or business writes. No contrast work and no push.

## Verification

`tests/summary-card-wording.spec.ts`: **7 passed** including auth setup. All seven live callers were checked on mouse at 1440 and touch at 390. Request Approval had no rows; the receipt confirmed the hidden summary and revealed the existing shell in Chromium only to check the words. Venue's card back fits at 320 and 390; the full explanation opens and the Available legend is unchanged. The five served Venue counts and legend matched the before receipt exactly. Pint and PHPStan passed. Raw evidence: `/tmp/crs-summary-wording/`.
