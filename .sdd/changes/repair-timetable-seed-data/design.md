# Design — repair-timetable-seed-data

**Batch 2 of 4** · Created 2026-10-07 · Governing inputs: frozen `proposal.md` (Batch 1, 2 rounds), `explore-brief.md`, user-locked scheme (29+30 merge; 25/32 re-time; scope bundle).

All placements below carry a first-hand consistency verification executed 2026-10-07 against the **code-truth template array** (33 templates via reflection; live DB unusable directly because of D5 drift).

---

## §1 Post-repair dataset shape (single source of numbers)

| Metric | Value | Derivation |
|---|---|---|
| `class_sessions` rows | **35** | 33 templates − 1 deleted (tpl#30) + 1 added (MPU-3133 RSD3 multi) + 2 added (MPU-3232 T rows) |
| `session_cohorts` rows | **44** | 37 − 1 (deleted tpl#30) + 2 (tpl#32 1→3 cohorts) + 1 (merged tpl#29 1→2) + 3 (new MPU-3133 RSD3 row) + 1 (new T RSD2G3) + 1 (new T RSD3G3) |
| occupied `time_slots` rows | **1988** | 34 × (2 h → 4 slots × 14 wks) = 1904; 1 × (3 h tpl#23 → 6 × 14) = 84; overlaps = 0 so no double-counting |
| cohort coverage | **14/14** | every cohort ≥ 1 session |
| venue/lecturer(day/time) overlaps | **0** | set-consistency scan 2026-10-07 (script `/tmp/opencode/w2-feasibility-verify.php`, mapping verified after an index-slip fix) |

## §2 The fail-fast occupancy pass (D1)

`markTimeSlotsOccupied()` is rewritten:

1. `run()` wraps the whole template loop in `DB::transaction()` (the frozen [Y5] consideration → an abort at template *N* rolls back to a coherent clean state rather than a partial 1..N store).
2. Per template: `markTimeSlotsOccupied()` accumulates `$affected` across the 14 weekly UPDATEs (the loop stays week-wise; each week expected = `⌈(end−start in minutes)/30⌉` cells; a 2-h session = 4 cells/week, 56 total).
3. After the loop: `if ($affected !== $expected) throw RuntimeException("[ClassSessionsSeeder] occupancy mismatch for session {$row}: affected {$affected} of expected {$expected} — template overlap or grid geometry changed")`.
   - **Expected is venue-keyed (never ×cohorts)** — frozen at proposal [Y1]: 56 for any 2-h session regardless of cohort count.
   - The `where('status','available')` guard **stays** (:78's pre-condition is correct); the *bug* was only the missing post-condition.
4. Because the assert is post-loop, a mismatch still throws *only after* logging the conflicting template's (module, venue, day, window) — the message includes all four, so the operator fixes the template, not the DB.

## §3 Template edit table (executor-grade — every row verified clash-free)

| # | Template (tpl# = template id; class id 1..35 post-seed) | Edit | Result state | Feasibility evidence |
|---|---|---|---|---|
| E1 | tpl#25 (BMIT2020, 5516, DFT1 P) | re-time: `day_of_week 4`, `10:00:00–12:00:00` (venue **B005 stays**) | old Fri 08–10 window vacated (kept by tpl#4 = DFT2 P); new window 4 cells ×14 | C3 scan: venue 0, lecturer 0, cohort 0 clash; B005 lab allows `P` ✓ |
| E2 | tpl#29 (MPU-3133, B110, Wed 10–12) | cohorts `[RAF2(S3)G2]` → `[RAF2(S3)G2, RAF2(S3)G4]` | one merged multi row replaces rows #29+#30 | C-scan clean; consistency scan: overlaps 0 |
| E3 | tpl#30 | **DELETE** (was the dup row) | −1 template, −1 session_cohorts row | its slot goes to E2's merged row |
| E4 | tpl#32 (MPU-3232 L, 5254) | venue B110 → **B111**; cohorts `[RBU1(S1)G1]` → `[RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G3]`; slot day0 14–16 **stays** | frees B110 Mon 14–16 for tpl#12 only; new cohort set = spec | C1 scan: venue 0 lec 0 cohort 0; B111 lecture hall allows `L` ✓ |
| E5 | tpl#33 (MPU-3232 T, 5254, B102 Thu 14–16) | cohorts `[RBU1(S1)G1]` → `[RSD2(S1)G2]` | slot/venue/lecturer kept | C4 scan clean; B102 allows `T` ✓ |
| E6 | **NEW** tpl#34: MPU-3133 L, lecturer 4363, **B111**, day2 **14:00–16:00**, cohorts `[RSD3(S1)G1, RSD3(S1)G2, RSD3(S1)G3]` | append | closes D3's "MPU-3133 has no RSD3 cohort" (cross-faculty = RAF2+RBU1+RSD3 now complete) | C2 scan: venue 0 lec 0 cohort 0 — B111 Tue 08–10 = tpl#22 (no same-day overlap); **no B111 Wed session exists pre-repair**; BMIT7072 (tpl#23, Wed 08–11) ends before 14:00 ✓ |
| E7 | **NEW** tpl#35: MPU-3232 T, lecturer 5254, **B102**, day3 **16:00–18:00**, cohort `[RSD2(S1)G3]` | append | L+T coverage for RSD2(S1)G3 (first C5 pick Wed 14–16 was rejected — it collides with tpl#18, the cohort's own BMIT7890 L at Wed 14–16; the scan caught it) | C5′ scan: venue 0 lec 0 cohort 0; B102 late window free (grid runs to 18:00) ✓ |
| E8 | **NEW** tpl#36: MPU-3232 T, lecturer 5254, **B102**, day4 **14:00–16:00**, cohort `[RSD3(S1)G3]` | append | L+T for RSD3G3 | C6 scan clean; RSD3G3's Wed 08–11 (tpl#23) untouched ✓ |

RSD2G2's T row stays at E5 (B102 Thu 14–16); RSD2G3 gets E7; RSD3G3 gets E8 — each group its own T (precedent: every existing T row is single-cohort).

**MPU-3133 final footprint** (criterion 4 floor + cap): tpl#29 B110 Wed 10–12 [RAF2G2, RAF2G4] · tpl#31 B111 Fri 08–10 [RAF2G2, RAF2G4, RBU1] (unchanged) · tpl#34 B111 Wed 14–16 [RSD3G1, RSD3G2, RSD3G3] ⇒ all RAF2/RBU1/RSD3 cohorts present; **no cohort outside those programmes** (cap honored: no DFT/DSF/RSD2-G1 rows exist).

**MPU-3232 final footprint**: tpl#32 B111 Mon 14–16 [RSD2G2, RSD2G3, RSD3G3] (L) · tpl#33 B102 Thu 14–16 [RSD2G2] (T) · tpl#35 B102 Thu 16–18 [RSD2G3] (T) · tpl#36 B102 Fri 14–16 [RSD3G3] (T) ⇒ == spec set exactly, L+T ✓. RBU1 stays covered via tpl#31 (MPU-3133) — invariant 4 held (37-row count unchanged pre-recast).

## §4 `dataset/timetable.md` synthesis (D4)

Generated from the **post-repair seeder**, never hand-maintained:

1. A one-off generator, committed as `dataset/generate-timetable-doc.php` and kept for regeneration parity: it reads the templates via the same reflection and renders one markdown table per day (`0=Monday … 5=Saturday`), columns = `start–end | venue | module | lecturer | type | cohorts`, ordered by day then start time, plus a header block carrying the §1 counts and a "source: ClassSessionsSeeder.php @ <commit>" line.
2. **The generator script is authoritative-owned by this design (§4.2's verbatim block)** — the doc drifts-side rule: **the seeder/DB is authoritative; the doc regenerates from it, never hand-edited**. The `## Counts` block's three literal patterns the T-5 parser keys on: `Session count: <N>`, `Session-cohort rows: <N>`, `Occupied time slots: <N>`.

3. The doc's slot rows are deliberately machine-parseable (consistent column count) — that is what makes the doc↔DB parity test of §5(T-5) possible.

### §4.2 Verbatim generator (authoritative; the implementer runs/commits this exact script)

`dataset/generate-timetable-doc.php` (run: `php artisan tinker --execute="require 'dataset/generate-timetable-doc.php';"`):

```php
<?php
// dataset/generate-timetable-doc.php — one-off, kept for regeneration parity.
// Authority rule: the seeder/DB is authoritative; the doc regenerates from it, never hand-edited.
use Illuminate\Support\Facades\DB;
use Database\Seeders\ClassSessionsSeeder;

$seeder = new ClassSessionsSeeder;
$m = (new ReflectionClass($seeder))->getMethod('getSessionTemplates');
$tpl = $m->invoke($seeder,
    DB::table('lecturers')->pluck('user_id','staff_id')->toArray(),
    DB::table('venues')->pluck('id','room_code')->toArray(),
    DB::table('modules')->pluck('id','module_code')->toArray());

// day-grouping contract (§4.1): the template array is authored in module/cohort order,
// NOT day order — sort explicitly before rendering or day headers interleave.
usort($tpl, fn($a, $b) => [$a['day_of_week'], $a['start_time']] <=> [$b['day_of_week'], $b['start_time']]);

$cn = [];
foreach (DB::table('cohorts')->join('programmes','programmes.id','=','cohorts.programme_id')
    ->select('cohorts.id','programmes.programme_code','cohorts.current_year','cohorts.semester','cohorts.tutorial_group')->get() as $c) {
    $cn[sprintf('%s%d(S%d)G%d', $c->programme_code, $c->current_year, $c->semester, $c->tutorial_group)] = $c->id;
}
$flip = array_flip($cn);
$room = DB::table('venues')->pluck('room_code','id');
$staff = DB::table('lecturers')->pluck('staff_id','user_id');
$mod = DB::table('modules')->pluck('module_code','id');
$dayName = [0=>'Monday',1=>'Tuesday',2=>'Wednesday',3=>'Thursday',4=>'Friday',5=>'Saturday'];

$scRows = 0; $occ = 0; $rows = []; $lastDay = -1;
foreach ($tpl as $s) {
    $names = implode(', ', array_map(fn($c) => $flip[$c] ?? '?', $s['cohorts'] ?? []));
    if ($s['day_of_week'] !== $lastDay) {
        $rows[] = "";
        $rows[] = "## {$dayName[$s['day_of_week']]} (day_of_week = {$s['day_of_week']})";
        $rows[] = "| start–end | venue | module | lecturer | type | cohorts |";
        $rows[] = "|---|---|---|---|---|---|";
        $lastDay = $s['day_of_week'];
    }
    $dur = (int) round((strtotime($s['end_time']) - strtotime($s['start_time'])) / 60);
    $occ += ($dur / 30) * 14;
    $scRows += count($s['cohorts'] ?? []);
    $rows[] = '| ' . substr($s['start_time'], 0, 5) . '–' . substr($s['end_time'], 0, 5)
        . " | {$room[$s['venue_id']]} | {$mod[$s['module_id']]} | {$staff[$s['lecturer_id']]} | {$s['session_type']} | {$names} |";
}
$counts = "\n## Counts\n\n- Session count: " . count($tpl) . "\n- Session-cohort rows: {$scRows}\n- Occupied time slots: " . (int) $occ . "\n";
file_put_contents(base_path('dataset/timetable.md'),
    "Baseline Timetable (generated from ClassSessionsSeeder — source of truth: seeder, never hand-edited)\n"
    . implode("\n", $rows) . $counts);

// numeric self-check (explicit prints — not just the doc's own Counts block)
echo "SELF-CHECK session count=" . count($tpl) . " session_cohorts={$scRows} occupied=" . (int) $occ . "\n";
echo "expected: 35 / 44 / 1988\n";
```
### §4.1 Format contract
- `## <Day> (day_of_week = N)` per day, then one row per session ordered by start time.
- Every session row includes ALL cohorts it covers (comma-separated).
- The file MUST also include a final `## Counts` block listing session count, session_cohorts rows, occupied slots — the parity test parses these three numbers with fixed patterns.

## §5 Test plan (five invariants — commissioned at proposal [R1])

New file `tests/Feature/TimetableSeedInvariantsTest.php` (`RefreshDatabase` + `DatabaseSeeder` seed — the suite-proven full-seed pattern from OCCValidatorTest S-12 / TimetableWiringTest; expected runtime cost is single suite-in inflating ~1–2 s):

| # | Invariant | Mechanism |
|---|---|---|
| T-1 | No orphans + exact coverage: every session has **exactly** `⌈duration/30⌉×14` occupied slots (2 h → 56; tpl#23 3 h → 84); **sum over all sessions = 1988**; every occupied slot has a non-null `class_session_id` | SQL aggregates on `time_slots` |
| T-2 | Zero (venue, day, overlapping-time) pairs; zero (lecturer, day, overlapping-time) pairs; zero (cohort, day, overlapping-time) pairs via `session_cohorts` × self-joins |
| T-3 | Fairness: all 14 cohorts covered (≥ 1 session) |
| T-4 | Spec sets: MPU-3133 cohort set ⊇ {RAF2(S3)G2, RAF2(S3)G4, RBU1(S1)G1} AND ∋ ≥1 RSD3 group AND no cohort outside the RAF2/RBU1/RSD3 programmes — mechanically asserted via join filter `programmes.programme_code NOT IN ('RAF', 'RBU', 'RSD')` (by programme_code, never a hardcoded cohort-id list); MPU-3232 cohort set == exact {RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G3} **and the module has ≥1 L row + ≥1 T row** (frozen criterion 4's L+T clause); every session's type ∈ venue allowed types |
| T-5 | Doc↔DB↔code parity: parse `dataset/timetable.md` (the §4.1 contract); assert THREE independent guards — (a) the parsed doc table contains **exactly 35 session rows** (anti-truncation, independent of the `## Counts` self-report; the `| start–end | … |` header line and each `|---|` separator line are non-session lines and MUST be excluded from the count); (b) `## Counts` block matches DB; (c) **FULL slot-for-slot comparison: every one of the 35 parsed doc rows' (day, start_time, venue, module, cohorts) tuple resolves to a live seeded session, and the DB's 35 sessions are all found in the doc — set-equality both ways, zero sampling** |

Session-count literal: **35** via templates (asserted indirectly as part of T-5's `## Counts` block).

The existing `DbIntegrityConstraintsTest` stays untouched (its FK/unique invariants are orthogonal and still meaningful).

## §6 Re-seed choreography (D5)

1. `DatabaseSeeder`'s call list gets **one line appended**: `ReplacementRequestsSeeder::class` after `ClassExceptionsSeeder` — the extracted demo-request seeder participates in the chain so a fresh demo DB carries its 3-state request fixture.
2. **Why chaining is safe against id shift**: its hardcoded `class_session_id`s (9, 12, 22) all index templates **< tpl#30** — and the only deletions/reorderings happen at/after tpl#30. tpl#1..29 seed to class ids 1..29 identically; tpl#31+ are renumbered but unused there. Its `replacement_time_slot` lookups resolve by `(week 11, day, start, venue id)` of **free-or-self slots** (B107 Mon 10:00 — free, tpl#9 ends 10:00; B110 Mon 14:00 — tpl#12's own row, pre-existing blemish kept as-is; B111 Tue 08:00 — tpl#22's own row, pre-existing) — **the repair introduces no new target-slot breakage** since neither tpl#12 nor tpl#22 moves and C1 (B111 Mon 14–16) hits neither lookup. Its `class_exceptions` fixture (tpl#9, week 5) survives the same way; occurrence verified against `ClassExceptionsSeeder`'s own content (it inserts 14 rows across weeks 1–14 for session ids {1,2,3,5,7,8,10,12,14,15,17,18,19,20} — session id **9 is absent from that set**, so no (class_session_id 9, week 5) duplicate-key competition).
3. After seeding: criterion-1..6 verification is mechanical (each handled by §5's tests or the SQL listed under criteria).

## §7 Verification matrix

| Gate | Command / mechanism | Pass condition |
|---|---|---|
| G1 | `composer run lint:check` | adminer-only baseline unchanged |
| G2 | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` | **0 errors** |
| G3 | `php vendor/phpunit/phpunit/phpunit --no-coverage` | zero failures; the 105-test baseline + the new suite all green; final count recorded |
| G4 | `php artisan migrate:fresh --seed` | exits 0; no `RuntimeException` (fail-fast assert confirms); `## Counts` numbers = §1's exact values; **plus chained-fixture check** `replacement_requests` == 3 and `class_exceptions` == 15 (14 + the (session 9, wk 5) conflict fixture) |
| G5 | The 5 invariant tests (T-1..T-5) | all green |
| G6 | Live UI spot-check: lecturer home + timetable views render; a student's cohort view shows zero phantom "available" slots at truly-occupied cells | 200 + visual sanity |

## §8 Debt registered (not absorbed)

| Debt | Note |
|---|---|
| "1-hour blocks (some 2-hour)" (BACKEND-TASKS.md:219) vs all-2-hour reality | Out of scope per locked proposal; revisit when the Wave 3 wire replaces template-driven durations |
| "20–30 class blocks per week" (Task 2.2) vs 35 post-repair | The patch adds 3 T rows and 1 MPU-3133 row; regenerating to trim back under 30 = a regenerate not a patch. Recorded as spec-vs-reality variance, disclosed in the verify report |
| `LecturerScheduleSeeder.php` dead-code disposition | Open question Q5; parked (a deletion is out of Wave 2's scope per user decision d) |
| The Doc-repair follow-up (pkill lines + `:146` 4-value enum) | Unchanged, tracked in the sync-upstream-fjing-ui archived §13 |

## §9 Post-merge commits expected (visibility only)

- `repair(seed)`: 7 template edits + fail-fast assert + DB::transaction wrap
- `docs(dataset)`: `dataset/timetable.md` synthesis
- `feat(seed)`: `DatabaseSeeder` chain line for ReplacementRequestsSeeder (+ verify)
- `test(seed)`: `tests/Feature/TimetableSeedInvariantsTest.php`
- changelog row + archive cycle
