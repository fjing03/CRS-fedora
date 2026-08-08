# Proposal — Frontend Read Wiring (MockData → Backend API)

> **Status:** Frozen (Batch 1). Baseline: explore-brief.md (locked decisions D1–D8).
> **Baseline:** explore-brief.md (locked decisions D1–D8).
> **Branch:** `fedora-frontend` (CRS-fedora). **Repos:** origin `fjing03/CRS-fedora` (push), upstream `FjingXR/class-replacement-system` (pull only).

---

## 1. Why This Change Is Needed

The 6 read-only UI pages (`/my-timetable-ui`, `/cohort-timetable-ui`,
`/student-my-timetable-ui`, `/my-request-history-ui`, `/replacement-home-ui`,
`/replacement-arrangement`) render entirely from `public/js/mock-data.js`
(`window.MockData` — semester, holidays, cohorts, lecturers, venues, myTimetable,
cohortTimetable, studentTimetable, requests, conflictedClasses, arrangementWeeks,
venueSlots). Meanwhile the backend (Phases 1–4: full schema, seeders,
`MatrixIntersectionEngine`, `OCCValidator`) has **zero API routes and zero
controllers** — the frontend mock phase and backend are completely disconnected.

This change wires the frontend to the backend: new `/api/v1` JSON endpoints serve
real seeded data, each read-only page fetches its data and swaps it into
`window.MockData` before render, and the arrangement page gets its first real
production use of `MatrixIntersectionEngine`. It also gives Phase 5 (write
operations) a ready API contract to build on.

## 2. Scope

### In scope

| Item | Detail |
|------|--------|
| API endpoints | New `routes/api.php` with `/api/v1/...` JSON routes: semester+holidays, my timetable, cohort timetable, student timetable, my requests, conflicting classes, arrangement slots (engine-backed) + venues, cohorts meta (D3, D4) |
| Controllers | New API controllers (single `ApiController` or one per domain — decided in design.md) returning MockData-shaped JSON (D4) |
| User resolution | Server-side: `Auth::user()` when logged in, seeded demo user otherwise (lecturer `5425` / first student) (D2) |
| Frontend fetch layer | Each of the 6 pages: fetch its sections, build MockData-shaped objects, swap into `window.MockData` before render; **fallback to bundled mock-data.js on API failure** (D5) |
| Arrangement engine call | `/replacement-arrangement` slot search calls `MatrixIntersectionEngine::findAvailableSlots()` via its endpoint; `venueSlots` derived from engine output (D6) |
| Request seed data | New `ReplacementRequestsSeeder` (small: a few requests for the demo lecturer 5425 across statuses incl. pending/approved/rejected + one conflicting class) so `/my-request-history-ui` and `/replacement-home-ui` render real rows and the `conflictedClasses` endpoint has input |
| Tests | PHPUnit feature tests for every endpoint; Playwright specs for the 6 wired pages (existing 9 tests in 5 specs stay green) |
| Config | Register `routes/api.php` via `withRouting(api: __DIR__.'/../routes/api.php')` in `bootstrap/app.php` (repo has no RouteServiceProvider; `shouldRenderJsonWhen($request->is('api/*'))` already present) |

### Explicitly out of scope

- Write operations (submit/approve/reject/cancel) — Phase 5, future change
- Nav bar user display wiring (upstream `auth-wiring` SDD — D7)
- `/request-approval` page (not in our routes — upstream change, D8)
- `approvalRequests` MockData section (belongs to request-approval page)
- Livewire/Inertia adoption, page visual redesign, API auth middleware
- Mock-data.js removal — it remains as fallback (D5)

## 3. Impact Scope

| File / module | Type | Effect |
|---------------|------|--------|
| `routes/api.php` | New | `/api/v1/*` JSON endpoints (D3) |
| `bootstrap/app.php` | Modify | `withRouting(api: ...)` registers `routes/api.php` |
| `app/Http/Controllers/` (new controller(s)) | New | Serve MockData-shaped responses (D4) |
| `database/seeders/ReplacementRequestsSeeder.php` | New | Seed demo replacement requests + a conflicting class |
| `database/seeders/DatabaseSeeder.php` | Modify | Register `ReplacementRequestsSeeder` in the call chain |
| `resources/views/ui-design-templates/*.blade.php` (6 templates) | Modify | Add fetch bootstrap + MockData swap (D5) |
| `public/js/ui-common.js` | Modify-if-needed | Shared fetch helper (DRY: promote on 3rd duplication) |
| `public/js/mock-data.js` | Keep | Fallback source only (D5) |
| `tests/Feature/Api*Test.php` | New | Endpoint coverage (shape + 200) |
| `tests/e2e/*.spec.js` | Modify | Wired-page specs against real data |
| `.sdd/changes/frontend-read-wiring/` | Docs | This change |

## 4. Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| MockData-shape mismatch between API response and page JS expectations | Ground truth = each template's JS reads; field-level contract pinned in design.md; Playwright specs assert rendered data |
| API failure breaks pages | Mock fallback with `console.warn` (D5) — pages never blank |
| Engine endpoint slow (intersection over 16k time_slots) | Pre-filter by venue/session_type/cohort in the query; result-level day filtering; benchmark in feature test (<500ms precedent from Phase 3) |
| **Semester calendar shifts** — seeded semester (`2026-08-31` start, `SemestersSeeder`) replaces mock's `2026-06-15`; mock's precomputed `chipText` must survive the swap | API `semester` response must include `chipText` (computed from real dates) or pages compute it — chip must never render "undefined"; e2e specs built against the real calendar |
| Demo user fallback returns wrong person's data | Deterministic: lecturer `5425`, first student; documented in design.md |
| Upstream merge conflicts on templates | This change lives on `fedora-frontend`; upstream pull reviewed before apply (Playwright gate precedent) |
| Existing Playwright suite breaks | Full suite (9 tests in 5 specs) must stay green; new specs additive |

## 5. Acceptance Criteria

1. All 6 pages render from real backend data; `window.MockData` fallback only on API failure (console warning). Request pages show seeded rows (not empty) — seeder included in scope.
2. `GET /api/v1/arrangement/slots` returns engine-backed results; arrangement page search works end-to-end.
3. Every endpoint covered by PHPUnit feature tests (200 + response shape).
4. Playwright suite: existing 9 tests in 5 specs green + new specs covering the 6 wired pages.
5. `npm run build`, `vendor/bin/pint`, `vendor/bin/phpstan analyse` all pass.
