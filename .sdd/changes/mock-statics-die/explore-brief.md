# explore-brief — `mock-statics-die` (the three unwired statics; 2026-10-27)

The user's ask: *"why these ain't wired to database?? pls plan to wire it via
sdd explore"*. This brief is the explore-stage artifact: WHO consumes each of
the three statics TODAY (grep-verified), what the DB already owns, and the
proposed wiring or deletion — with the decisions for the grilling log.

Read-first sources: `docs/venue-restrictions.md` (R7 display law,
item-referenced), `database/seeders/VenuesSeeder.php` (venue-info-changes
2026-10-27 mirror), `app/Livewire/VenueTimetable.php` :135 (the REVENUE
payload), `app/Livewire/ReplacementArrangement.php` :215 (`venuesPayload`).

---

## 1. `venueRealNames` — a SINGLE live consumer, both databases true

- **Consumer (the only one):** `ui-common.js::VenueDropdown._updateTrigger`
  (:3742) — the picker's label (`{short}` → `B005 — Computer Lab 5`) and the
  hover tooltip (`{full}` → `B005 — Computer Laboratory 5 (28 seats)`).
- **Why it existed:** the mock-era payload didn't carry `room_name` at all —
  the static was a hardcoded display mirror, checked against the dataset.
- **The DB has BOTH truths now:**
  - `venues.room_name` = the FULL name (the 2026-10-27 info-changes: `Computer
    Laboratory 5` / `Computer Laboratory 4: CISCO Lab` / …);
  - the SHORT form is deterministic: `replace('Laboratory', 'Lab')` on the
    room_name gives `Computer Lab 5` / `Computer Lab 4: CISCO Lab` (the
    static's B006 short used a dash — `Computer Lab 4 - CISCO Lab` — one
    drift character; the derived value uses the colon, matching the DB).
- **Payloads already carrying the name:**
  - the ARRANGEMENT page: `venuesPayload()` includes `name` (room_name) ✓ —
    the picker there can be wired with zero new payload columns;
  - the VENUE page: `VenueTimetable`'s `$venuesJs` (:135) carries
    `room_code, room_name, room_type, capacity, description` — full coverage ✓
    — its own toolbar dropdown reads the `venueInfo` local (@json($venue)) —
    with `room_name` present ✓.
- **Proposed wiring:** the dropdown derives the pair client-side from the
  payload's `name` (full → short by the Laboratory→Lab rule; trim the
  trailing code suffix for the non-lab rooms — the existing fallback law).
  **The static map dies.**
- **Grilling decision G1:** where does the SHORT derivation live?
  - (a) client-side (pure: full minus 'Laboratory', code suffix trim) — no
    payload change, no server display law;
  - (b) server-stamped (`short_name` rides the payload next to `name` — the
    ReasonLabel/titleCase precedent: display laws live server-side).
  The explore stage recommends **(b)** — one derivation, one owner, and the
  client keeps no string surgery; the payload carries `name` + `short_name`.
- **Tie-back to the venue-info law:** the VenuesSeeder mirror (the name +
  descriptions) stays authoritative — the JS static is the only copy that
  must die.

## 2. `cohorts` — ZERO live consumers (dead registry)

- **The grep verdict:** `MockData.cohorts` is referenced NOWHERE outside
  `mock-data.js` itself (the 148 in-file hits are the §2.5 dormant mock rows'
  `cohorts:` event fields — a different key, positional-safe).
- **The docblock admits it:** *"Registry only — page event `cohort` fields
  are not joined."*
- **Where the real cohort data flows today:** per-event `cohorts` +
  `studentCounts` (baseEvent — the BMSE3153-class-info law), the request
  rows' `cohortCounts` (the cohortBreakdown law), the cohort page's payload
  (scope codes), `cohorts` table = the master (14 rows with student_count).
- **Proposed wiring:** **DELETION** (not wiring). The registry duplicates the
  real `cohorts` table row-for-row minus drift protection; any future
  consumer bridges via the payload (the BMSE3153 breakdown pattern), never
  via the static.
