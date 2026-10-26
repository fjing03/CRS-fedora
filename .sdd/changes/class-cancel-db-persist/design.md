# design — `class-cancel-db-persist`

Reads against the frozen proposal (the two review rounds folded). All
anchors verified 2026-10-27 (tree at `87069fa` + the doc commits); the
implementer re-greps.

## D1 — `CancelClassBridge` (NEW `app/Livewire/Concerns/CancelClassBridge.php`)

```php
namespace App\Livewire\Concerns;

use App\Actions\CancelClass;
use App\Http\Requests\CancelClassRequest;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Renderless;

trait CancelClassBridge
{
    /* POST the cancel. The ownership-scoped resolve mirrors the
       submitBlocks :513 receipt — a fabricated id resolves to null. */
    #[Renderless]
    public function cancelClass(
        string $sessionId, int $weekNumber, string $reason,
        ?string $otherDetail = null,
    ): array {
        $session = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->find((int) $sessionId);
        if ($session === null || ! $this->validReason($reason)) {
            return ['ok' => false, 'hint' => 'Class not found among your classes.'];
        }
        if (! CancelClassRequest::validReason($reason)) {   // the enum law
            return ['ok' => false, 'hint' => 'Please select a cancellation reason.'];
        }
        $ok = (new CancelClass)(
            auth()->user(), $session, $weekNumber, $reason, $otherDetail);
        if (! $ok) {
            return ['ok' => false,
                    'hint' => 'This class is already cancelled.'];
        }
        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', $weekNumber)
            ->first();
        return ['ok' => true, 'exceptionId' => (int) $exception->id];
    }

    /* The true inverse (user law Q3 (a)): reads the row BEFORE the delete
       so the audit records the reason it erased; the ownership guard via
       the session join; the symmetric audit action. */
    #[Renderless]
    public function cancelClassUndo(string $sessionId, int $weekNumber): array
    {
        $session = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->find((int) $sessionId);
        if ($session === null) return ['ok' => false];
        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', $weekNumber)
            ->first();
        if ($exception === null) return ['ok' => false];
        $erasedReason = (string) $exception->reason;
        DB::transaction(function () use ($exception, $session, $weekNumber, $erasedReason): void {
            $exception->delete();
            AuditLog::create([                 // no deserted audit row
                'user_id' => auth()->id(),
                'action' => 'class_cancel_undone',
                'details' => ['class_session_id' => $session->id,
                              'week_number' => $weekNumber,
                              'reason' => $erasedReason],
            ]);
        });
        return ['ok' => true];
    }
}
```
- the reason whitelist lives in `CancelClass::REASONS` (widened to 10 in
  D2) — `validReason` calls `CancelClass::validReason($reason)`-shaped
  (the implementer: a tiny static bool on the Action, not a copy).
- the `AuditLog` import rides the trait's use block.
- both methods `#[Renderless]` (the :500 morph-wipe lesson).

## D2 — the Action + the Request (the enum + the detail)

1. `app/Actions/CancelClass.php`:
   - `REASONS` widens 5→10 (add `hospitalisation_leave`,
     `maternity_leave`, `compassionate_leave`, `paternity_leave`,
     `other`); the docblock "one of the 5 schema CHECK values" re-keys;
   - the signature: `__invoke(..., ?string $otherDetail = null)` — the
     AuditLog `details['other_detail'] = $otherDetail` when non-null;
   - the ownership guard, the transaction, the unique-violation receipt
     byte-stay (the reviewer-verified internals).
