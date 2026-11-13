# Design — cancelled-slot-reuse

Date: 2026-11-13
Batch 2: frozen after independent review Round 2. Proposal is frozen. No implementation approval.

## D1. Authority and limits

Implement the frozen proposal and its nine brief choices. The user has authorised planning through tasks, stopping before SDD Apply. This file authorises no application edits, tests that write, service changes, cleanup, commit or push. The formal FR/NFR copy stays untouched. No dependency or schema change is planned.

Part A is original-cell release, safe reuse, history payloads and shared stacking. Part B is room-only holder explanations, delivered separately. Existing-data cleanup is a third, separately approved operation, not a migration or page-read side effect.

## D2. Current paths and shared responsibilities

Read-only inspection found:

- `CancelClass` delegates to `CancelClassBatch`; the batch closes an active source request and creates the exception/audit but does not free original cells.
- `ApproveRequest::releaseOriginal()` frees occupied rows by session/week, without excluding a newer request on those rows. This is unsafe for exact-original-slot reuse.
- `ReleaseRequestSlots` has strict and non-strict policies; its normal release accepts pending cells. Closing an approved source request must also release its proven occupied landing cells, through an explicit opt-in policy, not by changing every caller's default.
- Lecturer/cohort vectors already exclude exception pairs. Room availability reads actual slot state. OCC reserves full spans and retains junction receipts.
- `ResolvesTimetableTimeline` handles personal/cohort payloads. Venue has its own row pass, request pass, exception backfill and same-start twin reducer. Its request pass currently depends on null `class_session_id`, although released rows can retain stale ids.
- `buildTimetableGrid` uses last-write-wins slot maps. `myTeachingStats` also deduplicates by day/start. Both must stop treating coincident coordinates as identity.

Promoted to shared:

1. `App\Services\OriginalOccurrenceReservation`: plan/classify original cells, shared by cancellation, approval and cleanup.
2. `App\Actions\ReleaseOriginalOccurrenceSlots`: consume a locked plan, version-check flips and return a structured receipt. Audit action remains the caller's existing legal action.
3. `App\Services\TimetableEventIdentity`: shared event identity/deduplication contract for payload builders. JS consumes the emitted `eventKey` rather than inventing identities by coordinates.
4. Pure `layoutTimetableDay` in `public/js/ui-common.js`: one lane algorithm for all history timetables; shared rendering/keyboard binding retained in the grid builder.
5. Existing holder text library/formatter remains the sole formatter for Part B. Do not write a second tooltip engine.

## D3. Named ownership rule: ORIGINAL-RESERVATION

Input is an occurrence `(semester_id, class_session_id, original_week)` and the immutable recurring session coordinates. Discover every expected 30-minute cell in that session's original venue/day/range; also report linked rows outside that range rather than freeing them. Never select by week or room alone.

Classify each expected cell, after locking slot rows:

| Result | Evidence | Behaviour |
|---|---|---|
| `original` | Exactly one physical row; semester/week/day/venue/30-minute coordinates match; status occupied; `class_session_id` matches; no request ownership or conflicting lifecycle evidence below | Release eligible |
| `available` | All matching physical rows already available (including duplicate available rows), regardless of stale original ids, with no conflicting active ownership evidence | Report duplicate available rows, otherwise no-op; do not bump versions or clear ids just to tidy data |
| `request_held` | A pending/approved request owns the cell through junction membership, or its anchor/span fallback when junction-less | Preserve slot, version and links; never original-release it, including the approving request's own cells |
| `missing` | No expected row | Report; runtime cancellation refuses the whole selected batch; cleanup cannot apply that occurrence |
| `ambiguous` | Duplicate coordinates with any non-available row, mismatched occupied owner, unexplained pending state, inactive/orphan junction, inconsistent active claim, or contradictory lifecycle evidence | Refuse runtime batch; cleanup reports and excludes the whole occurrence pending investigation |

Request ownership always wins over a matching legacy `class_session_id`. Derive a junction-less active request's covered range with `ReplacementRequestMapper::spanFromAnchor()` and `replacement_span` (legacy null = one cell), not new span arithmetic. Active claims must agree on semester/room/day/week; malformed ranges are ambiguous, not a reason to ignore the claim.

