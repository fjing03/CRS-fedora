# Venue Timetable: keep room context on Earliest bookable jumps

Status: applied and verified locally. The independent reviewer returned HTTP 429; the owner explicitly waived review for this batch.

## Scope

- Below 1280px, keep the existing tools row (count and Week summary) next to the week/day controls in Venue's sticky chrome. Add the current room code alongside the count using the same served room as the grid, not a second selection state. Do not move the full room picker or add another toolbar row. Shared partial options are opt-in; other timetable pages and the full Venue desktop grid stay unchanged.
- Keep the room-aware week/day/slot lookup, selected day, pulse, and focus. Only scroll when the target is not fully in view; when scrolling, land its whole cell below the measured docked chrome where viewport height permits. Honor reduced motion. No change to slot eligibility, picker navigation, booking actions, Today, layout breakpoints, or print output.

## Receipts and tasks

- Baseline: `/tmp/crs-venue-critique/task-flow.jsonl`; B002 jumped to Tue 24 Nov, 08:00, but room label disappeared at 320/390/1024 and Week summary was partly covered at 320/1024.
- On testing, use real room/slot data and touch at 320/390/820/1024 including short-height/landscape, sample both themes, and check the desktop boundary. Assert exact week/day/slot, visible room code, usable Week summary, and that the focused slot is not behind the sticky chrome. Check the room label after selecting another served room, and preserve other timetable chrome. If needed, use a labelled browser-only late-slot case for the scroll path. Do not submit anything or write business data.
- Inspect renders, run scoped browser/static checks, update the Venue changelog, and commit only this batch.

## Verification

B002 still jumps to the earliest served day and focuses its first available slot. The room and Week summary stay visible at 320×568, 390×844, 667×375, 820×1180 and 1024×768. At 820 the already-visible target did not move the page. At 1024 a repeat jump from lower in the grid brought the target and day heading back below the sticky bar. B005 updated the room text after a real room switch. The 1280px full grid and Cohort's tools placement were unchanged. All 20 scoped Playwright checks passed (including auth, session picker, existing jump/summary and print receipts); Pint and PHPStan passed. Captures and logs: `/tmp/crs-venue-jump/`. No arrangement or booking submitted.
