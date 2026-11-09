
## proposal Round 1 — 2026-10-31 19:24

Scope: `.sdd/changes/notif-new-request-ux/proposal.md` (batch 1, NEW). No frozen
artifacts yet. Baseline: `explore-brief.md` (D1–D14) + `explore-notes.md` (611
file:line facts) + the ask `prompts/pi-prompt-UIUX Update New Replacement Request.md`.

Verified by spot-check against the live code: `NavNotifications.php:55/:61-67/
:78-84/:106-118`, `notif-row.blade.php:13-20/:40/:43-44`,
`NotificationsBridgeTest.php:82/:147/:151-166/:191-201/:253-274`,
`notifications-island-testing-db.spec.ts:23-25/:133/:148`,
`notifications-island.spec.ts:49-73` (`getAttribute('href') || 'inactive'`),
`NotificationEmitter.php:42-43`, `ReplacementRequest/ClassSession/TimeSlot`
relations. No invented facts found; the 6-part coverage is faithful (nothing
silently dropped); scope discipline is good (emitter/mail/recipients/awaiting/
schema out; the other lanes' dirty files named as read-only context).

### 🔴 Fixed

(none — first review of a draft)

### 🟡 Addressed

- **D5 also narrows the real-bridge F-2 "whole-mailbox unread total" law.** The
  hidden-decisions table frames the change as "the two frozen laws" (click-highlight
  + real-bridge D4), but the badge redefinition (actionable-unread, not
  whole-mailbox-unread) touches a THIRD law statement: the island spec test name
  (`notifications-island.spec.ts:26`, "whole-mailbox unread total (F-2)") and the
  bridge-test assertion message (`NotificationsBridgeTest.php:160`, "the bell = the
  mailbox unread TOTAL law"). Both tests stay green on the demo fixture (no inactive
  rows), but the law WORDING should be listed with D5 in the user-confirm list, and
  the design batch should note the comment rewording so a future re-verify doesn't
  read it as a violation.
- **Unread-total derivation trap for design (one-query law).** `NavNotifications.php:55`
  computes `$unreadTotal` by a separate COUNT BEFORE the batch resolve at :61-68. Any
  `whereHas`/subquery exclusion of inactive rows makes that COUNT a SECOND query whose
  SQL mentions `replacement_requests` → `NotificationsBridgeTest.php:264-273` counts
  exactly one such mention and FAILS. The exclusion must be BATCH-DERIVED in PHP from
  the resolved rows (feasible: the panel loads the WHOLE mailbox, `->get()` at :62 has
  no limit). The brief's "batch-derived or subquery — pinned in design" leaves the
  losing option open; carry this constraint explicitly into design.md.
- **Writable receipts must carry the env guard.** The proposal's "Playwright per the
  ask's own requirement" is too thin: `notifications-island-testing-db.spec.ts:23-25`
  is env-guarded (`CRS_MODE=testing CRS_ALLOW_SQL=1`, `:8030` disposable DB) and the
  new badge-exclusion / inactive-on-read receipts are write-path receipts. State in
  design/tasks that they run ONLY against the testing DB — the demo DB is never
  written (AGENTS.md standing rule; notes §4.8).
- **KEEP-GREEN list could name the email-side tests explicitly.** `ApprovalWorkflowTest.
  php:520/:566` and `EmailQueueTest.php:29` (notes §5) stay green trivially because the
  emitter is untouched (verified: no notification-string assertions there). Adding them
  to the keep-green list guards a future implementer against "helpfully" touching
  `NotificationEmitter`. Non-blocking — notes §4.7 (the frozen map) doesn't include
  them either.
- **D2 vs part 4 asymmetry — verified, accepted.** Parts 1–3/N+1 scope to
  `type === 'submitted'` while part 4's tooltip covers all active request-linked rows
  (submitted/approved/rejected/awaiting); non-request rows (update/class-cancelled)
  keep the single-line tip. This mirrors the ask's own scoping (parts 1–3/N+1 name
  "New replacement request" specifically; part 4 says "active notification rows") and
  is defensible — an update row has no request to show. Accepted as stated.