For provenance, inspect successful slot-changing request audits as well as live requests/links. A slot showing a prior request reservation/release lifecycle followed by an unexplained occupied row is ambiguous, even if no active link survives. A matching original id by itself does not override evidence that the row was released/reused. A seeded original occupied row with matching coordinates and no request lifecycle evidence is the initial recurring reservation. Missing audit facts are not invented. Contradictory data is a report, not a repair opportunity.

When an active landing booking is proved and the original occurrence is already superseded, original release is a safe skip: cancellation can still record that the source class will not run. Unknown ownership is not a safe skip. A mixture of original/available/proven request-held cells is allowed; only the original subset changes. All cells are classified before the first flip.

## D4. Runtime transaction and concurrency

### Lock order

Cancellation and approval must re-read mutable state inside their transaction. First acquire PostgreSQL transaction-level advisory locks in a fixed CRS occurrence-write namespace, keyed by affected class-session id ascending, using `pg_try_advisory_xact_lock`; contention refuses with a refresh/retry response before writes. Then use a common row-lock order: affected `class_sessions` ascending id, affected source `replacement_requests` ascending id, then the union of original and relevant landing `time_slots` ascending id. Plan discovery before locks is provisional; re-read plans/links and validate membership after locks. If membership changes, roll back and return a refresh/refusal rather than expanding locks out of order.

`SubmitReplacementRequest` keeps its documented NO-outer-transaction structure: acquire a session-level PostgreSQL advisory lock in the SAME namespace/key using `pg_try_advisory_lock` before source/active-request validation, request creation and OCC; release in `finally` on the same connection after success or loser compensation. Contention refuses before creating a request. Do not take a transaction-scoped row lock outside a transaction and pretend it protects OCC. Cancellation/approval's advisory locks interlock with this session-level lock, serialising source cancellation versus submission without nesting OCC. A dropped database connection releases its session locks; never return a connection to reuse without the finally-unlock. Test both thrown and ordinary refusal paths for unlock.

OCC retains its own transaction, peri-flip rollback and surviving `occ_conflict` audit rewrite, and `compensateLoser()` retains its existing null-FK/delete behaviour. No conflict audit is swallowed by a new outer transaction. OCC still owns the available-to-pending full-span version check; different source requests compete on ordered slot locks. No path holding a slot may subsequently acquire advisory/source-session locks. Existing reject/withdraw/auto-reject paths retain request-then-slot order and do not acquire a session lock. Deadlock/version errors return a retry/refusal, never partial success. No scheduler changes.

### Cancellation

Within one transaction: resolve selected owned occurrences; lock/revalidate reason, notice, absence of existing cancellation, selected source requests and cells; capture landing ownership before deleting any junction; classify every original cell; then close/release attached requests, create exceptions, release eligible original cells, and write audits. A failed classification, conditional update or audit aborts the whole selected batch. Notifications are registered after commit only; outer rollback sends none.

For approved request closure, add an explicit request-release policy accepted only by `CancelClassBatch`: permit pending or occupied cells only when the locked receipt proves that this request is still their sole active owner. Default strict/non-strict behaviour of Reject/CancelPending/AutoReject remains unchanged. A newer holder is never released; ambiguous approved ownership refuses the batch. Use the existing release helper, not a copied loop. Capture candidate sets before closure so deleted links cannot make a landing cell look like an original reservation.

`cancelClassDay` must wrap selected-class cancellation and explicitly ticked make-up withdrawals in one outer transaction. Validate every selected withdrawal, stop if the class batch refuses, and treat a withdrawal failure as a rollback of all selections. Do not withdraw ticked requests after a refused class batch. Unticked rows and make-up source classes stay unchanged. This is required by the proposal's all-or-nothing boundary, not a new leave flow.

Each changed original cell becomes available, clears `class_session_id`, and increments version by one using conditional old status/id/version predicates. Record before/after identity, status, owner and version in the cancellation audit's `original_release` receipt; no new audit CHECK value. Available/protected rows are receipt no-ops with reason. Do not alter recurring `class_sessions` or other weeks. A repeated cancelled occurrence retains the existing refusal/no-new-notification behaviour; cleanup repeatability is separate.

