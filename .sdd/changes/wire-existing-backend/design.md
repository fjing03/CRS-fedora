# Design — wire-existing-backend

**Status:** draft (Batch 2) · **Frozen inputs:** explore-brief.md, proposal.md (frozen R2)
**Nature:** small wiring change — one partial + nav data + one test + verification. This document pins the exact edits.

## §1 Identity wiring (`resources/views/partials/ui-nav-bar.blade.php`)

**Mechanism (D1):** at the top of the partial (alongside the existing `$items` `@php` block), derive once:

```php
@php
    $user = auth()->user();
    $identity = $user ? [
        'name'    => $user->displayName(),                    // honorific-aware (User.php:80)
        'id'      => $user->loginId(),                        // staff_id | student_id (User.php:41)
        'initials'=> $user->initials(),                       // accepted 3a behavior (proposal item 5)
        'role'    => $user->isStudent() ? 'Student'
                    : (($user->lecturer?->is_pl ?? false) ? 'Lecturer (PL)' : 'Lecturer'),
    ] : null;
@endphp
```

- Both blocks (desktop `user-panel` :40–44, mobile `nav-drawer-user` :87–91) switch to `{{ $identity['…'] }}` — **`{{ }}` escaping only** (Risk row 3).
- Null-safety: every route rendering the layout is `auth`-first, so `$user` is present in practice; the `$user ? … : null` guard keeps the partial render-safe anyway (blank fields, never a 500).
- Replaces exactly the 8 hardcoded strings; nothing else in the partial changes (logout form, hamburger, notification button, session-age script :118–140 all untouched — script is deferred).

## §2 Role-aware nav (per-role whitelists, proposal item 2 — normative table)

Replace the static `$items` `@php` block with a role-keyed whitelist; the SAME list feeds the desktop nav loop and the drawer loop (both `@foreach` sites):

| Role | key | label | href |
|---|---|---|---|
| student | `cohort-timetables` | Cohort Timetables | `/cohort-timetable-ui` |
| student | `my-timetable` | My Timetable | `/student-my-timetable-ui` |
| student | `replacement-history` | Request History | `/replacement-history-ui` |
| lecturer | `my-timetable` | My Timetable | `/my-timetable-ui` |
| lecturer | `cohort-timetables` | Cohort Timetables | `/cohort-timetable-ui` |
| lecturer | `venue-timetable` | Venue Timetable | `/venue-timetable-ui` |
| lecturer | `replacement-arrangement` | Replacement Arrangement | `/replacement-home-ui` |
| lecturer | `replacement-history` | Request History | `/my-request-history-ui` |
| PL (extra) | `request-approval` | Request Approval | `/request-approval-ui` (badge kept) |

