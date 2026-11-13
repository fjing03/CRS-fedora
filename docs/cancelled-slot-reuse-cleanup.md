# Cancelled-slot reuse — existing-data cleanup & recovery runbook

Change: `cancelled-slot-reuse`, tasks C01–C03 (design D9, specs S26–S30).
Status: **code + real testing rehearsal completed (2026-11-13). No live cleanup has run, no demo database
was written, no scheduler/controller registration exists, and no recovery tool ships in the CLI.**

This runbook is written for the operator (the user/main session), not for automated use. Every
command below is meant to be read and run deliberately, one at a time, with the printed output
checked at each step.

---

## 0. Hard boundaries (read first)

1. **Dry-run is the default and writes NOTHING** — no audit rows, no notifications, no slot or
   exception writes. Only `--plan-output` writes a local plan JSON file.
2. **Apply is fail-closed.** It needs ALL of, together: `--apply`, `--confirm-database` (the exact
   live `current_database()` identity), `--plan` (the approved fingerprinted plan file with its
   approval block), `--snapshot` + `--snapshot-manifest` (verified dump + restore-list receipts),
   `--approval-reference` + `--approval-operator` (the separate write approval), `--operation-id`
   and `--actor-user-id` (an existing user id for the legal maintenance audits). Missing any one
   refuses before anything runs.
3. **Target allowlist.** Live apply: `class_replacement_fresh` ONLY. Rehearsals: explicitly
   confirmed `class_replacement_testing`. Viva, demo twins, `postgres`, anything else — refused.
   The confirmation must equal the actual `current_database()` byte-for-byte.
4. **`--env=testing` is forbidden.** No `.env.testing` exists — it silently boots the demo `.env`.
   Select the testing database ONLY with the environment variable:
   `DB_DATABASE=class_replacement_testing`. The command refuses outright — BEFORE reading
   `current_database()`, before the inventory, before any write — by inspecting the RAW global
   `--env` flag directly (Laravel only switches the environment file when `.env.<value>` EXISTS,
   so with no `.env.testing` file the forbidden flag silently keeps the demo `.env` and the file
   NAME alone can never catch it), and it additionally refuses an `.env.testing`-style environment
   file.
5. **Planning/apply-code approval is NOT cleanup-write approval.** The SDD apply gate (G1) that
   authorised writing this code does not authorise running it against the demo database. The
   exact live plan needs its own separate approval (G2), recorded as the approval reference and
   operator in the plan file's approval block and passed via the flags.
6. **Snapshot verification is NOT provenance.** The CLI verifies checksums and the target, but it
   cannot prove the `pg_restore --list` receipt came from that dump — that is the operator's
   recorded checklist below. Never present filename existence as verification.
7. **Port ≠ database.** The `:8030` lane serves the testing database, but a port number proves
   nothing: every step verifies the printed `current_database()` identity, not the port.
8. **No scheduler, no controller, no render call.** The facility is artisan-only
   (`classes:release-cancelled-slots`); recovery is a separately approved operator operation and
   is deliberately NOT exposed in the CLI.
9. **Code rollback never reactivates a class.** Deleting/reverting the cleanup code does not
   re-occupy cells; a rollback of the DATA is the separately approved recovery below.
10. **Recovery rehearsal helper is testing-only.** `App\Services\CancelledSlotCleanupRecovery`
    refuses any database other than `class_replacement_testing` (fail closed) and is not
    auto-invoked by anything.

---

## 1. Dry run (read-only inventory)

Run against the target you are inventorying. Default (no flags) is SELECT-only.

```bash
# Demo (fresh) inventory — read-only, safe:
php artisan classes:release-cancelled-slots

# Testing rehearsal inventory:
DB_DATABASE=class_replacement_testing php artisan classes:release-cancelled-slots \
  --plan-output=/tmp/cleanup-plan.json
```

The output prints, for every cancellation pair:

- the live `current_database()` identity (always first — check it before reading anything else);
- session/week/semester, the audit ids and the current exception with its reason;
- per-cell evidence: coordinates, slot id, classification (`original` / `available` /
  `request_held` / `missing` / `ambiguous`), owner, version, and — for included occurrences —
  the exact proposed after-state (`available`, owner NULL, version +1), the protected/available
  no-ops, and whole-occurrence exclusions with reasons;
