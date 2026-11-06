# TARUMT Class Replacement System — Main Project File

> **Purpose:** Single source of truth for this project. Read this first before any coding session.
> **Location note (2026-11-02):** this file lives in `prompts/for-ai-to-read/`; the root
> `AGENTS.md` points here. Paths below are relative to the **repository root**, except the
> `../final/…` and `../TARUMT …` links, which are written relative to this directory.
> This is the **fedora-repo canonical version** (§10.6 git policy: upstream pull-only). The original
> upstream copy and the older fedora variant are archived in `docs/archive/` for reference.
> Referenced by: FYP proposal (`../../../final/25SMR10186_Form 2_Proposal (v2025-11).docx.pdf`), Chapter 1 (`../../../final/Ch1.pdf`), Chapter 3 FR & NFR (`FR&NFR copy.md` — this folder; a verbatim copy of `../../../final/FR&NFR.md`), Full Functional Specifications V2 (`../../../TARUMT Class Replacement System - Full Functional Specifications V2.pdf`), and the live codebase at the repository root.
> Last reviewed: 2026-10-10 (post all-pages-design-parity).

---

## 1. Project Overview

A web application that replaces the manual Google-Sheets-based class replacement workflow at TAR UMT Sabah (FOCS faculty). It automates discovery of **conflict-free replacement windows** via a **Multi-Entity Matrix Intersection Engine**, prevents double-booking via **Optimistic Concurrency Control (OCC)**, and routes requests through a **First-Come-First-Served (FCFS) approval dashboard** for Programme Leaders.

- **Student:** Poong Foo Jing (25SMR10186) — Bachelor in IT (Hons) Software Systems Development, RSD3G2
- **Supervisor:** Mr. Lim Jia Zheng | **Moderator:** Ms. Teng Nga Sing
- **Client:** Faculty of Computing and Information Technology (FOCS), TAR UMT Sabah
- **Development model:** Agile (iterative) — FYP1 (14 weeks, design) + FYP2 (7 weeks, 3 sprints)
- **SDG alignment:** SDG 4 (Quality Education), SDG 8 (Decent Work & Economic Growth)

---

## 2. Problem Domain (5 Pain Points)

1. **Fragmented process & human error** — each lecturer keeps a personal schedule spreadsheet; no single source of truth; manual cross-referencing (Babaei et al., 2015).
2. **Blind spots in multi-cohort scheduling** — modules shared across cohorts (e.g. RSD3G1 + RSD2G2 + RSD2G3) require manually overlaying separate timetables.
3. **Venue availability tracking** — room occupancy lives in a static PDF, separate from scheduling; no capacity-aware filtering; room priority rules (Lab-only for P sessions, B006 for Networking) unenforced.
4. **Data race conditions** — Google Sheets has no transaction isolation → simultaneous submissions cause double-booking with no rollback (Kung & Robinson, 1981).
5. **Elevated PL administrative burden** — PL re-verifies every request from scratch (schedules + cohorts + venue) before writing "Y" in a cell; no audit trail.

---

## 3. Solution Architecture (5 Objectives)

| # | Objective | Core Idea | Status |
|---|-----------|-----------|--------|
| 1 | **Multi-Entity Matrix Intersection Engine** | 4-vector deterministic set intersection: lecturer availability × cohort free schedules (1+ cohorts) × room occupancy × capacity filter. | 🟢 Built + wired — timetable pages derive real conflicts (venue restrictions, B005) from DB |
| 2 | **Optimistic Concurrency Control (OCC) layer** | Millisecond-precision transactional validation at submission time; abort + rollback + UI alert on conflict. ACID transactions. | 🟢 Built + wired — `SubmitReplacementRequest` injects `OCCValidator` and calls `validateAndReserve()` on the live submit path (docs sync 2026-10-28; the 🟡 table entry was stale) |
| 3 | **FCFS Digital Approval Dashboard** | Chronological queue; one-click approve (→ Occupied) or reject with mandatory reason (→ Available); timestamped audit trail. | ✅ REAL page (SDD `span-aware-approval-flow`, 2026-10-18 — mock swapped by `class_exists`; write path = `ApproveRequest`/`RejectRequest`, span-aware, D7 release, bulk, no-undo) |
| 4 | **Role-Based Access Control (RBAC) + notifications** | 3 tiers: Student (view-only), Lecturer (create/submit/cancel own — FR 4.13), PL (hybrid = lecturer rights + approve/reject). Email notifications: PL on submission (FR 4.15), lecturer on outcome (FR 2.13), students on timetable updates (FR 1.9) — all via queue (FR 4.16). | ✅ Emails live — the three Mailables queue from the real Actions AFTER the transaction commits (log mailer in dev; SMTP = a `.env` flip + `php artisan queue:work`; `docs/queue-and-emails.md`). RBAC/auth done. **NEXT LANE:** the notifications bell/panel's data bridge (real table + per-action emitter — the panel UI is frozen but still fed by the mock seed `mock-data.js` §2.13) |
| 5 | **Prototype Deployment** | Laravel + PostgreSQL, localhost, real imported timetable dataset. | 🟢 Done — real data imported (not synthesized) |

