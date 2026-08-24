# Context Transfer Document — CRS-fedora (TAR UMT Class Replacement System)

> Paste this into new AI session. Covers full chat history + project state as of 2026-08-24.

---

### 1. User Profile & Working Preferences

- **Who:** Poong Foo Jing (25SMR10186), RSD3G2, Bachelor in IT (Hons) Software Systems Development, TAR UMT Sabah. Client: FOCS. Supervisor: Mr Lim Jia Zheng. Moderator: Ms Teng Nga Sing.
- **Language style:** Short, casual, lowercase, Malaysian English + Malay slang (`ady` = already, `shud` = should, `cant`). Wants concise, factual, no superlatives/emojis/praise. Prefers direct objective technical info.
- **Working style:** Gives short direct commands (`stop server`, `git commit and push to local`, `wrong branch !! shud be fedora-backend`). Expects AI to use `file_path:line_number` when referencing code. Expects verification via execution (run code/tests), not speculation.
- **Repeated instruction:** "Continue if you have next steps, or stop and ask for clarification if you are unsure how to proceed." — do not guess.
- **AI behavior expected:** Short concise responses, verify via tools, prefer `edit` over `write`, use specialized tools over bash for file ops, evidence before synthesis.

### 2. Academic Context — FYP

- **Project:** TAR UMT Class Replacement System — replaces manual Google Sheets workflow. Automates conflict-free replacement windows via Multi-Entity Matrix Intersection Engine + OCC + FCFS PL approval.
- **5 Objectives:** 1) Matrix Intersection Engine (4-vector: lecturer × cohort(s) × room × capacity) 2) OCC layer 3) FCFS Approval Dashboard 4) RBAC + notifications 5) Prototype Deployment (Laravel+PG, 14 cohorts/14 staff/23 rooms). Only Obj 4 & 5 partially built.
- **Deliverables:** FYP1 14 weeks design + FYP2 7 weeks (Sprint1 W1-2 Engine, Sprint2 W3-4 OCC/FCFS, Sprint3 W5 integration, W6-7 thesis/viva). SDG 4,8.
- **Source of truth:** `CodingMAIN.md` (541 lines), `AGENTS.md`, `final/FR&NFR.md` Chapter3. Never redesign system — record what was established.
- **Key FR/NFR:** RBAC 3 roles, seed data, 500ms engine, OCC version column, NFR 2.4 session 30min, NFR 5.1 Pint PSR-12, NFR 5.3 service classes `MatrixIntersectionEngine`, `OCCValidator`.

### 3. Projects & Assignments — Current State

**Project: CRS-fedora `/home/jinglinux/tarumt/CRS-fedora`**
- **Purpose:** Web app for class replacement; see above.
- **Status:** Auth + seeders done, 5 UI design templates mocked, OOP Blade refactor done. Matrix Engine, OCC, FCFS not built (FYP2).
- **Recent SDD change: `auth-wiring`** — 9 tasks, all completed (`fedora-backend:7058f7a`, also was on `fedora-frontend:9e815f9` before correction):
  - T1 User panel wiring (`ui-nav-bar.blade.php:37-53`, `83-99`): `Auth::user()->initials()/name/loginId()` + `isLecturer() && lecturer?->is_pl ? 'Programme Leader' : ucfirst(role)`; `@auth`/`@guest` guards; logout form POST `route('logout')`.
  - T2 Role redirect: Custom `App\Http\Responses\LoginResponse` bound to `LoginResponseContract` (Fortify v1.37.2 has no `redirectUsing`). student→`/student-my-timetable-ui`, staff→`/my-timetable-ui`.
  - T3 Session lifetime: `config/session.php` 43200 ceiling; `EnsureSessionLifetime` middleware (`bootstrap/app.php` appended) checks `session('_auth_last_activity')` + `role_lifetime` (43200 student, 30 staff).
  - T4 Remember me checkbox in `login-template.blade.php`.
  - T5 Staff lockout: 3 fails → cache `login_lockout:staffId` 10min, `ValidationException`, `ui-lockout-countdown.blade.php` + `lockout-countdown.js`.
  - T6 Session countdown: `ui-session-countdown.blade.php` + `session-countdown.js` (banner <120s, modal <60s, auto-logout at 0) included in `ui-template.blade.php` inside `@auth`.
  - T7 Session dot green indicator in nav bar.
  - T8 Auto-logout staff only: `auto-logout.js` (25min warning, 30min auto-logout, BroadcastChannel+localStorage multi-tab) included only for `isLecturer()`.
  - T9 Lint: Pint fixed; PHPStan 2.2.2 incompatible with PHP8.5.
  - **38 Playwright tests `tests/e2e/auth-wiring.spec.js` 38/38 pass.**
