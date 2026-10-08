# Spec — timetable-seed-repair

Scope of this spec: the observable *requirements* the repair must satisfy — executable properties of post-apply artifacts (seeder code, seeded DB, generated doc, tests). Aligned with frozen `proposal.md` criteria 1–7 and `design.md` §1–§7.

## Scenarios

### S1 — The occupancy pass fails loudly, never silently (D1; proposal criteria 1, 2)
`database/seeders/ClassSessionsSeeder.php` **MUST** post-apply:
- `run()` wraps the **entire template loop** in one `DB::transaction()` — NOT a per-template transaction (a per-template wrap would leave templates 1..N−1 committed on abort, i.e. exactly the partial seed state S1 exists to forbid — [Y5]/design §2.1)
- `markTimeSlotsOccupied()` **MUST** accumulate the affected-row count across the 14 weekly UPDATEs and, after the loop, throw `RuntimeException` when the count ≠ `⌈(end−start)/30min⌉ × 14` (2-h template → **56**, venue-keyed, cohort-count **independent** — frozen [Y1] wording; the one 3-h template tpl#23 → 84)
- the thrown message **MUST** carry the conflicting template's module code, venue room code, `day_of_week`, and window — operator fixes the template, not the DB. (Content contract: the four fields are formatted into the message per design §2.4; design §2.3's `{$row}` interpolation placeholder is **superseded** by this contract — an implementer must not ship a literal `{$row}` in the message)
- the `->where('status', 'available')` clause **survives on that UPDATE** (assert by grep within `markTimeSlotsOccupied`, not by a line number — the added `DB::transaction` wrap above the loop shifts coordinates by construction; the missing *post*-condition was the bug, the clause itself is a valid guard)

