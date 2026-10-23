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
- [x] T7. `app/Livewire/VenueTimetable.php` — F5: the `with()` gains
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
- [x] T11. Changelogs — update the EXISTING files only:
  `my-timetable-changelog.md` (the true handoff + the gate),
  `replacement-arrangement-changelog.md` (the pin-mandatory + the loser
  copies), `venue-timetable-ui-changelog.md` (F5); + the arrangement's
  stale-cache ritual before verifying the client gate.
- [x] T12. Gates per slice 12 (F1+F2 → F3 → F4 → F5 → F6): `composer run lint:check` +
  `vendor/bin/phpstan analyse --memory-limit=1G` + phpunit (222 + new) +
  the Playwright in-scope rows; NO git push; the demo DB untouched
  (the Route-C script stays user-run, run-file §8).

- [x] (STANDING, no code): D-P7's non-edit covenant holds — no consumer of a request's `week_number` gets edited: the trait's move branch, the queue's decided bucket, the venue full-run states, ReplacementHistory, the mapper's classDate.
- [x] T13 (post-apply user round, 2026-10-19/20): the popup-accuracy riders — the timeline pending-branch's `requestedAt` renders MYT (the mapper's `stampDisplay`), and `VenueTimetable`'s reserved-pass events gain `requestedAt` (MYT) + `requestedBy` (the proposer eager-loaded). Verified live on the #13 redo: the venue popup shows `20 Oct 2026, 12:48 PM / Surayaini Binti Basri`; the cohort page's pending row shows the same MYT form.
- [x] T14 (post-apply gates): withQueue-appropriate spot gates on the rider files (phpstan 0, php -l) — the FULL gates re-run with T12's F6 suite before /sdd-verify.
- [x] T15 (post-apply user round 2, CHOICE A landing-week coexistence): the trait's landing-week natural-block suppression DIES — (a) the in-loop `$approved` lookup/skip deleted (the D7 stamp's exceptionKeys guard alone suppresses the REPLACED week), (b) the pending branch renders BOTH (the class's own natural block + the amber at the landing cell; the same-hour overlap resolves by the slotMap's last-write-wins = the amber wins). User ruling: every week a class meets keeps its natural block; proposals/receipts live in their own cells.
- [x] T16 (test deltas): pin the W5-both-blocks shape (the natural Fri cell + the landing cell) + re-key any landing-week suppression rows to the stamped-week contract.
- [x] T17 (changelogs + gates): my-timetable + cohort-timetable dated entries; the gates per slice + the F6 suite re-run.
- [x] T18 (the 2026-10-20 user round): the arrangement dd's subject switch AUTO-PINS the earliest conflicted occurrence (the same facts the picker would surface; the picker-first ask survives for the no-conflict subjects) + the grid auto-navigates.
- [x] T19: the Conflict Schedule strip's 'undefined' day-name fix (the real-pick slot objects lack weekdayName — the strip re-derives from the grid's day row by slot-week/index).
- [x] T20 (the 2026-10-20 user round, the venue PH-day rendering): the venue page's events payload gains the DERIVED holiday-conflict layer — for each week's PH day the recurring sessions whose `day_of_week` matches the PH's day AND whose venue = the viewed venue build synthetic rows (status 'conflict', the holiday reason) — the same derivation the timeline trait already does. De-dupe vs the imported rows by session id + slot span (the imported row WINS for its payload richness; no double-blocks). SHIPPED `968b10e` (the derived pass) + `a6f34a4` (the half-hour fix: the end re-keys to the 0-based inclusive-cell convention).
- [x] T21: the cellRender PH branch: OTHERS' PH-day events render `event-public-holiday` (the 0.5px red hairline; the full event-block container) — only OWN stays the loud 3px `event-conflict`; Sundays keep the frozen hide-all. SHIPPED `968b10e`.
- [x] T22: the summary re-audit — the Occupied bucket counts the surfaced family (the visible blocks == the counted blocks). Tests: 5770's own AMCS2093(L) @ B110 W8-Mon renders as the OWN 3px red; a B009 W14-Wed others-family row renders as the red hairline (+ no dupes); Sundays stay hidden. Changelog rows; the standing guards (never TC70; no demo-DB writes). SHIPPED `968b10e` + `5dfc8d1` (the duplicate array_fill wiped the derived counts — the Occupied card reads the visible 26).
- [x] T22b (the live receipts 2026-10-20): B106's W14-Wed renders `BBBE1033(T)` + `AMMS1623(T)` blocks as the OTHERS' family (the red hairline) — the derived pass built them from the recurring rows; B009's W14-Wed renders BMIT2203/BMIT1173×2/BMCS1053 + W14-Thu BMCS2053 (all red hairlines); B110's W8-Mon renders 5770's own AMCS2093(L) at the OWN 3px family; Sundays stay hidden all round; zero demo-DB writes.
- [x] T23 (the grid-overflow fix, user-diagnosed + user-approved 2026-10-20): the cellRender's PH branch gains `!(info && info.occupied)` — continuation cells fall through to the display:none path regardless of ownership; holiday rows total hours.length again; the blocks realign (all three ownership cases covered in one conjunct — the user's own diagnosis). Verified live: B110/W8 + B009/W14 — every row = 23 = the header; the blocks carry the correct hour/columns. The commit stays file-scoped (the blade only).
