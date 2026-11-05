# Oct-31 Dataset — What the Evidence Could Recover

**Written:** 2026-11-01 (the recovery-hunt lane).
**Forensic context:** the 2026-11-01 ~03:22 agent fixture wipe destroyed the user's
Oct-31 evening UI submissions on `class_replacement_fresh` (see the DATA-LOSS
INCIDENT entry in `docs/demo-db-data-changes.md`). All full-recovery avenues were
checked and are dead: git never held DB rows; the backups dir holds only 3 dumps,
none of Oct-31; Postgres `archive_mode = off` (no point-in-time recovery); the app
logs (`storage/logs/laravel.log`) contain zero Oct-31 production-mail or error
traces; the CRS-demo twin (`:8001`) never saw these clicks (verified 0 rows).
**What survives is exactly what agent receipts happened to print on-screen during
Oct-30/Oct-31 evenings.** Those receipts live in the agent session transcripts
(opencode SQLite store) and were extracted 2026-11-01.

## Snapshot A — the full `replacement_requests` table, 2026-10-30 17:31

A psql receipt printed the whole table (8 rows):

| id | status    | week | the class's day | replacement starts |
|----|-----------|------|------------------|--------------------|
| 1  | approved  | 8    | day 1            | 13:30              |
| 2  | cancelled | 14   | day 4            | 12:30              |
| 3  | cancelled | 5    | day 4            | 13:30              |
| 4  | cancelled | 5    | day 4            | 13:30              |
| 5  | cancelled | 7    | day 4            | 12:30              |
| 6  | rejected  | 5    | day 4            | 14:00              |
| 7  | cancelled | 14   | day 3            | 14:00              |
| 8  | rejected  | 8    | day 0            | 14:00              |

