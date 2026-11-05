# Design — notif-recipient-rulings

**Batch 2** · 2026-11-03 · baseline = **frozen** `proposal.md` + `explore-brief.md`.
Carries the user's two approved refinements (2026-11-03): the same-text guard (§4.4) and
the single-row occurrence count in four states (§5, T5b).

---

## 1. The row contract after this lane

| Event | Recipient | `role_view` | `type` | Title | Link | ids | staff line |
|---|---|---|---|---|---|---|---|
| Submit | each PL | `pl` | `submitted` | New replacement request | `/request-approval-ui` | request, week | the proposer |
| Submit | non-PL proposer | `lecturer` | `update` | Replacement request submitted | `/my-request-history-ui` | request, week | *omitted* (self row, nobody acted yet) |
| Approve | owner | `lecturer` | `approved` | Replacement class updated | `/my-timetable-ui` | request, session, week | the decider — or the proposer when the owner did not propose |
| Approve | each cohort student | `student` | `approved` | Replacement class updated *(unchanged)* | `/student-my-timetable-ui` | request, session, week | the proposer |
| Reject | proposer | `lecturer` | `rejected` | Replacement rejected | `/my-request-history-ui` | request, week | the decider |
| Reminder | each PL | `pl` | `awaiting` | Approval still pending | `/request-approval-ui` | request, week | the proposer |
| Class cancelled | owner | `lecturer` | `update` | Scheduled class cancelled | `/my-timetable-ui` | session, week | *omitted* (self-only event) |
| Class cancelled | each cohort student | `student` | `update` | Scheduled class cancelled | `/student-my-timetable-ui` | session, week | the class lecturer |

No new `type` or `role_view` value. Every write still happens inside `DB::afterCommit`.

## 2. Emitter (`app/Support/Notifications/NotificationEmitter.php`)

### 2.1 `requestSubmitted()` — R1

Proposer becomes the **first** parameter; the redundant `$proposerName` goes away (it is
`$proposer->name`), so the body text stays byte-identical.

```php
/** @param  Collection<int, User>  $pls */
public function requestSubmitted(User $proposer, Collection $pls, string $moduleCode,
    string $moduleType, int $requestId, int $week): void
```

PL loop unchanged, then:

```php
if ($proposer->lecturer?->is_pl !== true) {                    // trap 9: no isPl() accessor
    $this->write($proposer, 'lecturer', 'update', 'Replacement request submitted',
        $moduleCode.' ('.$moduleType.') · W'.$week.' · awaiting PL approval',
        '/my-request-history-ui', $requestId, null, $week);
}
```

The PL fan-out is **not** filtered (trap 1): a PL proposer keeps the queue row and gets no
second row; the self-approval path that row exposes stays out of scope (§13).

### 2.2 `replacementConfirmed()` — R3 (new, after `requestDecided()`)

```php
public function replacementConfirmed(User $owner, ClassSession $session, int $requestId,
    int $week, string $deciderName): void
```

writes one `approved` row: title `Replacement class updated`, body
`classFact($session, $week).' · approved by '.$deciderName.' · your timetable is updated'`,
link `/my-timetable-ui`, `request_id`, `class_session_id`, `week`.

`requestDecided()` is untouched: rejections use it, and its `approved` branch stays
valid-but-unused (removing it would be a change beyond R3).

### 2.3 `classCancelled()` — R4

Delete step 3 in full (the `$deptId … $pls … foreach` block) and the then-unused
`use App\Models\Lecturer;`. Steps 1 (the actor/owner record row, link `/my-timetable-ui`)
and 2 (every cohort student) stay. The docblock is rewritten: **two** fan-outs, and it
records that this reverses the 2026-10-30 three-fan-out ruling and the dedupe that ruling
needed.

## 3. Actions

### 3.1 `SubmitReplacementRequest::succeed()` — R1 wiring + R2

The after-commit closure currently captures `$proposerName` only; it must also capture the
model (trap 6): `use ($requestId, $moduleCode, $sessionType, $proposerName, $proposer, $anchor, $span)`.

Mail loop (before `Mail::to(...)`):

