# Approved replacement cancellation — investigation baseline

2026-11-14. User approved: “fix and test this flow”. Manual :8030 data must remain untouched.

## Proven fault
Staff 5770 selected an Approved AMCS2093(P) replacement and a normal AMCS2093(T) occurrence. Only the tutorial was cancelled. Request 19 remained Approved and its two landing cells occupied. `cancelClassDay()` intersects submitted session IDs with recurring, exception-free occurrences; the approved replacement's source already has an exception and disappears from this set. `CancelClassModal._renderDaySuccess()` invents its total from ticks plus one rather than the saved receipt. The single path also submits only session/week, losing approved-request identity. Client cancellation ledger must not map a replacement landing date onto another recurring source occurrence.

## Decisions
- Treat the opened approved replacement as a scheduled class, identified by its request ID AND the shown landing week/day. Preserve the distinct request source session/week.
- Single and selected-day cancellation share one atomic backend path. A mixed selection either cancels all specified valid targets and pending withdrawals or changes nothing. Never silently discard a selected recurring class.
- Extend existing batch/lock/release policies, not a second slot-release engine. Validate actor, active semester, Approved state, landing date, working-day window, holiday, reason/detail and full claim under the complete lock union before writes. Release only proven occupied landing cells with OCC; cancel request, delete its links, retain request span and original source exception/reason. No original release for an approved-only target.
- Existing approved make-up NOTES stay non-tickable. This fix supports the explicitly opened approved class plus currently tickable normal occurrences/pending withdrawals; it does not expand bulk selection policy.
- Return server-confirmed display facts and source identity. Count/list only those receipts. Approved cancellation never patches or persists a client-ledger cancellation of the landing/source occurrence. Arrange Now uses the request SOURCE week; Close/Escape/header dismissal refresh from server.
- Established display contract remains: inactive landing disappears, cancelled source returns under own-Approved suppression rule, My Replacements shows Need to Arrange (blank requested slot, no Proposed tab); record/audit remains Cancelled. No new badge, second cancelled landing block or history-row duplication.
- Audit approved cancellation as a request cancellation with source + actual landing snapshot/release evidence and reason/detail, not fabricated evidence that the original source was cancelled again. Notify affected users after outer commit using actual landing facts; student privacy stays intact.

## Rejected
CSS hiding; rewriting source exceptions; treating landing week as source week; cancelling all classes when selected IDs resolve to none; accepting partial success; fabricating success rows from UI ticks; creating new history/status concepts; writing fixtures against manual :8030.

## Tests and safety
Use isolated PostgreSQL socket /tmp/crs-approved-suppression/socket port 55439, database class_replacement_testing, data_directory /tmp/crs-approved-suppression/pgdata; force/query all identity fields before refresh tests. No writes, resets or writing browser specs on manual :8030. Browser JS may be tested read-only with intercepted mutation responses, labelled as such; real persistence/lifecycle covered by isolated backend tests. Preserve unrelated working tree, live cron and manual server. No commit/push/archive or data repair requested.