- **Fail-open vs "do not hardcode" — no contradiction.** The fallback renders dynamic
  stored `title — body` when a fact is missing; the ask's "do not hardcode" targets
  example values. Legacy submitted rows briefly still showing "submitted by …" via the
  fallback path is an honest, flagged degradation, not a violation.
- **Grounding of the eager-load claim.** `proposer`, `classSession.module/.venue/
  .lecturer`, `replacementTimeSlot.venue` all exist (models verified); the eager loads
  hit users/lecturers/modules/venues/semesters/class_sessions/timeslots tables — none
  mention `replacement_requests` in SQL (`replacement_request_slots` does NOT contain
  the substring `replacement_requests`). D6's "one-query law intact" claim holds.

### 🔴 Outstanding

- **Tooltips for inactive rows that are NOT cancelled are undecided — the natural
  implementation violates the ask.** Today `notif-row.blade.php:17-18` uses ONE string
  (`…was cancelled.`) for EVERY inactive row, which is currently safe because inactive
  == cancelled/missing-status only (`NavNotifications.php:116-117`). Once D3/D4 land,
  read/approved/rejected `submitted` rows join the `.notif-row--inactive` class and the
  blade's inactive branch would stamp them with "…because the request was cancelled by
  the requester" — directly violating the ask's N+2 requirement
  "Apply this message only when the request is cancelled by the requester"
  (`pi-prompt…md:91`). Neither the proposal nor the brief rules on what those rows show
  instead (a generic inactive sentence? reason-specific per status? no tip?). An
  implementer following the proposal as written ships an ask-violating tooltip on the
  most common inactive states (read + approved). **Fix:** add a hidden decision (e.g.
  D15) pinning the copy — or the copy family — for read/approved/rejected inactive rows,
  and carry it into the bridge-test :191-201 rewrite (which must assert whichever
  string is chosen) and the new receipts.

## proposal Round 2 — 2026-10-31 11:45