- out-of-range rows carrying the source id (reported, never freed);
- the plan fingerprint (`sha256:…`), covering ALL scoped ownership inputs: session row, exception
  rows, every consulted audit (including undo and maintenance receipts), exact slot rows
  (duplicates and out-of-range rows included, with versions), active requests with full junction
  membership and anchor/span fallback facts. The current clock is never fingerprinted.

The plan file (`--plan-output`) is the SAME document plus its `fingerprint` and an informational
`generated_at` (excluded from the fingerprint by law). It is the artifact the approval binds to.

Inventory law (design D9): a pair needs a **non-maintenance `class_cancelled` audit for the exact
session/week AND a matching CURRENT exception**. Approval-only `official_event` stamps, this
facility's own maintenance receipts, and legacy `class_cancel_undone` rows are not source
evidence; contradictions (duplicate audits, undo alongside a live exception, missing/duplicated
exceptions, reason mismatch) exclude the whole occurrence and are reported, never repaired.

### Shared discriminator + lock seams (settled — no parallel copies)

Both parallel-lane helpers have landed and the cleanup side now delegates:

- **Evidence rule.** `App\Services\CancelledOccurrenceEvidence` is the SHARED
  generic evidence service: `pairFromDetails()` (the audit-details pair
  identity), `isMaintenanceDetails()` (truthy maintenance receipt) and
  `isUserCancellationAudit()` (exact `class_cancelled` action + a valid
  positive session id and week 1–14 + not maintenance).
  `App\Services\CancelledSlotCleanupEvidence` delegates all three to it —
  one rule for display and cleanup, never a second copy — and keeps only the
  cleanup-specific pair assembly and the stronger contradiction exclusions
  (duplicate audits, undo evidence, reason mismatch, missing/duplicated
  current exception, vanished session row). The current exception AND the
  audits stay mandatory for every pair.
- **Lock order.** The applier's D4 lock step calls the shared
  `App\Services\OccurrenceMutationLocks::lock()` helper INSIDE its own
  transaction (the helper never opens one): advisory locks → sessions →
  consulted requests → the full slot union, each ascending, with the plan's
  consulted request ids passed as the helper's extra request ids. The
  approved plan's full candidate union (original cells, duplicates,
  out-of-range rows, receipt runs, fallback anchors) must come back fully
  covered by the fresh plans — anything uncovered is ownership evidence the
  shared planner no longer reads, and the plan FAILS CLOSED instead of
  acquiring extra out-of-order locks. The fingerprint gate stays with the
  cleanup applier; the helper's re-plans never replace the mandatory fresh
  inventory compare.

---

## 2. Testing rehearsal (before any live plan)

Rehearse the full lifecycle on the testing database. Tests already cover this
(`tests/Feature/CancelledSlotCleanupTest.php`), and the manual rehearsal mirrors them:

```bash
# 1. inventory + plan file
DB_DATABASE=class_replacement_testing php artisan classes:release-cancelled-slots \
  --plan-output=/tmp/cleanup-testing-plan.json

# 2. add the approval block to the plan file (an operator step — the approval
#    binds reference + operator + the plan's own fingerprint):
#    "approval": {"reference": "...", "operator": "...", "plan_fingerprint": "<file fingerprint>"}

# 3. verified snapshot (see §3) → dump + restore-list receipt + manifest

# 4. apply the rehearsal
DB_DATABASE=class_replacement_testing php artisan classes:release-cancelled-slots \
  --apply --confirm-database=class_replacement_testing \
  --plan=/tmp/cleanup-testing-plan.json \
  --snapshot=<dump> --snapshot-manifest=<manifest> \
  --approval-reference=<ref> --approval-operator=<operator> \
  --operation-id=cleanup-testing-1 --actor-user-id=<existing user id>
```

Rehearse the refusal cases too (the test file does): missing gates, forbidden targets, tampered
snapshots, fingerprint drift (touch a scoped fact between plan and apply — the whole plan aborts
before any write), protected junction/anchor-span claims, excluded ambiguous occurrences, the
zero-change repeat (second inventory proposes nothing; repeat apply writes no audit and bumps no
version), and the recovery rehearsal (§5).

The rehearsal target must be selected exactly as shown. A command that starts without
`DB_DATABASE=class_replacement_testing` but is *supposed* to be a rehearsal is a mistake — stop
and re-read the printed target.

---

## 3. Verified snapshot (operator steps, recorded checklist)

The CLI never runs external backup commands (no shell, no injection). The operator produces and
verifies the snapshot and records it in a manifest the CLI then checks.

1. Dump (custom format only):

   ```bash
   pg_dump -Fc -d class_replacement_fresh -f /backups/crs-pre-cleanup-<date>.dump
   ```

