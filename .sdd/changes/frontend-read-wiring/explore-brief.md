# Explore Brief — Frontend Read Wiring (MockData → Backend API)

> **Status:** Explore complete. Baseline for `/sdd-propose frontend-read-wiring`.
> **Date:** 2026-08-06. **Branch:** `fedora-frontend` (CRS-fedora).
> **Goal:** Replace `window.MockData` reads on the 6 read-only UI pages with live
> backend JSON from new API endpoints. No Phase 5 write operations.

---

## 1. Problem

The 6 read-only UI pages render entirely from `public/js/mock-data.js`
(`window.MockData`, 1080 lines, sections: `semester`, `holidays`, `cohorts`,
`lecturers`, `venues`, `myTimetable`, `cohortTimetable`, `studentTimetable`,
`requests`, `conflictedClasses`, `arrangementWeeks`, `venueSlots`,
`approvalRequests`). The backend (Phases 1–4) has the real schema, seeders,
MatrixIntersectionEngine, and OCCValidator — but **zero API routes and zero
controllers**, so nothing serves real data to the frontend. The two systems are
entirely disconnected.

## 2. Locked Decisions (D1–D8)

| ID | Decision |
|----|----------|
| D1 | **All 6 read-only pages wired in one change:** `/my-timetable-ui`, `/cohort-timetable-ui`, `/student-my-timetable-ui`, `/my-request-history-ui`, `/replacement-home-ui`, `/replacement-arrangement` |
| D2 | **User context = `Auth::user()` with demo fallback** — when logged in, use real identity; otherwise fall back server-side to a seeded demo user (lecturer `5425` for lecturer pages, first student for student pages). Pages stay public; smoke suite stays green |
| D3 | **API lives in `routes/api.php`, `/api/v1/...` versioned prefix**, JSON only, public (no auth middleware — user resolution handled server-side per D2) |
| D4 | **Response shape mirrors MockData section structure** (`myTimetable`, `cohortTimetable`, `studentTimetable`, `requests`, `venueSlots`, `conflictedClasses`, `arrangementWeeks`, `holidays`, `semester`, `venues`) so page JS consumes real data nearly drop-in |
| D5 | **Frontend switch = thin per-page `fetch()` bootstrap** — each page's script fetches its sections, builds MockData-shaped objects, and swaps them into `window.MockData` before render; **mock fallback on API failure** (pages never break; console warning) |
| D6 | **`/replacement-arrangement` calls the real `MatrixIntersectionEngine`** via its endpoint (first production use of Phase 3); `venueSlots` section derived from engine output |
| D7 | **Nav bar user display stays hardcoded** (upstream `auth-wiring` change, out of scope here) |
| D8 | **Upstream `request-approval` page NOT wired** (not in our routes; belongs to upstream's own change) |

## 3. Page → MockData section → Backend source mapping

| Page | MockData sections consumed | Backend source |
|------|----------------------------|----------------|
| `/my-timetable-ui` | `myTimetable`, `holidays`, `semester` | `class_sessions` WHERE lecturer = user (D2), `holidays`, `semesters` |
| `/cohort-timetable-ui` | `cohortTimetable`, `holidays`, `semester` | `class_sessions` via `session_cohorts` for selected cohort(s); cohorts list from `cohorts` |
| `/student-my-timetable-ui` | `cohortTimetable`, `studentTimetable`, `holidays`, `semester` | student's cohort → cohort sessions (D2 demo student); `studentTimetable` derived from cohort sessions |
| `/my-request-history-ui` | `requests`, `semester` | `replacement_requests` WHERE proposer = user (D2) |
| `/replacement-home-ui` | `conflictedClasses`, `semester` | recent/relevant `replacement_requests` (+ conflict status) |
| `/replacement-arrangement` | `arrangementWeeks`, `myTimetable`, `venues`, `venueSlots`, `semester` | `MatrixIntersectionEngine::findAvailableSlots()` (D6); venues from `venues`; weeks from `semesters` |

## 4. API Contract (draft, per D3/D4)

All endpoints under `/api/v1`, JSON, public, user resolved server-side (D2):

| Endpoint | Returns (MirrorData-shaped) |
|----------|------------------------------|
| `GET /api/v1/semester` | `semester` + `holidays` |
| `GET /api/v1/timetable/my` | `myTimetable` (eventsByWeek, seedWeek) |
| `GET /api/v1/timetable/cohort?cohort_id=` | `cohortTimetable` |
| `GET /api/v1/timetable/student` | `studentTimetable` + cohort context |
| `GET /api/v1/requests/my` | `requests` (proposer = user) |
| `GET /api/v1/requests/conflicts` | `conflictedClasses` (replacement-home) |
| `GET /api/v1/arrangement/slots?cohort_ids=&session_type=&duration=&venue_id=&week_number=` | `venueSlots` + `arrangementWeeks` + venues — engine-backed (D6) |
| `GET /api/v1/meta/cohorts` | cohorts list (cohort picker) |

> Exact param names/response fields finalised in design.md against each template's
> JS expectations (ground truth = the `MockData` section shapes in
> `public/js/mock-data.js`).

## 5. Key Data Flows

```
[Page JS] ──fetch("/api/v1/...")──▶ [Controller] ──▶ [Model queries / MatrixIntersectionEngine]
      ▲                                   │
      └──────── JSON (MockData-shaped) ───┘
      └── on fetch failure: fallback to window.MockData (mock-data.js) + console.warn
```

- User resolution: `Auth::user()` → if null → `Lecturer::where('staff_id','5425')` (lecturer pages) / first `Student` (student page).
- Arrangement flow: page sends cohort_ids (+optional venue/session_type/duration/week) → controller calls `MatrixIntersectionEngine::findAvailableSlots()` → response built from engine output rows (`day, start_time, end_time, venue_id, venue_code, time_slot_ids`) grouped into the page's slot grid.

## 6. Rejected Approaches & Why

| Approach | Why rejected |
|----------|--------------|
| Require login (`auth` middleware) on wired pages | Breaks public-page smoke suite; blocks on upstream auth-wiring which isn't merged |
| `user_id` query param | No dev-only leakage; worse UX; D2 demo fallback is server-side and invisible |
| Clean domain-shaped API (ii) instead of MockData-shaped (i) | Forces rewrite of every page's render JS; D4 keeps diff small; contract can evolve in Phase 5 |
| Server-side `@json()` injection into Blade | Kills the reusable API contract; couples templates to controllers; no API for future phases |
| Wire only subset (e.g. defer arrangement) | Arrangement is the engine's production debut — deferring loses the main payoff; all 6 share one contract |
| Wire upstream request-approval page too | Not in our routes (D8); upstream owns that change |

## 7. Out of Scope

- Write operations (submit/approve/reject/cancel) — **Phase 5** (`ReplacementRequestService`, `SlotConflictException`), future change
- Nav bar user display (upstream `auth-wiring` SDD, D7)
- Request-approval page (D8)
- Livewire/Inertia adoption; visual redesign of pages
- `approvalRequests` MockData section (belongs to request-approval page)

## 8. Known Open Questions (resolved in design.md)

1. Exact field-by-field shape of each endpoint response — resolved by reading each
   template's JS consumption of the corresponding `MockData` section at apply/propose time.
2. Which seeded demo student to use for `/student-my-timetable-ui` fallback (first by id — confirm at design).
3. Whether `/replacement-home-ui` needs conflict **status computation** (slot overlap vs
   request list) — mirror the `conflictedClasses` section shape; exact query in design.md.
4. Playwright: existing 9 specs must stay green; new specs for real-data rendering
   added in this change (self-gate).

## 9. Acceptance Criteria (draft)

1. All 6 pages render from real backend data with mock-data.js fallback only on API failure.
2. `GET /api/v1/arrangement/slots` returns engine-backed results; arrangement page's slot search works.
3. Existing Playwright suite 9/9 green; new e2e specs cover the 6 wired pages against real data.
4. PHPUnit feature tests cover every endpoint (200 + shape assertions).
5. `npm run build`, pint, phpstan all pass.
