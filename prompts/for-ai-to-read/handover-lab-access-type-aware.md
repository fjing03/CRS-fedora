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

AMCS1013 has no `L` or `T` in a lab at all (its lab classes are `P`), so it
should leave the roster completely.

Must **keep** lab access (their own class type is already in a lab):

- `L`: AMIT2033, AMIT2034, BMIS2003, BMIT1723, BMIT2013, BMIT2154, BMIT3084
- `T`: AMIT2033, AMIT2034, BMIT2154, BMIT3084, AMCS1043, AMIS1003, AMSE1003,
  BMCS3033, BMIT1173

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
  - `sp8` (AMCS1013 T at B006) flips to **false**
  - `sp17` (BMIT2013 T at B010) flips to **false**
  - `sp18` (BMIT1173 L at B009) flips to **false**
  - add cases: AMSE1003 `L` at B009 → false, AMSE1003 `T` at B009 → true,
    AMCS1043 `L` at B011 → false, AMCS1043 `T` at B011 → true
- Rewrite `tests/Feature/TimetableSeedInvariantsTest.php` T8 into a
  **both-direction** invariant:
  1. every `(module, type)` pair in the map has a seeded lab/cisco_lab session
     of that exact module and type, and
  2. every seeded lab/cisco_lab `L` or `T` session has its `(module, type)`
     pair in the map.
  AMCS1013 must disappear from the map and the test must prove why.
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
- The 7 L-in-lab and 9 T-in-lab modules behave exactly as before.
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
