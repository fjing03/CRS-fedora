# Tasks — notif-recipient-rulings

Batch 4 · 2026-11-03 · baseline = frozen `proposal.md` + `design.md`.
Tick a box only when the receipt named on the line is observed. No product code is written
until the user approves `design.md` + `tasks.md`.

## T1 — emitter: R1 + R3 + R4
`app/Support/Notifications/NotificationEmitter.php`
- [x] `requestSubmitted(User $proposer, Collection $pls, string $moduleCode, string $moduleType, int $requestId, int $week)` — proposer first, `$proposerName` dropped, PL loop text byte-identical
- [x] conditional non-PL proposer row (`$proposer->lecturer?->is_pl !== true`): `update` / `Replacement request submitted` / `… awaiting PL approval` / `/my-request-history-ui`
- [x] `replacementConfirmed(User $owner, ClassSession $session, int $requestId, int $week, string $deciderName)` added after `requestDecided()`
- [x] `classCancelled()` step 3 deleted + `use App\Models\Lecturer;` removed + docblock rewritten (two fan-outs, reversal recorded)
- [x] `requestDecided()` untouched
- *Receipt:* `php -l`, then T2/T3/T4 green

## T2 — submit action: R1 wiring + R2
`app/Actions/SubmitReplacementRequest.php`
- [x] `$proposer` added to the `DB::afterCommit` closure `use (...)` (the model, not just the name)
- [x] mail loop skips `(int) $pl->id === (int) $proposer->id`
- [x] emitter call passes `$proposer` first
- [x] `if ($pls->isEmpty()) return;`, the mailable and its template untouched
- *Receipt:* T8/T9 green

## T3 — approve action: R3 merge
`app/Actions/ApproveRequest.php`
- [x] `requestDecided(..., 'approved', ...)` removed from the notification closure
- [x] `replacementConfirmed(...)` called **once, before** the cohort loop, guarded by `$proposer !== null`
- [x] the `approvalNotice()` loop untouched
- *Receipt:* T3 (one `approved` row) + T5(c)

## T4 — island: the staff line
`app/Livewire/NavNotifications.php`
- [x] `use App\Models\ClassSession;` imported
- [x] `approver.lecturer` added to the `ReplacementRequest` eager load
- [x] `attachStaffLines(Collection $rows, Collection $requests)` + `staffLine()` + `staffLabel()` added
- [x] both exits of `resolveRequests()` routed through `attachStaffLines()` (early exit passes `collect()`)
- [x] class batch gated on columns (`request_id === null && class_session_id !== null`) and batched (`with(['lecturer.lecturer'])`), never per row
- *Receipt:* T5, T5b, T6 green

## T5 — partial: the same-text guard
`resources/views/partials/notif-row.blade.php`
- [x] `$staffLine = $n->getAttribute('staff_line');` added beside `$subtitle`
- [x] two independent blocks on one source line: `@if($subtitle) … @endif@if($staffLine && $staffLine !== $subtitle) … @endif`
- [x] both null ⇒ no `notif-row-subtitle` span and unchanged whitespace
- *Receipt:* T5(d) + T5b (exactly one span per row in unread / read / decided / hidden)

## T6 — tests
`tests/Feature/NotificationsBridgeTest.php`, `tests/Feature/EmailQueueTest.php`
- [x] `submitEvent()` passes `$this->coordinator()`; the `:279` arm updated
- [x] non-PL proposer test (exactly one `update` row; PL rows untouched)
- [x] `test_approved_rows_follow_the_per_role_link_law` rewritten against `replacementConfirmed()`
- [x] cancellation test renamed `…_to_owner_and_students`, asserts **zero** `role_view='pl'`
- [x] four subtitle cases (PL / student / owner-after-decision / owner-own-cancel)
- [x] **T5b occurrence count**: one row × four states (unread, read, decided, hidden) ⇒ exactly one `notif-row-subtitle` span, text exactly `Lim Jia Zheng (5770)`; counted on spans, not raw text (the tooltip repeats the name)
- [x] `EmailQueueTest`: new PL-proposer test — `assertNotQueued` for the proposer, `$plCount - 1` queued
- *Receipt:* the measured counts of
  `php artisan test --filter="NotificationsBridgeTest|EmailQueueTest"`

## T7 — gates + no-migration proof
- [x] `composer run lint:check`
- [x] `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
- [x] `git diff --name-only | grep -E "^database/migrations/" && echo STOP || echo "no migration"`
- *Receipt:* the three outputs quoted

## T8 — browser receipt on the disposable lane (`:8030` only)
- [x] `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`; serve on `:8030`
- [x] 5770 submits → own confirmation row, no staff line
- [x] 5425 sees `New replacement request` + `Lim Jia Zheng (5770)`
- [x] 5425 approves → 5770 holds exactly one `Replacement class updated` + `Surayaini Binti Basri (5425)`
- [x] 5770 cancels a class → own row has no staff line; a student's row shows `Lim Jia Zheng (5770)`
- [x] `:8030` stopped; demo DBs untouched (read-only check after)
- *Receipt:* the observed rows quoted

## T9 — changelog + commits
- [x] `page-changelogs/notifications-panel-changelog.md`: one dated entry for R1–R5 + the 2026-10-30 reversal + the same-text guard refinement
- [x] commits: `feat(notifications): …` (T1–T5), `test: …` (T6), `docs: …` (lane + changelog)
- [x] never push
- *Receipt:* `git show --stat` per commit

## T10 — hygiene
- [x] `git add` only the files this lane touches; another session's dirty spec files, `tests/helpers/receipt-target-guard.ts`, `.sdd/changes/receipt-target-guard/`, `database/seeders/TestingFixtureSeeder.php` and `docs/demo-db-data-changes.md` are never staged
- *Receipt:* `git status --short` before each commit
