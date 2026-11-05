# Proposal — one jump from a replacement record to its block on My Timetable

- **Lane:** `mytimetable-jump-highlight`
- **Created:** 2026-11-04 · **Status:** proposed (awaiting A1)
- **Source:** `.sdd/changes/mytimetable-jump-highlight/explore-brief.md` + the user's 2026-11-04 order
- **Assumption to confirm:** **A1** — the ACTION-column icon is the same jump (see §6)

## 1. Why

A lecturer can now see the replacement's status on My Replacements (badges lane, completed-status lane,
merge lane). What the page still cannot answer is the next question every lecturer asks after reading
"Approved": ***where is that class now, in my week?***

Today the answer is a manual hunt: leave `/my-replacements`, open My Timetable, guess the week, scan the
grid. The replacement block is a `status = 'replacement'` event that looks like any other block, in a
week that is not necessarily the one the lecturer last looked at.

Two entry points, one destination:

| Entry point | Surface | When |
|---|---|---|
| `Go to My Timetable` (icon + text) | Replacement Details modal, bottom-right | Approved · Completed |
| Icon-only button, tooltip `Go to My Timetable` | My Replacements ACTION column | Approved · Completed |

Both land on `/my-timetable-ui`, switch to the replacement's week, scroll the exact replacement block
into view and **flash it once** — the same `.bookable-flash` pulse the Notifications, Earliest-bookable
and Today jumps already use.

## 2. What is in scope

1. **Server (one additive key).** `buildEventsByWeek()` stamps `requestId` on the approved replacement
   event so the client can identify the block by the **replacement record**, not by the class.
2. **Shared client.** `ui-common.js` gains `data-rid` on replacement blocks, the `repl-hl` / `repl-w`
   arrival arm, and one `goToReplacementInTimetable()` navigation helper used by both entry points.
3. **My Replacements page.** The ACTION column gains one icon-only button (approved · completed), and
   the Replacement Details footer gains `Go to My Timetable` (approved · completed).
4. **CSS (page-local).** `.col-action` widens so two icon buttons stay inside the column; the responsive
   law ("no cell content paints outside its column") is re-verified at every width.

## 3. What is out of scope

- No change to booking, approval, cancellation or any write path — read-and-navigate only.
- No change to the notification arms (`notif-hl=r…` / `s…`), their tests or their behaviour.
- No change to the modal's existing buttons (Close, Cancel Request, Arrange Replacement) or their gates.
- No change to other pages' blocks, tooltips or status colours.
- No new animation, no new duration, no new colour token.
- The deferred chip-TTL lane and the other parked lanes stay parked.

## 4. NFRs and laws this must respect

- **The ONE pulse.** `.bookable-flash` + `@keyframes bookable-pulse` (1.15 s, one iteration). Nothing new.
- **Arrival-grid-no-pin.** This is a user-initiated deep link of the same family as the notification
  links; it scrolls and flashes but **writes no state** (no default pin, no selection, no week-memory
  storm — it rides `selectWeek`, so the week memory updates exactly as a manual switch would).
- **Right class, never a neighbour.** Identification is by replacement record id; there is **no
  fallback selector**. A miss degrades to "navigated, nothing flashed".
- **Idempotent arrival.** Params are stripped before matching, so refresh/back never replays the flash.
- **Responsive table law.** The ACTION column may not overflow at 1920 / 1600 / 1440 / 1366 / 1280 /
  1100 / 900 / 820; below 1280 the table scrolls inside `#gridScroll`; ≤ 768 the card view takes over.

## 5. Acceptance criteria

1. An **Approved** or **Completed** row's modal shows `Go to My Timetable` at the bottom-right; the
   button is absent for **Pending**, **Rejected**, and "need to arrange" rows.
2. Clicking it lands on `/my-timetable-ui`, the week equals the replacement's week, and the block whose
   `data-rid` equals that request's id carries `.bookable-flash` exactly once, within 5 s.
3. A row whose request is **not** approved/completed shows no new button; the existing ban/eye/rotate
   buttons behave exactly as before.
4. The flash never targets a different block: with two approved replacements in the same week, only the
   clicked one flashes.
5. No cell content paints outside its column at any of the eight widths, and the ACTION column still
   holds both buttons at 1280 px.
6. `php artisan test` stays green; `composer run lint:check` and PHPStan level 7 stay clean;
   `node --check public/js/ui-common.js` passes.

## 6. Open question (blocks apply, not this proposal)

**A1 — what does the new ACTION icon do?** The order says "add a new icon-only button … do not affect
existing action buttons" without naming its purpose. This proposal assumes the jump
(`Go to My Timetable`, approved · completed, placed after the eye). If the intent was something else
(copy link, open in new tab, a second view affordance), only §2 item 3 and `tasks.md` T8–T9 change —
the shared machinery is unaffected.
