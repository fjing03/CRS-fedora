# proposal — `class-cancel-db-persist` (2026-10-27)

## Why (the user's law chain)

"Does Cancel Class write the database??? — STILL mockdata." → make the
Cancel Class flow write real DB rows while keeping the exact approved UI.
The user's 6 settled decisions (grilling log, explore-brief) unpack to
the scope:

1. the confirm writes ONE `class_exceptions` row per
   (class_session_id, week_number) — the existing `CancelClass` Action
   (app/Actions/CancelClass.php, FR 2.16, unit-tested in
   ReplacementFlowTest) does the work;
2. the reason vocabulary = the user's 7-leaf list + **Other KEPT**
   (radio + description box + the must-describe rule all continue) —
   the CHECK widens 5→10;
3. the UNDO writes the true inverse (delete the caller's exception row +
   the audit `'class_cancel_undone'`) — the audit action CHECK widens
   7→8;
4. the transport = ONE shared Livewire trait (`CancelClassBridge`,
   `#[Renderless]` `cancelClass` / `cancelClassUndo`) used by the three
   cancel-page components — the `submitBlocks` precedent, NO HTTP route,
   NO csrf-meta, NO controller;
5. the client visuals stay byte-identical (the ledger + the status flip
   + the toast + the home-table row append all continue; the server
   response {ok, exceptionId} rides the ledger entry for the undo; the
   applyLedger self-evict keeps the store honest after reload);
6. NO new Playwright spec; the user tests manually; the standing
   `pint` + `phpstan-1G` + phpunit gates still run; the demo-DB ALTERs
   are the USER's paste (the safety sheet below).

## Scope

