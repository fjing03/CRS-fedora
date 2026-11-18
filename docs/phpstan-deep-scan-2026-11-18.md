# PHPStan deep scan — 18 Nov 2026

**Read this before touching the approval, rejection, or cancellation paths.**

This is a handover note for a future AI session. It records what was scanned, what was
found, what was checked by hand, and what remains unproven.

---

## TL;DR

The project **passes its normal check cleanly** (`composer types:check`, level 7, 0 errors).
A one-off *deep* scan at maximum strictness found **30 findings in 6 files**. Reading the
code by hand narrows that to **two spots worth fixing now**, three more that are cheap
insurance, and the rest are type-precision noise or false positives.

Nothing here is a confirmed live bug. Everything here is a *risk* that the database
constraints mostly prevent — except in two conditions: a race between two requests, and
a queued mail payload that outlives the row it names.

**Do not believe the five findings below on this report's word.** They are static
inferences from reading types, not observed failures. No one has reproduced any of them.
Re-verify each one first — see "Re-verify all five before you believe them" — and treat
this document as a list of accusations, not convictions.

---

## How these findings were produced

```bash
# the normal gate — passes, 0 errors
composer types:check

# the deep scan that produced this report
vendor/bin/phpstan analyse --no-progress --level=max --memory-limit=1G
```

| Item | Value |
|---|---|
| PHP | 8.5.11 |
| PHPStan | 2.2.2 |
| Larastan | v3.10.0 |
| Project gate | level **7**, paths `app/ bootstrap/app.php config/ database/ routes/` |
| Deep scan | level **max** |
| Deep-scan result | 30 findings, 6 files |

Two things that will confuse the next reader:

1. **The output is one line of JSON, not a table.** That is `laravel/pao`
   ("agent-optimized output for PHP testing tools") rewriting PHPStan's output. It is
   intentional. The JSON contains `error_details` keyed by file, each with `line`,
   `message` and `identifier`.
2. **Without `--memory-limit=1G` the run dies** with
   `reached configured PHP memory limit: 128M` in a parallel worker. Level 7 does not
   need the flag; level max does.

### Which skills produced these findings

None. No agent skill found these — **PHPStan did**. The skills in this workspace were not
used for detection and claiming otherwise would be false.

The honest attribution is:

- `vendor/bin/phpstan analyse` — found all 30 mechanically.
- manual reading of the flagged lines — separated real risk from noise.
- **not used:** `diagnosing-bugs`, `tdd`, `code-review`, `impeccable`.

Those four are the *follow-up*, not the origin. See "What to explore next".

---

## Where the 30 findings sit

| Category | Count | Real risk? |
|---|---|---|
| `property.nonObject` — reading a property of something that may be missing | 5 | **yes, the important ones** |
| `argument.type` — passing something possibly empty where a real value is required | 7 | low (auth-guarded) |
| `cast.int` / `cast.string` — casting untyped request input | 13 | no — type precision |
| `method.nonObject` — calling a method on an untyped closure parameter | 4 | **no — false positive** |
| `return.type` — a declared array shape is more precise than what is returned | 1 | no |

---

## The five "might be empty" spots

All five are the same shape: a lookup can return nothing, and the next line reads from it
without checking. In PHP that is a fatal error, which surfaces as a 500 page, not a
friendly message.

### 1. `app/Concerns/CancelsClasses.php:81` — the one most worth fixing

```php
$cancelled = (new CancelClass)(auth()->user(), $session, $weekNumber, $reason, $otherDetail);
if (! $cancelled) {
    return ['ok' => false, 'hint' => 'This class is already cancelled.'];
}

$exception = ClassException::query()
    ->where('class_session_id', $session->id)
    ->where('week_number', (int) $weekNumber)
    ->first();

return ['ok' => true, 'exceptionId' => (int) $exception->id];   // <-- $exception may be null
```