**Wave 3b write path (live):** timetable pages render **real DB data**, and the booking/replacement write path is shipped — submit (OCC-enforced), approve/reject span-aware, class cancellations, pending-request cancels. Demo-DB data edits follow [`docs/demo-db-data-changes.md`](docs/demo-db-data-changes.md); the viva runbook lives at [`docs/viva-demo-runbook.md`](docs/viva-demo-runbook.md). Cards keep showing honest zeros (`sumPending`/`sumReplacement` = real state, never fabricated). Venue page keeps `sumOccupied`/"Occupied" (documented deviation from upstream's `sumUnavailable`).

### Slot State Machine (Objective 1–3 core)

> **FR 4.11 note (2026-10-01):** the latest FR&NFR lists **three** statuses — *Available, Pending, Occupied*. `reserved` is kept as a **derived view**, not a 4th DB status: DB `status = pending` + `proposer_id ≠ current user` → rendered grey ("Reserved by Another Faculty"); `proposer_id = me` → yellow ("Pending (Self)"). Same row, two colours.

| State | Meaning |
|-------|---------|
| `available` | Free — satisfies lecturer × cohort(s) × room × capacity |
| `pending` | Submitted, awaiting PL approval — yellow if mine (Pending (Self)), grey if another lecturer's (Reserved by Another) |
| `occupied` | PL approved (locked permanently) or blocked by master timetable |

### Visual grid colors (Module 2)
- **Green** — satisfies all 4 constraints, clickable
- **Red** — occupied by existing class
- **Yellow** — pending request submitted by you
- **Grey** — reserved by another lecturer
- **Blue** — current selection

### Two-axis block language (frozen UI, 2026-10)
All timetable pages use the frozen design's two-axis language: **colour = status** (same fill for everyone), **border weight = ownership** (3px thick = your class, 0.5px hairline = others'). Block classes in `theme.css`: `event-mine` / `event-others` / `event-mine-pending` / `event-others-pending` / `event-conflict` (own conflict or public holiday — loud) / `event-public-holiday` (others', hairline red). Legend chips render these block classes directly.

### Venue types & rules
- **Tutorial Rooms** (cap ≤ 35): B100–B109, B014–B018, B002 — L/T sessions
- **Lecture Halls** (cap > 35): B110, B111 — large multi-cohort assemblies
- **Computer Labs** (cap 28): B005, B009–B011 — Practical-only 'P' sessions
- **Cisco Specialized Lab** (cap 32): B006 — priority for Networking/IoT workloads
- **Excluded:** Block C venues (operational boundary for prototype)
- **MPU-3133 venue rule (user decision 2026-08-01):** may use **tutorial rooms OR lecture halls — labs excluded only**. Generalized in revised FR&NFR as **FR 4.7**: modules with session-type restrictions filter venues by allowed session type.
- **Enforced today:** see [`docs/venue-restrictions.md`](docs/venue-restrictions.md) — B005 no-Diploma (drives the live derived-conflict flag), B006 networking priority, P→labs (doc-only until Wave 3b booking validation).

### Edge cases the engine must handle
- No common slot found → return empty state, never crash
- Single-cohort request → 3 vectors (lecturer × cohort × room)
- All-day occupancy → correct empty result
- Session-type venue restrictions (e.g. MPU-3133 → exclude labs; a module marked P must use a lab; L-only modules must not use labs)
- **Duration-aware windows:** engine only returns windows ≥ the original class duration; clicking a green cell selects the whole contiguous block

### Replacement flow decisions (user-confirmed 2026-08-01)
1. **Student counts:** fixed per cohort (see §8) — capacity-filter demos are reproducible.
2. **Timetable data:** real past-semester timetable PDFs from TAR UMT Sabah, imported into the DB (≈38k time slots, 14 cohorts / 14 staff / 23 rooms) — see §11.
3. **Original block release:** after PL approval, the original class block is marked replaced/cancelled and its old time+room become **bookable by others**; the new slot becomes `occupied`.
4. **PL Master Configuration Panel: DROPPED** — not in FR&NFR; specs V2 mention is superseded by FR&NFR as source of truth.
5. **Notifications:** implement exactly FR 1.9 (students on timetable updates), 4.15 (PL on submission), 2.13 (lecturer on outcome); all sent via DB-backed queue (FR 4.16). No cross-lecturer alerts unless requested later.
6. **MPU-3133 venue rule:** tutorial rooms + lecture halls OK, labs excluded (see §3 venue rules).

### Auto-reject rule (user-confirmed 2026-11-06)
A **Pending** replacement request is **automatically rejected** when its proposed start is within — or
exactly — **3 working hours** of now. Working time is the TARUMT Sabah office clock:
**Mon–Fri 08:30–17:30**, never evenings, Saturdays, Sundays or the semester's own `holidays` rows; a
gap that crosses a closed period resumes at the next opening bell, and a proposal already in the past
has no time left (0). Anything further out stays Pending, and **no other status is ever considered**.

- Clock: `App\Support\WorkingHours` (`MIN_GAP_MINUTES = 180`, `gapMinutes()`,
  `subtractWorkingMinutes()`, `holidays()` resolved through `semesters.start_date`).
- Write leg: `App\Actions\AutoRejectRequest` — the cancel family's release (version-checked
  `pending→available` flip + junction DELETE) so the reserved cells return, stamped `rejected` with a
  fixed reason and **`approver_id` NULL** (no human decided); audits ride `action = 'rejected'` with
  `details.auto = true`, attributed to the proposer (`audit_logs.user_id` is NOT NULL).
- Trigger: `App\Services\PendingRequestExpiry` + `php artisan requests:auto-reject` (`--dry-run`),
  scheduled every five minutes. **Never** a write-on-read (a page render must not mutate data).
- UI: a Pending badge's `data-tip` states the deadline (proposed start − 3 working hours) through the
  shared `ReplacementRequestMapper::statusTip()`, on both My Replacements and the PL queue.

### FR 2.17 is enforced on the SERVER (2026-11-06)
The grid refuses a too-soon cell in the browser (`leadTimeCutoff()` in `ui-common.js`), and since
2026-11-06 `App\Actions\SubmitReplacementRequest` refuses it too — a submit needs the slot's DATE to
be at least the **3rd working day** after today (weekends + holidays skipped; the 3rd working day
itself stays selectable; today never), failure reason **`slot_too_soon`**. `WorkingHours::noticeCutoff()`
/ `isBookableOnDate()` is the calendar, so the grid's "Bookable from …" date, the server gate and the
pending-deadline tooltip are one computation. Measured 2026-11-06: Fri 6 Nov → grid and server both
say **12 Nov** (W8 Monday is Deepavali), 98/98 semester days agreeing. **A rule the page enforces must
also exist in the action** — the page is a convenience, the action is the truth.

The same clean-up closed **D2's holiday rule** (2026-10-11 decision: a replacement class may not LAND
on a public-holiday day): the page blocked it from the start, and `SubmitReplacementRequest` now
refuses it too with the existing **`holiday`** reason. Replacing the holiday-HIT occurrence stays
legal — the rule blocks the landing day, never the reason for the request. Sunday needs no arm: the
grid has no Sunday rows (`day_of_week` 0–5), so a Sunday slot cannot be referenced. Precedence: the
notice gate runs first, so a holiday inside the 3-working-day window answers `slot_too_soon`.

---

## 4. Tech Stack & Dev Tools

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 (PHP ^8.3) — `laravel/livewire-starter-kit` |
| Live components | Livewire 4 + Flux 2 + Blaze |
| Frontend | Blade templates, TailwindCSS (theme.css for custom), vanilla JS (`ui-common.js`) |
| Database | PostgreSQL (`class_replacement` DB, user `philler`) |
| Auth | Laravel Fortify + passkeys (WebAuthn) |
| Tooling | Pint (lint), PHPStan/Larastan (types), PHPUnit 12 (tests), Playwright (E2E) |

### Commands
```bash
composer run dev              # laravel dev server
scripts/dev-serve.sh [port]   # THE serve command: kills that port, clears compiled views,
                              # starts with revalidate_freq=0 (cures the OPcache stale-bytecode trap)
php artisan serve             # http://localhost:8000 (plain; fine for a first start)
php artisan migrate --seed    # incremental (TESTING DB ONLY — see below)
composer run lint             # pint --parallel (fix)
composer run lint:check       # pint --test
composer run types:check      # phpstan analyse (crashes at 128M here — use the line below)
vendor/bin/phpstan analyse --memory-limit=1G
composer run test             # lint:check + types:check + phpunit
php artisan requests:auto-reject [--dry-run]   # the auto-reject sweep (scheduled every 5 min;
                              # --dry-run prints what WOULD be rejected, writes nothing)
npx playwright test tests/venue-db.spec.ts tests/timetable-wiring.spec.ts tests/nav-identity.spec.ts   # gate specs
npm run dev / npm run build   # vite (not heavily used; UI uses static /css /js)
```

### Fix stale Blade cache (after UI changes)
**Use the script:** `scripts/dev-serve.sh [port]` (default `8000`) — it kills that port's server, clears `storage/framework/views`, and starts with `revalidate_freq=0`. That last part matters: this machine runs `opcache.enable_cli=On` with `revalidate_freq=180`, so a long-running `php artisan serve` can hand out a `.php` file's OLD bytecode for up to ~3 minutes — the classic "my PHP fix isn't taking effect" trap.

By hand, run **TWO separate commands, never one combined string** (a combined `pkill … && php artisan serve` string self-kills — the kill pattern matches its own wrapper). First:
```bash
pkill -f "[a]rtisan serve" || true
```
Then:
```bash
rm -f storage/framework/views/*.php && php artisan serve --port=8000 &
```
**Never `pkill -9 php`** — it force-kills every PHP process on the machine. Always kill the old server first: old processes hold stale compiled views in memory.

### Test login
- Default password for ALL seeded users: `Tarumt@2026`
- Lecturers: login by Staff ID (e.g. `5425`) via `/login/staff` — **optional `P` prefix supported ✓** (`P5425` normalised in `FortifyServiceProvider`, shared lockout counters)
- Students: `{yy}{PROGCODE}{seq}@student.tarc.edu.my` (e.g. `25RSD0001@student.tarc.edu.my`) via `/login/student`
- PLs: Pn. Surayaini Binti Basri (5425), En. Mohd Nur Rahmat Bin Mohd Taat (5516) — `is_pl = true`

