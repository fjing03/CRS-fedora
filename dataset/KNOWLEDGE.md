# Knowledge — Schedule datasets & extraction pipeline (semester 202505)

Summary of `dataset csv md/` and `py script/` for future agents. Read this before
touching the datasets or writing a DB seeder from them.

Last reviewed: 2026-10-08.

---

## 1. What this is

A **PDF → CSV/MD → (DB / mock-data.js) pipeline** that turned the three aSc
Timetables PDFs of semester **202505** into validated, in-scope schedule
datasets for the CRS prototype.

The three PDFs (one view of the same underlying timetable):

| PDF | Page meaning | Extractor |
|---|---|---|
| `11.07.2025 202505-lecturer.pdf` | one page per lecturer | `extract_lecturer_schedules.py` |
| `11.07.2025 202505-classroom.pdf` | one page per room | `extract_venue_schedules.py` |
| `11.07.2025 202505-Programme.pdf` | one page per cohort (63 pages) | `extract_programme_schedules.py` |

All three share the same grid: landscape A4, 17 time columns 09:00–17:30 in
30-min slots × 6 day rows Mon–Sat. Text extraction uses PyMuPDF spans (small
≈6.8pt spans = course/venue/lecturer, large ≥10pt spans = cohort text); grid
geometry comes from pdfplumber ruled lines.

---

## 2. Folder layout

```
Schedule ds/
├── dataset csv md/                          # extracted datasets + reports
│   ├── dataset-lecturers-schedules-202505.csv / .md
│   ├── dataset-lecturers-schedules-202505-out-of-scope.csv
│   ├── dataset-programmes-schedules-202505.csv / .md
│   ├── dataset-programmes-schedules-202505-out-of-scope.csv
│   ├── dataset-venues-schedules-202505.csv / .md
│   ├── dataset-venues-schedules-202505-out-of-scope.csv
│   └── recheck-report-202505.md
├── pdfs/                                    # the read-only source PDFs
└── py script/
    ├── extract_lecturer_schedules.py
    ├── extract_venue_schedules.py
    ├── extract_programme_schedules.py
    ├── course_titles.py                     # module-code -> title (single source)
    ├── lecturer_ids.py                      # short name -> staff ID (single source)
    └── seed_mock_data.py                    # CSVs -> mock-data.js §2.5a/2.6/2.7/2.8/2.11
```

---

## 3. Prototype scope (CodingMAIN.md §3, §8) — what is "in scope"

A block is **in scope** only if ALL of these hold:

1. **Cohort** is one of the 14:

   ```
   DFT1(S1)G1  DFT2(S1)G1  DSF1(S1)G1  DSF2(S1)G1
   RSD1(S1)G1  RSD2(S1)G1  RSD2(S1)G2  RSD2(S1)G3
   RSD3(S1)G1  RSD3(S1)G2  RSD3(S1)G3
   RAF2(S3)G2  RAF2(S3)G4  RBU1(S1)G1
   ```

2. **Venue** is one of the 23 Block-B rooms:
   `B002, B005, B006, B009, B010, B011, B014–B018, B100–B111`

3. **Subject** is not on the excluded list: `AMCS1034`

4. **(Venue & programme extractors only)** the lecturer printed on the room/
   cohort page is one of the 14 known staff (resolvable via `lecturer_ids.py`).

Everything else is kept in a separate
`dataset-*-schedules-202505-out-of-scope.csv` with a `scope_reason` column —
**never seed out-of-scope rows**. Mixed blocks (an in-scope cohort sharing a
block with an out-of-scope one) keep only their in-scope cohort labels.

### Counts (verified 46 checks / 0 findings in `recheck-report-202505.md`)

| Dataset | In-scope | Hours | Out-of-scope |
|---|---|---|---|
| Programme (14 cohorts) | 184 blocks | 282 h | 558 blocks |
| Venues (23 rooms) | 121 blocks | 182.5 h | (see CSV) |
| Lecturers (14 staff) | 101 blocks | 147 h | 62 blocks / 95.5 h |

Venue hours exceed programme hours because shared classes (one class, several
cohorts) print one row per cohort page but one row per room.

---

## 4. CSV column shapes

### dataset-lecturers-schedules-202505.csv

