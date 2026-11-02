# Tasks — venue-status-parity

Each task ≈ ≤2 h. Order matters: T2 must precede T4 (the venue button is only
verifiable once the pair renders).

## T1 — hoist + batch the fact context (`app/Livewire/VenueTimetable.php`)
- [x] move `$recurring` above the coalescing loop (no eager-load change)
- [x] `$factContext = app(LectureSessionCatalog::class)->factContext($recurring)`
- [x] `$pendingPairs` / `$approvedPairs` from one `ReplacementRequest` read
- [x] `$catalog` held once per render

## T2 — status law in the coalescing emit loop (bugs #2, #8, #11)
- [x] `released` (`exception && approved`) → emit nothing
- [x] `cancelled` (`exception && !approved`) → `status='cancelled'` + `cancelledReason`
- [x] additive keys: `proposed`, `occurrenceFact` on every natural/derived block
- [x] `occupied` accrual untouched (the user's A: still counted)

## T3 — derived-holiday law (bug #3)
- [x] suppress **only** on `exception && approved`
- [x] a pending pair renders `conflict` + `proposed = true`
- [x] cancelled pair renders `cancelled`
- [x] keep the de-dupe/covered check and the `occupied` accrual

## T4 — venue blade (bugs #1, #2, #4, #7, #12)
- [x] notes via `buildReplacementNote(e)`
- [x] class chain: `conflict || cancelled` → `event-conflict` / `event-public-holiday`
- [x] `Replace Now` = my-timetable's `occurrenceFact` gate (with the served-array re-read)
- [x] no `CancelClass.renderButton` on a cancelled class

## T5 — reserved pass (bug #6)
- [x] add `'requestId' => (int) $request->id`

## T6 — `Class Ref` identity (bug #5)
- [x] `ui-common.js` `openClassModal()`: prefer `event.sessionId`
- [x] check `/my-timetable-ui` + `/cohort-timetable` popups unchanged (they carry `id` only)

## T7 — `statusDesc` cancelled arm (bug #10)
- [x] one ternary arm in `ui-common.js`

## T8 — summary cards (bug #9)
- [x] `split('sumTotal', t.total, t.pending || 0)`; `Available` stays `set(...)`

## T9 — cohort `Replace Now` gate (bug #4)
- [x] port my-timetable's gate; drop the hand-rolled `isConflict`

## T10 — receipts
- [x] PHPUnit: cancelled / proposed-holiday / occurrenceFact / requestId / sessionId
- [x] NEW read-only `tests/venue-status-parity.spec.ts` (demo DB, 6 assertions)
- [x] full suite + pint + PHPStan + `node --check`

## T11 — changelogs + close-out
- [x] `venue-timetable-ui-changelog.md`, `cohort-timetable-changelog.md`, `my-timetable-changelog.md`
- [x] `sdd.yaml` → `applied`; handover §0 line