- **Grilling decision G2:** confirm the deletion (the §2.5 demo rows' mock
  `cohortCounts` become orphans but they are dormant anyway) — or keep a
  payload-driven `MockData.cohorts` for future joins? The explore stage
  recommends **delete**; a wiring with zero consumers is a liability, not a
  feature.

## 3. `arrangementWeeks` — ZERO live consumers (a dead scaffold from the mock demo window)

- **Its own docblock confesses:** *"was replacement-arrangement inline
  `weekData` (3 weeks, Week 11/10/9 DESCENDING). PAGE-SPECIFIC … a fixed
  demo window. Day-label quirks … PRESERVED verbatim"* — the mock-era
  arrangement walked Weeks 11/10/9 of a DEMO window (the Aug/Sep 2026 dates,
  a 04-Sep holiday flag living inside it and NOT in `holidays`).
- **The grep verdict:** `MockData.arrangementWeeks` is consumed NOWHERE
  outside `mock-data.js` (3 in-file refs = its own declaration + the
  holidays comment).
- **The real scaffold today:** the live arrangement builds `weekData =`
  `generateWeekData()` from **`MockData.semester` (WIRED) + `holidays`
  (WIRED)** — the payload-driven 14-week walk (`ReplacementArrangement`
  computes start/end/weeks server-side and the semester JS rides the
  payload). The static's odd weekday-quirk rows are gone from every surface.
- **Proposed wiring:** **DELETION** (not wiring). The scaffold was superseded
  by the semester-driven walk.
- **Grilling decision G3:** confirm the deletion. The one thing worth
  pinning BEFORE the delete: the §2.10 comment's "04-Sep holiday flag" —
  verify the REAL holidays table carries the 4 pairs (Deepavali Sun
  08 Nov/Mon in-lieu/W14 Christmas Eve+Day — the 2026-10-23 seed ✓
  verified 3 rows in the demo DB — confirm no missing pair from the mock
  was relied on anywhere (the mock's W14 Thu/Fri pair exists in the DB ✓).

## 4. The blast radius of the deletions

- `mock-data.js` sheds ~90 lines (the §2.3 cohorts block, the §2.10
  arrangementWeeks block, the §2.5b venueRealNames block).
- The ONLY behavioral surface to regression-check: the venue picker on ALL
  surfaces (the arrangement + the venue page + the cohort page) — the
  short label + the full tooltip (the user's 2026-10-27 ask, byte-stable).
- Playwright receipt: the picker hover (`B005 — Computer Laboratory 5 (28
  seats)`) + the label (`B005 — Computer Lab 5`) — the A/B/C/D receipts
  from the earlier venue-tip verification re-runnable verbatim.
- The MOCK-era spec fixtures referencing `MockData.cohorts` /
  `arrangementWeeks`: none (grep-verified) — the deletion is quiet.

## 5. The open decisions (for the grilling log)

| # | Decision | Options | Explore recommendation |
|---|---|---|---|
| G1 | where the SHORT label derives | (a) client string-surgery; (b) server-stamped `short_name` in the payload | (b) — the display law lives server-side (the ReasonLabel precedent) |
| G2 | `cohorts` static | delete vs payload-rebuild | delete (no consumers) |
| G3 | `arrangementWeeks` static | delete vs payload-rebuild | delete (0 consumers; the real walk is wired) |
| G4 | the deletions' scope | mock-data.js only vs also pruning the dormant §2.5 mock demo rows | map-level first; the dormant demo rows stay (the spec fixtures' era anchors — a separate lane) |

## 6. The task sketch (if G1(b) + G2/G3 deletes lock)

- T1: the venues payloads (arrangement `venuesPayload` + the VENUE page's
  `$venuesJs` + `$venue` local?) gain `short_name` (the server-side rule
  `str_replace('Computer Laboratory', 'Computer Lab', room_name)` — plus the
  non-lab rooms' short = the room_name minus the code suffix, trimmed);
  `VenueDropdown._updateTrigger` reads them; the static `venueRealNames`
  dies from mock-data.js.
- T2: the `cohorts` + `arrangementWeeks` blocks deleted from mock-data.js.
- T3: the receipts (the picker label/tooltip Playwright A–D re-run + the
  phpunit compact trio) + the changelogs (the venue pages) + ONE `feat:`
  commit.
