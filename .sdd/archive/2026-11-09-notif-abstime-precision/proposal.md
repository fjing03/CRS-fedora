# Proposal — notif-abstime-precision (combined small-lane batch, the user's approved F2)

## Problem (the user's live observation, 2026-10-30)

Two types of the SAME event (the dual-role class-cancel fan-out) showed
different abs-time tooltips ("3:50 AM" vs "3:51 AM") although the DB wrote
both rows at the identical second (03:50:19, verified read-only). The cause
is the display-time helper's rounding:

`$absTime` computes `now() − (int) minutesAgo(created_at)` — the seconds are
TRUNCATED, so the reconstructed "exact" time drifts backwards by up to one
minute depending on WHICH second of the minute a render lands in. The panel
re-renders every poll cycle, so one row's tooltip can show 3:50 on one
cycle and 3:51 on the next (a render-pass artifact: two captures at
different cycle positions show different minutes for the SAME row).

It never affects ordering (created_at direct), the unread/read states or
anything stored — purely the hover-tip's displayed minute.

## The change (the smallest)

`resources/views/livewire/nav-notifications.blade.php`:

```diff
-    $absTime = function (int $m): string {
-        $d = now()->subMinutes($m);
-        return $d->format('j M, g:i A');
-    };
+    /* F2 (notif-abstime-precision): format the STORED timestamp directly —
+       no now()−minutes reconstruction, no second-truncation drift. */
+    $absTime = function ($at): string {
+        return $at ? $at->copy()->format('j M, g:i A') : '';
+    };
```

`resources/views/partials/notif-row.blade.php:28`:

```diff
-    <span class="notif-row-time" data-tip="{{ $absTime($m) }}" data-tip-pos="left">{{ $relTime($m) }}</span>
+    <span class="notif-row-time" data-tip="{{ $absTime($n->created_at) }}" data-tip-pos="left">{{ $relTime($m) }}</span>
```

- `$relTime` (2h ago / 1d ago — the visible minutes) UNTOUCHED.
- The rendered format string is byte-identical (`j M, g:i A`); only its
  SOURCE changes. No classes/ids/structure touched (frozen UI).

## In scope

- The two blade edits + the Playwright stability receipt (the SAME row's
  tooltip captured twice ≥10 s apart must be EQUAL) + gates + ONE commit.

## Out of scope

- F1 (the dual-role fan-out dedupe) — deferred by the user.
- The venue #tooltipBookBtn affordance — REJECTED by the user (2026-10-30).
- relTime wording, ordering, the badge laws, everything else.

## Risks

- $n->created_at is a Carbon instance (cast) — the null guard defends the
  (theoretically impossible) null; $m stays for relTime.
- Any receipt asserting the abs-tip text against now()−: the byte-diff
  receipts used the tip only as noise; the specs assert relTime-class
  values only; no test breaks.

## Success criteria

- The SAME row's abs-tip is stable across poll cycles (two reads ≥10 s
  apart, equal); a row written at 03:50:19 always shows 3:50 AM.
- Gates: pint + phpstan 0 + the full suite + node check + the :8000 island
  spec. ONE commit, no push.
