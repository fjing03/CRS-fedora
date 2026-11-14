# Verification — approved replacement cancellation

2026-11-14 · scoped **PASS** · user approved fix and tests, not manual repair, commit, push or archive.

## What was fixed

An opened Approved replacement is now cancelled by its exact request ID and actual landing week/day. Its source session/week stays separate. Single and mixed decisions reuse the shared batch, mutation locks and strict full-span release. Every selected target must resolve; a late failure rolls back the entire decision.

The modal lists and counts saved server receipts, not ticks plus an assumed primary class. Approved cancellation does not write a recurring client cancellation ledger. Arrange Now uses the source week, and successful dismissal refreshes server state. Request-linked notices describe the actual landing, not the original class schedule or its old private reason.

No production timetable/history reader, CSS, schema, status or release policy was redesigned. Persisting Cancelled naturally removes the blue landing, restores the source cancellation and returns the original My Replacements row to **Need to Arrange**. The student row is **Awaiting Arrangement**. No second red landing block is added.

## Executed evidence

Receipts are retained in `/tmp/crs-approved-cancel/`.

| Check | Result | Receipt |
|---|---|---|
| Saved-original related backend baseline | 152/152; 1,845 assertions | `related-before.txt` |
| Current new tests against saved original classes | 26 tests: 10 pass, 15 assertion failures, 1 receipt-shape error | `baseline-final.txt` |
| Final integrated backend, 13 files | **226/226; 2,820 assertions**; 173.243s | `backend-final-r2.txt` |
| Current new regression file after the red run | **26/26; 724 assertions**; 6.046s | `green-after-baseline.txt` |
| Final read-only browser suite, 3 specs | **32/32**, including 3 authentication setups; 43.8s | `browser-guarded-final.txt` |
| Lint check | PASS, exit0 | `lint-check-final.txt` |
| PHPStan, memory limit1G | PASS, zero errors | `phpstan-final.txt` |
| PHP/JS syntax, Blade compilation, whitespace | PASS | `syntax.txt`, `views.txt`, `whitespace.txt` |
| Independent read-only implementation review | PASS, no blockers | `implementation-review.jsonl` |

The saved-original run autoloaded the pre-fix batch, concern and notification classes from outside the repository; live files were never reverted. Its receipt-shape error is the old approved-only call returning a normal-class receipt without `request_id`, not a passing regression. Earlier helper-name, clock, grid, date and source-room/landing-room fixture mistakes were corrected before these final runs; those initial runs are not counted as valid baseline evidence.

One older integrated test assumed the first seeded lecturer owned a three-cell class. It now selects an eligible seeded class deterministically, then derives its real owner. All newer-holder preservation, release, version and ownership assertions remain intact. The initial integrated run was216/217; the final226/226 includes the corrected fixture and nine mock-clock bridge checks.

## Requirement coverage

| Requirement | Implementation and checks |
|---|---|
| R1: distinct source/landing identity | `CancelsClasses::cancelApprovedLanding`, `CancelClassBatch::approvedRefusalFor`, and the shared modal carry exact request + landing coordinates. Real tests cover the reported13:00–14:00 landing overlapping its13:30–14:30 source, cross-week/day/room, a source genuinely outside the window, stale landings and foreign/state/semester/coordinate refusals. Browser assertions verify the separate argument lists, source-week navigation and same-session other-week scope. |
| R2: atomic selection and safe release | Existing `OccurrenceMutationLocks` and `ReleaseRequestSlots` are reused. Tests check1/2/4 cells, stored-span legacy fallback, cleared ownership, one version increment, retained span/approver/source exception, exact audit evidence, release reuse, competing holders, invalid selections, duplicate IDs and retry. A real late normal-audit exception after approved writes proves full rollback and no fan-out; a separate later-target refusal proves preflight atomicity. |
| R3: honest client receipt | Server display facts feed single/day success. Browser tests cover mixed targets, tutorial-only defensive partial receipts, missing/wrong approved receipts, no approved ledger/MockData mutation, inline refusal, stale async responses and successful dismissal refresh. Normal single ledger behavior is preserved. |
| R4: all-view/history/notification consistency | A real isolated cancellation is read through My/Cohort/Student/Venue timetables, with source-room and landing-room scoping kept distinct. Owner/student history, blank requested schedule, source history, full-span notice body/tip, missing-span omission and private-detail boundaries are asserted. No new production reader or badge logic was needed. |
| R5: safety and quality | Forced and queried isolated connection; wrong-target refusal before writes; intercepted browser mutation transport; static checks, independent review, manual fingerprints, unrelated-work preservation and temporary-cluster shutdown are recorded below. |