### Approval and same-slot reuse

Keep existing decision/FCFS/validity/notification rules. Lock the union of landing and original cells before flips. Approve landing cells normally and keep their junctions. Original release uses the same classifier and explicitly protects the approving request's landing set, even if it exactly equals the original range and stale class ids remain. Stamp the original exception only if absent; never replace a user's cancellation reason with `official_event`. A failure rolls back decision and flips. Existing legal audit `approved` carries the release receipt.

A valid same-class, same-room, same-week, same-time replacement therefore goes available → pending → occupied. Original release cannot turn its occupied cells back to available. Independent lecturer/cohort/room, venue, full-span, lead-time, holiday and one-live-request checks remain mandatory. The exception removes only the old occurrence, not new request busy vectors.

## D5. History is not occupancy

Every payload event receives a stable `eventKey`:

- Original/cancelled occurrence: `occ:<semester>:<session>:<week>`.
- Landing request: `request:<requestId>`.

Keep `id`, `sessionId`, `requestId`, original/landing schedule fields and current modal contracts for compatibility. Same coordinates or same source session do not mean same event. Combined-cohort duplicates of the SAME key merge cohort facts once; distinct keys never go through the venue same-start reducer as twins.

Build cancellation history from the recurring session plus a current exception AND a non-maintenance `class_cancelled` audit identifying that exact session/week, using the same discriminator as D9, not from occupied room rows. `official_event` alone is not proof: approval uses that same reason value. Approval-stamped pairs without user-cancellation audit evidence never gain cancelled history from overlap; their existing suppression stays unchanged in all cases. Missing/contradictory evidence is reported rather than synthesising a cancellation. Venue request events come from active requests and their junctions/span mapper regardless of stale slot class id. Original slot rows claimed by an active request are not also emitted as normal recurring events. Original history always uses the original room/range; landing uses its own full range. Deduplicate by eventKey before rendering and summary calculations.

Preserve existing suppression of handled originals where the approved landing does not overlap: when an original has an approved replacement, retain its cancelled history if ANY in-scope active event overlaps that original on the same week/day/time (and selected venue on venue pages); otherwise preserve today's handled-original suppression. This covers its own exact/partial-overlap approved landing and another class reusing the old room. A pending replacement does not suppress cancellation. Withdrawal/rejection/auto-rejection removes the active landing event only.

Scope on the server before deduplication/layout: staff personal = owned classes/requests, venue = selected room, cohort = selected cohort, student = own cohort only. Student projections retain today's generic public `ReasonLabel`/`cancelledReason` label, but omit staff-only free-text reason/detail fields (`other_detail`, private audit/request details and remarks), rather than merely hiding text in JS. Other lecturers see public timetable facts but not another request's remarks/private reason/full request action. Event keys are identifiers, not new permission grants.

## D6. Shared deterministic lane layout

`layoutTimetableDay(events)` accepts unique scoped events with inclusive grid indices `start..end`. Use half-open intervals `[start,end+1)` internally. Adjacent intervals do not overlap. Input is not mutated.

1. Partition by day; within a day form connected interval-overlap components. Sort component boundaries by start. This isolates distant non-overlapping blocks.
2. Within each component sort cancelled records first, then all other records; within each group sort `(start,end,eventKey)` lexically/numerically with a documented stable comparator.
3. Allocate cancellations by first-fit into the lowest non-overlapping lane. Allocate active records by first-fit, but any active record directly overlapping a cancellation must have a lane strictly greater than EVERY directly overlapping cancellation's lane. Existing active records in that lane must not overlap it. Allocate more lanes when necessary.
4. Independent components start from lane zero. Day lane count is the largest component lane count, minimum one. Preserve original time and span; return `{event,lane,span}` and lane count. Layout makes no availability decision.

Example: cancelled 09–11 and pending 10–12 produce two lanes, with their actual ranges. Two cancellations 09–11 plus an approved event 09:30–10:30 produce three lanes. 09–10 and 10–11 may share a lane. Shuffling input must not change output. No paint-order or z-index trick may substitute for lanes.