### Database safety (non-negotiable)
- Demo DBs hold **real imported data** — `class_replacement_fresh` (:8000) + `class_replacement_demo` (:8001 twin) on the CachyOS machine, `class_replacement` on the HP — **never re-seed, never `migrate:fresh`, never `wipedata`** on them. Every demo-DB write (user clicks included) is user-gated and logged in `docs/demo-db-data-changes.md`.
- Tests use the disposable `class_replacement_testing`, where `php artisan migrate:fresh --seed` is fine — selected ONLY via `DB_DATABASE=class_replacement_testing php artisan …`.
- **Port map:** `:8000` = dev on `class_replacement_fresh` · `:8001` = the demo twin (`CRS-demo`, its own viva DB) · **`:8030` = the disposable AI/E2E self-test lane** (`DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`) — Playwright/spec runs only, never a demo DB, killed after the run.
- **E2E write-guard law (2026-11-01 DATA-LOSS incident, lane `receipt-target-guard`, applied 2026-11-05):** every Playwright spec that WRITES to the database is fail-closed through `tests/helpers/receipt-target-guard.ts` — `abortUnlessTestingTarget(specPath, { requireAllowSql })` as the first statement after the imports (aborts at COLLECTION, before any login or SQL), or `throwUnlessTestingTarget(name)` on the first line of an individual dangerous test. Both demand the EXACT strings `CRS_MODE=testing` + `CRS_BASE=http://127.0.0.1:8030` (+ `CRS_ALLOW_SQL=1` where a fixture runs SQL). There are no defaults and no `test.skip(!ALLOWED)` escapes left: a missing flag is a loud exit-1 refusal that prints the blessed invocation. Run them as `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <spec>` after `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`. Never loosen the guard to make a run "work".
- **⛔ The `--env=testing` trap (real accident 2026-10-26):** this machine has **no `.env.testing`**, so `--env=testing` on any command **silently boots `.env`** — i.e. the DEMO DB — and a fresh/seed then destroys real records. Never append `--env=testing` to any destructive command; the env-var form above is the only sanctioned testing-DB switch.
- **Schema-migration law (2026-11-02):** migrations are **strictly additive** on any demo DB — add a nullable column / add an index, nothing else. Never `change`, `rename`, `drop*`, or data statements. Every run: verified `pg_dump -Fc` snapshot → rehearse on `class_replacement_testing` via `DB_DATABASE=…` → **print the target first** (`php artisan tinker --execute="echo DB::connection()->getDatabaseName();"`) → plain `php artisan migrate` on `class_replacement_fresh`, then `DB_DATABASE=class_replacement_demo …` → row-count receipt before/after (`crs:db-row-counts`) → log it in `docs/demo-db-data-changes.md`. A migration run **is** a demo-DB write (user-gated). Reference execution: `2026_11_02_000001_add_hidden_at_to_notifications_table.php` (5 + 0 rows preserved, every `hidden_at` NULL).
- **Snapshot law (the 2026-10-31 loss lesson):** before **any** demo-DB write take a verified `pg_dump -Fc` (pattern `<db>-<state>-<date>.dump`; `pg_restore --list` must pass) into the backups dir — `/home/philler/Desktop/tarumt/backups/` (HP mirror: `/home/jinglinux/tarumt/backups/`) — and take another dump after any demo-worthy milestone (user's own demo prep clicks count). Restores follow the backups README recipe (stop the server precisely → drop → restore → restart; two separate commands).
- **Canonical viva state (2026-11-01):** `class_replacement_demo-post-recovery-2026-11-01.dump` = the recovered October record and the current live content of BOTH :8000 and :8001 (see `docs/demo-db-oct31-recovered.md`).
- Pristine snapshot + restore recipe: `/home/jinglinux/tarumt/backups/README.md` (HP) + its CachyOS mirror `Desktop/tarumt/backups/README.md`.

---

## 5. Domain Model & Database Schema

### Core tables (all migrated — `database/migrations/`)
| Table | Key fields | Notes |
|-------|-----------|-------|
| `users` | id, name, email, password, **role** (`student`/`lecturer` — PL is `lecturer` + `is_pl=true`), honorific, two-factor, passkeys | |
| `faculties` / `departments` / `programmes` | codes + names, FK chain | FOCS/FAFB → DCIT/DSSH/DACB → DFT/DSF/RSD/RAF/RBU |
| `cohorts` | programme_id, current_year, semester, tutorial_group, academic_year, intake, **student_count** | natural-key unique (2026_10_06) |
| `students` / `lecturers` | **user_id (PK)**, student_id / staff_id, cohort_id / dept_id, is_pl | |
| `semesters` | timeline anchor for weeks | |
| `venues` | room_code, room_name, capacity, room_type (`tutorial`/`lecture_hall`/`lab`/`cisco_lab`), allowed_session_types | 23 Block B rooms |
| `modules` | module_code, module_name, session_types | |
| `class_sessions` | module, lecturer, day, start/end, venue, session_type, week | |
| `session_cohorts` | (class_session_id, cohort_id) pivot | |
| `time_slots` | class_session_id, day, start/end, venue, status, **version (OCC)**, week_number | ≈38,640 rows (real import) |
| `holidays` / `class_exceptions` | semester-scoped exceptions | holiday uniqueness (2026_10_06) |
| `replacement_requests` | class_session_id, replacement_time_slot_id, proposer, status, timestamps | one active request per block occurrence (partial unique) |
| `audit_logs` | user, action (7 values incl. `class_cancelled`), request/slot, old/new status, OCC result | |
| `passkeys` | WebAuthn credentials | |

### Applied optimization deltas (`2026_08_24_000001`, SDD: db-optimization-pass1)
- `replacement_requests.class_session_id` + `replacement_time_slot_id`: FK cascade → **RESTRICT** (`proposer_id`/`semester_id` remain CASCADE deliberately)
- Partial unique `uq_replacement_requests_active_block (class_session_id, week_number) WHERE status IN ('pending','approved')`
- Indexes: `idx_replacement_requests_time_slot`, `idx_replacement_requests_proposer_submitted`, `idx_audit_logs_time_slot`
- `cohorts.student_count` (nullable smallint, CHECK > 0; seeder-authoritative from §8) — engine headcount reads it with live-COUNT fallback
- `audit_logs.action` CHECK widened to seven values incl. `'class_cancelled'` (FR 2.16 audit support)

### Applied delta (`2026_08_24_000002`, SDD: venue-room-name)
- `venues.room_name` VARCHAR(60) NULLABLE — D8/FR 4.2 "room name"; seeder backfills pattern labels. Terminology ruling: entity reported as **Subject** = table `modules` (FR 4.7 'modules', FR 3.3 'subject'); `session_cohorts` pivot stands in place of planned `module_cohort`.

---

## 6. User Roles & Permissions Matrix

| Capability | Student | Lecturer | Programme Leader |
|-----------|:-------:|:--------:|:----------------:|
| Login / Logout | ✅ | ✅ | ✅ |
| View consolidated master timetable | ❌ *(FR 1.2 scopes students to their cohort timetable)* | ✅ | ✅ |
| View cohort timetables | ✅ | ✅ | ✅ |
| View replacement history / request status | ✅ *(cohort-scoped, FR 1.3–1.4)* | ✅ *(own, FR 2.15)* | ✅ |
| Create / submit replacement request | ❌ | ✅ | ✅ (hybrid inheritance) |
| Cancel own pending request (before PL decides) | ❌ | ✅ | ✅ |
| Cancel own scheduled class (with valid reason, FR 2.16) | ❌ | ✅ | ✅ |
| View venue timetable (FR 2.14) | ❌ | ✅ | ✅ |
| View other lecturers' requests | ❌ | ❌ | ✅ |
| Approve / reject (FCFS queue) | ❌ | ❌ | ✅ |
| Audit trail access | ❌ | ❌ | ✅ |
| Master seed-data configuration | ❌ | ❌ | ❌ *(dropped 2026-08-01 — not in FR&NFR)* |

- Middleware: `CheckRole`, `CheckPl` in `app/Http/Middleware/`
- Route gating + authorization gates (Laravel native) on all dashboard routes
- **Students are strictly view-only**

---

## 7. Requirements (FR & NFR)

Source: `FR&NFR copy.md` (this folder — a verbatim copy of `../../../final/FR&NFR.md`, Chapter 3 §3.4; the copy carries the version note and change log, and **may only be edited with the user's explicit approval**). **Synced 2026-11-02: 49 FRs / 26 NFRs.** Each FR is traceable to one or more of the 5 objectives (§3). Login supports Student/Staff ID via Fortify `username = login_id` (FR 1.1, 2.1 — incl. optional "P" prefix ✓).

### 7.1 Traceability map (FR → Objective)

| FR # | Requirement summary | Objective |
|------|--------------------|-----------|
| 1.1–1.9 | Student login by ID; cohort timetable; request status for cohort; view upcoming replacement details; **cannot create / edit / delete / modify timetable data (4 separate FRs)**; email on timetable updates (FR 1.9) | 3, 4, 5 |
| 2.1–2.17 | Staff ID login (4 digits, optional "P" prefix); own timetable; click class (incl. conflicted/cancelled) → details; start replacement from details; venue dropdown (default original); venue change recalculates ≤500 ms; colour-coded grid; click green slot; submit; **cancel own pending (edit dropped)**; cannot see/edit others'; cannot approve/reject; email on outcome; **view selected venue timetable; view own request history; cancel own scheduled class with reason**; **only propose a slot ≥3 working days ahead (3rd working day itself selectable — FR 2.17, UI-enforced)** | 1, 2, 3, 4, 5 |
| 3.1–3.7 | PL inherits lecturer rights **except FR 2.11 & 2.12** (may see others' requests, may approve/reject); FCFS queue sorted by submission time; queue shows proposer/subject/cohorts/time/venue; pre-computed slot validity; 1-click approve; mandatory reject reason; full audit trail | 1, 2, 3, 4 |
| 4.1–4.2 | Seed 14 teaching staff / 14 cohorts / 23 Block B rooms; room metadata (name, capacity, type, allowed session L/T/P combo) | 5, 1 |
| 4.3–4.7 | Four-part check = 3-set intersection (lecturer × cohorts × room) + capacity as 4th part; "No available slots" message; 3-vector for single cohort (capacity still applies); empty result when fully occupied; session-type venue filtering | 1 |
| 4.8–4.12 | OCC via integer version column on `time_slots`; exactly one concurrent submission wins; conflict alert to loser; slot state machine (Available / Pending / Occupied — **3 states, see §3 note**); OCC outcomes logged | 2, 3 |
| 4.13–4.16 | RBAC 3 roles (Lecturer = create/submit/**cancel** own); unauthenticated → login redirect; email to PL on submission; **all emails via database-backed queue** | 4, 5 |

### 7.2 Functional Requirements (verbatim, FR&NFR.md §3.4.1)

| No. | Requirement |
|-----|-------------|
| 1.0 | **Student** |
| 1.1 | Students shall be able to log in using Student ID and password. |
| 1.2 | Students shall be able to view their cohort timetable. |
| 1.3 | Students shall be able to view the status of replacement requests for their cohort. |
| 1.4 | Students shall be able to view upcoming replacement details (new date, time and venue) for their cohort. |
| 1.5 | Students shall not be able to create any replacement requests. |
| 1.6 | Students shall not be able to edit any replacement requests. |
| 1.7 | Students shall not be able to delete any replacement requests. |
| 1.8 | Students shall not be able to modify any timetable data. |
| 1.9 | Students shall receive email notifications for timetable updates. |
| 2.0 | **Lecturer** |
| 2.1 | Lecturers shall be able to log in using Staff ID (four digits with an optional "P" prefix, e.g., P5425 or 5425) and password. |
| 2.2 | Lecturers shall be able to view their own teaching timetable. |
| 2.3 | Lecturers shall be able to click a class on their own timetable, including conflicted or cancelled classes, to view its details. |
| 2.4 | Lecturers shall be able to start a replacement for a class from its details view. |
| 2.5 | Lecturers shall be able to select a replacement venue from a dropdown that defaults to the original venue of the class. |
| 2.6 | Lecturers shall be able to change the venue dropdown while arranging a replacement, which shall recalculate available slots within 500 ms. |
| 2.7 | Lecturers shall be able to view a weekly timetable grid (time x day) with colour-coded cells showing slot availability. |
| 2.8 | Lecturers shall be able to click a green slot to select it as the proposed replacement. |
| 2.9 | Lecturers shall be able to submit the replacement request for Programme Leader approval. |
| 2.10 | Lecturers shall be able to cancel their own pending requests before the Programme Leader approves or rejects them. |
| 2.11 | Lecturers shall not be able to view or edit other lecturers' requests. |
| 2.12 | Lecturers shall not be able to approve or reject any request. |
| 2.13 | Lecturers shall receive an automated email when their submitted request is approved or rejected. |
| 2.14 | Lecturers shall be able to view the timetable of a selected venue. |
| 2.15 | Lecturers shall be able to view the history of their own replacement requests. |
| 2.16 | Lecturers shall be able to cancel their own scheduled class by providing a valid reason. |
| 2.17 | Lecturers shall only be able to propose a replacement slot on a date at least three working days (Monday–Friday, excluding public holidays) after the current date; the third working day itself shall remain selectable. |
| 3.0 | **Programme Leader** |
| 3.1 | Programme Leaders shall inherit all lecturer privileges except FR 2.11 and FR 2.12 (FR 2.1–2.10, 2.13–2.16). |
| 3.2 | Programme Leaders shall be able to view a FCFS queue of all pending replacement requests, sorted by submission time (earliest first). |
| 3.3 | Programme Leaders shall be able to view the proposer name, subject, affected cohort(s), proposed time, and proposed venue for each request in the queue. |
| 3.4 | Programme Leaders shall be able to view the pre-computed slot validity for each request. |
| 3.5 | Programme Leaders shall be able to approve a request with one click. |
| 3.6 | Programme Leaders shall be able to reject a request by providing a mandatory reason. |
| 3.7 | Programme Leaders shall have every approval and rejection action automatically recorded in the audit trail, including Programme Leader identity, timestamp, action taken, slot ID, and rejection reason (if any). |
| 4.0 | **System** |
| 4.1 | The system shall store timetable data for 14 teaching staff, 14 cohorts, and 23 Block B rooms as seed data. |
| 4.2 | The system shall store each room's details including room name, capacity, room type (Tutorial / Lecture Hall / Lab / Cisco Lab), and allowed session types (L, T, P, or a combination). |
| 4.3 | The system shall apply a four-part check with a three-set intersection of lecturer availability, cohort free schedules (supporting multiple cohorts), and room vacancy, plus venue filtering that checks room capacity as the fourth part. |
| 4.4 | The system shall display a "No available slots" message when no common slot is found. |
| 4.5 | The system shall compute the intersection for single-cohort requests with one cohort schedule (lecturer x one cohort x room), and the capacity check shall still apply. |
| 4.6 | The system shall return an empty result set when a lecturer is fully occupied for the entire day. |
| 4.7 | For modules with session-type restrictions, the system shall filter venues to only those that allow the module's session type. |
| 4.8 | The system shall implement Optimistic Concurrency Control using an integer version column pattern on the time_slots table (Kung & Robinson, 1981). |
| 4.9 | The system shall allow exactly one submission to succeed when two lecturers submit a request for the same slot at the same time. |
| 4.10 | The system shall return a conflict alert to the lecturer whose submission did not succeed. |
| 4.11 | The system shall manage the slot state machine transitioning a slot's status through Available, Pending, and Occupied (state-machine method in Harel, 1987). |
| 4.12 | The system shall log all Optimistic Concurrency Control validation outcomes in the audit trail (Kung & Robinson, 1981). |
| 4.13 | The system shall enforce Role-Based Access Control with three roles: Student (view-only), Lecturer (create/submit/cancel own), and Programme Leader (hybrid — inherits lecturer privileges plus approve and reject rights). |
| 4.14 | The system shall redirect unauthenticated users to the login page. |
| 4.15 | The system shall send an automated email notification to the Programme Leader when a new replacement request is submitted. |
| 4.16 | The system shall send all email notifications in the background through a database-backed queue. |

### 7.3 Non-Functional Requirements (verbatim, FR&NFR.md §3.4.2)

| No. | Requirement |
|-----|-------------|
| 1.0 | **Performance** |
| 1.1 | The system shall complete the matrix intersection calculation within 500 milliseconds from user input to grid display. |
| 1.2 | The system shall complete Optimistic Concurrency Control (OCC) validation with less than 100 ms extra time beyond the database write. |
| 1.3 | The system shall load all dashboard pages within 2 seconds under normal load. |
| 1.4 | The system shall process queued email jobs within 1 minute of the queue worker starting. |
| 2.0 | **Security** |
| 2.1 | The system shall enforce role-based permission checks on all dashboard routes using Laravel middleware. |
| 2.2 | The system shall hash all passwords using Bcrypt and never store passwords in plain text. |
| 2.3 | The system shall use Laravel's Eloquent ORM for all database queries to prevent SQL injection. |
| 2.4 | The system shall terminate inactive **staff** sessions after 30 minutes. |
| 2.5 | The system shall terminate inactive **student** sessions after 30 days. |
| 2.6 | The system shall apply CSRF token verification on all POST, PUT, and DELETE form submissions. |
| 3.0 | **Usability** |
| 3.1 | The system shall provide a responsive web interface that works on desktop, tablet, and mobile screen sizes. |
| 3.2 | The system shall display the slot availability grid with colour-coded cells labelled with their meaning for accessibility. |
| 3.3 | The system shall display error messages in clear and understandable language. |
| 3.4 | The system shall redirect each user to their own timetable page immediately after login. |
| 3.5 | The system shall support light and dark themes. |
| 3.6 | The system shall remember the user's chosen theme across sessions. |
| 4.0 | **Reliability** |
| 4.1 | The system shall guarantee that no double-booking occurs under any situation where many users submit at the same time. |
| 4.2 | The system shall maintain at least 99% uptime during the evaluation and demonstration periods. |
| 4.3 | The system shall support database backup via PostgreSQL pg_dump. |
| 5.0 | **Maintainability** |
| 5.1 | The system shall follow the PHP Standard Recommendation 12 (PSR-12) coding standard, verifiable through Laravel Pint. |
| 5.2 | The system shall track all database schema changes through Laravel migration files in database/migrations/. |
| 5.3 | The system shall separate core logic into dedicated service classes (e.g., MatrixIntersectionEngine, OCCValidator). |
| 6.0 | **Scalability** |
| 6.1 | The system shall support up to 50 concurrent users without performance degradation. |
| 6.2 | The system shall process email notifications through a separate queue worker to avoid blocking the web application. |
| 7.0 | **Legal and Ethical** |
| 7.1 | The system shall process only the minimum necessary data: timetable schedules, staff names, staff IDs, student names, student IDs, cohort codes, and TAR UMT email addresses (Personal Data Protection Act 2010 [Act 709]). |
| 7.2 | The system shall be developed only on localhost to ensure no university data leaves TAR UMT. |

### 7.4 NFR implementation notes (verified against codebase where marked ✓)
- **NFR 2.2 ✓** — Bcrypt hashing is Laravel default; seeder uses `Hash::make`.
- **NFR 2.6 ✓** — CSRF protection is Laravel default (VerifyCsrfToken middleware).
- **NFR 1.2** — OCC validation must add <100 ms beyond the DB write (⏳ benchmark receipt pending — planned for the demo-pack week; no number is written until measured).
- **NFR 2.4 ✓ / 2.5 ✓** — per-role session lifetimes via `EnsureSessionLifetime` middleware (auth-wiring): staff 30 min, student 43200 min (= 30 days); `config/session.php` ceiling set accordingly.
- **NFR 3.4 ✓** — post-login redirect per role via custom `LoginResponse` (student → `/student-my-timetable-ui`, staff → `/my-timetable-ui`).
- **NFR 3.5 / 3.6 ✓** — light/dark theme toggle + `localStorage('theme')` persistence (`ui-common.js`, `ui-template.blade.php` pre-CSS guard).
- **NFR 5.1 ✓** — Pint configured (`pint.json`, `composer run lint:check`).
- **NFR 5.2 ✓** — all schema changes live in `database/migrations/`.
- **NFR 5.3 ✓** — `App\Services\MatrixIntersectionEngine`, `App\Services\OCCValidator` (+ `OCCResult`) exist with Feature tests; the booking write path is live (OCC enforced in `SubmitReplacementRequest`).
- **FR 4.16 / NFR 6.2 / 1.4 ✓** — `QUEUE_CONNECTION=database` (`.env`); the worker runbook = `php artisan queue:work` as a separate worker (`docs/queue-and-emails.md`).
- **FR 2.1 ✓** — Staff ID optional `P` prefix normalised in `FortifyServiceProvider` (both forms share lockout counters).
- **NFR 7.2** — never deploy beyond localhost; no external data egress.

---

## 8. Dataset Reference (Seeding)

### Cohorts (14 — imported)
- **FOCS (11):** DFT1(S1), DFT2(S1), DSF1(S1), DSF2(S1), RSD1(S1)G1, RSD2(S1)G1, RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G1, RSD3(S1)G2, RSD3(S1)G3
- **FAFB (3):** RAF2(S3)G2, RAF2(S3)G4, RBU1(S1)G1
- Code format: `{ProgCode}{Year}(S{Sem})G{Group}`; intake = June of enrolment year; academic year 2025/26
- Master dataset in `dataset/cohorts.md`

### Student counts per cohort (FIXED — user decision 2026-08-01, reproducible capacity-filter demos)

| Cohort | Students | Cohort | Students |
|--------|----------|--------|----------|
| DFT1(S1) | 30 | RSD3(S1)G1 | 14 |
| DFT2(S1) | 28 | RSD3(S1)G2 | 14 |
| DSF1(S1) | 24 | RSD3(S1)G3 | 13 |
| DSF2(S1) | 22 | RAF2(S3)G2 | 12 |
| RSD1(S1)G1 | 18 | RAF2(S3)G4 | 10 |
| RSD2(S1)G1 | 16 | RBU1(S1)G1 | 20 |
| RSD2(S1)G2 | 16 | | |
| RSD2(S1)G3 | 15 | | |

**Capacity implications (by design):** MPU-3133 combined = 83 students → only B110/B111 (cap > 35) fit. MPU-3232 combined = 44 → also B110/B111. Tutorial rooms (≤35) remain valid for single-cohort and small modules. *(Adjustable — update seeder + this table together.)*
*Seeder key format note: seeder keys always include the group suffix (`DFT1(S1)G1`); display names in `dataset/cohorts.md` omit `G1` for DFT/DSF.*

### Academic staff (14 — imported)
- **DCIT (11):** 9 Lecturers + 2 PLs (Surayaini 5425, Mohd Nur Rahmat 5516)
- **DACB (2):** Tan Sharon (4363), Chang Foo Chung (5254)
- **DSSH (1):** Muada Bin Ojih (3799)
- Master dataset in `dataset/lecturers.md`

### Venues (23 Block B rooms — seeded)
- Tutorial (16): B002, B014–B018, B100–B109 | Lecture Halls (2): B110, B111 | Labs (4): B005, B009–B011 | Cisco Lab (1): B006

### Timetable dataset (real import — done)
- Past-semester timetable PDFs from TAR UMT Sabah, imported to the demo DB (≈38,640 time slots across 14 weeks). The demo DB is **never re-seeded** (§4 Database safety). Test DB uses the disposable seeder dataset.

### High-risk use cases (engine validation targets)
1. **Cross-faculty (MPU-3133 Falsafah dan Isu Semasa):** shared across RAF2, RBU1, RSD3 → must compute unified slot across faculties; venue filter = exclude labs.
2. **Cross-year stacking (MPU-3232 Entrepreneurship):** L&T module shared between RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G3 → prevent horizontal clashes with concurrent core modules.

---

## 9. Page Inventory & Routes

### Auth + settings (`routes/web.php`)
| Route | Purpose | Auth |
|-------|---------|------|
| `/` | Welcome / landing | public |
| `/login/student`, `/login/staff` | Role-specific login pages | public |
| `/dashboard` | Authenticated dashboard | `auth` |
| `/settings/profile`, `/settings/appearance`, `/settings/security` | Profile settings | auth (+verified) |

### Livewire pages (`routes/web.php` — one branch per route: `mock_fallback` off **and** the
component class exists → the component, otherwise the untouched frozen `ui-design-templates/*` copy)
| Route | Component | Role |
|-------|-----------|------|
| `/my-timetable-ui` | `MyTimetable` | Lecturer/PL |
| `/cohort-timetable` | `CohortTimetable` | Lecturer/PL (students pinned to own cohort) — renamed 2026-10-13 from `/cohort-timetable-ui` (old URL 302-redirects) |
| `/student-my-timetable-ui` | `StudentMyTimetable` | Student |
| `/venue-timetable-ui` | `VenueTimetable` | Lecturer/PL |
| `/my-replacements` | `ReplacementHome` | Lecturer/PL — **the canonical My Replacements page** (merge lane `replacement-merge-one-table`, 2026-11-04): one table, one row per class occurrence |
| `/replacement-home-ui`, `/my-request-history-ui` | `ReplacementHome` — the **same** component | Lecturer/PL — extra addresses, **never redirects**, so `?notif-hl=` / `?blocked=` survive stored notification links and the post-submit landing |
| `/replacement-arrangement` | `ReplacementArrangement` | Lecturer/PL — the booking grid (pick a slot, submit a request) |
| `/request-approval-ui` | `RequestApproval` | PL |
| `/replacement-history-ui` | `ReplacementHistory` | Student — upcoming replacements + past |

There is **no** `/upcoming-replacements-ui` route any more: the student's upcoming list lives inside
`ReplacementHistory`. `/api/v1/*` stays **commented out** in `routes/api.php` (orphaned since 2026-10),
so the API is not a live surface — every page is fed by its own Livewire payload.
One post-merge quirk kept on purpose: `/my-request-history-ui` still carries the nav key
`replacement-history`, which no lecturer nav item uses, so nothing is highlighted on that alias.

### Nav bar (role-aware, `partials/ui-nav-bar.blade.php`)
- **Lecturer / PL:** My Timetable · Cohort Timetables · Venue Timetable · **My Replacements** (`/my-replacements`) · Request Approval (PL only, pending-request badge)
- **Student:** My Timetable · Replacement History

### Page changelogs
Each page's development history is logged in `page-changelogs/*.md` — **create or update the relevant changelog when you change a page** (`<page>-changelog.md`; append a dated entry, newest first, and create the file when the page has none).

---

## 10. Coding Conventions (IMPORTANT — OOP Concepts)

The project applies **OOP principles at every layer**; FYP rubric evaluates this. Keep it consistent:

### 0. UI Design Rules (user-mandated 2026-08-01)
These ten rules are **non-negotiable** for every page and every future change:

1. **Colors must be consistent across all pages.**
   - ALL colors come from the CSS custom-property token set in `public/css/theme.css` (`--color-bg`, `--color-surface`, `--color-primary`/`secondary`/`tertiary`/`error` + their `-container`/`-on-*` variants, defined once for dark + once for light).
   - **NEVER hardcode hex/rgb/rgba** in a page's `@section('page-styles')`. Use `var(--color-...)`. If a new color is needed, add the token to `theme.css` once.
   - **Sole sanctioned exception (FOUC guard)** — the shared layout's pre-theme paint in `resources/views/layouts/ui-template.blade.php`: `html.dark { background: #0D1B2A; }` / `html.light { background: #F0F3F7; }`. These literals run before `theme.css` loads so a dark-mode reload never flashes white. If the theme's background token ever changes, update BOTH literals together (grep `html.dark`). No other hardcoded color is permitted.
   - Slot-grid colors are fixed token-mapped: Green=`--color-secondary`, Red=`--color-error`, Yellow=`--color-tertiary`, Grey=`--color-outline-strong`, Blue=`--color-primary`. Status badges use the same mapping on every page.

2. **Use the same name + same color for the same meaning everywhere.**
   - A status/legend label and its color are a **canonical pair defined once below**; every page must use that exact pair. Never give the same concept a different label or a different color on another page.
   - Identical component = identical class name across pages (`.badge`, `.legend-item`, `.summary-card`, `.filter-select`, `.cell-code`, `.empty-state`, etc.), defined once in `theme.css`.
   - **Timetable blocks use the frozen two-axis language** (§3): block classes `event-mine`/`event-others`/`event-mine-pending`/`event-others-pending`/`event-conflict`/`event-public-holiday`; legends render block-class chips + the ownership hint ("thick border (3px) = your classes · thin border (0.5px) = others'"). Tooltips render `name · lecturer · status` via `data-name` + `data-tip2` (`lecturer · status`).

   #### Canonical legend / status → color map (single source of truth)
   Two semantic contexts share one palette — green=free/ok, red=conflict/occupied, yellow=pending, blue=primary, grey=reserved/neutral.

   **A. Read-only timetable legend** (block classes in `theme.css`):
   | Block class | Fill token | Meaning |
   |-------------|-----------|---------|
   | `event-mine` / `event-others` | `--color-secondary` (green) | scheduled class (border weight = ownership) |
   | `event-mine-pending` / `event-others-pending` | `--color-tertiary` (yellow) | awaiting PL approval |
   | `event-conflict` | `--color-error` (red, 3px border) | YOUR conflict or public-holiday class |
   | `event-public-holiday` | `--color-error` (red, hairline) | others' conflict / holiday |

   **B. Replacement-arrangement slot grid legend** (booking interface, slot states — Wave 3b):
   | Label | Token | Meaning |
   |-------|-------|---------|
   | Available | `--color-secondary` (green) | satisfies all constraints |
   | Your Current Selection | `--color-primary` (blue) | active pick |
   | Pending (You) | `--color-tertiary` (yellow) | your submitted request awaiting PL |
   | Reserved by Others | `--color-surface-variant` (grey) | another lecturer's pending request |
   | Occupied / Class on Public Holiday | `--color-error` (red) | locked / blocked |

   **C. Request-history status badges** (`.status-*`):
   | Label | Container tokens |
   |-------|------------------|
   | Pending | `--color-tertiary-container` / `-on-tertiary-container` |
   | Approved | `--color-secondary-container` / `-on-secondary-container` |
   | Rejected | `--color-error-container` / `-on-error-container` |
   | Cancelled | `--color-surface-variant` / `-on-surface-variant` |
   | Completed | `--color-primary-container` / `-on-primary-container` |

   Adding a new status/legend item = add ONE row to the relevant table above + ONE class to `theme.css`, then reuse it everywhere. Do not invent a synonym.

3. **Utilise OOP concepts** (see §10.1–10.4 below — Inheritance, Composition, Encapsulation/DRY, service classes). No copy-paste; reuse layout/partial/shared-module.

4. **Minimise plain text, maximise icon buttons.**
   - Prefer **icon buttons** over text labels where the action is self-evident (edit ✎, cancel ✕, approve ✓, view 👁, chevrons ‹ ›, sort ▲▼, theme-toggle, notifications). Use `title`/`aria-label` for accessibility instead of visible text.
   - Keep visible text to essentials: page title, table headers, status badges, and the key data the user came for.
   - Reusable inline SVG icon set lives in `resources/views/flux/icon/`; reuse those — don't paste ad-hoc SVGs per page.

5. **Don't overwhelm the user — push detail/secondary info into modals.**
   - The page surface shows only what's needed to scan & act: the grid/table, its filters, summary cards, and primary actions. Everything else opens in a **modal** on click, not inline.
   - Rule of thumb: if a column/field is "nice to know" rather than "need to scan", it belongs behind an icon button that opens a modal.

6. **Promote-on-3rd-duplication (DRY / OOP).** If a markup block / CSS class / JS helper is **the same across 3+ pages**, promote it to a shared file (Blade partial `resources/views/partials/`, `theme.css`, `ui-common.js`) and refactor the duplicates away. Record every promotion in the SDD `design.md` "Promoted to shared" section.

7. **Minimise steps — fewest clicks possible.** Pre-select sensible defaults; avoid hopping between pages for a single task. If a flow needs more than ~3 clicks, rethink it.

8. **Confirm critical actions.** Any destructive or irreversible action (delete, submit, cancel, approve, reject) MUST show a confirmation popup before executing.

9. **Mobile responsive design.** Every page MUST include mobile layout (≤768px breakpoint): nav drawer, card layout for tables, 2-col summary cards, flex-wrap legend, bottom-sheet modals, ≥44×44px touch targets (WCAG 2.5.5), swipe week navigation, `clamp()` typography, safe-area insets, full-width inputs, viewport meta with `viewport-fit=cover`. Shared CSS in `theme.css`, JS helpers in `ui-common.js`.

10. **Toast bar for critical actions.** After any critical action, show a toast bar at **bottom-left** with a success message; auto-dismiss after **5 seconds**. CSS in `theme.css`, JS helper `toast.show(message, undoCallback, duration)` in `ui-common.js` (the old `showToast()` name is gone). **The Undo button exists only for genuinely reversible actions** (hiding a notification, clearing a selection). **Cancel Class is FINAL** (user ruling 2026-11-05) — no undo affordance, no undo callback, and the server-side `cancelClassUndo()` was deleted; the toast is a plain confirmation. Every final decision's modal additionally carries the shared sentence *"This action cannot be UNDONE."* (`partials.ui-irreversible-line`, used by Approve, Reject, Cancel Request and Cancel Class). The cancellation reminder toast is **one-shot** (it cannot re-fire on every page load) and a `?blocked=request-pending|request-approved` arrival shows the entry-guard message FIRST, deferring any cancellation reminder to the next page load.

### 1. Inheritance
- **Blade layout inheritance:** every page `@extends('layouts.ui-template')` and fills `@section('content')`, `@section('page-styles')`, `@section('page-scripts')`, `@yield('title')`.
- PHP: Eloquent models inherit `Model`; `User` extends `Authenticatable`; middleware extends base classes; migrations use anonymous `class extends Migration`.

### 2. Composition
- **Blade partials** via `@include('partials.ui-nav-bar', ['activeNav' => ...])`, `@include('partials.ui-summary-bar', ['cards' => [...]])`, etc.
- Timetable partials: `ui-page-header`, `ui-week-nav` (incl. `showPrint`; opt-in `showEarliestBtn` adds the Earliest-bookable action to the toolbar, default off so other pages are unchanged), `ui-guide-block`, `ui-legend-bar` (block-class chips + `ownershipHint`), `ui-summary-bar`, `ui-empty-state`, `ui-grid-table`, `ui-class-detail-modal`, `ui-cancel-class-modal` (Wave 3b), `ui-today-btn`, `ui-venue-dropdown`, `ui-logout-modal`.
- **Inline-handler export law.** A page script runs inside a `DOMContentLoaded` closure, so any
  page function referenced by an inline `onclick=` in a shared partial must be exported
  (`window.fnName = fnName;`) in that block — otherwise the markup cannot see it (a modal that
  opens but throws *"… is not defined"* when closing). Shared: `partials/ui-intent-session-modal`
  (the venue Select-a-Session modal + the slot warning) is the current example.
- Shared JS: `public/js/ui-common.js` (`buildTimetableGrid` with two-axis default rendering + span coalescing, `WeekNavigator`, `computeSummary`, `buildReplacementNote`, `openClassModal`, `CancelClass`, `toast.show`, theme helpers), `public/js/mock-data.js` (`window.MockData` — fallback registries + default persona; real-data pages override `MockData.currentUser` / semester / holidays from the server payload).

### 3. Encapsulation & DRY — shared modules
- **`public/css/theme.css`** — ALL shared CSS (nav, layout, tables, badges, summary cards, empty states, timetable block classes, responsive breakpoints, color tokens). Page-specific styles stay in `@section('page-styles')`.
- **`public/js/ui-common.js`** — shared JS helpers (see §2). Page-specific logic stays in `@section('page-scripts')`.
- **`public/js/mock-data.js`** — fallback data source for mock-fallback pages; real-data pages treat it as a shell they override. Legacy consumers are a known debt.
- **NEVER copy-paste** nav bar, tables, helpers into a new page — reuse the layout/partials/shared modules.
- Theme toggle: `localStorage('theme')` = `dark`/`light`, `<html class="dark">` default; login pages use `.login-theme-toggle`.

### 4. General PHP/Laravel conventions
- PSR-4 (`App\`), anonymous classes for migrations, `protected function casts()` for attribute casting
- Route model binding, Eloquent relationships typed with return types + PHPDoc
- Existing middleware pattern: `CheckRole`, `CheckPl` — follow for any new role gating
- Run `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G` before finishing any task
- Fortify actions in `app/Actions/Fortify/`; concerns/traits in `app/Concerns/`; Livewire components in `app/Livewire/`

### 5. Naming
- Blade templates: `kebab-case-UI-design-template.blade.php` for UI mocks; `layouts/ui-template` for real layout
- Tables: `snake_case` plural; models: `StudlyCase` singular
- CSS classes: semantic kebab-case (`.col-code`, `.badge`, `.summary-card`, `.filter-select`)
- **Core logic service classes (NFR 5.3):** `App\Services\MatrixIntersectionEngine`, `App\Services\OCCValidator` — no business logic in controllers/views

### 6. Git workflow

#### Git policy (user-mandated — non-negotiable)
- **This working repo:** `CRS-fedora` (`/home/jinglinux/tarumt/CRS-fedora`)
- **`origin`** → `https://github.com/fjing03/CRS-fedora.git` — the **ONLY** push target. All pushes go here.
- **`upstream`** → `https://github.com/FjingXR/class-replacement-system.git` (branch `fjing`) — **PULL ONLY**. Never push there unless the user explicitly says so.
- Always verify remote + branch before any push (`git remote -v && git branch --show-current`).

#### Branches
- Primary working branch: **`fedora-backend`** (others: `fedora-frontend`, `main`; origin tracks `fedora`, `fedora-backend`, `fedora-frontend`, `fedora-jing`)
- Conventional commits: `ui:`, `feat:`, `fix:`, `refactor:`, `oop:`, `style:`, `docs:`
- **SDD workflow:** each change gets a folder under `.sdd/changes/<change-name>/` (`proposal.md`, `design.md`, `specs/`, `tasks.md`, `review-log.md` → verify → archive to `.sdd/archive/`). Recent archives: `venue-event-blocks-db`, `venue-block-span-coalescing`, `all-pages-design-parity`, `wire-backend-into-refactored-ui`.

---

## 11. Current Status

### ✅ Done
- Laravel 13 scaffold (Livewire starter kit + Fortify + passkeys + Flux), role-specific logins (incl. `P`-prefix staff IDs), per-role session lifetimes
- Full schema migrated + **real timetable dataset imported** (≈38,640 time slots, 14 cohorts / 14 staff / 23 venues) — demo DB protected by the no-reseed rule + pristine snapshot
- Engine services + Feature tests: `MatrixIntersectionEngine`, `OCCValidator` (PHPUnit 130/130 green)
- Venue-restriction conflict derivation live (B005 no-Diploma; `docs/venue-restrictions.md`)
- **All pages at frozen upstream UI design** (two-axis block language, span coalescing, honest cards): venue / cohort / my-timetable / student = real data; arrangement / approval / histories / home = frozen mock-fallback templates
- SDD archives: `wire-backend-into-refactored-ui` (Slice A), `venue-event-blocks-db`, `venue-block-span-coalescing`, `all-pages-design-parity`, `b005-diploma-conflict`, etc.
- Playwright gates: `venue-db`, `timetable-wiring`, `nav-identity` (+ `pages-parity`)

### 🔲 Not built
- **Notifications data bridge** (the next lane): the bell/panel UI is frozen but fed by the mock seed (`mock-data.js` §2.13) — real table + per-action emitter inside the Actions (submit/approve/reject decisions; cancel events = user's call in the lane).
- **UAT + viva demo prep** — the runbook draft = [`docs/viva-demo-runbook.md`](docs/viva-demo-runbook.md); the NFR benchmark receipts + the SMTP flip are demo-pack-week items.
- Thesis Chapters 5–7, final documentation.

---

## 12. Roadmap

> **FYP2 deadline (user-stated 2026-10-10): the 5th week after 2 Nov 2026 →
> week of 7 Dec 2026.** Treat that week as the planning anchor; no finer-grained
> course dates are recorded anywhere in the repo.

| Sprint | Deliverable | Status |
|--------|-------------|--------|
| Sprint 1 | Venue/module/timetable schema + real data import; `MatrixIntersectionEngine` | ✅ Done |
| Sprint 2 | OCC layer + state machine + FCFS dashboard + audit trail | ✅ Shipped — OCC wired to the live submit path; span-aware approval shipped |
| Sprint 3 | Real data into UI templates; RBAC gates; emails; tests | ✅ Shipped — timetables + booking + approvals + cancellations live; mails queued from the real Actions; suite 266/266 |
| Final | Thesis Chapters 5–7, documentation, viva prep | 🔲 |

### Success criteria to verify per objective (from Ch1)
1. Engine returns correct common free slots for multi-cohort combos in <500ms; capacity filter excludes small rooms; all edge cases handled.
2. Two simultaneous submissions for same slot → exactly one succeeds, other gets rollback alert; outcomes logged in audit trail.
3. Queue sorted by submission timestamp; pre-validated slots; 1-click approve; mandatory rejection reason; audit trail complete.
4. Student view-only; lecturer create/submit/cancel own; PL hybrid; unauthenticated users redirected to login; emails fired on state transitions.
5. All seed data loads without FK errors; all 3 roles log in with test credentials; each objective's criteria verifiable via test cases.

---

## 13. Key Files Map

```
AGENTS.md                            ← session rules (auto-loaded) — read first alongside this file
CodingMAIN.md                        ← this file (canonical)
docs/venue-restrictions.md           ← B005/B006/P→labs rules (B005 enforced, rest doc-only)
docs/archive/                        ← superseded docs (FYP-BRIEFING, BACKEND-TASKS, LEFTOVER_TASKS, CodingMAIN variants, the FCB palette rationale, the Livewire JS investigations, the Nov-1 recovery record)
routes/web.php                       ← auth + Livewire page routes
app/Livewire/                        ← real-data timetable components (MyTimetable, CohortTimetable, StudentMyTimetable, VenueTimetable)
app/Services/                        ← MatrixIntersectionEngine, OCCValidator (+ OCCResult)
app/Http/Middleware/                 ← CheckRole, CheckPl, EnsureSessionLifetime
database/migrations/                 ← full schema (§5)
database/seeders/                    ← testing-DB seeders (demo DB is import-only)
dataset/cohorts.md, lecturers.md     ← master datasets
resources/views/layouts/ui-template.blade.php   ← shared layout (inheritance)
resources/views/partials/            ← shared partials (nav, legend, summary, week-nav, modals, …)
resources/views/livewire/            ← real-data page blades
resources/views/ui-design-templates/ ← frozen reference templates (upstream f8b35a2)
public/css/theme.css                 ← ALL shared CSS + block classes
public/js/ui-common.js               ← shared JS helpers (grid builder, WeekNavigator, computeSummary, …)
public/js/mock-data.js               ← fallback data (window.MockData)
tests/Feature/                       ← PHPUnit (MatrixIntersectionEngine, OCCValidator, VenueRestrictionConflict, …)
tests/*.spec.ts                      ← Playwright (venue-db, timetable-wiring, nav-identity, pages-parity gates)
page-changelogs/                     ← per-page change logs
.sdd/changes/ + .sdd/archive/        ← SDD workflow
prompts/                             ← SDD templates + run prompts
```

---

## 14. Notes for Future Sessions (Forking Context)

- **Before coding:** read `AGENTS.md` + this file, check `.sdd/changes/` for active proposals, run `git status`/`git log`.
- **Always:** update the matching `page-changelogs/*.md` for UI changes; follow the SDD flow for non-trivial features; never push without explicit user authorization.
- **Demo DB is fragile:** never `migrate:fresh` / re-seed the demo DBs (`class_replacement_fresh` / `class_replacement_demo`; HP: `class_replacement`) — tests use `class_replacement_testing` via `DB_DATABASE=…`. Remember the ⛔ `--env=testing` trap (no `.env.testing` on the machine — it silently boots `.env` = the demo DB) and the snapshot law: verified `pg_dump` before any demo-DB write and after any milestone (§4 Database safety). Restore recipe + the canonical viva-state dump: `/home/philler/Desktop/tarumt/backups/README.md` (HP: `/home/jinglinux/tarumt/…`).
- If the database isn't reachable, check `php artisan migrate:status` and that PostgreSQL is running (`pg_isready`).