2. Verify it is restorable and capture the receipt:

   ```bash
   pg_restore --list /backups/crs-pre-cleanup-<date>.dump > /backups/crs-pre-cleanup-<date>.restorelist.txt
   ```

   **Operator checklist (record next to the files):** the `pg_restore --list` output was produced
   BY THE OPERATOR from THAT dump file, listed the expected tables, and exited successfully. The
   CLI checks checksums only — this provenance step is yours and must be recorded, not implied.

3. Hash both files:

   ```bash
   sha256sum /backups/crs-pre-cleanup-<date>.dump /backups/crs-pre-cleanup-<date>.restorelist.txt
   ```

4. Write the manifest (the CLI reads exactly these keys):

   ```json
   {
     "dump_path": "/backups/crs-pre-cleanup-<date>.dump",
     "dump_sha256": "<dump checksum>",
     "restore_list_path": "/backups/crs-pre-cleanup-<date>.restorelist.txt",
     "restore_list_sha256": "<receipt checksum>",
     "target_database": "class_replacement_fresh",
     "captured_at": "2026-11-13T09:00:00+08:00"
   }
   ```

5. For the CURRENT plan (not an old one): the plan file must be generated AFTER the snapshot from
   the same database state you intend to change, and the approval must bind THAT plan's
   fingerprint. An old backup or a stale plan is never "already approved".

---

## 4. Apply (only after separate approval)

Order of gates, all fail-closed: environment-file guard → required options → target policy →
snapshot verification → plan structure → plan fingerprint → target binding → approval block
(reference + operator + fingerprint binding) → audit actor exists. Then, inside ONE transaction:
the shared D4 lock boundary (`OccurrenceMutationLocks` — advisory + sessions + consulted requests
+ the full slot union, each ascending, plus a fail-closed coverage check of the approved
candidate union), fresh re-inventory, fingerprint compare — **any drift, missing
planned identity or target change aborts the ENTIRE plan before a single write** and requires a
new dry-run and a new approval. Only approved, unambiguous, included occurrences change; ambiguous
ones are excluded as whole occurrences; protected rows stay byte-identical.

```bash
php artisan classes:release-cancelled-slots \
  --apply --confirm-database=class_replacement_fresh \
  --plan=<approved plan file> \
  --snapshot=<dump> --snapshot-manifest=<manifest> \
  --approval-reference=<G2 reference> --approval-operator=<approver> \
  --operation-id=cleanup-<date>-<n> --actor-user-id=<existing user id>
```

What apply writes: original cells → `available`, owner NULL, version +1 (exact before
status/owner/version conditions; a stale member aborts everything); ONE legal `class_cancelled`
maintenance audit per released occurrence with `details.maintenance = true`, the operation id,
the approval reference and the per-cell before/after/no-op receipt. **No notifications.** No
request, exception, junction, recurring-schedule or other-week edit.

After commit the command re-verifies every changed cell, protected field/link/version, exception,
recurring schedule and other week and prints the findings. A clean result ends with:

- the operation id and receipt summary;
- the instruction to append ONLY this operation's receipt to `docs/demo-db-data-changes.md`
  (never another session's rows, never blanket-staged);
- the zero-change repeat check: run a second dry-run — with no genuinely new cancellations it
  proposes nothing further, and a repeat apply writes no maintenance audit and bumps no version;
- the reminder that rollback/recovery is a SEPARATELY APPROVED operation (§5).

If post-commit verification reports findings: the write already committed — investigate, do not
re-apply, and consider the recovery path below with a fresh decision.

---

## 5. Recovery (pre-reuse restore — SEPARATELY APPROVED, never automatic)

A recovery before the released slots are reused restores ONLY receipt cells that are still
available, have the EXACT recorded post-version and null post-owner, and carry no live claim
(junction or junction-less anchor/span fallback). It increments the CURRENT version (an old
version is never restored) and preserves the cancellation exception, the maintenance audits and
all history: it re-fills cells, it does NOT undo the class cancellation.

**If ANY receipt cell has drifted or been reused — no partial restore.** Stop, report the affected
bookings, and ask the user for recovery instructions. Whole-DB snapshot restoration over newer
work and implicit reactivation of cancelled classes are forbidden.

There is no destructive recovery tool for production. The operator path is:

1. Extract the receipt cells from the operation's maintenance audit (`audit_logs`,
   `details.original_release.changed`).