- **Additional fixes in same commit:**
  - Login button disabled bug (`layouts/login-template.blade.php:435`): `const idRegex = @json(...)` produced **string**; `.test()` is RegExp method → silently failed → button stayed disabled. Fixed to `new RegExp(@json(...))`. Regexes: student `^\d{2}[A-Za-z]{3}\d{4}$`, staff `^\d+$`.
  - Role-based logout redirect: stores `login_type` in `session` + `cookie('login_type',43200)` on login (`FortifyServiceProvider.php:115`), custom `App\Http\Responses\LogoutResponse` reads cookie (session invalidated before response), `EnsureSessionLifetime` and `Livewire/Actions/Logout.php:15` capture `login_type` before invalidation → redirect to `/login/student` or `/login/staff`.
  - Hide `Request Approval` for non-PL: `ui-nav-bar.blade.php:7` added `'pl_only'=>true`; both desktop (`:18`) and drawer (`:93`) loops skip if `!(Auth::check() && isLecturer() && is_pl)`. Guest guard required to avoid null error.
  - Login OOP refactor (cherry-picked `40ba963/35a51d1`): `login-student/staff.blade.php` extend `layouts/login-template.blade.php` with props `title, bgImage, btnColorToken, idRegex, loginType, roleSwitchHtml, rememberHint, showLockout`.

### 4. Technical / System Context

- **Stack:** Laravel 13 PHP^8.3, Livewire4+Flux2+Blaze, Blade+Tailwind, `theme.css` tokens (never hardcode hex), `ui-common.js` 197 lines, `mock-data.js` single source `window.MockData` (read-only, slice before mutate), PostgreSQL `class_replacement` user `philler` no pw, Fortify+WebAuthn, Pint/PHPStan/PHPUnit/Pail/Sail.
- **Commands:** `composer run dev`, `php artisan migrate:fresh --seed`, `composer run lint:check`, `composer run types:check`, `php artisan serve` (port 8000).
- **Domain Model:** `users.role` = `student`/`lecturer` only; PL = `lecturer`+`lecturers.is_pl=true`. `User` helpers `loginId(), isStudent(), isLecturer(), initials()`. Lecturers `user_id` PK non-incrementing, `staff_id` unique. Seed: faculties FOCS/FAFB, depts DCIT/DACB/DSSH, programmes DFT/DSF/RSD/RAF/RBU, 14 cohorts (see CodingMAIN §8), 14 staff (PLs 5425 Surayaini, 5516 Mohd Nur Rahmat), students random 10-15/cohort.
- **Seed password:** `Tarumt@2026` for ALL. Logins: staff numeric ID via `/login/staff`, student `25RSD0001@student.tarc.edu.my` via `/login/student`.
- **Routes:** `/`, `/login/student`, `/login/staff`, `/dashboard`, UI templates `/my-timetable-ui`, `/cohort-timetable-ui`, `/replacement-home-ui`, `/replacement-arrangement`, `/my-request-history-ui`, `/request-approval-ui` (planned). Nav 5→6 items (Dashboard, My Timetable, Cohort Timetables, Replacement Arrangement, Request Approval[PL only], Replacement History).
- **UI Design Rules (10 non-negotiable, CodingMAIN §10.0):** token colors only, canonical legend→color map, OOP inheritance/composition, mock-data single source, icon buttons maximize, details in modals, promote-on-3rd-duplication, minimize clicks (~3), confirm critical actions, mobile responsive (hamburger, cards, bottom-sheet), toast/undo bottom-left 5s.
- **Git:** Branches `fedora-backend` (current, correct for auth-wiring), `fedora-frontend`, `main`, `fjing` (AGENTS working branch), `fedora-jing`. Remotes `origin https://github.com/fjing03/CRS-fedora.git`, `upstream https://github.com/FjingXR/class-replacement-system.git`. Conventional commits `ui:, feat:, fix:, refactor:, oop:, style:, docs:`. Last push: `fedora-backend 52b07be..7058f7a`, reverted `fedora-frontend 9e815f9..35a51d1` via `git push --force`.
- **Files to know:** `CodingMAIN.md`, `AGENTS.md`, `prompts/sdd-propose-ui-page.md`, `resources/views/layouts/ui-template.blade.php`, `partials/ui-nav-bar.blade.php`, `public/css/theme.css`, `public/js/ui-common.js`, `public/js/mock-data.js`, `.sdd/changes/`, `page-changelogs/`, `dataset/`, `routes/web.php`, `app/Providers/FortifyServiceProvider.php`, `app/Http/Responses/LoginResponse.php`, `app/Http/Responses/LogoutResponse.php`, `app/Http/Middleware/EnsureSessionLifetime.php`.

### 5. Important Decisions (Confirmed)

- PL Master Config Panel **DROPPED** (not in FR&NFR).
- MPU-3133 venue rule: tutorial rooms + lecture halls OK, labs excluded (overrides older Ch1 wording). Duration-aware windows: engine returns windows ≥ original duration; green cell selects contiguous block.
- Student counts fixed per cohort (§8 table) for reproducible capacity demos.
- Timetable data **synthesized** (no real sheets), user reviews.
- Original block release after approval → old slot bookable again.
- Notifications exactly FR 1.5, 4.15, 4.16 via DB queue.
- Frontend mock phase: no migrations/models/backend unless requested.
- Fortify `redirectUsing` unavailable → custom `LoginResponse` binding.
- Session hard ceiling 43200, per-role via middleware (not config-only).

