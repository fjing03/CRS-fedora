# Handover prompt — type-aware lab access (L only when its own class is in a lab)

> **What this file is.** A self-contained prompt to hand to the implementer (PI).
> It is not the spec. The SDD folder it asks for is the spec, the design and the
> task list. Read it top to bottom before touching any code.
>
> **Status of the ruling.** The user approved this direction on 2026-11-07. It
> supersedes the 2026-11-03 "per-subject, not per-class" sentence in
> `docs/venue-restrictions.md` R3.

You are working in the TARUMT CRS repo (`CRS-fedora-backend`, Laravel + Livewire).

## Read first, in this order

1. `prompts/for-ai-to-read/AGENTS.md`
2. `prompts/for-ai-to-read/CodingMAIN.md` (section 4 DB safety, section 10 conventions)
3. `prompts/for-ai-to-read/my-coding-rules.md`
4. `docs/venue-restrictions.md` (R1, R3, R6)

## The ruling to implement (user, 2026-11-07)

A lecture (`L`) must not be allowed to book a lab room unless that subject's own
lecture already sits in a lab in the seeded timetable. The same type-aware rule
applies to tutorials (`T`). Practicals (`P`) are unchanged, they already use
labs through the base R1 row.

This **supersedes** the 2026-11-03 sentence in `docs/venue-restrictions.md` R3
that says "The roster is **per-subject, not per-class**". Record the reversal,
do not silently delete the old ruling.

## Re-verify first — this list may be incomplete

The first audit pass missed a row (`AMCS1013` has a second `T` at B006), so treat
every name below as a lead, not as the answer. Before changing code:

1. Re-derive the whole population from the seeded timetable, for every module
   and both `L` and `T`, not only the roster:

```sql
select m.module_code, cs.session_type,
       count(*) filter (where v.room_type in ('lab','cisco_lab')) as lab_rooms,
       count(*) as rooms
from class_sessions cs
join modules m on m.id = cs.module_id
join venues  v on v.id = cs.venue_id
where cs.session_type in ('L','T')
group by 1, 2
order by 1, 2;
```

   Every row with `lab_rooms > 0` must be in the map with that exact type.
   Every map entry must have `lab_rooms > 0` for every type it lists.
2. Build the over-grant list explicitly: modules where the type is on the map
   but no seeded class of that type sits in a lab. After the change that list
   must be empty.
3. Run the same queries against `class_replacement_testing`, not only the demo
   DB. If the seed and the demo disagree, stop and report.
4. Check `database/seeders/RealScheduleSeeder.php`, not just the live rows. A
   fresh install gets the seeder, not the demo DB.
5. Grep the whole repo for a second copy of the roster. Any module list in JS,
   a seeder, an API controller, a test fixture or a doc is a place the rule can
   drift.
6. Walk every consumer of the gate: the engine, the booking modal, the
   arrangement fallback, the blocked-venues payload, and any client-side copy.
7. Prove the new invariant test bites: remove one map entry, run it, confirm it
   fails, restore it. Do the same by adding a fake entry.
8. Look for partial cases like `AMCS1013`, where one type is in a lab and the
   other is not. They are the easiest to get wrong.
9. Baseline counts today: 1 semester, 101 sessions, 42 modules, no duplicate
   module codes, no session without cohorts. If any of those change, redo the
   whole audit.

## Verified current behaviour (demo DB, read-only)

`LectureSessionCatalog::ruleMap()` keeps two flat, module-wide arrays, and
`sessionAllowedForVenue()` widens **any** `L` or `T` of a listed module into
`lab` or `cisco_lab`. Six modules therefore grant lab access to an `L` that is
not in a lab:

| module | `L` sits in | headcount | labs offered today |
|---|---|---|---|
| AMSE1003 | Lecture Hall B111 | 25 | all five |
| AMCS1043 | Tutorial Room B014 | 23 | all five |
| BMCS3033 | Tutorial Room B018 | 10 | all five |
| BMIT1173 | Tutorial Room B107 | 17 | all five |
| AMCS1013 | Lecture Hall B111 | 48 | none (capacity blocks) |
| AMIS1003 | Lecture Hall B111 | 48 | none (capacity blocks) |

AMCS1013 is a partial case, and the first draft of this file got it wrong. Its
`L` is in Lecture Hall B111, but it also has a `T` in **B006 (cisco lab)** and
another `T` in B016 (tutorial). So it stays on the roster for `T` only, and
loses `L`.

Must **keep** lab access (their own class type is already in a lab):

- `L`: AMIT2033, AMIT2034, BMIS2003, BMIT1723, BMIT2013, BMIT2154, BMIT3084
- `T`: AMIT2033, AMIT2034, BMIT2154, BMIT3084, AMCS1043, AMIS1003, AMSE1003,
  BMCS3033, BMIT1173, AMCS1013

