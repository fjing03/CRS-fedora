# design — `cancelled-blocks-stay-visible`

Reads against the frozen proposal (round-1 folds in place). The implementer
re-greps every anchor.

## D1 — the loop head (`ResolvesTimetableTimeline::buildEventsByWeek` ~:226 — re-grep; the R2-audit drifted :226→:228)

The FIRST decision — round-2's fold — **the discriminator is READ-TIME, not
a check-value regime**:

- The D7 release stamp ALWAYS coexists with an APPROVED replacement request
  for the same `(session, week)` pair (approve → release in one txn; the
  `approvedByWeek` map is already built in this function from `$requests`).
- A user cancellation NEVER has an approved pair for its own week (a
  proposal still pending or rejected or absent).
- Therefore: **skip the render iff the pair is exception'd AND approved-pair
  covered**; otherwise the exception'd week renders the cancelled block.
- NO CHECK widen, NO migration, NO ApproveRequest edit, NO backfill paste —
  the R1 sheet section STRUCK (the demo sheet becomes EMPTY; the 10-value
  CHECK stays as-is; `replaced` dies as a value; `official_event` keeps
  both duties safely).

```php
// extra map (0 extra queries — $requests is already loaded):
$approvedByPair = [];
foreach ($requests as $r) {
    if ($r->status === 'approved') {
        $approvedByPair[$r->class_session_id.':'.$r->week_number] = true;
    }
}

if (isset($exceptionKeys[$s->id.':'.$w])) {
    if (isset($approvedByPair[$s->id.':'.$w])) {
        continue;   // law ④: an approved release (the D7 stamp or the
                    // user-cancel-then-approve composite) — the original
                    // vanishes; the landing block renders at its own week
                    // via the approvedByWeek junction pass below
    }
    // laws ①/③: the cancelled block STAYS — original slot, red, labelled
    $ev = $baseBySession[$s->id];
    $ev['status'] = 'cancelled';
    $ev['cancelledReason'] = ReasonLabel::from(
        $exceptionRow[$s->id.':'.$w]->reason ?? '');   // the LABEL string (the payload contract);
                                                       // the model map replaces the boolean map
    $pendingOnCancelled = isset($pendingByOccurrence[$s->id.':'.$w]);
    if ($pendingOnCancelled) { $ev['proposed'] = true; }   // law ③ "(Proposed ⏳)"
    // D-P4 receipt: the pair's fact rides so the popup's Replace-Now
    // handoff stays available for the cancelled-only state; the
    // 'handled' pair yields null (pending/approved pairs) — transparent.
    $fact = $catalog->occurrenceFact($s, $w, $factContext);
    $ev['occurrenceFact'] = $fact !== null
        ? ['week' => $w, 'kind' => $fact['kind'], 'reason' => $fact['reason']] : null;
    $events[] = $ev;
    // the amber make-up rides SAME-WEEK from THIS branch; ALWAYS continue —
    // the tail push can never double-fire.
    if (($p = $pendingByMakeupSlot[$s->id.':'.$w] ?? null) !== null) {
        // the EXISTING amber shape (the $pending path, D-P10): re-shape the
        // natural block onto the slot's own di/start/end/venue — the
        // implementer may hoist that emission into a tiny private helper
        // reused by both branches (the DRY deme no constraint needed).
        $events[] = $this->buildAmberEvent($baseBySession[$s->id], $p, $s);
    }
    continue;
}
```

The map conversion: `$exceptionKeys` (bool map) →
`$exceptionRow` (session:week → ClassException row) — the same keying
`LectureSessionCatalog::factContext` already uses. `$incidentRows`… reuse
the `$exceptions` Collection directly (`keyBy` once at the top).

The amber make-up block: the round-3 🔴1 pin — the emission order and the
no-fallthrough rule:

1. the exception'd branch emits the cancelled block and, IF
   `$pendingByMakeupSlot[$s->id.':'.$w]` is SET (a same-week makeup — a Tue
   class proposed to Thu of the same week), emits the amber block from the
   SAME branch (`$events[] = $evA` — the conventional shape), then
   `continue` — ALWAYS `continue` after the exception'd branch, so the tail
   `$events[] = $ev;` can never double-push (the R3 🔴1 no-fallthrough
   receipt).
2. the guard on the else-side: the pending path's NATURAL emission never
   sees an exception'd week (the branch ordering guarantees it — the
   exception'd pair cannot reach the pending path).
