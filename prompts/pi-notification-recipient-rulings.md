# Prompt — notification recipient rulings + the staff subtitle (SDD lane `notif-recipient-rulings`)

> Hand this file to a fresh OpenCode session working in
> `.../BMCS3404 PROJECT I (4)/CRS-fedora-backend`.
> The five rulings in §3 are the user's decisions (2026-11-03). Do not re-open
> them. Everything else here was verified against the code on 2026-11-03.
> Follow §1 (SDD) before writing product code, and §14 for how to ask questions.

---

## 0. What the system is (30-second orientation)

The bell/panel is a Livewire island, `App\Livewire\NavNotifications`, mounted on
every page. Rows come from the `notifications` table: one row per recipient per
event, written by the single emitter `App\Support\Notifications\NotificationEmitter`
inside `DB::afterCommit` closures (never inside the transaction).

- `type` is frozen to five tile keys: `submitted | awaiting | approved | rejected | update`.
- `role_view` is `pl | lecturer | student` (the reader's category).
- `link` is the per-role deep link; students never link the lecturer-gated pages.
- Read state is the durable `POST /notifications/read` (keepalive) fired on row click.
- The row partial is `resources/views/partials/notif-row.blade.php`, shared by the
  inbox, the manage-mode `pick` variant and the hidden-shelf `hidden` variant.

Today's recipients (verified):

| Event | Recipients today |
|---|---|
| Request submitted | every `is_pl` user, **regardless of department** (a PL proposer gets it too) |
| Request approved | proposer ("Replacement approved") + every cohort student ("Replacement class updated") |
| Request rejected | proposer |
| Pending reminder (daily 08:00, deduped until read) | every `is_pl` user |
| Scheduled class cancelled | the class owner (actor row) + every cohort student + the **department** PLs of the class owner |

---

## 1. SDD — required. Answer to "does this need SDD?": **yes.**

The repo law is *"Every change runs through SDD … No proposal, no change"*
(`prompts/for-ai-to-read/my-coding-rules.md:94`). This lane changes **who
receives a notification** and **reverses an earlier user ruling** (the
2026-10-30 "THREE fan-outs" for class cancellation), so it is not a decorative
tweak.

Create `.sdd/changes/notif-recipient-rulings/` **before** touching product code:

| File | Must contain |
|---|---|
| `proposal.md` | why (the user's problem: silent submit; duplicate approval rows; PL copies nobody can act on; inconsistent subtitles), scope (5 files + tests + changelog), the five rulings quoted, and the explicit reversal of the 2026-10-30 three-fan-out ruling |
| `design.md` | the resulting row contract (§6), the subtitle algorithm (§7), the PL-proposer nuance (§3 R1), the test matrix (§8), the traps (§12) |
| `tasks.md` | T1 emitter · T2 submit action · T3 approve merge · T4 NavNotifications subtitle · T5 partial · T6 tests · T7 changelog, each a checkbox |
| `review-log.md` | one honest reviewer round **or** an explicitly logged user waiver; never self-declare frozen |
| `sdd.yaml` | `name: notif-recipient-rulings`, `created: 2026-11-03`, `status: proposed` → `applied` |

Load the **`sdd-workflow`** skill and follow its stages. Do not archive the lane
yourself — ask the user (§15).

---

## 2. Read first (before the proposal)

1. `prompts/for-ai-to-read/AGENTS.md` — standing rules, port map, DB law.
2. `prompts/for-ai-to-read/my-coding-rules.md` — SDD gate, commit prefixes, UI rules.
3. `prompts/for-ai-to-read/CodingMAIN.md` — §4 DB safety, §10 conventions, §10.6 git.
4. `docs/demo-db-data-changes.md` (top entry) + `tests/helpers/receipt-target-guard.ts`.
5. The five anchors named in §4.

---

## 3. The rulings (settled — do not re-ask)

| # | Ruling |
|---|---|
| **R1 — submit** | The **non-PL proposer** gets a confirmation row: title `Replacement request submitted`, body `<CODE> (<TYPE>) · W<w> · awaiting PL approval`, link `/my-request-history-ui`, `type=update`. A **PL proposer keeps their single existing queue row** — do **not** add a second row and do **not** filter the PL fan-out. |
| **R2 — email** | The proposer is **never emailed** their own "new replacement request", whatever their role. Skip them in the mail loop only; every other PL still gets the mail. |
| **R3 — approve** | **Merge.** The owner gets **one** row: title `Replacement class updated`, body `<classFact> · approved by <decider> · your timetable is updated`, link `/my-timetable-ui`, `type=approved`, carrying `request_id` and `class_session_id`. The separate `Replacement approved` owner row is removed. Cohort students keep their existing `approvalNotice` row unchanged. |
| **R4 — class cancelled** | Owner + cohort students **only**. Delete the department-PL fan-out entirely (reverses the 2026-10-30 ruling). The owner is the actor and is still notified; a PL who owns the class is the owner, so they still get their row. |
| **R5 — subtitle** | Every notification type, **every state** (unread, read, decided, inactive, hidden, manage-mode pick) shows the "other party" staff line as `Name (staff_id)`:<br>• PL/student rows → the staff the row is about: proposer (request rows) or class lecturer (class rows);<br>• staff self rows → the PL who acted (the approver);<br>• a self-only event (owner cancelling their own class) → **omit** the line. |

Note for R1: the PL proposer's queue row still exists and still points at the
approval queue. That self-approval path is a **separate** issue (§13) — do not
change it here.

---

## 4. The five anchors (verified 2026-11-03)

```
app/Support/Notifications/NotificationEmitter.php     :34 requestSubmitted, :56 requestDecided,
                                                      :81 approvalNotice, :110 classCancelled
app/Actions/SubmitReplacementRequest.php              :194–228 the after-commit closure (mail loop :208)
app/Actions/ApproveRequest.php                        :200–224 the after-commit closure
app/Livewire/NavNotifications.php                     :396 resolveRequests, :408 eager load,
                                                      :445 the display loop, :487 _display gate
resources/views/partials/notif-row.blade.php          :110 the subtitle span
```

---

## 5. Implementation spec — file by file

### 5.1 `NotificationEmitter`

**`requestSubmitted()`** (`:34`) — add the proposer as the first argument and
append the conditional row. Target shape:

```php
/** @param Collection<int, User> $pls */
public function requestSubmitted(
    User $proposer,
    Collection $pls,
    string $moduleCode,
    string $moduleType,
    int $requestId,
    int $week,
): void {
    $this->commit(function () use ($proposer, $pls, $moduleCode, $moduleType, $requestId, $week): void {
        foreach ($pls as $pl) {
            $this->write($pl, 'pl', 'submitted', 'New replacement request',
                $moduleCode.' ('.$moduleType.') · W'.$week.' · submitted by '.$proposer->name,
                '/request-approval-ui', $requestId, null, $week);
        }

        // R1: the non-PL proposer has no copy today; a PL proposer already holds
        // the queue row above, so this must not fire for them.
        if ($proposer->lecturer?->is_pl !== true) {
            $this->write($proposer, 'lecturer', 'update', 'Replacement request submitted',
                $moduleCode.' ('.$moduleType.') · W'.$week.' · awaiting PL approval',
                '/my-request-history-ui', $requestId, null, $week);
        }
    });
}
```

There is **no `isPl()` accessor** on `User`; use the relation as shown.

**New `replacementConfirmed()`** (place it after `requestDecided()`):

```php
public function replacementConfirmed(
    User $owner, ClassSession $session, int $requestId, int $week, string $deciderName,
): void {
    $this->commit(function () use ($owner, $session, $requestId, $week, $deciderName): void {
        $this->write($owner, 'lecturer', 'approved', 'Replacement class updated',
            $this->classFact($session, $week).' · approved by '.$deciderName.' · your timetable is updated',
            '/my-timetable-ui', $requestId, (int) $session->id, $week);
    });
}
```

`requestDecided()` stays as-is (reject still uses it; its `approved` branch stays
valid but unused by the app).

**`classCancelled()`** (`:110–152`) — delete step 3 (the whole
`$deptId…$pls…foreach` block), delete the now-unused
`use App\Models\Lecturer;`, and rewrite the docblock: the fan-out is the owner's
record row + every cohort student. Keep the actor comment and the student loop.

### 5.2 `SubmitReplacementRequest`

In the `DB::afterCommit` closure (`:194`): add `$proposer` to the `use (...)` list
(the method already receives the `User`). In the mail loop (`:208`):

```php
foreach ($pls as $pl) {
    if ((int) $pl->id === (int) $proposer->id) {
        continue;   // R2 — never email a lecturer their own request
    }
    Mail::to((string) $pl->email)->queue(new ReplacementSubmitted(/* unchanged args */));
}
```

Then pass the proposer: `->requestSubmitted($proposer, $pls, $moduleCode, $sessionType, $requestId, (int) $anchor->week_number)`.

Do not change the `if ($pls->isEmpty()) return;` early return, and do not change
the mailable or its template.

### 5.3 `ApproveRequest`

Replace the `requestDecided(..., 'approved', ...)` block (`:207–215`) with a
**single** call placed **before** the cohort loop:

```php
if ($proposer !== null) {
    app(NotificationEmitter::class)->replacementConfirmed(
        $proposer, $session, (int) $locked->id, (int) $locked->week_number, (string) $decider->name,
    );
}
```

The cohort loop calling `approvalNotice()` per cohort stays untouched. Writing
the owner row **inside** that loop would duplicate it once per cohort — this is
the trap.

### 5.4 `NavNotifications`

1. Eager load (`:408`): add `'approver.lecturer'` to
   `with(['classSession.module', 'replacementTimeSlot.venue', 'proposer.lecturer'])`.
2. `resolveRequests()` returns **early** when there are no request ids (`:402`).
   Attach the staff lines after that branch too — call a new
   `attachStaffLines($rows, $requests)` at both exits (`return $rows;` at
   `:402` and `:499`). The first call passes an empty request collection.
3. `attachStaffLines()`:

```php
$classIds = $rows->filter(fn (Notification $n) => $n->request_id === null && $n->class_session_id !== null)
    ->pluck('class_session_id')->unique()->values();

$sessions = $classIds->isEmpty() ? collect()
    : ClassSession::query()->with(['lecturer.lecturer'])->whereIn('id', $classIds->all())->get()->keyBy('id');

foreach ($rows as $row) {
    $row->setAttribute('staff_line', $this->staffLine($row, $requests, $sessions));
}
```

4. The rule (`staffLine()` + `staffLabel()`):

```php
private function staffLine(Notification $row, Collection $requests, Collection $sessions): ?string
{
    $request = $row->request_id !== null ? $requests->get((int) $row->request_id) : null;

    if ($request !== null) {
        $subject = $request->proposer;
        if ($subject === null) return null;
        if ((int) $subject->id === (int) $row->user_id) {
            $actor = $request->approver;                 // the PL who decided
            return $actor !== null ? $this->staffLabel($actor) : null;
        }
        return $this->staffLabel($subject);
    }

    $session = $row->class_session_id !== null ? $sessions->get((int) $row->class_session_id) : null;
    $owner = $session?->lecturer;

    return $owner === null || (int) $owner->id === (int) $row->user_id ? null : $this->staffLabel($owner);
}

private function staffLabel(User $user): string
{
    $staffId = $user->lecturer !== null ? (string) $user->lecturer->staff_id : '';
    return $staffId !== '' ? $user->name.' ('.$staffId.')' : (string) $user->name;
}
```

**Do not** reuse the `_display` attribute for this. `_display` is attached only
to `submitted` rows with `reason === null` (`:487`), so anything stored there
disappears once the row is read. `staff_line` is independent and must survive.

### 5.5 `notif-row.blade.php`

Line 110 today:

```blade
@if($subtitle) <span class="notif-row-subtitle">{{ $subtitle }}</span> @endif
```

Change to prefer the composed subtitle, then the staff line:

```blade
@if($subtitle)
    <span class="notif-row-subtitle">{{ $subtitle }}</span>
@elseif($n->getAttribute('staff_line'))
    <span class="notif-row-subtitle">{{ $n->getAttribute('staff_line') }}</span>
@endif
```

When `staff_line` is null the rendered output must be **byte-identical** to
today. The partial is shared by the inbox, `pick` and `hidden` variants — one
change covers all three.

---

## 6. Resulting row contract (this is what the tests assert)

| Event | Recipient | `role_view` | `type` | Title | Link | ids |
|---|---|---|---|---|---|---|
| Submit | each PL | `pl` | `submitted` | New replacement request | `/request-approval-ui` | request, week |
| Submit | non-PL proposer | `lecturer` | `update` | Replacement request submitted | `/my-request-history-ui` | request, week |
| Approve | owner | `lecturer` | `approved` | Replacement class updated | `/my-timetable-ui` | request, session, week |
| Approve | each cohort student | `student` | `approved` | Replacement class updated (unchanged) | `/student-my-timetable-ui` | request, session, week |
| Reject | proposer | `lecturer` | `rejected` | Replacement rejected | `/my-request-history-ui` | request, week |
| Reminder | each PL | `pl` | `awaiting` | Approval still pending | `/request-approval-ui` | request, week |
| Class cancelled | owner | `lecturer` | `update` | Scheduled class cancelled | `/my-timetable-ui` | session, week |
| Class cancelled | each cohort student | `student` | `update` | Scheduled class cancelled | `/student-my-timetable-ui` | session, week |

No new `type` or `role_view` values. No row is written inside the transaction.

---

## 7. Subtitle worked examples (demo/testing identities)

| Reader | Row | `subject` | `subject == reader?` | Subtitle |
|---|---|---|---|---|
| 5516 (PL) | submitted (5770 proposed) | 5770 Lim Jia Zheng | no | `Lim Jia Zheng (5770)` |
| 5770 (owner) | own approval | 5770 | yes → actor = approver | `Surayaini Binti Basri (5425)` |
| student | approval notice | 5770 (proposer = class lecturer) | no | `Lim Jia Zheng (5770)` |
| student | class cancelled | 5770 (session lecturer) | no | `Lim Jia Zheng (5770)` |
| 5770 (owner) | own cancellation | 5770 | yes, no actor | *omitted* |
| 5770 (non-PL proposer) | own submit confirmation | 5770 | yes, no approver yet | *omitted* |

Confirm the ids on the lane you use (`psql -d class_replacement_testing -c …`);
the demo DB and the testing seed both carry 5770/5425/5516.

---

## 8. Tests — `tests/Feature/NotificationsBridgeTest.php`

Current state you must work with:

- `submitEvent()` (`:59`) calls `requestSubmitted($pls, …)` directly → it must
  pass a proposer. Pass `$this->coordinator()` (user 1, a PL) so the existing
  assertions keep **two** `submitted` rows and no extra `update` row.
- `test_submitted_rows_land_on_every_pl…` (`:71`) — unchanged expectations.
- **new** — non-PL proposer: build a non-PL lecturer (the `EmailQueueTest`
  helper shows how), call `requestSubmitted($nonPl, $pls, …)`, assert exactly
  one `update` row for that user (link `/my-request-history-ui`, title
  `Replacement request submitted`) and that the two PL rows are untouched.
- `test_approved_rows_follow_the_per_role_link_law` (`:87`) — rewrite to call
  `replacementConfirmed($owner, $session, id, week, 'Mohd Nur Rahmat Bin Mohd Taat')`
  and assert: one `approved` row, `role_view=lecturer`, link
  `/my-timetable-ui`, title `Replacement class updated`, body contains
  `approved by`.
- `test_class_cancelled_fans_out_to_owner_students_and_dept_pls` (`:101`) —
  rename to `…_to_owner_and_students`; assert the owner row, the student rows,
  and **zero** rows with `role_view='pl'` (user 2 must be absent).
- **new** — subtitle rendering with `Livewire::test(NavNotifications::class,
  ['variant' => 'panel', 'pageKey' => …, 'unreadOnly' => false])` as:
  1. a PL → `assertSeeHtml('Lim Jia Zheng (5770)')` for the submitted row;
  2. a student → `assertSeeHtml('Lim Jia Zheng (5770)')` for the approval notice;
  3. the owner after a decision → `assertSeeHtml('Surayaini Binti Basri (5425)')`;
  4. the owner for their own cancellation → assert that row has no
     `notif-row-subtitle` (assert on the surrounding markup, not the whole page).
- `test_the_island_renders_with_exactly_one_replacement_requests_query` (`:344`)
  — it filters SQL by `replacement_requests`; the new `approver.lecturer`
  eager load and the `ClassSession` batch are different tables, so it should
  still pass. Do **not** add any per-row `find()`.
- Lines `:279` (read-state arm) calls `requestSubmitted` too — update the
  argument list. It marks the row of `$this->pl()` (user 1) read; because the
  helper passes user 1 as the proposer (a PL), that row still exists.

`tests/Feature/EmailQueueTest.php` — the fixture proposer is a **non-PL**
(`Submit Mail Requester`), so `$plCount` at `:256` stays correct. Optional extra:
a PL-proposer test asserting `Mail::assertNotQueued(ReplacementSubmitted::class, fn ($m) => $m->hasTo($proposer->email))`.

---

## 9. Gates

```bash
php artisan test --filter="NotificationsBridgeTest|EmailQueueTest"
composer run lint:check
vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: the two files green (quote the measured counts in the report). If a
pre-existing red appears, prove it is unrelated before claiming so.

No migration exists in this lane. Prove it:

```bash
git diff --name-only | grep -E "^database/migrations/" && echo "STOP: unexpected migration" || echo "no migration"
```

## 10. Browser receipt on the disposable lane (recommended, never the demo DB)

```bash
DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
setsid nohup env DB_DATABASE=class_replacement_testing php artisan serve --host=127.0.0.1 --port=8030 \
  > /tmp/opencode/serve-8030.log 2>&1 &
# password for every seeded identity: Tarumt@2026
```

Walk it: 5770 submits → bell shows the new confirmation, no subtitle (self row);
5425 sees `New replacement request` with `Lim Jia Zheng (5770)`; 5425 approves →
5770 sees **one** `Replacement class updated` with `Surayaini… (5425)`; 5770
cancels a class → 5770's own row has no subtitle, a student's row shows
`Lim Jia Zheng (5770)`. Then:

```bash
pkill -f "[a]rtisan serve --host=127.0.0.1 --port=8030"
```

The `receipt-target-guard` is mandatory for any write spec; this lane does not
need one (no spec writes).

## 11. Changelog + commits

- `page-changelogs/notifications-panel-changelog.md` — one dated entry stating
  R1–R5 and the reversal of the 2026-10-30 three-fan-out ruling.
- `feat(notifications): …` for the behaviour; `test: …` for the test updates
  (or one commit if it is one unit); `docs: …` for the SDD lane + changelog.
- Never push. Never stage another session's files (see §12, trap 12).

## 12. Known traps (read before coding)

1. **Do not filter the PL fan-out** — R1 is additive only; a PL proposer keeps
   their queue row.
2. Write the merged owner row **once, outside** the cohort loop.
3. The subtitle rides `staff_line`, not `_display` (which dies on read).
4. `resolveRequests()` has an **early return** — attach staff lines at both exits.
5. Keep `notif-row` byte-identical when `staff_line` is null (frozen partial).
6. Capture the `User $proposer` in the mail closure; the closure currently only
   has `$proposerName`.
7. `requestSubmitted` has two test callers (`:61`, `:279`) plus the action.
8. `submitted` rows already render `_display['subtitle']` — do not double it.
9. No `isPl()` accessor; use `$proposer->lecturer?->is_pl`.
10. Do not add a per-row query — use the two batched loads.
11. The `_display` degradation after read is **out of scope** (§13).
12. **Another session is working in this repo.** Modified
    `tests/cancel-class.spec.ts`, `tests/notifications-island-testing-db.spec.ts`,
    `tests/request-approval.spec.ts`, `tests/submission-conflict-refresh.spec.ts`,
    `tests/ui-regression.spec.ts`, `tests/venue-db.spec.ts`,
    `tests/venue-timetable.spec.ts`, plus untracked
    `tests/helpers/receipt-target-guard.ts`, `.sdd/changes/receipt-target-guard/`,
    `database/seeders/TestingFixtureSeeder.php`. Never stage, revert or reformat
    them; `git add` only the files this lane touches.

## 13. Out of scope (report, do not fix)

- The approval queue is unscoped and accepts the PL's own request (self-approval).
- A `submitted` row still loses its composed title/ID/schedule after read.
- Mail **templates** (only the recipient loop changes).
- Any UI redesign of the panel.
- The demo copy (`CRS-demo`) — do not touch it unless the user asks.

## 14. How to talk to this user

- Phrase every question as: **"So the question in plain terms: …"**, then one
  sentence of context, then a recommendation with its trade-off. One question at
  a time; no jargon-only questions.
- Do not ask about R1–R5.
- If a genuinely new decision appears, stop and ask; do not guess.

## 15. Archive

Only on the user's explicit word: move `.sdd/changes/notif-recipient-rulings/`
to `.sdd/archive/`, update `prompts/sdd.md`, keep the changelog entry.

## 16. Final report

- Lane path + status; commits (hashes, prefixes).
- Test counts (measured), lint/phpstan results.
- The browser receipt summary (who saw which row/subtitle).
- Files changed; "no migration" evidence.
- Any red with its classification; any decision still open.
- Confirmation that no demo DB was written and no push happened.

## 17. Definition of done

- [ ] SDD lane exists and is honestly reviewed (or waived and logged)
- [ ] R1–R5 implemented exactly as specified (row contract §6)
- [ ] Subtitle survives read / decided / inactive / hidden / pick states
- [ ] `notif-row` output byte-identical when `staff_line` is null
- [ ] Tests updated + new tests green; lint + phpstan clean
- [ ] Changelog entry written; commits made; no push
- [ ] Demo DBs untouched; `:8030` stopped