```php
if ((int) $pl->id === (int) $proposer->id) { continue; }   // R2 — by id, not by address
```

Emitter call gains the proposer (first argument). Untouched: the
`if ($pls->isEmpty()) return;` early return, the mailable, its template and the
capture-at-queue-time facts.

### 3.2 `ApproveRequest` — R3 merge

Inside the existing notification after-commit closure, **before** the cohort loop:

```php
if ($proposer !== null) {
    app(NotificationEmitter::class)->replacementConfirmed(
        $proposer, $session, (int) $locked->id, (int) $locked->week_number, (string) $decider->name,
    );
}
foreach ($session->cohorts as $cohort) { … approvalNotice() … }
```

The `requestDecided(…'approved'…)` call is removed. Written inside the loop instead, the
owner row would repeat once per cohort (trap 2).

## 4. The staff line — R5

### 4.1 Why it cannot ride `_display`

`_display` is attached only to `submitted` rows with `inactive_reason === null`
(`NavNotifications.php:487`) — it disappears the moment the row is read. R5 demands the
line in *every* state, so the line is its own computed attribute, `staff_line`.

### 4.2 Two batched sources (never a per-row query)

| Source | How it is loaded |
|---|---|
| the request's proposer and approver | the existing `ReplacementRequest` batch, extended to `with([... 'proposer.lecturer', 'approver.lecturer'])` |
| the class's lecturer (rows with `request_id === null`) | **one** `ClassSession` batch: `with(['lecturer.lecturer'])->whereIn('id', $classIds)` |

`approver_id` is stamped by both decisions (`ApproveRequest.php:146`,
`RejectRequest.php:139`), so "the PL who acted" resolves for approvals *and* rejections —
no new column, no migration.

### 4.3 The rule

```
request row  → subject = request->proposer
               reader == subject → the actor (request->approver) | null when nobody acted
               otherwise         → the subject
class row    → owner = session->lecturer
               missing session / reader == owner → null (self-only event: omit)
label(user)  → "Name (staff_id)"   | "Name" when the lecturer row or staff_id is missing
```

Worked examples: PL 5516 reads a 5770 submit → `Lim Jia Zheng (5770)`; 5770 reads their own
approval → `Surayaini Binti Basri (5425)`; a student reads the cancellation →
`Lim Jia Zheng (5770)`; 5770 reads their own cancellation → nothing; 5770 reads their own
submit confirmation → nothing.

### 4.4 Both exits of `resolveRequests()` (trap 4)

`resolveRequests()` returns early (`:402`) when no row carries a `request_id` — a
cancellation-only inbox. Both exits flow through a new
`attachStaffLines(Collection $rows, Collection $requests)`; the early exit passes
`collect()` for the requests.

The class batch is gated on **columns** (`request_id === null && class_session_id !== null`),
never on `inactive_reason`: at that early exit the classifier has not run yet, so an
attribute-based gate would silently skip every class row. That pre-existing asymmetry is
**do not fix** territory for this lane.

### 4.5 The partial — the same-text guard (user refinement 1)

`notif-row.blade.php` computes `$subtitle` at `:57`; add beside it:

```blade
$staffLine = $n->getAttribute('staff_line');
```

and replace the subtitle line with **two independent blocks**:

```blade
@if($subtitle) <span class="notif-row-subtitle">{{ $subtitle }}</span> @endif@if($staffLine && $staffLine !== $subtitle) <span class="notif-row-subtitle">{{ $staffLine }}</span> @endif
```

Why compare the **text** and not "is `$subtitle` set":

- today the two guards behave identically — the only row that produces a `$subtitle` is the
  live `submitted` row, and there the composed subtitle *is* exactly the staff line, so the
  comparison suppresses the duplicate;
- if the composed subtitle ever changes into something else (schedule text, a reason, …),
  the row then shows both lines — the context line and the staff line — which is correct,
  whereas an "is `$subtitle` set" guard would silently drop the staff line forever;
- both null (a self-only event) ⇒ **nothing is emitted** and the row HTML stays
  byte-identical to today, including the surrounding whitespace (the two blocks stay on one
  source line so the newline pattern does not change).

