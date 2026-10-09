# Explore Brief — student-nav-label-parity

Date: 2026-10-09 · Trigger: user report — student nav labels differ per page: `/student-my-timetable-ui` shows "My Timetable" + "Request History" while `/replacement-history-ui` shows "Student My Timetable" + "Replacement History". User decision: **"i want 'My Timetable' & 'Replacement History'"** on the student side.

## Root cause (verified)

- Shared whitelist: `partials/ui-nav-bar.blade.php` `$roleItems` (student: My Timetable → `/student-my-timetable-ui`, Request History → `/replacement-history-ui`). Rendered when a page does NOT pass `navItems`.
- `/replacement-history-ui` serves the **legacy template** `ui-design-templates/replacement-history-UI-design-template.blade.php` — its Livewire component `App\Livewire\ReplacementHistory` does not exist (Wave 3b, parked), so the route dispatcher falls back: `fn () => view($page['legacy'], ...)` (routes/web.php:91-94). That template `@extends('layouts.ui-template', [... 'navItems' => [...]])` with **mock-era labels** ("Student My Timetable" / "Replacement History"), which override `$roleItems` (ui-nav-bar line: `$items = $navItems ?? $roleItems ?? []`).
- The other legacy-served pages (`/replacement-home-ui`, `/replacement-arrangement`, `/my-request-history-ui`, `/request-approval-ui`) do NOT pass `navItems` — they already inherit the shared whitelist. Only TWO templates override: the live `replacement-history` one and the fallback-only `student-my-timetable-UI-design-template.blade.php` (its component exists → template unused at runtime).
- No automated test pins the student "Request History" label (nav-identity.spec.ts + NavIdentityTest assert hrefs/keys only). `MANUAL-TEST-CASES.md` + `e2e/*` references are to the LECTURER page `/my-request-history-ui` — untouched.

## Rejected approaches

1. **Fix labels inside the replacement-history template's own `navItems`** — rejected: keeps two sources of nav truth; the divergence the user hit is exactly this override's fault. House rules (§10.0.3 OOP/shared partials; AGENTS.md DRY) point the other way.
2. **Build the `ReplacementHistory` Livewire component now** — rejected: that is Wave 3b wiring, explicitly parked pending user go. This change is display-parity only.
3. **Also rename the LECTURER nav item** ('Request History' → /my-request-history-ui) — out of scope: user said student side; lecturer page has a different name/meaning ("My Request History").

## Final solution

| Edit | File | Detail |
|---|---|---|
| 1 | `partials/ui-nav-bar.blade.php` | Student whitelist label `'Request History'` → `'Replacement History'` (key `replacement-history` + href unchanged → activeNav highlighting intact) |
| 2 | `ui-design-templates/replacement-history-UI-design-template.blade.php` | Delete the `navItems` override from `@extends(...)` — page inherits the shared whitelist; `homeUrl` + `activeNav` + `pageKey` stay |
| 3 | `ui-design-templates/student-my-timetable-UI-design-template.blade.php` | Same override deletion (fallback-only today; prevents the same divergence recurring if it ever serves) |

Result: both student pages show **My Timetable** + **Replacement History**, from the single whitelist. Page h1 titles unchanged ("Student My Timetable" / "Replacement History" — page identity, not nav).

## Known open questions

- The replacement-history page BODY is still mock-era static content until Wave 3b wires it — explicitly out of scope here.
