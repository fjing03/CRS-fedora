# Proposal — repair-timetable-seed-data

**Batch 1 of 4** · Created 2026-10-07 · Baseline: `explore-brief.md` (evidence gathered first-hand 2026-10-07 from live DB + source)

## Why

The seeded timetable is silently wrong in three classes, and every downstream consumer (availability views, replacement request UX, the Sprint-2 matrix intersection engine) inherits the wrongness:

1. **The occupancy pass can fail silently.** `ClassSessionsSeeder::markTimeSlotsOccupied()` filters `time_slots` by `status = 'available'` (:78) but never checks that the update actually hit anything. When a template shares venue+day+overlapping time with a template processed earlier, its update matches **0 rows** and the seeder finishes green. The result is a `class_sessions` row with **zero** referenced `time_slots` — an orphan invisible to every view that reads occupancy.
2. **Three orphan/double-booked sessions exist right now.** Live-DB trace (2026-10-07): **#25** (BMIT2020 / DFT1 P, exact venue-day-time dup of #4 @ B005 **Fri**), **#30** (MPU-3133 / RAF2(S3)G4, exact dup of #29 @ B110 **Wed** 10–12; `day_of_week = 2` = Wednesday, 0=Mon..5=Sat), **#32** (MPU-3232 / RBU1, dup of #12 @ B110 Mon 14–16). All three have zero occupied time_slots. These are precisely the three sessions the approved wave plan named.
3. **MPU cohort assignment contradict the frozen dataset spec** (`BACKEND-TASKS.md:222–223`): MPU-3133 is intended **cross-faculty (RAF2, RBU1, RSD3)** but is seeded with RAF2(S3)G2/G4 + RBU1 only — **no RSD3 cohort at all**; MPU-3232 is intended **cross-year stacking (RSD2G2, RSD2G3, RSD3G3)** with L+T sessions but is seeded to **RBU1(S1)G1 only** — the wrong cohort set entirely.
4. **`dataset/timetable.md` does not exist.** BACKEND-TASKS.md Task 2.2 (the timetable baseline document: 14 cohorts / 14 lecturers / 23 rooms / 20–30 weekly blocks / 08:00–18:00 with 1- and 2-hour blocks, including the two MPU high-risk cases) was never synthesized. Both application and spec reference a document that isn't there.
5. **The live DB has drifted from the seeder source.** The #4/#25 overlap window the DB reports is 09:00–11:00; the current code says 08:00–10:00 — the store predates the current templates. Any repair that does not end in a fresh re-seed plus a code↔DB parity assertion leaves this class of drift undetectable.

The failure mode being repaired is *silence*: no exception, no warning, a green seeder run that hides data loss. A timetable the demo depends on must be able to fail loudly.

## What

1. **Patch the existing `ClassSessionsSeeder`** (locked decision: patch, not regenerate):
   - **Fail-fast occupancy**: `markTimeSlotsOccupied()` asserts the update affected exactly the expected count — `⌈duration/30⌉ × 14` = 56 per 2-hour session (venue-keyed, cohort-count-independent) — and throws `RuntimeException` on any mismatch — the silent no-op becomes impossible.
   - **Resolve the 3 clashes per the user-approved scheme**: 29+30 merge into **one multi-cohort session** (RAF2(S3)G2 + RAF2(S3)G4, MPU-3133 kept legal at B110 **Wed** 10–12); **#25 re-timed** (DFT1 P moves to a legal slot; exact placement pinned in design with executor-grade DB checks); **#32 re-timed/venue-moved** away from B110 **Mon** 14–16 (venue-merge rejected: putting RBU1 into the DFT2/DSF2 BMIT9012 slot would fake an enrollment that never happened).
   - **Recast the MPU rows to spec cohort sets**: MPU-3133 gains RSD3 cohort presence (lecture-venue only); MPU-3232's cohort set becomes **RSD2G2 + RSD2G3 + RSD3G3** (L+T). Exact slot placements pinned in design with first-hand feasibility checks (lecturer/cohort/venue all free or re-timed accordingly).