| File | Change |
|---|---|
| `app/Actions/CancelClass.php` | `REASONS` widens to **10** (the 4 new leaves + `other` — the CHECK alone writes nothing, review R1-blocker①); the signature becomes `__invoke(User $user, ClassSession $session, int $weekNumber, string $reason, ?string $otherDetail = null)` — `other`'s typed detail rides `AuditLog.details.other_detail` (the review-suggested param naming); the enum values travel from the client |
| `app/Http/Requests/CancelClassRequest.php` | `rules()`'s `in:` list mirrors the same 10; a `public static function validateReason(): array` helper stays coherent with the DB CHECK |
| `public/js/ui-common.js` | the `ClassCancellation` reason data → `{value: enum, label}` pairs (7 leaves + Other), radios carry value=enum; **`validate()`'s Other branch re-keys on `'other'`** (review 🟡2 — the literal 'Other' would break silently); `cancel()`/`cancelledReason`/the home's `conflictReason` now hold the ENUM (the home's `titleCase()` law renders it — the medical_leave→'Medical Leave' drift documented); a NEW await-POST step inside `CancelClassModal.confirm()`: pending-disable the Yes, `await Livewire.first().call('cancelClass', …)` — the SERVER response is the truth (ok → flip+ledger, fail → the hint, NO flip — review 🟡1's midnight-roll caution folded: the client's own recheck never overrides the server); the 5s toast uncover AND the home chip's Undo affordance (:4151 — the cross-page undo is a pinned mechanic) both post `cancelClassUndo` **await-then-revert** (review R1-blocker④): the entry carries `exceptionId`; ok → the client reversal applies; fail → the cancel stands + the honest hint |
| NEW `app/Livewire/Concerns/CancelClassBridge.php` | the trait: `cancelClass($sessionId, $weekNumber, $reason, ?string $otherDetail)` + `cancelClassUndo($sessionId, $weekNumber)` — ownership-scoped session resolve (the `submitBlocks` :513 receipt), `CancelClassRequest::rules()` validation, the Action calls (the undo reads the row FIRST so the audit records the reason it erased — review-suggested), the response arrays `{ok, exceptionId?}/{ok, hint?}`, `#[Renderless]` on both |
| `app/Livewire/MyTimetable.php` / `CohortTimetable.php` / `VenueTimetable.php` / **`ReplacementHome.php`** | ONE `use CancelClassBridge;` line each — **the home is in the list** (review R1-blocker③: the home chip's Undo affordance is a pinned acceptance mechanic; `window.Livewire.first()` verified to be the page component per the `my-request-history.blade.php`:647 pattern) |
| NEW `database/migrations/*_widen_cancel_reason_and_audit_checks.php` | the two CHECK widenings; **down() = the narrowing HAZARD** (the `2026_08_24_000001`:63 precedent comment mirrored — after any real usage the narrow restore fails; the down() body keeps the widen, `@SuppressWarnings` free) — **NOT auto-run on the demo DB** |
| `tests/cancel-class.spec.ts` | the declarative re-keys: the radio locators `[value="Medical Leave"]` ×4 (+ the ledger/label assertions :243 and the S7/S8 gating live receipt) carry the enum values; NO battery run |
| `e2e/full-suite.spec.js:218` / `e2e/api-wiring.spec.js:83` | the OLD 5-value reason vocabulary strings re-key (the declarative receipt) |
| the four page changelogs (my-timetable, cohort, venue, home) | dated entries |

OUT of scope: the exception-row read side (already wired), the
replacement-home payload (already emits the cancelled rows), the
subtitle/badge titleCase drift (documented, not smoothed), the
server-side window re-check (the report's law = client permission; a
later lane), students (FR 2.16 = lecturer/PL only, unchanged).

## The vocabulary table (the frozen mapping, the user's labels verbatim)

| # | modal label (radio, plain) | DB enum | status |
|---|---|---|---|
| 1 | Medical/Sick Leave | `medical_leave` | existing |
| 2 | Annual Leave | `annual_leave` | existing |
| 3 | Emergency Leave | `emergency_leave` | existing |
| 4 | Hospitalisation Leave | `hospitalisation_leave` | NEW |
| 5 | Maternity Leave | `maternity_leave` | NEW |
| 6 | Compassionate Leave | `compassionate_leave` | NEW |
| 7 | Paternity Leave | `paternity_leave` | NEW |
| 8 | Other (+ the description needed) | `other` | NEW (the user KEPT it) |
| — | — | `public_holiday` | existing, JS-never-offered |
| — | — | `official_event` | existing, JS-no-longer-offered (the row id 4 survives) |

## The demo-DB safety sheet (the USER pastes; I never run)

```sql
-- demo-db-data-changes.md entry: the cancel-reason + audit-action CHECK widen
ALTER TABLE class_exceptions DROP CONSTRAINT class_exceptions_reason_check;
ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check
    CHECK (reason IN ('public_holiday','annual_leave','medical_leave','official_event','emergency_leave',
                      'hospitalisation_leave','maternity_leave','compassionate_leave','paternity_leave','other'));
ALTER TABLE audit_logs DROP CONSTRAINT audit_logs_action_check;
ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
    CHECK (action IN ('submitted','approved','rejected','cancelled','completed','occ_conflict','class_cancelled','class_cancel_undone'));
```
Protected: the pending requests #1–#3, the `class_exceptions` id=4 row —
untouched by both statements.

## Acceptance

A lecturer cancels their own future class (in-window per the 2026-10-27
law): the modal's Yes → the DB gains the `class_exceptions` row + the
`class_cancelled` audit row → the UI shows the same approved visuals
(the status flip, the toast, the home-table row). The undo — from the 5s
toast OR the home chip, any time later — posts `cancelClassUndo`,
removes the DB row, audits `class_cancel_undone`, and reverts BOTH the
client state and the server truth (a reload re-renders the block — the
undo is complete). Reload without undo → the server render agrees with
the cancel (applyLedger self-evicts). Failure → nothing written, the
hint tells the truth, no client flip.

## The cancelReasonEnum → titleCase → badgeClass contract (review round-2 fold)

- **The enum transports everywhere client-side**: radios value, the ledger's
  `cancelledReason`, the home's `conflictReason` (the row both appended and
  server-rendered), the home's status filter (`:353`).
- **The titleCase law extends inline** (one map receipt):
  `medical_leave` → 'Medical/Sick Leave' (the drift retire — the modal
  label EQUALS the home badge now), `other` → 'Other', and the 4 new
  leaves titleCase naturally. `emergency_leave` → 'Emergency Leave',
  `annual_leave` → 'Annual Leave', `official_event` → 'Official Event',
  `public_holiday` → 'Public Holiday' all byte-existing.
- **The home badge colors (badgeClass :302–:312)** — 5 new map entries
  (the same-name/same-color §10.0 law):
  - `hospitalisation_leave` → `badge-hospitalisation-leave`
    = `--color-primary-container` / `--color-on-primary-container`
    (the Official Event precedent — the Home page's local badge CSS).
  - `maternity_leave` → `badge-maternity-leave`
    = `--color-tertiary-container` / `--color-on-tertiary-container`
    (the medical pair, the sibling leave).
  - `compassionate_leave` / `paternity_leave` → both the
    `--color-primary-container` family (the leave family's neutral eyes).
  - `other` → `--color-secondary-container` /
    `--color-on-secondary-container` (the neutral grey family).
  ALL colors tokens-only (the §10.0 law); the CSS lives in the home
  blade's page CSS (the same `:20-47` block the existing 5 live in).
- **The SECOND 'Other' literal** — `CancelClassModal._refreshGate`
  (:4376 `reason === 'Other'`) re-keys on `'other'` (the confirm's
  Yes gate; the reviewer's heaviest round-2 find).
- **ONE shared undo call** — `ClassCancellation.undoViaServer(entry)`
  promoted (the toast callback + the home chip's
  `undoCancelledByRowId` both single-homed onto it; the
  Promote-on-duplication precursor).