(week/day = the **original** class occurrence being replaced; start time = the
**replacement** slot it proposed. This predates the user's Oct-31 evening clicks.)

## Snapshot B — 2026-10-31 ~19:16 (the user's submission evening, mid-flow)

A psql receipt printed the live pending+approved view:

| class_session | week | status   |
|---------------|------|----------|
| 23            | 8    | approved |
| 39            | 14   | pending  |
| 49            | 14   | pending  |
| 97            | 8    | pending  |

**Honesty note:** two of these three pendings (39/W14, 49/W14 — and possibly
97/W8) match the shape of the user's Oct-31 evening submissions, but the notif
lane's own fixture family also inserted pending rows during its runs that night —
attribute with care when using this list.

`class_exceptions` at the same moment: (23, 8, medical_leave),
(40, 5, medical_leave), (40, 7, annual_leave).

## Snapshot C — the last-known pre-wipe remark

The notif lane's final report, recovered only as a quoted remark, said the live
requests had reached **ids 8/10/13** — i.e. MORE submissions and/or decisions
happened after Snapshot B (up to id 13) that left **no later receipt**.

## Still unknown (unrecoverable)

- The exact identity and final statuses of request ids ~9–13 after 19:16.
- Their proposers, venues, replacement-slot details, and submitted_at times.
- Every notifications fan-out row and any class_exceptions added that night.
- Whatever the user's own evening clicks decided/approved after 19:16.

## Practical rebuild (per the user's decision: "closer to demo week", mix "1 waiting + 1 done")

When demo week approaches (week of Dec 7), on the `:8001` CRS-demo twin
(clean by design), via the UI only:

1. **"1 waiting"** — submit a fresh replacement request as the staff account
   (any W14 booking is in the spirit of the lost 39/49 rows).
2. **"1 done"** — decide one (approve via the PL account flow) so the history
   shows a completed colour, echoing the lost approved/rejected mix.
3. Then take a `pg_dump` snapshot of `class_replacement_demo` and record it in
   `docs/demo-db-data-changes.md` (and mirror the dumps folder per
   `Desktop/tarumt/backups/README.md`) so this can never happen again.

The wall-clock demo value is untouched by the loss: the system's schedules,
holidays, and cohorts all come from the seeder/import, which is intact — only
that one evening's click-history is gone.

---

## REBUILD EXECUTED 2026-11-01 (user OK'd plan A: badge 3, older bell rows read; DFT2 deletions KEPT)

### Twin upgrade first (its app was Oct-13-era, pre-approval/pre-notification)
- `../CRS-demo` clone: fetched from this repo's HEAD, `bd9b208 → ed0fb01`
  (ff-only; its dirty `package-lock.json` stashed as "package-lock drift before
  twin upgrade 2026-11-01").
- Migrations applied additively (no data loss): replacement_request_slots,
  venues.description, cancel_reason widen + audit checks, notifications. Ledger = 31/31.
- `:8001` serve restarted from the upgraded clone (stale compiled views cleared).

### What was written (one transaction, /tmp/opencode/twin-write.sql)
- **class_exceptions 3 (EXACT from receipts):** (23, W8, medical_leave),
  (40, W5, medical_leave), (40, W7, annual_leave) — created/updated 2026-10-30 17:31.
- **replacement_requests 11** — statuses/weeks/own-day/repl-times = receipt-exact;
  **class identities for the 8 decided = look-alikes the user OK'd**
  (receipts never showed the module): 86/W8 rej (Rahmat), 19/W5 rej ×1 + cancelled ×2 + W7 cancelled
  (Christopher), 78/W14 cancelled (Norshikin), 94/W14 cancelled (Lim), 33/W8 approved (Daniel).
  Trio = REAL classes/states: 39/W14 pending, 49/W14 pending, 97/W8 pending (all Lim, Oct-31 17:48–18:07).
  Make-up cells (all validated free): 21033, 34113 (busy-by-design ⇒ the clash story), 16690,
  37492, 37512, 37750, 23453, 34492, 35172, 36869, 27767.
- **Slot holds:** 34492→occupied (approved), 35172/36869/27767→pending (trio) — each version+1, app-conformant.
- **notifications 90** (native emitter shapes: submitted ×22 (both PLs), approved-to-proposer 1,
  rejected-to-proposer 2, approval student notice ×15 (class 33), leave fan-outs ×46
  = 3 events × (actor + students 14/15/15 + dept-PL copy)). Trio's 3 per PL UNREAD ⇒ badge = 3;
  everything Oct-30 read at 17:40.
- Times honesty: all Oct-30 decided rows submitted ≤ 17:31 (the receipt moment), staggered 17:05–17:31;
  trio = Oct-31 evening (≤ the 19:16 receipt).
- **NOT reconstructed:** audit_logs rows (the app writes them on every action; the twin's UI has
  no audit page, left clean on purpose). Approval/rejection "receipts" beyond what receipts captured
  (rejection reasons = the demo story's phrasing, verbatim in the rows).

### Verified on-screen (throwaway read-only playwright, then deleted)
Login 5425: badge = 3 ✓, bell shows the 3 trio tiles ("Week 8 · Wednesday · 11:00–11:30 · B110", 19h ago) ✓,
/inbox approval page = 3 waiting ✓; login 5770: own history shows AMCS2093 rows ✓.

### Snapshots (Desktop/tarumt/backups/)
1. `class_replacement_demo-user-edited-2026-11-01.dump` — the user-edited state BEFORE everything
2. `class_replacement_demo-pre-upgrade-2026-11-01.dump` — before the code upgrade
3. `class_replacement_demo-pre-write-2026-11-01.dump` — post-upgrade, post-migration, pre-rebuild
4. `class_replacement_demo-post-recovery-2026-11-01.dump` — **final demo-ready state**

### Still open (unchanged lanes)
- Conflict-spec hang: the one-line `pageA.reload({ timeout: 15_000 }).catch(() => {})` fix at
  `tests/submission-conflict-refresh.spec.ts:122` still pending its rerun (reseed testing DB first).
