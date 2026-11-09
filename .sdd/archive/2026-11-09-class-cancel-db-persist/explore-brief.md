# explore-brief — `class-cancel-db-persist` (2026-10-27)

## The user's law (the grilling's settled answers)

"Real DB cancel-persist": the Cancel Class flow stops being visual-only —
the confirm writes a real `class_exceptions` row per (class_session_id,
week_number), audited, with a true inverse-undo. All six decisions
settled live with the user:

1. **Transport = (b) the shared Livewire trait** — `#[Renderless] public
   function cancelClass(...)` / `cancelClassUndo(...)` on ONE trait used
   by MyTimetable + CohortTimetable + VenueTimetable (the three pages the
   cancel modal serves). The repo's ONE write-path precedent
   (`Livewire.first().call('submitBlocks', …)`, arrangement blade :2028 →
   `ReplacementArrangement::submitBlocks` :498) repeats. CSRF is Livewire's
   job. NO HTTP route, NO controller, NO csrf-meta, NO fetch.
2. **The reason vocabulary = the user's 7-leaf list** (verbatim, their
   numbering) — the OLD 'Family Emergency' / 'Venue/Facility Issue' entries RETIRE
   (the user's list replaces them) and **'Other' is KEPT** (the user's
   follow-up 2026-10-27 — the radio, the description box and the
   must-describe rule all continue):

   | modal label (radios, plain) | DB `class_exceptions.reason` | status |
   |---|---|---|
   | Medical/Sick Leave — **the user's label edit: 'Medical Leave (Sick Leave)' → 'Medical/Sick Leave'** | `medical_leave` | existing |
   | Annual Leave | `annual_leave` | existing |
   | Emergency Leave | `emergency_leave` | existing |
   | Hospitalisation Leave | `hospitalisation_leave` | NEW |
   | Maternity Leave | `maternity_leave` | NEW |
   | Compassionate Leave | `compassionate_leave` | NEW |
   | Paternity Leave | `paternity_leave` | NEW |
   | Other (+ the description box, required) | `other` | NEW — user decision 2026-10-27 keep: **the Other radio + the `#cancelOtherDetail` textarea + the S7/S8 must-describe validation ALL STAY** (the earlier "retire" note inverted); the DB stores `other` and the typed detail rides the AuditLog details (also mirrored in the client ledger's `cancelledDetail`, the existing field) |

   CHECK keeps ALL 5 existing values (`public_holiday`,
   `official_event` stay — JS-no-longer-offered but the enum survives;
   the demo row id 4 = `official_event` continues to render
   'Official Event' on replacement-home). Radios carry value = the DB
   enum; label = the plain display. The MC parenthetical edit is the
   user's verbatim ask.
3. **Undo = the true inverse** (the user's option (a)): a second trait
   method `cancelClassUndo(sessionId, weekNumber, user)` deletes the
   caller's own `class_exceptions` row (the ownership guard re-checked
   through the session join) + writes the symmetric
   `AuditLog action='class_cancel_undone'`. The 5-second toast window
   stays — after it the cancel stands (no admin UI; DB access only).
4. **Plain labels, NO code prefixes** (the default adopted in the
   defaults sweep).
5. **'Other' STAYS** (the user's keep, 2026-10-27): the modal's Other
   radio + the `#cancelOtherDetail` textarea + `validate()`'s
   must-describe branch ALL continue; the POST sends `other` and the
   typed detail rides the AuditLog details + the ledger's
   `cancelledDetail` (the same field, no schema change for it).
6. **The double-write stays double** (the simplest honest expiry):
   confirm = the DB row (server) + the client ledger flip (in-page, the
   SAME visual verdict the user approved). On reload the server render
   SKIPS the exception week (the FR 2.16 read side is ALREADY WIRED —
   `buildEventsByWeek` :179 `continue`), the ledger's matchKey finds
   nothing and **self-evicts** (the existing `applyLedger` ledgerRemove,
   :4105). The failure path (network / duplicate / ownership) writes
   NOTHING and shows the hint.

## The wiring receipts (who calls who)

- **JS → server:** `CancelClassModal.confirm()` → the guards pass →
  `window.Livewire.first().call('cancelClass', sessionId, weekNumber,
  mappedReason)` → await the response `{ok, exceptionId?, hint?}` →
  ok runs the EXISTING `ClassCancellation.cancel()` client mutation + the
  ledger + the toast (the visuals unchanged); the response's
  `exceptionId` rides the ledger entry for the undo. fail = the honest
  hint, no ledger, no flip.
- **The reason map lives in the CLIENT**: radios = value (the enum) +
  label; `validate()` checks the enum set. The JS `REASONS` array
  reshapes to `{value, label}` pairs.
- **Server method order**: the trait resolves the session
  (`ClassSession::where('lecturer_id', auth()->id())` — a fabricated id
  resolves to null, the arrangement's `submitBlocks` :513 receipt),
  validates the enum + the week range via `CancelClassRequest` rules,
  calls the existing `CancelClass` Action (undamaged), returns
  `{ok:true, exceptionId}` / `{ok:false, hint}`.
- **The duplicate receipt**: the Action's unique-violation catch = false;
  the trait maps it to "This class is already cancelled." (the window
  law's S9 hint family remains for DATE-window failures).
- **The undo receipt**: `cancelClassUndo` finds the row BY
  (class_session_id, week_number) joined to the caller's
  `lecturer_id` (no row = `{ok:false}`); deletes it; audit
  `'class_cancel_undone'` (details: the same class_session_id +
  week_number + reason it erased); the client reverts
  (`applyLedger`'s removal path + `deselect` equivalents already exist
  via the `ledgerUpdate` reversal :3983).
- **The audit CHECK widen** (verified 2026-08-24's CHECK list): 7 values →
  + `'class_cancel_undone'`.
- **The reason CHECK widen** (the migration + the demo-DB SQL sheet the
  user runs): 5 → 10 (the 4 new leaves + `other`).
- **The home badge drift receipt (declared):** replacement-home reads
  `titleCase(medical_leave)` = 'Medical Leave' while the modal says
  'Medical/Sick Leave' — the one-word enum-vs-label drift the home's
  titleCase() law carries; documented, NOT smoothed (the enum is the
  store of truth).

## The demo-DB safety sheet (the user runs the SQL)

- The real pending requests #1–#3 never touched.
- The existing `class_exceptions` row (id 4, official_event, session 40,
  W14) untouched.
- The ALTER sheet = the reasons CHECK (5→10) + the audit action CHECK
  (7→8), both one DROP/ADD pair. The `demo-db-data-changes.md`
  convention: the user pastes; the migration file covers fresh DBs only.

## The home-table inclusion (user ask 2026-10-27 — ALREADY SERVED, pinned as an acceptance clause)

"A successful Cancelled Class shud be included in the table of
replacement-home-ui" — TWO mechanics already exist; the DB lane pins them
as acceptance rows, it does NOT build them:

1. **immediately, same tab** — `ClassCancellation.cancel()` (:4032–:4034)
   appends a `conflictedClasses`-shaped row (built by `rowFromEvent`,
   owner-lecturer backfilled) into `MockData.conflictedClasses` — the exact
   array replacement-home's table + cards render; the 'Just cancelled' chip
   rides it (S13).
2. **permanently, every viewer, every tab** — the `class_exceptions` row
   the DB lane writes feeds `ReplacementHome.php`'s server payload (the
   `$exception !== null` :79 branch emits the row; the badge =
   `titleCase(reason)`; the DB enum + the Home's добавить titleCase law is
   the same one the rest of the lane documents).
3. the cross-page glue — the `applyLedger` bootstrap (:4210, registered on
   EVERY page's DOMContentLoaded) replays the ledger toward the home
   in-session and self-evicts once the server row appears.

If either mechanism breaks, the home table stops showing the cancelled
class — the two rows above are the acceptance clauses in design.md.

## Tests (the user's standing manual-verify directive)

- NO new Playwright spec; the standing battery untouched BUT two spec
  families reference the OLD vocabulary and need declarative re-keys
  during apply: `tests/cancel-class.spec.ts` — the S7/S8 Other-gating row
  stays VALID (Other is kept) but its label list assertions re-key to the
  7-leaf + Other vocabulary; NO battery run.
- phpunit (the Feature suite) runs: the trait + the touched Actions
  receive ψuser's standing gates `pint + phpstan-1G`.

## Rejected approaches and why

1. **(a) the HTTP route + csrf-meta** — rejected (user decision Q1): the
   repo's one write precedent is Livewire; no new infra.
2. **(b) trimming the modal's reasons to the 3 mapable ones** — rejected
   (user's UI law: the approved copy stays).
3. **(c) misleading remaps** (Family Emergency → emergency_leave etc.)
   — rejected: false data into an audit trail.
4. **Undo = client-only** — rejected (user decision Q3 (b→a)): the DB
   row would stand while the screen says undone.
5. **Undo dropped** — rejected (user decision Q3 (c)): loses the
   approved 5-second grace.
6. **Server-side labels** (storing 'Medical/Sick Leave' instead of the
   enum) — rejected: the enum is the store of truth; the labels live in
   the client list; the home badge reads titleCase (the declared drift).
7. **Removing the client ledger after the DB write** — rejected: the
   in-page flow (status flip, the toast, the row append) reads from the
   ledger; the self-evict behavior keeps it honest — the least-change
   shape.

## Known open questions

- Q1 (deferrable): should the replacement-home badge eventually show the
  modal's exact label ('Medical/Sick Leave')? Currently titleCase
  ('Medical Leave'). The drift is a one-word receipt on the home
  page. NOT in this lane unless the user asks.
- Q2 (already settled by the window law, 2026-10-27): the 2-working-day
  window gate stays client-side (the isCancellable guard family) — the
  server Action does NOT re-check the window (the report's example law
  = a client permission); the confirm-time re-check exists in the
  cancel() S9 path. If a server-side window receipt is wanted, it rides
  a later lane.
