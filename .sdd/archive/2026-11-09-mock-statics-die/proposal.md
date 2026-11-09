# proposal — `mock-statics-die` (2026-10-27)

## Why (the user's law chain)

"why these ain't wired to database??" → the three statics die, with the
explore recommendations LOCKED by the user's "proceed with the explore
recommendations":

- **G1(b) server-stamped:** the SHORT venue label derives server-side
  (`room_name` → `short_name`, the ReasonLabel/titleCase display-law
  precedent); the client keeps NO string surgery;
- **G2 delete:** `MockData.cohorts` (0 live consumers — the registry's
  own docblock admits it);
- **G3 delete:** `MockData.arrangementWeeks` (0 live consumers — the
  semester-driven `generateWeekData` walk superseded it);
- **G4 scope:** the three maps only; the dormant §2.5 demo demo-rows stay
  (the spec fixtures' era anchors);
- **G5 closed by the DB law:** the Deepavali Sunday row CANNOT enter
  `holidays` (the `day_of_week BETWEEN 0 AND 5` CHECK); NO insert, parity
  is not viable — the sheet records the resolution.

## Scope

| File | Change |
|---|---|
| `app/Livewire/ReplacementArrangement.php` `venuesPayload()` | gains the server-derived `short_name` per venue row (`str_replace('Computer Laboratory', 'Computer Lab', room_name)` + the non-lab trim: room_name minus the trailing room-code suffix, the existing display convention) |
| `app/Livewire/VenueTimetable.php` the `$venuesJs` + the `$venue` local | the same `short_name` derivation (the page's dropdown + the trigger read it) |
| `public/js/ui-common.js` `_updateTrigger` | reads the payload's `short_name` — the `venueRealNames` map DIES; rooms with no server value keep the current fallback (the code-suffix trim / the humanized type) |
| `public/js/mock-data.js` | the `venueRealNames` block DELETED (with its docblock), the `cohorts` registry block DELETED (§2.3), the `arrangementWeeks` block DELETED (§2.10), the stale comment line-sweep (`:66` the holidays docblock's arrangementWeeks mention; the §2.5a courses docblock's "both counts come from the MockData.cohorts registry") — the dormant §2.5 mock demo rows stay |
| `tests/e2e/full-suite.spec.js` (:507-520) | the 'MockData has venueSlots and arrangementWeeks' fixture RE-CUT: the venueSlots half stays; the `arrangementWeeks.length === 3` arm retires (the real semester walk is 14 weeks — bridging the static back would be the wrong direction). The API-level arrangementWeeks fixtures (:251-305, api-wiring :101, MANUAL-TEST-CASES.md) are the DIFFERENT `/api/v1/arrangement/slots` surface — untouched |
| `docs/demo-db-data-changes.md` | already carries the G5 resolution row (the CHECK law wins); NO DB writes in this lane |
| the changelogs | venue pages (the label law) + a small note on the cohort page (its payload already carries everything dynamic) |

OUT of scope: the dormant §2.5 demo rows; the notifications static; the
DB data (already applied by the user's own runs + the sheet).

## Acceptance

1. The venue picker on ALL surfaces keeps the SHORT label (`B005 — Computer
   Lab 5`) and the FULL tooltip (`B005 — Computer Laboratory 5 (28 seats)`)
   byte-stable **except B006's short label gains the colon** (the DB-derived
   truth `Computer Lab 4: CISCO Lab` vs the static's historical dash) —
   sourced from the DB now (rename a venue in the DB and the picker follows
   after a reload; no JS map to update). The code-suffixed rooms
   (`Tutorial Room B002`, `Lecture Hall B110`) keep their tooltip = the
   label law verbatim (the code is never duplicated).
2. `MockData.cohorts` / `MockData.arrangementWeeks` gone; the pages render
   identically (no consumer existed).
3. The phpunit suite stays green; the Playwright picker receipts re-run.
