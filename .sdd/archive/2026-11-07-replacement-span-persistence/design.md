# Design

## D1 — the column

`replacement_span` `smallInt` **nullable**, added after `replacement_time_slot_id`, no index, no
default. Nullable is the compatibility contract: a null row keeps today's anchor behaviour exactly,
so the migration cannot change any existing display until a row carries a value.

## D2 — who writes it

`SubmitReplacementRequest::__invoke()` already has `$span` (1..4) validated before the request row
is created; the `ReplacementRequest::create([...])` call gains `'replacement_span' => $span`.
Nothing else writes it — not approval, not rejection, not cancellation.

## D3 — who reads it

`spanFromAnchor(?TimeSlot $anchor, ?int $span = null)`:

```
count  = ($span ?? 1)                                   // null/0 → 1 (legacy)
start  = anchor->start_time
end    = count > 1 ? start + count*30 min : anchor->end_time
```

Callers: `span()` passes `$r->replacement_span`; `ReplacementHome::buildRow()`'s batch fallback
passes `$latest->replacement_span`. The junction path is untouched and still wins, so approved rows
(the only decided rows that keep their junction) read byte-identically.

## D4 — the backfill

`php artisan crs:backfill-replacement-span` — dry run by default, `--commit` writes, `--force`
lifts the demo-DB refusal (database names ending `_fresh` / `_demo`).

Span source, in order:
1. `audit_logs.details->'slots'` count on the row's first `rejected`/`cancelled`/`approved` audit
   entry (the decision's own record — exact);
2. else `count(audit_logs where action = 'submitted')` (one audit row per cell);
3. else the row is left null and listed as `unresolved`.

Only rows with `replacement_span IS NULL` are touched; values outside 1..8 are refused.

## D5 — tests

- `ReplacementSpanTest`: reject a 2-cell request → the PL row and the My Replacements row read the
  true 1-hour range; approve keeps the junction range; cancel stores the span even though the row
  displays a dash; legacy null row keeps 30 min; the command fills from the audit log and refuses
  without `--commit`.
- `MyReplacementsPayloadTest` case 5 keeps its legacy assertion and gains a stored-span leg.