```
staff_id,lecturer,day,start,end,duration_hours,course,session_type,venue,cohorts,source
5770,En. Lim Jia Zheng,Monday,11:00,13:00,2,AMCS2093,Lecture,B110,DFT2(S1)G1/DSF2(S1)G1,11.07.2025 202505-lecturer.pdf p.41
```

- `lecturer` = **full name as printed in the lecturer PDF** (with titles
  En./Pn./Dr./Ts./Cik. — strip them when seeding; titles were already removed
  from the DB lecturer names in CRS-fedora).
- `cohorts` = `/`-joined seeder-key format (`DFT2(S1)G1/DSF2(S1)G1`) — a shared
  lecture prints **one row with several cohorts**.
- `venue` may carry a room label (`B009 - Lab 1`) — take the first token as code.

### dataset-venues-schedules-202505.csv

```
venue,room_label,day,start,end,duration_hours,course,session_type,lecturer,cohorts,source
B009,Computer Laboratory 1,Tuesday,14:00,16:00,2,BMIT2013,Lecture,Jia Zheng,RSD2(S1)G2/RSD2(S1)G3,11.07.2025 202505-classroom.pdf p.14
```

- `lecturer` = printed **surname form only** (e.g. "Jia Zheng", "Dr Chris").
  Map to staff ID via `lecturer_ids.py`, to full registry name via the
  surname-voting described in §7.

### dataset-programmes-schedules-202505.csv

```
cohort,day,start,end,duration_hours,course,session_type,lecturer,venue,source
DFT2(S1)G1,Monday,11:00,13:00,2,AMCS2093,Lecture,Jia Zheng (5770),B110,11.07.2025 202505-Programme.pdf p.25
```

- `cohort` = one seeder-key label per row (the cohort of the page).
- `lecturer` = surname form **+ `(staff_id)` appended** (via `lecturer_ids.py`).
- Shared classes appear once per cohort page, so a class taught to several
  cohorts yields several rows (NOT a clash — the re-check counts them apart).

`day` ∈ Mon…Sat, times are 30-min aligned within 09:00–17:30, `start < end`,
`duration_hours == (end−start)/60`.

---

## 5. py script/ — the single-source helpers

### course_titles.py — `COURSE_TITLES`

Module-code → real title. Titles are NOT printed in the PDFs (codes only);
anything missing prints as `'Subj <code>'`/`—`. 22 known titles, including the
user-supplied batches of 2026-10-06:

```
AMCS1013 Problem Solving and Programming      AMCS1043 Database Development and Applications
AMIS1003 Introduction to Cybersecurity        AMIT1303 Introduction to Interface Design
AMSE1003 Software Engineering                 BMIT1723 IT Fundamentals and Applications
AMIT2033 Networking Essentials                AMIT2034 Fundamentals of Computer Networks
BMIT2154 Switching and Routing Technologies   BMIT3084 Enterprise Networking
BMIT2123 Internet of Things                   BMIT2013 Web-Based Integrated Systems
BMIT1173 IT Fundamentals                      BMIS2003 Blockchain Application Development
BMCS3033 Social and Professional Issues       BMCS2053 Object-Oriented Analysis and Design
BMIS2113 Information Technology Infrastructure
MPU-3133 Falsafah dan Isu Semasa              MPU-3232 Entrepreneurship
BMIT2203 Human Computer Interaction           BMCS2063 Data Structures and Algorithms
AMCS2093 Operating Systems
```

When seeding `modules`, never invent titles — absent codes fall back to a
placeholder and get reported.

### lecturer_ids.py — `LECTURER_IDS`

Printed short name (classroom/programme PDFs) → staff ID:

```
Ellis 2873 · Lee 3221 · Muada 3799 · Teng 3825 · Patricia 4127 · Dr Chris 4288
Sharon 4363 · Chang 5254 · Su 5425 · Ts Shikin 5514 · Rahmat 5516
Jefther 5599 · Daniel 5652 · Jia Zheng 5770
```

Full printed names per staff ID (lecturer PDF `TARGETS`):

```
2873 Cik Ellis Chieng              5425 Pn. Surayaini Binti Basri
3221 Pn. Lee Yee Fong              5514 Ts. Norshikin Binti Zainal Abidin
3799 En. Muada Bin Ojih            5516 En. Mohd Nur Rahmat Bin Mohd Taat
3825 Pn. Teng Nga Sing             5599 En. Jefther Edward
4127 Pn. Patricia G Kissol         5652 En. Daniel Royd Michael
4288 Dr. Christopher Lazarus       5770 En. Lim Jia Zheng
4363 Pn. Tan Sharon
5254 Dr. Chang Foo Chung
```