2. `app/Http/Requests/CancelClassRequest.php`:
   - the rules list mirrors the 10; add
     `public static function validReason(string $r): bool` (the trait's
     delegating target);
   - 'other' does NOT require the detail server-side? — REFINED: the
     detail rides the AUDIT (not the reason); a rule `required_with` is
     wrong (the enum isn't a data-shape pairing); the CLIENT gating
     (`mustDescribe` when value==='other') already enforces the user's
     must-describe ask; the server accepts 'other' with or without the
     detail (an empty-detail cancel is legal — the reason IS the data).
3. `tests/Feature/ReplacementFlowTest.php` — the :356 failure message
   "one of the 5 CHECK values" re-keys to "one of the 10 CHECK values"
   (a comment-only edit; the test bodies stay green).

## D3 — the three + one components (the use lines)

`MyTimetable` / `CohortTimetable` / `VenueTimetable` / `ReplacementHome`
each: `use App\Livewire\Concerns\CancelClassBridge;` + the `use
CancelClassBridge;` body line. Verified (review round-1): no nested
`<livewire:…>` components on these pages — `window.Livewire.first()` is
the page component itself, so the JS `call()` hits the trait from any of
the four pages.

## D4 — the client (ui-common.js)

1. **The reason list** — `ClassCancellation.REASONS` becomes
   `[{value:'medical_leave', label:'Medical/Sick Leave'}, … + {value:'other',
   label:'Other'}]` — 8 pairs; the radio build (:4307) mirrors
   value/label; `validate()` re-keys:
   - `REASONS.some(r => r.value === reason)` replaces `indexOf`;
   - `'other'` replaces `'Other'` in the must-describe branch;
   - `CancelClassModal._refreshGate`'s `reason === 'Other'` (:4376)
     re-keys on `'other'` (the Yes gate — the round-2's heaviest find).
2. **The confirm await** (`CancelClassModal.confirm` :4020-area):
   - the Yes button pending-disables FIRST (the double-fire guard);
   - `const res = await window.Livewire.first().call('cancelClass',
     String(event.id ?? event.sessionId), weekIndex + 1, reason, detail);`
   - `res.ok === true` → the EXISTING client mutation + the ledger —
     the entry gains `exceptionId: res.exceptionId` (the undo's handle);
     the row append, the strip, the toast all byte-continue;
   - `res.ok === false` → the modal shows `res.hint` (the same
     `#cancelReasonHint` node), NO flip, NO ledger;
   - the SERVER response is the truth — the client's own re-check never
     overrides it (the midnight-roll folded receipt).
3. **`undoViaServer(entry)`** (the NEW single-homed ASYNC call —
   review-round-2 receipt):
   ```js
   async function undoViaServer(entry) {
       const res = await window.Livewire.first().call('cancelClassUndo',
           String(entry.matchKey.sessionId),
           entry.matchKey.week + 1);
       ...
   }
   ```
   - the response contract `{ok, hint?}`: `res.ok === true` → the
     EXISTING client reversal (`ClassCancellation.undo(entry)` bodies);
     `false` → 'Class restore failed — refresh to see the truth', NO
     reversal, NO navigation;
   - **the toast callback is RESTRUCTURED to await BEFORE the
     cross-page deep-link** (review 🟡-5 receipt: `showUndoToast`'s
     callback at :4159 navigates away synchronously — the browser
     navigates MID-AWAIT and kills the undo). The callback wraps in
     an async IIFE: disables the toast's Undo affordance, awaits
     `undoViaServer`, and only THEN the deep-link
     `?week=<wk>&restored=1` (my-timetable stays in place + rebuilds);
     the ⛔ caveat documented: the USER navigating away themselves
     mid-await loses the toast — acceptable (the undo is reachable
     again on next load);
   - the home's `undoCancelledByRowId` (replacement-home.blade.php
     :477): the Undo button pending-disables, awaits, the existing
     body, re-enables;
   - the trait wraps delete-then-audit in `DB::transaction` (the
     cancel-Action's pattern — no deserted audit row).
4. **The home badge/filter** (replacement-home.blade.php):
   - `badgeClass` gains the 5 entries (the mapping in the proposal's
     contract section — tokens only, the CSS lives in the blade's
     `:20-47` badge block);
   - the titleCase law extends inline (the STR map + 5 receipt lines);
   - the filter dropdown (:353) is enum-internal (no edit needed — the
     values ride the same map; verify at apply).

## D5 — the migration + the demo-DB sheet (the USER runs the SQL)

NEW migration `*_widen_cancel_reason_and_audit_checks.php`:
- `up()`: the reasons CHECK DROP/ADD (10), the audit action CHECK
  DROP/ADD (8) — the same statement pairs as the proposal's sheet;
- `down()`: the narrowing HAZARD comment (the `2026_08_24_000001`:63
  precedent) mirrored verbatim; the down() body KEEPS the wide CHECKs
  and only logs — the frozen proposal's stance (a real-usage DB cannot
  narrow without data loss; fresh DBs reach the 10-valued state via the
  migration sequence). The blade-shaped "restores the ORIGINAL 5-list"
  clause from the earlier draft is STRUCK (the review's contradiction).

## D6 — the gates

- NO new Playwright spec; NO battery run (the user tests manually).
- `pint` + `vendor/bin/phpstan analyse --memory-limit=1G` (PHP touched);
  `node --check public/js/ui-common.js`.
- phpunit: the standing suite must stay 258-green (the Action's tests
  ride `CancelClassRequest` + the widened REASONS — no breakage
  expected; the :356 message edit is comment-only).
- `tests/cancel-class.spec.ts` + `e2e/*.spec.js` — the DECLARATIVE
  re-keys listed in the proposal's Scope (the enum-typed locators);
  NOT RUN (the user's regime).
- The four page changelogs.
- ONE conventional commit (`feat:`); NEVER push.

---
## Implementer deltas (applied 2026-10-27 — recorded for the verify stage)
1. **The trait's home: `app/Concerns/CancelsClasses.php`** (namespace
   `App\Concerns`, name `CancelsClasses`) — the repo's existing convention
   (the `ResolvesTimetableTimeline` precedent) beats the design draft's
   `app/Livewire/Concerns/CancelClassBridge.php`. Same method names.
2. **`conflictReason` carries the LABEL, not the raw enum** (the frozen
   contract's INTENT kept: "the modal label EQUALS the home badge"):
   - the server already emits titleCase'd labels (ReplacementHome + the
     `/requests/conflicts` ApiReadController both had their own maps);
   - `rowFromEvent` now calls `ClassCancellation.labelOf(reason)` —
     `medical_leave` → 'Medical/Sick Leave' (Sick), etc.; unknown values
     pass through;
   - `ReplacementHome::titleCase` + ApiReadController `$reasonMap`
     re-key medical_leave → 'Medical/Sick Leave', `other` → 'Other'
     (the drift receipt retired at BOTH producers);
   - the ledger's `reason` stays the ENUM (the server handle's
     vocabulary) — validated by the re-keyed :243 spec assertion.
3. **`undoViaServer` reads `entry.sessionId`** — the design snippet's
   `entry.matchKey.sessionId` never existed; cancel() stamps
   `sessionId` (from `event.sessionId ?? event.id`) + `exceptionId: null`
   on the entry, the response stamps the id, ledgerUpdate persists.
4. **The undo audit** records the FULL erased row (`$exception->toArray()`
   — superset of the design's three keys).
5. **`ApiReadController.php` added to the touched set** (its own secret
   copy of the label map was re-keyed + widened to 10) — the e2e
   valid-reason arrays re-keyed to the 10 labels accordingly.
