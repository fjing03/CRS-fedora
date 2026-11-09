# Tasks — notif-new-request-ux

**Frozen baselines:** `proposal.md` (D1–D15) → `design.md` (frozen 2026-11-01).
**Commit discipline:** one concern per commit — `oop:`/`feat:` code (T1–T5),
`test:` receipt updates + new receipts (T6–T8), `docs:` changelog (T9). Never push.
**Gates after any PHP:** `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`.
Never sweep other lanes' dirty files (`my-request-history.blade.php`,
`replacement-arrangement.blade.php`, `ReplacementArrangement.php`,
`notifications-real-bridge/{review-log,sdd.yaml}`).

## T1 — Mapper surgery (additive; `oop:`)

`app/Services/ReplacementRequestMapper.php` per design §3:
1. NEW `stringsFromSpan(?array $span, Carbon $semesterStart, ?Venue $venue): array`
   — parameterise today's `replacementStrings()` body; keys `date/time/week/day/
   venue/venueName` (time keeps the legacy SPACED `H:i – H:i`; `day` from
   `DAY_NAMES[span.day]`; `venueName` = `$venue->room_name` or null).
2. `replacementStrings()` becomes a thin wrapper calling it (return keys unchanged
   for existing callers — `MyRequestHistory:75-76`, `toRow()` :69 stay unmodified).
3. NEW `spansForRequests(array $requestIds): array` — ONE join query
   (`replacement_request_slots ⊕ time_slots`, whereIn, order by start_time) →
   `requestId → {week,day,venue_id,start,end,count}` (span()'s first/last shape).
Acceptance: existing mapper consumers' tests green; the notifications path never
calls `span()`/`replacementStrings()`/`classRow()`/`displaySlot()` per row.

## T2 — NavNotifications: batching, composer, classifier, counting (`feat:`)

`app/Livewire/NavNotifications.php` per design §1/§2/§4:
1. Batch `whereIn` gains `->with(['classSession.module', 'replacementTimeSlot.venue', 'proposer.lecturer'])`.
1b. Wire the three §1 batches EXPLICITLY — `spansForRequests(ids)` →
    `Venue::whereIn(...)->keyBy('id')` → `Semester::whereIn(...)->keyBy('id')`; NO
    per-row span/venue/semester lookup on the poll path (a per-row `Venue::find`/
    `Semester::find`/`DB::table` span call passes phpstan + :253-274 silently — the
    acceptance enforces the batch shape).
2. NEW composer: for `type === 'submitted'` rows, attach `_display` per design §2
   (title-with-ID / subtitle / desc / aria / tip keys + the fail-open matrix:
   IDENTITY lines per-line omitted; SCHEDULE lines all-or-nothing with the WHOLE
   tip incl. the missing-semester row; time tokens from RAW `span.start`/`span.end`
   24 h UNspaced en dash; venue name null → code alone, D11; empty tip →
   `title — body`).
3. NEW inactive-reason classifier: first-match chain `cancelled > approved >
   rejected > read` (status null keeps the current inactive edge, cancelled copy —
   documented in-line per design §4); attach `inactive_reason`; NO reason = active
   (href/data-id/target untouched).
4. DELETE the standalone COUNT at :55; derive `$unreadTotal` from the classified
   collection (`read_at === null && inactive_reason === null` = the literal
   unread-filter predicate, D5); badge == pill == $userTotal == filter ==
   empty-state all read the derived number; `markAll()` untouched.
Acceptance: `vendor/bin/phpstan` green; the ONE-QUERY law test :253-274 passes;
no `replacement_requests`-mentioning query added; the §1 batch wiring enforced (no
per-row span/venue/semester lookup). If the batch overruns 2 h, the composer and the
classifier/count split cleanly into two `feat:` commits in this file.

## T3 — Row partial: render the composition (`ui:`)

*(tasks Round 1 soft-pin: T4 lands in the SAME `ui:` commit as T3 — the `$rowTip`
multiline branch consumes T4's closures; separating them leaves every staff page
throwing an undefined-variable blade exception between commits.)*

`resources/views/partials/notif-row.blade.php` per design §6:
1. Title/subtitle/desc render `_display` with the stored-title/body fallbacks.
2. NEW `<span class="notif-row-subtitle">` (only when a subtitle exists).
3. `$rowTip`: inactive → the D1/D15 reason copy by `inactive_reason`; active +
   `_display['tip']` → the 5-line `\n`-joined multiline tip (design §5's exact
   formats, `$relLong`/`$tipAbs` from T4); else the existing `title — body`.
4. aria-label mirrors (surviving parts joined out; ` (inactive)` suffix).
5. href/data-id/data-notif-target/data-notif-week mechanics untouched.
Acceptance: ONE-FEED equality unaffected; read-only island spec green.

## T4 — Island view closures + count flow (`ui:` — same commit as T3)

`resources/views/livewire/nav-notifications.blade.php` per design §5/§6:
1. NEW `$relLong` (lowercase verbose: `just now` / `N minute(s) ago` / `1 hour ago`
   / `N hours ago` / `Yesterday` / `N days ago`) and `$tipAbs` (`j M Y, g:i A`,
   same display TZ machinery as `$absTime`) closures beside :23-38.
2. The derived unread count flows into badge/pill/mark-all as today (blade reads
   it — no numeric logic in the view).
Acceptance: T6/T7 tooltip+tip receipts render from these closures.

## T5 — theme.css: the subtitle class (`style:`)

ONE new `.notif-row-subtitle` inside the notif block (≈:479-493): smaller step,
`--color-on-surface-variant`, no new tokens, no media-query additions (mobile
inherits the panel's :513-526).
Acceptance: the subtitle renders unstyled-then-styled on the T7/T8 panel receipts.

## T6 — Feature tests (`test:`)

`tests/Feature/NotificationsBridgeTest.php` per design §8:
1. UPDATE `:147` → the D1 sentence; `:191-201` → the approved row renders
   `.notif-row--inactive` + no href + the D15-approved tip; `:151-166` → badge law
   comment reworded (actionable unread) + inactive-exclusion assertions.
2. NEW: title-with-ID; subtitle `Name (staff_id)`; desc format; 5-line tip content
   (exact strings per T2/T4 formats); fail-open matrix (missing proposer → line 1
   omitted; missing module → line 5 omitted; missing semester → whole tip falls
   back; legacy status-null row keeps the inactive edge); the DECIDED-AWAITING case
   (an unread `awaiting` reminder row of an approved/rejected request also renders
   inactive with the D15 sentence — the deliberate D2 side-effect, recorded in T9).
3. KEEP GREEN: `:82`, `:253-274`, ONE-FEED, `:221`, email-side
   (`ApprovalWorkflowTest.php:520/:566`, `EmailQueueTest.php:29`).

## T7 — Writable Playwright receipts (`test:`)

`tests/notifications-island-testing-db.spec.ts` (env-guarded :8030,
`CRS_MODE=testing CRS_ALLOW_SQL=1` — demo DB :8000 NEVER written):
1. UPDATE `:131-133` (read `submitted` rows → inactive spans; other types keep
   `a.notif-row--read`), `:148` → the D1 string.
2. NEW: badge-exclusion on decision (approve → the submitted row's badge/pill
   drop without read_at stamping), inactive-on-approved/rejected, the tooltip
   multiline rendering (`.data-tip-tooltip--multi` above the row), D15 family
   receipts per reason.

## T8 — Read-only Playwright receipts (`test:`)

`tests/notifications-island.spec.ts` (:8000 demo DB, storageState) per design §8:
panel assertions that need no writes — the composed title/subtitle/desc on REAL
legacy submitted rows (render-time composition applies to them), the cancelled
row's D1 copy (**verify the demo mailbox for staff 5425 actually holds a cancelled
submitted row; if not, that receipt belongs in the T7 :8030 fixture set**), the
panel-open-survives-poll shape keeping `_display` strings, the one-feed equality,
and the F-2 test NAME rewording at :26 (the test's own name/comment updates to the
actionable-unread wording so a future re-verify reads it as D5-consistent).

## T9 — Changelog + final gates (`docs:`)

1. `page-changelogs/notifications-panel-changelog.md` — new dated entry on top:
   the 6 parts, D1/D15 copy family, D5's badge redefinition (+ the F-2 comment
   rewording), D3/D4's two law amendments, the D6/D7 render-time/no-backfill
   ruling, the mapper promotion (design §7), the D2 side-effect line (the blanket
   inactive chain deliberately flips decided-awaiting rows too), the gates +
   receipts summary.
2. Final gate run + a fresh full Playwright notifications suite pass.
3. At commit time ALONE: verify `git status` and never sweep other lanes' files —
   `my-request-history.blade.php`, `replacement-arrangement.blade.php`,
   `ReplacementArrangement.php`, `page-changelogs/replacement-arrangement-changelog.md`,
   `notifications-real-bridge/{review-log,sdd.yaml}`.
