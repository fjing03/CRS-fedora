# SDD review log — receipt-target-guard

## Round 1 (glm sdd-reviewer) — **PASS with fixes**

Verdict: *"PASS with fixes. Proceed to `/sdd-apply` only after Required fix 1
(T9f decision + audit-line correction) and fix 2 (removal counts +
abort-placement prescription) are folded."*

**The reviewer's full verified claims** (independently confirmed against the repo):
1. island-testing-db's defaults/loose-gate confirmed (`:8000` default + `includes(':803')`), PLUS the removal inventory correction (3 skips 148/196/223 + 3 hook gates 114/126/142).
2. submission-conflict-refresh's ctxB write confirmed (the `#modalConfirmBtn` "Yes, Submit Request" click = the real reserve).
3. TC-4.5 = conditional write-capable; TEST-body throw correct (approved).
4. cancel-class confirm-clicks at exactly 218/256/376/471 → `ui-common.js:4496` `pageLivewire().call('cancelClass', …)` = real persist (approved — audit line exact).
5. TC70 retired comment-only; keeping the `--grep-invert "TC70"` law correct.
6. Module-abort ordering claim correct (collection-phase refusal precedes the setup project); the helper is never auto-collected.

**Review completes the audit (independent sweep):** zero `request.post`/POST/`fetch` across all 50 spec files; one `execSync` (island); NO class_replacement_fresh DSN anywhere; the client exposes EXACTLY TWO server-write bridges — `cancelClass` (:4496) + `cancelClassUndo` (:4207–4223, S14's undo leg — recorded in the proposal's scope row) — plus the arrangement reserve/submit + the notif mark-read POSTs; no missed write family.

### Required fixes — FOLDED
- **Fix 1 (T9f)** — the reviewer's finding: the probe's "FAKE id" story was
  FALSE; it fired the decide wire with the first row's REAL id (write-capable
  on a live pending queue — the incident's exact class). **Fold = the
  structural option (reviewer's b), implemented as a zero-write rewrite:** the
  wire is tripped DIRECTLY with nonexistent id 999999; `ApproveRequest` returns
  the `$request === null → 'already decided'` loser and writes nothing, whatever
  the queue holds. Asserts the loser string in the response + pending-before ==
  pending-after. (The guard add was unnecessary — zero-write by construction —
  the pre-check/asserts ARE the receipt; proposal/design/tasks updated.)
- **Fix 2 (counts + placement)** — tasks.md T2 now names all THREE skip lines +
  all THREE hook gates + the PLACEMENT LAW (abort as the first statement after
  the imports, before any test() registration). The code had already satisfied
  both (verified: zero `ALLOWED` references anywhere) — the docs now match it.

### Should-fixes — FOLDED
- Proposal: named Tier client-side confirm-clickers (confirm-guards +
  clear-guards-cross-week + UI-TC-4.3 — no wire calls); TC-4.4/5.1/5.2
  non-writing asserts; auth-full = session/limiter side-effects line.
- Proposal: S14's scope row carries the second bridge (`cancelClassUndo`,
  ui-common.js:4215).
- Design: 'SQL-fixture' mislabel fixed (conflict carries no SQL; both enable
  `CRS_ALLOW_SQL=1` because their documented invocations say so).
- Design: the two OPERATOR EXPECTATIONS (full-directory runs abort at
  collection by design; the five throws = permanent honest reds — never
  un-guard).
- Tasks: T3e softened to `crs:db-row-counts` before/after EXPECT UNCHANGED.
- Advisory rename: `isDestructiveTestingTarget` → `isSanctionedTestingTarget`
  (boolean-inversion trap removed; T3's wrong-base refusal would catch an
  inversion anyway — renamed regardless).

---

## Receipts (T3)

a. **Bare run (island)** → ⛔ box + **exit 1** — collection-phase refusal, no
   server hit, no login, no SQL. The box names what was found
   (`CRS_MODE=<unset> …`), what is required, and the full blessed invocation.
b. **Wrong-base run** (`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://localhost:8000`)
   → ⛔ box + exit 1 — the old guard would have SILENTLY SKIPPED here (and the
   old island default would even have gone to `:8000`); the exact-string rule
   is what closes the incident's failure mode.
c. (b-c) **Bare run (conflict)** → ⛔ box + exit 1, same shape.
d. **Collection** (`--list` ui-regression + cancel-class) → 42 tests listed
   clean, exit 0 — the throws live in test bodies; collection compiles.
e. **Positive path** — testing DB reseeded (`DB_DATABASE=class_replacement_testing
   migrate:fresh --seed`, sanctioned) + dedicated serve `:8030` (login probe 200):
   - island suite: **6/6 passed (53.2s)** — 3 setup logins + the 3 fixture
     tests (badge lifecycle 25.3s / disabled cancelled row 3.2s / D15 family
     13.5s), exit 0.
   - conflict suite: see below (run after the reviewer's fixes folded).
f. **T9f zero-write round-trip** at :8000 (`--grep "T9f"`): **passed (926ms,
   exit 0)** — works on ANY queue state including the post-restore empty one.
g. **Demo DB untouched**: the guard's whole point — my lane's write-capable
   receipts ran ONLY with the blessed envs at `:8030`/`class_replacement_testing`;
   the refusals fired at collection; T9F's within-test assert proved the queue
   unchanged (before==after). `crs:db-row-counts` at receipt-time (NOT my
   write — see note): requests **1** (id 1, pending, W14) / notifications **2**
   (the 2-PL 'submitted' fan-out, unread) / class_exceptions **0** /
   class_sessions 101.

**Demo row-authorship note (honest):** the pending request + 2 notifications
carry created 2026-11-01 06:40:49 — the notif-new-request-ux lane's own T8
":8000 real-row composer receipt" executed POST-restore by the incident log's
author session (predates this lane's first action ~16:0x; shape = the exact T8
1-waiting + 2-PL-fan-out receipt). Recording per the log's convention is its
owner's; the counts above are this lane's honest observation only.


---

## Finalised 2026-11-05 (pi session) — status → applied

The lane's code was still unstaged in the working tree with its bookkeeping
unfinished (status `draft`, tasks unticked). Re-verified and closed out here.
**These are MY receipts, run today; the lane's own T3 entries above stand as
recorded and were not re-run except where noted.**

| Gate | Result (2026-11-05) |
|---|---|
| a. bare run (module-abort spec, no envs) | ⛔ box + **exit 1** (`notifications-island-testing-db`) — refusal at collection |
| b. wrong-base / missing switch | same box shape, exit 1 (the found/required lines print the exact strings) |
| c. collection clean (throw-guard files) | `--list ui-regression + cancel-class` → **41 tests in 3 files**, exit 0. The two MODULE-abort specs refuse `--list` too — by design (collection-phase refusal) |
| d. conflict suite | **NOT VERIFIED — see tasks.md T3d.** Two attempts at :8030 on a fresh seed failed inside the spec's own arrival flow (login redirect timeout, then `#weekSelector` missing). No green record exists for this suite; the guard concern is proven independently by (a)/(b) |
| e. demo DB untouched | `crs:db-row-counts` before **and** after the receipts: 5 requests / 77 notifications / 2 class_exceptions — IDENTICAL. Every write-capable run was at :8030 |
| f. island suite (re-run) | **6 passed (50.4 s)** at :8030 on a fresh seed |

Helper already committed in `ce31746` (the cancel-class spec imports it, as do
already-committed specs). Code committed 2026-11-05; no push.