### S2 — Template set conforms to the frozen edit table (D2 + D3; proposal criteria 2–4)
The template array (post-apply source, verified against `design.md` §3's E1–E8 table) **MUST**:
- contain **exactly 35** templates; `session_cohorts`-derived rows = **44**; occupied `time_slots` = **1988**
- **no pair of templates** shares (venue, `day_of_week`, overlapping window) — the 3 live-DB clashes (#25/#4, #29+#30, #32/#12) structurally impossible
- **no pair** shares (lecturer, `day_of_week`, overlapping window) — criterion 3's second clause
- **no cohort** appears in two time-overlapping sessions; all 14 cohorts covered by ≥1 session
- MPU-3133 rows = exactly 3: B110 Wed 10–12 [RAF2(S3)G2, RAF2(S3)G4] (merged E2/E3) · B111 Fri 08–10 [RAF2G2, RAF2G4, RBU1] (tpl#31 untouched) · B111 Wed 14–16 [RSD3(S1)G1, RSD3(S1)G2, RSD3(S1)G3] (E6) — cohort cap: any cohort outside {RAF, RBU, RSD} programmes is a **spec violation**, not a data quirk
- MPU-3232 rows = exactly 4: B111 Mon 14–16 [RSD2G2, RSD2G3, RSD3G3] (E4) · B102 Thu 14–16 [RSD2G2] (E5) · B102 Thu 16–18 [RSD2G3] (E7) · B102 Fri 14–16 [RSD3G3] (E8)
- every session's `session_type` ∈ its venue's `allowed_session_types` (B005 lab ⇒ `P` only; B102 tutorial ⇒ `L,T`; B110/B111 hall ⇒ `L`)

### S3 — The removal leaves inserted-order identity intact for the demo-request fixture (design §6.2)
Post-repair seeding NOTES (not a code requirement — an ordering invariant):
- template deletion at position 30 ⇒ templates 1–29 seed to class ids **1..29** unchanged; `ReplacementRequestsSeeder`'s hardcoded `class_session_id` values (9, 12, 22) resolve to the same classes as the pre-repair DB (source-verified positions; executable pin at verify: SQL asserting class ids 9/12/22 = {BMIT9012 B107 Mon 08–10, B110 Mon 14–16 multi, BMIT9012 B111 Tue 08–10 multi} — the pre-repair identities)
- no re-ordering of the template array (insertGetId sequence = array order) is permitted in this change after the frozen placement is applied

### S4 — `dataset/timetable.md` is machine-generated and machine-parsed (D4; proposal criterion 6)
Post-apply **MUST**:
- `dataset/generate-timetable-doc.php` exists, matching `design.md` §4.2's verbatim block (committed as the generator; **seeder/DB authoritative, doc regenerates, never hand-edited** — mechanically: after regeneration, `git diff --exit-code dataset/timetable.md` against the committed doc MUST be clean, i.e. the committed doc == generator output)
- `dataset/timetable.md` exists with: day-grouped `## <Day> (day_of_week = N)` sections (sorted by day then start — the `usort` MUST be present in the generator), one `| start–end | venue | module | lecturer | type | cohorts |`-table per day (header + `|---|` separator inside each section — frozen [Z3]), and a final `## Counts` block with the three literal patterns `Session count: <N>`, `Session-cohort rows: <N>`, `Occupied time slots: <N>`
- the generator's `SELF-CHECK` print (35 / 44 / 1988) is its exit evidence — a mismatch means the operator STOPS: doc rows untrusted, do not proceed to seeding the claim-space (this is the apply-gate trust rule, NOT a script behavior — the frozen §4.2 verbatim block ends in `echo`, it must not be modified to `exit(1)`; the committed file must match §4.2 exactly)
- doc rows' cohort names use the `PROG<yr>(S<sem>)G<grp>` key format (matches `CodingMAIN` conventions and the seeder's own `keyBy`)

### S5 — Invariant test suite commissioned and green (proposal criteria 2–4, 5, 6)
New `tests/Feature/TimetableSeedInvariantsTest.php` on `RefreshDatabase` + full `DatabaseSeeder` (suite-proven pattern) **MUST** assert (each = design §5's T-x verbatim):
- **T-1** every session has exactly `⌈duration/30⌉ × 14` occupied slots; global sum = 1988; no occupied slot has a null `class_session_id`
- **T-2** zero (venue ∧ day ∧ overlap), (lecturer ∧ day ∧ overlap), and (cohort ∧ day ∧ overlap) pairs — the overlap test is `a.start < b.end AND b.start < a.end` (boundary-exclusive; back-to-back sessions are legal — E7's Thu 16–18 follows tpl#33's Thu 14–16 legitimately)
- **T-3** all 14 cohorts covered (≥1 session each)
- **T-4** MPU-3133: ⊇ {RAF2(S3)G2, RAF2(S3)G4, RBU1(S1)G1} ∧ ∋ ≥1 RSD3 group ∧ **no cohort outside the RAF2/RBU1/RSD3 programmes** (asserted via `programmes.programme_code NOT IN ('RAF','RBU','RSD')` join — never a hardcoded cohort-id list); MPU-3232: cohort set == exact {RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G3} **∧ ≥1 L row ∧ ≥1 T row**; every session's type ∈ venue allowed types
- **T-5** doc↔DB parity: parse `dataset/timetable.md` (header/`|---|` lines are non-session rows — excluded from counts; frozen [Z3] clarifier) → exactly **35** session rows parsed (anti-truncation, `## Counts` not trusted for this number) → `## Counts` values match DB queries → **FULL bidirectional set-equality** of all 35 (day, start, venue, module, cohorts) tuples (zero sampling — frozen [Z1])
- the pre-change 105-test baseline stays green (any regressions in the existing suite = a change to report, never absorbed)

### S6 — Re-seed choreography (D5; proposal criteria 1, 6)
`php artisan migrate:fresh --seed` post-apply **MUST**:
- exit 0 with zero `RuntimeException` occurrences (fail-fast archive: silence-by-assertion is now structurally impossible)
- chain `ReplacementRequestsSeeder` after `ClassExceptionsSeeder` in `DatabaseSeeder.php:44-52`'s call list (one added line; nothing else reordered)
- yield `replacement_requests` = **3** and `class_exceptions` = **15** (14 from ClassExceptionsSeeder + the single (session 9, wk 5) conflict fixture — no duplicate-key competition, session 9 ∉ ClassExceptionsSeeder's set, source-verified [N3])
- yield §1's exact `## Counts` numbers (35 / 44 / 1988) — recorded as evidence lines in the apply notes

## Boundary rules

- **No** schema/migration edits, no OCC engine changes, no UI/mock-data edits, no `LecturerScheduleSeeder` disposition (parked, Q5) — Wave-3 concerns stay fenced.
- Verification commands used per design §7 G1–G6; adminer.php remains the lint baseline exception.
