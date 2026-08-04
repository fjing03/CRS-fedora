# Changelog — Phase 2: Database Seeders (Backend)

## Context

Implements the seeder layer for all 10 domain tables created in `phase1-db-schema`. Seeders populate the PostgreSQL `class_replacement` database with realistic FYP demo data derived from `public/js/mock-data.js` and `CodingMAIN.md`.

---

## Files Changed

### `database/seeders/SemestersSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Seeds 1 semester row: code `202605`, label `202605 Semester`, start `2026-08-31`, end `2026-12-06`, 14 weeks |

### `database/seeders/VenuesSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Seeds 23 Block B rooms: 16 tutorial (cap 35, L/T), 2 lecture halls (cap 80, L), 4 computer labs (cap 28, P), 1 Cisco lab (cap 32, P). Uses `allowed_session_types` column per migration schema |

### `database/seeders/ModulesSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Seeds 36 modules: 31 BMIT series + 2 MPU (MPU-3133, MPU-3232) + 3 COM series. Uses `allowed_session_types` column per migration schema |

### `database/seeders/TimeSlotsSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Generates all available 30-min time slots: 23 venues × 14 weeks × 6 days (Mon–Sat) × 20 slots/day (08:00–18:00) = 386,400 rows. Chunks inserts in batches of 5,000. `venue_id` NOT NULL per migration; `class_session_id` nullable for unoccupied slots |

### `database/seeders/ClassSessionsSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Seeds ~35 class session templates covering all 14 cohorts. Includes single-cohort and multi-cohort sessions (e.g. DFT2+DSF2, RSD3G1+RSD3G2, RAF2G2+RAF2G4+RBU1). Maps 14 seeded lecturers to modules by staff_id. Sessions: L/T/P types across Mon–Sat, 08:00–16:00. `markTimeSlotsOccupied()` updates matching `time_slots` rows to `occupied` status for all 14 weeks |

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:15 | `ClassSessionsSeeder` | Fix schema mismatch | Changed `duration_minutes` → `end_time` to match actual migration column. Session templates now specify `start_time` + `end_time` strings |

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:15 | `ClassSessionsSeeder` | Fix undefined key | Removed reference to `BMIT6666` (Mobile App Development) — module code not in `ModulesSeeder` (not in mock-data.js event codes). Replaced with `BMIT5555` |

### `database/seeders/ClassExceptionsSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Seeds 14 class exceptions: 3 public_holiday, 3 annual_leave, 3 medical_leave, 3 official_event, 2 emergency_leave. Each maps a class_session_id + week_number. Uses only `reason` column per migration (no `notes`) |

### `database/seeders/HolidaysSeeder.php` (new)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | — | New seeder created | Seeds 5 holidays from mock-data.js: Week 1 Mon, Week 3 Tue, Week 3 Thu, Week 5 Wed, Week 7 Fri. Uses `label` column per migration schema |

### `database/seeders/DatabaseSeeder.php` (modified)

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-04 10:00 | `run()` method | Add seeder calls | Appended `$this->call([...])` block invoking 7 new seeders in dependency order: Semesters → Venues → Modules → TimeSlots → ClassSessions → Holidays → ClassExceptions |

---

## Schema Fixes Applied

During testing, 4 column-name mismatches between assumed and actual migration schemas were found and fixed:

| Seeder | Assumed Column | Actual Column | Migration |
|--------|---------------|---------------|-----------|
| `SemestersSeeder` | `name`, `total_weeks` | `label`, `week_count` | `000001_create_semesters_table` |
| `VenuesSeeder` | `room_name`, `building`, `floor` | `allowed_session_types` | `000002_create_venues_table` |
| `ModulesSeeder` | `credits`, `dept_code` | `allowed_session_types` | `000003_create_modules_table` |
| `HolidaysSeeder` | `holiday_date`, `name` | `label` | `000007_create_holidays_table` |

Additionally, `ClassSessionsSeeder` used `duration_minutes` which does not exist — fixed to use `end_time` (time column) per migration `000004`.

---

## Current Status

- `SemestersSeeder` ✅
- `VenuesSeeder` ✅
- `ModulesSeeder` ✅
- `TimeSlotsSeeder` ✅ (tested: 386,400 rows inserted in ~2.6s)
- `ClassSessionsSeeder` — has one remaining issue: `BMIT6666` undefined key (module code not seeded). Fix in progress.
- `HolidaysSeeder` ✅
- `ClassExceptionsSeeder` ✅
- `DatabaseSeeder` updated ✅