**Root cause.** The cancel action returns `true`, then the code re-queries the row it
expects that action to have written. The re-query is a separate statement; it trusts that
the write is visible and that the lookup matches the same week.

**Consequence.** If the re-query misses, the cancellation **has already happened** and the
user is shown an error instead of a result. They retry, and now get "This class is already
cancelled." The database is right, the screen lied, and the audit trail looks suspicious.

**Fix direction.** Do not re-query for something the action already produced — have
`CancelClass` return the exception (or its id). If it must stay a query, treat `null` as a
failure that reports the truth: changed, but receipt unavailable.

### 2. `app/Actions/RejectRequest.php:172` — unguarded line between two guarded ones

```php
$start   = (string) ($slotIds->isNotEmpty() ? TimeSlot::find((int) $slotIds->first())->start_time ?? '' : $anchor?->start_time);
$end     = (string) ($slotIds->isNotEmpty() ? TimeSlot::find((int) $slotIds->last())->end_time   ?? '' : $anchor?->end_time);
$venueId = $slotIds->isNotEmpty() ? TimeSlot::find((int) $slotIds->first())->venue_id : $anchor?->venue_id;  // <-- no ?? ''
```

**Root cause.** The two neighbouring lines use `?? ''`, which survives a missing slot.
The venue line does not. It is a one-character omission, not a design flaw — which is
exactly why it is worth fixing.

**Consequence.** A rejection whose slot rows no longer resolve throws while composing the
notification, so the rejection fails after the decision was made.

**Fix direction.** `?->venue_id`, and let the existing `$venueId !== null` check below it
handle the absence — that branch already does the right thing.

### 3. `app/Concerns/CancelsClasses.php:472 / 479` — inside list rendering

```php
$slot = $r->replacementTimeSlot;      // may be null
$endAt = Carbon::parse((string) $slot->start_time)->addMinutes(30 * $cells);
...
'start' => substr((string) $slot->start_time, 0, 5),
```

**Root cause.** The relation is used without a null check, in a method that maps a list.

**Consequence.** One bad row takes out the **whole day's list**, not just that row, because
the mapping runs before rendering.

**Fix direction.** Filter rows whose slot is missing before mapping, or skip inside the
`map`. Do not let one orphan blank the page.

### 4. `app/Actions/SubmitReplacementRequest.php:263`

```php
$moduleCode = (string) $session->module->module_code;
```

**Root cause.** `module` is a relation; the FK protects it in practice, so this is
theoretical.

**Consequence.** Would throw in the success path, after the request row exists — so the
request is created but the response errors.

**Fix direction.** `$session->module?->module_code ?? ''`, matching how
`CancelsClasses` already writes the same field at line 478.

### 5. `app/Actions/ApproveRequest.php:98`

```php
return $this->loser($validity->reason);   // reason is string|null, loser() wants string
```

**Root cause.** The validity object models "invalid" and "reason" as independent, so the
type allows invalid-with-no-reason.

**Consequence.** A refusal path that fails to produce copy: the admin sees a 500 where they
should see a refusal message. This is the one finding where the *contract* is the bug, not
the line.

**Re-verified — cleared.** `app/Services/ValidityResult.php` has a private constructor and
exactly two factories: `valid()` returns (`true`, null) and `conflict(string $reason)`
returns (`false`, a non-null string). So when `valid` is false, `reason` cannot be null.
The type simply cannot express that correlation. **Verdict: false positive at runtime.**

**Fix direction.** Not a null guard at the call site. If you touch it, make the contract
say what it means — a union type, or an `invalidReason(): string` accessor that throws when
called on a valid result.

---

## The four auth findings — low risk, worth one guard

`CancelsClasses.php` lines 71, 136, 389, 398 pass `auth()->user()` into actions that
declare a non-null `User`. The routes are authenticated, so this cannot normally be null.

It becomes real in one case only: the session expires while a long request is in flight.
A single `$user = auth()->user() ?? abort(403);` near the top of the trait removes four
findings and one real edge case.

