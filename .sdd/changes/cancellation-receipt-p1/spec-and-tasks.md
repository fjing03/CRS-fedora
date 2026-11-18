# P1 — Return the receipt from single-class cancellation

Scope approved: P1 only. The current receipt lookup is redundant; a miss was not reproduced. The singleton batch already returns the written exception ID. Reuse it rather than reading the exception again after success.

## Design and acceptance

- Add `CancelClass::cancelWithReceipt(): ?int`; keep the boolean callable as a wrapper. Validation and the singleton batch remain on one path with `enforceWindow: false`. `null` means no cancellation; success returns the existing integer `cancelled[0]['exception_id']`, asserting that contract, not adding a cast to satisfy PHPStan. No new write, transaction, or response shape.
- `CancelsClasses::cancelClass()` normal branch uses the returned ID directly as `exceptionId`. Preserve `This class is already cancelled.` and leave approved-landing and day paths untouched.
- In `tests/Feature/CancelClassReceiptTest.php`, use `actingAs` and a direct component invocation so a Livewire render does not pollute the query trace. Verify the returned ID identifies the only row for that occurrence and that repeat cancellation is refused with one row remaining. Check for no `class_exceptions` select after the insert; pre-insert duplicate checks stay. Red is the redundant query, not a reproduced crash.
- Test only on a new socket-only PostgreSQL cluster with `DB_HOST=<socket directory>` and `DB_DATABASE=class_replacement_testing`. Verify database, socket transport, port and data directory through Laravel before each run. Plain migrations and transaction-scoped fixtures only; no resets/reseeding, no app server changes. Lint and the normal level-7 PHPStan gate are checks, not receipt proof. No commit/push.

## Tasks

- [x] One independent read-only review (OpenCode `sdd-reviewer`): PASS, five clarifications folded in; frozen before application edits. Raw review: `/tmp/crs-cancellation-receipt-p1-oc.log`.
- [x] Verified in the socket-only cluster at `/tmp/crs-p1-pg.Isi5sbvl` (port 55487): red on the exact post-insert receipt `SELECT`; green 1 test / 9 assertions. `composer run lint:check` passed; `composer types:check -- --memory-limit=1G` reported 0 errors. `git diff --check` passed. Fixtures rolled back (0 exception rows left); isolated cluster stopped. No protected DB or app-server changes.
- [x] Manual sign-off: user replied `p1 ok`. The Week 10 AMSE1003 occurrence (session #36) showed Class Cancelled / Need to Arrange, and the pop-up offered Arrange Replacement instead of Cancel Class. A read-only query confirmed exactly one cancellation record in `class_replacement_fresh`. Repeat refusal was verified by the automated test, not the browser. P2 was not started.
