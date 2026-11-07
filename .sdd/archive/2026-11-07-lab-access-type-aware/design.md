# design — `lab-access-type-aware` (2026-11-07)

## D1 — one type-aware map, B006 derived from it

`ruleMap()` keeps its type rows (`P`/`T`/`L`) and the R6 population keys verbatim. The two
flat roster arrays are replaced by:

```php
'lab_access' => [
    'AMIT2033' => ['L', 'T'],
    'BMIT2154' => ['L', 'T'],
    'BMIT3084' => ['L', 'T'],
    'AMIT2034' => ['L', 'T'],
    'BMIT2013' => ['L'],
    'BMIS2003' => ['L'],
    'BMIT1723' => ['L'],
    'AMCS1013' => ['T'],
    'AMCS1043' => ['T'],
    'AMIS1003' => ['T'],
    'AMSE1003' => ['T'],
    'BMCS3033' => ['T'],
    'BMIT1173' => ['T'],
],
```

- `lab_access_module_codes` is **deleted**. Nothing hand-maintains a second copy of the
  module set: the module identity is the map's key.
- `b006_allowed_module_codes` is **kept as a derived key** (`array_keys($labAccess)`), because
  it is in the shipped page payload and in the docs; it is documented as derived and a test
  pins `array_keys(lab_access) === b006_allowed_module_codes`. Deleting it would have been a
  second, unnecessary payload change.
- The PHPDoc return shape gains `lab_access: array<string, list<string>>` and keeps every
  other key — PHPStan reads it.

Why a map of lists rather than `module => L|T` single values: a module can legitimately carry
both (the four networking modules do), and the truth is per `(module, type)` pair.

## D2 — the gate: type membership, not "is this an L/T"

`sessionAllowedForVenue()` keeps its signature and its shape (base row OR additive arm OR
B006 arm, then the R6 population deny). Only the two additive arms change:

```php
$moduleTypes = $moduleCode !== null ? ($rule['lab_access'][$moduleCode] ?? []) : [];
$labsAdditive = in_array($sessionType, $moduleTypes, true)
    && in_array($rawRoomType, ['lab', 'cisco_lab'], true);
$b006Widen = $venueCode === $rule['b006_venue_code']
    && in_array($sessionType, $moduleTypes, true);
```

- The `in_array($sessionType, ['L','T'], true)` literal disappears: the map can only contain
  `L`/`T`, and an empty `$moduleTypes` (unknown or unloaded module) yields `false` — the
  existing fail-closed behaviour, now expressed by the data instead of by an `is L/T` guard.
- `$inWidenRows` (module relation loaded) stays as the explicit fail-closed guard, so a
  missing relation can never widen.
- B006's arm still keys on the **room code**, not the room type, and still composes with R6
  unchanged. `P` keeps its base R1 row and never appears in the map.

## D3 — the client mirror reads the same key

`venueMatrixAllows()` (blade) is the one client reflection of the rule. It changes in exactly
two lines:

```js
const modTypes = (rule.lab_access || {})[moduleCode] || [];
const labsAdditive = !!moduleCode && labs.indexOf(tag) !== -1 && modTypes.indexOf(type) !== -1;
...
const b006Widen = venueCode === b006Code && !!moduleCode && modTypes.indexOf(type) !== -1;
```

Fail-closed behaviour is preserved (missing map → deny; missing module code → no widen). No
new JS constant, no second roster: the map arrives in `PAGE_DATA.ruleMap`.

## D4 — the seed invariant becomes both-direction

`TimetableSeedInvariantsTest::test_t8_…` is rewritten as a two-way check over `(module, type)`:

1. every map pair has a seeded `lab`/`cisco_lab` session of that exact module **and** type;
2. every seeded `lab`/`cisco_lab` `L`/`T` session has its `(module, type)` pair in the map;
3. the AMCS1013 partial case is asserted explicitly (`T` present, `L` absent) so the
   "easiest to get wrong" row can never quietly flip;
4. `array_keys(lab_access) === b006_allowed_module_codes` (the derived key cannot drift).

This is the invariant that bites when a future import adds a lab `L` for a module that is
only on the roster for `T`.

## D5 — matrix test rows

`SessionAllowedForVenueMatrixTest` (pure unit, in-memory models):

| row | change |
|---|---|
| `sp17` BMIT2013 `T` @ B010 | **true → false** (no seeded `T`) |
| `sp18` BMIT1173 `L` @ B009 | **true → false** (its `L` sits in B107) |
| `sp8` AMCS1013 `T` @ B006 | **stays true** |
| new: AMSE1003 `L` @ B009 / `T` @ B009 | false / true |
| new: AMCS1043 `L` @ B011 / `T` @ B011 | false / true |
| new: AMCS1013 `L` @ B006 / `T` @ B006 | false / true |
| new: BMIT2013 `T` @ B006 | false (the B006 arm is type-aware too) |

## D6 — runtime receipt

A seeded feature test (`LabAccessTypeAwareGateTest`) proves the law against the real seed:
AMSE1003 `L` denies B005, B006, B009, B010, B011 while AMSE1003 `T` allows B009 (its own
room) — the same shape repeated for AMCS1013 (its `T` allows B006, its `L` denies it). Same
verdicts printed from `tinker` in the report.

## D7 — docs

- `docs/venue-restrictions.md` R3: a dated **2026-11-07** entry quoting the ruling, the
  per-subject sentence marked ~~superseded~~ (kept, dated, not rewritten), the new law stated
  as "the roster is per **module AND session type**", the two roster lists replaced by one
  type-aware table. R1/R6 wording untouched.
- `dataset/DATASET-NOTES.md` §3: the "9 kept as printed" note gains the type qualifier (its
  table already prints the type of each row, so only the prose changes).

## Risks

- **A module losing access it "should" keep.** Mitigated by deriving the map from the seed in
  both directions and by the 7-L/10-T acceptance list — every one of them is asserted in the
  matrix test.
- **The client mirror drifting from the server.** Both read `lab_access`; the client is a
  literal mirror of the same three lines, and the browser spec for the venue picker
  (`venue-book.spec.ts`) exercises the resulting eligibility.
- **Capacity behaviour changing.** No: the engine's capacity check is separate and untouched.
