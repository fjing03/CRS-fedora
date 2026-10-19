# Proposal — lt-venue-capacity-rule

## Why
The arrangement booking path admits venue pools that contradict the project's
own venue policy and the real timetable. The user's frozen ruling (2026-10-19):
**"Lecture Hall & Tutorial Rooms are allow to have L/T"** — capacity-gated.
Three blocker receipts already exist:

1. `CodingMAIN.md` §Venue types documents tutorials as **L/T** rooms (it has
   always said so) — the strict booking filters simply never implemented it.
2. The demo DB runs **23 distinct L sessions in tutorial rooms** today (the
   assignment-era data honours the same law) — yet a NEW booking request for
   such a class (AMSE2003 L @ B100 was the live report) is offered only the
   two Lecture Halls on some surfaces and more on others — the layers
   disagree with each other.
3. The engine's CSV-column gate makes **T-in-LectureHall** engine-blocked
   while the client/list layers each interpret type differently — the ruling
   says both venues admit L **and** T, so one layer blocking T-in-LH is
   already a contract violation.

No user-visible symptom today beyond the venue families being too narrow —
but the rules disagree BETWEEN surfaces (client matrix vs service ruleMap vs
engine CSV), which is exactly the "same meaning, different law" drift the
coding rules forbid.

## In scope
- The **canonical rule map** becomes the single booking-time eligibility law:
  room_type based (DB `room_type`, not the CSV column):
  - `L → ['tutorial', 'lecture_hall']`
  - `T → ['tutorial', 'lecture_hall']`
  - `P → ['lab', 'cisco_lab']` (unchanged)
- All three existing surfaces consume that ONE map (see design).
- Capacity gate (`headcount <= capacity`) unchanged, applied in all layers as
  today.
- Pinned tests updated as **designed contract changes** (documented, not
  silent) — THREE existing tests bend:
  - the rule map's L/T rows widen (`LectureSessionCatalogTest`);
  - `ReplacementArrangementTest::test_grid_busy_vectors_and_ineligible_venues_follow_d4_rule_1`
    pins `blockedVenues` with a fixture whose B005 carries
    `room_type='lab'` + CSV `'L,T'` — under the room-type law P becomes
    B005-eligible there, so its blocked list changes by design. The
    assertion is updated, NOT the fixture (the fixture's CSV divergence is
    exactly what retiring the gate makes irrelevant — the honest delta).
- The demo-DB-dependent receipts (labs CSV = 'P') stay true empirically on
  the dataset; “P → lab + cisco_lab byte-stable” is **dataset-scoped**, not
  suite-wide — the fixture above proves the suite's expected lists were
  CSV-law-shaped.

## Explicitly NOT in scope
- **B005 (no-Diploma)** stays DOC-ONLY — the engine gains no Diploma check
  here (its own future change).
- **B006 networking-priority widening / R4 IoT exclusivity** stay
  PLANNED-NEW — untouched; `P → lab + cisco_lab` behaviour **dataset-stable**
  (the demo DB's labs stay `'P'`; the suite's lab-fixture expectations shift
  as the declared contract change above).
- The demo DB's `venues.allowed_session_types` column **is NOT edited** (the
  no-mutation abs for the demo DB stands; the column remains reference
  inventory — divergences stay visible, which is where an auditor wants them).
- No legend, colour, or status changes.
- The B005-Diploma conflict derivations (`.conflictedFor()` / the trait's
  venue-restriction conflict) stay UNTOUCHED — they key on the B005 rule,
  not the type map.
- The surfaces that DO read the map widen as designed fallout, declared +
  receipt-tested (the venue page's producer-modal session pool via
  `sessionAllowedForVenue`; the API inventory via `eligibleVenues`).

## Impact scope (files, modules, systems)
| File | Change |
|---|---|
| `app/Services/LectureSessionCatalog.php` | `ruleMap()`: L and T rows each → `['tutorial','lecture_hall']` |
| `app/Services/MatrixIntersectionEngine.php` | `eligibleVenues()`: consult `ruleMap()` over `room_type` (capacity gate unchanged; the CSV column check is retired) |
| `app/Livewire/ReplacementArrangement.php` | `venuesPayload()` comment refresh + `blockedVenuesPayload()` unchanged mechanically (it already delegates to `eligibleVenues()`) |
| `app/Livewire/VenueTimetable.php` | NO logic edit — `sessionAllowedForVenue` reads the map, its pool widens automatically (designed); stale comments refreshed |
| `app/Http/Controllers/Api/ApiReadController.php` | consumer audit only (its inventory reads the engine) — no edit expected |
| `resources/views/livewire/replacement-arrangement.blade.php` | `venueEligible()` driven by the shared `window.__ruleMap` + `rawRoomType` (L/T admit `Tutorial \|\| LectureHall`); the :2955–61 law comment rewritten; `requiredTypeHuman`/zero-fit wording → shared helper |
| `resources/views/livewire/venue-timetable.blade.php` | “hosts … only” note + `sessionTypesHuman` fold into the same shared wording helper |
| `tests/Feature/LectureSessionCatalogTest.php` | pinned L/T map rows updated (designed contract change) |
| `tests/Feature/ReplacementArrangementTest.php` | the `blockedVenues` assertion for the lab-fixture updates to the room-type law (designed contract change) |
| `tests/Feature/*` | one new engine test: L-in-tutorial + T-in-LH pass `eligibleVenues()`; P-in-any-lab excludes tutorials/LH; capacity violation still rejected. Receipt test pins the pool-widening on the AMSE2003-L fixture (B100 admits AMSE2003); B110 shows no T pool entries only because no T session is conflicted in the dataset — never pin “T excluded from LH” |
| `tests/venue-intent-modal.spec.ts` | the B009 zero-fit copy pin (`hosts Practical (P) sessions only`) updates to the shared dual-admission wording (designed contract change) |
| `docs/venue-restrictions.md` | R1 table amendment + dated user-clarification; preamble's live-code list gains the venue page's producer-modal pool row |
| `page-changelogs/replacement-arrangement-changelog.md` | dated dd-widening entry |
| `page-changelogs/venue-timetable-ui-changelog.md` | dated pool-widening + shared wording-helper entry |

## Success criteria
- For ANY session type T or L, the arrangement venue dd, the
  Select-a-Session modal's eligible set, the blocked-venues payload, and the
  submit-side engine return the SAME venue family — tutorials + lecture halls
  — filtered by capacity (and the client-side B005 Diploma hide alone).
- AMSE2003 (L, 15 students) is bookable in e.g. B002 free at W5 Thu 9:00–11:00
  (the enquiry that opened this change).
- All gates stay green (pint / phpstan 0 / phpunit / in-scope Playwright).
