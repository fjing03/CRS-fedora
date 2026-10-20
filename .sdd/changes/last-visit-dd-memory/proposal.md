# Proposal — last-visit-dd-memory

## Why
The three dropdowns the user actually navigates with — the cohort page's
**Faculties dd**, **Cohorts dd**, and the venue page's **Venue dd** — forget
the visitor on every reload, while the WEEK selector already remembers
(`WeekNavigator`'s storage keys). The user's verbatim ask (2026-10-19/20):
"make/save the user's last visit (in the same login session), faculties dd &
cohorts dd (Cohort page) & venue dd (Venue page)." — the dds should restore
the last visited pair/venue INSIDE the same login session (per-tab),
silently falling back to the existing arrival rules when nothing valid is
remembered.

## In scope
- **ONE shared helper** in `public/js/ui-common.js`: `LastVisit` with
  `remember(name, value, id)` / `recall(name, id)` / `forget(name, id)` —
  **sessionStorage, per-tab** (it survives the same tab's reloads and in-tab
  re-logins; the user's "same login session" bound is honoured by the
  **id-suffixed key**, not by the storage engine). `remember/recall` take
  the identity EXPLICITLY (the payload's `staffId` string, `''` when the
  viewer has none — never a fabricated `0`), keyed
  `lastvisit-<name>-<staffId>`: different users never share keys; the same
  user in the same tab restores (accepted; arguably desirable). The helper
  is try/catch-guarded (private-mode safety) and NEVER reads
  `window.MockData.currentUser` for the identity (the mock leak is
  forbidden on real-DB pages).
- **Cohort page**: remember `{facultyId, cohortId}` ONLY on a USER-INITIATED
  pick (the native select's inline `onchange` path — NOT
  `onFacultyChange`'s programmatic auto-pick, NOT the arrival default
  branch, NOT the restore path itself: those never call remember; the
  restore preselect reuses the pinned-student code shape which sets
  `select.value` DIRECTLY, without routing through `onCohortChange`).
  Restore on arrival ONLY for staff (the FR 1.2 student pin stands) and
  ONLY when the saved pair is still payload-valid (the cohort exists in
  the saved faculty's payload) — **taught status does NOT gate the
  restore** (staff legitimately browse non-taught cohorts; the dd lists
  all of them). When nothing is saved or the pair is no longer in the
  payload, the existing 2026-10-13 default rule plays (own faculty + first
  taught cohort).
- **Venue page**: remember the code in `VenueDropdown.onSelect` (right
  beside the existing `?venue=` assign); restore on load ONLY when
  `?venue=` is ABSENT (the URL stays the SSOT) and the saved code exists in
  the served `venuesJs`, via ONE `location.replace('?venue=' + code)`
  (the existing full-render; no history spam).
- **Tests**: `tests/last-visit-dd.spec.ts` — the cohort pair's reload
  restore; the stale/invalid pair fallback; the venue's no-`?venue`
  auto-land + the explicit-`?venue`-wins row; a fresh context (new login)
  → the defaults (session-backed, NOT user-backed).
- **Changelogs**: `cohort-timetable`, `venue-timetable-ui`.

## Explicitly NOT in scope
- The **week selector's** localStorage memory (`WeekNavigator`'s keys) —
  already works; untouched.
- The **replacement-arrangement** page's venue dd and any other dd (request
  history etc.) — different contexts, no "last visit" meaning.
- `VenueDropdown`'s Favourites/Recent machinery — the 2026-10-19 user
  directive froze that code COMMENTED-OUT first; this feature does not
  un-comment, re-shape, or read it.
- Server-side persistence of any kind (a demo-DB write = out of the
  question; the memory is a per-tab session concern).
- Mobile widgets' RESIZE re-renders (the existing `populateWeekSelect`
  registry handles changes; the dd memory does not interact with them).

## Impact scope
| File | Change |
|---|---|
| `public/js/ui-common.js` | + the shared `LastVisit` helper (≈30 lines, ONE new global; no existing-code edits) |
| `resources/views/livewire/cohort-timetable.blade.php` | the remember hooks in `onCohortChange`/`onFacultyChange`'s pick branch + the arrival-restore branch (staff-only, validity-guarded) |
| `resources/views/livewire/venue-timetable.blade.php` | the remember call in its OWN `onSelect` closure (⚠️ NOT inside the shared `VenueDropdown` class — no hook/flag/edit there; replacement-arrangement's dd must not remember) + the arrival-restore branch (the `?venue=`-absent guard) + **ONE added line seeding the real identity** (`MockData.currentUser = @json([...staff_id...])` — the page never sets it today; without it the helper would key onto the MOCK `'5770'` leak) |
| `tests/last-visit-dd.spec.ts` | new Playwright suite (the restore/fallback matrix + the SAME-TAB user-swap isolation row) |
| changelogs | `cohort-timetable`, `venue-timetable-ui` |

## Success criteria
- Cohort: pick a faculty + cohort → RELOAD → the exact pair is pre-selected
  and the grid renders it (the summary cards read the restored cohort's
  selected week; the week arrows' edge-disabled states correct).
- Venue: switch to B014 → arrive on `/venue-timetable-ui` WITHOUT
  `?venue=` → the page lands B014; with an explicit `?venue=` → that wins.
- **ISOLATION ROW (`last-visit-dd.spec.ts`)**: SAME TAB: login as staff A →
  pick cohort X + venue V → **logout → login as staff B** (a different
  staffId) → arrive without `?venue=` → assert **B's ARRIVAL DEFAULTS**
  (B's own faculty + first taught cohort / the server default venue) —
  never A's remembered pair. (The key suffix is the only isolation
  mechanism; this row is the one that fails when it breaks.)
- A stale saved pair (the cohort no longer in the FACULTY payload) falls
  back silently; a **non-taught but payload-valid** saved pair RESTORES
  (the cohort dd lists all cohorts of the faculty; staff legitimately
  browse them — see R4's decision).
- The gates stay green (pint / phpstan 0 as applicable for JS/blade-only +
  the in-scope Playwright suite + NEVER TC70).
