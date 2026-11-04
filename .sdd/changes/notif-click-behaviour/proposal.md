# Proposal — a notification click follows the record's STATUS, not its stored link

**Lane:** `notif-click-behaviour` · **Created:** 2026-11-04 · **Status:** applied

## Why

Until now a notification click was a *link*: the row carried the destination written when the
notification was created (`/my-timetable-ui` for class news) and the click went there. That was right
when the timetable was the only place a replacement lived. It is wrong now:

- the lecturer works on **My Replacements**, where a class and its request state share one row;
- a **cancelled** class has a row there ("Need to Arrange") that the click should point at;
- a **completed** replacement has nothing to jump to — the class already happened — so the click
  should simply put the notification away.

## What the user ordered (2026-11-04, part 1 only)

| Related record status | On click |
|---|---|
| **Cancelled** | open `/my-replacements`, flash that class's row |
| **Approved** | open `/my-replacements`, flash that request's row |
| **Completed** | hide that notification row in the panel, stay on the page |

## Scope

1. **Identity, not guesses.** A request row is identified by its **replacement request id**; a
   cancelled class has no request, so it is identified by **class session + week** and matched to the
   row on the page. Never by name, never by "the only cancelled thing this week".
2. **The status is the record's LIVE status**, resolved the way every page resolves it (so a derived
   *Completed* counts, not a stored column).
3. **The flash is the existing one** — `flashRowPulse` / `.bookable-flash` / `bookable-pulse`, already
   used by the notification, Today and Earliest-bookable jumps.
4. **Hiding reuses the existing hide path** (`NavNotifications::hideOne` → the manage-mode gate →
   `hidden_at`), so nothing is deleted and the Hidden shelf brings the row back.
5. **Lecturer-facing class notifications now store `/my-replacements`**, and the row partial maps the
   legacy `/my-timetable-ui` rows to the same behaviour, so old and new notifications agree.
6. **Student notifications are untouched** (`/student-my-timetable-ui`), as are the approval-queue
   (`submitted`) and rejected rows.

## Out of scope

- The other two items of the earlier order (manage-screen Back button, notification panel width).
- `request-approval-ui` behaviour, approval logic, and every write path except the single-row hide.
- The `mytimetable-jump-highlight` lane (record → timetable). It stays proposed and unapplied.
- Fixing F1 (the notification arm's dead `notif-w` week switch): these rows no longer land on the
  timetable, so that path loses its last caller.

## Acceptance

1. Clicking a cancellation notice opens My Replacements and flashes the class's row.
2. Clicking an approved notice opens My Replacements and flashes the request's row.
3. Clicking a completed notice hides the row in place; the page does not change.
4. Pending/rejected rows keep exactly their current behaviour.
5. Whatever the outcome, a refresh never re-flashes and never re-hides.
