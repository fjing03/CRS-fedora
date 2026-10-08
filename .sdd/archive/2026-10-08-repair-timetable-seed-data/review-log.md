# Review Log — repair-timetable-seed-data

## Batch 1 Round 1 — 2026-10-07 (proposal)

### 🔴 Fixed
- **[R1]** `What` item 4's invariant enumeration was incomplete vs the success criteria — criterion 6's doc↔DB parity test, criterion 3's lecturer-overlap invariant, and criterion 5's 14-cohort coverage invariant were all cited as enforced-but-uncommissioned. Fixed: `What` item 4 now enumerates **five invariants** (orphans-exact-count, venue/day/time overlaps, lecturer overlaps, 14-cohort coverage, doc↔DB parity + MPU-set + venue-type assertions), parity test explicitly commissioned as "constructed in Batch 2".

### 🟡 Addressed
- **[Y1]** "4 cells per cohort-venue match" implied a ×cohorts multiplier that would spuriously throw the fail-fast assert on heavily-merged sessions. Reworded to `⌈duration/30⌉ × 14` = 56, **venue-keyed, independent of cohort count**.
- **[Y2]** Wrong weekday labels: day_of_week 2 = **Wednesday**, not Tuesday. All clash descriptions relabeled (B110 Wed 10–12 for #29/#30; "Fri" named for the B005 #4/#25 pair; Mon stays for #12/#32; `0=Mon..5=Sat` stated).
- **[Y3]** MPU-3133 `⊇` had no upper bound (DFT1 or any cohort could be attached without failing). Added cap: "…and no cohort outside the RAF2/RBU1/RSD3 programmes; exact group enumeration pinned in design".
- **[Y4]** Criterion 7 self-contradicted (105/105 **plus** more tests can never both hold). Reworded: zero failures/skips, pre-change 105 baseline still passes, new tests in the passing total, final count recorded at verify.
- **[Y5]** Missing material risk added: fail-fast throwing mid-seeder leaves partial seed state; recovery = deterministic re-run of `migrate:fresh --seed`; design to consider `DB::transaction` wrap.

### 💡 Optional (accepted)
- Criterion 1 simplified to "exits 0 with the fail-fast assert active (no `RuntimeException`)" — the old wording also passed pre-repair.

### 🔴 Outstanding
*(none — fixes applied; awaiting Round 2 confirmation)*

### ⚖️ Verdict
Round 1: **FAIL — 1 🔴 ([R1])** → fixed in same pass.

## Batch 1 Round 2 — 2026-10-07 (proposal re-review)

### 🔴 Outstanding
*(none)*

### 🟡 Addressed in-pass
- **[Y1-residual]** Reviewer's re-round caught that the [Y1] formula fix had missed `What` item 1's parenthetical (it still carried the "4 cells per cohort-venue match" cohort-multiplier phrasing; source-wise `markTimeSlotsOccupied()` runs once per session, venue-keyed, no cohort dimension). Mechanical one-phrase edit applied: `⌈duration/30⌉ × 14` = 56 per 2-hour session (venue-keyed, cohort-count-independent) — per reviewer's own instruction "no re-review needed for a mechanical edit of this size".

### 💡 Optional (accepted for design)
- Record concrete verify counts: 32 sessions post-merge (pre-MPU additions), final template count after MPU recast, `session_cohorts` row delta (37 → N).

### ⚖️ Verdict
**Round 2: PASS — proposal FREEZEN** (Batch 1 of 4 ✓). Design (Batch 2) may begin.

## Batch 2 Round 1 — 2026-10-07 (design)

Reviewer re-derived §1's arithmetic, the E-table's idx↔tpl# mapping (incl. every E1–E8 feasibility claim), the id-shift analysis (9/12/22 < deleting position 30), the venue-id mapping (14=B107, 17=B110, 18=B111), and the ClassExceptionsSeeder collision question from source. Placements and numbers confirmed sound.

### 🔴 Fixed
- **[Z1]** T-5's "sample of 10 rows" under-implemented frozen criterion 6's slot-for-slot parity. Fixed: T-5 = **full bidirectional set comparison over ALL 35 rows** + anti-truncation guard (parsed doc contains exactly 35 session rows, independent of `## Counts` self-report) + Counts-block match. Zero sampling anywhere.

### 🟡 Addressed
- **[N1]** §4's false "recorded verbatim" claim (the generator didn't exist). Fixed: §4.2 now contains the **actual authoritative generator script** (`dataset/generate-timetable-doc.php`) with numeric self-check (35/44/1988), the `## Counts` literal parser patterns pinned, and the drift rule stated: **seeder/DB authoritative, doc regenerates from it, never hand-edited**.
- **[N2]** E6's false citation ("B111 Wed 08–10 is tpl#22") corrected: tpl#22 is B111 **Tue** 08–10; **no B111 Wed session exists pre-repair**. (Conclusion still verified clash-free.)
- **[N3]** §6.2's false ClassExceptionsSeeder justification ("week-3 only") replaced with the source-verified fact (14 rows, weeks 1–14, session ids {1,2,3,5,7,8,10,12,14,15,17,18,19,20}; session **9 absent** ⇒ no (9, wk5) collision).
- **[N4]** "un-sessioned slots" → "free-or-self slots" (2 of the 3 request-seeder lookups point at the proposing session's own row — pre-existing blemish kept as-is, unchanged by the repair).
- **[N5]** T-4 mechanism pinned: programme cap via `programmes.programme_code NOT IN ('RAF','RBU','RSD')` join (never a hardcoded cohort-id list); MPU-3232 must additionally assert **≥1 L + ≥1 T row** (frozen criterion 4's L+T clause).

### 💡 Optional (accepted)
- **[N6]** G4 pass condition extended: `replacement_requests` == 3, `class_exceptions` == 15 (converts §6's reasoning into a mechanical check).
- **[N7]** absorbed into T-5(a).
- **[N8]** notation fixed (type ∈ allowed types).

### 🔴 Outstanding
*(none — awaiting Round 2)*

### ⚖️ Verdict
Round 1: **FAIL — 1 🔴 ([Z1])** → fixed in same pass; awaiting confirmation.

## Batch 2 Round 2 — 2026-10-07 (design re-review)

Reviewer confirmed [Z1]+[N1]–[N6] landed with no decision drift — but flagged that the [N1] fix pass AUTHORED a defective generator.

### 🔴 Fixed
- **[Z2]** The Round-1-introduced verbatim generator rendered day headers in template order (authored sequence 0,3,1,4,2,… — not day order), violating §4.1's own contract and breaking T-5(c) day-tuple parity. Fixed: explicit `usort($tpl, fn($a,$b) => [$a['day_of_week'],$a['start_time']] <=> [$b['day_of_week'],$b['start_time']]);` before the render loop (lexicographic 'HH:MM:SS' compare safe), with a comment pinning WHY the sort exists.

### 🟡 Addressed
- **[Z2a]** Comment lines above `<?php` moved inside the file (pre-`<?php` content = literal output on a required .php file).
- **[Z2b]** §4 intro's stale "tinker heredoc" phrasing aligned with §4.2's committed-file approach (`dataset/generate-timetable-doc.php`).
- **[Z2c]** The numeric self-check (35/44/1988) is now an actual `echo SELF-CHECK` at the script's end — implementable as written.

### 💡 Optional
- §4.1/§4.2 physical ordering (contract after implementation) — cosmetic, left as-is.

### 🔴 Outstanding
*(none — awaiting Round 3)*

### ⚖️ Verdict
Round 2: **FAIL — 1 🔴 ([Z2])** → fixed; awaiting confirmation.

## Batch 2 Round 3 — 2026-10-07 (design re-review #2)

Reviewer executed a line-by-line syntax/brace/interpolation audit of the rewritten generator + arithmetic re-derivation (35/44/1988 all hold: 34×2-h→56, tpl#23 3-h→84, venue-keyed `$occ`) + fresh source check of the seeder's field shapes (pluck orientations correct; `$cn` key format matches seeder `keyBy`). No 🔴.

### 🟡 Addressed in-pass (mechanical, per reviewer's freeze instruction)
- **[Z3]** The emitted doc promised "one markdown table per day" but wrote the column header+separator once globally (blank line after top header terminates that table; daily rows would render as bare pipe text). Fixed in the frozen text: header+separator pair is now emitted after EACH `## Day` heading; the doc-level pair removed — §4 item 1's wording is now literally true.
- (Declarative) T-5(a) parser spec gains the explicit note: the `| start–end | … |` header and `|---|` separator lines are non-session lines, excluded from the 35-row count — carried into Batch 3's task wording.

### 💡 Optional (noted)
- `setAccessible(true)` unnecessary ≥8.1 — left off; fine.
- The Round-2 lesson (authored code must be execution-checked before freezing) recorded for Batch 3/4.

### 🔴 Outstanding
*(none)*

### ⚖️ Verdict
**Round 3: PASS — design FREEZEN** (Batch 2 of 4 ✓, with [Z3] applied in-pass). Specs (Batch 3) may begin.

## Batch 3 Round 1 — 2026-10-07 (specs)

Reviewer re-derived all load-bearing numbers from repo truth (tpl#23 3-h ✓; ClassExceptionsSeeder 14 rows / id 9 absent ✓; request hardcodes 9/12/22 ✓; DatabaseSeeder call list :44–52 ✓; 44 = 37−1+2+1+3+1+1 ✓; 35 ✓; 1988 = 34×56+84 ✓). No 🔴 — no frozen-design mismatch, no orphaned criterion, no Wave-3 smuggle.

### 🟡 Addressed in-pass (all four mandated)
- **[S1a]** `:78` line-anchor removed → text-survival wording ("clause survives on that UPDATE, asserted by grep within `markTimeSlotsOccupied`") — house precedent from the sync spec's S7; the coordinate will shift by construction when the transaction wrap is added above the loop.
- **[S1b]** Transaction scope pinned: `run()` wraps the **entire template loop** in one `DB::transaction()` — NOT per-template (which would leave 1..N−1 committed on abort = the exact partial state S1 forbids).
- **[S4a]** "a mismatch aborts" clarified as the apply-gate trust rule (operator stops; doc untrusted) — NOT a script change; the frozen §4.2 verbatim block ends in `echo` and must not be modified to `exit(1)`.
- **[S1c]** Message-content contract pinned: the four fields (module code, venue room code, day, window) formatted per design §2.4; §2.3's `{$row}` placeholder **superseded** — implementer must not ship a literal placeholder in the message.

### 💡 Optional (accepted)
- S5's citation extended to criterion 4 (T-4 enforces it).
- S3 gains the executable verify pin (class ids 9/12/22 resolve to their pre-repair identities by slot+module).
- "never hand-edited" made mechanical: post-regen `git diff --exit-code dataset/timetable.md` must be clean.

### 🔴 Outstanding
*(none)*

### ⚖️ Verdict
**Round 1: PASS — specs FREEZEN** (Batch 3 of 4 ✓). Tasks (Batch 4) may begin.

## Batch 4 Round 1 — 2026-10-07 (tasks)

Reviewer verified traceability (every task ↔ a frozen artifact; S1–S6 → T2.1/T2.2–T2.6/T5.4/T4.x/T6.1–T6.2/T3.1+T5.x — no orphan scenario; G1–G6 → T6.4/T6.3/T6.2/T5.1–5.3/T6.1/T6.5), execution ordering (T4.2's pre-reseed generator run is coherent — template-array-derived, deterministic), T7.2's classification, B-class traps (fail-fast recovery / rollback tag / re-seed), and all numbers. **0 🔴.**

### 🟡 Addressed in-pass
- **[T4/T1.3]** The "only `.commandcode/` + nothing else" assertion was literally false at T1's own execution time (the change's own `.sdd/` files legitimately show M/??) — reworded to the scoped-confinement form (sync T1.2's frozen wording): tracked-modified confined to this change's `.sdd/` dir + `.commandcode/` sole foreign untracked.
- **[T5/T6.5]** The smoke example was cohort-ambiguous (student IDs don't determine cohort; "RSD-cohort" under-specified). Fixed with deterministic named pairs: RSD3(S1)G3 student sees the E8 Fri 14–16 B102 row; RSD2(S1)G1 student sees NO MPU-3232 row.
- (Optional accepted) T7.2 gains the phased note: T7.3's changelog append lands after the assertion's window.

### 🔴 Outstanding
*(none)*

### ⚖️ Verdict
**Round 1: PASS — tasks FREEZEN** (Batch 4 of 4 ✓). All four batches frozen; `/sdd-apply` may begin.

## Verify pass — sdd-verify — 2026-10-08

**Mode:** apply-complete verify (all 4 artifacts frozen; T1–T6 executed). Reviewer-verifier subagent (self-run per user standing instruction).

### Criteria walkthrough (proposal rows 1–8)

- Row 1 (re-seed exit 0, fail-fast active): **PASS** — seed exit 0, zero `RuntimeException`; transaction scope + venue-keyed assert + surviving `where('status','available')` all verified in code (:30–69).
- Row 2 (counts exact, zero orphans): **PASS** — SQL 35/44/1988/0-null; orphan query empty; T-1 strict formula precedence honored incl. tpl#23's 84.
- Row 3 (zero venue+lecturer overlaps): **PASS** — T-2 green (boundary-exclusive predicate); manual placement audit: B102-Thursday and B005-Friday pairs are back-to-back (legal), no lecturer double-booking created.
- Row 4 (MPU sets per spec + L+T + programme cap): **PASS** — exactly 3 MPU-3133 rows (all RAF2/RBU1/RSD3 cohorts present, none outside) + exactly 4 MPU-3232 rows (exact set, ≥1 L + ≥1 T); T-4 asserts via programme_code join (no hardcoded ids).
- Row 5 (14/14 coverage + zero type violations): **PASS** — T-3 + T-4 venue-type filter + chained fixtures 3/15.
- Row 6 (doc parity): **PASS** — SELF-CHECK 35/44/1988; regen `git diff --exit-code` clean; T-5's three guards implemented and green (anti-truncation / Counts↔DB / full bidirectional equality, zero sampling).
- Row 7 (gates): **PASS** — lint adminer-only (after pint's generator excursion closed byte-identically), phpstan 0, phpunit **110/110 = 105 baseline + 5 new** (523 assertions).
- Row 8 (SDD gate): this report = its verify half; archive next.

### 🟡 Should Fix (non-blocking)

- **[W-1] T6.1's main-side-fix deviation disclosure:** the three test-file fixes were applied MAIN-SIDE after three consecutive subagent-spawn transport failures (ECONNRESET ×3). Reviewer judgment: **sanctioned** — context preserved (surgical fixes, probe-proven rollback, scratch deleted), but the deviation must live in repo artifacts, not just the transcript → recorded HERE + carried into T7.3's changelog rows.
- **[W-2] T-2's cohort-overlap diagnostic defect** (`test:265–277`): `sc1.class_session_id` and `sc2.class_session_id` selected un-aliased — second overwrites first; the failure-path message would print sc2's id labeled as sc1's. Diagnostics-only (invariant logic unaffected). **Deferred to a follow-up** (fixing post-verify would touch the verified artifact) — record in the archive's debt notes.

### 💡 Optional (archival accuracy notes)

- tasks.md T6.1's "585 ln" → the committed file is 708 ln post-fixes (archival note only).
- Seeder's `ceil((d/30)*14)` vs frozen `⌈d/30⌉ × 14` — coincide on the 30-min grid; T-1's strict form would diverge first on a grid-misaligned duration (fail loudly — acceptable). Argh note not needed; recorded here.
- Frozen design §4 item 1's stale "source @ <commit>" promise superseded by §4.2 — moot.

### Scope discipline

`T7 pre-state` = change's own outputs only (M seeders ×2, ?? generator/doc/test, ?? .sdd dir, ?? .commandcode) — consistent with the T7.2 enumeration; no Wave-3 smuggle.

### ⚖️ Verdict

**PASS — no 🔴 outstanding.** Proceed: T7.2 assertion → commits → T7.3 changelog → T7.4 archive → report (no push).
