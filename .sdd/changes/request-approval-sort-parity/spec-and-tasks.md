# Request Approval: same order on desktop and phone

Status: applied and verified locally. The independent reviewer returned HTTP 429; the owner explicitly waived independent review for this batch.

## Contract

For the same filters and sort state, the desktop table and phone cards render requests in the same sequence. Keep the existing desktop comparator, default oldest submission first, saved sorts, and tie behavior. Desktop still paginates the ordered list; phone still shows the complete ordered list. No new phone sorting control.

## Implementation and receipts

- [x] Sort the filtered list once in `renderTable` using existing `sortData`; pass that ordered snapshot to both renderers. Preserve `currentFiltered` for existing review navigation, selection and counts; no decision, review workflow, filtering, or persistence changes.
- [x] Reproduce the mismatched IDs on protected testing using real decided records and Clear all. Desktop: `10, 24, 26, 66`; phone: `66, 26, 24, 10` (`/tmp/crs-approval-sort/before.txt`).
- [x] Verify the default order, reversed desktop sort carried over to a 390px touch view, and a non-empty filtered subset. Check the same request opens from a card without submitting a decision, and inspect rendered results at 1440 and 390px. No business data writes.
- [x] Run static checks, record the page changelog, and commit only this change's hunks. Empty-queue copy and tablet breakpoints are out of scope.

## Verification

Default order now matches at 1440px and 390px (`10, 24, 26, 66`). The test reverses the desktop sort, restores it in a touch context, checks the filtered order and taps the first result to confirm its Request ID. The sort and previous countdown receipts passed (8 checks including auth). Pint and PHPStan passed. Captures and logs: `/tmp/crs-approval-sort/`. No decisions were submitted and no business data changed.
