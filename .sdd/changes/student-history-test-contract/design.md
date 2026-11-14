# Design — test-only occurrence contract repair

Frozen proposal: proposal.md (independent PASS). Authority: archived student-class-replacements/design.md §§1a–3 and current approved component; no production rewrite.

## Change
Rename only the obsolete mock-shape test to describe own-cohort occurrences. Wrap its complete fixture and assertions in Carbon::withTestNow with 2026-10-01 09:00:00, restored even on exception. Semester starts 2026-08-31; keep current fixture helpers and their existing sequence setup unchanged (do not add resets).

Keep own pending source week 7, own approved source week 8, other cohort approved week 7. Independently expected identities: sessionA.id:7 and sessionA.id:8; requestId links to each actual request. Assert exact two-row identities in chronological order, distinct from request identity, and absence of the captured foreign request id/module/session.

## Exact contracts
- Preserve empty cohortTimetable.rsd3g2Flags, BMIT1001 code, Lecturer A, B100 and original two-hour duration.
- Original data lives in orig: week 7/8 (1-based), dates 2026-10-13 / 2026-10-20, Tuesday, start 10:00, end 12:00, duration 2, venue B100. Top-level week remains 6/7 (0-based).
- Pending: status pending, statusShort Pending, requestId correct; req is present (a proposal, not a confirmed replacement) with week 7, date 2026-10-15, Thursday, 14:00 to 14:30, B100.
- Approved: status approved, statusShort Approved, requestId correct; req week 8, date 2026-10-23, Friday, 14:00 to 14:30, B100. Fixed time ensures not completed. Do not assert retired replacement status, newDay/newTime/originalTime flat keys or formatted prose for machine-time fields.
- Allowed student conflictKind Class Cancelled; no raw annual_leave/Annual Leave or foreign BMIT2002 module in serialized rows. Keep standalone privacy/read-only tests untouched.
- Assert data against explicit fixture-derived expectations, not a copied runtime payload or permissive defaults; do not constrain unrelated timestamps/deadline strings or whole-hour rounding of half-hour req duration.

## Approved second method — deterministic date boundary
User approved this extension after the first repair and original-HEAD reproduction. Change only test_merged_page_keeps_today_and_future_approved_requests_as_approved in addition to the already repaired occurrence method. Wrap its entire existing setup and assertions in Carbon::withTestNow('2026-11-12 09:00:00', callback). Thu 12 Nov and Fri 13 Nov are both inside the Aug31–Dec6 fixture semester: week11 day3/day4. Add explicit assertions for those two cell coordinates so a wrong clock/date cannot silently pass. At this instant UTC and campus dates are both Nov12; 09:00 UTC is 17:00 MYT, so the same-day 16:00 class is already past by the campus clock but still approved for that date. Keep original due/later request creation, source exceptions, and both approved-status assertions. Do not alter helpers, schema, the first repair, sibling cases or global clocks. Callback restores even on exceptions. No Sunday workaround/offers or production changes.

## Approved final two date fixtures
Only acceptance7's two methods may additionally change. Wrap each entire method body in Carbon::withTestNow callback, retaining all old setup/status/label/day-count/no-write assertions, with additive independent geometry guards.

- MyReplacementsPayloadTest::test_completed_is_derived_and_the_boundary_is_today: semester was created in setUp relative to actual date, so freeze at $this->startDate->copy()->addWeeks(3)->addDays(3)->setTime(9, 0). startDate is Monday UTC, making frozen today Thursday/week4/day3 at09UTC (17MYT, same date). Yesterday Wed/week4/day2 and tomorrow Fri/week4/day4 are legitimate cells. Assert these exact three coordinate pairs. Can capture the full today pair instead of only its week for the additive check; keep original todaySession construction and every existing assertion. Never mutate startDate or move the semester/setup/global timezone. No literal2026 date here: it would eventually precede the dynamically created semester.
- ReplacementHomeTest::test_home_reads_the_effective_status_per_the_date_matrix: existing fixed2026Aug31 semester; freeze callback at literal2026-11-12 09:00:00, as the sibling repair. Assert yesterday/today/tomorrow exactly week11/day2,day3,day4. Keep exceptions/source weeks5,6,7, cases/expected statuses, component payload assertions and stored-approved-count3 no-write assertion unchanged. Do not touch helpers or setUp sequence logic.

UTC/campus date agree in both callbacks; 09UTC=17MYT, after14:00 slot start, so today must remain approved even after its time of day. Callback restores on exceptions. First two repairs unchanged. Main runs isolated two methods, three complete files, lint/PHPStan, independent review and full suite only with enough time left. Report/stop for anything else. No schema/Sunday offerings/app edits.

## Verification / safety
Main verifies PHP current_database is exactly class_replacement_testing before fixture writes and runs literal DB_DATABASE=class_replacement_testing commands serially, no browser fixtures/server. Record failing baseline, fixed single method, all ReplacementHistoriesTest cases and relevant student payload/mapper privacy regressions, lint/PHPStan. Reviewer is read-only; coding delegate edits only the currently approved method/comment (second extension: boundary method only). No UI/code/DB baseline changes, dependencies, commit/push or final cancelled-slot-reuse Verify. Fail closed and report if test repair exposes a product mismatch.
