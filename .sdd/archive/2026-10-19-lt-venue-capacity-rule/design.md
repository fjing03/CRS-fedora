# Design — lt-venue-capacity-rule

## D0 — One law, one home (inherited principle)
`LectureSessionCatalog::ruleMap()` — **the canonical room_type rule map** —
is the single source of L/T/P booking-time eligibility. Every consumer reads
the data row; NO consumer re-encodes the law of today's change.

## D1 — The law itself (room_type vocabulary)
```php
L → ['tutorial', 'lecture_hall']   // WAS ['lecture_hall']
T → ['tutorial', 'lecture_hall']   // WAS ['tutorial']
P → ['lab', 'cisco_lab']           // UNCHANGED (no B006 creep)
```
The CSV column `venues.allowed_session_types` stops gating. It remains in
the DB as reference inventory (the demo DB is NOT edited; divergences —
tests' lab fixture CSV 'L,T' — stay visible). Consequence: a room's legal
type-at-booking = its `room_type`, read through the map — the column's
disagreement becomes cosmetic, never gate-relevant.

## D2 — Eligibility evaluation contract (shared evaluator)
`eligibleVenues()` keeps its signature `($headcount, string $sessionType,
?int $venueId)` and its return shape (`[id => room_code]`). Internals change
in exactly TWO places:

1. the **select list**: `->get(['id', 'room_code', 'capacity', 'room_type'])`
   — `room_type` joins it; `allowed_session_types` is DROPPED from the
   select (its last operative consumer is gone; the COLUMN itself stays in
   the DB as D1's visible reference inventory — ApiReadController's direct
   CSV audit read is untouched).
2. the **venue predicate**:
```php
->filter(fn (Venue $v): bool => $v->capacity >= $headcount
    && in_array($v->room_type, ruleMap()[$sessionType] ?? [], true))
```
- `in_array` over `explode(',', allowed_session_types)` is retired.
- Unknown `$sessionType` (not a key) → empty array → no venues (the
  conservative default is preserved — today's CSV gate fails open nowhere).
- The `$sessionType` values stay the canonical uppercase `L/T/P` from
  `class_sessions.session_type` (unchanged inputs).

## D3 — Consumer parity table (who eats the law)
| Consumer | Reads | Edit needed |
|---|---|---|
| Engine `eligibleVenues()` (submit-side + `validateSlot()` inheritance + `blockedVenuesPayload()`) | ruleMap over room_type | ONE predicate (D2) |
| `LectureSessionCatalog::sessionAllowedForVenue()` (venue page producer pool + the arrangement's intent modal) | ruleMap arrays (iterate; `in_array`) | none — data row widening flows through |
| `ruleMap()` nursery consumers (Select-a-Session modal, arrangement fallback `pageData.ruleMap`) | `window.__ruleMap` (raw room_type keys) | none mechanically |
| Client dd filter `venueEligible()` (arrangement blade) | **switches** from hand-coded type literals to `window.__ruleMap[$ctx.type]` + `v.rawRoomType` | rewritten (below) |
| `ApiReadController` inventory (`allowedSessions` CSV explode) | the CSV column | **none** — reference inventory may legitimately disagree with the booking law after this change (mirrors D1's column stance); audit-noted |

## D4 — The client filter rewrite (blade)
```js
function venueEligible(v, ctx) {
    if (!v || !ctx) return true;
    const types = window.__ruleMap ? (window.__ruleMap[ctx.type] || []) : null;
    if (types === null) return false;  /* map absent → DENY (fail-closed): a
        payload regression must never re-open the law drift — the dd renders
        empty rather than over-offering; parity with the engine's
        conservative unknown-type default */
    if (types.indexOf(v.rawRoomType) === -1) return false;   // DB room_type verdict
    var capOk = v.capacity >= (ctx.studentCount || 0);
    var dipOk = !(v.code === 'B005' && (ctx.cohorts || []).some(function (c) { return /^\s*D/.test(c); }));
    return capOk && dipOk;
}
```
- The obsolete `type === 'Lab'/'Tutorial'/'LectureHall'` literals go.
- The two `:2955–61` law comments ("the engine still reads
  allowed_session_types") rewritten to the map-over-room_type sentence.
- Display vocabulary (`v.type` Tutorial/LectureHall) is OUT of the verdict —
  `rawRoomType` is the key the map speaks. The dropdown SHOWS `v.type`;
  the filter judges `rawRoomType`.
- Philosophy note: a missing `studentCount` stays capacity-fail-open
  (today's behavior — the count is a datum), while a missing MAP now fails
  closed — the map is the LAW.
- The client B005-Diploma hide stays (DOC-ONLY asymmetry, unchanged).

## D5 — The shared wording helper (promote-on-3rd-duplication)
Both impact blades load `public/js/ui-common.js` — the helper's HOME is
there (NOT a blade script block: separate routes never execute each other's
scripts). One exported function, ONE canonical string per room law (§10.0
same-meaning-same-wording — no word-order variants):

```js
/* ui-common.js — the venue's per-type session vocabulary (SDD
   lt-venue-capacity-rule D5). */
function venueTypeWording(rawType) {
    return rawType === 'lab' || rawType === 'cisco_lab'
        ? 'Practical (P)'
        : 'Lecture & Tutorial (L/T)';   // lecture_hall + tutorial: the DUAL-admission phrase
}
```
- SCOPE GUARD: the other page-local type map (`roomTypeHuman` twins —
  'Lab'/'tutorial room'/'lecture hall' framing copy) is NOT part of the
  session-type law and stays page-local, untouched (2 sites only; user-frozen
  framing copy — not this change's declared delta).
- Consumers (all duplicated literals erased): `venue-timetable.blade.php`
  (`sessionTypesHuman` + its "hosts …" sentence) and the arrangement
  blade's zero-fit notice, booking-intent banner (`req` map + the
  "hosts … sessions" sentence), and the type-reason sentence.
- The adapter at `requiredTypeHuman()` (a venueCode lookup — resolves the
  raw type via the venue payload first, then calls the helper): one lookup
  line, then the helper.
- "only" placement (single executable decision): the SENTENCE WRAPPERS
  branch on the same rawType key — labs → «hosts Practical (P) sessions
  only.»; dual rooms → «hosts Lecture & Tutorial (L/T) sessions.» —
  "only" attaches ONLY to the P sentence, page-locally, keyed on
  `rawType ∈ {lab, cisco_lab}`.
- The B009-notice Playwright pin (`hosts Practical (P) sessions only`)
  survives byte-identically (the P sentence keeps its "only").

## D6 — Test contract deltas (designed, all declared in proposal)
1. `LectureSessionCatalogTest`: L row → `['tutorial','lecture_hall']`;
   T row → `['tutorial','lecture_hall']`; every other pinned key unchanged.
2. `ReplacementArrangementTest::test_grid_busy_vectors...`: the blocked list
   drops B005 for the P session (room-type lab) — assertion updated; fixture
   CSV untouched.
3. `venue-intent-modal.spec.ts`: the zero-fit copy pin — expected to hold
   byte-identically IF the P branch keeps emitting 'Practical (P) sessions
   only' via the page-local sentence wrapper; if the wrapper rewrite shifts
   whitespace/case, the pin updates (declared either way).
4. NEW `tests/Feature/LtVenueCapacityRuleTest.php` (or rows in the existing
   engine test):
   - L(20) on tutorial B002 → eligible; T(20) on lecture_hall B110 →
     eligible; P(10) on tutorial → rejected; capacity violation → rejected;
     unknown session type → empty.
   - Producer-pool receipt: `sessionAllowedForVenue('L', 'tutorial', …AMSE2003 fixture…)`
     true; the venue page pool gains the class (assert via the component's
     payload on a fixture venue).
   - "B110 shows no T entries" is NEVER asserted (T-in-LH is now legal) —
     the pool widening pins on the L fixture (proposal round-3 wording).

## D7 — Docs & records
- `docs/venue-restrictions.md`: R1's row pair becomes
  `L → tutorial, lecture_hall` + `T → tutorial, lecture_hall`; a dated
  "user clarification 2026-10-19" note (the user's verbatim ruling); the
  preamble's live-code list gains the venue page's producer-modal pool row;
  §3's "Producer modal + fallback" line notes the widening travelled with
  the map.
- `page-changelogs/replacement-arrangement-changelog.md` +
  `page-changelogs/venue-timetable-ui-changelog.md`: dated entries (dd
  widening; the wording helper's promotion).
- Success criterion 2's DATASET attestation (B002 free W5 Thu 9–11 for
  AMSE2003): a READ-ONLY tinker probe re-run at /sdd-verify —
  `eligibleVenues(15,'L')` includes B002 + those slot rows still
  'available'. The probe is the vehicle; no demo-DB mutation.
- `prompts/my-coding-rules.md`: NO edit — the venue-type law lives in the
  restrictions doc, not the UI rules; the slot-grid Table B's semantics are
  unchanged by eligibility.

## D8 — Explicit non-goals (re-stated from proposal, now consistent)
- The B005-BUCKETED conflict derivation (`.conflictedFor()` chips) stays —
  only map-riding pools widen (D6 receipt test).
- B006's PLANNED-NEW widening + R4 IoT stay out — `P → lab + cisco_lab`
  byte-stable on datasets; the lab fixture's tests delta is the declared
  list (three tests).
- The demo DB is not mutated; the CSV column remains reference inventory.

## D9 — Data flow after the change (summary paragraph)
Booking proposal → `eligibleVenues(headcount, 'L')` → predicate room_type in
['tutorial','lecture_hall'] + capacity → venue pool; the same map row feeds
the producer pools (`sessionAllowedForVenue`) and the client dd filter
(`__ruleMap` + rawRoomType); blocked payloads = rooms minus the pool; the
wording helper renders the same law in prose. ONE data row governs four
surfaces.
