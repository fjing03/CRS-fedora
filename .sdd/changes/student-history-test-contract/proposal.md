# Proposal — repair the student history regression test

## Why
The old mock-shape test in tests/Feature/ReplacementHistoriesTest.php indexes a current occurrence-keyed payload by request id. Later assertions also describe retired flat fields/status words. Align this one regression test with the approved student-class-replacements contract, without weakening own-cohort privacy or changing the app.

## Scope and acceptance
1. Reproduce the exact failure on the disposable testing DB, then check canonical archived design against current component.
2. Keep two own-cohort conflicted occurrences (pending, approved) and a foreign-cohort request. Assert exactly the two own occurrence identities plus correct request associations; foreign data never appears.
3. Assert source week/date/day/time/room and actual requested geometry via orig/req, pending versus approved status and allowed public facts. Fix the clock locally so the approved fixture cannot age into completed. Pending req is a proposal, not an approved replacement.
4. Keep meaningful original module/lecturer/venue checks and strengthen explicit identity/privacy assertions. No skipped case, permissive fallback, production edit or global fixture rewrite.
5. Run focused test, whole test file and relevant student payload/privacy tests, lint, PHPStan and independent read-only review. Report full-suite status honestly; a focused pass does not mean full-suite green.

6. Later user approval also covers the unchanged sibling test_merged_page_keeps_today_and_future_approved_requests_as_approved's Saturday/Sunday fixture error: freeze only that method's clock to an in-semester Thursday so today/tomorrow are legitimate teaching days, retain both approved-status assertions and all helpers/schema/product rules. Re-run both repaired methods, entire file and related checks; full-suite run if the remaining timebox permits, reporting any unrelated failures without expanding scope.

7. User subsequently approves ONLY the remaining full-run date fixtures: MyReplacementsPayloadTest::test_completed_is_derived_and_the_boundary_is_today and ReplacementHomeTest::test_home_reads_the_effective_status_per_the_date_matrix. Repair each method-local clock so yesterday/today/tomorrow are valid in-semester Wed/Thu/Fri. Preserve every status, day-count and no-write assertion; earlier repairs/helpers/schema/app remain unchanged. The payload fixture has a relative semester: derive its frozen Thursday from that existing semester, not a calendar literal that can age out. Verify both complete files and earlier repaired file, then full suite within remaining timebox. No further scope expansion.

## Gates
User approved this limited repair within a 60-minute timebox, then explicitly approved the second date-setup repair with the same quality-over-speed instruction. The latest explicit yes also approves the two remaining named date fixtures, and nothing else. Stop/report if production changes are needed or scope grows. No demo access/write, baseline reseed, new dependency, commit/push or completion of cancelled-slot-reuse Verify. Existing unrelated dirty work must remain unchanged. Separate new spec file unnecessary: test-only single-file repair, existing archived student page contract is authority.