### 6. Known Problems & Solutions

- **Login button disabled:** `@json(idRegex)` string + `.test()` → fix `new RegExp(@json(...))`. Verified 38/38 pass.
- **Logout → wrong login page:** session invalidated before response → solution cookie+session store + custom `LogoutResponse`.
- **Request Approval visible to non-PL:** added `pl_only` + `Auth::check()` guard; without guard guests threw null error → 8 tests failed → fixed.
- **PHPStan 2.2.2 + PHP8.5 incompatibility:** exits code1 no output → manual verification.
- **No REST API:** `loadMockSection()` tries fetch to non-existent → silently falls back to `MockData` (by design for mock phase).
- **Merge conflicts fedora-frontend→backend:** 8 files (tasks.md deleted, ui-common, login-*.blade, ui-template, nav-bar, api.php) → resolved via `--theirs` (accept incoming).

### 7. Current / Pending Tasks

- **Completed:** All auth-wiring 9 tasks + 3 fixes + git push to `fedora-backend`. Tests green. Server stopped via `pkill php artisan serve` (last user command).
- **Pending / Next:**
  - Manual testing for 3 roles (user asked "what advanced functions can i test manually for all 3 roles' login" — not yet answered due to `stop server` interrupt). Need to provide matrix: student/lecturer/PL: login validation, remember me, lockout, session countdown, auto-logout, badges, redirects.
  - Sprint 1-3 Roadmap not started: venue/module/timetable migrations, `MatrixIntersectionEngine`, `OCCValidator`, FCFS dashboard, email queue, UAT.
  - SDD workflow: proposal→design→tasks→apply→verify→archive; update `page-changelogs/*.md` after UI changes.
- **Blocked:** PHPStan types check.

### 8. Writing Style Profile (based on user samples)

- **Vocabulary:** Simple, short words, technical terms introduced without definition (assumes shared context). Minimal adjectives.
- **Sentence structure:** Very short fragments, often missing subject/punctuation/capitalization (`why can't i login ady ?`, `when logout , it shud direct to login page. depnds on the last login is staff or student`).
- **Grammar:** Informal, frequent shortcuts (`shud`, `cant`, `wanna`, `ady`), occasional Malay particles. Lowercase start.
- **Paragraph:** Single sentence or line, bullet-like commands.
- **Tone:** Direct, imperative, not academic. No transitions.
- **For AI to reproduce:** Keep English simple B1-B2, short sentences (10-15 words), plain structure, avoid upgrading to native academic English unless explicitly requested for reports.

### 9. Important Constraints

- **Never** hardcode colors; use `theme.css` tokens. Follow canonical status→color map.
- **Mock data:** `window.MockData` single source; treat read-only.
- **OOP:** `@extends('layouts.ui-template')`, `@include` partials, shared CSS/JS; no copy-paste. Promote on 3rd duplication.
- **Git:** Working branch is `fedora-backend` (current) per user correction (AGENTS says `fjing` but user explicitly said `fedora-backend`). Never push without ask (but user explicitly asked push this time). Conventional commits.
- **DB:** PostgreSQL; never commit secrets; localhost only (NFR 7.2).
- **Verification:** Run `composer run lint:check` + `types:check` after PHP changes; run `npx playwright test tests/e2e/auth-wiring.spec.js` (1.1m).
- **Responsive:** All pages ≤768px must have drawer, card layout, bottom-sheet modals, 44px touch targets.

### 10. Instructions for the New AI

- Do **not** assume missing info — ask if unsure (per user rule).
- **Read first every session:** `CodingMAIN.md` full + `AGENTS.md` + relevant `.sdd/changes/<change>/` before coding.
- Preserve existing implementation decisions; do not redesign architecture.
- Keep responses **short and concise**, factual, objective, no superlatives/praise/emojis; include `file_path:line_number` when referencing code.
- Verify via execution (run bash/tests) whenever reasonable.
- Check `git status`/`git log --oneline -10` before any git work; respect correct branch `fedora-backend`.
- Answer in simple concise English matching user style for casual chat; use proper academic style only when user requests report/thesis writing.
- Follow UI Design Rules §10.0 strictly for any frontend work.
- Update `page-changelogs/*.md` after UI changes.
- For new UI page, **MUST** read `prompts/sdd-propose-ui-page.md` before proposing.

### 11. Unresolved / Needs Confirmation

- **Conflicting branch info:** `AGENTS.md` says working branch `fjing`, but user corrected `fedora-backend` is correct for auth-wiring. Treat `fedora-backend` as current source of truth, but confirm for next SDD.
- **Manual test matrix for 3 roles:** Requested but interrupted by `stop server`. Still needs to be delivered in new session.
- **Server state:** Stopped per user command; restart with `php artisan serve` or `composer run dev` if needed for manual testing.
- **Next SDD:** Which feature next? User hasn't specified — needs confirmation (likely `request-approval` SDD or Sprint1 Engine).

---

**Handover complete.** New AI: start by confirming you have read `CodingMAIN.md` and `git status` on `fedora-backend`.
