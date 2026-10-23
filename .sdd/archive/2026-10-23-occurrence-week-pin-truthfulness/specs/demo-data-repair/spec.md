# Spec — demo-data-repair (Route C, USER-RUN ONLY)

## Requirements
- The demo DB `class_replacement_fresh` is NEVER written by application
  code at ANY step of this change.
- The repair SQL lives verbatim in the run-file §8
  (`prompts/run-occurrence-week-pin-truthfulness.md`) and runs ONLY:
  (a) after the user's pgAdmin pre-flight exports (reqs, request slots,
  the W5 time-slot slice, class_exceptions, the two requests' audits); and
  (b) after the user says "repair approved".
- The ONE transaction (order is load-bearing):
  1. `audit_logs NULL-ing` (replacement_request_id for 11/12),
  2. `replacement_request_slots` delete (11,12),
  3. `replacement_requests` delete (11,12),
  4. `time_slots` 10693–96 → `available`, version +1,
  5. `time_slots` 10623–26 → `occupied`, version +1,
  6. `class_exceptions` delete `(67, 5)` — the bogus stamp.
- NO `(67,14)` stamp insert in the script (the holiday row IS the true
  fact; the redo live approve stamps it).
- Render consumers key `week_number` themselves — the repair's truth is
  the renders' contract; no render code edits.

## Post-repair verification (read-only SQL + the fixed-UI redo)
- requests 11/12 gone; audits nulled; 10623–26 occupied; 10693–96
  available; the exceptions list holds ONLY `(94, 14)`.
- The user REDOES live through the fixed UI:
  my-timetable → class #67 → Replace Now → `?session=67&week=14` (the
  stamp's week) → strict validation passes (the holiday fact) → submit →
  pin=14 lands; the PL approve stamps `(67, 14)` and frees NOTHING
  (no W14 rows exist). #68/#12 redo optional per the user.
