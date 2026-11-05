# Design — venue-slot-validation

## D1 · Two stages, one arithmetic, no assumption

| Stage | Question | Source of truth | Surface |
| --- | --- | --- | --- |
| Hover / cell paint | "is there at least **one hour** of continuous room here?" | fixed floor `MIN_BOOK_SLOTS = 2` half-hour slots | `cell-no-fit` (red, day end) / `cell-no-room` (green, "Not Enough Slot") |
| After the class is picked | "does **this** class fit from this start?" | the picked row's real `durationSlots` | `#slotWarningOverlay` warning modal, no navigation |

The hover stage must never consult a class, because until the picker is used there is no class:
`MIN_SLOTS` (the previous `minClassSlots`-derived constant) is replaced by the fixed floor, and
the server-side `minClassSlots` plumbing is deleted with it.

## D2 · `refreshSessions()` stops judging

```
for each session of the selected subject (fitting[mi].order)
  row per flagged occurrence:
     data-sid, data-cohort, data-week, data-label, data-tip   (unchanged)
     data-slots = e.durationSlots || 1                        (new)
     data-fit   = 1 when hi + slots <= hours.length && !spanBlocked(di, hi, slots)  (new)
  pre-select: the first row with data-fit="1", else the first row
  arrangeBtn.disabled = (no rows)
```
No `allFit` / `spanFails` / `dayEndFails` counters, no `#intentNotice` for durations.
`#intentNotice` remains for the **room-type** case only (`fitting.length === 0`).

## D3 · The gate lives on the Arrange button

`confirmIntentSession()` (already the single entry to `bookVenueWithSession`) gains, before the
navigation:

```
pick = the .session-dd-item.active row
slots = Number(pick.dataset.slots) || 1
if (intentContext.hi + slots > hours.length)      → showSlotWarning('dayEnd', …); return
if (spanBlocked(intentContext.di, intentContext.hi, slots)) → showSlotWarning('span', …); return
…existing bookVenueWithSession(...) call, byte-unchanged…
```

`intentContext` gains `di`, `hi`, `venue` (it already holds `dateStr`, `timeStr`).

One definition of overlap (lifted out of the render closure so both stages share it):

```
function spanBlocked(di, hi, spanSlots)      // any event overlapping [hi, hi+spanSlots)
function nextEventStartAfter(di, hi)         // index of the next event start below, or Infinity
```
`roomBeforeNextEvent()` becomes a thin wrapper over `nextEventStartAfter()` so the paint loop and
the warning cannot disagree.

## D4 · Warning modal

`resources/views/partials/ui-intent-session-modal.blade.php`, right after the intent modal:

- `.modal-overlay #slotWarningOverlay`, `.modal` max-width 560 px, `.modal-header` with
  `.modal-title` ("Slot Too Short") and the shared `.modal-close`, `.modal-body`:
  `.modal-icon.modal-icon--warning` (yellow face, existing class) + `.confirm-warning
  #slotWarningHead` + a `.intent-notice` block holding `#slotWarningBody` (muted),
  `#slotWarningGuide` (bold CAPS amber), `#slotWarningTail` (muted) — all existing tokens, no
  new colour.
- ONE closing action in the footer: `.btn-confirm #slotWarningPickBtn` — "Pick another slot" —
  plus the header ×; both call `closeSlotWarning()`, which also accepts an overlay click.
- Borders/radius from the shell: no new CSS except making `.intent-notice` blocks inside
  `#slotWarningOverlay` behave the same as inside the intent modal (they already do — the rules
  are class-scoped, not modal-scoped).

### Copy

| kind | head | body | guide | tail |
| --- | --- | --- | --- | --- |
| `span` | `None of your classes can run in <VENUE> at this cell` | `<start> plus the selected class duration (<duration>) runs into an existing event below` + ` — the class starting at <next>` when known | `NOT ENOUGH FREE TIME BELOW` | `— pick a Free Cell.` |
| `dayEnd` | `This class does not fit before the day end (<dayEnd>)` | `<start> plus the selected class duration (<duration>) runs past <dayEnd>.` | `PICK AN EARLIER CELL` | `— the grid ends at <dayEnd>.` |

`<duration>` = `1 hour` / `1.5 hours` / `2 hours` (half-hour aware, singular/plural).
`<start>`, `<next>`, `<dayEnd>` are `hours[]` labels (`13:00`), so the message speaks the grid's
own language. No browser alert; no navigation on failure.

## D5 · Order of closing

`showSlotWarning()` closes the intent modal first (`closeIntentSessionModal()` — which also
restores focus to the cell) and then shows the warning; closing the warning leaves the user on
the grid. This is the brief's "clear way to close the warning and return to the timetable".

## D6 · Verification plan (Playwright, brief §6)

Fixture: staff `5425`, venue `B002`, Week 9 (index 8), Monday — 12:00/12:30/13:00 free, 14:00
`MPU-3232`. Pool: `AMSE2003 (L)` 2 h, `AMSE2003 (T)` 1 h, `AMSE1003 (L)` 2 h.

| # | Case | Expected |
| --- | --- | --- |
| 1 | hover 13:30 before the 14:00 class | `cell-no-room`, "Not Enough Slot" |
| 2 | 2 h class + 12:00 | navigates to `/replacement-arrangement?…&time=12%3A00…` |
| 3 | 2 h class + 12:30 | warning modal, no navigation |
| 4 | 2 h class + 13:00 | warning modal, no navigation |
| 5 | 1 h class + 13:00 | navigates |
| 6 | open the pop-up, switch subject | no notice / no warning before the Arrange click |

Plus: the red-cell toast now says a fixed 60 minutes; `cell-no-fit` / `cell-no-room` follow the
1-hour floor in every week (audited cell by cell).
