# Review Log — frontend-read-wiring

## proposal Round 1 — 2026-08-06

### 🔴 Fixed
- Config claim corrected: no RouteServiceProvider in repo (Laravel 13) — `routes/api.php` must be registered via `withRouting(api: ...)` in `bootstrap/app.php`; `bootstrap/app.php` added to Impact Scope.
- Missing `ReplacementRequestsSeeder` decision made: small seeder (demo requests across statuses + one conflicting class) added to scope so request pages render real rows; AC1 updated to require non-empty request pages.
- Semester calendar shift acknowledged as a risk: seeded semester (2026-08-31 start) replaces mock (2026-06-15); API must supply `chipText` so chips never render undefined; e2e specs built against real calendar.

### 🟡 Addressed
- Wording: "9 tests in 5 specs" replaces "9 specs".
- Engine risk mitigation: no `day` param on `findAvailableSlots()` — pre-filter by venue/session_type/cohort, result-level day filtering.
- Endpoints row now mentions "+ venues" (arrangement endpoint returns venues section per brief §4).

### 🔴 Outstanding
- (none)

## proposal Round 2 — 2026-08-06

### 🔴 Fixed
- Round 1 blockers verified resolved (api routing via withRouting, seeder in scope + AC1, chipText risk row).

### 🟡 Addressed
- `DatabaseSeeder.php` registration row added to Impact Scope (declarative).
- Status header updated to "Frozen (Batch 1)".

### 🔴 Outstanding
- (none) — batch passes.

## design Round 1 — 2026-08-06

### 🔴 Fixed
- A10 rewritten: top-level `await` removed (SyntaxError in classic inline scripts) — each template's existing DOMContentLoaded render callback becomes `async` with `await loadMockSection(...)` as first statement; `loadMockSection` helper added to ui-common.js.
- `cancelledFlags` source corrected: `class_exceptions` (joined to active cohort's sessions, key = week_number − 1), not replacement_requests; `class_exceptions` added to §5 deps and conflicted-class derivation.
- Venue contract pinned: `{code, type (room_type mapped to PascalCase Tutorial/LectureHall/Lab/CiscoLab), capacity, allowedSessions}` — schema has no `name` column; DB snake_case types mapped to page's PascalCase values.
- conflictedClasses field map added + reason derivation switched to `class_exceptions` (mapped Title case, all 5 reasons have badgeClass) ∪ holidays overlap; dropped unstyled `'Pending Replacement'` reason.

### 🟡 Addressed
- Grid bound corrected to 08:00–18:30 (hi 0..21, 22 entries — matches ui-common.js `hours` and page's missing-triple→available branch).
- A5 corrected: `myTimetable.eventsByWeek` keys are 0-indexed (0..13); `seedWeek` = 11 and key 11 must exist; engine `weekNumber` is 1-indexed (DB CHECK 1–14).
- A6: `requestedAt`/`requestedBy` specified as flat event fields (pages read flat), not nested.
- A9: default `cohort_ids` pinned = resolved lecturer's first class_session's cohort ids (page has no picker).
- `rsd3g2Base` populated in the cohort endpoint too (shared `cohortBaseEvents()` helper) so RSD3(S1)G2 renders on cohort-timetable (page unconditionally rebuilds that cohort from base).
- A3 chipText example corrected to `~ 06-Dec-2026` (seeded end date).
- `replacementTime` format pinned: `"H:i – H:i"`.
- `meta/cohorts` marked contract-only (no template reads `MockData.cohorts`).
- Known cosmetic shifts recorded: DB display codes `DFT2(S1)G1` vs mock `DFT2 (S1)`; semester chip uses seeded calendar.

### 🔴 Outstanding
- (none — pending round 2 re-review)

## design Round 2 — 2026-08-06

### 🔴 Fixed
- A10 rewritten to fix parse-time ordering: every page builds its MockData structures at script-parse time (MyTimetable L203–248, CohortTimetable L133–194, Student L65–131, History L556, Home L234, Arrangement L987–988), BEFORE any DOMContentLoaded callback — so an async callback alone left stale parse-time copies. Design now specifies relocating those parse-time derivations INTO the async DOMContentLoaded callback after the `await loadMockSection(...)`; A12 updated with the explicit carve-out.
- §7 student fixture made data-true: first Student belongs to DFT1(S1)G1 (sessions BMIT2020, ids 24–25) which has NO class_exceptions rows — dropped the unfulfillable "week-14 BMIT7070 cancellation" e2e assertion (BMIT7074 belongs to RSD3(S1)G1); e2e asserts base BMIT2020 cells instead; cancellation-flag path covered by feature tests.

### 🟡 Addressed
- `venues[]` pinned = exactly the keys of `venueSlots` (dropdown lists only engine-eligible venues; no stale fully-available fallback).
- `remarks` content pinned (`d-M-Y` replacement date; pages render `(Replaced for ...)` verbatim) + `requestedAt` pinned display format `d M Y, g:i A` (modals render verbatim).
- conflictedClasses dedup pinned: one row per (session, week), exception wins over holiday.
- A8 note: seeded time_slots grid ends 17:30 → hi 20–21 synthesized as occupied.
- A11 display-code formula pinned: `"{programme_code}{current_year}(S{semester})G{tutorial_group}"` (matches DatabaseSeeder::cohortCode() L189–198).
- §7 cosmetic shifts extended: student page hardcoded header chip `RSD3(S1)G2` stays as-is (mismatch with DFT1(S1)G1 demo student recorded).

### 🔴 Outstanding
- (none — pending round 3 re-review)

## specs Round 1 — 2026-08-06

### 🔴 Fixed
(none)

### 🟡 Addressed
- 16 E-rows covering all 12 design decisions + 5 proposal ACs + cross-module requirements (api.php registration, DatabaseSeeder, ui-common helper, relocation, tests, quality gates). Field-format appendix consistent with design §3 and page verbatim renders. Scenarios cover fallback (S-3), engine (S-4), seeder (S-5), e2e (S-6), quality gates (S-7).

### 🔴 Outstanding
- (none — batch passes)

## tasks Round 1 — 2026-08-06

### 🔴 Fixed
- Task 3 `loadMockSection` wrapping corrected to `Object.assign(window.MockData, data)` directly (API responses already match MockData top-level shapes; no section-key wrapping needed).
- Task 19: student timetable e2e assertion added (base BMIT2020 cell from DFT1(S1)G1) per design §7.

### 🟡 Addressed
- Tasks 5/6/10 noted as potentially exceeding 2 hours (complex queries + tests); acceptable.

### 🔴 Outstanding
- (none — batch passes)

