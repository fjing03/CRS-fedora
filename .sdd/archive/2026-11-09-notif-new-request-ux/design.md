# Design — notif-new-request-ux

**Date:** 2026-11-01
**Frozen baseline:** `proposal.md` (D1–D15) + `explore-brief.md` + `explore-notes.md`
**Status:** DRAFT (batch 2 — awaiting reviewer gate)

---

## 1. Architecture: server-side composition, partial-only rendering

The 10 s `wire:poll` morph re-renders the island server-side — every composed string
must be emitted by the blade per render (no JS one-shot bindings). All composition
lives in `app/Livewire/NavNotifications.php`; `resources/views/partials/notif-row.blade.php`
only renders what it is given.

```
NavNotifications::render()
  ├─ rows = Notification::where(user)->orderByDesc(created_at)->get()   // 1 query (unchanged)
  ├─ resolveRequests(rows)                                             // 1 replacement_requests query (unchanged shape)
        ├─ batch whereIn(replacement_requests) + ->with(['classSession.module', 'replacementTimeSlot.venue', 'proposer.lecturer'])
        ├─ spans  = $mapper->spansForRequests(ids)                     // 1 join query (replacement_request_slots ⊕ time_slots)
        ├─ venues = Venue::whereIn(id, span venue ids)->keyBy(id)      // 1 query
        ├─ sems   = Semester::whereIn(id, request semester ids)        // 1 query
        └─ per row ->attach _display[] / inactive reason            // PHP only
  └─ unreadTotal  = BATCH-DERIVED in PHP from the classified rows      // 0 new queries
```

**Query budget vs the ONE-QUERY law** (`NotificationsBridgeTest.php:253-274` counts
SQL mentioning `replacement_requests`): the batch stays the ONLY such query — the
span join hits `replacement_request_slots`, which does NOT contain the substring
`replacement_requests` (char-verified: `…request_` + `slots`, no `requests`);
`->with()` eager loads hit `class_sessions/modules/users/lecturers`; the venue/semester
batches hit their own tables. The per-row span queries of the existing
`replacementStrings()` path are NOT introduced on this page (see §3).

**Compose attachment**: each request-linked row gains `_display` (array with the keys
below) or `_inactive_reason` (string), attached in `resolveRequests`; non-request rows
stay untouched.

## 2. The composer (`type === 'submitted'`, fallback matrix per D6)

