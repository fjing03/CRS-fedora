## 2026-11-17 — the phone card says when the replacement is asked for

A card on a phone now reads like the drawing: the code and its status on top, then an
**ORIGINAL** band (week, date, countdown, time, room) and — when a proposal exists — a
**REQUESTED** band with the same four facts for the new slot. A row with no proposal prints
*Not requested* instead of leaving the space blank. The conflict badge and the cohorts share
the foot, and from a tablet up the two bands sit side by side.

Before this, a phone card showed one flat line of prose and **no requested slot at all**, so a
pending card never said when the make-up class was being asked for. The desktop table is
unchanged.


### Postscript — own-records page + My summary cards (2026-10-08)

The page is now a true **own-records dashboard**: the table lists only
the logged-in lecturer's conflicted classes (owner scoping via the new
`lecturer` field on §2.10 rows; runtime-cancelled rows get theirs from
`ClassCancellation.rowFromEvent`, and `applyLedger` backfills lecturer
for ledgers persisted before this change). Scoping is a function
re-evaluated per rebuild so ledger-appended rows always appear.
Summary bar reduced to three mine-scoped cards — **My Conflicted
Classes (4) · My Hours to Cover (8) · My Courses (2)** — replacing the
old list-wide five (Venues Affected and Students Affected removed as
not user-relevant). Verified: 4 of the 14 seeded conflicts belong to
En. Lim Jia Zheng (ids 1, 2, 7, 13); others' rows no longer render.
Note: §2.10 id 8 (BMIT2073(T)) has no (T) slot in the timetable (only
L/P exist) — its lecturer was attributed by course code.