Short names **without** a staff ID (out-of-scope only): Kenny, Paul, Noirom,
Jr Kinabalu, Chan, Barbara Vun, Tan Ai Ping, Suzanne, Pit Kee, Jernestcia,
Jamie.

---

## 6. Seeding conventions (from seed_mock_data.py — replicate in any DB seeder)

- `di` (day index): **0 = Monday … 5 = Saturday**.
- `start`/`end` slot indexes: 30-min index **from 08:00** (09:00 == 2), and the
  **end is inclusive** (`end = slot(end_time) − 1`). In the DB the class_sessions
  schema stores clock times (`start_time`/`end_time`) — do the index arithmetic
  only where the UI needs it.
- `venue` = **bare room code** (`B010`), never the label.
- `lecturer` = **full registry name** (resolved from surnames, §7); the pages
  compare against `MockData.currentUser.name`.
- `week` = **0-based** index; note the arrangement page's `checkConflict()`
  parses the 1-based week labels and is off by one there (known, deliberate).
- Semester start (Week-1 Monday): **2026-09-21**, 14 teaching weeks.
- Session types: `(L)=Lecture, (T)=Tutorial, (P)=Practical` → store `L/T/P`.

### Public holidays (NOT in the datasets — canonical list below)

- The aSc PDFs and all six CSVs carry **no holiday information** — a holiday is
  indistinguishable from a no-class slot in the raw data.
- **Demo semester: 14 weeks, Mon 21 Sep 2026 – Sun 27 Dec 2026**
  (Week-1 Monday = 2026-09-21; week numbers below are 1-based, dayIndex 0 = Mon).

  | Date | Day | Holiday | Coverage | Week | dayIndex |
  |---|---|---|---|---|---|
  | 8 Nov 2026 | Sunday | Deepavali | National | 7 | 6 |
  | 9 Nov 2026 | Monday | Deepavali Holiday (In Lieu) | State / National replacement | 8 | 0 |
  | 24 Dec 2026 | Thursday | Christmas Eve | Sabah State Holiday | 14 | 3 |
  | 25 Dec 2026 | Friday | Christmas Day | National | 14 | 4 |

- 8 Nov (Deepavali itself) is a **Sunday** — outside the Mon–Sat teaching grid,
  so it paints no cell; its effect arrives via the **9 Nov Monday in-lieu** day.
  The effective grid holidays are therefore **Week-8 Monday**, **Week-14
  Thursday** and **Week-14 Friday**.
- Today's declaration source is **`public/js/mock-data.js` §2.2 `holidays`**
  (`{ week, dayIndex, label }`). It currently holds 5 demo placeholders
  (W1 Mon, W3 Tue, W3 Thu, W5 Wed, W7 Fri) that predate this real list.
- `seed_mock_data.py` re-parses that section into `HOLIDAY_CELLS` and **walks
  every demo status (conflict/pending/replacement) off holiday days** — a red
  holiday cell would paint over the status anyway.
- Consumers: CohortTimetable + Student My Timetable render holiday cells;
  **MyTimetable has no holiday render path** (explicit non-goal). The
  arrangement page has its own holiday embedded in `arrangementWeeks`
  (page-specific, deliberately not double-sourced).
- For a future DB seed there is **no holiday table** yet — migrate from the
  table above (it supersedes the §2.2 placeholders once mock-data.js is updated).

### Demo status overlays (PDFs carry no status)

The PDFs record occupancy only — normal/replacement/pending/conflict states do
not exist in the source. `seed_mock_data.py` overlays them via
`COHORT_STATUS_DEMO` and `MY_STATUS_DEMO` (week → block/code picks), pinned to
the logged-in lecturer's own subject where the arrangement page needs it
demo-able, and kept off holiday days per the rule above. A DB seeder should
likewise leave class-session data status-neutral and let the app's own
request/approval tables drive statuses — do not seed fake statuses from these
CSVs.

### Lab-room rule (§3 / spec 1.3.3)