3. the D7 receipt #2 caveat: the amber 'already renders today' hold is
   TRUE ONLY for different-week makeups; a same-week makeup renders a
   TOTAL BLANK today (the exception skip ate the whole week) — D7 receipt
   #2 pins the NEW same-week shape.

The map conversion final: ONE `$exceptionRow = $exceptions->keyBy(...)` —
presence (`isset`) + the reason both read from it; the bool map DIES
(the R3 optional suggestion folded). The `cancelledReason` label rides on
the cancelled event ONLY when the row exists.

## D2 — the state matrix (who renders what)

| state | the cancelled original block | the amber make-up block | the approved landing block |
|---|---|---|---|
| cancelled-only | ✅ red `event-conflict`, tip `cohort · CANCELLED` (the user-locked form), note `(Cancelled)`, Replace-Now available | — | — |
| cancelled + proposed (pending) | ✅ red + `(Proposed ⏳)` note | ✅ amber at its own slot (existing) | — |
| cancelled + APPROVED | ❌ (the approved-pair read-time derivation → skip) | — | ✅ at the slot's own week (existing D-P10) |
| cancelled + request REJECTED | ✅ red plain (the `pendingBy*` key off `pending` only — teardown is automatic; the pinned receipt) | ❌ | — |
| exception w/o any approved pair, any reason (incl. legacy `official_event`) | ✅ red cancelled | — | — |

## D3 — `ReasonLabel` (NEW `app/Support/ReasonLabel.php`)

```php
final class ReasonLabel
{
    public const OVERRIDES = [
        'medical_leave' => 'Medical/Sick Leave',
        'other' => 'Other',
        'replaced' => 'Replaced',
    ];

    public static function from(string $enum): string
    {
        return self::OVERRIDES[$enum] ?? ucwords(str_replace('_', ' ', $enum));
    }
}
```
Delegations (the promote-on-3rd receipt): `ReplacementHome::titleCase`
→ `return ReasonLabel::from($enum);`; `ApiReadController::$reasonMap` →
built from `ReasonLabel` (its 10-key map collapses to the helper; the
'e2e 10-label vocabulary' arrays stay as-is — same labels); the timeline
payload uses it in D1.

## D4 — STRUCK (round-2 fold)

`ApproveRequest` is UNTOUCHED: the release keeps stamping `official_event`
and the read-time derivation (D1) makes the stamp's VALUE irrelevant. The
CHECK widen / migration / backfill / label-for-`replaced` collapses with it.

## D5 — the client — FIVE touch points (round-3 additions)

1. `buildTimetableGrid` :726 AND :754 — `const dayEvents = ...filter(e =>
   e.di === di && e.status !== 'cancelled')` (×both) → the cancelled
   filter TERM removed; cancelled events join the day render.
2. the class family (:805) — insert BEFORE the conflict arm:
   `else if (e.status === 'cancelled') { div.classList.add('event-conflict'); }`
   — event-conflict REUSED (no new CSS; the §10.0 same-name rule).
