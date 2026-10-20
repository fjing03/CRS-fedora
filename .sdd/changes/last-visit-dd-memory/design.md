# Design — last-visit-dd-memory

## D1 — the shared helper (`ui-common.js`, ONE new global)
```js
/* LastVisit — the dd memory (SDD last-visit-dd-memory; per-tab sessionStorage,
   keys are id-suffixed — the user isolation hook). The id arrives EXPLICITLY:
   the helper NEVER reads MockData identity on real-DB pages. */
const LastVisit = {
    key(name, id) {
        return 'lastvisit-' + name + '-' + (id || '');
    },
    remember(name, value, id) {
        try { sessionStorage.setItem(this.key(name, id), JSON.stringify(value)); }
        catch (e) { /* private-mode / quota: the memory is a nicety — silence */ }
    },
    recall(name, id) {
        try {
            const raw = sessionStorage.getItem(this.key(name, id));
            return raw === null ? null : JSON.parse(raw);
        } catch (e) { return null; }
    },
    forget(name, id) {
        try { sessionStorage.removeItem(this.key(name, id)); } catch (e) {}
    },
};
window.LastVisit = LastVisit;
```
- A future "reset my dd" affordance rides `forget` (no UI now).

## D2 — the identity feeding (THE leak guard)
- **Cohort page**: the payload's `staffId` (the blade already seeds
  `MockData.currentUser` with the REAL `staff_id`, page :169) → the page
  script passes `MockData.currentUser.staffId` EXPLICITLY into
  `remember/recall`.
- **Venue page**: the blade gains ONE line seeding the REAL identity (the
  page lever leaves `MockData.currentUser` untouched today — the mock
  `'5770'` leak). Same line-shape as the cohort page's (the user's
  lecturer row; `''` when the viewer has no lecturer row).
- The pages NEVER let the helper self-resolve identity.

## D3 — the cohort page flow (the remember/restore gates)
- **Remember** — ONLY the user-initiated path: the native `cohortSelect`'s
  inline `onchange` (`onCohortChange(this.value)` — blade :119) and the
  `facultySelect`'s `onchange` fire `onFacultyChange(this.value)` — inside
  `onCohortChange`, when `selectedCohortId` resolves to a REAL pick AND the
  call is USER-origin, `LastVisit.remember('cohortPage', {f: fid, c: cid},
  staffId)`.
- **The user-origin thread**: `onFacultyChange` + `onCohortChange` take an
  optional second arg `{ origin: 'user'|'programmatic' }`; the inline
  onchange handlers pass `'user'`; the arrival default, the faculty-switch
  auto-pick (`document.getElementById('cohortSelect').value = ...;
  onCohortChange(..., { origin: 'programmatic' })`), and the restore path
  pass `'programmatic'` (or omit — default programmatic). ONLY
  `origin === 'user'` calls remember (gated by the real-pick check).
