# review-log — `lab-access-type-aware` (2026-11-07)

## Re-verification before any code change (handover §"Re-verify first")

1. **Population, both DBs.** The 17 seeded `lab`/`cisco_lab` `L`/`T` rows are
   identical in `class_replacement_fresh` and `class_replacement_testing`
   (`diff` of `module|type|room|room_type` → no output), same ids. Map derived
   from them: 7 `L`-in-lab modules, 10 `T`-in-lab modules.
2. **Baseline counts** (both DBs): 1 semester / 101 class_sessions / 42 modules /
   0 duplicate module codes / 0 sessions without cohorts — as the handover says,
   so no re-audit was needed.
3. **Over-grant list (before):** AMSE1003, AMCS1043, BMCS3033, BMIT1173,
   AMCS1013, AMIS1003 — each with an `L` that is not in a lab, each able to book
   all five labs. Runtime receipt before the change
   (`php artisan tinker`, read-only demo DB):
   `AMSE1003 L (session 99, its own room B111) => B005:ALLOW B006:ALLOW B009:ALLOW B010:ALLOW B011:ALLOW`
   (same shape for the other five).
4. **The AMCS1013 partial case** confirmed from the data: `T` in **B006**
   (session 11) *and* `T` in B016 (session 60); its `L` (session 100) is in
   Lecture Hall B111. So `T` keeps, `L` loses.
5. **Seeder.** `database/seeders/RealScheduleSeeder.php` carries no roster — the
   only module list in it is `$networkingIoT = ['AMIT2033','AMIT2034',
   'BMIT2154','BMIT3084','BMIT2123']`, which drives the import's venue choices,
   not eligibility. A fresh install therefore gets its rows from the same
   seeder the demo DB came from; no second roster to align.
6. **Second copies of the roster.** Repo-wide grep for the two key names finds:
   the catalog (source), the arrangement blade's client mirror
   (`venueMatrixAllows()`, reads the shipped payload), the seeded feature test,
   and docs/changelogs. No JS constant, no API controller, no fixture holds a
   module list. (`public/js/mock-data.js` has none; the Playwright specs that
   mention these module codes use them as *session fixtures*, not as a rule.)
7. **Consumers walked:** `sessionAllowedForVenue()` (producer modal +
   arrangement fallback), the engine's `validateSlot()` → `sessionVenueEligible()`
   (OCC-time), `MatrixIntersectionEngine::eligibleVenues()` (type rows only —
   unaffected), the client mirror, and the shipped `PAGE_DATA.ruleMap`.
8. **Invariant proven to bite** (both directions, see §"red-checks" below).

## Changes

| File | What |
|---|---|
| `app/Services/LectureSessionCatalog.php` | `LAB_ACCESS` const (type-aware map, documented from the seed); `ruleMap()` returns `lab_access` + **derived** `b006_allowed_module_codes`; `sessionAllowedForVenue()` tests the session type against the module's entry in both additive arms; PHPDoc shape updated |
| `resources/views/livewire/replacement-arrangement.blade.php` | `venueMatrixAllows()` mirrors the same type test (client arm) |
| `tests/Unit/SessionAllowedForVenueMatrixTest.php` | `sp17`/`sp18` flipped to deny; new `sp21`–`sp29` (AMSE1003, AMCS1043, AMCS1013 `L` vs `T`, BMIT2013 `T` @ B006, BMIT1173 `L` @ B006, AMSE1003 `L` keeps its hall row) |
| `tests/Feature/TimetableSeedInvariantsTest.php` | T8 rewritten: both directions over `(module, type)`, shape checks, derived-key equality, the AMCS1013 partial case, and "its L is still in a hall" |
| `tests/Feature/LabAccessTypeAwareGateTest.php` | NEW — seeded runtime receipt (4 tests): AMSE1003 `L` denies all five labs / `T` allows them; the AMCS1013 partial case; the six over-grant modules; the 7 L + 10 T + P acceptance set |
| `docs/venue-restrictions.md` | R3: the two-array anatomy replaced by the type-aware table + the dated 2026-11-07 ruling quoting the user, with the per-subject sentence struck through and marked superseded |
| `dataset/DATASET-NOTES.md` | §3 gains the type qualifier (the same table now states which type each row is) |
| `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` | the standing "roster matches the timetable" note corrected to the type-aware law |

## Red-checks (the invariant bites)

- Remove one map pair (`AMSE1003 => ['T']` deleted) → T8 **fails**:
  *"the lab roster and the seeded lab L/T population must be the same SET of
  (module, type) pairs…"*.
- Add a fake pair (`BMCS2053 => ['L']`) → T8 **fails** with the same message.
- Both restored; the file compared byte-identical to its backup afterwards.

## Verification

| Command | Result |
|---|---|
| `php artisan test --filter 'SessionAllowedForVenue'` | 33 / 33 (39 assertions) |
| `--filter 'TimetableSeedInvariants'` | 8 / 8 (233 assertions) |
| `--filter 'LabAccessTypeAware'` | 4 / 4 (113 assertions) |
| `--filter 'SessionAllowedForVenue|TimetableSeedInvariants|LtVenueCapacity|MatrixIntersection|LectureSessionCatalog|LabAccessTypeAware'` | **92 / 92 (851 assertions)** |
| `composer run lint:check` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` | passed, 0 errors |
| `php artisan view:cache` | cached successfully |

**Runtime proof after the change** (tinker, read-only demo DB) — every `L` of the
six over-grant modules now denies all five labs, and every `T` keeps them:

```
AMSE1003 L (99, B111)   => B005:deny B006:deny B009:deny B010:deny B011:deny
AMSE1003 T (26, B009)   => B005:ALLOW B006:ALLOW B009:ALLOW B010:ALLOW B011:ALLOW
AMCS1043 L (50, B014)   => all deny        AMCS1043 T (42, B011) => all ALLOW
BMCS3033 L (65, B018)   => all deny        BMCS3033 T (48, B011) => all ALLOW
BMIT1173 L (92, B107)   => all deny        BMIT1173 T (29, B009) => all ALLOW
AMCS1013 L (100, B111)  => all deny        AMCS1013 T (11, B006) => all ALLOW
AMIS1003 L (101, B111)  => all deny        AMIS1003 T (41/66)    => all ALLOW
```

**Client mirror in the browser** (testing DB, staff 5425, week 8): with
`AMSE1003` selected the venue dropdown offers only the *Tutorial* and *Lecture
Hall* families — the Lab and Cisco Lab families are gone (they were offered
before). The allow direction shares the identical branch and is pinned server
side. Screenshot: `/tmp/opencode/client-mirror-amse1003.png`.

## Notes / non-goals

- `P` untouched (R1's base row); R6's population gate untouched; capacity
  untouched; no schema/data/migration change; no new dependency.
- The Demo DBs were only read (population queries + the tinker receipts). All
  writes happened on `class_replacement_testing`, which was re-seeded afterwards.
