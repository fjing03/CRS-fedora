# Tasks — wire-existing-backend

**Status:** draft (Batch 3) · **Frozen inputs:** explore-brief.md, proposal.md (R2), design.md (R2) — § refs normative.
Every task ≤ 2h; execution notes appended per task.

## Task 1 — Pre-edit baseline + live auth/retrieval ground truth (design §4 step 0, criteria 3–4 "before")

- [x] **T1.1** Serve-restart (safe form, design §4): `pkill -f "[a]rtisan serve" || true`; `rm -f storage/framework/views/*.php`; `php artisan serve --port=8000`; 200 on `/login/student`.
- [x] **T1.2** Baseline captures (pre-edit): login 5425 → GET `/my-timetable-ui` → save panel+drawer HTML; login 25RSD0001 → GET `/student-my-timetable-ui` → save. Record: current panel shows mock identity (5770/LJZ) for BOTH — the "before" of the diff.
- [x] **T1.3** Auth matrix spot-checks (design §4 step 3): guest → 302 on `/student-my-timetable-ui`, `/venue-timetable-ui`, `/request-approval-ui`; student → 403 on `/venue-timetable-ui`; 5425 → 200 on `/venue-timetable-ui`, `/replacement-home-ui`, `/request-approval-ui`. Record outputs.
- [x] **T1.4** Retrieval ground truth (design §4 step 4): 5425's `/my-timetable-ui` HTML contains `BMIT9012` + `B107`; 25RSD0001's page contains `BMIT2222` + `B101`. Record. *(If a retrieval gap surfaces here, STOP — it changes scope; report instead of fixing ad hoc.)*

## Task 2 — Identity + nav wiring (design §1–§2; proposal items 1–2)

- [x] **T2.1** `ui-nav-bar.blade.php`: add the `@php` identity block per §1 (null-safe `auth()->user()`; `displayName()`/`loginId()`/`initials()`; PL via `$user->lecturer?->is_pl ?? false`).
- [x] **T2.2** Replace the 8 hardcoded identity strings (desktop :40–44 + drawer :87–91) with `{{ $identity['…'] }}` — `{{ }}` escaping only; nothing else in the partial changes (session-age script :118–140 untouched).
- [x] **T2.3** Replace the static `$items` block with the §2 per-role whitelist (exact keys/labels/hrefs from the §2 table; `$items = $navItems ?? <whitelist>`; both `@foreach` sites consume it; request-approval badge markup kept for PL).
- [x] **T2.4** `php -l` + pint on the partial; hard restart; quick render sanity (no 500 on any page family).

## Task 3 — `NavIdentityTest` (design §5; proposal item 5)

- [x] **T3.1** Create `tests/Feature/NavIdentityTest.php` per §5: RefreshDatabase + explicit `$this->seed(DatabaseSeeder::class)`; 3 tests with pinned pages (5425→`/my-timetable-ui`, 25RSD0001→`/student-my-timetable-ui`, 5770→`/my-timetable-ui`) and the §5 assertion columns.
- [x] **T3.2** Run the new file alone → 3/3; then full suite → **113** (110 + 3).

## Task 4 — Post-edit live verification (design §4 steps 2–5; criteria 1–4, 6)

- [x] **T4.1** Identity assertions live (design §4 step 2): 5425 contains `Pn. Surayaini Binti Basri`/`5425`/`Lecturer (PL)`/Request Approval, never `5770|LJZ|Lim Jia Zheng`; 25RSD0001 per §4 step 2's contains/not-contains lists. Diff against T1.2 baselines. Record.
- [x] **T4.2** Re-run the §4 step 3 matrix + step 4 retrieval tuples post-edit. Record.
- [x] **T4.3** Sweep (design §3): source grep residual = `mock-data.js` only; rendered pages clean; the **three** accepted-deferred ownership consumers recorded verbatim (venue :782/:795, my-request-history :316, CohortTimetable :363/:375). Record.

## Task 5 — Gates (design §6; criterion 5)

- [x] **T5.1** phpunit full → **113/113**; phpstan `--memory-limit=1G --no-progress` → 0; `composer run lint:check` → adminer-only. Record.

## Task 6 — Records + verify + archive (design §6; criteria 7)

