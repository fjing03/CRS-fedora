# Apply log — 2026-11-14

User authorized “fix and test this flow”; no manual repair, commit/push or archive authorized.

## Before implementation
- Proposal R1 PASS, design R1/R2 PASS, specs R1 PASS, tasks R1 PASS, sequential freezes recorded in review-log.md. Source saved outside repository before edits.
- HEAD 46fd6f7. Unrelated dirty patch/status/hashes retained under `/tmp/crs-approved-cancel/`.
- Manual `class_replacement_testing` snapshot validated (`manual-before.dump` / restore list); SELECT-only business fingerprints captured. No restore/reset/reseed or business writes there.
- Separate PostgreSQL restarted with explicit port/socket. Queried target: class_replacement_testing, port55439, data_directory /tmp/crs-approved-suppression/pgdata. Out-of-repo PHPUnit bootstrap forces socket/port/database and rejects mismatches before tests. Wrong-port refusal exit2 captured in wrong-target.txt.

## Implementation
Narrow delegates owned separate backend, client, notifier and new test paths; main reviews/runs checks. Backend batch/concern carry distinct approved request and landing identities, all-selected-or-refuse behavior, strict full-span release and display receipts. Shared modal uses saved receipts and source identity without approved recurring ledger writes. Request-linked notifications use actual landing facts, not original source reason. Existing history/timetable readers remain unchanged.

Main review corrections: moved every approved span check before mutations; explicit projection relations; pending-only reason gate; exact selected-session resolution; distinguish database/runtime errors from withdrawal refusal; require exact single approved receipt; omit missing notification span rather than invent source schedule. New test fixture compile-name collision and clock-lifetime errors corrected without relaxing business assertions. Initial faulty harness runs are not counted as baseline evidence; final receipts are recorded below.

## Recorded checks
- Pre-fix saved-class related suite: 152/152, 1,845 assertions, 143.632s (related-before.txt).
- Existing read-only planner/layout browser suite: 21/21 including three auth setups, 29.4s (layout-browser.txt). No writing lifecycle browser fixture executed.
- Final saved-original new-file baseline:26 tests,10 pass,15 assertion failures and1 missing-request-ID receipt-shape error. Current implementation:26/26,724 assertions after that baseline. Live files were never reverted.
- Final integrated backend:226/226,2,820 assertions across13 files. An initial216/217 run exposed one older arbitrary-first-owner fixture; it now deterministically selects an eligible class and derives its owner, preserving all safety assertions. Nine mock-clock bridge checks were added to the final run.
- Final read-only browser:32/32 including3auth setups;11new modal contracts and18existing planner/layout cases. Synthetic responsive screenshots captured; main inspected desktop-light and phone-dark. No writing browser lifecycle ran. Final component safeguard aborts every non-GET/HEAD request, covering hashed Livewire and notification-read routes; hardened rerun32/32 in43.8s.
- Lint check/PHPStan1G/PHP+JS syntax/Blade compilation/whitespace and independent implementation review PASS. Scoped Verify PASS; full mapping and limits in verify-report.md.
- Manual request/exception/junction/slot hashes and full audit rows unchanged (request19 stillApproved); notification count/content unchanged except one concurrent read_at/updated_at change, documented without undo or actor inference.38unrelated tracked/deletion fingerprints unchanged, prior shared changelog bodies and JS planner preserved, index empty. Manual8030 HTTP200 with current served JS; no restart/cron change needed. Verified temporary cluster stopped; no archive, commit or push.
