# Design — venue-status-parity

**Date:** 2026-11-02
**Frozen baseline:** `proposal.md` (the 12 items + the 3 rulings)

---

## 1. The one architectural decision

**Do not** move the venue page onto `ResolvesTimetableTimeline` (it builds a
*session* grid; the venue grid is a *slot* grid keyed by `time_slots` rows). Instead:

> **Share the LAWS, not the builder** — read the same `LectureSessionCatalog`
> contract the other pages read, and render the same note/status vocabulary.

That keeps every fix small and local, and means there is exactly **one** definition of
"is this pair a conflict / handled / cancelled" in the codebase.

## 2. `app/Livewire/VenueTimetable.php` — the payload gains three truths

### 2.1 Hoist + batch the fact context (once per render)

`$recurring` (the viewed venue's sessions) is today loaded inside the T20 derived
section (~:419). Hoist it above the coalescing loop, then:

```php
$catalog     = app(LectureSessionCatalog::class);
$factContext = $catalog->factContext($recurring);   // exceptions + handled + holidays (3 queries)
$pendingPairs = [];  $approvedPairs = [];           // session:week → true (1 query)
```

`factContext()` is already the shared, tested contract (`handled` = pending ∪ approved,
`exceptions`, `holidays`). The pending/approved split is a second cheap read because the
venue page must distinguish "proposal waiting" (render + hint) from "approved"
(released).

### 2.2 The status law (fixes #2, #8, #11)

For every emitted natural block, with `$pair = session:week`:

```
released  = exceptions[$pair] !== null && approvedPairs[$pair]     → emit NOTHING
cancelled = exceptions[$pair] !== null && !released                → status 'cancelled'
else      = today's severity map (pending → 'pending', else 'normal')
```

- Applying this in the **coalescing emit loop** (not in the exception-specific derived
  path) is what fixes #8 (cancelled-on-a-holiday) and #2 (cancelled-on-a-normal-week)
  with one rule.
- **#11 (user A):** the slot ROWS are untouched, so `occupied` still counts a cancelled
  class. Only the block's appearance/status changes. No totals edit.
- `released` is the timeline's law ④ (`exception && approved`) — byte-parity with
  `ResolvesTimetableTimeline` :245–248.

### 2.3 The derived-holiday law (fixes #3)

The current suppression is `if (isset($handledPairs[$pair])) continue;` where
`handledPairs` = exceptions ∪ (pending ∪ approved). A **pending** proposal therefore
deletes the conflict block. New rule:

```
suppress iff (exception && approved)        // genuinely released
otherwise  emit the block, and
           status = cancelled ? 'cancelled' : 'conflict'
           proposed = pendingPairs[$pair] ?? false
```

`proposed` is the flag `buildReplacementNote()` already understands → `(Proposed ⏳)`.
The `occupied` accrual for the derived block stays exactly as today (it is now correct
because the block genuinely renders).

### 2.4 Per-event keys added (all additive)

| Key | Value | Consumer |
|---|---|---|
| `sessionId` | class_session id | already there; now authoritative for `Class Ref` (#5) |
| `requestId` | the request id (reserved pass only) | shared `View Full Request` action (#6) |
| `proposed` | bool | `buildReplacementNote` (#3) |
| `occurrenceFact` | `?array` from `occurrenceFact()` | `Replace Now` gate (#4/#12) |
| `cancelledReason` | reason label (cancelled blocks only) | future use / popup honesty |

`occurrenceFact` is the **exact** my-timetable datum: `null` = handled (proposal/approved)
or normal → **no button**; non-null (exception/holiday) → button. This single key
replaces two hand-rolled heuristics (cohort's `isMine && (holiday || status==='conflict')`,
venue's `isMine && status==='conflict'`).

## 3. `public/js/ui-common.js` — one line each

1. **`openClassModal()` `Class Ref` row** (fixes #5):
   `value: '#' + (event.sessionId != null ? event.sessionId : event.id)`.
   Rationale: the venue payload's `id` is the grid row key (slot id); the class id is the
   quotable handle the user reports ("#40").
2. **`statusDesc`** (fixes #10): add the `cancelled` arm →
   `'Class cancelled — a replacement is needed'` (mirrors the badge vocabulary; no new
   wording invented, the existing `(Cancelled)` note stays authoritative).

No other shared-JS change: notes, `View Full Request`, footer cleanup and the badge all
already exist.

## 4. `resources/views/livewire/venue-timetable.blade.php`

1. **Note** (fixes #1, #2, #3, #8): replace the inline
   `(e.status === 'replacement' && e.remarks ? … )` with the shared
   `buildReplacementNote(e)` — no `checkOwnership` option, because the venue page
   deliberately annotates everyone's blocks today (the `(Replaced for …)` arm already
   does).
2. **Class chain** (fix #2): `conflict || cancelled` → `event-conflict` (mine) /
   `event-public-holiday` (others') — the existing two-axis vocabulary, no new class.
3. **`Replace Now` gate** (fixes #4, #12): port `my-timetable.blade.php` :141–157
   verbatim, including the "re-read the truth from the served array by `id`+`di`"
   hardening.
4. **Cancel** (fix #7): skip `CancelClass.renderButton(...)` when
   `event.status === 'cancelled'`.
5. **`updateSummary()`** (fix #9): `split('sumTotal', t.total, t.pending || 0)` — the
   `+Y` tail ALWAYS renders (the same law as the four cohort cards). `sumAvailable`
   stays `set(...)` (plain, the user's A).
6. **#6 needs no blade edit** — the shared footer action appears once `requestId` rides
   the event and `requestedBy === currentUser.name`.

## 5. `resources/views/livewire/cohort-timetable.blade.php`

**`Replace Now` gate** (fix #4): replace the hand-rolled `isConflict` with my-timetable's
`occurrenceFact` gate (same body, same hardening). Keep the existing `isMine` condition.

## 6. Laws that must not move

- the locked summary law (cancelled excluded from classes/hours, included in conflicts);
- the two-axis block language (§10.0: colour = status, border = ownership);
- `(Proposed ⏳)` / `(Cancelled)` / `(Replaced for …)` / `(Pending since …)` wording;
- `Available` = the client-counted bookable cells;
- the venue grid's coalescing, de-dupe and offday/lead-time rules;
- `/my-timetable-ui` + `/student-my-timetable` payloads (no key changes at all).

## 7. Promoted to shared

Nothing new is created. Three existing shared assets absorb the duplication:
`buildReplacementNote()` (block notes), `LectureSessionCatalog::occurrenceFact()`
(conflict verdict), and `openClassModal()`'s footer action (view request).

## 8. Receipts plan

- **PHPUnit** (`tests/Feature/VenueTimetableTest.php`): cancelled status + note-ish keys;
  the pending-pair conflict block is emitted with `proposed` (the #3 regression lock);
  `occurrenceFact` null vs non-null; `requestId`; `sessionId` present on derived rows.
- **Playwright** (NEW `tests/venue-status-parity.spec.ts`, **read-only**, demo DB): the six
  live assertions in `proposal.md` §Success criteria, one test per page pair.
- Gates: pint, PHPStan (1G), full PHPUnit, `node --check`.
- Changelogs: `venue-timetable-ui-changelog.md`, `cohort-timetable-changelog.md`,
  `my-timetable-changelog.md` (the shared modal copy).