Inputs per row: the resolved `$request` (may be null = fail-open), `$semesterStart`
(from the sems map — **NO `now()` fallback**: the tip's schedule line composes ONLY
from a REAL semester row; a missing semester row fail-opens the WHOLE tip to the
single-line `title — body`, while `desc` (which needs no date) still composes), the
row's span array
with its display venue (eager-loaded — see §3), the eager-loaded class session +
module + proposer(+lecturer). **Time token source:** the composer builds desc/tip
times from the RAW `span.start`/`span.end` (24 h, UNspaced en dash `09:00–11:00` —
the ask's exact format); `stringsFromSpan()['time']` keeps the legacy SPACED form
for the history consumers and is NOT the composer's source.

| Output key | Source | Fallback (any required fact missing) |
|---|---|---|
| `_display['title']` | `New replacement request (ID #%d)` from `$n->request_id` (legacy rows included — the ID is stored on them too) | stored `$n->title` |
| `_display['subtitle']` | `{proposer->name} ({proposer->lecturer->staff_id})`; staff_id null → name alone | none (no subtitle span) |
| `_display['desc']` | `Week {span.week} · {DAY_NAMES[span.day]} · {span.start}–{span.end} · {venue.code}` (mapper-formatted 24 h, en dash — `Week 7 · Friday, 09:00–11:00 · B002`) | stored `$n->body` |
| `_display['aria']` | composed title + ' — ' + subtitle + ' — ' + desc (+ ` (inactive)` for inactive rows); missing parts are simply JOINED OUT (the surviving parts concatenate — the aria never falls back unless title+desc BOTH fall back) | stored title — body (+ ` (inactive)`) |
| `_display['tip']` | the 5-line block (§5) | `title — body` single line |

Non-`submitted` rows: untouched (title/body/aria unchanged). Non-request ACTIVE rows
(class-cancelled/update): `rowTip` stays `title — body` (frozen proposal's part-4 scope).

**Fail-open granularity (the one ruling all composer tests encode):** IDENTITY lines
(1 Staff, 5 Subject) are per-line omissions — a missing proposer drops line 1, a
missing classSession/module drops line 5, the rest of the tip survives. SCHEDULE
lines (3+4) are all-or-nothing with the WHOLE tip: a missing span, a missing
`replacementTimeSlot`, or a missing semester row → the entire tip fails open to
`title — body` (a tooltip missing its schedule is misleading, not degraded).

## 3. Mapper surgery (additive, existing callers byte-identical)

`app/Services/ReplacementRequestMapper.php` — the house already composes with
`classRow()` + `replacementStrings()` per row (`MyRequestHistory.php:65-76`); that path
runs 1 span query + 1 lazy venue query PER ROW, which is fine on-demand but wrong for a
10 s poll. Additive refactor, zero behaviour change for existing callers:

- **`stringsFromSpan(?array $span, Carbon $semesterStart, ?Venue $venue): array`** — NEW.
  The body of today's `replacementStrings()` parameterised: `date` (addWeeks/addDays
  math), `time` (`H:i – H:i`, 24 h en dash), `week`, `day` (`DAY_NAMES[span.day]`),
  `venue` (code), `venueName` (`$venue->room_name` or null). `replacementStrings()`
  becomes a thin wrapper: build `$span`/`$slot` as today, call `stringsFromSpan()`,
  return the same keys as before (existing consumers read code/date/time only —
  additive keys are safe).
- **`spansForRequests(array $requestIds): array`** — NEW. ONE query:
  `replacement_request_slots ⊕ time_slots whereIn(replacement_request_id, ids)`
  ordered by `start_time`, grouped into the SAME first/last shape `span()` returns
  (`week/day/venue_id/start/end/count`). `span()` itself stays (legacy callers).
- The spans batch also covers `displaySlot()`'s `replacementTimeSlot` fallback branch:
  the `->with()` graph gains **`replacementTimeSlot.venue`** (law-legal — the eager
  loads hit `time_slots`/`venues` tables), so a request with NO `request_slots` rows
  composes from the LOADED anchor relation — the rare legacy shape never lazy-loads
  on the poll; no per-row `replacementStrings()` call happens on this page.
  (Multi-slot spans — junction count > 1 — keep `span()`'s first/last shape: the
  desc/tooltip show the first slot's week/day/venue with min–max times, the SAME
  pre-existing behaviour the queue/history pages render; deliberately reused, not
  re-shaped here.)

## 4. Counting + classification (`NavNotifications.php`)

**Unread total (D5, batch-derived)** — after rows load and are classified:
`$unreadTotal = $rows->whereNull('read_at')->reject->isInactive()->count()` over the
WHOLE mailbox collection (the island loads it unfixed — verified no limit). The badge,
pill, `$userTotal`, the empty-state read this ONE derived number, and the unread-only
toggle filter reads the literal predicate `read_at === null && inactive_reason === null`
(the inactive exclusion can't be forgotten); the standalone COUNT query at `:55` is
DELETED. `markAll()` unchanged
(stamping `read_at` everywhere stays harmless — classification ignores it).

**Inactive reasons (D15 — first-match chain, NOT a boolean)**:
`resolveRequests` attaches, for `request_id !== null` rows, in order:
1. `status === 'cancelled'` → `'cancelled'` (also the preserved current edge:
   `status === null` (missing request) → `'cancelled'`-copy, documented in-line —
   the in-app delete path does not exist, so the copy stays truthful in practice);
2. `status === 'approved'` → `'approved'`;
3. `status === 'rejected'` → `'rejected'`;
4. `read_at !== null` → `'read'`.
No reason → ACTIVE row (href, `data-id`, `data-notif-target` all preserved — the
click-highlight first click keeps working).

**Copy family** (D1 + D15, exact strings):
- cancelled: `This notification is inactive because the request was cancelled by the requester.`
- approved: `This notification is inactive because the request was approved.`
- rejected: `This notification is inactive because the request was rejected.`
- read: `This notification is inactive because you have already read this request.`

## 5. The multiline tooltip (part 4; engine untouched)

Lines joined with `\n` into the row's `data-tip` (the engine's multiline arm,
`ui-common.js:3038-3042`, adds `.data-tip-tooltip--multi`; `textContent` injection —
no innerHTML). Positioning: unchanged (above + clamp, D10).

```
Staff: 5425 — Stephen Curry
Requested at: 29 Oct 2026, 10:44 AM (5 minutes ago)
Proposed Schedule: Week 7 · Friday, 6 Nov 2026 · 09:00–11:00
Proposed Venue: B002 — Tutorial Room
Subject: AMSE2003 (L) — Subject Name
```

- Line 1: `{staff_id} — {proposer->name}`; missing staff_id → name alone; missing
  proposer → line omitted (fail-open, never an empty shell).
- Line 2: `$tipAbs` (`j M Y, g:i A` — the ask's exact format, same display TZ
  machinery as the panel's existing `$absTime` closures, notif-abstime-precision law)
  + `( {relLong} )` — verbose relative (D9, lowercase like the row chip family):
  `just now` / `N minute(s) ago` / `1 hour ago` / `N hours ago` / `Yesterday` / `N days ago`; recomputed per render.
- Line 3: `Week {w} · {Day}, {d M Y} · {time}` — date's day-month-year from
  `stringsFromSpan()['date']` (`Y-m-d` → `d M Y` tokens), same MYT date math as the
  mapper already ships.
- Line 4: `{venue.code}` or `{venue.code} — {venue.name}` (D11: name null → code alone).
- Line 5: `{courseCode} ({classType}) — {moduleName}` read DIRECTLY from the
  eager-loaded `classSession.module` + `classSession.session_type` — **never call
  `classRow()` on the poll** (it lazy-loads the class venue and re-derives the date);
  the class type letter renders as stored.
Scope: ACTIVE request-linked rows (`submitted/approved/rejected/awaiting`). Inactive
rows keep their D15 family sentence; class-cancelled/update rows keep `title — body`.

## 6. View + CSS changes

- `resources/views/partials/notif-row.blade.php`: the row body renders
  `_display['title']`(fallback title) + NEW `<span class="notif-row-subtitle">` +
  `_display['desc']`(fallback body); `$rowTip` branches: inactive → the reason copy;
  active + `_display['tip']` → the multiline tip; else `title — body`. The aria-label
  mirrors. The target/href/data-id mechanics are UNTOUCHED (click-highlight law).
- `resources/views/livewire/nav-notifications.blade.php`: add the `$relLong`/`$tipAbs`
  closures beside the existing `$minutesAgo/$relTime/$absTime` (:23-38, abstime-
  precision pattern); the unread-derived count flows into badge/pill/mark-all as today.
- `public/css/theme.css`: ONE new class `.notif-row-subtitle` inside the notif block
  (≈:479-493 family) — smaller step, `--color-on-surface-variant`, no new tokens
  (§10.0 law). Mobile inherits the panel's existing media queries (:513-526).

## 7. Promoted to shared

| What | Where | Why |
|---|---|---|
| `stringsFromSpan()` — the span→strings body | `ReplacementRequestMapper` (shared service) | 3rd request-display consumer (history, API adapter, now the notif composer) rides ONE format source; `replacementStrings()` callers unchanged |

## 8. Test updates (frozen map from the proposal)

- UPDATE `NotificationsBridgeTest.php`: `:147` exact sentence → D1 string;
  `:191-201` → approved row renders INACTIVE (no href, `.notif-row--inactive`,
  D15-approved tip); `:151-166` badge law comment reworded to actionable-unread +
  a new assertion set for inactive-exclusion; NEW composer tests (title-with-ID,
  subtitle name+staff-id, desc format, 5-line tip, fail-open matrix).
- UPDATE `tests/notifications-island-testing-db.spec.ts`: `:131-133` (read submitted
  rows become inactive spans — `a.notif-row--read` body receipts for OTHER types),
  `:148` → D1 string; NEW writable receipts for badge-exclusion + inactive-on-decision.
- KEEP GREEN: `:82` (stored body), `:253-274` ONE-query law, ONE-FEED equality,
  `:221` read endpoint, email-side (`ApprovalWorkflowTest.php:520/:566`,
  `EmailQueueTest.php:29`).
- Gates: pint + phpstan(1G); Playwright per the proposal's env-guard split
  (:8000 read-only island spec / :8030 writable, `CRS_MODE=testing CRS_ALLOW_SQL=1`).

## 9. Non-goals in this design

No toast/undo (display-only change), no new icons, no mobile-work beyond inheritance,
no emitter/mail/recipients/awaiting/schema changes, no other lanes' files.
