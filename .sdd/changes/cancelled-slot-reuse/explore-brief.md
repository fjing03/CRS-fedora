# Cancelled slot reuse — decision record

Date: 2026-11-13 (discussion began 2026-11-12).
Mode: proposal only. The user said “write it but don't execute it first”, then approved including the hover task and said “proceed”. This is NOT approval to implement or change data.

## 2026-11-14 — approved lifecycle refinement (supersedes approval-retention choice)

User screenshot `/tmp/pi-clipboard-5466a774-0aa6-467e-99e1-c9811bdd3faf.png` shows staff 5770's cancelled 13:30–14:30 original above its approved 13:00–14:00 replacement. User explicitly said the cancelled block should disappear after approval, then approved updating/fixing/testing this rule across My Timetable, Cohort, Student and Venue timetables.

Current ruling: pending keeps the original cancelled block and the pending landing; an approved replacement for the SAME semester/session/source-week occurrence hides that original on all four timetable surfaces, regardless of exact/partial/no overlap, other active reuse, landing week or selected venue. Approval of ANOTHER source occurrence must not hide this cancellation. Unhandled originals and withdrawal/rejection/automatic-rejection cases still show cancellation. Keep cancellation exceptions, reasons, audits and replacement-history records; this is server-side display filtering only, not deletion or release. Existing true-span/identity/privacy/availability rules remain. Summary changes follow the existing data formulas naturally; no client-side hiding or count patch.

This reopens proposal and all downstream planning batches for real sequential review before implementation; it does not reopen demo cleanup, commit, push or archive authority. Protect the user's manual testing DB and leave :8030 running. Backend tests may use an isolated temporary PostgreSQL instance with the same literal testing database name, verified socket/port/data_directory, never the user's active cluster. No new dependency or application configuration change.

## Evidence supplied by the user

- `ui-prototype/cancelled class.png`: staff 5425's classes on Thu, 19 Nov 2026, Week 9: AMSE2003(L), 09:00–11:00, B100; AMSE2003(T), 11:00–12:00, B100; BMSE2163(P), 12:00–13:00, B010. All are cancelled.
- `ui-prototype/all free.png`: AMSE1003(L) arrangement, DSF1 cohort, Week 9, venue B111. The Thursday morning cells are green.
- These are DIFFERENT rooms. The user has not observed a real same-room double booking. Their concern is how a cancelled block and a new Pending block at the same date/time/room will be displayed.

## Final choices (supersede earlier choices in this conversation)

| # | User choice |
|---|---|
| 1 | Do not treat cancellation as a full-day staff-unavailability ban. Only each cancelled occurrence's own interval is affected. |
| 2 | Release room, lecturer and cohort time for that occurrence only; other weeks are untouched. |
| 3 | Keep both cancelled and new booking blocks, visibly separated, on all timetable pages, including student views, within each viewer's allowed scope. |
| 4 | Stack overlapping blocks vertically; grow the day's row as needed. Cancelled above the new booking. |
| 5 | Allow the same cancelled class to request its original date, time and venue again, subject to the normal checks and approval process. |
| 6 | Apply to single-class and whole-day cancellation alike. Existing request closure/release behaviour stays. |
| 7 | Include existing cancellations in a separately approved one-off cleanup of `class_replacement_fresh` (:8000) only. Do not touch the viva/older demo copies. |
| 8 | Freed arrangement cells look plain green, with no new “freed by cancellation” note, provided every other check passes. |
| 9 | Include the parked room-only conflict hover task as a separate part of this proposal, intended for a separate implementation commit. |

Earlier “keep the room blocked” and proposed lecturer-busy restrictions are SUPERSEDED. The user first picked interval-only blocking while the concern was misunderstood; their later explicit choice releases all three resources and is authoritative.

## Hover task reconciliation

Source: `prompts/for-ai-to-read/my to-do list.md`, Thursday 12 November, Small tasks, item 1.
The task is to name the real holder of a blocked room-only cell rather than leave its hover empty. Its old example named a cancelled class, and its “stays blocked” wording assumed the old rule. Under the new rule a cancelled occurrence is NOT an active holder. The hover part does not change booking eligibility: it explains remaining active room conflicts only. Freed cells stay plain green. Do not rewrite or tick the user's task list in this proposal stage.

## Expected module boundaries for later design

- Cancellation action → original occurrence slot release plus existing request closure, within one transaction; notifications after commit.
- Shared span/availability readers → one consistent booking answer in frontend and backend.
- Timetable event payloads → keep cancellation history independently of stored room occupancy.
- Shared grid layout → retain every relevant event instead of overwriting overlaps; venue page's custom renderer must follow the same contract.
- Arrangement holder payload → truthful room-only conflict hover without depending on the viewer sharing the lecturer/cohort.
- Cleanup → dry-run inventory, rehearsal in testing, verified backup, exact target printed, separate user approval, scoped write, before/after receipt, safe rollback.

## Not decided here

Detailed layout algorithm, exact hover copy, file-by-file task list and cleanup command are later design/spec work. No prototype, app code, tests, services, database records, commits or pushes are authorised by this proposal-writing request.
