# UI/UX Update — Venue Timetable: bookable cells

**Date:** 2026-11-05 · **For:** PI · **Status:** brief only — no code written yet
(all line numbers are from today's `HEAD`; page: `/venue-timetable-ui`)

---

## 1. The three asks

1. **The "gap" sentence is misleading.** Today the notice under a booking attempt says
   *"Please book with at least a 2-hour gap"*. That number is not a rule — it is copied from the
   length of the shortest class in the dropdown (see §2). **Drop the number and keep the
   warning.**
2. **The late cells are too strict.** The 5:00 / 5:30 / 6:00 PM cells are marked
   *Too Late to Book* because the page assumes every booking is 2 hours long. **Keep the red
   warning, but judge it by the real class lengths** this venue can host.
3. **Cells that cannot hold a class should say so before the click.** Any free cell with too
   little room before the next existing class gets a **blocked cursor** and the hover words
   **"Not Enough Slot"** instead of "Arrange ?", and a click shows the reason in the usual
   bottom-left message. **No red background** — the normal green look stays.

---

## 2. What the code does today

### The grid

- `app/Livewire/VenueTimetable.php:419` — the venue page's day grid is **08:00 → 18:30**
  (21 half-hour slots, last start 18:00). Do not change it.
- `resources/views/livewire/venue-timetable.blade.php:401` — `const BOOK_SPAN = 4;`
  (4 × 30 min = **2 hours**, a fixed assumption used by the cell classes and the click guard).

### The "Too Late to Book" state

```js
/* venue-timetable.blade.php:863 — inside the available-cell branch */
if (hi + BOOK_SPAN > hours.length) div.classList.add('cell-no-fit');
```

```css
/* :60 and :70–72 — the red-cell law (user, 2026-10-25) */
.cell-available.cell-no-fit { --hover-label: 'Too Late\A to Book'; white-space: pre-line; cursor: not-allowed; }
.cell-available.cell-no-fit { background: var(--color-error-container); }
.cell-available.cell-no-fit::after { background: var(--color-error); color: var(--color-on-error); }
```

```js
/* :405–408 — clicking one never opens the arrange pop-up */
if (hi + BOOK_SPAN > hours.length) {
    toast.show('A booking needs ' + (BOOK_SPAN * 30) + ' minutes — not enough time left in the day.');
    return;
}
```

So today: 3 fixed cells at the end of **every** day are red, whatever the class lengths are.
With 1-hour classes, 5:30 PM could in fact host a booking and is still refused.

### The "gap" sentence

```js
/* :577–580 — minSpan = the SHORTEST session of the subject currently in the dropdown */
minSpan = Math.min(minSpan, (e.durationSlots || 1));
/* :625–630 — that shortest length is printed as if it were a spacing rule */
const gapHours = Math.round((minSpan * 30) / 60 * 10) / 10;
'Please book with at least a ' + (gapHours === 1 ? '1-hour' : gapHours + '-hour') + ' gap',
```

There is **no gap rule anywhere** in the booking engine (`app/Services/OCCValidator.php`,
`app/Actions/SubmitReplacementRequest.php`): blocks are rejected only when they overlap. The
sentence simply states the length of the shortest class that did not fit, which is why it reads
"2-hour" for 2-hour classes.

### Where the class lengths come from

- `VenueTimetable::intentSessionOptions()` (`app/Livewire/VenueTimetable.php:67`) builds the
  dropdown **on click** (`$wire` call). It is **week-agnostic** — every conflicted session the
  venue's type admits.
- The grid renders **before** that call, so it has no class lengths today. That is why the
  red cells had to use the fixed `BOOK_SPAN`.

---

## 3. Change 1 — the gap sentence

In `renderIntentSessionModal()` → `refreshSessions()` (`venue-timetable.blade.php:574–640`):

1. Delete the `gapHours` line and the sentence it feeds.
2. Replace that guidance slot in `noticeHtml(...)` with a fixed, truthful line:
   - head (unchanged): `None of your classes can run in {venue} at this cell`
   - muted (unchanged): `{hour} plus the class length runs into an existing event below.`
   - guide (new): `NOT ENOUGH FREE TIME BELOW` (the `.notice-guide` slot renders CAPS, token
     colour — keep that shape) + tail: `— pick a Free Cell.`
3. `minSpan` exists only for that number — remove it too once nothing reads it.

The badge wording `Too Late` / `to Book` is **not** part of this change.

---

## 4. Change 2 — late cells judged by real class lengths

### 4.1 Teach the grid the shortest bookable class

- Extract the candidate computation from `intentSessionOptions()` into a private helper,
  e.g. `minBookableSlots(): int`, returning the **smallest `durationSlots`** among the sessions
  that call would offer for this venue (week-agnostic, same `$eligible` filter).
- Put it in the `render()` payload (`app/Livewire/VenueTimetable.php:701`) as
  `minClassSlots`, and read it in the blade next to `eventsData` (`~line 231`).
- Cost: one extra query set per page render. Keep it inside the same helper — do not duplicate
  the venue/room-type rules.
- **Empty list** (nothing can be booked here): fall back to `BOOK_SPAN` so the page behaves
  exactly as it does today.

### 4.2 Use it

```js
const minSlots = window.minClassSlots || BOOK_SPAN;
if (hi + minSlots > hours.length) div.classList.add('cell-no-fit');
```

and the click guard at `:405` becomes:

```js
if (hi + minSlots > hours.length) {
    toast.show('A booking needs ' + (minSlots * 30) + ' minutes — not enough time left in the day.');
    return;
}
```

**Consequences to accept** (state them in the changelog):

| Shortest class | 5:00 PM | 5:30 PM | 6:00 PM |
| --- | --- | --- | --- |
| 1 hour | bookable | **bookable** | still red (only 30 min left) |
| 1.5 hours | bookable | red | red |
| 2 hours | red | red | red (today's look) |

So "every late cell is bookable" is not literally true — a cell is bookable only if something
can actually fit. That is the user's chosen rule (§10, item 2).

---

## 5. Change 3 — cells with no room before the next class

### 5.1 New state, no new colour

Add a second class next to `.cell-no-fit` (name it however you like, e.g. `cell-no-room`),
**without** touching the background:

```css
.cell-available.cell-no-room {
    --hover-label: 'Not Enough Slot';
    cursor: not-allowed;
}
```

No red background, no red pill, no change to `.cell-no-fit` (user ruling 2026-11-05: the cursor
and the words only).

### 5.2 When a cell gets it

For each available cell, look at the **next event that starts after this cell on the same day**
(the day's events are already in `eventsData`):

```text
roomBeforeNextEvent = nextEventStart - hi      // in half-hour slots
if (roomBeforeNextEvent < minSlots) → cell-no-room
```

- A cell 30 minutes before a 1-hour class: `1 < 2` → blocked.
- A cell 30 minutes before a 30-minute class: `1 < 1` false → allowed (it fits exactly).
- Back-to-back events, or an event starting in the same hour: still computed the same way.
- The **day-end** run stays the `cell-no-fit` job (Change 2). A cell cannot be both, because a
  following event always ends the run sooner than the day does.
- Only the `cell-available` branch is affected. Occupied, pending, reserved, PH and the
  lead-time `cell-too-soon` cells are untouched.

### 5.3 Clicking one

Mirror the late-cell guard at `:405`: show the reason in the same bottom-left message and do not
open the arrange pop-up.

```js
if (div.classList.contains('cell-no-room')) {
    toast.show('Not enough room below this class — pick a Free Cell.');
    return;   // no tooltip, no modal
}
```

Keep `tabIndex`, `role="button"` and the `aria-label` so the cell is still reachable by
keyboard/screen reader; the hover words carry the explanation.

### 5.4 Manual check (the user's own example)

Venue **B103**, **Week 9**: the cells **Mon 8:30 AM**, **Tue 1:00 PM**, **Fri 9:30 AM** and
**Fri 1:30 PM** must show the blocked cursor and "Not Enough Slot" (assuming those classes are
1 hour or longer). Adjust the wording if the real class lengths make the count wider —
the rule, not the example, is the spec.

---

## 6. Reuse map

| What | Where | Why |
| --- | --- | --- |
| `noticeHtml(head, muted, guideCaps, guideTail)` | `venue-timetable.blade.php:658` | the four-slot notice shape — Change 1 stays inside it |
| `spanOccupied(spanSlots)` | `venue-timetable.blade.php:554` | the overlap test the new rule must agree with |
| `eventsData` / `hours` | `venue-timetable.blade.php:231`, `:513` | the day's events and the 21-slot day |
| `toast.show(...)` | `public/js/ui-common.js:2855` | the bottom-left message the page already uses |
| `--hover-label` + `::after` pill | `public/css/theme.css:2616–2619` | the hover words mechanism |
| `.cell-no-fit` precedent | `venue-timetable.blade.php:60–72` | copy its *shape*, not its colour |

---

## 7. What must not change

- The 08:00–18:30 grid, `BOOK_SPAN`'s other uses, and the `Too Late` / `to Book` badge wording.
- `cell-too-soon` (the 3-working-day lead time) and every occupied / pending / reserved / PH cell.
- Other pages' grids (My Timetable, Cohort, Replacement Arrangement) — this is the venue page only.
- The frozen design template (`resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`)
  is a reference, not a target.

---

## 8. Tests to update / add

- `tests/venue-book.spec.ts:101` **U2a-T6** asserts the toast text `/120 minutes/` and that a
  `.cell-no-fit` exists — it must now expect the **class-driven** minutes and may need a week
  whose shortest class is not 1 hour.
- `tests/venue-book.spec.ts:24` `firstFreeCell()` uses
  `.cell-available:not(.cell-no-fit)` — add `:not(.cell-no-room)` (or the chosen class) so the
  helper never clicks a blocked cell.
- New assertions:
  - a cell with a following event inside `minSlots` carries the blocked class, shows
    `content: "Not Enough Slot"`, and has `cursor: not-allowed`;
  - clicking it produces the message and **does not** open `#availableTooltip`;
  - a cell with exactly `minSlots` of room before the next event is still bookable;
  - the last cells of the day follow the real class length, not 120 minutes.
- PHPUnit: `minBookableSlots()` returns the smallest `durationSlots`, and the render payload
  carries `minClassSlots` (empty list → `BOOK_SPAN`).

---

## 9. Constraints (house rules)

- No new dependencies; colours from `theme.css` tokens only; no new colour for the blocked state.
- Demo DB read-only; test writes only against the seeded testing DB
  (`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`); never loosen
  `tests/helpers/receipt-target-guard.ts`.
- `php artisan view:cache` must compile; `node --check public/js/ui-common.js`; `pint`.
- Changelog: `page-changelogs/venue-timetable-ui-changelog.md` (all three changes, with the
  real-length table from §4.2).
- Conventional commit, e.g. `ui(venue-timetable): late and blocked cells follow real class lengths`.
- Never push.

---

## 10. Decisions (settled 2026-11-05)

1. **Gap sentence:** drop the number, keep the warning — never print a gap length that is not a
   rule.
2. **Late cells:** keep the red state, but judge it with the shortest class this venue can host
   (not the fixed 2 hours).
3. **Cells that cannot fit a class:** only the cursor and the hover words change
   (`Not Enough Slot`); the cell keeps its normal background.
4. **Which cells:** every free cell with too little room before the next event — not only the
   single cell touching the class.
5. **Click:** show the reason in the bottom-left message; do not open the arrange pop-up.

---

## 11. Risks / things to say out loud

- A 6:00 PM cell can only ever be bookable if a 30-minute class exists; with 1-hour classes it
  stays red. That follows from decision 2 — flag it in the changelog so nobody reports it as a bug.
- `minBookableSlots()` runs on every render; if it proves slow, cache it per request (memoised
  property), not per cell.
- The hover pill for the new state inherits the cell's normal colour; check it is readable on the
  green/blue available background in both themes.
