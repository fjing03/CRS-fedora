# verify — class-cancel-db-persist (2026-10-27, tree at 529f971)

## ✅ Tasks
5/5 complete — all tasks marked `[x]` in tasks.md.

## ✅ Scope match (each row code-verified)
| Scope row | Receipt |
|---|---|
| `app/Actions/CancelClass.php` | REASONS = exactly **10** values; `?string $otherDetail = null` rides `details['other_detail']` when non-null; internal transaction/unique-violation receipt untouched |
| `app/Http/Requests/CancelClassRequest.php` | `self::REASONS = CancelClass::REASONS` (single source) + `validReason()` delegating; `rules()` intact (`in:` rides the widened const) |
| `app/Concerns/CancelsClasses.php` (NEW — the delta'd home) | `#[Renderless]` ×2, ownership-scoped resolve ×2, response arrays `{ok,hint?}`/`{ok,exceptionId?}`/`{ok}`; undo = read-row-then `DB::transaction` (delete+audit `class_cancel_undone`, details = the full erased row) |
| 4 components | `use CancelsClasses;` present in MyTimetable / CohortTimetable / VenueTimetable / ReplacementHome |
| migration | the two CHECK widenings verbatim; `down()` = the logging DECLINE (D5 stance honoured) |
| `public/js/ui-common.js` | the 8 `{value,label}` pairs; radios carry `reason.value`; `REASONS.some` re-key; the `'other'` literals re-keyed exactly where correct (validate + _refreshGate; the word 'Other' survives only as the label string + hint text) |
| `tests/cancel-class.spec.ts` | 5 radio locators → `value="medical_leave"` + the `'other'` radio; the seed fixture re-keyed (entry.reason = the enum; row.conflictReason = the label); the live :243 ledger assertion re-keyed to `'medical_leave'`; declarative only — NO battery run per the user's law |
| `tests/e2e/full-suite.spec.js` / `api-wiring.spec.js` | the 10-label vocabulary lists |
| `resources/views/livewire/replacement-home.blade.php` | `badgeClass` map = 11 entries (5 new + the legacy `'Medical Leave'` alias); the badge CSS block duplicates — the `:20-47` block only; `undoCancelledByRowId` awaits + pending-disables the chip's Undo button |
| `docs/demo-db-data-changes.md` | the 2026-10-27 entry pasted (the user still runs the SQL — the pending handoff) |
| 4 changelogs | dated entries written |

## ✅ Design fidelity
- D1/D2/D3 byte-match (established above).
- D4 confirm-await: the Yes pending-disables FIRST → the preflight re-check → `window.Livewire.first().call('cancelClass', sessionId, week+1, reason, otherDetail)` → `res.ok !== true` keeps the modal open with the honest hint and NO client flip/ledger; ok → `ClassCancellation.cancel()` applies the existing mutation and the SAME `out.entry` object gains `exceptionId` via `ledgerUpdate` — JS reference semantics keep `ctx.entry` and the ledger entry identical (the undo's handle rides correctly).
- D4 undo-await: `undoViaServer` is single-homed (3 references in ui-common.js = its definition + the toast callback + the week-resolution receipt) and the home chip calls it through `await` in `undoCancelledByRowId`; the toast callback's async IIFE awaits BEFORE the deep-link (`?week=&restored=1` / the in-place rebuild) — the browser-navigates-mid-await hazard is fixed as designed.
- D5 down() = the narrowing-HAZARD decline (verified in-file).
- D6 gates: pint ✅ · phpstan 0 ✅ · phpunit **258/258** ✅ · `node --check` ✅ · no Playwright run ✅ · ONE `feat:` commit ✅ · NOT pushed ✅.

## 🟡 Documented deviations (deliberate, recorded in design.md "Implementer deltas")
1. Trait home = `App\Concerns\CancelsClasses` (the repo's namespace-law beats the
   draft's `App\Livewire\Concerns`) — method names unchanged; the discoverability receipt
   (`window.Livewire.first()`, verified real in the Livewire v4 bundle) holds.
2. `conflictReason` carries the LABEL (+ the `labelOf` law) — the contract's intent ("the
   modal label EQUALS the home badge") vs its letter ("raw enum"); the ledger keeps the enum.
3. `ApiReadController.php` joined the touched set (its own `$reasonMap` re-keyed to the label law + widened to 10) — an honest scope addition (a third copy of the label map would otherwise have kept the drift alive), flagged in the deltas postscript and committed with the lane.
4. The undo audit records the FULL erased row via `$exception->toArray()` — a superset of the design's 3 keys.
## 💡 Suggestions (non-blocking)
1. **Legacy ledger entries predating the bridge** (a pre-refresh tab's sessionStorage entries
   lack `sessionId`) — `undoViaServer` returns `ok:false` + 'Class restore failed — refresh to
   see the truth.' which is honest but never reversible. A chipped off `!sessionId → plain
   client undo` fallback would soften it; the design truth says no, "a mock-only undo is
   never the code's promise". Acceptable; the refresh path resolves it.
2. The stale-serve ritual (`pkill -f "[a]rtisan serve"` → `rm -f storage/framework/views/*.php` → `php artisan serve --port=8000`) is the user's manual-testing pre-step — the home Blade was touched, so run it before verifying visually.

## ⚖️ Verdict
**PASS** — every scope row implemented, design fidelity holds (the 4 documented deltas
preserve the frozen intent: server-truth, one shared bridge, the undo as the true inverse,
byte-identical visuals), and all standing gates are green. The only open operational item is
the USER's demo-DB ALTER paste (`docs/demo-db-data-changes.md`, 2026-10-27 entry) — without
it the confirm's server call fails the CHECK and writes nothing on the demo DB.

Ready to archive with `/sdd-archive class-cancel-db-persist` — do so AFTER the user
tests the flow manually and pastes the ALTER sheet (the lane's own acceptance clause).
