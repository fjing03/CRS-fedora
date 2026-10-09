# Design — student-nav-remove-cohort

Frozen baseline: proposal.md (R1 PASS). Baseline context: explore-brief.md.

## 1. Nav whitelist (`partials/ui-nav-bar.blade.php`)

Delete **line 12 only** (the student `cohort-timetables` entry). Result:

```php
$isStudent ? [
    ['key'=>'my-timetable','label'=>'My Timetable','href'=>'/student-my-timetable-ui'],
    ['key'=>'replacement-history','label'=>'Request History','href'=>'/replacement-history-ui'],
]
```

Line 17 (lecturer `cohort-timetables`) and the route's `nav => 'cohort-timetables'` key stay — lecturer/PL active-state highlighting unchanged. Desktop bar and mobile drawer both render from `$items`, so one deletion covers both surfaces.

## 2. Route gate (`routes/web.php`)

`/cohort-timetable-ui` mw: `['auth']` → `['auth', 'role:lecturer']`.

- Semantics verified: `CheckRole::handle` compares `role !== 'lecturer'`; PLs carry `role='lecturer'` + `is_pl=true` → PL 5425 passes, plain lecturers pass, students 403, guests redirect to login (auth-first, bootstrap/app.php).
- The legacy-blade fallback branch inherits the same route mw — gated identically.

## 3. Test updates (3 files)

| File | Change |
|---|---|
| `tests/Feature/RouteGateMatrixTest.php` | `/cohort-timetable-ui` **stays in `ALL_ROUTES`** (L31 — it drives the guest/student/lecturer/PL assertion loops; deleting it would silently drop 4 assertions). Only the docblock bucket label changes (auth bucket empties, lecturer bucket gains it), the `LECTURER_ONLY` const (L43-50) gains the entry, and the special-cases at L108 (`|| $uri === '/cohort-timetable-ui'`) and L119 (`$uri === '/cohort-timetable-ui' ||`) are deleted. **L120 survives**: `LECTURER_ONLY` means "role:lecturer or PL" and `/request-approval-ui` keeps its own lecturer-403 special case. Docblock buckets updated to match. |
| `tests/Feature/NavIdentityTest.php:102` | Flip `assertSee('/cohort-timetable-ui', false)` to an absence assertion in the same form as the existing negative block (L105-109): `assertDontSee('href="/cohort-timetable-ui"', false)`. Also update the L99 comment "all three student links" → "both student links". |
| `tests/nav-identity.spec.ts` test 2 | Whitelist loop 3 → 2 (drop `/cohort-timetable-ui` from the expected-attached list); add `/cohort-timetable-ui` to the expected-absent list; append a direct-visit check: student `page.goto('/cohort-timetable-ui')` must NOT render the cohort page (403 response / no `.page-header` "Cohort Timetable" h1). |

## 4. Legacy-spec audit (review 🟡 — recorded for /sdd-verify)

The repo's older suites still navigate to `/cohort-timetable-ui`: `ui-regression.spec.ts`, `cancel-class.spec.ts`, `e2e/full-suite.spec.js`, `e2e/auth-wiring.spec.js` (smoke list L292), `tests/Browser/*.py`, `MANUAL-TEST-CASES.md` L162. **Audited (proposal R1): none authenticates as a student against the route — all guest or staff actors** (`timetable-wiring`'s cohort test logs in as staff `4288` → passes `role:lecturer`; its student test only visits `/student-my-timetable-ui`). No legacy suite breaks; `MANUAL-TEST-CASES.md` "Click Cohort Timetable link" remains valid in lecturer flows only. Legacy suites stay parked per standing decision — do not edit them.

## 5. Dead code (explicitly left)

`CohortTimetable.php`'s `$isStudent` branch (pinning, `pinnedCohortId`) and the cohort blade's student guards (`@if($isStudent) disabled`, week-nav disable) become unreachable after the route gate. Left in place as defensive code — precedent: the archived `b005-diploma-conflict` review left pre-existing dead branches in the same component untouched. Removing them would widen the diff onto the lecturer render path for zero behavioural gain. Do not reference them in comments as live behaviour.

## 6. Changelogs

- `page-changelogs/cohort-timetable-ui-changelog.md`: page becomes lecturer/PL-only (nav + route gate entry, dated 2026-10-09).
- `page-changelogs/auth-wiring-changelog.md`: student nav whitelist 3 → 2 (primary changelog for the nav change).
- Optional postscript in `student-my-timetable-ui-changelog.md` (nav visible on that page) — include, one line, pointing at the auth-wiring entry.

## 7. Canon

CodingMAIN.md **§6** RBAC matrix (L168) stays unedited — "View cohort timetables: Student ✅" reads as own-cohort view (FR 1.2), satisfied by Student My Timetable. Any rewording is the user's separate call. (Section is §6, not §7 — §7 is FR & NFR.)

## 8. Gates

`lint:check` · `types:check` (phpstan --memory-limit=1G) · phpunit (129 + updated) · nav-identity 3/3 (updated) · timetable-wiring 5/5 · venue-db 4/4. Records-intact: no DB writes (nav/route/test-only change). Server restart via the guarded pattern only (standalone `pkill -f "[a]rtisan serve" || true`, never `pkill -9 php`) — blade changes require the view-cache clear.

## 9. Commit plan (no push without explicit authorization)

1. `feat(nav): drop Cohort Timetables from the student side — nav whitelist + route gate` (partial + routes + tests + changelogs).
2. `docs(sdd)` archive after verify.
