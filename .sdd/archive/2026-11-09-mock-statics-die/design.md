# design — `mock-statics-die`

## D1 — the server-stamped short label (G1(b))

The derivation law (ONE helper, three call sites):
`shortVenueName(?string $roomName, string $roomCode): string`
```
1. trim the trailing room-code suffix ('Computer Laboratory 5' has none;
   the tutorial names bake it — 'Tutorial Room B002' → 'Tutorial Room');
2. replace 'Computer Laboratory' → 'Computer Lab' (the lab family);
3. empty result → default to the humanized code-less type? never empty —
   fall back to the room_name minus code, or the room_name itself.
```
Location: a small static on `App\Support\ReasonLabel`'s home? NO — named
`App\Support\VenueNameShortener`? lean: a `private static function
shortVenueName(?string $roomName, string $roomCode): string` per Livewire
component would be a THIRD copy — promote: add to `App\Concerns\ResolvesTimetableTimeline`? the Arrangement + VenueTimetable both `use` it already ✓ the trait is the right home. The CLIENT receives `short_name` + the full `name` (room_name — already there).

The consumers:
- `ReplacementArrangement::venuesPayload()` adds `'short_name' => ...`;
- `VenueTimetable`'s `$venuesJs` map adds the field (map() shape update);
- `VenueTimetable`'s `$venue` local (@json($venue)) — the trigger's initial
  state — add short_name as well (its modal/venueInfo node).

## D2 — `VenueDropdown._updateTrigger` (round-1 re-cut — the tooltip law
keeps its two-branch shape; ONLY the map read swaps)

```js
// the current structure stays; shortName/tooltip sources swap to the payload:
const shortName = v.short_name;             // server-stamped (D1)
let base = shortName
    || (v.name ? v.name.replace(codeSuffixRegex, '').trim() : '')
    || VenueDropdown.humanizeType(v.type);
const text = v.code + ' — ' + base;
if (label) label.textContent = text;
else this.trigger.textContent = text;
const tipText = text;   // the code-suffixed rooms' label law VERBATIM — the
                        // code never duplicates; for the labs the tooltip
                        // = the full room_name (the D1 payload also carries
                        // name):'B005 — Computer Laboratory 5 (28 seats)'.
// the exploratory middle STRUCK (the authoritative form below = THE law).
- the label = `short_name` (the fallback = the name minus the code suffix /
  the humanized type — the PRE-EXISTING fallback rules verbatim);
- `MockData.venueRealNames` reads REMOVED; the map deleted;
- the final rule the implementer codes (the authoritative form):
  ```js
  const base = v.short_name || (v.name ? v.name.replace(suffixRe, '').trim() : '') || humanised;
  const label = v.code + ' — ' + base;
  const tip   = v.code + ' — ' + (v.name && v.name.trim() !== base ? v.name.replace(suffixRe, '').trim() : base)
                + ' (' + v.capacity + ' seats)';
  ```
  — the labs: name=full ≠ short → the tooltip rides the FULL name; the
  code-suffixed rooms: name−suffix === base → the tooltip = the label ✓.

## D3 — the deletions (G2/G3)

- `mock-data.js`: the `venueRealNames` block + its comment,
  the `cohorts` block (§2.3), the `arrangementWeeks` block (§2.10)
  (≈115 lines with comments); the dormant §2.5 demo rows stay (G4);
- the COMMENT line-sweep: `:66` ("holiday stays embedded inside
  arrangementWeeks") + the §2.5a courses docblock ("both counts come
  from the MockData.cohorts registry" — reworded to the payload law);
- the e2e fixture re-cut: `tests/e2e/full-suite.spec.js` :507-520 —
  the venueSlots half stays, the `arrangementWeeks.length === 3` arm
  retires (🔴1 — the live page never bridges that key);
- grep-for-consumers receipts at apply: `MockData.cohorts`,
  `MockData.arrangementWeeks`, `MockData.venueRealNames` — 0 remaining.
- the T1 four sites (🔴4): `$venuesJs` (:135) + the `$venue` local
  (:566-573) + the null-branch array (:128) + both phpdoc generics
  (`VenueTimetable.php:577-578`).

## D4 — the receipts

- the picker Playwright re-run (the venue page: the label + the tooltip
  byte-stable rename a venue receipt? the DB rename test = optional; the
  label/tooltip standard receipts run);
- phpunit: the compact trio (TimetableWiring/CancelledBlock/
  ReplacementArrangement) + the full suite green;
- pint/phpstan/node --check.

## D5 — the gates
- ONE `feat:` commit; no push; the changelogs (the venue pages).

## D6 — the helper's home (the implementer note)

- `protected static function shortVenueName(?string $roomName, string $roomCode): string`
  lives on `App\Concerns\ResolvesTimetableTimeline` (the trait — the
  Arrangement + VenueTimetable both already `use` it, one copy, two
  consumers). The law:
  ```php
  $base = rtrim((string) preg_replace('/\s*' . preg_quote($roomCode, '/') . '\s*$/i', '', (string) $roomName));
  return str_replace('Computer Laboratory', 'Computer Lab', $base) ?: (string) $roomName;
  ```
  (the code-suffix trim first, THEN the labfamily word swap, the empty
  fallback = the room_name intact).