---

## The 19 that are not bugs

- **13 × `cast.int` / `cast.string`.** Casting request input. PHPStan cannot know the
  payload type; the cast is the intended handling. Leave them.
- **4 × `method.nonObject`** (`CancelsClasses.php:505-508`). The `whereNotExists` closure
  parameter has no type hint, so `$q` is `mixed`. Typing it as
  `\Illuminate\Database\Query\Builder` removes all four **legitimately** — this is a
  missing annotation, not a defect.
- **1 × `return.type`** (`:435`). The docblock promises a tighter array shape than the
  method returns. Either loosen the docblock or build the array exactly.

---

## What the database already protects you from

Checked, because it changes the severity:

- `replacement_requests.replacement_time_slot_id` → `cascadeOnDelete()` on `time_slots`.
- `class_exceptions.class_session_id` → `cascadeOnDelete()`.
- **No `SoftDeletes` anywhere in `app/Models`.** Nothing is hidden-but-present.
- `time_slots.class_session_id` is nullable, so a slot can exist without a session — but
  the reverse (a request pointing at nothing) is blocked.

So the "missing row" state mostly cannot be reached by ordinary deletion. It remains
reachable through:

1. **a queued job or mail** holding raw ids after the row is gone (facts are captured at
   queue time, but ids are re-resolved in at least one path), and
2. **a race** between two requests in the same second.

That is why findings 1–3 matter: they are the paths where the guarantee does not hold.

---

## Re-verify all five before you believe them

**Assume this report is wrong until you have evidence.** PHPStan reads types, not
invariants: it cannot know that a private constructor forbids a state, that a foreign key
cascades, or that a caller already checked. On the first re-verification pass, one of the
five was cleared outright and a second turned out much narrower than it read. Expect more
of that.

For each finding, answer these three questions before changing any code:

1. **Can the state exist?** Find the write path that could actually produce the missing
   value. If no code path can produce it, it is theoretical.
2. **Is the value read after the guarantee lapses?** A transient state inside one
   transaction is safe. A read after commit, or inside a queued job, is not.
3. **Does a test fail today?** If you cannot make it go red, you have not confirmed a bug.

Then record a verdict in this file:

| Verdict | Meaning | Allowed action |
|---|---|---|
| CONFIRMED | a red test exists | fix it |
| THEORETICAL | no reachable path found, but the guard is cheap | fix only if it is one line and obviously correct |
| FALSE POSITIVE | a proven invariant rules it out | leave the code alone; improve the type instead |

### Per-finding leads

**#5 `ApproveRequest.php:98` — already cleared.** `ValidityResult`'s private constructor
means invalid-with-no-reason cannot be built. See its entry above. **False positive.**

**#1 `CancelsClasses.php:81` — check the write, not the read.** Read `CancelClass` and
confirm it writes `(class_session_id, week_number)` with *exactly* the values re-queried
here, in the same transaction. The failure to hunt for is a week mismatch — the action
writing week `$weekNumber` as a string, or a different week index than the query casts —
and whether the action can return `true` without writing at all.

**#2 `RejectRequest.php:172` — find where `$slotIds` comes from.** The 2026-11-07 span
migration's own comment says `replacement_request_slots` rows are **deleted** by
`RejectRequest` to free the cells, leaving only the anchor as evidence. So establish:
(a) does `$slotIds` hold junction ids or `time_slots` ids, (b) do those rows still exist
when `queueMail` runs, and (c) can deleting a junction row strand the `TimeSlot::find()`
calls on either side of the venue line.

**#3 `CancelsClasses.php:472/479` — prove the relation can be null.** The FK cascades and
there are no soft deletes, so the slot should exist for as long as the request does. Hunt
for any path that nulls or rewrites `replacement_time_slot_id` (the span work added a
column beside it) before accepting this one.

