# fedora-frontend-legacy — artifacts preserved from the `fedora-frontend` branch

Ported 2026-10-10 from `origin/fedora-frontend` by the MSI session (change
`.sdd/changes/msi-handoff-integration/`, proposal §1.1). **These 15 files existed
on no other branch, machine, or clone** — everything else unique to
`fedora-frontend` was either byte-identical to a `fedora-backend` copy (47 files),
an older draft superseded by a newer copy already in `.sdd/archive/` (~27 files),
or superseded code (3 mock-era seeders, `playwright.config.js`, `BACKEND-TASKS.md`).

## Contents

| Path here | What it was | Status |
|---|---|---|
| `frontend-read-wiring/` | SDD for wiring the 6 read-only pages from `MockData` to a Backend API (`/api/v1` read endpoints). Header says **"Status: Frozen (Batch 1)"** — a frozen decision record, hence the port. | **Superseded**: the approach was replaced by the Livewire direct-Eloquent wiring in `wire-backend-into-refactored-ui` (Slices A/B/C); the `/api/v1` routes it fed were commented out as orphans in `b1af915`. Kept for the decision rationale. |
| `playwright-ui-smoke-suite/` | Original Playwright smoke-suite SDD (mock-era pages). Header: "Draft — NOT frozen". | **Superseded**: current gate set is `venue-db`, `timetable-wiring`, `nav-identity`, `pages-parity` (see `CodingMAIN.md`). Kept as history. |
| `tests/Feature/Api/ApiReadEndpointsTest.php` | Feature tests for the `/api/v1` read endpoints. | **Parked with its routes**: the endpoints are commented out (`b1af915`), so this test is not runnable and is NOT part of any phpunit testsuite here (phpunit loads only `tests/Unit` + `tests/Feature`). Restore it only if the API routes are deliberately revived. |

## Standing notes

- **`fedora-frontend` has NOT been deleted** — deletion is on explicit user hold
  until this port is pushed to origin. Nothing in this folder authorizes deletion.
- Do not re-activate these as live SDD changes: everything under this folder is
  archive material. Read-only reference.
- Original branch state at port time: `origin/fedora-frontend` @ `35a51d1`
  (2026-10-10 check).