Labs `B005, B009, B010, B011` (cap 28) and `B006` Cisco lab (cap 32) are
**Practical-only**. A Lecture/Tutorial in a lab is only acceptable for the
Networking/IoT modules `AMIT2033, AMIT2034, BMIT2154, BMIT3084, BMIT2123`
(and BMIT2123 has no block in 202505). Other L/T blocks that sit in labs are
flagged in `dataset-venues-schedules-202505.md § Lab sessions (L/T)` and must be
re-homed or exception-listed **before seeding**.

---

## 7. Surname → full-name resolution (how seed_mock_data.py does it)

The lecturer CSV has full names; the venue/programme CSVs have surnames.
Resolution (already computed, re-derivable):

1. Key every lecturer-CSV row by `(course, day, start, end, cohort)` → full name.
2. For every programme-CSV row with the same key, vote the printed surname for
   that full name (Counter).
3. Take each surname's most-voted full name. Ambiguous surnames are reported
   rather than guessed; unknown surnames stay as printed.

Only the 14 in-scope lecturers can resolve — anyone else means the block is
out of scope.

---

## 8. Quirks & gotchas found in the source

- **Stacked cells** — 5 grid cells hold 2 classes stacked vertically (and some
  side-by-side); the programme extractor splits them into separate rows
  (e.g. DFT2 Thursday 13:30‑14:30 MPU-2212 B103 + MPU-2202 B108).
- **Cohort double-bookings printed by the source itself** (2, keep as-is):
  - `DFT2(S1)G1` Wednesday: AMIT2014 10:00–12:00 vs AMIT2034 11:00
  - `DFT2(S1)G1` Thursday: MPU-2212 13:30–14:30 vs MPU-2202 13:30
- **Cross-page re-homing** — 3 AMIT2034 blocks of DFT2(S1)G1 are printed only on
  p.61 (`DFT2 (S1) Jefferson Ng (2310971)`, a student view whose title is not a
  clean cohort label). They are re-homed into the in-scope programme CSV with
  `source` p.61; the raw rows stay in OUT OF SCOPE too.
- **Duplicated cohort tokens** in a cell (`DFT2 (S1)/DFT2 (S1) Name`) are
  deduped; the re-check asserts no duplicated token survives in any row.
- **Courses without (L)/(T)/(P)** markers exist (e.g. `MPU34W2`) — these print
  with empty `session_type`; the 3 source double-bookings are the only overlaps
  (E2), 63 room slots serve multiple cohort rows by design (shared classes).
- **Grid lines are sometimes double-drawn** (p.20 of the classroom PDF) — the
  extractors dedupe lines <2pt apart; keep that if re-implementing.

---

## 9. Re-running the pipeline

```bash
cd "py script"
python3 extract_lecturer_schedules.py           # lecturer PDF -> CSV/MD
python3 extract_venue_schedules.py              # classroom PDF -> CSV/MD
python3 extract_programme_schedules.py          # programme PDF -> CSV/MD (+ cross-check vs venue CSV)
python3 seed_mock_data.py                       # CSVs -> mock-data.js SCHEDULE sections
```

- Default inputs/outputs are relative to the script folder (`../pdfs`,
  `../dataset csv md`); both accept `[pdf_path] [out_dir]` overrides.
- `extract_programme_schedules.py` raises if a documented `CROSS_PAGE_ADDITIONS`
  entry no longer matches — that is intentional (it guards silent data drift).
- `recheck-report-202505.md` re-derived every dataset independently; **46/46
  checks passed, 0 findings**. `seed_mock_data.py` requires exactly 14 cohorts
  and 23 venues in the registries it reads, and fails loudly otherwise.

---

## 10. Using these as the future DB dataset (CRS-fedora)

- The lecturer CSV can replace the earlier hand-made per-staff CSVs
  (`lecturer_schedule_5770.csv`, `lecturer_timetable_pls.csv`) — it adds
  staff 2873, 3221, 3799, 3825, 4127, 4288, 4363, 5254, 5514, 5599, 5652
  with full multi-cohort blocks.
- Map `staff_id` → `lecturers.staff_id` → `users.id`; module `course` →
  `modules.code` (create missing ones, titles from `course_titles.py`); cohort
  labels → `cohorts` in seeder-key format; venue first token → `venues.code`.
- Seed **only** the in-scope CSVs. Out-of-scope CSVs exist to prove nothing
  was dropped, not to be imported.
- Semester code in datasets is `202505`; the current app semester is `202605`
  — decide per run whether to relabel the semester or keep the source code.

---