## Database and working-tree protection

- Backend writes used only `class_replacement_testing` on `/tmp/crs-approved-suppression/socket`, port55439, data directory `/tmp/crs-approved-suppression/pgdata`. The out-of-repo bootstrap/XML forces these values and queries the full identity before tests. The wrong-port configuration refused with exit2 before writing (`wrong-target.txt`).
- The manual server uses the different5432 cluster at `/var/lib/postgres/data`. Its name is also `class_replacement_testing`; name-only checks were not treated as isolation.
- A verified manual backup was retained; no manual restore/reset/reseed, record repair or writing browser fixture ran. Browser component tests use the served JS, copied static modal shell and local fixtures with a stubbed Livewire bridge; Every non-GET/HEAD request in component cases is aborted, including hashed Livewire paths and notification-read endpoints; the bridge remains locally stubbed. Backend tests, not those stubs, prove database persistence.
- Before/after hashes for requests, exceptions, junctions and slots match. Audit count13 and notification count93 also match (`manual-preservation.json`). An offline backup-to-current full-row comparison further proves all13audit rows and all notification content unchanged, except notification89's `read_at` and `updated_at` (`audit-notification-preservation.json`, `notification-field-changes.json`). The server logged a `/notifications/read` request at14:38:39, between our14:34:52 and14:42:30 browser runs. No actor is inferred and that read state was not undone. Counts alone are not presented as full notification equality. Manual request19 remains Approved intentionally: it was not silently repaired and must be cancelled again through the fixed flow.
- All38 unrelated tracked file/deletion fingerprints are unchanged. Five shared changelog bodies retain their exact prior bytes beneath the new dated entry; the shared JS prefix, including the older planner work, is unchanged (`preservation-final.json`). The index remains empty. The previously clean notification changelog also receives only a scoped entry.
- The temporary PostgreSQL identity was queried again before stopping **only that cluster** (`shutdown-target.txt`, `shutdown.txt`, exit0). Manual8030 remains HTTP200, serves the exact current JS, and already runs with OPcache timestamp validation/revalidation0; no restart or scheduler change was needed (`server-final.txt`, `served-js.sha256`). Live cron was left alone.

## Limits and remaining gates

- After spotting the notification read-state difference, main also found the original transport backstop matched only `/livewire/update`, not the actual hashed route. It was hardened to abort every non-GET/HEAD request in component cases and all32browser checks passed again. Local stub calls had already prevented cancellation transport; there was no browser test running at the recorded notification-read time.
- Browser checks are read-only component/layout contracts, not a writing end-to-end cancellation on the user's records. Four synthetic1440/390 light/dark screenshots were captured; main inspected desktop-light and phone-dark. They do not certify all real-page mobile modal behavior. No CSS/layout change is included.
- Notification tests check persisted notification content, fan-out and tooltip facts, not external mail delivery.
- This is scoped verification of this cancellation defect, not a new full-repository suite claim or whole `cancelled-slot-reuse` Verify. Earlier full-suite results are separate evidence.
- Existing manual records, cleanup, broader acceptance, archive, commit and push remain separate decisions.

## Verdict

**PASS —7/7 tasks complete, R1–R5 covered, no scoped implementation blocker.** Ready for a separate archive decision; not archived or committed.
