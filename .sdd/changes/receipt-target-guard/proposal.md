# Proposal — receipt-target-guard

## Problem
2026-11-01 ~03:22 DATA-LOSS INCIDENT (`docs/demo-db-data-changes.md`, top entry,
uncommitted-but-authoritative): the notif-new-request-ux T7 fixture family wrote
destructively DIRECTLY onto the demo DB (`class_replacement_fresh`, `:8000`) —
the user's Oct-31 UI-submitted requests (approvals/cancellations + pending
submits + notifications fan-out + class_exceptions) were permanently lost; the
demo was restored from the pristine dump + `migrate --force`. The same class of
accident happened before: 2026-10-26 `--env=testing` accident (`backups/README.md`).

The incident's standing guard, verbatim: *"every destructive spec/fixture MUST
abort unless `CRS_BASE = http://127.0.0.1:8030` AND `CRS_MODE = testing`. No
receipt work touches `:8000`/`class_replacement_fresh` again — read-only only,
ever, from agents."* It was marked **must ship with the next code commit** —
this lane IS that next code commit.

## Goal
A fail-closed, code-enforced target guard for every destructive Playwright
spec/fixture in `tests/`: they must REFUSE to run (loud abort) unless both env
switches provably aim them at the disposable testing DB. No silent skips.

## Scope (the full audit trail — nothing else writes)
| File | Write capability | Guard scope |
|---|---|---|
| `tests/notifications-island-testing-db.spec.ts` | psql INSERT/DELETE + UI submit fixtures (`sql()` helper) | MODULE-level abort (every test writes) |
| `tests/submission-conflict-refresh.spec.ts` | real reserve-submit writes (ctxB "reserves the slot") | MODULE-level abort |
| `tests/ui-regression.spec.ts` TC-4.5 | clicks confirm on "Submit Request" → "request recorded" | TEST-body refusal (write test only) |
| `tests/cancel-class.spec.ts` S9/S11, S10, S14 (+S6 belt) | `#confirmCancelClassBtn` → `pageLivewire().call('cancelClass', …)` = real `class_exceptions` row (`ui-common.js:4496`); S14's undo leg adds the SECOND server bridge `cancelClassUndo` (`ui-common.js:4215`) — both inside already-guarded tests | TEST-body refusal per confirm test |

## Read-only, verified, NOT touched (with the decisive evidence)
- `tests/notifications-island.spec.ts` (:8000 island) — header: "READ-ONLY by construction"; no execSync/insert.
- `tests/venue-timetable.spec.ts` — TC70 RETIRED (comment-only block; "TC70 — cancelling an own class frees the venue slot — RETIRED (U2a T8)"); all TC01–TC67 titles are asserts.
- `tests/replace-now.spec.ts`, `tests/cohort-venue-replace-now.spec.ts`, `tests/venue-book.spec.ts` — `#btnReplaceNow`/`#tooltipBookBtn`/`#intentArrangeBtn` clicks are NAVIGATION handoffs (`waitForURL` asserts), never server writes.
- `tests/request-approval.spec.ts` T9f — REVIEWER ROUND-1 CORRECTION: the
  original probe's "fake id" story was FALSE (the UI clicks fire the wire with
  `currentApproveIds` = the row's REAL id → on a live pending queue it was a
  genuine approval write). This lane REBUILT it zero-write: the wire is tripped
  directly with a nonexistent id (999999) — `app/Actions/ApproveRequest.php`
  answers the `$request === null → 'already decided'` loser and writes nothing,
  whatever the queue holds; the pending-count before==after is asserted.
- `tests/confirm-guards.spec.ts` + `tests/clear-guards-cross-week.spec.ts` +
  `tests/ui-regression.spec.ts` TC-4.3 — all click `#modalConfirmBtn`, all
  CLIENT-side (venue/subject/slot-change confirm + clear-selection resets;
  verified: no wire call, no POST).
- `tests/ui-regression.spec.ts` TC-4.4/TC-5.1/TC-5.2 — modal-visibility +
  disabled-button asserts only (TC-4.4 stops at the confirm modal; TC-5.1 opens
  the notes modal without confirming; TC-5.2 asserts the disabled Confirm).
- `tests/auth-full.spec.ts` — wrong-password logins exercise Fortify's 5/min
  limiter only: session/limiter side-effects are NON-data; unguarded by design
  (the write-capability definition covers persistent app data only).
- `tests/e2e/*.spec.js` — `request.get` only; zero POSTs; zero write-capable clicks.
- The remaining root specs (arrival-pin, tooltips, parity, wiring, week-memory,
  type-labels, …) — asserts on served state; no page writes.

## Non-goals
- No app code (`app/`, `resources/`, `public/js`) changes.
- No read-only spec re-anchoring; `--grep-invert "TC70"` battery law unchanged.
- No retention of the loose `BASE.includes(':803')` checks — replaced by exact-string match.

## Success
1. Bare `npx playwright test tests/notifications-island-testing-db.spec.ts` → loud box + exit 1 BEFORE any browser/login/SQL (collection-phase refusal).
2. Same for `tests/submission-conflict-refresh.spec.ts`; and a wrong-base env (`CRS_BASE=http://localhost:8000`) also refuses.
3. Blessed invocation runs BOTH suites green against `:8030` + `class_replacement_testing`.
4. The mixed files' read-only siblings still collect (`--list`) with no phase errors.