2. **Synthesize `dataset/timetable.md`** (Task 2.2's intended product) reflecting the **repaired** baseline so the doc, the seeder, and the DB agree on one number = the repaired set.
3. **Re-seed the demo data** via the sanctioned `php artisan migrate:fresh --seed` (AGENTS.md standing rule), including a decision on whether the newly extracted `ReplacementRequestsSeeder` participates in the chain.
4. **Extend the integrity test suite** (`tests/Unit/DbIntegrityConstraintsTest.php`, or a sibling seeder-invariant feature test) with **five invariants** so D1/D2/D3-class failures can never re-enter silently:
   1. no orphan sessions — every session has exactly the expected occupied-slot count (`⌈duration/30⌉ × 14`);
   2. no venue/day/time template overlaps across the whole set;
   3. no lecturer-time overlaps (criterion 3's second clause — a venue fix must never trade for a lecturer double-booking);
   4. all 14 cohorts still covered by ≥1 session (the safety net for the MPU recast, which strips RBU1's only two MPU-3232 rows);
   5. `dataset/timetable.md` ↔ seeded-DB slot-for-slot parity (a real test, constructed in Batch 2 — parsing the doc's slot table and asserting against the seeded store), plus MPU cohort-set and venue allowed_session_types assertions.

## In scope

- `database/seeders/ClassSessionsSeeder.php` — the `markTimeSlotsOccupied()` post-condition assertion + template edits (merge 29+30; re-time 25, 32; recast MPU cohort sets; any supporting template ordering).
- `dataset/timetable.md` — new file, synthesized from the **repaired** seeder (post-merge execution), reflecting what actually seeds.
- `database/seeders/DatabaseSeeder.php` — only if the `ReplacementRequestsSeeder` chaining decision requires it.
- `tests/Unit/DbIntegrityConstraintsTest.php` (and/or one new feature test) — the invariant extension.
- **Execution**: `php artisan migrate:fresh --seed` on the live demo DB; verification via the criteria table below.
- Changelog: append the repair out row to `page-changelogs/backend-automated-by-ai.md` post-verify (housekeeping convention, outside criterion-8's window — precedent: sync-upstream-fjing-ui T7.3).

## Out of scope

- "1-hour blocks (some 2-hour)" — the spec's `BACKEND-TASKS.md:219` mentions them, but every current template is 2-hour and the locked decision is patch-not-regenerate; recorded below as debt instead (Wave 3 may need 1-hour granularity for replacement scheduling anyway).
- `LecturerScheduleSeeder.php` deletion (dead code, external `../CRS/…` reference, never called by `DatabaseSeeder`) — parked as question Q5; a deletion is a scope expansion, not a repair.
- Schema/migration changes, `MatrixIntersectionEngine` / OCC logic, UI mock-data cleanup (`public/js/mock-data.js` — Wave 3 wiring), playwright e2e runtime.

## Deferred (open question carried from `explore-brief.md`, not resolved here)

- **Q5** — `LecturerScheduleSeeder.php` disposition: delete, rewrite against repo-internal data, or leave parked. Needs an explore of what "past sem pdf" data is worth carrying into the demo; not a Wave 2 blocker.
- **1-hour block granularity** — see Out of scope; revisit when the Wire-backend-into-refactored-ui change determines whether the replacement request UX needs sub-2-hour sessions.

## Success criteria

| # | Criterion |
|---|---|
| 1 | `php artisan migrate:fresh --seed` completes without error — **exits 0 with the fail-fast assert active (no `RuntimeException` thrown)**; the silent no-op is structurally impossible from this point on |
| 2 | `class_sessions` total == exactly the template count after repairs; every session has exactly `⌈duration/30⌉ × weeks` occupied time_slots (2-hour → 4 × 14 = 56); **zero orphans** |
| 3 | Zero (venue, day, overlapping-time) template pairs across the whole set; zero lecturer-time overlaps too (the venue clashes were the visible ones, but the repair must not create a lecturer double-booking while avoiding a venue one) |
| 4 | MPU-3133's cohort set ⊇ {RAF2(S3)G2, RAF2(S3)G4, RBU1(S1)G1} **and ≥1 RSD3 cohort, and no cohort outside the RAF2/RBU1/RSD3 programmes** (spec = cross-faculty RAF2/RBU1/RSD3; exact group enumeration pinned in design), L sessions at lecture-eligible venues only; MPU-3232's cohort set == {RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G3} with L+T sessions — per BACKEND-TASKS.md:222–223 |
| 5 | All 14 cohorts still covered by ≥1 session; zero session_type vs `venues.allowed_session_types` violations (state preserved from the pre-repair audit) |
| 6 | `dataset/timetable.md` exists and matches the seeded DB by count + slot-for-slot (code↔doc↔DB parity asserted by the new tests) |
| 7 | Repair gates run green: `composer run lint:check` (adminer-only baseline), `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → 0 errors, `php vendor/phpunit/phpunit/phpunit --no-coverage` → **zero failures/skips: the pre-change 105-test baseline all still pass and the new invariant tests are part of the passing total** (final count recorded in the verify report) |
| 8 | SDD gate: proposal → design → specs → tasks frozen (reviewer run each batch), apply executed per tasks, then `/sdd-verify` + `/sdd-archive` complete; no push without explicit user authorization |

## Risks

| Risk | Mitigation |
|---|---|
| Re-seeding wipes demo data mid-development (replacement requests demo rows, staff/student users) | `migrate:fresh --seed` is the AGENTS.md-sanctioned reset for demo data; the live DB is demo-only (no real user data). `ReplacementRequestsSeeder` participation decided in design (it re-creates the demo request set deterministically). |
| Re-timing #25/#32 and adding RSD3 MPU rows creates NEW clashes | Design phase pins every changed template to a first-hand verified (lecturer-free ∧ cohort-free ∧ venue-compliant ∧ venue-free) slot before writing code; the new invariant tests assert it post-seed. |
| Silently mutating lecturer busy-hours breaks "healthy" rows (e.g., moving DFT1 P disturbs lecturer 5516's day) | The success criteria include lecturer-overlap-free assertion (criterion 3); the test suite checks each changed slot, not just the 3 victims. |
| `dataset/timetable.md` drifts from the seeder immediately after being written | The doc's synthesis step explicitly reads the post-repair seeder's template array (a "generate from source" step, not hand-maintained prose), and criterion 6's parity test pins them. |
| Fail-fast assert throws mid-seeder → partial seed state (fresh DB wiped, only templates 1..N committed, chained seeders after `ClassSessionsSeeder` never run) | Recovery is deterministic and sanctioned: re-run `migrate:fresh --seed` (self-healing, demo-only data). Design to additionally consider wrapping the template loop in `DB::transaction` so an abort rolls back to a coherent empty state instead of partial N | 
| PHPUnit grows past 105 tests; suite time balloons with full-seed tests | New invariant tests piggyback on the existing full-seed Feature test pattern (one full seed total — parallel in the bootstrap datetime assumption was already proven to work in 105/105's current runtime of ~18 s). |
