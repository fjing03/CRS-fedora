# proposal — `lab-access-type-aware` (2026-11-07)

## Why (the user's ruling)

> *"A lecture (`L`) must not be allowed to book a lab room unless that subject's own
> lecture already sits in a lab in the seeded timetable. The same type-aware rule applies
> to tutorials (`T`). Practicals (`P`) are unchanged."*

This **supersedes** the 2026-11-03 sentence in `docs/venue-restrictions.md` R3:

> *"The roster is **per-subject, not per-class**: every BMIT2013 L/T may now book any lab,
> and B006 too."*

The 2026-11-03 ruling was correct about *which modules* belong on the roster (their seeded
L/T really does sit in a lab) and wrong about *which types* — it widened every `L` and `T`
of a listed module. Measured consequence on the demo DB: **six** modules whose `L` is not
in a lab could book all five labs.

## The bug, measured (read-only demo DB → identical on the testing DB)

`LectureSessionCatalog::ruleMap()` carries two flat, module-wide arrays, and
`sessionAllowedForVenue()` widens **any** `L` or `T` of a listed module into `lab` /
`cisco_lab`. Over-grant, i.e. `L` on the roster with no seeded `L` in a lab:

| module | its `L` sits in | headcount | labs offered today |
|---|---|---|---|
| AMSE1003 | Lecture Hall B111 | 25 | B005, B006, B009, B010, B011 |
| AMCS1043 | Tutorial Room B014 | 23 | all five |
| BMCS3033 | Tutorial Room B018 | 10 | all five |
| BMIT1173 | Tutorial Room B107 | 17 | all five |
| AMCS1013 | Lecture Hall B111 | 48 | all five (capacity blocks them) |
| AMIS1003 | Lecture Hall B111 | 48 | all five (capacity blocks them) |

The same widening grants **`T`** to modules whose only seeded `T` is *not* in a lab in one
direction and hides a real partial case in the other:

- **BMIT2013** has a seeded `L` in B009 but **no seeded `T` at all** yet its `T` could book
  any lab (`sp17` in the matrix test pinned that as *allow*).
- **BMIT1173** has a seeded `T` in B009 but its `L` sits in B107, yet its `L` could book
  any lab (`sp18` pinned that as *allow*).
- **AMCS1013** is the partial case the first draft of the handover got wrong: its `L` is in
  Lecture Hall B111, its `T` is in **B006 (cisco lab)** *and* B016 (tutorial) — so it must
  keep `T` and lose `L`.

## The re-verified population (both DBs agree; `class_replacement_fresh` = `class_replacement_testing`)

The 17 seeded `lab`/`cisco_lab` `L`/`T` rows, as `(module, type)` — this is the map, in both
directions:

| module | types whose own seeded session sits in a lab |
|---|---|
| AMIT2033 | L, T |
| AMIT2034 | L, T |
| BMIT2154 | L, T |
| BMIT3084 | L, T |
| BMIS2003 | L |
| BMIT1723 | L |
| BMIT2013 | L |
| AMCS1013 | T |
| AMCS1043 | T |
| AMIS1003 | T |
| AMSE1003 | T |
| BMCS3033 | T |
| BMIT1173 | T |

Over-grant list after the change: **empty** (every listed type has a seeded lab session of
that exact type; every seeded lab `L`/`T` is listed).

## Scope

1. **`ruleMap()`** — the two flat arrays (`lab_access_module_codes`,
   `b006_allowed_module_codes`) become **one type-aware map** `lab_access`:
   `module_code => list<session_type>`. B006's roster is **derived** from it
   (`array_keys`), never hand-maintained, so the two can no longer diverge.
2. **`sessionAllowedForVenue()`** — both additive arms require the session type to be in
   the module's entry; fail-closed when the module relation is not loaded, exactly as now.
3. **The client mirror** (`replacement-arrangement.blade.php` `venueMatrixAllows()`) — the
   same type test; it reads the same payload key, so it cannot drift on its own.
4. **Tests** — matrix rows `sp17`/`sp18` flip to deny; new cases for the four T-only
   modules' `L` versus their `T`; the AMCS1013 partial pair; the seed invariant becomes
   **both-direction**; a runtime (seeded, feature) receipt for AMSE1003.
5. **Docs** — `docs/venue-restrictions.md` R3 gains the dated 2026-11-07 ruling and marks
   the per-subject sentence superseded; `dataset/DATASET-NOTES.md` §3's roster note follows.

Out of scope, by the handover: no schema change, no data change, no capacity change, no new
dependency, no FYP-report edit, and R1/R6 wording stays byte-identical.

## Acceptance

- The six modules return `false` for every `lab` / `cisco_lab` venue on their `L`.
- AMSE1003, AMCS1043, BMCS3033, BMIT1173 keep lab access on their `T`; `P` unchanged.
- AMCS1013 keeps `T` access to B006 and loses `L`.
- The 7 L-in-lab and 10 T-in-lab modules behave exactly as before.
- The over-grant list is empty and the map matches the seed in both directions.
- Tests: the named filter is green, pint and PHPStan clean.
