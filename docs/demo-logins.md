# Demo logins — students (3 per cohort) and staff

**Purpose:** a testing cheat-sheet for the two demo databases. Read-only reference; nothing here is
generated at runtime.

- **Login page (students):** `/login/student`
- **Password (all 42 student IDs):** `Tarumt@2026`
- **Source of truth:** the `students` / `lecturers` tables in the demo database. The list below was read
  back from `class_replacement_fresh` on 2026-11-09; re-read it any time with

  ```bash
  psql -q -t -A -F' | ' -U philler -d class_replacement_fresh -c "
  select p.programme_code || c.current_year || '(S' || c.semester || ')G' || c.tutorial_group as cohort,
         s.student_id
  from cohorts c
  join programmes p on p.id = c.programme_id
  join students s on s.cohort_id = c.id
  order by cohort, s.student_id;"
  ```

> **These are handles, not rosters.** Every cohort really holds 5–25 students (155 students exist in the
> demo database). The three IDs per cohort are simply the first three of each cohort, kept stable so a
> receipt can be repeated.

## 14 cohorts × 3 student logins

| # | Cohort | Student logins |
|---|---|---|
| 1 | DFT1(S1)G1 | `25DFT0001` · `25DFT0002` · `25DFT0003` |
| 2 | DFT2(S1)G1 | `24DFT0001` · `24DFT0002` · `24DFT0003` |
| 3 | DSF1(S1)G1 | `25DSF0001` · `25DSF0002` · `25DSF0003` |
| 4 | DSF2(S1)G1 | `24DSF0001` · `24DSF0002` · `24DSF0003` |
| 5 | RAF2(S3)G2 | `24RAF0001` · `24RAF0002` · `24RAF0003` |
| 6 | RAF2(S3)G4 | `24RAF0006` · `24RAF0007` · `24RAF0008` |
| 7 | RBU1(S1)G1 | `25RBU0001` · `25RBU0002` · `25RBU0003` |
| 8 | RSD1(S1)G1 | `25RSD0001` · `25RSD0002` · `25RSD0003` |
| 9 | RSD2(S1)G1 | `24RSD0001` · `24RSD0002` · `24RSD0003` |
| 10 | RSD2(S1)G2 | `24RSD0008` · `24RSD0009` · `24RSD0010` |
| 11 | RSD2(S1)G3 | `24RSD0013` · `24RSD0014` · `24RSD0015` |
| 12 | RSD3(S1)G1 | `23RSD0001` · `23RSD0002` · `23RSD0003` |
| 13 | RSD3(S1)G2 | `23RSD0011` · `23RSD0012` · `23RSD0013` |
| 14 | RSD3(S1)G3 | `23RSD0016` · `23RSD0017` · `23RSD0018` |

Password for every ID in the table: **`Tarumt@2026`**.

## Two students that carry real request data (useful for receipts)

| Login | Cohort | What it holds |
|---|---|---|
| `24DFT0001` | DFT2(S1)G1 | One **pending** request — AMCS2093, original Mon 9 Nov 2026 (Week 8, a public holiday), requested Mon 16 Nov 2026 (Week 9). |
| `24RSD0009` | RSD2(S1)G2 | One **approved** request — BMSE2163, original Thu 24 Dec 2026 (Week 14), requested Fri 13 Nov 2026 (Week 8); plus a cancelled request that the history page hides by ruling. |

## Staff demo accounts (for reference)

Login page: `/login/staff`. Same password pattern as the demo dataset.

| Staff ID | Name | Role on the pages | Notes |
|---|---|---|---|
| `5425` | Surayaini Binti Basri | Lecturer (PL) | Holds PL rights: sees `/request-approval-ui`; owns the AMSE1003 / AMSE2003 sessions used in the arrangement receipts. |
| `5770` | Lim Jia Zheng | Lecturer (non-PL) | Owns the BMIT2013 / AMCS2093 sessions; the proposer behind the demo requests. |
| `4363` | Tan Sharon | Lecturer | Third account used by the Playwright auth setup. |

| Student ID | Name |
|---|---|
| `23RSD0011` | Victor Wembanyama |
| `23RSD0012` | Kyrie Irving |
| `23RSD0013` | Allen Iverson |

## Things to keep in mind

- The demo databases are **read-only for agents** unless a write is explicitly gated; opening a
  notification still flips its `read_at`, which is by design (the mark-read POST fires before
  navigation) and must be logged in `docs/demo-db-data-changes.md`.
- Logging in is safe: it writes nothing except the session table.
- The two demo databases carry the same real schedule (42 modules, 101 sessions), so any of the 42 IDs
  above exists in both `class_replacement_fresh` (:8000 / agent :8010) and `class_replacement_demo`
  (:8001). The testing database (`class_replacement_testing`, :8030) is disposable and reseeded.
