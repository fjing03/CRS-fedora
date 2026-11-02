# PI prompt — Venue Timetable public-holiday row overflow bug

**What this is:** paste-ready prompt for the PI chat session (that AI cannot see images, so the bug is
described in words). It presents the diagnosis, asks agree/disagree, and hard-blocks any fix until the
user explicitly approves.

**Page:** `http://127.0.0.1:8000/venue-timetable-ui?venue=B010` (ONLY this page may be fixed).
**Status:** awaiting PI verdict → then user approval → then implement.

---

Copy everything below this line:

````markdown
# Venue Timetable — grid overflow bug on public-holiday rows (diagnosis review, DO NOT FIX YET)

## Context
Repo: TARUMT Class Replacement System (Laravel 12 + Livewire + Blade, branch `fedora-backend`).
Page in question (ONLY this page): `http://127.0.0.1:8000/venue-timetable-ui?venue=B010`
Route `/venue-timetable-ui` → `App\Livewire\VenueTimetable` → view `resources/views/livewire/venue-timetable.blade.php`.
The grid is built by the SHARED helper `buildTimetableGrid()` in `public/js/ui-common.js` (line 640),
which is also used by My Timetable, Cohort Timetables, Replacement Arrangement, etc.

## The visual bug (described in words — you cannot see the screenshot)
On a week containing public-holiday days that also have classes (e.g. Week 14 of 14, Mon 21–Sun 27 Dec 2026,
where Thu 24 Dec and Fri 25 Dec 2026 are PUBLIC HOLIDAY), in the timetable table:

1. The header row and ALL normal rows (Mon/Tue/Wed/Sat) end flush at the last time column
   ("18:00–19:00"), so the area to the right of the grid is blank white.
2. The two PUBLIC HOLIDAY rows (Thu 24, Fri 25) do NOT end there: each extends **2 extra cells to the
   right**, painting pink (the public-holiday `cell-ph` colour) into what should be blank space —
   i.e. the holiday rows overflow past the table header / other rows by 2 columns.
3. Both holiday rows carry exactly 2 class events (Thu: AMSE2002(P) 2:30–3:30 PM + AMSE2013(P)
   3:30–4:30 PM; Fri: BMCS3033(T) 9:30–10:30 AM + AMCS2093(P) 11:00 AM–12:00 PM), each 1 hour.
   Overflow width = (number of events on that holiday row) × (span − 1) columns, where span = 2
   half-hour slots for a 1-hour class. Normal (non-holiday) rows with events — e.g. Wed's 4 classes —
   do NOT overflow at all.
4. Side effect: because the holiday rows are shifted right by those phantom cells, the event blocks on
   Thu/Fri also sit 1–2 columns to the right of their true time columns (hard to notice because every
   cell on those rows is the same pink).

## My error finding (root cause hypothesis)
Column accounting in a `<table>`:
- Header: each time-pair `<th>` has `colSpan = 2`, so header columns = `hours.length` (hours are
  half-hour slots).
- An event occupies one HEAD `<td>` with `td.colSpan = info.span` (venue-timetable.blade.php line 669),
  and its remaining span−1 CONTINUATION cells must be removed from layout via
  `td.style.display = 'none'` (line 671) so the row still totals `hours.length`.

But the venue page's custom `cellRender` (venue-timetable.blade.php lines 607–671) checks branches in
this order:
1. `if (day.sunday)` → renders `cell-sun`
2. `else if (day.holiday && !offMine && !(info && info.event))` → renders `cell-ph`  ← THE CULPRIT
3. `else if (info && info.event)` → event block + `td.colSpan = info.span`
4. `else if (info && info.occupied)` → `td.style.display = 'none'` (continuation) ← never reached on holidays

For a CONTINUATION cell on a public-holiday row, `info.event` is null (so branch 2's
`!(info && info.event)` is true) and `day.holiday` is true → branch 2 catches it and keeps the cell
VISIBLE as a pink PH cell instead of falling through to branch 4 and hiding it.
Result: every event on a holiday row leaks `span − 1` extra visible columns into the row → the table
widens to the widest row → header and normal rows stop early (blank white), holiday rows fill the
phantom columns with pink = the observed 2-cell overflow (2 events × (2−1) = 2 columns on each of
Thu/Fri). On non-holiday rows branch 4 works, which is why only holiday rows overflow.

Why the obvious alternative (move the `info.occupied` check to the very top) was REJECTED:
on Sunday rows branch 1 intentionally wins for ALL cells — the event branch never runs, so no
`colSpan` is ever set there. If continuations were hidden first on Sundays, event-bearing Sunday rows
would SHRINK by span−1 columns instead — trading one misalignment for another.

## Proposed minimal fix (confined to the venue page ONLY)
One condition added in `resources/views/livewire/venue-timetable.blade.php`, line 624:

```js
} else if (day.holiday && !offMine && !(info && info.event) && !(info && info.occupied)) {
```

Continuation cells then fall through to branch 4 → `display: none` → holiday rows total
`hours.length` again → no overflow, and Thu/Fri event blocks realign with their true time columns.
Empty holiday slots still render `cell-ph` (the legend promise "remaining holiday slots show as empty
'PH' cells" is preserved). No shared file (`ui-common.js`, `theme.css`, partials) is touched.

## What I want from you
1. Do you AGREE with this root-cause analysis? If not, what do you think actually causes it?
2. Do you AGREE the one-line guard above is the right minimal fix for this page, with no cleaner/safer
   alternative I've missed?
3. Blast radius: confirm the change is local to `livewire/venue-timetable.blade.php`'s `cellRender`
   and CANNOT affect unrelated pages — My Timetable, Cohort Timetables, Replacement Arrangement and
   Request History use either the default `buildTimetableGrid` cell path (which checks
   `info.event → info.occupied → empty` in the correct order) or a cellRender that ignores `info`
   entirely (no colspan there). `ui-design-templates/*` copies are non-routed design references.

## HARD CONSTRAINT — do NOT fix yet
Answer questions 1–3 ONLY (agree / disagree + reasoning). Do NOT edit any file, do NOT propose patch
files, do NOT "just apply it". The change will only be implemented AFTER I explicitly approve — even
if you fully agree, wait for my go-ahead.
````
