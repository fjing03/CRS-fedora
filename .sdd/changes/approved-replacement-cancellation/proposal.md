# Proposal — cancel the actual approved replacement

## Why
The shared cancellation modal offers Cancel Class for an approved replacement, but sends its recurring source identity as though the clicked landing were a recurring occurrence. Source exception filtering drops it; a mixed selection can save only another class while success claims both. Request 19 in the user's manual lane demonstrated this mismatch. The blue timetable and Approved history are honest reflections of the unchanged request, not stale colours.

## Scope
1. Carry the opened Approved request ID separately from normal session IDs and pending-withdrawal IDs; bind it to the displayed landing week/day, while retaining source session/week for history and arranging again.
2. Reuse the existing class batch, complete occurrence mutation lock union and strict attached-landing release. In one transaction validate every selected target, close the Approved request, OCC-release its entire proven occupied span and its own links, audit reason/detail plus source/landing/release facts, and fan out notifications after outer commit. Preserve original exception/reason and any unrelated booking. Apply the cancellation window to the actual landing date, not an older source date.
3. Reject any stale/foreign/invalid selected target instead of filtering it away and succeeding on the remainder. Preserve request-only pending withdrawal and legacy whole-day callers without an accidental hidden day cancel.
4. Render success count and schedule list from saved server receipts, never ticks plus one. Approved cancellation bypasses the recurring client ledger; Arrange Now uses source week, and every success dismissal refreshes server truth. Existing UI layout, confirmation, date formats and palette stay intact.
5. Verify existing projection: inactive landing no longer appears on My/Cohort/Student/Venue timetables, source cancellation is visible again under the exact own-Approved policy, and My Replacements returns to Need to Arrange with no stale requested schedule. The request/audit record remains Cancelled; do not invent a new history badge or second cancelled landing block.
6. Cover single/mixed, same-week/cross-week/day/room, partial-span overlap, legacy full-span fallback, negative/rollback/retry/privacy and receipt honesty. Backend writes run only on a separately verified PostgreSQL instance. Read-only browser checks can intercept mutation calls for UI contracts, clearly distinguished from real database lifecycle checks.

## Non-goals
No manual record repair, schema migration, new dependencies, cleanup/reseed, cron change, new status or source-exception rewrite. Existing approved make-up notes remain non-tickable; no new bulk cancellation policy. Preserve pending withdrawal, eligibility, Sunday/holiday/notice rules, OCC/FCFS, unrelated history and all other sessions' work. No commit, push, archive or whole cancelled-slot-reuse acceptance claim.

## Impact
`app/Actions/CancelClassBatch.php`, `app/Concerns/CancelsClasses.php`, shared `public/js/ui-common.js`; focused Feature/browser regression files and affected page changelogs. Existing shared request mapper/release/lock and history/timetable renderers are reused, not redesigned. A helper may be extracted for cancellation eligibility or receipt formatting only if needed to avoid duplicate logic. No business logic in Blade, no CSS change required.

## Acceptance
The selected approved request really becomes Cancelled, exactly its proven full landing span is released once, source identity/history remain intact, and a mixed selection is atomic. A refused selection reports failure with zero business mutations/notifications. Success names/counts only saved targets. Existing source-based arrangement/history and all four timetable projections remain consistent. Manual :8030 data and live scheduler remain untouched and :8030 remains available.
