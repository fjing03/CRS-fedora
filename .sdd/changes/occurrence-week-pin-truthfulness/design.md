# Design — occurrence-week-pin-truthfulness

## D-P0 — the ONE fact-lookup primitive (F1's shared source)
New API on `LectureSessionCatalog` (batched context + a pure verdict — NO
per-pair query storm; `conflictMapFor` today is 3 batched queries + a pure
per-pair verdict, and the primitive MUST keep that shape):

```php
/**
 * Batched fact context — EVERYTHING the verdict needs, loaded ONCE per
 * consumer call: the active semester, its week bound, handled keys
 * (pending/approved requests), class_exceptions and holidays batch-loaded;
 * the verdict itself is pure in-memory.
 *
 * @return array{semester: ?Semester, weeks: int,
 *               handled: array<string, true>,            // "session:week" => true
 *               exceptions: array<string, ClassException>, // "session:week" => row
 *               holidays: array<int, array<int, string>>}  // week => day => label
 */
public function factContext(Collection $sessions): array

/**
 * The verdict primitive: the live conflict FACT for one <session, week>
 * pair, or null when the pair carries NO fact (a normal bookable
 * occurrence). $context optional: a ONE-shot pair builds a one-session
 * context internally (F1's action call); the week-loop consumers pass the
 * shared context from factContext().
 *
 * @return array{kind: 'exception'|'holiday'|'venue', reason: string}|null
 */
public function occurrenceFact(ClassSession $session, int $weekNumber, ?array $context = null): ?array

/**
 * The facts index for the timeline renderers: ONE factContext build,
 * "session:week" => fact for every live (unhandled) conflicted pair.
 *
 * @return array<string, array{kind: string, reason: string}>
 */
public function factsIndex(Collection $sessions): array

/** The week bound — week_count ?? 14, the catalog's single read. */
public static function weekBound(?Semester $semester = null): int
```

- Verdict precedence (byte-parity with today's shipped reason strings, in
  order): ① handled (`"session:week"` in handled) → `null` (a deliberate
  exclusion, not "no conflict"); ② a live exception at the pair →
  `['kind' => 'exception', 'reason' => 'Cancelled — ...']`; ③ a holiday on
  `<week, session.day_of_week>` → `['kind' => 'holiday', 'reason' => 'Public
  Holiday ('.$label.')']`; ④ the venue restriction (room_code B005 AND any
  D* cohort) → `['kind' => 'venue', 'reason' => 'Venue Restriction (B005
  no-Diploma)']` (week-independent but gated to 1 ≤ week ≤ weeks); else null.
- ONE re-implementation — the consumers collapse (signatures + returned
  shapes unchanged): `conflictFacts()`'s hand-rolled branch chain is DELETED
  (its body = one factContext + per-session occurrenceFact);
  `conflictMapFor` keeps its shape, ONE factContext + a pure week loop;
  `conflictedFor`/`build` sit unchanged on top.
- weekBound consolidation: `conflictMapFor`'s `$weeks`, `weekContext()`'s
  and `SubmitReplacementRequest`'s hard-coded `> 14` read `weekBound()`
  (the last `?? 14` outside the catalog is gone from the action). The
  timeline trait's `timelineWeeks()` stays untouched (same expression, a
  component-local render concern — noted so tasks don't over-edit).

## D-P1 — F1's loser shapes + copy (SubmitReplacementRequest)
The check sits with the OTHER pre-write losers (after ownership/week-range
`invalid_week`, BEFORE the slot lookup — and before
`ReplacementRequest::create`, so no compensation path is touched). The
codebase's loser mechanism = the flat `$fail()` return; NO exception:

```php
$fact = LectureSessionCatalog::occurrenceFact($session, $requestWeek);
if ($fact === null) {
    return $fail('occurrence_not_conflicted');
}
```

- The week range reads `weekBound()` (D-P0) instead of the literal 14.
- The polite copy lands in `ReplacementArrangement::conflictText()`'s
  existing map (the DisplayAction vocabulary, no new shape):
  `occurrence_not_conflicted` → "That week has no conflict to replace —
  pick an occurrence that needs a make-up (holiday, cancellation, venue
  restriction)."
- The impact table (frozen proposal) gains declarative rows:
  `app/Livewire/ReplacementArrangement.php::conflictText()` (the copy map)
  and `app/Services/LectureSessionCatalog.php` + the trait (the primitive +
  factsIndex) — soft-freeze additive.