## Required change

1. Replace the two flat arrays in `ruleMap()` with one type-aware map, and
   derive the B006 roster from the same map so the two can never diverge:

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

   Update the `ruleMap()` PHPDoc return shape, PHPStan reads it.

   If that refactor turns out to be too invasive, the acceptable fallback is
   two extra arrays (`lab_access_lecture_module_codes` and
   `b006_lecture_module_codes`) plus a test that keeps each pair aligned.

2. In `sessionAllowedForVenue()`, the additive arm must require the session
   type to be present in the module's entry, not merely
   `in_array($sessionType, ['L','T'])`. Keep fail-closed behaviour when the
   module relation is not loaded.

3. Keep the B006 R6 population gate exactly as it is. Only type admission
   narrows.

4. No schema change, no data change, no capacity change, no new dependency.

## Tests

- Update `tests/Unit/SessionAllowedForVenueMatrixTest.php`:
  - `sp17` (BMIT2013 T at B010) flips to **false** — BMIT2013 has no seeded `T`
  - `sp18` (BMIT1173 L at B009) flips to **false** — its `L` sits in B107
  - `sp8` (AMCS1013 T at B006) must STAY **true** — its `T` really is in B006
  - add cases: AMSE1003 `L` at B009 → false, AMSE1003 `T` at B009 → true,
    AMCS1043 `L` at B011 → false, AMCS1043 `T` at B011 → true
  - add the partial-case pair: AMCS1013 `L` at B006 → false, AMCS1013 `T` at
    B006 → true
- Rewrite `tests/Feature/TimetableSeedInvariantsTest.php` T8 into a
  **both-direction** invariant:
  1. every `(module, type)` pair in the map has a seeded lab/cisco_lab session
     of that exact module and type, and
  2. every seeded lab/cisco_lab `L` or `T` session has its `(module, type)`
     pair in the map.
  AMCS1013 stays in the map with `['T']`, and the test must prove its `L` is
  absent while its `T` is present.
- Add a runtime test (feature or tinker receipt) showing AMSE1003 `L` returns
  false for B005, B006, B009, B010, B011 while AMSE1003 `T` returns true where
  its own timetable is in a lab.
- Run:
  - `php artisan test --filter 'SessionAllowedForVenue|TimetableSeedInvariants|LtVenueCapacity|MatrixIntersection|LectureSessionCatalog'`
  - `composer run lint:check`
  - `vendor/bin/phpstan analyse --memory-limit=1G`

## Docs

- `docs/venue-restrictions.md` R3: add a dated 2026-11-07 ruling entry, quote
  the user, mark "per-subject, not per-class" as superseded, and state the new
  law. Keep R1 and R6 wording intact. Update the roster lists.
- Update `DATASET-NOTES` section 3 if it lists the roster.
- Do **not** edit the FYP report, the owner syncs that separately.

## SDD

Create `.sdd/changes/lab-access-type-aware/` with `proposal.md`, `design.md`
and `tasks.md`, in the shape of `.sdd/changes/b005-diploma-allowed/`. Record
the superseding ruling verbatim. Verify, then archive.

## Safety (non-negotiable)

- Demo DBs are read-only. Never run `migrate:fresh`, `migrate:refresh`,
  `db:wipe` or any reseed against them. Any test write only with
  `DB_DATABASE=class_replacement_testing`. Never use `--env=testing`.
- Run `git status` first and do not stage or revert another session's dirty
  files.
- Conventional commit (`fix:` or `refactor:`). Do not push.

## Acceptance

- The six named modules return false for every `lab` and `cisco_lab` venue on
  their `L` session.
- AMSE1003, AMCS1043, BMCS3033 and BMIT1173 keep lab access on their `T`/`P`
  where the timetable has them.
- AMCS1013 keeps `T` access to B006 and loses `L`.
- The 7 L-in-lab and 10 T-in-lab modules behave exactly as before.
- The over-grant list from "Re-verify first" is empty, and the map matches the
  seed in both directions.
- Give the runtime proof and the test counts before and after.

Report back with: files changed, test output, and the tinker proof.

## Appendix — read-only queries used for this audit

```sql
-- module, type and room for every roster module
select m.module_code, cs.session_type, v.room_code, v.room_type
from class_sessions cs
join modules m on m.id = cs.module_id
join venues  v on v.id = cs.venue_id
where m.module_code in (
  'AMIT2033','BMIT2154','BMIT3084','AMIT2034','BMIT2013','AMCS1013',
  'AMCS1043','AMIS1003','AMSE1003','BMCS3033','BMIS2003','BMIT1173','BMIT1723'
)
order by m.module_code, cs.session_type;
```

`day_of_week` in this schema is `0 = Monday` through `5 = Saturday`
(`LectureSessionCatalog::DAY_ABBRS`). Do not read it as Sunday-first.