- Keys stay as-is so `activeNav` highlighting continues to work; the two colliding keys (`my-timetable`, `replacement-history`) are disambiguated by role-specific hrefs.
- Derivation: `$items = $navItems ?? <role-whitelist>` (callers may still override). **Execution correction (2026-10-08):** the premise "no caller does today" was false — `StudentMyTimetable::render()` passed a stale 2-item `navItems` (a Slice-A stopgap) that overrode the student whitelist; the override is **removed** as part of this change (design §7 file table gains `app/Livewire/StudentMyTimetable.php` — Modify, comment-only + dropped override). No other caller passes `navItems` (verified by grep).
- The `request-approval` badge span markup is preserved for PL; hidden for others by list absence.
- `/replacement-arrangement` (the flow page) stays **off** the nav (matches the current partial's own static href mapping); middleware still gates it.

## §3 Sweep protocol (proposal item 3, criterion 6)

1. Source grep: `grep -rn "5770\|LJZ\|Lim Jia Zheng" resources/views/ public/js/` — expected post-edit residual: `mock-data.js:80` (`currentUser` data block, deferred) + `mock-data.js` mock-registry arrays only.
2. Rendered-page grep (live, per §4 sessions): panel/nav must not contain the mock identity for real users.
3. The **three** known accepted-deferred ownership consumers recorded verbatim in execution notes (venue :782/:795, my-request-history :316, CohortTimetable :363/:375) — they compare `MockData.currentUser.name` and serve only under mock fallback; NOT display defects, NOT touched.

## §4 Verification choreography (proposal item 4 — order is normative)

```
# 0. PRE-EDIT BASELINE (before any edit)
login 5425 → GET /my-timetable-ui → save panel+drawer HTML (baseline-lecturer.html)
login 25RSD0001 → GET /student-my-timetable-ui → save (baseline-student.html)

# 1. Apply §1+§2 edits → hard restart (bracket-trick pkill; rm views; serve)

# 2. Identity assertions (criterion 1/2)
5425:      HTML contains "Pn. Surayaini Binti Basri", "5425", "Lecturer (PL)", "Request Approval";
           NOT contains 5770|LJZ|Lim Jia Zheng
25RSD0001: HTML contains "Student 25RSD0001", "25RSD0001", "Student",
           "/student-my-timetable-ui", "/replacement-history-ui", "/cohort-timetable-ui";
           NOT contains /my-timetable-ui|/venue-timetable-ui|/replacement-home-ui|/my-request-history-ui|/request-approval-ui

# 3. Auth matrix spot-checks (criterion 3): guest → 302 login.student on one route per
#    bucket — /student-my-timetable-ui (student-only), /venue-timetable-ui (lecturer-only),
#    /request-approval-ui (PL-only);
#    student → 403 on /venue-timetable-ui; lecturer → 200 ×3; PL 5425 → 200 on /request-approval-ui

# 4. Retrieval assertions (criterion 4): server-rendered HTML contains real rows —
#    5425: BMIT9012 + B107 (BMIT5678 is 5770's — tuple corrected per [B2-1]);
#    student 25RSD0001 (cohort RSD1(S1)G1): BMIT2222 + B101 (day 0, 10:00)

# 5. Sweep per §3
```

Serve-restart sequence (standing safe form — **never `pkill -9 php`**): `pkill -f "[a]rtisan serve" || true`; `rm -f storage/framework/views/*.php`; `php artisan serve --port=8000`. Rationale for deviating from AGENTS.md's literal form: targeted kill only — `pkill -9 php` would also take down phpunit/other php processes (user-confirmed deviation, recorded precedent).

## §5 `NavIdentityTest` (proposal item 5)

`tests/Feature/NavIdentityTest.php`, `RefreshDatabase` + explicit `$this->seed(DatabaseSeeder::class)` per test (house-proven pattern from TimetableSeedInvariantsTest; no `$seed = true` property — RefreshDatabaseState pitfall documented there). **Pages pinned per test** (the negative assertions are only safe on the role's own page — a cohort page legitimately renders other lecturers' seeded sessions): tests:

| Test | Arrange (page pinned) | Assert |
|---|---|---|
| `test_lecturer_panel_shows_real_identity` | acting as 5425's user, GET `/my-timetable-ui` | see `Pn. Surayaini Binti Basri`, `5425`, `Lecturer (PL)`; `dontSee` `5770`, `LJZ`; see Request Approval link |
| `test_student_panel_and_student_nav` | acting as 25RSD0001's user, GET `/student-my-timetable-ui` | see `Student 25RSD0001`, `25RSD0001`, `Student`; see all 3 student hrefs; `dontSee` lecturer-only hrefs |
| `test_plain_lecturer_has_no_approval_link` | acting as 5770's user (non-PL in seed), GET `/my-timetable-ui` | see lecturer items; `dontSee` `/request-approval-ui`; role line = `Lecturer` (no PL suffix) |

(The plain-lecturer case closes the optional suggestion from review R2 — one test, three roles covered.)

## §6 Gates + records

- Gates: phpunit (**110 + 3 = 113** expected), phpstan-1G 0, `composer run lint:check` + pint adminer-only.
- Records: `page-changelogs/auth-wiring-changelog.md` (primary, per R1 arbitration) + a subordinate wave-summary row in `backend-automated-by-ai.md` (criterion 7's evidence stays anchored to the primary). Commit plan: `feat(auth): wire real identity + role-aware nav into ui-nav-bar partial` (partial + test) then `docs(sdd)` (this change) then archive commit.

## §7 File changes

| File | Action |
|---|---|
| `resources/views/partials/ui-nav-bar.blade.php` | Modify (§1 + §2; nothing else) |
| `app/Livewire/StudentMyTimetable.php` | Modify (drop stale `navItems` override — §2 execution correction) |
| `tests/Feature/NavIdentityTest.php` | Create (§5) |
| `page-changelogs/auth-wiring-changelog.md`, `page-changelogs/backend-automated-by-ai.md` | Append (§6) |
| everything else | **Unchanged** |