Render logical day as one or more physical table rows with day-header rowspan; preserve time-column alignment and existing block classes. Keep one focusable/clickable block per eventKey, original `data-sid`/`data-rid`, and add `data-event-key`. Bind Enter/Space through the shared event-opening seam, with Escape/return-focus preserved. Notification jumps select request id for a landing and occurrence identity/week for a source, not the first matching day/time.

Default personal/student/cohort grid and venue custom renderer consume the SAME layout result. Extend the custom-cell contract with lane/event context, do not copy the algorithm into Blade. Venue availability/selection reads real slot occupancy, never cancelled event geometry. Where venue history would cover an otherwise selectable slot, expose the selectable cell in a separate base availability row below history; that base row exists only on days needing this separation. It is not an extra event and never enters summaries. `roomBeforeNextEvent`/span picking must ignore cancelled history and non-booking lane rows. Ordinary no-overlap days keep their existing height except where a base availability row is necessary.

Use local lane classes in theme.css, not global `position:relative` on all grid cells (which can break absolute cell content). Keep block content/colSpan, widths, sticky day headers and existing responsive horizontal scrolling. Ensure lane rows grow with content, and shared print CSS retains all rows without fixed-height clipping. No print redesign or arrangement-grid history overlay.

## D7. Data-based summary contract

Deduplicate events by eventKey, never day/start or lane. Existing cards retain their meanings:

- Classes = unique non-cancelled events; hours = sum of each such event's true slot count × 0.5.
- Pending classes/hours = pending subset; confirmed split = total minus pending.
- Replacements = approved landing events. Conflicts = unique events meeting existing conflict/cancelled/holiday predicate, once per event even if several predicates apply.
- My Teaching uses the same unique non-cancelled data restricted to the logged-in lecturer; same-start distinct request and original identities cannot overwrite each other.
- Venue occupied/free hours are a UNION of actual room cells with pending/occupied status, bounded by the current selected week/day/open-hour denominator. No history or lane contributes occupancy. Preserve existing holiday denominator/exclusion policy and existing pending card split; assert current no-reuse baseline stays identical.

One cancellation 09–11 plus a pending landing at 09–11 yields zero taught hours from cancellation and two pending hours, not four occupied hours. A second base layout row contributes zero classes/hours. PHP venue totals and JS cards are checked against the same fixture facts; do not substitute rendered-block counts.

## D8. Part B — active room holders

Keep `classifyState` precedence, labels and eligibility unchanged. Thread actual holder ids from `venueSlotsByWeekPayload` into desktop AND mobile tooltip composition even when the viewer has no lecturer/cohort busy-vector match. Active junction ownership outranks a stale original id. Use the existing shared text library and deduplication of one factual holder across venue/lecturer/cohort arms. Keep separate holders when identities differ.

Public fact block: existing room · course (type) · actual time; existing lecturer/cohort lines where permitted; final public state `NORMAL`, `PENDING` or `REPLACEMENT`. New/changed time strings use the common 12-hour `start ~ end` formatter; the grid axis stays 24-hour. Use text `data-tip`, `\n` line breaks, escaped content, never injected HTML. No remarks, private cancellation reason, submission metadata or full-request access for non-owners. Unknown holders get a neutral `Venue Conflict` explanation, never a fabricated class name; keep blocked status unchanged and report an ambiguous fixture in tests.

Cancelled occurrences are excluded from active holder lists; available cells have no new cancellation note. During the interval before separately approved legacy cleanup, a still-occupied cancelled original with no active holder gets the neutral `Venue Conflict` fallback, not a fabricated `NORMAL` class holder or a claim that the cancellation remains an active booking. This does not perform cleanup on read. If another active booking now holds the room, explain THAT booking. Part B ships separately after Part A, and may not reinstate the old rule. Another session has created `.sdd/changes/arrangement-cancelled-room-hover/sdd.yaml` only; do not edit/merge its folder. Before Apply, agree one owner for overlapping Part B files and reconcile any later artifacts to avoid duplicate work.

## D9. Guarded existing-data cleanup

Later implement an explicit CLI facility `classes:release-cancelled-slots`: default dry-run; testing rehearsal allowed only with explicit testing DB selection; live apply allowlisted ONLY to `class_replacement_fresh`. `--env=testing` is forbidden. Refuse apply to viva/demo/other DBs. No scheduled invocation and no controller/render call.