Consequence to state plainly: when `$subtitle` and `$staffLine` differ, a row carries **two**
`notif-row-subtitle` spans. §5's T5b counts them, so this can never drift unnoticed.

## 5. Tests

### `tests/Feature/NotificationsBridgeTest.php`

| # | Case | Assertion |
|---|---|---|
| T1 | `submitEvent()` helper | passes `$this->coordinator()` (a PL, user 1) → the existing "two `submitted` rows" expectation holds and no extra `update` row appears |
| T2 | non-PL proposer (5770) | exactly one row for that user: `type=update`, title `Replacement request submitted`, link `/my-request-history-ui`, `role_view=lecturer`; the two PL rows are untouched |
| T3 | `replacementConfirmed()` | one `approved` row for the owner, `role_view=lecturer`, link `/my-timetable-ui`, title `Replacement class updated`, body contains `approved by`, `request_id` **and** `class_session_id` set |
| T4 | cancellation | renamed `…_to_owner_and_students`: the owner row, the student rows, and **zero** rows with `role_view='pl'` (user 2 absent) |
| T5 | subtitle rendering (`Livewire::test(NavNotifications::class, ['variant' => 'panel', …])`) | (a) a PL on a submit row → `Lim Jia Zheng (5770)`; (b) a student on the approval notice → same; (c) the owner after a decision → `Surayaini Binti Basri (5425)`; (d) the owner's own cancellation → the row has **no** `notif-row-subtitle` span |
| **T5b** | **occurrence count, user refinement 2** | for one row in **four states — unread, read, decided, hidden** — the panel contains **exactly one** subtitle span and its text is exactly `Lim Jia Zheng (5770)`. Counting is done on `<span class="notif-row-subtitle">…</span>` occurrences, **not** on the raw panel text: the row tooltip legitimately repeats the name (`Staff: 5770 — Lim Jia Zheng`, `:594`), so a text count would be a false alarm. Hidden = the row carries `hidden_at`, so it renders in the shelf |
| T6 | ONE-query law | `test_the_island_renders_with_exactly_one_replacement_requests_query` stays green — the new `approver.lecturer` eager load and the `ClassSession` batch are different tables; no per-row `find()` |
| T7 | read-state arm (`:279`) | `requestSubmitted` gains the proposer argument; user 1 is a PL, so their queue row still exists and is still the one marked read |

### `tests/Feature/EmailQueueTest.php`

| # | Case | Assertion |
|---|---|---|
| T8 | existing submit test | the fixture proposer is a non-PL, so `$plCount` (`:256`) stays exactly right |
| T9 | new: PL proposer | `Mail::assertNotQueued(ReplacementSubmitted::class, fn ($m) => $m->hasTo($proposer->email))` and the other PLs are still queued (`$plCount - 1`) |

## 6. Traps (mapped, so none is missed)

| # | Trap | Where handled |
|---|---|---|
| 1 | never filter the PL fan-out | §2.1 |
| 2 | owner row written once, outside the cohort loop | §3.2 |
| 3 | the line rides `staff_line`, not `_display` | §4.1 |
| 4 | both exits of `resolveRequests()` | §4.4 |
| 5 | partial unchanged when the line is null | §4.5 |
| 6 | capture `$proposer` in the mail closure | §3.1 |
| 7 | three `requestSubmitted` callers (action + two tests) | §5 T1/T7 |
| 8 | no doubled subtitle | §4.5 (same-text guard) + T5b |
| 9 | no `isPl()` accessor | §2.1 |
| 10 | no per-row query | §4.2 |
| 11 | `_display` degradation after read stays out of scope | §4.1 |
| 12 | another session's dirty files are never staged | tasks T10 |

## 7. Gates and receipts

`php artisan test --filter="NotificationsBridgeTest|EmailQueueTest"` · `composer run
lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` · no-migration
proof (`git diff --name-only | grep database/migrations` must be empty) · browser receipt on
the disposable `:8030` only (demo DBs untouched, `:8030` stopped afterwards). No Playwright
spec writes, so `receipt-target-guard` is not needed.
