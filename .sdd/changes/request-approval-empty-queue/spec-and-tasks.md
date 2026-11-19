# Request Approval: distinguish an empty queue from no matches

Status: applied and verified locally. The independent reviewer returned HTTP 429; the owner explicitly waived independent review for this batch.

Baseline: the live Pending view showed **No requests found** and **Showing 0 of 4 results**. Receipt: `/tmp/crs-approval-empty/before.txt`.

## Contract and tasks

- [x] In an empty **Pending** view with neither search text nor either week filter applied, say **No pending requests** and **There are no requests waiting for your review.** The Completed toggle has no effect on Pending membership.
- [x] If there are other requests, offer **View all requests** using the existing Clear all action. If the served list itself is empty, omit the action because it cannot reveal anything. Use the existing empty-state layout and button styling with the coarse-pointer touch floor. Preserve the count pill, filter chip contract, sorting, approval actions, and pagination outside this action.
- [x] For search, either week filter, or another status with no matches, keep **No requests found** and the existing message; hide the new action. A populated view never shows either empty message. Apply only to Request Approval.
- [x] Use a read-only Chromium receipt for the live empty Pending queue, narrower filters, and real touch on the action with all four decided records visible afterward. Use browser-only test data for a completely empty dataset if needed; no business data changes. Inspect rendered desktop/phone in both themes and run static checks. Stage only this lane's hunks.

## Verification

Desktop and true touch both show the new queue copy. Search and both week filters keep the generic no-match copy. The action opened all four real decided requests, including Completed; a browser-only empty payload hid the action. Inspected 1440px desktop and 390px touch in light and dark; the phone action measured 147×48px. Playwright passed all 11 checks including the earlier sort and countdown receipts and auth setup. Pint and PHPStan passed. Captures and logs: `/tmp/crs-approval-empty/`. No business data changed.
