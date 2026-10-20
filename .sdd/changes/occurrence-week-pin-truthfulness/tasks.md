# Tasks — occurrence-week-pin-truthfulness

- [x] T1. `app/Services/LectureSessionCatalog.php` — the D-P0 primitive:
  `factContext(Collection $sessions)`, `occurrenceFact(ClassSession $session, int
  $weekNumber, ?array $context = null)` (the verdict precedence: handled →
  exception → holiday → venue → null; byte-parity reason strings),
  `factsIndex(Collection $sessions)`, `weekBound(?Semester $semester)`; collapse
  `conflictFacts()`/`conflictMapFor()` onto it (signatures + return shapes
  byte-identical; their branch chains deleted); `weekContext()` reads weekBound. factsIndex carries a one-line comment
  ("render-ready index; consumers attach in a later change") so a future
  dead-code purge never deletes a frozen-API member by mistake.
- [x] T2. `app/Actions/SubmitReplacementRequest.php` — the flat
  `$fail('occurrence_not_conflicted')` loser with its order FIXED:
  ownership → `invalid_week` → `invalid_span` → the fact loser (pure
  bounds checks never trail a query-bearing build; the loser still sits
  BEFORE the TimeSlot lookup and BEFORE create); the week-range literal
  becomes `LectureSessionCatalog::weekBound()` — the last `?? 14` outside
  the catalog is gone from the action.
- [x] T3. `app/Livewire/ReplacementArrangement.php` — `submitBlocks`: the
  absent-$occurrenceWeek guard pushes the per-block loser row
  (`conflictText('no_occurrence_pin')`) BEFORE continue; `$requestWeek =
  $occurrenceWeek`; `conflictText()`'s map gains both new loser copies
  (occurrence_not_conflicted + no_occurrence_pin) with the design's exact
  sentences.
- [x] T4. `resources/views/livewire/replacement-arrangement.blade.php` — the
  client gate: the submit path blocks when `window.pinnedOccurrence` is absent
  (picker-first polite message; the same copy as the server's
  no_occurrence_pin); the pick (:3159) + the URL hydration (:3392) stay.
- [x] T5. `app/Concerns/ResolvesTimetableTimeline.php` `buildEventsByWeek` —
  the week-loop stamp: `$fact = app(LectureSessionCatalog::class)->occurrenceFact(...)`
  (INSTANCE-style — phpstan) inside the loop AFTER the exception-skip
  continue; `$ev['occurrenceFact']` = the pair stamp
  `['week' => w, 'kind', 'reason']` or null (one batched context per render);
  NOTE: the exception kind's pairs yield NO rendered event (the loop skips
  them) — their fact is verified at the catalog/PHP level (T8), never via a
  Playwright button hunt.
- [x] T6. `resources/views/livewire/my-timetable.blade.php` — `openModal`'s
  gate + :99's handoff switch to the stamp (`event.occurrenceFact`; the
  handoff reads `.week`); the day-flag branch stays DISPLAY ONLY
  (holidaysForJs verbatim — no new button title/copy).
- [ ] T7. `app/Livewire/VenueTimetable.php` — F5: the `with()` gains
  `classSession.venue`; the reserved-pass event gains the four original*
  keys with the venue-less guards (both statuses by construction).
- [x] T8. `tests/Feature/OccurrencePinTest.php` (new) — D-P8's rows: S1
  no-pin with TWO blocks (unpinned first, one pinned second) → the per-block
  loser lands at its OWN index (the results[i]↔blocks[i] positional
  contract); S2 normal-week pin on a WOULD-be-bookable slot; S3
  holiday-week accept (the AMSE2003-style fixture keyed by SHAPE, assert
  the qualities); S4 the same submit without a pin; S6 the approve chain →
  the D3 stamp of the original week + the no-free control; S8's
  seeder-premise assert.
- [x] T9. `tests/venue-timetable.spec.ts`/`LtVenueCapacityRuleTest`-style PHP
  row for the F5 keys (or a row in OccurrencePinTest) — both statuses' keys
  asserted (approved + pending); never assert "T not in LH" style data laws.
- [x] T10. Playwright: EXACT-PAIR gate + handoff coverage — S5's BOTH modes
  (the W14-fact-only event shows the button + hands off `?session=<id>&week=<fact
  week>`; the same class on a W5 view shows NONE), and S7's page-side fact
  kinds (holiday + venue kind buttons; the exception kind is asserted at the
  PHP level because the week loop's exception pairs SKIP stamping — put the
  one-line note in T5 so nobody hunts a button that cannot exist); the
  arrangement's no-pin client gate blocked-submit row; always
  `--grep-invert "TC70"` (T12 too).
- [ ] T11. Changelogs — update the EXISTING files only:
  `my-timetable-changelog.md` (the true handoff + the gate),
  `replacement-arrangement-changelog.md` (the pin-mandatory + the loser
  copies), `venue-timetable-ui-changelog.md` (F5); + the arrangement's
  stale-cache ritual before verifying the client gate.
- [ ] T12. Gates per slice 12 (F1+F2 → F3 → F4 → F5 → F6): `composer run lint:check` +
  `vendor/bin/phpstan analyse --memory-limit=1G` + phpunit (222 + new) +
  the Playwright in-scope rows; NO git push; the demo DB untouched
  (the Route-C script stays user-run, run-file §8).

- [ ] (STANDING, no code): D-P7's non-edit covenant holds — no consumer of a request's `week_number` gets edited: the trait's move branch, the queue's decided bucket, the venue full-run states, ReplacementHistory, the mapper's classDate.
