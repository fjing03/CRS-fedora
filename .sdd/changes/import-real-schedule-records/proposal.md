# Proposal — import-real-schedule-records

Batch 1. Baseline for review: `explore-brief.md` (same directory).
Status: **DRAFT** — frozen only after reviewer pass.

---

## 1. Problem & why

The demo DB `class_replacement` currently runs on **35 hand-made class
sessions** built from invented templates (`ClassSessionsSeeder`), plus demo
overlays referencing them. Real, validated timetable data for the same 14
cohorts / 23 rooms / 14 lecturers now exists: the semester-202505 aSc-PDF
datasets (46-check re-derivation; current on-disk CSVs internally consistent —
see brief §1 for the stale recheck-count discrepancy).

The user has decided (**REPLACE, not coexist**): the real records become the
system's sole class-session source of truth. Hand-made sessions and everything
hanging off them go away.

## 2. Scope

### In scope

1. **In-repo dataset copy** under `dataset/`:
   - the 3 in-scope CSVs (lecturer / venue / programme, `202505`),
   - a PHP-portable form of the single-source helpers (`course_titles.py`,
     `lecturer_ids.py`) — as data files the importer reads,
   - `KNOWLEDGE.md` (durable copy — the original folder is outside any repo),
   - a `DATASET-NOTES.md` capturing the corrections discovered on 2026-10-08
     (155/101/101 counts; single surviving double-booking; lab-exception list
     incl. AMIT2034@B010 and the 9 kept L/T-in-lab blocks).
2. **Re-runnable importer** (artisan command + seeder, exact form decided in
   design) that on `class_replacement`:
   - deletes the 35 hand-made `class_sessions` **and** their `session_cohorts`
     links + `time_slots` occupancy (time_slots rows themselves stay — the grid
     is re-linked, not rebuilt; exact strategy in design),
   - drops the 3 `replacement_requests` and 15 `class_exceptions` (user-answered
     (a); Slice B/C re-demos later),
   - upserts modules for the **42 real course codes** (titles from the
     course-titles single source; placeholder + loud report when absent — never
     invented),
   - creates **101 class_sessions** (from the venue dataset as the physical
     truth, cross-asserted against the lecturer and programme datasets) +
     **155 session_cohorts** links,
   - **re-links `time_slots`**: every real session occupies its slots across 14
     weeks; zero orphans; zero venue double-bookings (DB partial-unique index
     is the backstop); holiday-day slots (W8 Mon, W14 Thu, W14 Fri) left
     `available` per the prompt's normative rule; `version` untouched at 1,
   - updates the `semesters` row dates to **2026-09-21 → 2026-12-27** (code
     stays `202605` — user-answered (b)),
   - **replaces the 5 stale `holidays` rows** with the canonical list that fits
     the 0–5 day CHECK: W8 Mon "Deepavali Holiday (In Lieu)", W14 Thu "Christmas
     Eve", W14 Fri "Christmas Day" (3 rows; Deepavali Sunday 8-Nov is outside
     the grid and lives only in docs/mock-data.js),
   - is **idempotent-by-verification**: safe re-runs either detect the already-
     imported state and verify it, or fail loudly on unexpected DB state
     (strategy detailed in design); never blind re-insert,
   - module handling: `modules.allowed_session_types` strategy for the 42 real
     codes AND prune-vs-keep of the ~34 unreferenced hand-made modules are
     **explicit design decisions** (FK-safe either way; see brief §4/§9).
3. **Row-count snapshot tooling**: before/after counts of all 26 tables,
   printed by the importer run and stored with the change's execution notes —
   becoming the standing "records-intact" gate for future merges.
4. **Test rework (mandatory knock-on):**
   - `TimetableSeedInvariantsTest`: re-pin 35/44/1988 → **101 / 155 / 3963**
     (exact occupied count re-derived in design; holiday rule applied),
     re-derive T1–T5 against real data; T2 gets an explicit allowance for the
     kept DFT2-Wed cohort clash (user-answered (c)); position-id fixtures
     1..35 re-anchored to real tuples.
   - `TimetableWiringTest` / `NavIdentityTest`: verified unaffected (brief §6);
     no rework beyond confirming green.
   - New or extended tests asserting: no orphan time_slots, zero double-booked
     venue slots, holiday slots free, semester dates, holiday rows, login
     accounts intact (5425 is_pl, 5770, 25RSD0001).
