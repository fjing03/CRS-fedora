# Explore brief — jump from a replacement record to its block on My Timetable

- **Lane:** `mytimetable-jump-highlight`
- **Explored:** 2026-11-04 (read-only; no code touched)
- **User order:** one icon-only button in the My Replacements ACTION column; one `Go to My Timetable`
  button in the Replacement Details modal for **Approved** / **Completed**; navigate, then **flash the
  matching replacement block once**, reusing the existing animation.

## 1. What already exists (the machinery to reuse, not rebuild)

| Piece | Where | What it gives us |
|---|---|---|
| The ONE pulse | `ui-common.js:220-224` `flashRowPulse(el)` | removes `.bookable-flash`, forces a reflow restart, re-adds it, clears after 1300 ms. CSS: `@keyframes bookable-pulse` 1.15 s, with arms for `tr`, `td`, `.cell-content` and **`.event-block`** (`theme.css:2761-2769`) |
| The retried finder | `ui-common.js:355` `tryFind(sel, left, fallbackSel)` | polls every 250 ms, then `scrollIntoView({block:'center'})` and flashes 500 ms later. Silent skip on a miss |
| The cross-page arm | `ui-common.js:310-348` `applyNotifHighlight()` | reads a URL param, **strips it first** (a refresh never re-flashes), switches the week through `window.selectWeek`, then `tryFind('#tableBody .event-block[data-sid="…"]')` |
| The event block | `ui-common.js:894-903` `buildTimetableGrid` | every block carries `data-sid` = the **class session id** (`e.sessionId ?? e.id`) |
| The week wrapper | `my-timetable.blade.php:266-270`, exposed at `:299` | `window.selectWeek(i)` re-syncs `currentWeek`, `WeekNavigator` and `saveWeek()` — the ONLY safe way to move weeks |
| The modal | `replacement-home.blade.php:281-301` (shell), `:838-845` (footer gates) | `#quickViewModal`, `#cancelRequestBtn` (pending only), `#qvArrangeBtn` (hidden when `isHandled(c)`) |
| The row action cell | `replacement-home.blade.php:460-468` `actionCell(c)` | ban (pending) / eye (approved·completed) / rotate (otherwise) — one button per row today |
| The payload | `ReplacementHome.php:272` | rows already carry `requestId`, `requestStatus`, `sessionId`, `week`, **`replacementWeek`** |

## 2. The gap

1. **The timetable does not know a block's request.** `buildEventsByWeek()` builds the approved
   replacement event from `$baseBySession[$session->id]` and only stamps `status = 'replacement'`
   (`ResolvesTimetableTimeline.php:325-333`). `sessionId` is the *class* id, so the replacement block
   and the class's natural blocks share `data-sid` — matching on it would be ambiguous, and the user
   explicitly forbids highlighting the wrong class.
2. **No navigation exists from a replacement record to its own block.** `goToReplacementFor()` goes to
   `/replacement-arrangement`; the notification arm goes to the *cancelled class* cell on
   `/my-timetable-ui` (`notif-hl=s<session>&notif-w=<week>`), never to a replacement block.

## 3. Rejected approaches (and why)

- **Multiple `.col-action` buttons in a 6 % column.** Two 34 px buttons need ≈ 76 px of content; 6 % of
  the 1280 px table floor is ≈ 77 px *including* padding. Rejected as-is → the column must widen
  (D8), verified at every breakpoint under the existing responsive law.
- **A livewire round-trip to hand the request id over.** The app's cross-page mechanic is URL params +
  `history.replaceState`, not server state (`ui-common.js:4502` documents the same choice). Rejected.
- **`localStorage`/`sessionStorage` handoff.** A bookmark, a refresh or a back-navigation would replay
  a stale flash. Rejected — the same reason the notif arm strips its params first.
- **Reusing `notif-hl` for this.** Its `r`/`s` prefixes mean "approval-queue row" and "cancelled class
  cell"; overloading it would blur two different stories and break the documented notif contract.
  Rejected → a separate arm (D3).
- **Matching the replacement block by session id + week.** Ambiguous by construction (the class's own
  block sits in other weeks; a class can be replaced more than once). Rejected — the user asked for the
  *replacement record* id.
- **A new flash animation.** The user asked for the existing one; `.event-block.bookable-flash` already
  exists. Rejected.
- **Flashing the row in the My Replacements table instead of navigating.** Does not answer
  "where is my replacement class"; the user asked for the timetable. Rejected.

## 4. Final solution (D1–D10)

- **D1** Reuse `flashRowPulse()` + `tryFind()` — the ONE pulse (`.bookable-flash`). No new animation,
  no new duration.
- **D2** Server stamps the request id on the approved replacement event only:
  `$ev['requestId'] = (int) $r->id;` in `buildEventsByWeek`'s approved branch (additive key; the
  concern is shared by cohort/venue/student timetable pages, which simply ignore it).
- **D3** `buildTimetableGrid` sets `div.dataset.rid = String(e.requestId)` **only when the event has
  one** → `data-rid` exists on replacement blocks alone.
- **D4** New URL arm, self-documenting and disjoint from notifications:
  `/my-timetable-ui?repl-hl=<requestId>&repl-w=<week>` (week is **1-based**, the pinned rule).
- **D5** Params are stripped with `history.replaceState` **before** any matching — a refresh or a
  back-navigation never re-flashes.
- **D6** The week moves through `window.selectWeek(wk-1)` — the page's own wrapper — guarded by
  `location.pathname === '/my-timetable-ui'` and `typeof window.selectWeek === 'function'`.
- **D7** **No fallback selector.** If `[data-rid="<id>"]` never appears, the arm skips silently. This is
  the "never highlight the wrong class" rule made structural.
- **D8** Retry budget for this arm: **20 × 250 ms ≈ 5 s** (my-timetable's first render waits on
  `MockData`, the week restore and a skeleton pass — the notif arm's 14 ticks were tuned for the
  approval queue).
- **D9** One shared navigation helper, `goToReplacementInTimetable(requestId, week)`, used by **both**
  entry points (modal button + row icon button) — no second URL builder.
- **D10** `.col-action` widens 6 % → 10 %, `#timetable .col-reason` 22 % → 18 % (both page-local), so
  two icon buttons fit **inside** the column at every width under the responsive law.

## 5. Open question (blocking apply, not the proposal)

**A1.** Part 1 of the user's order ("add a new icon-only button to the ACTION column") does not say what
the button does. This brief assumes it is the **same jump** — `Go to My Timetable`, tooltip
`Go to My Timetable`, shown on **Approved / Completed** rows only, placed after the existing eye button.
Alternatives considered: a second "view" affordance (the eye already does that); showing it on every row
(nothing to jump to for `pending`/`none`); a copy-link affordance (not requested anywhere).