**#4 `SubmitReplacementRequest.php:263` — check the FK.** If `class_sessions` points at
`modules` under a restricting key, this is theoretical. The one-line `?->` is still fine;
just do not describe it later as a fix for a crash you never saw.

### The rule that matters

**If a verdict is FALSE POSITIVE, say so in this file and leave the code alone.** Adding
guards because a static analyser guessed is how working systems collect dead branches that
hide the real failure when it finally arrives.

---

## Guardrails for whoever fixes this

- **Do not add `@phpstan-ignore`, `@phpstan-ignore-next-line`, or a baseline entry** to
  silence these. The analyser is right; the code is the thing to change.
- **Do not widen a parameter to `?User` or return `mixed`** to make a message disappear.
- **Do not add casts just to quieten a warning.**
- Fix the underlying assumption, then prove it with a test.
- The level-7 gate must stay green after every change: `composer types:check`.

---

## What to explore next

Work these in order. Each is small and independently verifiable.

1. **Re-verify all five findings first — none of them have been reproduced.** Work through
   "Re-verify all five before you believe them" above. For any you cannot make fail, record
   the verdict and leave the code alone. `diagnosing-bugs` describes this as the whole job:
   get a feedback loop that goes red on *this* bug before changing anything.
2. **Check queued payloads.** For every `Mail::`/`dispatch` that captures slot ids, confirm
   whether the id is resolved at queue time or render time. Any render-time lookup is a
   place a cascade cannot save you.
3. **Look for the same shape elsewhere.** The pattern is "relation or `find()` without a
   check, followed by `->property`". Search `app/` for `find(` and `->` in one statement.
   PHPStan already found five; views and string-built queries are not covered.
4. **Re-run the deep scan after fixes** and confirm the count drops for the right reason.
   A finding disappearing because a docblock changed is not a fix.
5. **Confirm the false positives stay false.** Add the `Query\Builder` type hint, re-run,
   and check that the four `method.nonObject` findings actually go away rather than moving.
6. **Decide about level.** Level 7 as the gate, max as an occasional deep scan, is
   deliberate and should stay that way — 30 findings where 19 are noise would train people
   to ignore the output.

## Skills to use for that work

| Skill | Use it for |
|---|---|
| `diagnosing-bugs` | findings 1–3: build the failing signal before guessing at a fix |
| `tdd` | lock each guard behaviourally, at a seam you have agreed |
| `code-review` | check the fix diff against this repo's standards before committing |
| `domain-modeling` | if "orphaned request" / "missing slot" is a real domain state, name it in `CONTEXT.md` instead of guarding it inline |
| `impeccable` | not this. It reviews UI; it will not find these |

## Do not

- Do not run the full test suite with `--env=testing`. There is no `.env.testing`; it would
  boot the demo database. Select the test database with
  `DB_DATABASE=class_replacement_testing`.
- Do not reseed or wipe the demo databases.

---

## Appendix — all 30 findings