## D-P2 — F2: the pin is mandatory (the fallback dies)
`ReplacementArrangement::submitBlocks`: `$requestWeek = $occurrenceWeek ?? $week;`
becomes:
```php
if ($occurrenceWeek === null) {
    // loser no_occurrence_pin: "Pick the slot on the grid (a pin) — the
    // week being replaced must be the conflicted occurrence's own."
    // POSITIONAL CONTRACT: the result row is PUSHED BEFORE the continue —
    // the client pairs results[i] with blocks[i], so skipping the push
    // shifts every later verdict onto the wrong block.
    $results[] = ['ok' => false, 'reason' => $this->conflictText('no_occurrence_pin')];
    continue;
}
$requestWeek = $occurrenceWeek;
```
The `?session=&week=` URL entries hydrate `window.pinnedOccurrence` (the
existing :3392 path) — the hydration IS the pin; no-pin = the user must
pick a grid cell first (:3159's pick re-pins). The server contract: the UI
path sends `{sessionId, week}` (the pin); the `{sessionId, occurrenceWeek}`
key stays for direct/legacy callers — NEITHER keys' absence reaches a
silent takeover.

## D-P3 — F3: the client gate (arrangement blade)
At the submit entry: when `window.pinnedOccurrence` is absent → block with
the same polite copy + focus the picker ("picker-first" message). The
existing re-pin paths stay (grid pick :3159; URL hydration :3392).

## D-P4 — F4: the Replace-Now truth (my-timetable) — EXACT-PAIR gate
- **Producer** (`ResolvesTimetableTimeline::buildEventsByWeek`'s week loop):
  while stamping each base event per (week, session), the event gains ITS
  OWN pair's verdict:

```php
$fact = app(LectureSessionCatalog::class)
    ->occurrenceFact($session, $w, $factContext);   // one batched context per render
$ev['occurrenceFact'] = $fact !== null
    ? ['week' => $w, 'kind' => $fact['kind'], 'reason' => $fact['reason']]   // the pair's week RIDES the stamp (the handoff reads fact.week — no undefined deref)
    : null;   // a normal occurrence → no button, no handoff
```

  (null for normal occurrences; one set of batched loads per render — the
  facts index, not a per-pair storm). The popup renders a button ONLY when
  the OPENED event itself carries a non-null `occurrenceFact`; the handoff
  uses THAT fact's week — the popup is never opened from a week whose
  occurrence carries no fact, so no cross-week synthesis exists.
- **Gate** (blade `openModal`): `replaceBtn.style.display = event.occurrenceFact ? 'flex' : 'none';`
  (the `days[event.di].holiday || event.status === 'conflict'` branch
  becomes DISPLAY ONLY — the loud-red day-flag rendering keeps reading
  `holidaysForJs()` verbatim; the GATE reads the stamp; BOTH bound to the
  same `week_count` — divergence impossible by construction).
- **Handoff** (blade :99): `goToReplacementFor(currentModalEvent?.id,
  currentModalEvent?.occurrenceFact?.week, 'my-timetable')` — strictly the
  STAMP's `week` field (the stamp only exists when a fact did, so the
  field is never undefined at click time; the gate hides the button for
  null stamps). `window.currentWeek` loses its last STALE-STATE reader;
  the :124–125 server-render initializer pair stays (declarative note —
  no cleanup task touches it).
- **D-P2's contextJson note**: the client UI path ALWAYS sends
  `{sessionId, week}` (pins); the `{sessionId, occurrenceWeek}` key stays
  for direct/legacy callers — NEITHER keys' absence bypasses D-P2's
  rejection.

## D-P5 — F5: the venue pass's original keys (VenueTimetable)
Two deltas in the reserved pass:

① `ReplacementRequest::with(...)` gains `classSession.venue` (the keys read
`$request->classSession->venue` — relation-unloaded today).

② the four keys ride the reserved-pass `$event = [...]` build (tokens only;
a venue-less session sends empty strings — never a read-on-null):

```php
'originalVenue' => $session->venue !== null ? (string) $session->venue->room_code : '',
'originalVenueName' => $session->venue !== null ? (string) ($session->venue->room_name ?? '') : '',
'originalStartTime' => substr((string) $session->start_time, 0, 5),
'originalEndTime' => substr((string) $session->end_time, 0, 5),
```

(the trait's approved branch is the reference implementation;
ui-common's Original rows already consume these key names — no client
change).

## D-P6 — Route C repair (user-run ONLY)
The repair SQL lives verbatim in the run-file §8. This change's artifacts
REFERENCE it; no application code touches the demo DB; no `(67,14)` stamp
insert happens in the script (the holiday row IS the true fact; the redo
live approve via the fixed UI stamps it).

## D-P7 — render readers need NO change
Every consumer of `week_number` (the timeline move branch, the queue's
decided bucket, the venue full-run states, the student ReplacementHistory,
the mapper's classDate) keys the REQUEST row's week — correct under
truthful pins; they get NO edits. State it so tasks don't over-edit.

## D-P8 — tests (F6)
`tests/Feature/OccurrencePinTest.php`:
1. no-pin `submitBlocks` → per-block rejected `no_occurrence_pin` (no
   reservation, no audit-written-as... the submit never reaches the action);
2. pin on a normal week → `occurrence_not_conflicted` (+ no audit, no
   reserve) — the pin targets a slot that WOULD otherwise be bookable
   (available slot, valid span) so the test isolates the fact loser, not
   `invalid_span`/`slot_not_available`;
3. pin on the holiday week (session 67 fixture, W14) → accepted (the
   request row + junction land; week_number = 14 — the receipt);
4. positive control: approve (the PL flip) → `class_exceptions` stamps
   `(67,14)`; freeing removes NOTHING (no W14 rows exist — the shipment
   contract);
5. F5's four keys on the venue events payload — for BOTH statuses with one
   shape assertion (the reserved pass builds the keys identically apart from
   status/remarks): APPROVED renders `status 'replacement'` + remarks = the
   original date; PENDING renders `status 'pending'` + the legacy tip; BOTH
   carry originalVenue/Name/StartTime/EndTime (the keys are
   status-independent by construction) — assert the pair, so a one-brancher
   drift breaks the test.
Fixture: the house `DatabaseMigrations + $this->seed()` pattern; the
LectureSessionCatalog fact primitive exercised through the REAL seed
(session 67 + the W14 holiday); the qv-arrange... NO — the
`replacement-home` page files are the CONCURRENT ZONE (qv fix) — the test
file touches NO blade; only the action + Livewire + catalog.

## D-P9 — page-changelogs
`my-timetable`, `replacement-arrangement`, `venue-timetable` (the F5 keys +
the venue popup's Original rows now served accurate from the payload).
