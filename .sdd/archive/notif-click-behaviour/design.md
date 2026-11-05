# Design — `notif-click-behaviour`

## 1. Where each piece lives

| Piece | File | Change |
|---|---|---|
| the status the click branches on | `app/Livewire/NavNotifications.php` | `notif_status` attribute per row, resolved live |
| the class-only status (no request) | same | `classOnlyNotifStatus()` |
| the single-row hide | same | `hideOne(int $id)` — reuses `gateableInboxRows()` |
| the destination + anchor, new and legacy rows | `resources/views/partials/notif-row.blade.php` | `$href` re-point + `o<session>` arm + `data-notif-status` |
| stored links for new notifications | `app/Support/Notifications/NotificationEmitter.php` | two lecturer links → `/my-replacements` |
| the click branch + in-place hide | `public/js/ui-common.js` | `data-notif-status === 'completed'` → `hideNotifRowInPlace()`; `notif-w` also rides `o` anchors |
| the arrival for a cancelled class | `resources/views/livewire/replacement-home.blade.php` | `jumpToMyOccurrence(sessionId, week)` + the `o` arm in the arrival block |
| the flash that survives a rebuild | `public/js/ui-common.js` `tryFind()` | re-query the selector at flash time |

## 2. Status resolution (`NavNotifications::resolveRequests`)

```
row.request_id === null
    └─ class_session_id !== null && link === '/my-timetable-ui'   → 'cancelled'
    └─ otherwise                                                  → null   (no branch: stored link)
row.request_id !== null
    └─ $mapper->effectiveStatus($request, semesterStart)          → pending|approved|rejected|completed|cancelled
```

`effectiveStatus()` is the SAME resolver the pages use, so **Completed is derived** from an approved
request whose replacement date has passed — never a stored column.

The request-less case is stamped in `resolveRequests()`'s early exit as well: a cancellation-only inbox
has no request batch to iterate, and that is precisely the case this lane is about.

## 3. The row's destination and anchor (`notif-row.blade.php`)

| Stored link | request_id | href | anchor |
|---|---|---|---|
| `/request-approval-ui` | yes | unchanged | `r<request>` |
| `/my-request-history-ui` | yes | unchanged (alias route, same page) | `r<request>` |
| `/my-timetable-ui` | yes (replacement confirmed) | **`/my-replacements`** | `r<request>` |
| `/my-timetable-ui` | no (class cancelled) | **`/my-replacements`** | **`o<session>` + `notif-w`** |
| `/student-my-timetable-ui` | — | unchanged | untouched |

Mapping the legacy stored link at render is deliberate: the demo already holds notifications written
before this lane, and they must behave like new ones. The gate is still the stored link (the lifecycle
law) — only its *destination for lecturers* moves.

## 4. The click (`ui-common.js` capture-phase handler)

1. mark the row read (unchanged)
2. `data-notif-status === 'completed'` → `hideNotifRowInPlace(row)`; if it returns true, `preventDefault`
   and stop. If it returns false (no Livewire component, or no id) → fall through to the link, so a click
   is never swallowed.
3. otherwise append the anchor to the href, exactly as before — plus `notif-w` for `o` anchors.

`hideNotifRowInPlace` finds the owning component by **containment** (`Livewire.all().find(c => c.el.contains(row))`)
rather than a `[wire\:id]` selector: the first implementation used the escaped selector, threw
`SyntaxError`, and a synthetic-row probe caught it. Livewire 4 note: the call API lives on `$wire`.

## 5. The arrival (`replacement-home.blade.php`)

```
?notif-hl=r<requestId>            → jumpToMyRow()          (existing; the shared r-arm flashes)
?notif-hl=o<sessionId>&notif-w=<week>
                                  → jumpToMyOccurrence()   (new; flashes tr[data-id] itself)
```

Both are captured **synchronously** at `DOMContentLoaded` (the shared highlighter strips the params
150 ms later). `jumpToMyOccurrence` matches the payload on `sessionId` + `week` — never on the row-id
formula — pages the row into view, and calls `tryFind('tr[data-id="…"]', 20)`. A payload miss is
silent, as with the `r` arm.

## 6. The flash fix this lane had to make

`tryFind()` flashed the element it had resolved 500 ms earlier. On `/my-replacements` the skeleton
teardown rebuilds the table in that window: measured, the element was **disconnected** by +700 ms
(`DBG-700 connected false`) while still carrying `.bookable-flash` — a pulse painted on a detached node,
i.e. invisible. `tryFind` now re-queries the selector at flash time and flashes whatever carries it
(falling back to the element it found). That is also safer for the notification `s` arm on the timetable,
where a week rebuild can do the same thing.

## 7. Risks

| Risk | Answer |
|---|---|
| hiding a row that still needs the user | `hideOne` re-uses the manage-mode gate (`read_at !== null` OR decided); a completed request is decided, so it is allowed — and the Hidden shelf restores it |
| the click swallowed when Livewire is absent | `hideNotifRowInPlace` returns false and the handler falls through to the link |
| flashing the wrong class | a cancelled row is matched on session + week; a request row on its request id; no fallback selectors |
| a refresh re-hiding / re-flashing | hiding is a stored state (idempotent); arrival params are stripped before matching |
| old notifications pointing at the timetable | the partial maps the legacy link at render |
