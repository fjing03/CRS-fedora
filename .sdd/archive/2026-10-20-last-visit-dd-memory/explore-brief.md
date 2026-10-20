# Explore brief — last-visit-dd-memory (2026-10-19/20, the user request + CHOICE context)

## The user's verbatim ask
"make/save the user's last visit (in the same login session), faculties dd &
cohorts dd (Cohort page) & venue dd (Venue page)."

## What exists today (the code read)
- **Cohort page** (`resources/views/livewire/cohort-timetable.blade.php`
  + `app/Livewire/CohortTimetable.php`): on arrival the staff flow
  auto-picks the lecturer's OWN faculty (payload `defaultFacultyId`) and
  pre-picks the FIRST TAUGHT cohort of that faculty (`onFacultyChange`'s
  `taughtCohortIds` rule — the 2026-10-13 user decision). Students are
  PINNED (FR 1.2: `pinnedCohortId`, server-side). The native selects +
  the select-dd panel carry the payload's taught marks; `onCohortChange`
  wires `selectedCohortId` in-memory (lost on reload).
- **Venue page** (`venue-timetable.blade.php` + `VenueTimetable.php`):
  the venue renders ␣?venue=` full-render; the dd (`VenueDropdown`) has
  `onSelect` → `location.assign('?venue='+code)`. Arrivals WITHOUT
  `?venue=` = the server default (the component's own selection); the
  Favourites/Recent stars are DROPPED in this page's dd (the documented
  2026-10-12 divergence — mock localStorage; the groups stay commented-out
  per the 2026-10-19 user directive ⇒ no recent list to piggyback on).
- **The precedent memory in the repo**: `WeekNavigator` persists the
  viewed WEEK in localStorage ('weekNav-<page>' keys) across sessions —
  a hydration-on-load + a validity fallback shape THE new dds' memory must
  mirror (F-11's legacy-migration pattern is the model).

## The user-choice context (why sessionStorage)
"same login session" = the memory lives PER TAB (browser-session storage),
dies on the tab close, dies across users — nothing stale crosses a login
boundary. (The week navigator's localStorage semantics stay as-is; the dds
use sessionStorage — the deliberate, user-worded scope.)

## Rejected approaches
1. **localStorage for the dds** — rejected: survives logins/tabs beyond the
   user's stated scope (their verbatim: "in the same login session").
2. **Server-side persistence (a user-preferences row/col)** — rejected: the
   demo DB is sacred (never new writes from app code without an SDD wave);
   the memory is per-tab/session, not a user preference.
3. **Client-only venue re-render (no `?venue=`)** — rejected: the venue
   page's payload is venue-keyed server-side; a redesign = out of scope.
   The restore reuses the existing `?venue=` full-render.
4. **Piggybacking the VenueDropdown's (commented-out) Favourites/Recent** —
   rejected: that code is user-frozen "commented first" (2026-10-19); this
   feature is a separate, smaller contract.

## Final shape
- ONE shared helper in `public/js/ui-common.js`: `LastVisit` —
  `remember(name, value)` / `recall(name)` / a per-user key
  (`lastvisit-<name>-<staffId or 0>`), `try/catch`-guarded sessionStorage.
- The cohort page: remember `{facultyId, cohortId}` on a real pick; restore
  on arrival ONLY for staff (the student pin wins) and only when the pair
  remains payload-valid + taught (taughtCohortIds contains the cohort);
  otherwise the 2026-10-13 default rule. THE 2026-10-13 BUTTON-ARRIVAL
  RULE IS THE FALLBACK, never deleted.
- The venue page: remember the code in `onSelect`; restore on load only
  when `?venue=` is absent (the URL is SSOT), once, via
  `location.replace('?venue='+code)` (the same full-render),
  and only when the code exists in the served venuesJs.
- Tests: `tests/last-visit-dd.spec.ts` (Playwright) — the cohort pair's
  reload-restore; the stale-pair fallback; the venue's no-?venue restore +
  the explicit-?venue-wins row; the fresh-login context falls back to the
  defaults (session-backed, not user-backed).
- Changelogs: `cohort-timetable`, `venue-timetable-ui`.