5. **Execution on the demo DB** with fresh pre-run `pg_dump` backup, before/
   after evidence, live smoke (login 5425 / 25RSD0001, Playwright
   `tests/nav-identity.spec.ts`).
6. **Changelog entries** (`page-changelogs/` primary domain log +
   `backend-automated-by-ai.md`) and declarative count corrections to
   `KNOWLEDGE.md`'s repo copy (addendum form; the source folder is read-only
   ground truth and is not edited).

### Out of scope

- Students, users, lecturers, cohorts, venues rows (datasets carry no changes
  for them; roster already matches exactly).
- `mock-data.js` §2.2 holidays — already canonical; not regressed.
- Slice B/C of `wire-backend-into-refactored-ui` (frozen; its queued addendum
  stays queued; demo overlays re-creation happens there).
- Out-of-scope CSVs (never imported), `upstream` remote, any UI redesign.

## 3. Decisions already made (user + prompt)

| # | Decision | Source |
|---|---|---|
| 1 | REPLACE hand-made sessions, don't coexist | prompt ✅ section |
| 2 | Drop 3 replacement_requests + 15 class_exceptions | user (a) 2026-10-08 |
| 3 | Keep semester code `202605`; fix dates only | user (b) |
| 4 | Keep the DFT2-Wed cohort clash as printed | user (c) |
| 5 | Exception-list the 9 L/T-in-lab blocks (+ AMIT2034@B010 room exception); no re-homing. The "exception" is **documentation + importer/test allowance only — no DB rows** (class_exceptions stays 0) | user (extra) |
| 6 | Holidays → 3 grid rows; time_slots stay free on holiday days | prompt normative |
| 7 | Import only current on-disk in-scope CSVs (155/101/101) | brief §1 finding |
| 8 | Never `migrate:fresh --seed` the demo DB; no `pkill -9 php` | standing rules |
| 9 | No push without explicit authorization | standing rule |

## 4. Success criteria

1. Importer run leaves: class_sessions **101**, session_cohorts **155**,
   occupied time_slots = **3963** (derivation inputs design must show:
   147 h × 2 slots/h × 14 weeks = 4116, minus 153 holiday-affected slot-rows —
   W8 Mon 62, W14 Thu 54, W14 Fri 37), holidays **3** (W8 Mon/W14 Thu/W14 Fri),
   semesters dates 2026-09-21→2026-12-27, replacement_requests **0**,
   class_exceptions **0**, time_slots orphans **0**, venue double-bookings
   **0**, users/students/lecturers/cohorts/venues unchanged; modules count per
   the design decision, recorded in the snapshot.
2. All gates green: phpunit with `EXPECTED_*` constants matching the importer's
   post-run counts (diff shown in execution notes), phpstan 0,
   `composer run lint:check`.
3. Live smoke: 5425 sees own real sessions; 25RSD0001 sees own cohort sessions;
   nav-identity Playwright spec green.
4. Before/after row-count snapshot stored; fresh backup taken pre-run.
5. Re-running the importer a second time does not duplicate or corrupt data.

## 5. Risks & mitigations

- **Delete-phase FK ordering** — `time_slots.class_session_id` is a plain FK
  with **no cascade**: deleting sessions before clearing occupancy fails
  outright. Mitigation: FK-safe order (clear time_slots links → session_cohorts
  → sessions) inside a single transaction.
- **Grid desync / orphans** → exact-count assertion per session (house pattern
  from `ClassSessionsSeeder`), full-recount verification pass, snapshot diff.
- **Wrong-source drift** (future CSV edits) → importer asserts dataset
  fingerprint counts (101/155/147 h/42 codes) before writing.
- **Demo overlays half-dropped** → single transaction for the delete phase;
  snapshot confirms 0/0.
- **Test churn hides real regressions** → re-pinned invariants derive expected
  values from the same dataset files the importer reads, not hardcoded magic
  numbers where feasible.
