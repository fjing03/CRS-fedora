# Design — notif-abstime-precision

## D1 The edits (exact)

Island (`resources/views/livewire/nav-notifications.blade.php:33–36`):
- the `$absTime` closure signature `int $m` → `$at` (the Carbon created_at);
  body: format the instance directly (`$at->copy()->format('j M, g:i A')`);
  the null-safe guard returns '' (an absent time simply shows the relTime).

Partial (`resources/views/partials/notif-row.blade.php:28`):
- `data-tip` source `$absTime($m)` → `$absTime($n->created_at)`; `$m`
  (the relTime minutes) retained for the visible label.

`$minutesAgo` stays as-is (drives `$relTime` only) — as does the unused-nothing
else in the closure set.

## D2 Stability receipt (the apply proof)

`:8000` as 5425 (read-only): open the panel, capture `#notifList`'s row tips
(`data-tip` of `.notif-row-time`), wait ≥11 s (one poll cycle), re-capture:
the SAME data-tips must be byte-equal (the previous implementation rotated
one minute per boundary). Plus the :8000 island spec 7/7 (no regression) and
the full suite.

## D3 Out-of-scope record

- F1 (dual-role dedupe) — deferred by the user; not here.
- The venue #tooltipBookBtn affordance — REJECTED by the user (2026-10-30);
  it leaves the candidate list entirely.
