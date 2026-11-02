# Prompt — notification recipient rulings + the staff subtitle (SDD lane `notif-recipient-rulings`)

> Hand this file to a fresh OpenCode session working in `CRS-fedora-backend`.
> The five rulings below are the user's decisions (2026-11-03). Do not re-open
> them; implement them. If a new question appears, ask it in plain terms (see §8).

## 0. Read first

1. `prompts/for-ai-to-read/AGENTS.md` — standing rules, port map, DB law.
2. `prompts/for-ai-to-read/my-coding-rules.md` — SDD gate (line 94: "no proposal, no change"), commit prefixes.
3. `prompts/for-ai-to-read/CodingMAIN.md` — §4 DB safety, §10 conventions.
4. `docs/demo-db-data-changes.md` (top entry) and `tests/helpers/receipt-target-guard.ts`.
5. Load the **`sdd-workflow`** skill and follow it.

## 1. SDD — required (answer: yes)

Create `.sdd/changes/notif-recipient-rulings/` with:

- `proposal.md` — why, scope, and the **policy reversal** (the 2026-10-30
  "THREE fan-outs" ruling for class cancellation is replaced), plus the five
  rulings quoted.
- `design.md` — the row shapes, the subtitle algorithm, the PL-proposer
  nuance, the test matrix.
- `tasks.md` — the six tasks in §3–§6 below, as checkboxes.
- `review-log.md` — one honest reviewer round **or** an explicitly logged user
  waiver; never self-declare frozen.
- `sdd.yaml` — `name`, `created: 2026-11-03`, `status: proposed` → `applied`.

Do not edit product code before the lane exists.

## 2. The rulings (settled — do not re-ask)

| # | Ruling |
|---|---|
| R1 | **Submit** → the **non-PL proposer** gets a confirmation row ("Replacement request submitted", `/my-request-history-ui`, `type=update`). A **PL proposer keeps their single existing queue row** — do not add a second row. |
| R2 | **Email** → the proposer is **never emailed** their own "new replacement request", whatever their role (skip them in the mail loop). |
| R3 | **Approve** → **merge**: the owner gets ONE row, title "Replacement class updated", body `<classFact> · approved by <decider> · your timetable is updated`, link `/my-timetable-ui`. Remove the separate "Replacement approved" owner row. Cohort students keep their existing `approvalNotice` row unchanged. |
| R4 | **Class cancelled** → owner + cohort students **only**. Delete the department-PL fan-out (this reverses the 2026-10-30 ruling and its test). |
| R5 | **Subtitle** → every notification type, **every state** (unread, read, decided, inactive, hidden) carries the "other party" staff line in the format `Name (staff_id)`:<br>• PL/student rows show the staff the row is about — proposer (request rows) or class lecturer (class rows);<br>• staff self rows show the PL who acted (approver);<br>• a self-only event (owner cancelling their own class) omits the line. |

## 3. Implementation anchors (verified 2026-11-03)

- `app/Support/Notifications/NotificationEmitter.php`
  - `requestSubmitted()` (`:34`) — add the proposer `User` argument; write the
    proposer row only when `$proposer->lecturer?->is_pl !== true`.
  - new `replacementConfirmed(User $owner, ClassSession $session, int $requestId, int $week, string $deciderName)`.
  - `classCancelled()` (`:110–152`) — delete step 3 (dept PLs) and the now-unused
    `App\Models\Lecturer` import; fix the "THREE fan-outs" docblock.
- `app/Actions/SubmitReplacementRequest.php`
  - `:195–216` — skip the proposer in the `Mail` loop; capture `$proposer` in the closure.
  - `:222` — pass `$proposer` to `requestSubmitted()`.
- `app/Actions/ApproveRequest.php`
  - `:204–215` — replace `requestDecided(..., 'approved', ...)` with **one**
    `replacementConfirmed()` call **outside** the cohort loop (inside it would
    write one row per cohort).
- `app/Livewire/NavNotifications.php`
  - `:408` — add `'approver.lecturer'` to the eager load.
  - `resolveRequests()` returns early when there are no request ids (`:402`) —
    attach the staff lines **after** that branch too, so pure class rows get one.
  - one batched `ClassSession::with('lecturer.lecturer')` query for rows with
    `class_session_id !== null && request_id === null`.
  - set a **separate** `staff_line` attribute (not `_display`): `_display` is
    gated on `reason === null` (`:487`), so anything stored there disappears
    once the row is read. `staff_line` must survive every state.
  - the rule: `subject = proposer | class lecturer`; if `subject.id === row.user_id`,
    show the approver when one exists, else `null`.
- `resources/views/partials/notif-row.blade.php:110`
  - `@if($subtitle) … @elseif($n->getAttribute('staff_line')) … @endif`.
    When the attribute is null the output is byte-identical to today.

No migration, no schema change, no new dependency.

## 4. Tests — `tests/Feature/NotificationsBridgeTest.php`

- `submitEvent()` helper (`:59`) — pass `$this->coordinator()` (user 1, a PL) as
  the proposer so the existing tests keep their two PL rows.
- new — a **non-PL** proposer gets exactly one `update` row, link
  `/my-request-history-ui`, no `submitted` row for them.
- `test_approved_rows_follow_the_per_role_link_law` — assert the merged owner
  row instead (title, `/my-timetable-ui`, body contains "approved by").
- `test_class_cancelled_fans_out_to_owner_students_and_dept_pls` — rename and
  assert **no** `role_view='pl'` rows (user 2 absent), owner + students present.
- new — subtitle: PL row → proposer; student approval row → class lecturer;
  owner decision row → the approver; owner cancellation row → absent.
- `tests/Feature/EmailQueueTest.php` — the fixture proposer is already a
  non-PL, so `$plCount` stays correct; add an assertion only if it is cheap.

## 5. Gates

```bash
php artisan test --filter="NotificationsBridgeTest|EmailQueueTest"
composer run lint:check
vendor/bin/phpstan analyse --memory-limit=1G
```

Browser receipt on the disposable lane only:

```bash
DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
setsid nohup env DB_DATABASE=class_replacement_testing php artisan serve --host=127.0.0.1 --port=8030 &
# check the bell panel as 5770 / 5425 / a student, then:
pkill -f "[a]rtisan serve --host=127.0.0.1 --port=8030"
```

Changelog: `page-changelogs/notifications-panel-changelog.md` — one dated entry
stating R1–R5 and the reversal of the 2026-10-30 three-fan-out ruling.

## 6. Commits

- `feat(notifications): …` — the behaviour (emitter + actions + Livewire + partial).
- `test: …` — the test updates (or fold into the feat commit if it is one unit).
- `docs: …` — the SDD lane + the changelog.

Never push. Do not commit `.auth/`, `playwright-report/`, or another session's
working-tree files.

## 7. Out of scope (report, do not fix)

- The approval queue is unscoped and accepts the PL's own request (self-approval).
  Separate issue; mention it in the final report.
- A `submitted` row still loses its composed title/ID/schedule after it is read
  (`_display` is gated on `reason === null`). The new `staff_line` is independent
  and survives; do not refactor the composer in this lane.
- Do not change the mail **templates**; only the recipient loop.

## 8. How to talk to this user

- Always phrase questions as: **"So the question in plain terms: …"**, then one
  sentence of context, then a recommendation with its trade-off. One question at
  a time; no jargon-only questions.
- Do not ask about R1–R5 — they are decided.
- If a genuine new decision appears, stop and ask; do not guess.

## 9. Archive

Only on the user's explicit word. Then move the lane to `.sdd/archive/`, update
`prompts/sdd.md`, and leave the changelog entry in place.