Inventory cancellation pairs using non-maintenance `class_cancelled` audit identity plus the matching current exception/session; not every `official_event` exception is a user cancellation. Ignore `class_cancel_undone` as source evidence; this change adds no undo path. Missing/contradictory cancellation evidence is ambiguous. Show semester/session/week, expected slot identities, ownership classification, exact before/after fields, row versions, request/anchor/span protection, no-ops and excluded ambiguous occurrences. Dry-run performs SELECTs only, including no audit/notification writes. Output a plan file with target identity, sorted changes and a fingerprint of all scoped ownership inputs (including protected active claims, exception/session fields, slot versions and relevant audits), not just a list of ids.

Before live apply: remeasure current rows; rehearse the same classifier on testing with equivalent safe/reused/ambiguous cases; verified `pg_dump -Fc` into the approved backups directory and successful `pg_restore --list`; record snapshot path/checksum and target `current_database()`; show the exact current plan and get separate user approval for that write. Snapshot verification alone is not approval. No live write now, even if every artifact passes review.

Apply requires explicit `--apply`, target confirmation, approved plan and verified snapshot reference. Inside one transaction, lock sessions/requests/slot union using D4, recalculate all ownership facts and compare fingerprint; any drift or missing planned identity aborts ALL writes and requires a new dry-run/approval. Only occurrences explicitly included and unambiguous in the approved plan may change. Mixed original/available/request-held is safe; ambiguous occurrences are excluded as whole occurrences. Conditional flips use D4, clear original owner only on changed cells, increment versions. No request/exception/junction edit, new notification, reseed or mass repair. Existing cancellation audit remains; add a legal `class_cancelled` audit receipt with `maintenance=true`, operation id and per-cell before/after evidence, not another user cancellation notification. Inventory must ignore maintenance receipts as cancellation-source evidence.

After commit: verify exact changed cells and no-ops, request statuses/links and protected slot versions, recurring schedules, other weeks and row counts; append only this operation's receipt to `docs/demo-db-data-changes.md`. Second dry-run proposes zero further releases unless genuinely new cancellations occurred. Do not bundle demo execution into code deployment or migration.

Rollback is a separately approved operation. Stop rollout, reread receipt cells and active ownership. Before reuse, restore only receipt cells that are still available, have EXACT recorded post-version/post-owner and no new links/active anchor-span claim; increment current version rather than restoring old versions. Preserve cancellation exceptions/history (not an undo). If any receipt cell has drifted or been reused, do not partially restore or load a whole snapshot: report affected bookings and ask for recovery. Code rollback alone never reoccupies cells.

## D10. Verification and delivery gates

Later tests use `DB_DATABASE=class_replacement_testing`, frozen dates, 30-minute cells and valid week/day constraints. Use RefreshDatabase. The machine's testing checker runs every five minutes; do not change it. Keep browser pending fixtures safely outside expiry, identify them by test ids and verify status before assertions. Unit/feature clock travel is local to that test process, not the cron worker.

Cover original ownership/no-ops/drift and transaction rollback; selected/unticked day scope and request-only withdrawal; approved closure; exact same-class reuse through approve; other lecturer/cohort reuse and all independent blockers; full-span concurrency; default release-helper caller policies; unique/scoped events and student omissions; deterministic exact/partial/multiple/adjacent lanes; modal/jump/keyboard identities; data/union summaries; room-only desktop/mobile hover; safe cleanup dry-run/apply/repeat/refusal/rollback on testing.

Required gates after implementation: `composer run lint:check`; `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`; `DB_DATABASE=class_replacement_testing php artisan test`; `node --check public/js/ui-common.js`; `DB_DATABASE=class_replacement_testing php artisan view:cache`; scoped Playwright at 8030 with exact `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030` guards. Exclude TC70; record existing failures separately and never claim a clean full suite if it is not. Desktop/tablet/phone, light/dark, keyboard and existing print output require receipts.

Before any Apply, recheck shared-file ownership/dirty tree (guide/mobile/print/hover lanes). Planned code delivery is separate Part A and Part B commits only with later commit authority; page changelogs travel with each. Cleanup needs its own exact approval and receipt. This planning batch stops before Apply.
