# Design — conflict-schedule-header-status-tip

## D1. The server key (additive, ReasonLabel-shared)

`ReplacementArrangement::conflictEventsPayload` — the event array gains:

```php
'cancelledReason' => $exception !== null
    ? ReasonLabel::from((string) $exception->reason)
    : null,
```

(`use App\Support\ReasonLabel;` — the ONE label law; a status
`conflict`/holiday event carries null). Returns shape-grows but ONLY
additively — no consumer reads positionally.

## D2. The client status resolver (one pass over the events)

Inside `renderTitleSummary()` the existing `holidayTip` IIFE becomes
`statusTip`:

```js
const statusTip = (function () {
    if (!hasSlot || !anchorSlot) return null;
    /* 0-based week + the session identity BOTH sides */
    const sid = Number((window.pinnedOccurrence && window.pinnedOccurrence.sessionId)
        || currentSessionId || 0);
    if (!sid) return null;
    const evs = (window.MockData && MockData.cohortTimetable && MockData.cohortTimetable.events) || [];
    const dayIdx = /* the existing abbr/day derivation (unchanged) */;
    const hit = evs.find(function (e) {
        return Number(e.week) === anchorSlot.week
            && Number(e.event.sessionId) === sid
            && Number(e.event.di) === dayIdx
            && Number(e.event.start) === Number(anchorSlot.start);
    });
    if (hit && hit.event.status === 'cancelled') {
        return '\u26A0\uFE0F Class Cancelled \u2014 ' + (hit.event.cancelledReason || 'Cancelled');
    }
    const hs = (window.MockData && MockData.holidays) || [];
    const h = hs.find(function (x) {
        return Number(x.week) === anchorSlot.week + 1 && Number(x.dayIndex) === dayIdx;
    });
    if (h) {
        const label = String(h.label || '');
        const stem = /holiday/i.test(label) ? label : label + ' holiday';
        return '\u00B7 ' + stem + ' \u2014 no class';
    }
    return null;
})();
```

The holiday arm is byte-frozen (the applied lane's law). The cancelled
arm precedes it — the payload's own precedence (an exception beats the
holiday; a class cancelled ON a holiday day reports the CANCELLATION,
matching the grid's read-time discriminator order).

Precedence note = the frozen contract: the same order the server grants
statuses.

## D3. The attributes move (byte-identical visible text)

- The occurrence span returns to the plain form:
  `${slotLine ? `<span class="cs-val">${slotLine}</span>` : ...}`
- The `.cs-head` div gains the conditional attribute in the same innerHTML:
  `${holidayTip/statusTip ? ` data-tip="${escHtml(statusTip)}"` : ''}>`
  on `<div class="cs-head"` — attribute-only; textContent unchanged
  (the mechanical receipt).
- Escape via the shared global `escHtml` (ui-common:1327).

## D4. The receipts (falsifiable, all read-only)

| Anchor | Expected `.cs-head` data-tip |
|---|---|
| `?session=23&week=8` (cs 23Tue, W8, `medical_leave`) | `⚠️ Class Cancelled — Medical/Sick Leave` |
| `?session=40&week=14` (cs 40 Fri, W14 Christmas) | `· Christmas Day holiday — no class` |
| `?session=40&week=7` (normal) | NO data-tip on the head |
| Hover screenshots + the `#subjectInfo` textContent identity pre/post |
- Green suite ≥266/266; pint + phpstan 0 (the payload key ResolvesTimetableTimeline,
  not the shared one); changelog entry; ONE commit (no push).

## D5. Frozen rejects

- NO visible layout/copy/icon changes (attribute-only).
- The occurrence span keeps NO tip (the supersession is user-directed).
- No audit-log dig; `other` binds 'Other'.

## D6. Round-1 fold (the reviewer's 🔴 — declarative in the snippet)
- The cancelled arm checks `hit.event.status === 'cancelled'` — a
  holiday-conflict event (status 'conflict') must NOT render the
  cancellation warning; the holiday arm keeps the frozen literal after
  it. T2 propagates the status-checked form.