3. `buildReplacementNote` (:650) — a third top note arm:
   `if (e.status === 'cancelled') return '<span class="ev-note">(Cancelled — ' + labelFor(e.cancelledReason) + ')</span>';
   `labelFor` = the ClassCancellation.labelOf-style passthrough (enum →
   label; already-labelled strings ride; null → '').
4. `eventStatusLabel` (:2343) — the EXISTING cancelled arm extends:
   return 'Cancelled' + (a reason? ' — ' + reason : '') — keep the
   arithmetic minimal: `'Cancelled' . (label ? ' — ' . label : '')`.

(the :801 `.toUpperCase()` is untouched grid behaviour — receipted.)

5. **cohort-timetable.blade.php:508** — the page's own two-axis
   `statusClassFn` (the all-pages-design-parity C4/C5 idiom) gets a
   cancelled arm FIRST (before the conflict arm):
   `if (e.status === 'cancelled') { div.classList.add('event-conflict'); ...the ownership border pair... }` — otherwise the cancelled block takes
   the neutral `else` on the cohort surface (law ① broken on one of the
   three targets).

ALSO the inertness receipt (R3 🟡): the cellRender-branch filter edit is
consumed by replacement-arrangement + venue-timetable, but both carry NO
cancelled events in their payloads (the venue slot-world has none; the
arrangement page builds conflict facts only) — the edit is inert there
(declared in the verified scope OUT rows, not contradicted).

Anchor re-pins (the R3 🟡 drift receipt): re-grep ':726/:754' (the current
file sits ~:731/:788), ':890', ':907→myTeachingStats', ':2343→:2338',
':650' from the file at apply, never from this document.

## D6 — the summary re-machine (round-3 rewrite — three edits, not one)

The `"ONE tally edit + the blades' filters stay"` pairing was
arithmetically contradictory (R3 🔴2):
- the 3 filtered blades starve the new `cancelled` tally term;
- the unfiltered cohort :463 site inflates `sumMyClasses`/`sumMyHours`
  via `myTeachingStats` (:907).

Locked re-cut:
1. **computeSummary** (:883–893): the conflicts tally gains
   `|| e.status === 'cancelled'`;
2. **the THREE blade filters removed** (`my-timetable.blade.php:244`,
   `student-my-timetable.blade.php:124`, `cohort-timetable.blade.php:538`)
   — full arrays pass;
3. **`myTeachingStats`** (:907, not :902 — re-grep) gates `total` and `hrs`
   on `e.status !== 'cancelled'` (the teaching truth — a block that never
   runs is not taught);
   `cohort-timetable.blade.php:463` stays untouched (unfiltered input now
   correct); the conflict cards across all three pages now reflect the
   red cancelled blocks.

## D7 — the tests (round-3 re-cut)

NEW `tests/Feature/CancelledBlockStaysVisibleTest.php` (4 receipts):

1. a cancelled exception (`annual_leave`, no request) → the event carries
   `status='cancelled'`, `cancelledReason='Annual Leave'`,
   `occurrenceFact.kind='exception'` (the fact's `reason` field is the
   COMPOUND pre-existing shape `'Cancelled — Annual Leave'` — not the bare
   label; the payload's `cancelledReason` is the bare label — the receipt
   pins BOTH shapes so nobody merges them);
2. cancelled + a PENDING request for the pair (the SAME-week makeup — the
   R3 🔴1 caveat: a total BLANK today) → TWO events: the cancelled one
   `proposed=true`, the make-up one `status='pending'`; the fact is NULL
   (the handled map eats it) — pinned;
3. the approval composite: a cancelled exception + an APPROVED request for
   the request's week → NO original render (the derivation skip); the
   landing week renders via the approvedByWeek pass;
4. the legacy receipt: an `official_event` stamp + NO approved request →
   red-cancelled (the demo row id-4-class data renders honestly).

   The TIP receipt (user decision 2026-10-27): the cancelled block's
   `data-tip2` renders `cohort · CANCELLED` — the reason string rides the
   payload (`cancelledReason`) but NOT the tip/note displays; the fact's
   COMPOUND reason still lives in the popup family UNTOUCHED (the D-P4
   contract) and at the cancelled+pending state the note reads
   `(Proposed ⏳)` per the existing note law.

**The ×9 fixture re-key list STRUCK** (round-2 audit): the 7
`ReplacementFlowTest` stamps are occurrence-PIN fixtures, `OccurrencePinTest:119`
and `EnforcementProducerPoolTest:59` likewise — ALL stay `official_event`.

**The suite receipt is NOT "unchanged"** (R3 🔴4):
`TimetableWiringTest::test_cancelled_week_renders_the_empty_state` (:243)
creates 'public_holiday' week 3, NO request — exactly the D2 red-cancelled
state — and asserts the empty state `'All classes for this week have been
cancelled.'`. Under the derivation that test FAILS (the red block renders
→ the grid is non-empty). It is RE-CUT (upserted): the renamed receipt
asserts the cancelled block RENDERS (`event-conflict`, the red state) and
the empty state only holds when NO red blocks exist — 257 + 1 re-cut + 4
new = the phpunit gate's shape (D9).

## D8 — the demo-DB sheet — STRUCK (round-2 fold)

NO paste at all this lane: the read-time derivation needs no CHECK widen
(10 stays), no migration, no backfill. `docs/demo-db-data-changes.md`
gains NO row.

## D9 — the gates

- PHP touched: pint + phpstan-1G + phpunit — the gate shape **257 + 1 re-cut
  (`TimetableWiringTest` empty-state receipt upserted) + 4 new = 262**.
- JS touched: `node --check`.
- NO new Playwright spec; NO battery run (the user's regime) — the receipts
  in the lane's changelog + the manual testing.
- The changelogs: my-timetable, cohort-timetable, student-my-timetable-ui,
  venue (the receipt-only note). NO demo-db-data-changes row (D8).
- ONE conventional commit (`feat:`); NEVER push.