## 11. Addendum 2026-10-08 — corrections after live-DB audit (CRS-fedora)

Verified first-hand against `class_replacement` (26 tables). Where this file and
the live schema disagree, THIS SECTION WINS:

1. **A `holidays` table EXISTS** (semester-linked). ⚠️ Its current 5 rows (W1 Mon,
   W3 Tue, W3 Thu, W5 Wed, W7 Fri) are **stale demo placeholders**. The canonical
   real list is §6's table (Deepavali 8 Nov Sun + 9 Nov Mon in-lieu, Christmas
   24/25 Dec = effective grid holidays W8 Mon, W14 Thu, W14 Fri) — already live
   in `mock-data.js` §2.2. The DB rows must be **replaced** by the canonical
   list during the import, not kept.
2. **A `semesters` table EXISTS**: 1 row, `semester_code 202605`, week_count 14,
   but start_date **2026-08-31 / end 2026-12-06 is stale**. Canonical demo
   semester (§6): **Mon 2026-09-21 → Sun 2026-12-27**. The import must update
   the semesters row to match — this also anchors the holiday week numbers and
   the dataset's week/day mapping.
3. **A `time_slots` table EXISTS: 38,640 rows** (semester × 23 venues × 14 weeks
   × 6 days × 30-min slots) with `class_session_id`, `status`, `version` (OCC).
   Any class_sessions import MUST re-link this grid — treat as core scope.
4. **Column names**: `modules` uses `module_code`/`module_name` (+ `allowed_session_types`),
   not `code`/`title` as §10 sketches. `class_sessions` = semester_id, module_id,
   lecturer_id, day_of_week, start_time, end_time, venue_id, session_type.
   Shared classes = one `class_sessions` row + N `session_cohorts` links.
5. **Row baseline (pre-import)**: users 266, lecturers 14 (already the dataset
   roster), students 252, cohorts 14, venues 23, modules 36, class_sessions 35,
   session_cohorts 44, holidays 5, time_slots 38640. Lecturers.is_pl is NOT in
   the datasets (5425's is_pl=true is seed-set — preserve or decide).
6. **Safety backup**: `CRS-fedora/backups/class_replacement-pre-import-20261008.dump`
   (gitignored; **restore-verified by scratch-DB drill 2026-10-08 — baseline
   35/38640/266/5 reproduced**). The import prompt lives in this folder:
   `PROMPT-import-real-records.md`.

---

## 12. Addendum 2026-10-08 — in-repo import copy (CRS-fedora `dataset/`)

This file's repo copy carries the real-records import. Corrections relative to
§1/§3/§8 (the source folder outside the repo is the read-only ground truth and
is not edited):

1. **Counts on disk: programme 155 rows / 229 cohort-hours, venue 101 rows /
   147 room-hours, lecturer 101 rows / 147 h** — the recheck-report headline
   (184/121/101) describes the pre-14:14 regeneration and is stale. The
   current files are internally consistent (155/155 programme↔venue
   cross-check; 155 cohort-instances from all three views).
2. **Only ONE source double-booking survives** (§8 listed 2): DFT2(S1)G1 Wed,
   AMIT2014 10:00–12:00 B009 vs AMIT2034 11:00–13:00 B005 (overlap 11:00–12:00).
   The Thu MPU-2212/MPU-2202 clash is gone — MPU-2202 rows are out-of-scope now
   (lecturer "Jamie" has no staff ID). Kept as printed (user decision).
3. **§8's empty-`session_type` remark is stale** — no empty session_type rows
   exist in any current in-scope CSV; MPU34W2 does not appear in scope.
4. **DB holiday rows are 3, not 4/5**: `holidays.day_of_week` CHECK 0–5 cannot
   store Deepavali Sunday 8-Nov; the table holds W8 Mon (Deepavali in lieu),
   W14 Thu (Christmas Eve), W14 Fri (Christmas Day) only.
5. **is_pl**: the datasets carry no PL data; the seed sets 5425 **and 5516** to
   is_pl=true (both preserved by the import — users untouched).
6. **Module titles**: 38 of the 42 dataset codes now have real titles
   (22 from the .py source + 16 more user-directed via Downloads/mock-data.js
   and direct correction on 2026-10-08 — see `dataset/import/course-titles.php`).
   4 codes remain code-fallback: AMSE2002, AMSE2003, AMSE2013, MPU-2302.