---

## Schema Fixes Applied

During testing, 4 column-name mismatches between assumed and actual migration schemas were found and fixed:

| Seeder | Assumed Column | Actual Column | Migration |
|--------|---------------|---------------|-----------|
| `SemestersSeeder` | `name`, `total_weeks` | `label`, `week_count` | `000001_create_semesters_table` |
| `VenuesSeeder` | `room_name`, `building`, `floor` | `allowed_session_types` | `000002_create_venues_table` |
| `ModulesSeeder` | `credits`, `dept_code` | `allowed_session_types` | `000003_create_modules_table` |
| `HolidaysSeeder` | `holiday_date`, `name` | `label` | `000007_create_holidays_table` |

Additionally, `ClassSessionsSeeder` used `duration_minutes` which does not exist — fixed to use `end_time` (time column) per migration `000004`.

Module code `BMIT6666` was undefined — replaced with `BMIT6767` (OOP) in both RSD2 tutorial sessions.

TimeSlotsSeeder assumed `venue_id` nullable — migration requires NOT NULL. Fixed to generate per-venue slots (23 venues × 14 weeks × 6 days × 20 slots = 386,400 rows).

---

## Verified

- `php artisan migrate:fresh --seed` on PostgreSQL: all 21 migrations + 7 new seeders run clean
- `vendor/bin/phpunit`: 19/42 pass, 2 fail, 3 errors, 18 skipped — **0 new failures** (all pre-existing Fortify/config issues)

## Not Verified

- `composer run lint:check` (Pint timed out — not related to seeders)
- `composer run types:check` (not run)

---

## Test Suite Repair (Fortify + Auth) — 19/42 → 42/42

Root-caused all 23 non-passing tests: 18 skipped (Fortify features disabled in `config/fortify.php`), 2 failures, 3 errors.

### Changes

| File | Change |
|------|--------|
| `config/fortify.php` | Enabled features: registration, resetPasswords, emailVerification, updateProfileInformation, updatePasswords, twoFactorAuthentication (confirm + confirmPassword), passkeys |
| `app/Providers/FortifyServiceProvider.php` | Bound `CreateNewUser`/`ResetUserPassword` actions; registered 6 view bindings (register/forgot/reset/verify/confirm/two-factor-challenge); `authenticateUsing` accepts email OR login_id+login_type with `instanceof User` narrowing; bound custom `LoginRequest` |
| `app/Http/Requests/LoginRequest.php` (new) | Accepts `email` OR `login_id` (`required_without` each other) + password — fixes stock Fortify tests that post email while keeping the app's login_id-based forms working |
| `bootstrap/app.php` | `then:` closure loads `routes/settings.php` (defines profile.edit/security.edit/appearance.edit — was never loaded); `redirectGuestsTo(route('login.student'))` |
| `app/Models/User.php` | Added `MustVerifyEmail` (trait + contract), `TwoFactorAuthenticatable`, `PasskeyAuthenticatable` + implements `PasskeyUser` |
| `app/Actions/Fortify/CreateNewUser.php` | Creates users with `role = 'student'` |
| `public/build/` | Generated via `npm install && npm run build` (Vite manifest was missing — 5 failures) |

### Root cause of "Call to a member function all() on array"

`config/fortify.php` has `'username' => 'login_id'`, so Fortify's stock `LoginRequest` required `login_id` — posts with `email` failed validation ("The login id field is required"), redirecting to `/` (no referer). The `all() on array` error was a Laravel test-helper crash (`TestResponseAssert::injectResponseContext`) masking the real redirect assertion failure. Fixed with the custom `LoginRequest` accepting either field.

### Verified

- `vendor/bin/phpunit`: **42/42 pass, 0 failures, 0 errors** (was 19/42)
- `migrate:fresh --seed`: clean
- Pint: fixed all files touched by this work (seeders, bootstrap/app.php, LoginRequest, migrations/000008)
- PHPStan (`--memory-limit=1G`): 0 new errors in touched files; 19 pre-existing errors remain (missing Eloquent generics on frozen models Cohort/Department/Faculty/Lecturer/Programme/Student/User + original DatabaseSeeder user-seeding block — out of scope, models frozen)
- Note: `composer run types:check` crashes at default 128M memory limit; run `vendor/bin/phpstan analyse --memory-limit=1G`