| # | Location | Message | Identifier |
|---|---|---|---|
| 1 | `app/Actions/ApproveRequest.php:98` | Parameter #1 $reason of method App\Actions\ApproveRequest::loser() expects string, string\|null given. | `argument.type` |
| 2 | `app/Actions/CancelClassBatch.php:298` | Cannot cast mixed to int. | `cast.int` |
| 3 | `app/Actions/CancelClassBatch.php:450` | Cannot cast mixed to int. | `cast.int` |
| 4 | `app/Actions/RejectRequest.php:142` | Parameter #4 $slotIds of method App\Actions\RejectRequest::queueMail() expects Illuminate\Support\Collection<int, int>, Illuminate\Support\Collection<(int\|string), mixed> given. | `argument.type` |
| 5 | `app/Actions/RejectRequest.php:172` | Cannot access property $venue_id on App\Models\TimeSlot\|null. | `property.nonObject` |
| 6 | `app/Actions/ReleaseRequestSlots.php:83` | Cannot cast mixed to int. | `cast.int` |
| 7 | `app/Actions/SubmitReplacementRequest.php:207` | Parameter #3 $cohortIds of method App\Services\MatrixIntersectionEngine::validateSlot() expects array<int, int>, array<mixed> given. | `argument.type` |
| 8 | `app/Actions/SubmitReplacementRequest.php:263` | Cannot access property $module_code on App\Models\Module\|null. | `property.nonObject` |
| 9 | `app/Concerns/CancelsClasses.php:71` | Parameter #1 $user of callable App\Actions\CancelClass expects App\Models\User, App\Models\User\|null given. | `argument.type` |
| 10 | `app/Concerns/CancelsClasses.php:81` | Cannot access property $id on App\Models\ClassException\|null. | `property.nonObject` |
| 11 | `app/Concerns/CancelsClasses.php:136` | Parameter #1 $user of callable App\Actions\CancelClassBatch expects App\Models\User, App\Models\User\|null given. | `argument.type` |
| 12 | `app/Concerns/CancelsClasses.php:257` | Cannot cast mixed to int. | `cast.int` |
| 13 | `app/Concerns/CancelsClasses.php:266` | Cannot cast mixed to int. | `cast.int` |
| 14 | `app/Concerns/CancelsClasses.php:285` | Cannot cast mixed to int. | `cast.int` |
| 15 | `app/Concerns/CancelsClasses.php:331` | Cannot cast mixed to int. | `cast.int` |
| 16 | `app/Concerns/CancelsClasses.php:335` | Cannot cast mixed to int. | `cast.int` |
| 17 | `app/Concerns/CancelsClasses.php:351` | Cannot cast mixed to int. | `cast.int` |
| 18 | `app/Concerns/CancelsClasses.php:366` | Cannot cast mixed to int. | `cast.int` |
| 19 | `app/Concerns/CancelsClasses.php:389` | Parameter #1 $user of callable App\Actions\CancelClassBatch expects App\Models\User, App\Models\User\|null given. | `argument.type` |
| 20 | `app/Concerns/CancelsClasses.php:398` | Parameter #1 $user of callable App\Actions\CancelPendingRequest expects App\Models\User, App\Models\User\|null given. | `argument.type` |
| 21 | `app/Concerns/CancelsClasses.php:435` | cancelClassDay() should return array{ok: bool, cancelled: array<int, array<string, mixed>>, ...} but returns array{...} | `return.type` |
| 22 | `app/Concerns/CancelsClasses.php:438` | Cannot cast mixed to int. | `cast.int` |
| 23 | `app/Concerns/CancelsClasses.php:440` | Cannot cast mixed to string. | `cast.string` |
| 24 | `app/Concerns/CancelsClasses.php:471` | Cannot cast mixed to int. | `cast.int` |
| 25 | `app/Concerns/CancelsClasses.php:472` | Cannot access property $start_time on App\Models\TimeSlot\|null. | `property.nonObject` |
| 26 | `app/Concerns/CancelsClasses.php:479` | Cannot access property $start_time on App\Models\TimeSlot\|null. | `property.nonObject` |
| 27 | `app/Concerns/CancelsClasses.php:505` | Cannot call method select() on mixed. | `method.nonObject` |
| 28 | `app/Concerns/CancelsClasses.php:506` | Cannot call method from() on mixed. | `method.nonObject` |
| 29 | `app/Concerns/CancelsClasses.php:507` | Cannot call method whereColumn() on mixed. | `method.nonObject` |
| 30 | `app/Concerns/CancelsClasses.php:508` | Cannot call method where() on mixed. | `method.nonObject` |

Findings 9–30 are reported once per consuming class; all of them here surface through
`App\Livewire\CohortTimetable`. Other classes using the same trait may report separately
on a fresh run.

---

*Generated 18 Nov 2026. Raw output retained at `/tmp/opencode/phpstan-max.json` on the
machine that ran it — regenerate with the command at the top rather than trusting this
file's numbers if the code has moved since.*