2. Verify every receipt cell with plain SELECTs:

   ```sql
   -- for each changed cell: still available, null owner, exact post-version?
   SELECT id, status, class_session_id, version FROM time_slots WHERE id = <slot_id>;

   -- no live junction claim on it?
   SELECT r.id, r.status FROM replacement_request_slots l
     JOIN replacement_requests r ON r.id = l.replacement_request_id
    WHERE l.time_slot_id = <slot_id> AND r.status IN ('pending','approved');

   -- no live junction-less anchor/span claim opening at/before it in the same room/week/day?
   SELECT r.id, r.replacement_span, a.start_time, a.end_time
     FROM replacement_requests r
     JOIN time_slots a ON a.id = r.replacement_time_slot_id
    WHERE r.semester_id = <semester> AND r.status IN ('pending','approved')
      AND a.week_number = <week> AND a.day_of_week = <day> AND a.venue_id = <venue>
      AND a.start_time <= '<cell start>'
      AND NOT EXISTS (SELECT 1 FROM replacement_request_slots x WHERE x.replacement_request_id = r.id);
   ```

3. ALL cells pass → an explicitly approved, exact, conditional UPDATE per cell
   (`status='occupied'`, `class_session_id=<original owner>`, `version = current version + 1`,
   guarded by `status='available' AND class_session_id IS NULL AND version=<current>`) may be
   rehearsed with the testing-only helper and then, on the demo database, applied by the operator
   as a separately approved, hand-checked operation.
4. ANY failure → no partial restore; report and ask.

For rehearsal on the testing database only,
`App\Services\CancelledSlotCleanupRecovery::check()` / `->rehearse()` implement exactly these
rules (guarded, transactional, monotonic versions, all-or-nothing refusal). It is not reachable
from the CLI, scheduler, controllers or render paths, and it refuses every non-testing database.
No new CLI option or schema auto-invokes recovery.

---

## 6. What was and was NOT done in this preparation

Done (code + tests + runbook, testing-only):

- `app/Console/Commands/ReleaseCancelledClassSlots.php` — the guarded CLI (dry-run default,
  fail-closed apply, no scheduler/controller caller).
- `app/Services/CancelledSlotCleanup{Evidence,Pair,Occurrence,Plan,Inventory,Fingerprint,
  TargetPolicy,SnapshotGate,Applier,Result,AbortedException,Recovery,RecoveryRefused}.php` —
  inventory/discriminator/fingerprint/gates/applier and the testing-only recovery rehearsal
  helper. The discriminator's three predicates (pair identity, maintenance, user cancellation)
  and the applier's D4 lock step are DELEGATES of the shared
  `CancelledOccurrenceEvidence` / `OccurrenceMutationLocks` services — no second copy of either
  rule exists.
- `tests/Feature/CancelledSlotCleanupTest.php` — dry-run zero-writes, per-gate refusals, pure
  target policy, snapshot gate, testing apply with receipts/protected links/zero-change repeat,
  all-or-nothing fingerprint drift (protected claim / exception / audit / version), excluded
  ambiguous sibling, recovery refusal vs safe monotonic restore.
- This runbook.

NOT done (gates still open — do not claim them passed):

- Whole-feature Verify and a fully green regression suite are still open. Main's lint/PHPStan gates pass; the complete direct PHPUnit run has 707/708 passing and the known unrelated history-shape error (see apply-log.md).
- Unit snapshot manifests remain fabricated fixtures, not backup evidence. Main separately completed a REAL testing rehearsal: `/tmp/crs-reuse-next/cleanup-testing.dump`, its actual `pg_restore --list` receipt, SHA256s, manifest and recorded operator checklist. A fresh testing plan released exactly 4 legacy original cells (versions 1→2), wrote one maintenance audit and zero notifications, and passed post-commit verification. Repeat inventory proposed zero changes. Real recovery restored safely at version 3 inside a rolled-back rehearsal transaction; version drift and active reuse each refused with zero partial writes. Identity-scoped rehearsal fixtures were removed. Exact commands, evidence and hashes are in apply-log.md. These receipts authorise no demo operation.
- No dry-run has been executed against the demo `class_replacement_fresh` database (that is O01,
  on explicit user request, still read-only).
- No snapshot has been taken, no plan approved, no apply run anywhere near the demo databases
  (G2/O02 remain closed). The recovery rehearsal helper guards on the EXACT
  `current_database()` = `class_replacement_testing` before any write and there is no
  production restore tool; the CLI refuses `--env=testing` outright before any DB mutation.
