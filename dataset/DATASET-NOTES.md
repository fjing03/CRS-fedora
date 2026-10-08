# DATASET-NOTES — real-records import (2026-10-08)

Pins the corrections and decisions the importer and tests rely on.
Baselines: `KNOWLEDGE.md` (this folder, incl. §12 addendum),
`.sdd/changes/import-real-schedule-records/` (frozen artifacts).

## 1. True dataset counts (supersede the recheck-report headline)

| Dataset | Rows | Hours | Out-of-scope rows |
|---|---|---|---|
| Programme | **155** | 229 cohort-h | 587 |
| Venue | **101** | 147 room-h | 215 |
| Lecturer | **101** | 147 h | 62 |

The recheck-report's 184/121/101 describes the pre-14:14 regeneration — stale.
Current files are internally consistent: 155/155 programme↔venue cross-check,
155 cohort-instances from all three views, 42 distinct course codes.

## 2. Source double-bookings — exactly ONE survives

- **KEPT (user decision, printed in the PDF):** DFT2(S1)G1 Wednesday,
  AMIT2014 10:00–12:00 (B009) overlaps AMIT2034 11:00–13:00 (B005);
  effective overlap 11:00–12:00. Venue-distinct → no `time_slots`
  double-booking violation. The invariant test whitelists exactly this tuple.
- The Thu MPU-2212/MPU-2202 clash of §8 is GONE: MPU-2202 rows are
  out-of-scope now (lecturer "Jamie" has no staff ID).

## 3. Lab L/T partition (17 in-scope L/T blocks sit in labs)

**8 subject-allowed (Networking/IoT)** — spec §3 names B006; AMIT2034@B010 is
a room-level exception:

| Course | Room | Day | Time | Type |
|---|---|---|---|---|
| AMIT2033 | B006 | Wed | 14:00–16:00 | Lecture |
| AMIT2033 | B006 | Wed | 16:00–17:00 | Tutorial |
| AMIT2034 | **B010** | Tue | 13:30–15:30 | Lecture |
| AMIT2034 | **B010** | Tue | 15:30–16:30 | Tutorial |
| BMIT2154 | B006 | Mon | 09:00–11:00 | Lecture |
| BMIT2154 | B006 | Mon | 11:00–12:00 | Tutorial |
| BMIT3084 | B006 | Mon | 13:00–15:00 | Lecture |
| BMIT3084 | B006 | Fri | 10:00–11:00 | Tutorial |

**9 kept as printed (non-Networking L/T in Practical-only labs)** — no DB
rows; documentation + importer-warn only; `venues.allowed_session_types`
remains the guard for future manual bookings:

| Course | Room | Day | Time | Type |
|---|---|---|---|---|
| BMIS2003 | B006 | Tue | 11:00–13:00 | Lecture |
| AMCS1013 | B006 | Tue | 13:30–14:30 | Tutorial |
| BMIT2013 | B009 | Tue | 14:00–16:00 | Lecture |
| AMSE1003 | B009 | Wed | 09:00–10:00 | Tutorial |
| BMIT1173 | B009 | Thu | 12:00–13:00 | Tutorial |
| AMIS1003 | B010 | Fri | 15:30–16:30 | Tutorial |
| AMCS1043 | B011 | Wed | 10:00–11:00 | Tutorial |
| BMIT1723 | B011 | Wed | 13:30–14:30 | Lecture |
| BMCS3033 | B011 | Fri | 09:30–10:30 | Tutorial |

Importer warning list = 9 + 2 = **11 rows** (AMIT2034 counted per-row).

## 4. Canonical holidays (DB rows fit `day_of_week` 0–5)

Semester: Mon **2026-09-21** → Sun **2026-12-27**, 14 weeks, code `202605`.

| Week | Day | Label | Source date |
|---|---|---|---|
| 8 | 0 (Mon) | Deepavali Holiday (In Lieu) | 9 Nov 2026 |
| 14 | 3 (Thu) | Christmas Eve (Sabah State) | 24 Dec 2026 |
| 14 | 4 (Fri) | Christmas Day | 25 Dec 2026 |

Deepavali itself (Sun 8 Nov) is outside the Mon–Sat grid → docs-only.
Holiday-pair slots stay `available` in `time_slots`; occupancy math:
4116 − 153 (Mon 62 + Thu 54 + Fri 37 slot-rows) = **3963**.

## 5. Per-module observed session-type unions (→ `modules.allowed_session_types`)

Data-derived from the venue CSV (subset of L,T,P in that fixed order):

```
AMCS1013 L,T,P   AMCS1043 L,T,P   AMCS2093 L,T,P   AMIS1003 L,T
AMIS1012 L,T     AMIT2014 L,P     AMIT2033 L,T,P   AMIT2034 L,T,P
AMMS1623 T       AMMS3653 L,T     AMSE1003 L,T,P   AMSE2002 L,P
AMSE2003 L,T,P   AMSE2013 L,T,P   BBBE1033 L,T     BMCS1013 L,T,P
BMCS1053 P       BMCS1113 L,T,P   BMCS2053 L,T,P   BMCS2063 L,P
BMCS3033 L,T     BMIS2003 L,P     BMIS2113 L,T,P   BMIT1173 L,T,P
BMIT1723 L,P     BMIT2013 L,P     BMIT2043 L,T,P   BMIT2073 L,P
BMIT2154 L,T,P   BMIT2203 L,T,P   BMIT3084 L,T,P   BMIT3173 L,P
BMIT3273 L,P     BMMS1743 L,T     BMSE2163 L,T,P   BMSE3153 L,P
MPU-2212 T       MPU-2302 T       MPU-3103 T       MPU-3133 T
MPU-3232 L,T     MPU-3302 T
```

## 6. Module titles status

38 of 42 codes have real titles (22 from the .py single source + 16
user-directed via Downloads/mock-data.js and direct correction — see
`dataset/import/course-titles.php` header). 4 codes remain code-fallback
(importer reports them): AMSE2002, AMSE2003, AMSE2013, MPU-2302.