- **Restore** — in the INIT section, INSIDE the staff `else if` BEFORE the
  2026-10-13 default rule:
  ```js
  const saved = LastVisit.recall('cohortPage', staffId);
  if (saved && !isStudentViewer) {
      const fac = facultyData.find(f => String(f.id) === String(saved.f));
      const cohort = fac ? fac.cohorts.find(c => String(c.id) === String(saved.c)) : null;
      if (fac && cohort) {
          facultySel.value = String(saved.f);
          populateCohorts(String(saved.f));
          document.getElementById('cohortSelect').value = String(saved.c);
          selectedCohortId = parseInt(saved.c, 10);
          document.getElementById('weekSelect').disabled = false;
          refreshTips();
          buildTimetable();
      } // else → the default rule below plays
  }
  ```
  - The restore preselect DIRECT-SETS the selects (never routes through
    `onCohortChange` — the remember gate stays single-path).
  - **Taught status does NOT gate** (D-P-design decision, user-blessed via
    the proposal's R4(a)): staff browse non-taught cohorts; the dd lists
    them. Payload-validity gates the restore instead.
  - The restored `currentWeek` = the current-week logic runs afterward as
    today (the WeekNavigator memory untouched).
- **Students**: the FIRST branch (the `selectedCohortId`/FR 1.2 pin) still
  wins; the restore never reaches them.

## D4 — the venue page flow
- **Remember** — `venue-timetable.blade.php`'s OWN `onSelect` closure:
  ```js
  onSelect: function (code) {
      LastVisit.remember('venuePage', code, venueOwnerStaffId);
      if (code && code !== venueInfo.code) {
          window.location.assign('?venue=' + encodeURIComponent(code));
      }
  }
  ```
  (`VenueDropdown.select()` fires `onSelect` only on a real pick — the
  constructor's `initialCode` path does not fire — the init is safe.)
  ⚠️ NO edit inside `VenueDropdown` (ui-common.js) — replacement-
  arrangement's dd must not remember.
- **Restore** — the ONLY safe placement: inside the `DOMContentLoaded`
  handler of `venue-timetable.blade.php`, immediately after
  `const venueInfo = @json($venue);` (line 193) and immediately before
  `new VenueDropdown(` (line 197). Hoist `const servedVenues =
  @json($venuesJs);` alongside it and switch the constructor's opts line
  (:200) from `venues: @json($venuesJs)` to `venues: servedVenues`:

  ```js
  const servedVenues = @json($venuesJs);
  /* Restore (last-visit-dd-memory) — the ?venue= absence guard + the
     known-code check ride the SERVED list (one const, two consumers:
     the dd opts + this guard; the name names what the blade served). */
  const urlVenue = new URLSearchParams(window.location.search).get('venue');
  const ownerStaffId = @json((string) (auth()->user()?->lecturer?->staff_id ?? ''));
  const savedVenue = urlVenue ? null : LastVisit.recall('venuePage', ownerStaffId);
  const known = servedVenues.some(v => v.code === savedVenue);
  if (!urlVenue && known && savedVenue !== venueInfo.code) {
      window.location.replace('?venue=' + encodeURIComponent(savedVenue));
      return;   // the rest of the init runs after the replacement render
  }
  ```
  (The `servedVenues.some(...)` check doubles as the known-code guard —
  `VenueDropdown.select()`'s ignore-unknown behaviour lines up. The const
  names are Three distinct things: `servedVenues` = what the blade
  served, `savedVenue` = the sessionStorage payload, `known` = the
  boolean — never conflate them.)
- **The identity line** reads the payload DIRECTLY (the same shape the
  cohort page's seed uses) and the venue page gains NO
  `MockData.currentUser` reads (its memory key rides this line alone).

## D5 — the tests (`tests/last-visit-dd.spec.ts`)
- **S1 reload-restore (cohort)**: login 5770 → pick (FOCS, RSD2(S1)G1) →
  `page.reload()` → assert: the faculty select's value is the FOCS faculty
  id, `cohortSelect.value` is the RSD2(S1)G1 cohort id (string compare —
  the restore direct-sets, per D3), `weekSelect.disabled === false`, and
  the timetable body renders the cohort's OWN events (a presence check on
  the grid, NOT just the two dropdown values).
- **S2 the venue no-?venue auto-land**: visit `?venue=B010` → navigate to
  `/venue-timetable-ui` (no param) → the trigger shows B010 (+ the full name).
- **S3 the explicit `?venue=` wins**: visit `?venue=B002` → the trigger
  shows B002 (never the remembered B010).
- **S4 the same-tab isolation (the 🔴1 row)**: login 5425 → pick a cohort →
  in-page logout → login 5770 → arrive without `?venue=` → assert 5770's
  ARRIVAL DEFAULTS (never 5425's memory) — the key-suffix mechanism's
  failing row.
- **S5 the payload-staleness fallback**: a fixture-less shape — the saved
  pair's cohort/faculty goes missing (a test-stubbed sessionStorage write
  of a bogus pair via `addInitScript`) → the page falls back to the
  default rule silently (no error states).
- **S6 (a)**: the non-taught but payload-valid pick RESTORES (a browse
  cohort of the faculty's payload — the dd lists it) — the R4(a) decision's
  pinned row.
- Runner: `--project=chromium`, `--grep-invert "TC70"` — the standing guard.

## D6 — the changelogs + the records
- `page-changelogs/cohort-timetable-changelog.md` +
  `page-changelogs/venue-timetable-ui-changelog.md` — dated entries.
- No page-changelog for ui-common (not a page); the helper's promotion is
  scoped by this change's own artifacts (the 2-page promote-on-2 rule
  note: two pages share it — the promotion files THE shared helper; a 3rd
  consumer would trigger the partial-promotion review later).

## D7 — the non-goals (restated from proposal)
- NO edits to `WeekNavigator`/`weekNav-*` keys or their localStorage; the
  venue dd of `replacement-arrangement`; the Favourites/Recent machinery;
  server-side persistence; mobile-resize behaviours.
- S5's stub builds the key via D1's formula ('lastvisit-cohortPage-5770'
  for the logged-in 5770) and writes the DTO via JSON.stringify — never a
  bare unsuffixed key name.
- S2/S4's "navigate" = page.goto in the SAME tab (per-tab sessionStorage is
  the mechanism under test; a new context voids it).
- **S7 the no-remember negative gate**: staff 5770 arrives UNTOUCHED (the
  2026-10-13 default rule auto-picks) → reload → the same default lands
  again (assert the selects) — and the sessionStorage contains NO
  lastvisit-cohortPage-* key at all (page.goto 5770's arrival twice;
  inspect the keys).
