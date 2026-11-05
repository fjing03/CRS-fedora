# Explore brief — notif-recipient-rulings

**Date:** 2026-11-03 · **Stage:** explore (no product code touched)
**Input:** `prompts/pi-notification-recipient-rulings.md` (current version, 479 lines —
the one that carries §6's role matrix and the rewritten §14).

This brief is the completeness checklist for the proposal. Everything below was read
from the code in this tree, not assumed.

---

## 1. What happens today (verified in the code)

| Event | Who receives a row | Where |
|---|---|---|
| Request submitted | **every** `is_pl` user, any department (a PL proposer included) | `NotificationEmitter::requestSubmitted` |
| Request approved | the proposer, title `Replacement approved` | `NotificationEmitter::requestDecided(…'approved')` |
| Request approved | every cohort student, title `Replacement class updated` | `NotificationEmitter::approvalNotice` |
| Request rejected | the proposer | `requestDecided(…'rejected')` |
| Pending reminder | every `is_pl` user, deduped until read | `pendingReminder` |
| Class cancelled | the actor/owner + every cohort student + the **dept PLs of the owner** | `classCancelled` steps 1–3 |
| Submit mail | every `is_pl` user — **including the proposer when the proposer is a PL** | `SubmitReplacementRequest::succeed()`, mail loop |

So a submit is invisible to a non-PL proposer, duplicated (2 rows) for an owner who
proposed, and a cancel spams PLs who cannot act on it.

## 2. Facts that constrain the design

1. **`_display` is not a place for the staff line.** `NavNotifications` attaches it only
   to `submitted` rows whose `inactive_reason === null` (`:487`); a read row loses it.
   R5 needs the line in every state → the line must be its own computed attribute.
2. **The `submitted` composer already builds the staff line.** `composeSubmitted()`
   (`NavNotifications.php:543–551`) writes `Name (staff_id)` as the subtitle for a live
   submitted row. So the partial must let `$subtitle` win and use the new attribute only
   as the fallback — otherwise the same line prints twice.
3. **`resolveRequests()` has an early return** (`:402–404`) taken when no row carries a
   `request_id` — a cancellation-only inbox. The line must be attached at **both** exits.
   At that early exit `inactive_row`/`inactive_reason` are NOT yet set, so the class batch
   must be gated on columns (`request_id === null && class_session_id !== null`), not on
   those attributes.
4. **`approver_id` exists and is stamped by BOTH decisions.** Migration
   `2026_08_03_000009_create_replacement_requests_table.php:22`;
   `ApproveRequest.php:146` and `RejectRequest.php:139` both write it. So a self row can
   always resolve "the PL who acted" — including on a rejection.
5. **Relations for one-query resolution:** `ReplacementRequest::proposer()`/`approver()`
   (both `User`), `User::lecturer()` → `Lecturer` (has `staff_id`),
   `ClassSession::lecturer()` → `User` (so the class side needs `lecturer.lecturer`).
6. **No `isPl()` accessor** on `User`; the only test is `$user->lecturer?->is_pl`.
7. **No `staff_line` column** and none is needed — it is per-render data.
8. **`requestSubmitted()` has three call sites**: the submit action + two test helpers.
9. **Identity map for the receipts:** user 1 = 5425 Surayaini (PL, class owner),
   user 2 = 5516 Mohd Nur Rahmat (PL), user 11 = 5770 Lim Jia Zheng (non-PL).
   Test DB = `class_replacement_testing` (disposable); demo DBs stay untouched.

## 3. Rejected approaches and why

| Approach | Why not |
|---|---|
| Store the staff line in the existing `_display` array | dies on read (fact 1) → R5's "every state" fails |
| Add a `staff_line` column | a pure display join, no migration in this lane; rows would go stale |
| Compute the line per row inside the Blade partial (`$n->request->proposer`) | one query per row — breaks the island's ONE-query law the poll depends on |
| Filter the PL fan-out so a PL proposer gets only the confirmation row | R1 forbids it (trap 1): the PL keeps the queue row; the confirmation is additive |
| Fix the subtitle by "improving" `requestDecided()` instead of merging | R3 removes the owner row entirely and moves the link to the timetable |
| Keep the dept-PL cancel fan-out but hide it in the panel | R4 deletes it; a hidden row is still a row |
| Write the owner's merged row inside the cohort loop | duplicates once per cohort (trap 2) |
| Gate the subtitle by role (`role_view` = pl/student) | the rule is about *who the row is about*, not the reader's category — the same staff line appears on all three |

## 4. Final solution (the shape the proposal will describe)

- **R1** — `requestSubmitted(User $proposer, Collection $pls, …)`: PL loop unchanged, then
  one conditional `update` row for a non-PL proposer. PL fan-out untouched.
- **R2** — one identity `continue` in the mail loop; the emitter call gains `$proposer`
  (the closure must capture the model, not just the name).
- **R3** — new `replacementConfirmed(User $owner, ClassSession $session, int $requestId,
  int $week, string $deciderName)`; the approve action calls it **once, before** the
  cohort loop; `requestDecided()` stays for rejections.
- **R4** — `classCancelled()` keeps steps 1–2 and drops step 3 (+ the now-unused
  `Lecturer` import). This reverses the 2026-10-30 three-fan-out ruling.
- **R5** — a computed `staff_line` per row in `NavNotifications::attachStaffLines()`,
  fed by the existing request batch (extended with `approver.lecturer`) plus ONE
  `ClassSession` batch for class rows; rendered by the shared partial *after* `$subtitle`.
  Rule: request rows → the proposer, or the deciding PL when the reader IS the proposer;
  class rows → the class lecturer; self-only events → omitted.

## 5. Open questions (for the user, not for guessing)

1. **None blocking.** R1–R5 are settled. One wording consequence to confirm at proposal
   review: on a **rejection** the proposer sees the *decider* (`Name (staff_id)`), because
   `approver_id` is stamped by both decisions — the prompt's worked examples only show the
   approval case, so the brief records the inference rather than inventing a new rule.
2. **Scope reminder, not a question:** the self-approval path, the `_display` degradation
   after read, mail templates, and any panel redesign stay out (§13).

## 6. Test plan sketch (detail belongs in `design.md`)

Emitter rows (5 tests incl. the new non-PL proposer and the merged owner row), the
cancellation fan-out reduced to owner + students, four subtitle rendering cases
(PL / student / owner-after-decision / owner-own-cancel), the ONE-`replacement_requests`
query law, and the mail-loop skip. Browser receipt only on `:8030`.
