# Explore brief — occurrence-week-pin-truthfulness

(this file = handoff Sections 1–4 merged + the rejected approaches; date 2026-10-19)

## The incident (verified by the investigating session; facts LOCKED — do not re-run)
- My-Timetable's "Replace Now" hands off `window.currentWeek + 1` (the VIEWED
  week, 1-based) instead of the conflicted occurrence's OWN week:
  `my-timetable.blade.php:99`.
- The arrangement honours any `?week=` and `submitBlocks` still carries the
  silent fallback `$occurrenceWeek ?? $week`.
- Consequence (the live incident): request **#11** recorded
  `week_number = 5` (the make-up week) instead of **14** (the holiday
  occurrence it replaced); its approval stamped a bogus
  `class_exceptions (67, 5, official_event)` and freed the LEGIT W5 class
  block(s) 10623–26.

## Locked facts (pgAdmin, 2026-10-19)
- Semester `202605`: start 2026-09-21 (Mon), 14 weeks → W5 Thu = 22-Oct-2026;
  W14 Thu = 24-Dec-2026 = holiday "Christmas Eve" (holidays W14 day 3).
- Sessions: **#67** AmSE2003 (L, 2h) THU 09:00–11:00 venue 7 (B100);
  **#68** THU 11:00–12:00 T (1h). NO W14 slot rows exist for 67/68 (holiday import).
- **#11**: session 67, week_number 5 (WRONG, true = 14), approved
  (self-approve, user 1, 08:31:39), junction 10693–96 (span 4, makeup W5 THU
  14:00–16:00 B100 = a legitimate receipt).
- **#12**: session 68, week_number 14 (CORRECT), pending, junction 10623–24
  (span 2, makeup W5 THU 09:00–10:00).
- Audits: #11 = 4×submitted + 4×approved + 1 summary (id 37, anchor 10693);
  #12 = 2×submitted.
- Exceptions: `(67, 5, official_event)` @08:31:39 = **BOGUS** (#11's approve);
  `(94, 14, official_event)` @06:07:57 = CORRECT-keying positive control
  (#10: original W14, junction W5 B002).
- W5 slot states: 10623–26 = #67's legit block (freed v2 by #11's release);
  10623–24 re-reserved v3 by #12's pending; 10627–28 = #68's legit block
  occupied v1 (untouched).

## TO-VERIFY answers (grepped 2026-10-19, this repo; amended per review round 2 — the reviewer's code-truthful reading)
(a) **The Replace-Now gate + handoff**: openModal's
    gate reads `days[event.di].holiday || event.status === 'conflict'` where
    `days = weekData[currentWeek].days` and `currentWeek` is the LOCAL variable —
    correct against the DISPLAYED week, because every navigator method routes
    through `_buildTimetable()` → `window.buildTimetable()` → blade:172
    (`currentWeek = weekNav.currentWeek`). The HANDOFF is the divergence:
    blade:99 reads `window.currentWeek + 1`, re-synced only by the page's
    prevWeek/nextWeek/selectWeek wrappers (blade:198/205/212) and STALE after
    (i) `weekNav.load()`'s localStorage restore (blade:216–217 syncs only the
    local), (ii) the Today button (jumpToToday, ui-common.js:333–341), and
    (iii) the [ / ] shortcuts, which call the CLASS methods (ui-common.js:476–487)
    and bypass the wrappers. Net: gate and handoff can reference different
    weeks in the same click; and neither checks whether THIS occurrence/week
    carries a live conflict fact. The fix (F4):
    occurrence-fact-driven — the button fires ONLY when the payload row
    carries a live conflict fact, and THAT fact's week is the handoff's
    week; `window.currentWeek` is no longer consumed anywhere on this page
    (its sole reader in the repo is blade:99).
(b) **Venue original keys**: `VenueTimetable` sets `originalDate` for its
    remarks, but its reserved-pass events do NOT carry
    `originalVenue/Name/StartTime/EndTime` — the trait's timeline branch
    HAS them (ResolvesTimetableTimeline:307–308) — **F5 must add them to
    the venue pass** (or the popup falls back to the landing clock).
    Also `ReplacementHistory.php:101` carries originalVenue (parity OK).

## Rejected approaches
1. Patch the four renderers to derive dates from the holidays table —
   rejected: four renderers fork; `week_number` is the SSOT and render code
   keys it correctly.
2. Route A "keep #11, edit week in place" — rejected by the user (Q4 → C).
3. Route B "snapshot-restore" — rejected (snapshot recency unknown).