- [x] **T6.1** Append entries: `page-changelogs/auth-wiring-changelog.md` (primary — identity wiring + role-aware nav + the 5770-bug resolution) + subordinate wave-summary row in `backend-automated-by-ai.md`.
- [x] **T6.2** `/sdd-verify` (reviewer subagent, self-run per standing instruction) against frozen criteria 1–7 → verdict in review-log.md.
- [x] **T6.3** Commits: `feat(auth): wire real identity + role-aware nav into ui-nav-bar partial` (partial + test) → `docs(sdd)` (this change's `.sdd/` + changelogs).
- [x] **T6.4** `/sdd-archive` → `.sdd/archive/2026-10-08-wire-existing-backend/` + yaml → archive commit.
- [x] **T6.5** Report to user. **No push** without explicit authorization. *✅ Reported: local commits ahead of origin, awaiting push authorization.*

## Execution notes

(appended during apply — baseline captures, gate outputs, sweep results)

### T1 — pre-edit baseline + ground truth (2026-10-08, all PASS)

- T1.1 safe restart (serve4.log); `/login/student` 200
- T1.2 baselines captured (`/tmp/opencode/base-lecturer.html`, `base-student.html`): **BOTH roles saw the mock identity** (`user-name">En. Lim Jia Zheng`, `user-id">5770` ×2 = desktop + drawer) — the bug's "before" state on file
- T1.3 matrix: guest 302→`login.student` ×3 (one per bucket); student 403 on `/venue-timetable-ui`; 5425 200 on venue/replacement-home/request-approval
- T1.4 retrieval: 5425 page contains `BMIT9012`=1, `B107`=1; student page `BMIT2222`=1, `B101`=1 (real DB rows, server-rendered). *(First student check passed; the 5425 check needed one retry — transient login throttle from rapid smoke logins, not an app issue.)*

### T2 — wiring (2026-10-08)

- T2.1–T2.3: partial edited per design §1–§2 (identity `@php` block; 8 strings → `{{ }}`; per-role whitelists; both `@foreach` sites). php -l clean, pint pass, **0** static identity strings remain in source. *(Edits executed main-side — surgical Blade change; main-side precedent per repair change's sanctioned [W-1] deviation.)*
- **Execution-discovered design-premise defect:** `StudentMyTimetable::render()` passed a stale 2-item `navItems` override (Slice-A stopgap) that beat the partial's whitelist — design §2's "no caller does today" was false. **Fix:** dropped the override (comment explains why); design §2/§7 updated as a declarative correction (recorded in review-log).
- T2.4: hard restart (serve5.log); 200 ×3 across page families (my-timetable, cohort, student) — no 500s.

### T3 — NavIdentityTest (2026-10-08)

- T3.1: 3 tests created; **first run 1/3** — (a) the sequence-drift FK error (fixed: added the `setval` reset from the TimetableSeedInvariantsTest precedent), (b) the student test exposed the stale `navItems` override above (fixed at the caller).
- T3.2: **3/3** (27 assertions); full suite **113/113**.

### T4 — post-edit live verification (2026-10-08, all PASS)

- T4.1: 5425 → `user-name">Pn. Surayaini Binti Basri`, `user-id">5425`, `user-role">Lecturer (PL)`, approval link present (×2 = nav+drawer), **mock-identity hits: 0**; 25RSD0001 → `Student 25RSD0001`/`25RSD0001`/`Student`, all 3 student links present, **0 lecturer-only links** (grep noise "stray \" = shell escaping artifact only, re-verified with plain greps)
- T4.2: matrix re-run identical to T1.3 (302×3/403/200s); retrieval tuples re-confirmed (BMIT9012+B107 / BMIT2222+B101)
- T4.3 sweep: source residual = `public/js/mock-data.js` only (the deferred `currentUser` data block + mock registries); rendered pages 0 mock-identity hits. **The three accepted-deferred ownership consumers recorded verbatim:** `venue-timetable-UI-design-template.blade.php:782/:795`, `my-request-history-UI-design-template.blade.php:316`, `CohortTimetable-UI-design-template.blade.php:363/:375` (all `MockData.currentUser.name` comparisons; serve only under mock fallback; B/C converts them)

### T5 — gates (2026-10-08, all PASS)

- phpunit **113/113** (550 assertions — re-run after the StudentMyTimetable edit); phpstan-1G **0**; lint **adminer-only**
