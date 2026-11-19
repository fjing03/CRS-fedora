# Decided request countdown parity

Status: applied and verified locally. Owner approved the countdown mismatch only. The independent reviewer returned HTTP 429; the owner explicitly waived independent review. Ordering, tablet layout, empty-queue copy and contrast are out of scope.

## Decision

- **Student Class Replacements:** use one page-local predicate for showing the original-slot countdown in the desktop row, phone card and Original details tab. Approved and Completed retain the original schedule but omit its countdown, as the existing details tab already does. All other original-slot countdown conditions remain unchanged. The requested-slot countdown stays Pending-only.
- **Request Approval:** the phone card's requested-slot countdown is Pending-only, matching the desktop column and details. Keep the date, week, time, room and slot-validity marker for decided rows. Keep the independent request-age signal, order, decision controls, and all calendar calculations intact.

## Checks and tasks

- [x] Reproduce both failures with real served data: the student's approved BMIT2013 row/card says Overdue but its Original details do not; Request Approval's decided cards show countdowns after clearing the default Pending filter, while the desktop requested slot does not.
- [x] Implement the two presentation guards with no server, data, or threshold changes. Add a focused read-only Chromium receipt covering both 1440 mouse and 390 touch, including an open/awaiting student record that keeps its countdown. If the database has no approval Pending row, validate the positive arm with a labelled browser-only fixture, without submitting any actions or writing database content. Baseline receipt: `/tmp/crs-decided-countdown/before.txt`.
- [x] Verify unaffected schedule and age facts, inspect rendered phone cards in both themes, run PHP lint/static checks and record dated page changelog entries. Stage only the two scoped fixes, receipts and docs; preserve the other session's Request Approval hunks.

## Verification

The read-only Chromium receipt passed all **7 checks** including auth setup (three feature checks). The student approved row/card and Original tab agree; Awaiting Arrangement keeps the countdown. Approval's live Approved, Rejected and Completed records keep their slot facts and request age without countdowns. The positive Pending card used a detached browser-only render of a served schedule; no live Pending record was available. Phone captures in both themes and receipts are in `/tmp/crs-decided-countdown/`. Pint and PHPStan passed. No database content changed.
