# Venue Restrictions — booking & assignment rules

Reference list of venue-specific restrictions. **Doc-only for now** — these rules are
NOT yet enforced anywhere; they are the spec for a future enforcement change
(booking validation / arrangement filtering, Slice B scope). Any enforcement work
needs its own SDD proposal first.

---

## 1. B006 — Cisco Lab

**Networking subjects only — all session types (L/T/P); IoT subjects — practical (P) only.**

- B006 is the single `cisco_lab` venue (capacity 32, `allowed_session_types = 'P'` in `venues`).
- Networking subjects currently in the DB (42 modules):
  | Code | Name | Allowed types per this rule |
  |---|---|---|
  | AMIT2033 | Networking Essentials | L, T, P |
  | AMIT2034 | Fundamentals of Computer Networks | L, T, P |
  | BMIT2154 | Switching and Routing Technologies | L, T, P |
  | BMIT3084 | Enterprise Networking | L, T, P |
- IoT subjects: **none exist in the DB yet** — when added, P only.
- ⚠️ Open question: the authoritative Networking/IoT subject list needs confirmation
  (name-based match above may miss edge cases, e.g. Internet Security). If the
  curriculum defines a module category, prefer that over name matching.

## 2. B005 — Lab

**Diploma students disallowed (programmes `D**` — cohort codes starting with `D`).**

- B005 is a `lab` venue (capacity 28, `allowed_session_types = 'P'`).
- Diploma programmes in the DB: `DFT` (Diploma in IT), `DSF` (Diploma in Software
  Engineering) — their cohort codes (`cohortCode()`: programme + year + semester +
  group) all start with `D`.
- Bachelor programmes (`RSD`, `RAF`, `RBU` — codes starting with `R`) are unaffected.

## 3. Practical classes must use Labs

**Any `P` (Practical) session may only be scheduled in a venue with
`room_type IN ('lab', 'cisco_lab')`.**

- Current lab-type venues: B005 (lab), B006 (cisco_lab) — of 23 venues total
  (tutorial 16, lab 1, lecture_hall 2, cisco_lab 1).
- This parallels the existing `venues.allowed_session_types` column (both labs
  currently declare `'P'`); this rule additionally forbids lecture halls and
  tutorial rooms from hosting practicals regardless of what that column says.

---

## Enforcement notes (for the future change)

| Rule | Natural check |
|---|---|
| B006 networking/IoT | module list/registry lookup at booking time (venue → allowed module categories) |
| B005 no-Diploma | cohort's `programme_code` starts with `D` → reject B005 |
| Practical → Labs | `session_type === 'P'` requires `room_type IN ('lab', 'cisco_lab')` |

Related schema facts: `venues.room_type` (`lab`/`cisco_lab`/`lecture_hall`/`tutorial`),
`venues.allowed_session_types`, `programmes.programme_code` (D*/R*), the
`time_slots_no_double_book_idx` partial unique index, and the OCC `version` column
from the booking write path (Slice B).
