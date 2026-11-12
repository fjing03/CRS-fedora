# Proposal — student-modal-staff-parity

**Date:** 2026-11-11 · **Page:** `/student-class-replacements` (student Class Replacements)
**Kind:** display-only · one blade + one read-only payload addition · no DB, no routes, no new dependency
**Baseline:** `explore-brief.md` (same directory) — the measured comparison and the frozen decisions

## Why

The student pop-up and the staff pop-up do the same job — "what is this class, and where did the
replacement land" — but they are built differently enough that a viva walk-through reads as two
products:

| | Staff | Student (today) |
|---|---|---|
| Title / subtitle | `Replacement Details` / `CODE — Name (Type)` | `BMIT2013 — Class Replacement` / the name only |
| Conflict | one strip above the tabs, printed in full | its own tab, in the long form |
| Tabs | Requested → Original → Request Status (Short) | Course → Original → Conflict Reason → Requested → Request Status |
| IDs | `Request ID` / `Class ID` rows | none |
| Footer | Close LEFT (outline), action RIGHT (primary blue) | Go to My Timetable LEFT (outline), Close RIGHT |

Two of those are outright defects rather than taste: the footer **flips sides** depending on whether
the row has a request (measured), and the student never sees the request number while every other
pop-up prints one.

## User decision record (2026-11-11, all answered in conversation)

| # | Decision |
|---|---|
| 1 | Title `Replacement Details`; subtitle `CODE — Name (Type)` — the staff format |
| 2 | The `Course` tab is **deleted**; Lecturer, Cohorts and Students move into a new **`Class Info`** tab |
| 3 | A facts strip above the tabs carrying **only the Conflict badge, in full** (`Public Holiday — Deepavali Holiday (In Lieu)`; `Class Cancelled` for a cancellation) |
| 4 | Tabs open on **Requested Slot Schedule** when the row has one, otherwise on **Class Info** |
| 5 | **Go to My Timetable** = a static button on the RIGHT, primary blue, shown only for **approved / completed** |
| 6 | Checklist item **B9** joins this lane: the status tab label carries the short status — `Request Status (Approved)` |
| 7 | Checklist items **B5/B6** join as far as this pop-up is concerned: ID rows read `Request ID` and `Class ID` (the staff names) |
| 8 | The Status tab keeps its present content; the "why was it rejected" line is parked (needs the payload work described in Non-goals) |

## In scope

1. **Title + subtitle** in the staff shape.
2. **The `Conflict Reason` tab is removed** and its fact becomes the strip.
3. **`Class Info` tab** holding Lecturer · Cohorts · Students (the last two are new data — see below).
4. **The slot tabs adopt the staff row shape**: `Class ID`/`Request ID` (strong) · Week · `Day & Date` (countdown in brackets) · `Time & Duration` · Venue.
5. **Tab order and opening tab** per decision 4.
6. **Status tab label** carries the short status (B9).
7. **Footer**: static Close (outline, left) + Go to My Timetable (`btn-action` primary + the calendar glyph, right), display-gated on approved/completed.
8. **Three new payload keys** on the student row — `cohorts`, `totalStudents`, `statusShort` — read-only derivations in `occurrenceRow()` (the cohorts are already eager-loaded; nothing new is queried per row).
9. Changelog entry + receipts (spec/feature) as the tasks stage will spell out.

## Out of scope (non-goals — do not drift into them)

- **No cancellation reason for students.** Their earlier ruling stands: a cancelled row's strip reads
  `Class Cancelled` and carries no leave reason. Sending it is a separate decision.
- **No rejection reason / remark / status sentence in the Status tab.** Same payload family; parked.
- **Checklist B8** (facts as a strip on two modals vs labelled rows on two others) — NOT settled here.
- **Checklist B10** (a holiday stated more than once) — NOT settled here.
- **Checklist B2 / B11 / B13** — different pop-ups entirely (Request Details; student My Timetable;
  the Approve/Reject modals).
- No staff-side file changes, no `theme.css`, no `ui-common.js`, no DB, no routes, no new dependency.
- No push (the user pushes).

## Risks

| Risk | Mitigation |
|---|---|
| The student payload grows and someone later assumes it carries the leave reason | The changelog and the payload's own comment state that it does not, and why |
| The strip and the Class Info tab both showing "facts" could be read as the B8 problem | Logged in the changelog as a known residue with a pointer to B8 |
| The `Class Info` tab is a new name on one page only | It is a tab label, not a shared component; the staff pop-up has no equivalent tab |
| Removing a tab could hide information a student relied on | Nothing is lost: the conflict moves to the strip, and the course facts move into the subtitle + Class Info tab (measured against today's 5 tabs) |

## Acceptance (what "done" means)

Opening the BMIT2013 row as student `24RSD0012` shows: title `Replacement Details`; subtitle
`BMIT2013 — Web-Based Integrated Systems (Lecture)`; a strip reading
`Conflict  Public Holiday — Deepavali Holiday (In Lieu)`; the four tabs in the agreed order, opening on
*Requested Slot Schedule*; `Request ID #27` and `Class ID #24` rows; `Class Info` listing the lecturer,
the cohort `RSD2 (S1) G2` and its student count; a footer with Close left and a blue *Go to My
Timetable* right; and **no** `Conflict Reason` tab anywhere. A pending row shows no *Go to My
Timetable*, and a row with no request opens on *Class Info* with no *Requested* tab.
