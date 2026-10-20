# Tasks — last-visit-dd-memory

- [ ] T1. `public/js/ui-common.js` — the `LastVisit` helper (D1 verbatim:
  `key/remember/recall/forget`, sessionStorage, the id-suffixed keys,
  try/catch-guarded, exported to `window`).
- [ ] T2. `resources/views/livewire/cohort-timetable.blade.php` — the
  remember gate + the restructured guard chain per D3:
 (a) `onCohortChange`/`onFacultyChange` gain the optional `{ origin }`
  second arg; the inline onchange handlers pass `{ origin: 'user' }` (the
  remembering runs ONLY there, on a real pick whose identity reuses
  `MockData.currentUser.staffId` — the explicit source per D2; the
  programmatic paths and the restore never call remember);
 (b) the INIT tail's staff `else if` restructures to the D3 pasteable
  guard chain (the D7-restored select direct-sets; the 2026-10-13 default
  rule verbatim in the `else` arm).
- [ ] T3. `resources/views/livewire/venue-timetable.blade.php` — D4:
 (a) hoist `servedVenues = @json($venuesJs)` + switch the dd constructor's
  opts to `venues: servedVenues`; the identity read =
  `@json((string)(auth()->user()?->lecturer?->staff_id ?? ''))`;
 (b) the restore block right after `venueInfo` + before the dd
  construction (the `?venue=` absence + `servedVenues.some(v => v.code ===
  savedVenue)` + `savedVenue !== venueInfo.code` → the one-time
  `location.replace`);
 (c) the remember call inside the page's OWN `onSelect` closure (never in
  the shared class).
- [ ] T4. `tests/last-visit-dd.spec.ts` (new) — the D5 matrix, S1–S7:
  - S1 **the reload-restore**: 5770 → pick (FOCS, RSD2(S1)G1) → reload →
    assert the faculty select = the FOCS faculty id, `cohortSelect.value` =
    the RSD2(S1)G1 id (string — the restore direct-sets), `weekSelect.disabled ===
    false`, and the grid renders THAT cohort's own events (a presence
    check, not dropdown-only).
  - S2 **the venue auto-land (SAME-tab)**: 5770 lands `?venue=B010` →
    pick a venue via the dd (a REAL `onSelect` — remember fires) → then
    `page.goto('/venue-timetable-ui')` (NO `?venue=`, same tab, D5's
    same-page rule) → assert: the trigger shows the PICKED code (+ its
    full name) AND the venue grid renders THAT venue's rooms (a presence
    check beyond the dropdown value; B010 = the remembered state).
  - S3 **the explicit `?venue=` wins**: in the SAME tab (riding S2's
    remembered B010) → `page.goto('/venue-timetable-ui?venue=B002')` →
    the trigger shows B002, never B010 (B002 = param-only — no memory
    write; the `lastvisit-venuePage-*` key still holds B010).
  - S4 **the same-tab logout→login swap (the 🔴1 row)**: staff 5425 →
    pick a cohort → in-page logout → login 5770 → arrive without
    `?venue=` → assert **5770's ARRIVAL DEFAULTS** (own faculty + first
    taught cohort / the server default venue), never 5425's memory.
  - S5 **the stale/stub fallback**: an `addInitScript` write of a bogus
    pair under the id-suffixed key `lastvisit-cohortPage-5770` (the DTO
    via `JSON.stringify` — never a bare unsuffixed key) → the page falls
    back to the default rule silently (no error states).
  - S6 **(a) the non-taught restore**: the non-taught but payload-valid
    pick RESTORES (a browse cohort of the faculty's payload — the dd
    lists it) — the R4(a) decision's pinned row.
  - S7 **the no-remember negative gate**: staff 5770 arrives UNTOUCHED
    (the 2026-10-13 default rule auto-picks) → reload → the same default
    lands again (assert the selects) + the sessionStorage contains NO
    `lastvisit-cohortPage-*` keys at all (page.goto 5770's arrival
    twice; inspect the keys).
  - Runner: `--project=chromium --grep-invert "TC70"` — the standing
    guard.
- [ ] T5. Changelogs: `cohort-timetable-changelog.md` +
  `venue-timetable-ui-changelog.md` (+ the one-line mechanical note in
  `venue-dropdown-changelog.md`); then the stale-cache ritual and the in-
  scope Playwright rerun (the new suite + the residence suites); NO git
  push; no demo-DB writes.
