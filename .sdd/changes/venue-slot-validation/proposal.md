# Proposal: venue slot validation — hover floor vs. class-duration check

## Intent

On `venue-timetable-ui` the "Select a Session" pop-up decided **before the user chose a class**
whether the clicked cell could host it, and printed *"None of your classes can run in B002 at this
cell — 13:00 plus the class length runs into an existing event below. NOT ENOUGH FREE TIME
BELOW."* The user had clicked a free 13:00 cell whose next class starts at 14:00 and read the
message as "this slot is broken". It is not: the shortest class they can move here fits in exactly
one hour, but the subject the dropdown happened to be showing is a 2-hour lecture.

The page cannot know the class length at hover time, so it must stop pretending to. The brief
(`prompts/completed prompt (Archieve)/Venue Timetable Slot Validation.md`, archived 2026-11-05)
splits the two questions into two stages:
**hover = "is there at least one hour?"**, **after the class is picked = "does THIS class fit?"**.

## Scope

### In Scope
- `resources/views/livewire/venue-timetable.blade.php`
  - Hover floor becomes a fixed **1 hour** (`MIN_BOOK_SLOTS = 2`); `cell-no-fit` / `cell-no-room`
    / the red-cell toast all read it. The `minClassSlots` client constant is deleted.
  - `refreshSessions()` no longer filters or warns: every session of the selected subject is
    listed, each row carries `data-slots` (and `data-fit`), and the first fitting row is
    pre-selected when one exists.
  - `confirmIntentSession()` (Arrange) validates the picked row's real duration — day-end first,
    then overlap — and on failure opens the new warning modal instead of navigating.
  - `intentContext` carries `di` / `hi`; the overlap helper is lifted out of the render closure
    so both stages use one definition.
- `resources/views/partials/ui-intent-session-modal.blade.php`: new `#slotWarningOverlay`
  warning modal (icon, head, body, guidance, one close action).
- `app/Livewire/VenueTimetable.php`: delete `minBookableSlots()` + `BOOK_SPAN_FALLBACK` and the
  `minClassSlots` payload key — superseded by the fixed floor, so it would be dead code.
  `bookableCandidates()` stays: it is still the one pool definition behind the dropdown.
- Tests: `tests/venue-book.spec.ts` (hover floor, plus the six verification cases from the
  brief), `tests/Feature/VenueTimetableTest.php` (drop the two `minClassSlots` cases).

### Out of Scope
- The Replacement Arrangement page and every other page; the handoff URL contract is frozen.
- The room-type notice (`fitting.length === 0`), the advisory block, the tooltip design, the
  `data-tip` system, the grid geometry, lead-time `cell-too-soon`, occupied/pending/reserved/PH
  cells.
- No server-side booking rule change: `SubmitReplacementRequest` / slot validation untouched.

## Capabilities
Modified: `venue-timetable-booking` (hover classification + the arrange gate). No new capability.

## Approach
One constant change (`MIN_SLOTS` → fixed 2), one filter removed (`refreshSessions`), one guard
added (`confirmIntentSession`), one modal added. The duration arithmetic is already on the wire
(`durationSlots`), so no new endpoint and no new payload.

## Affected Areas
| Path | Change |
| --- | --- |
| `resources/views/livewire/venue-timetable.blade.php` | hover floor, refreshSessions, confirmIntentSession, warning wiring |
| `resources/views/partials/ui-intent-session-modal.blade.php` | new warning modal markup |
| `app/Livewire/VenueTimetable.php` | remove dead `minClassSlots` plumbing |
| `tests/venue-book.spec.ts` | floor assertions + 6 verification cases |
| `tests/Feature/VenueTimetableTest.php` | remove 2 superseded cases |
| `page-changelogs/venue-timetable-ui-changelog.md` | entry |
