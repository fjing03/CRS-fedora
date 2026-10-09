
## proposal Round 1 — 2026-10-09
### 🟡 Addressed (carried into design.md)
- Legacy-spec audit result to be recorded in design (ui-regression / cancel-class / e2e specs / Browser/*.py / MANUAL-TEST-CASES.md all guest-or-staff actors; no student session hits the route).
- 💡 folded into design: RBAC matrix is §6 (not §7); RouteGateMatrixTest LECTURER_ONLY = role:lecturer-or-PL semantics + L120 request-approval special case survives; NavIdentityTest absence assertion uses the href= form (match negative block L105-109).
### 🔴 Outstanding
- (none)

**Verdict: PASS** — proposal.md is FROZEN as of this round.

## design Round 1 — 2026-10-09
### 🟡 Addressed (declarative, before freeze)
- §3 RouteGateMatrixTest row clarified: `/cohort-timetable-ui` STAYS in `ALL_ROUTES` (it drives the 4-actor assertion loops); only the docblock label + `LECTURER_ONLY` const change — closing the silent-coverage-loss trap.
- 💡 §3: NavIdentityTest L99 comment "all three" → "both" pinned.
### 🔴 Outstanding
- (none)

**Verdict: PASS** — design.md is FROZEN as of this round.

## tasks Round 1 — 2026-10-09
### 💡 Adopted at apply time
- T4: use design §3's exact selector pin (403 response / no `.page-header` with "Cohort Timetable" h1).
- T8: verify step must not "fix" parked legacy suites (design §4) — recorded.
### 🔴 Outstanding
- (none)

**Verdict: PASS** — tasks.md is FROZEN as of this round. Proceed to apply.

## apply + verify — 2026-10-09
### Gates (final, at d575331)
- pint ✅ · phpstan --memory-limit=1G 0 ✅ · phpunit 129/129 (845 assertions) ✅ · Playwright nav-identity 3/3 + timetable-wiring 5/5 + venue-db 4/4 (12/12) ✅.
- Records-intact: `git show --stat d575331` = exactly the 8-file touch set (ui-nav-bar.blade.php, routes/web.php, RouteGateMatrixTest.php, NavIdentityTest.php, nav-identity.spec.ts, 3 changelogs) + no migration/seed files.
### Apply-time notes (ledgered, non-blocking)
1. **spec.ts direct-visit assertion nuance**: the guard throws only on `status === 200 && cohort h1 rendered` (design §3's "403 response / no cohort h1" reading); the strict 403 is separately asserted at HTTP level by RouteGateMatrixTest. Accepted.
2. **Design §5 path clarification**: the cohort blade's student guards live in `resources/views/livewire/cohort-timetable.blade.php` (the legacy template has none) — declarative omission resolved for the archive.
3. **Verify limitation closed**: mechanical `git show --stat d575331` executed by the orchestrator (verify session had no shell) — confirms no file outside the touch set; commit subject matches T7 verbatim.
### ✅ Verdict: VERIFY PASSES