Scope: `.sdd/changes/notif-new-request-ux/proposal.md` + `explore-brief.md`
(re-review after round 1's single blocker). All round-1 items checked against
the new artifact contents.

### 🔴 Fixed

- **D15 (round-1 blocker) — landed in both artifacts.** `explore-brief.md:30`
  adds the reason-specific copy family in the same frozen sentence pattern with
  explicit stronger-fact-wins precedence cancelled > approved > rejected:
  cancelled → the exact D1 sequence (`…because the request was cancelled by the
  requester.`); approved → `…because the request was approved.`; rejected →
  `…because the request was rejected.`; read (request still undecided) →
  `This notification is inactive because you have already read this request.`
  (correct non-stamped phrasing, consistent with D5's classification semantics).
  (a) The classifier consequence is carried verbatim — "the inactive
  classifier (which must classify the REASON, not a boolean)" — and the
  bridge-test :191-201 rewrite is pinned to assert the approved family member.
  (b) `proposal.md:65-69` adds the matching D15 hidden-decision bullet
  (family + precedence + read sentence + classifier requirement); no drift
  between brief and proposal.

### 🟡 Addressed

- **F-2 law wording in proposal D5** (`proposal.md:79-82`): the badge is now
  framed as the ACTIONABLE unread; the F-2 test name and bridge-test assertion
  message comment rewording are both named.
- **Unread-total derivation closed** (`explore-brief.md:88-91`,
  `proposal.md:76-78`): BATCH-DERIVED in PHP from resolved rows, with the
  escape hatch explicitly closed ("NO whereHas/subquery exclusion — that would
  make the :55 count a SECOND `replacement_requests` query and fail :253-274").
- **Env guard named** (`proposal.md:98-100`): new WRITE-PATH receipts run ONLY
  on the `:8030` disposable testing DB under `CRS_MODE=testing CRS_ALLOW_SQL=1`;
  demo DB `:8000` never written.
- **Email-side keep-greens named** (`proposal.md:95-96`):
  `ApprovalWorkflowTest.php:520/:566` + `EmailQueueTest.php:29` with "the
  emitter is untouched". (The brief's §5 list doesn't repeat them, but the
  proposal's frozen map is the canonical confirmation list — not a defect.)

### 🔴 Outstanding

(none — the proposal batch may be frozen.)

#### 💡 Optional
- When design.md adds the reason map for the blade's inactive branch, mirror
  D15's precedence order (cancelled > approved > rejected > read) as an
  explicit first-match chain so an implementer can't evaluate `read` first.

## design Round 1 — 2026-11-01 00:10 +08

Scope: `.sdd/changes/notif-new-request-ux/design.md` (batch 2, NEW), reviewed
against the FROZEN proposal.md (D1–D15 + test-impact map) + explore-brief.md +
explore-notes.md. Verified by spot-check against the live code:
`NavNotifications.php:55/:61-67/:96-121`, `notif-row.blade.php:13-20/:27-40/:43-45`,
`nav-notifications.blade.php:23-39/:72`, `ReplacementRequestMapper.php:58/:125-148/
:159-211/:218-233`, `MyRequestHistory.php:65-76`, `User.php:32/:45/:67`,
`ReplacementRequest.php:45/:57/:70`, `Notification.php` casts, `theme.css:479/:487/
:496/:513-526`, `NotificationsBridgeTest.php:82/:147/:151-166/:191-201/:253-274`,
`notifications-island-testing-db.spec.ts:131-133/:136-153`, and the ask
(`prompts/pi-prompt-UIUX Update New Replacement Request.md:33/:53-54/:90`).

### 🔴 Fixed

(none — first review of a draft)

### 🟡 Addressed

- **Faithfulness sweep vs the frozen proposal — all D-rulings verified landed;
  no drift, no silent broadening, nothing dropped.** D1 exact string byte-matches
  brief D1 and is pinned at §4 (with `:147` update); D15 family + precedence is an
  explicit first-match chain cancelled > approved > rejected > read (round-2 💡
  satisfied — `read` cannot evaluate first); D5 batch-derived unread with the `:55`
  COUNT deleted (badge == pill == userTotal == filter == empty-state read one
  number; markAll untouched); D6 render-time composition + per-key fail-open
  matrix; D7 no-backfill restated in §9; D8 `j M Y, g:i A` byte-matches the ask
  :53; D10 engine untouched (multiline arm cited); D11 name-null → code alone;
  D13 aria composition + ` (inactive)` with the stored fallback; D2 scope
  preserved (parts 1–3 = `submitted` only; part 4 = active request-linked rows;
  non-request rows keep `title — body`). §8 mirrors the frozen test-impact map
  anchor-for-anchor (every cited line verified byte-exact in the live tests).
- **One-query law recount — pass.** Char-checked `replacement_request_slots`
  (`…replacement_request` + `_slots`; the character after `request` is `_`, not
  `s`) → `str_contains(sql, 'replacement_requests')` at :264 cannot match the span
  join; eager loads hit class_sessions/modules/users/lecturers; venue/semester
  batches hit their own tables; even the rare-row `replacementStrings()` fallback
  path (span() + displaySlot's venue lazy) mentions no `replacement_requests`.
  Deleting :55 (a notifications-table COUNT) is law-correct: the :253-274
  assertion (exactly 1 query + `' in ('`) stays green on the extended batch
  whereIn.
- **Mapper surgery is additive and call-site-safe.** `stringsFromSpan()`
  parameterises today's body; `replacementStrings()` becomes a thin wrapper
  returning the same keys (date/time/venue), so `MyRequestHistory:75-76` and
  `toRow()` (:69) migrate unmodified; `span()`/`displaySlot()`/`classRow()` stay
  for legacy callers. §7's 3rd-consumer promotion claim verified (queue toRow,
  history, API adapter, now the composer) — §10.0.6 law satisfied.
- **§6 anchors verified.** `.notif-row-title` at theme.css:479 / `.notif-row-desc`
  at :487 — the `≈:479-493 family` placement is exact; `--color-on-surface-variant`
  is an existing token (:496); the mobile-inheritance claim holds (the :513-526
  media query styles the PANEL, so a subtitle row inherits) — panel-only
  non-goal acceptable for this change.
- **Eager-load graph grounded.** `proposer.lecturer` exists (User::lecturer HasOne
  :67; `staff_id` :45); `classSession` :70 + `replacementTimeSlot` :57 +
  module/venue chains verified; `request_id` cast confirmed on Notification
  (legacy `submitted` rows carry the stored ID — §2's title source is safe).
- **Time FORMAT pin missing — §2 vs §3 contradiction.** §2/§5 compose desc and
  tip line 3 with the UNspaced en dash `09:00–11:00` (ask :33/:54), but §3's
  `stringsFromSpan()` `time` key keeps the legacy SPACED `H:i – H:i` (mapper :230).
  If the composer sources `time` from that key, part 3 renders `09:00 – 11:00` and
  silently violates the ask. Pin in one line: the composer builds desc/tip time
  from the RAW `span.start`/`span.end` (24 h, UNspaced en dash);
  `stringsFromSpan()['time']` stays the history consumers' spaced form and is NOT
  the composer's time source.
- **Fail-open edge still unpinned: tooltip line 5 (Subject).** §2's matrix pins
  line-1 granularity (proposer missing → line omitted) but not line 5:
  classSession missing (nullOnDelete) or module missing → is the line omitted, or
  does the whole tip fail to `title — body`? (Same question for the aria join when
  subtitle/desc facts are absent — join the surviving parts, or fall back?) Pin it
  so the new fail-open-matrix composer tests encode one answer.
- **`classRow()` is reachable-but-trappy for the composer.** Line 5's facts are
  described as "`classRow()`-sourced", but `classSession.venue` is NOT in §1's
  `->with()` graph and `classRow()` needs `$semesterStart` + lazy-loads
  `$session->venue` — a literal call taxes every poll row with an extra venues
  query. Pin: line 5 reads `module_code`/`module_name`/`session_type` directly
  from the eager-loaded relations; never call `classRow()` on the poll.
- **§3 bullet 3 self-contradiction on the legacy-span fallback.** It says the
  fallback slot's fields "come from the EAGER-LOADED `replacementTimeSlot`", but
  §1's `->with()` graph does not load it, and the same bullet's second half routes
  such rows through `replacementStrings()` (which lazy-loads `replacementTimeSlot`
  + `$slot->venue` per rare row). Either add `replacementTimeSlot.venue` to the
  `->with()` graph (still law-legal — hits time_slots/venues) or reword to
  "lazily loaded for the rare legacy row". The two halves must agree.

### 🔴 Outstanding

- **§2's `$semesterStart` fallback sentence is self-contradictory — the one edge
  where the fail-open matrix says two opposite things.** "fallback `now()` date
  only if the semester row is missing — the tooltip's date line then fail-opens
  instead of guessing": clause 1 substitutes `now()` as semester start (rendering
  a WRONG but plausible `Week N, {date}` line anchored to today's calendar);
  clause 2 forbids guessing and fail-opens the date line. Recommended ruling
  (consistent with the brief's honest-fallback stance and clause 2's own wording):
  NO `now()` fallback — tooltip line 3 composes ONLY from a REAL semester row;
  semester row missing → the whole tip fails open to the single-line
  `title — body` (matching §2's tip fallback row), while `desc` (which needs no
  date) still composes. One sentence in §2 fixes it; block freeze until it lands,
  since the fail-open composer tests would otherwise encode whichever half the
  test author read.

### 💡 Optional

- D9 vocabulary drift: §5 lists `Just now` capitalised while brief D9 and the
  ask's parenthetical pattern (:53) are lowercase (`just now`) — pick lowercase
  for `($relLong)` consistency.
- §1's diagram lists `unreadTotal = BATCH-DERIVED` ABOVE `resolveRequests`, while
  §4's text correctly says "after rows load and are classified" — reorder the
  diagram line so nobody computes the count before `isInactive()` exists.
- §4's phrase "the unread-only toggle filter … read this ONE derived number"
  conflates the filter (a predicate) with the number; D5 already pins the
  semantics, but design.md could restate the literal predicate
  (`read_at === null && inactive_reason === null`) so the filter's inactive
  exclusion can't be forgotten.
- Multi-slot spans (junction count > 1) inherit `span()`'s first/last shape, so
  desc/tooltip show the first slot's week/day/venue with min–max times —
  pre-existing queue/history behaviour, deliberately reused; noting only so
  nobody "fixes" it inside this change.

## design Round 2 — 2026-11-01 09:47 +08

Scope: `.sdd/changes/notif-new-request-ux/design.md` (batch 2 RE-REVIEW after
round 1). All round-1 items checked against the new artifact contents;
re-verified spot-checks: `ClassSession.php:23/:27` (`session_type` fillable),
`ReplacementRequest.php:57/:70` (`replacementTimeSlot`/`classSession` both
BelongsTo → nested eager load valid).

### 🔴 Fixed

- **Round-1 blocker: §2's `$semesterStart` fallback self-contradiction —
  resolved with the recommended ruling and now internally consistent.** §2 pins
  "NO `now()` fallback: the tip's schedule line composes ONLY from a REAL
  semester row; a missing semester row fail-opens the WHOLE tip to the
  single-line `title — body`, while `desc` (which needs no date) still
  composes." Verified consistent with §2's granularity block (SCHEDULE lines
  all-or-nothing with the whole tip) and mechanically sound: desc's format uses
  no semester-derived date (week comes from the span), tip line 3's `{d M Y}`
  does — so the split is data-supported, not arbitrary. The fail-open composer
  tests now have one unambiguous answer.

### 🟡 Addressed

- **Pin 1 (composer time source)**: §2 — desc/tip times built from RAW
  `span.start`/`span.end` (24 h, UNspaced en dash `09:00–11:00`);
  `stringsFromSpan()['time']` keeps the legacy SPACED form for history
  consumers and is NOT the composer's source. §2/§5 vs §3 no longer
  contradict; stringsFromSpan stays the `date` source (§5 line 3).
- **Pin 2 (fail-open granularity + aria join)**: §2 — IDENTITY lines (1/5)
  per-line omitted (missing classSession/module drops line 5, tip survives);
  SCHEDULE lines (3+4) all-or-nothing with the whole tip; aria = surviving
  parts joined out, falling to `stored title — body ( (inactive))` only when
  title+desc BOTH fall back. One ruling for the whole test set.
- **Pin 3 (no classRow() on the poll)**: §5 line 5 reads `module_code`/
  `module_name`/`session_type` DIRECTLY from the eager-loaded
  `classSession.module` + `classSession.session_type` — "never call
  `classRow()` on the poll" (lazy venue + re-derived date). Fact-verified.
- **Pin 4 (§3 halves agree)**: §3 bullet 3 — `->with()` graph gains
  `replacementTimeSlot.venue` (law-legal: hits `time_slots`/`venues`), so the
  legacy no-`request_slots` shape composes from the LOADED anchor relation;
  "never lazy-loads on the poll; no per-row `replacementStrings()` call on
  this page." §1 diagram graph matches (`replacementTimeSlot.venue` present).

### 🔴 Outstanding

- (none — batch 2 may be frozen.)

#### 💡 Optional
- (round-1 optionals all folded: D9 lowercase `just now` in §5; §1 diagram
  `unreadTotal` reordered AFTER classification; §4 literal unread-filter
  predicate `read_at === null && inactive_reason === null`; multi-slot
  first/last-shape note in §3. Nothing further.)

## tasks Round 1 — 2026-11-01 14:40 +08

Scope: `.sdd/changes/notif-new-request-ux/tasks.md` (batch 3 — TERMINAL, no specs/
per the notif-lane family precedent: notif-ghost-retract-strip + notifications-real-bridge
both ship without specs dirs). Frozen chain: proposal.md (D1–D15) + design.md (frozen
2026-11-01) + explore-notes.md + the ask.

Pin sweep (design → task, all verified landed, no dropped pin): §1→T1.3/T2 (with-graph
incl. `replacementTimeSlot.venue`; ONE-QUERY law asserted in acceptance); §2→T2.2 (RAW
UNspaced time tokens; IDENTITY-vs-SCHEDULE fail-open granularity incl. missing-semester
whole-tip; title-with-ID/subtitle/desc/aria/tip keys; empty tip → `title — body`);
§3→T1 (stringsFromSpan keys + legacy SPACED `time` wrapper + spansForRequests ONE join
+ first/last shape; existing callers :69/:75-76 pinned unmodified); §4→T2.3/T2.4 (D15
chain with the status-null cancelled-copy edge, documented in-line; `:55` DELETE +
literal predicate + badge==pill==userTotal==filter==empty-state + markAll untouched);
§5→T3.3/T4.1 (exact 5-line formats, D9 lowercase vocabulary all six forms byte-matched,
D8 `j M Y, g:i A`); §6→T3/T4.2/T5 (subtitle span only-when-exists; no new tokens; mobile
inherits); §7→T1 (promotion) + T9 (changelog records it); §8→T6/T7/T8 (T6.1 proxies
:147/:191-201/:151-166; T7 :131-133/:148 + new env-guarded receipts; T6.3 keep-green
superset `:82`/`:253-274`/ONE-FEED/`:221`/email-side). §9 respected (no emitter/mail/
recipients/schema/icon tasks). Commit split + the four no-sweep exclusions + never-push
+ PHP gates correct. Env guards correct: writes :8030-only guarded, :8000 read-only,
ONE-QUERY law stays asserted green.

### 🔴 Fixed

(none — first review of draft tasks)

### 🟡 Addressed

- **T3-before-T4 rendering hazard (task ordering).** T3.3's `$rowTip` multiline branch
  consumes `$relLong`/`$tipAbs`, which T4 defines in `nav-notifications.blade.php`
  (:23-38 block). As numbered, landing T3 before T4 leaves the island throwing an
  undefined-variable blade exception on EVERY staff page (one-feed surface) between the
  two `ui:` commits. Fold: T4 lands in the SAME `ui:` commit as T3 (pinned in T3/T4).
- **Frozen-proposal item without an owning task: the F-2 test NAME rewording.** D5
  (proposal.md:80-82) commits "the F-2 test name / bridge-test assertion message get
  their comments reworded". T6.1 carries the bridge message; nothing carried the
  `notifications-island.spec.ts:26` test name. Fold: one clause added to T8.
- **Classifier type-scope vs the frozen D2 note (awaiting rows).** Design §4's chain
  applies to ALL `request_id !== null` rows with no type gate, so a decided request's
  unread `awaiting` reminder row flips inactive with the approved/rejected D15
  sentence — silently changing the behaviour the frozen proposal records as "stays
  active until read" (D2: flagged, not addressed). Recommendation accepted: RECORD it
  (a T6 assertion pinning the decided-awaiting case + a T9 changelog line recording
  the blanket chain as the deliberate D2 side-effect) — not re-rule (the blanket
  scope survived two design rounds).
- **T2's own steps omit the §1 batch wiring; the per-row variant passes every
  acceptance silently.** Fold: one explicit step ("wire the three §1 batches:
  spans → venues-by-id → sems-by-id; no per-row span/venue/semester lookup") + one
  acceptance clause ("no per-row Venue::find/Semester::find/DB::table span query on
  the poll path").

### 🔴 Outstanding

(none — batch 3 frozen; the four 🟡 soft-pins FOLDED into tasks.md as declarative
amendments immediately after this review, before apply.)

#### 💡 Optional
- T6.3: name the email-side tests explicitly — folding now.
- T4/T5 acceptance one-liners — folding now.
- T2 split option recorded (composer vs classifier/count = two `feat:` commits if
  the batch overruns 2 h).
- T9: verify `git status` at apply time; `page-changelogs/replacement-arrangement-changelog.md`
  (another lane's dirty file) must NOT be swept into the `docs:` commit.
- T8: confirm the demo mailbox for staff 5425 actually contains a cancelled submitted
  row before writing that receipt; if not, that receipt belongs in the T7 :8030 set.

---

## Apply closure (2026-11-01, pi session — the resume run)

Apply completed per the frozen chain: T1–T5 landed (commits `6886db2`, `591449e`,
`0b7674f`), T6–T8 receipts green (`acefe72`, `bcdaeb9`), T9 changelog + gates
closed with this flip. Gates: pint passed; PHPStan errors 0 (1G); phpunit 279
passed; `node --check ui-common.js` OK. T7 (:8030) 6/6 ×2 — the second run NO
reseed = the idempotency receipt (fix: id-keyed per-test fixture sweeps +
beforeAll residue sweep; the D15 receipt needs `unreadOnly=false` per the
Feature contract, since the classifier's predicate excludes inactive rows from
the unread-only list). T8 (:8000) 8/8 read-only. sdd.yaml flipped frozen →
applied; archives on the user's word only.
