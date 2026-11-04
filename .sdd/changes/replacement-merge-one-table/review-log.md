# Review log — replacement-merge-one-table

Append-only. One entry per review round per batch.

## proposal Round 1 — 2026-11-03 21:38

### 🔴 Fixed
- (none — first round)

### 🟡 Addressed
- (none — first round)

### 🔴 Outstanding
- **"Reads only" is false; the cancel path has no owner.** §4 ("This lane reads only", twice)
  contradicts M6/M10 (ban icon cancels a pending request through the Confirm Cancellation modal).
  The only implementation (`MyRequestHistory::cancelRequest/cancelRequests` →
  `CancelPendingRequest` with ownership/pending guard + D6 release + audit;
  `my-request-history.blade.php:275` `#cancelConfirmOverlay`) lives in what §3 item 4 leaves as
  "retires or becomes a thin forwarder", and §3 item 1 scopes the component change as payload-only.
  Risk: ban icon with no action, or a local status write that bypasses the guard/release/audit.
  Fix: name `CancelPendingRequest` reuse + relocate `#cancelConfirmOverlay`; reword §4.
- **Test-impact inventory is wrong.** "25 + 34 test files" is unsourced (measured: 20 files
  reference `/replacement-home-ui`, 22 reference `/my-request-history-ui`; the brief says 23), and
  "the history-only ones (bulk cancel, quick-cancel column, timeline)" omits
  (a) the reversed landed receipts `tests/replacement-home-status-badges.spec.ts:41-64` (D2
  badge-link) and `tests/request-completed-status.spec.ts:104` (badge href),
  (b) the class-level tests no alias can save — `tests/Feature/ReplacementHistoriesTest.php:178-190,206-229,250-290,346-380`
  and `tests/Feature/SpanReservationTest.php:454-476`,
  (c) page-identity e2e specs `tests/e2e/history.spec.js:6`,
  `tests/e2e/smoke-my-request-history.spec.js:6`, `tests/e2e/auth-wiring.spec.js:294`,
  `tests/e2e/full-suite.spec.js:453-472,593,645,692`, which the alias cannot keep green.
  The mitigation "the alias keeps URL-level specs green" is therefore false and is the stated
  reason no further test work is scoped.

### 🟡 Non-blocking (fold into §3/§2 or park in §8)
- **URL-consumer inventory incomplete + "alias" undefined.** `notif-row.blade.php:96` does a strict
  `===` compare on the stored link string before emitting the highlight anchor; `requestHref()`
  (`replacement-home.blade.php:376`, used at `:383`, `:643`, `:649`, `:768`) is the code M6 deletes
  and would point the merged page at itself. A route-table alias at the same URI preserves the query
  string; a redirect silently drops `?notif-hl=` and `?blocked=…` (asserted in
  `ReplacementArrangementTest.php:643,723`, `arrangement-entry-guard.spec.ts:144`). The proposed
  "both URLs render the merged page" test passes for both designs, so it does not cover the risk.
- **`?notif-hl=r<id>` has no target on a class-keyed row.** `applyNotifHighlight`
  (`ui-common.js:320-341`) matches `tr[data-id="<requestId>"]` and then skips silently
  (`:356`); the merged row id is the occurrence (`ReplacementHome.php:118`). Needs both id spaces
  (`data-request-id`) or a server-side mapping.
- **Brief's filters/sort/reset-law block dropped.** Search · week navigator · status select ·
  Exclude Completed ON by default (completed lane C4) · reset · chips with ×/Clear all and the two
  different reset laws · needs-action-first grouping with sort inside groups · `.sort-hint` ·
  `.result-count` · the dash rule. M1–M12 carry none of it, yet M9 presupposes it.
- **CSS scope is three definitions, not one.** Besides the icon-only button (§3 item 8), the brief
  needs a neutral-grey conflict badge (replacing nine page-local classes,
  `replacement-home.blade.php:27-59`) and an **outlined** `Need to Arrange` pill; `theme.css` has
  only filled variants (`:1161-1183`).
- **Brief's flagged blocker not carried.** `ui-page-header.blade.php:22` escapes `$description`, so
  the locked copy with `.info-keyword` spans prints as literal HTML. Brief offers `descriptionHtml`
  + `{!! !!}` for this page, or switching the shared partial (`ui-summary-bar` is the precedent).
- **Modal shell ambiguous.** Home uses `#quickViewModal` (`replacement-home.blade.php:303`,
  `:724-757`), history uses `#modalOverlay` (`my-request-history.blade.php:256`, `:864`);
  `tests/ui-regression.spec.ts:149` pins both id spaces. M7 specifies contents only, and it is
  unstated whether the phone-card quick view survives.

### Notes
- Citation nit: `ReplacementHome.php:139-142` is `cohortCounts`; the `effectiveStatus()` call is
  `:151-157`.
- "25 + 34" is unsourced; measured 20 / 22.
- `routes/web.php:71` keeps `'nav' => 'replacement-history'` for the alias while the component
  renders `activeNav = 'replacement-arrangement'` — harmless, worth one sentence so nobody "fixes"
  it into a second nav highlight.
- Days-left stays client-side (`urgency-*` + `daysLeftLabel()`); any move of filtering to the
  server must keep that computation in the payload.

**Verdict: FAIL** — 2 blocking, 6 non-blocking. No design decision needs re-opening.

## proposal Round 2 — 2026-11-03

### 🔴 Fixed
- **"Reads only" / cancel-path owner (round-1 blocker 1).** §4 now reads "read-only except one
  existing write path" and names it; §3.1 adds `ReplacementHome::cancelRequest(int $id): bool`
  delegating to `CancelPendingRequest`; §3.2 keeps the action unchanged; §3.4 relocates
  `#cancelConfirmOverlay` with its markup, `openCancelConfirm()`/`closeCancelConfirm()`
  (verified: `my-request-history.blade.php:892-901`) and the `pendingCancelId = null` double-submit
  guard (`:695-697`). M12 removes `cancelRequests()` with the bulk bar. Verified: the delegation is
  exactly today's shape (`:117-120`), and `pageLivewire()` resolves whatever component is on the page,
  so the moved call still reaches the right owner.

### 🟡 Addressed
- **URL consumers + alias definition** — all consumers check out (`NotificationEmitter.php:64,88`;
  `FROM_LANDING_ROUTES:79`; `notif-row.blade.php:96` strict `===`). "Second URI in the route table"
  is implementable: `routes/web.php` is a `$uiPages` array rendered by one `foreach`, so a second key
  keeps the query string and `role:lecturer`. Residual → R2-4.
- **`?notif-hl=` targeting** — mechanism confirmed (`ui-common.js:330` selector, `:356` silent skip).
  Residual → R2-4.
- **Filters / reset law** — carried into M17. Residual → R2-3.
- **CSS scope = three definitions** — all three real: `.btn-icon` only in the ≤768 px block
  (`theme.css:3326`), `:1161-1180` filled only, conflict classes page-local
  (`replacement-home.blade.php:27-59`, ten not nine).
- **Page-header escaping** — `ui-page-header.blade.php:22` escapes, `ui-summary-bar.blade.php:35` is
  the `{!! !!}` precedent; `descriptionHtml` is a two-line change.
- **Modal shell** — M7 names `#quickViewModal`, retires `#modalOverlay`. Its test consequence moved to
  R2-1/D.

### 🔴 Outstanding
- **R2-1 — §6 group A was still "nothing breaks", and four in-bucket files prove otherwise.**
  `request-cancel-pending.spec.ts:68-89` (expects the row to vanish after a cancel; M1 keeps it as
  `Cancelled by YOU`, and it cancels the *first* row), `warning-keywords.spec.ts:13-24`
  (`#cancelRequestBtn` inside the modal), `ui-regression.spec.ts:151` (`#modalOverlay` → goes vacuous,
  not red), `e2e/full-suite.spec.js:593-603` (`#resultCount` + search on the MRH URL).
- **R2-2 — group B's line ranges cover a fraction of the files they name.**
  `replacement-home-status-badges.spec.ts` classifies rows by `a.badge-link` in its shared extractor
  (`:34-35`) and reads `.col-urgency` (`:32`), so receipts 3–7 break too, not only D2 `:41-64`.
  `request-completed-status.spec.ts`: only `:104` was cited while receipts 1–3 (`:85-118`, `:120-141`,
  `:143-159`) all break. "The new assertion is the badge's text" described a two-line edit that does
  not exist.

### 🟡 Non-blocking
- **R2-3** the arrival-reveal law (completed lane R5) and the `mrh-filters` key were missing from the
  filter block.
- **R2-4** §3.10 required `public/js/ui-common.js` but no surface row listed it (shared with
  `/request-approval-ui` and `/my-timetable-ui`, so the edit must be additive).
- **R2-5** the moved cancel overlay is not a cut-and-paste: the history handler splices `mockRequests`
  and calls `renderTable()`, while the merged table is built from `payload.conflictedClasses`.
- **R2-6** §1/§4 implied a page still renders the trail, while §3.6 retires the only one that does.

**Verdict: FAIL** — blocker 1 fixed; blocker 2 source-fixed but not classified.

## proposal Round 3 — 2026-11-03

### 🔴 Fixed
- **R2-1** — all four in-bucket files are in the revised §6 D bucket with reasons that hold:
  `request-cancel-pending.spec.ts:68-89` (`:68-70` cancels the *first* row, `:82` `toHaveCount(0)`),
  `warning-keywords.spec.ts:13-24`, `ui-regression.spec.ts:151` ("vacuous, not red" is exactly right —
  `:160-163` gates on a hidden selector, so the body never runs), `e2e/full-suite.spec.js:593-603`.
- **R2-2** — every citation re-read and exact: `replacement-home-status-badges.spec.ts` extractor
  `:29-38`, receipts 3–7 `:71-85`, `:87-93`, `:95-108`, `:110-124`, `:126-135`;
  `request-completed-status.spec.ts` receipts `:85-118`, `:120-141`, `:143-159`.

### 🟡 Addressed
- **R2-3** — M16 carries the reveal law and the `mrh-filters` hazard, which is real:
  `replacement-home.blade.php:219` has "NO localStorage", while the law lives in
  `my-request-history.blade.php:359-363` and the key at `:423/:432`.
- **R2-4** — item 10b lists `ui-common.js` as additive; sharing confirmed
  (`applyNotifHighlight` `:307`, `:320-342`; `request-approval.blade.php:683`;
  `my-request-history.blade.php:570`).
- **R2-5** — item 10c cites `my-request-history.blade.php:690-714` exactly; the merged side is
  `payload.conflictedClasses` → `HtmlBuilder.replacementHomeRow` (`replacement-home.blade.php:518`),
  and `buildTable()` does run `updateSummary()` (`:589`), so the cards refresh too.
- **R2-6** — §1 states the trail is DB-only for lecturers; `/replacement-history-ui` is
  `role:student` (`routes/web.php:50-55`), so that is true.

### 🔴 Outstanding
- **R3-1 — three grep-matched files were in no bucket:** `replacement-home-qv.spec.ts` (plays the
  quick view → arrange handoff, i.e. the DOM this lane rebuilds), `arrangement-entry-guard.spec.ts`
  (in both grep sets), `replacement-strip-anchor.spec.ts`.

### 🟡 Non-blocking
- **R3-2** B row 3 named a nonexistent artifact: My Requests' badge is a `span` with `onclick`
  (`my-request-history.blade.php:576`); the anchor is `replacement-home.blade.php:383`.
- **R3-3** `full-suite.spec.js:593-603` was overstated as a break (`textContent()` needs attachment,
  not visibility).
- **R3-4** no DOM keep-list; `HtmlBuilder.replacementHomeRow` (`ui-common.js:2481-2506`) unscoped.
- **R3-5** "accepts either id" has no precedence (`querySelector` `:349` resolves by document order).
- **R3-6** §1 claimed the home row still vanishes (the badges lane removed that).
- **R3-7** "verified in the lane's own run" claimed a run that cannot exist.

**Verdict: FAIL** — one blocker (R3-1); R3-2…R3-7 declarative.

## proposal Round 4 — 2026-11-03

### 🔴 Fixed
- **R3-1** — the union is now fully bucketed. Re-measured: 28 code files match
  `replacement-home`, 19 match `my-request-history`, 11 in both, union **36**; every one appears in
  A / A′ / B / C / D. The three former orphans carry verifiable trailers: `arrangement-entry-guard`
  (`:134` toast href, `:144` blocked target, `:231` post-submit redirect), `replacement-strip-anchor`
  (`:12` `from=replacement-home`, then arrangement-page ids only), `replacement-home-qv` → A′. A
  spot-audit of A found no file touching an element M6/M7/M12 delete.

### 🟡 Addressed (verified against the code)
- **R3-2** the corrected cause is right (`my-request-history.blade.php:576` span vs
  `replacement-home.blade.php:383` anchor).
- **R3-3** `textContent()` needs attachment, not visibility, so the caveat is true.
- **R3-4** item 2b is scoped (`ui-common.js:2481-2506` via `replacement-home.blade.php:518`; frozen
  template `ui-design-templates/replacement-home-UI-design-template.blade.php:412`; `config/app.php:29`).
- **R3-5** precedence stated and matching `:330` / `:349` / `:356`.
- **R3-6** §1 is past-tense and true (`ReplacementHome.php:66-72`, `:77-79`).
- **R3-7** no run claimed.

### 🔴 Outstanding
- (none)

### 🟡 Non-blocking after PASS (all folded, declarative — no decision changed)
- **R4-1** §3 item 4b is narrower than A's claim: A files also read `#searchInput`, `#resultCount`,
  `#cardView`, `#timetable`, `#toastBar`. → folded: 4b extended, and A's justification restated.
- **R4-2** A′ needs the payload's key, three row fields and array order, not just ids. → folded.
- **R4-3** `type-labels.spec.ts` pins rows by venue/time text, and M4 adds a second venue/time to the
  same row. → folded as a caveat.
- **R4-4** `full-suite.spec.js:593` sat in two buckets; a stray "(moved to A)" had no row. → folded:
  593 dropped from C, the stray line removed.
- **R4-5** the "All files" column (34/25) does not match the measurement (32/23); the code column
  (28/19/11/36) is exact. → folded: corrected to 32/23 with the reconciliation note.

**Verdict: PASS** — proposal frozen for `design.md`.

## proposal Round 5 — 2026-11-03

<!-- Unfrozen by the user's decision-level rename (canonical /my-replacements; both old URLs stay as
     route entries for the same component). The round-4 PASS stands for everything outside
     §2 M2, §3 items 5 / 7 / 7b / 7c, §6, §7, §8. Only the URL/name decision was re-opened. -->

### 🔴 Fixed
- (none from round 4 — R4-1…R4-5 stay folded; the rename does not disturb them)

### 🟡 Addressed (re-verified against the repo)
- **The rename is implementable.** `routes/web.php` is a `$uiPages` map rendered by one `foreach`
  (`:31-96`), so a third key gets the same middleware, keeps the query string and needs no redirect.
  `ReplacementHome.php:170` already passes `activeNav => 'replacement-arrangement'` — the key M3 gives
  the single nav item — so the highlight needs no new code, and the mobile drawer reuses `$items`
  (`ui-nav-bar.blade.php:101`).
- **`NavIdentityTest` / `nav-identity.spec.ts` stay green** — both assert the *absence* of the old
  hrefs on the **student** page (`:110-111`, `:67`), which a lecturer rename cannot touch.
- **Defect 2 confirmed** — `ui-common.js:3079` is live: `getBackUrl()` resolves `#routes[from] ||
  getDefault()` (`:3085`), and `ReplacementArrangement.php:201` is the server-side twin.
- **The live/stored split is correctly drawn** — `NotificationEmitter.php:64,88` writes the bell row's
  link, `notif-row.blade.php:104` renders it, `:96` is a strict `===` gating the highlight. Moving any
  of them orphans every stored notification.
- **Copy sweep (7c) is complete** for user-facing text; every other match is a comment, a test, or a
  frozen template.
- **No missed live navigation** — full sweep of `app/`, `resources/` (minus `ui-design-templates/`) and
  `public/`; `navigateHome()` resolves `PAGE_HOME` (`/my-timetable-ui`), not an old URL.

### 🔴 Outstanding
- **R5-1 — §6 bucket A is now false for three files, and §3 item 7b is the cause.** A promises "green
  through the alias", but the alias only keeps *link-followed* navigations green; it does not keep
  green an assertion whose **expected value** is a URL that 7b moves:
  `Feature/ReplacementArrangementTest.php:643` (`assertRedirect('/replacement-home-ui?blocked=request-approved')`),
  `:723` (`assertReturned('/replacement-home-ui?blocked=request-pending')`) — **these run on every
  PHPUnit pass**, so layer A goes red on the first apply run; `arrangement-entry-guard.spec.ts:134`
  (toast href), `:144` (URL contains `/replacement-home-ui?blocked=…`), `:231` (waitForURL after
  submit); `submission-conflict-refresh.spec.ts:84` (waitForURL), `:111` (`?blocked=request-pending`).
  The two Playwright files are testing-DB gated, so on a bare run they skip — "green" that is really
  absence of proof.

### 🟡 Non-blocking (all folded)
- **R5-2** the `?id=` premise was wrong: `my-request-history.blade.php:1043-1057` *does* read it
  (fed by `:334`/`:341`, `MyRequestHistory.php:79`). The action is right for the opposite reason —
  the merge deletes that reader, so the producer must move to `?notif-hl=`.
- **R5-3** each `$uiPages` entry needs `component` / `legacy` / `nav`; `class_exists`
  (`routes/web.php:89`) makes a stale `MyRequestHistory::class` fall back **silently** to the frozen
  old template — a false green, not a 404.
- **R5-4** `RouteGateMatrixTest` needs `/my-replacements` added (`:29-51`, docblock `:16`), or the URL
  the nav now points at is gated by nothing.
- **R5-5** the frozen `ui-design-templates/replacement-arrangement-UIdesign-template.blade.php:1916`
  names the old URL and must be named as deliberately frozen.
- **R5-6** 7b was ambiguous between `#routes` **keys** and **values**: only the values move; the keys
  and the `from=` producers stay.
- **R5-7** atomicity has a second edge: the `?notif-hl=` producer ↔ the merged row's `data-request-id`.
- **R5-8** "eleven call sites" did not match the enumeration (8 live files / 16 line occurrences).

**Verdict: FAIL** — one blocker (R5-1); nine total, the rest declarative.

## proposal Round 6 — 2026-11-03

<!-- The user's one extra round. Scope: the new §6b (data contract), the R5-1 fix, and the eight
     round-5 items. §1–§5, §7, §8 and M1–M17 were cleared in round 5 and were not re-reviewed. -->

### 🔴 Fixed
- **R5-1 — closed, exactly.** The two `Feature/ReplacementArrangementTest.php` assertions are out of
  A and in A″ with the "runs on every PHPUnit pass" warning; A″'s seven rows check cause-by-cause
  (`FROM_LANDING_ROUTES` values `:78-79`, the `?? '/replacement-home-ui'` default `:201`, the toast
  URL `:4420,4423`, the post-submit `location.href` `:2245`, `submission-conflict-refresh:111`).
- **The buckets are whole again.** 36 code files match; all 36 bucketed (A 23 · A″ 3 · A′ 1 · B 3 ·
  C 6 · D 3). `C`'s two class-level files sit outside the grep union, so the claim stays one-way and
  true. `e2e/home.spec.js:10` / `auth-wiring.spec.js:293` assert the title through a `RegExp`
  (`tests/e2e/helpers/page-check.js:37`), so "My Replacements" still matches.

### 🟡 Addressed (round-5 items, re-verified)
- **R5-2** `?id=` is read at `my-request-history.blade.php:1043-1057`, produced at
  `ui-common.js:1292`; M2 + 7b now say so. Consequence → R6-7.
- **R5-3** `$uiPages` `:31-96` with the `:88-93` silent `class_exists` fallback, exactly as described.
- **R5-4** gate matrix pins the URL at `:34,36` and `:46,48`, docblock `:21-22`.
- **R5-5** the frozen template is named. **R5-6** values-vs-keys matches `:3070-3076` / `:3085`.
- **R5-7** the second coupling edge is stated. **R5-8** the 16 occurrences are exact.

### 🔴 Outstanding
- **R6-1 — §6b named the anchor as the source for "Requested Replacement"; the real source is the
  span read, and the modal's new schedule tab had no source at all.** `replacementTimeSlot` is only
  the **fallback** in `ReplacementRequestMapper::span()` (`:206-238`); every consumer renders
  through `replacementStrings()` → `displaySlot()` → `span()`. `SubmitReplacementRequest` reserves a
  1–4 cell **span** with the anchor = the first cell only (`:33-35`, `:98-110`, `:146`), so a 2-hour
  arrangement would have printed `09:00 – 09:30`. `CancelPendingRequest` deletes the junction rows,
  which is why the mapper has the two-step rule. Also: the "same date" clause sat on the occurrence
  date while `effectiveStatus` judges the **replacement** date (`:93`, `:99-101`).

### 🟡 Non-blocking
- **R6-2** law 3's premise was wrong: three base queries today, plus **two per approved row**
  (`span()`'s junction read + the `TimeSlot → venue` lazy load). An additive batch entry point is
  required, not optional.
- **R6-3** law 4 ("re-read") contradicted item 10c ("update the row locally").
- **R6-4** `Status Description` had no §6b row although the two pages mean different things by it.
- **R6-5** the `submitted_at` null clause was impossible (`NOT NULL`) and backwards (pgsql sorts
  NULLs **last** on ASC).
- **R6-6** "8 files" mixed scopes: 6 live files + 2 frozen consumers.
- **R6-7** `?id=` → `?notif-hl=` silently downgraded "View Full Request" from *opens the modal* to
  *scroll and pulse* (which M16 can even answer with "hidden by your current filters").
- **R6-8** `#cancelRequestBtn` was missing from the 4b keep-list while two specs click it; and the
  modal shows **two** venues (original + proposed) with one §6b row.

**Verdict: FAIL** — one blocker (R6-1); all eight folded above.

## proposal Round 7 — 2026-11-03

<!-- Confirmation pass granted by the user. Scope: the eight round-6 findings only. Every fold was
     re-checked against the code, not against round 6's own text. -->

### 🔴 Fixed
- **R6-1 — folded, and correctly.** §6b names `replacementStrings()` → `stringsFromSpan()` as the
  one source, junction (min→max) first and the anchor "only as the fallback". Verified end to end:
  `span()` reads the junction ⋈ `time_slots` (`:206-238`), `displaySlot()` models it (`:241-258`),
  `replacementStrings()` delegates (`:265-285`), `stringsFromSpan()` formats (`:303-313`). The
  junction is DELETEd by `CancelPendingRequest.php:85-88` **and** `RejectRequest.php:132-134`, so
  "anchor only" would have printed `09:00 – 09:30` for a 2-hour arrangement. The occurrence-vs-
  replacement date conflation is gone (§6b rows for the occurrence date and the row status).

### 🟡 Addressed
- **R6-2** the batch entry point exists (`spansForRequests()` `:327-359`); its gap became R7-1.
- **R6-3** law 4 and item 10c now say the same thing. **R6-4** the `Status Description` row exists.
- **R6-5** `NOT NULL` is right (`create_replacement_requests_table.php:26`). **R6-6** the 16
  occurrences reconcile (3 + 6 + 2 + 1 + 2 + 2). **R6-7** the downgrade is named. **R6-8**
  `#cancelRequestBtn` is in the keep-list and the second venue has its own row.

### 🔴 Outstanding
- **R7-1 — law 3's batch path silently drops the anchor fallback, so two of the six states render
  `—` for a request that had a slot.** `spansForRequests()` reads `replacement_request_slots` only
  (`:333-345`), so it cannot return a span for a **cancelled** request (junction deleted,
  `CancelPendingRequest:85-88`), a **rejected** one (`RejectRequest:132-134`) or a legacy
  anchor-only row. `stringsFromSpan(null, …)` returns all-nulls (`:299-301`) → "Requested
  Replacement" prints `—`, and M7 hides the Proposed Replacement Schedule tab for them. That
  contradicts §6b's own "anchor is the fallback" clause and invents absence — against the user's
  bar. Calling `replacementStrings()` per row instead would reinstate the N+1 law 3 forbids.

### 🟡 Non-blocking
- **R7-2** `?notif-hl=` has two producers (bell `ui-common.js:2135-2143` = jump; View Full Request
  = open the modal); one param cannot distinguish them.
- **R7-3** §6b held two venue rules for the modal (code+name in one row, `room_name` label in
  another). **R7-4** §7 still said "eight files", counting the two frozen consumers 7b freezes.
- **R7-5** `statusDesc()` covers five keys and returns `''` otherwise (`:782`), so
  `Need to Arrange` and a label-keyed `Completed — …` would print an empty row — and the no-request
  state of the Request Status tab was unspecified.
- **R7-6** the nulls parenthetical was backwards (pgsql: NULLs last on ASC, **first** on DESC).
- **R7-7** 4b kept `#cancelRequestBtn`'s id but not the law that gates it to `pending` only
  (`my-request-history.blade.php:876`).

**Verdict: FAIL** — R6-1 closed; one new blocker (R7-1) introduced by the R6-2 fold; all eight
folded above (law 3's fallback step, the `&view=request` marker, one venue rule, the commit set,
the six-state description map + the no-request tab, the nulls parenthetical, the Cancel gate).

## proposal Round 8 — 2026-11-04

<!-- Confirmation pass (user-granted). Scope: the seven round-7 findings only. Every fold re-checked
     against the code, not against round 7's own text. §1–§5, M1–M17 and §7's other rows were
     cleared earlier and are not re-litigated here. -->

### 🔴 Fixed
- **R7-1 — folded, and law 3 is a faithful restatement of `span()`, verified step by step.** `span()`
  (`ReplacementRequestMapper.php:206-238`) = junction ⋈ `time_slots` ordered by `start_time`; first
  row's week/day/venue/start, last row's end, `count` = row count; step 2 = `$r->replacementTimeSlot`
  with `count: 1`. `spansForRequests()` (`:327-359`) returns exactly that shape grouped by request id,
  so "id absent from the map → build it from the anchor" reproduces `span()` field for field, and both
  paths format through the one helper `stringsFromSpan()` (`:297-313`). The anchor is genuinely
  preloaded (`ReplacementHome.php:74`, `replacement_time_slot_id` in the narrow select `:80`), and
  `ApproveRequest:54` only *reads* the junction — so approved rows always keep their span and the
  batch path cannot suppress the `completed` state. Cancelled and rejected rows now print their slot
  instead of an em dash, and keep their Proposed Replacement Schedule tab.
- **No new cross-law contradiction.** Law 5 survives an *optional* span argument (`ApiReadController`
  reaches `effectiveStatus` only via `toRow():131`); law 2 survives because the span is injected, not
  re-derived; `effectiveStatus()`'s `lt(today())` is unaffected by where the span came from.

### 🟡 Addressed
- **R7-2** item 10 names both producers and carries `&view=request`; `applyNotifHighlight` strips only
  `notif-hl`/`notif-w` (`:324-327`), so the marker survives. **R7-3** one modal venue rule, matching
  `NavNotifications.php:678-679`. **R7-4** "six live files" reconciles exactly (3+6+2+1+2+2 = 16 URL
  occurrences); the copy edits (`:1214`, `:4419`, `:4422`) are correctly excluded.
- **R7-5** the description keys on the **status** and covers all six plus the no-request sentence.
  **R7-6** the pg parenthetical is now correct (`NULLS LAST` on ASC, `NULLS FIRST` on DESC).
  **R7-7** 3b keeps the pending-only gate.

### 🟡 Residuals — folded after PASS (wording only, implementation unchanged)
- **10b** now names the mechanism (`tryFind($sel, $fallbackSel)` → `querySelector($sel) || querySelector($fallbackSel)`),
  never a comma-joined list — that form resolves by document order and restores the wrong-row match.
- **10** now defines "present" as **present in `payload.conflictedClasses`**, not in the DOM: a saved
  `mrh-filters` filter or the week navigator could otherwise hide a row that exists and silently
  restore the downgrade.
- **10c** now keeps **`closeModal()`** (overlay *and* `#quickViewModal`, `:703-705`), because a
  still-open detail modal would show a stale Cancel button against item 3b's gate.
- **3b** now records that the node is **static** with a `style.display` gate (`:268`, `:876`), and that
  opening the modal must bind the viewed request id (`:689`, `:809-810`).
- **§6b row 11** now points at `stringsFromSpan()` directly and says the merged page never calls the
  per-row `replacementStrings()`; and it states the anchor limitation instead of hiding it (a cancelled
  2-hour arrangement prints `09:00 – 09:30`, inherited from `replacementStrings()` — a later lane).
- **§6b nulls row**: the guard, if the column ever became nullable, is **`nulls first`**, not
  `nulls last` — with `keyBy` keeping the last row on an ASC order, NULLs-last is what would make an
  undated row newest.

### 🔴 Outstanding
- None.

**Verdict: PASS** — the round-7 blocker is closed; law 3 yields `replacementStrings()`-identical output
with no per-row query (three base reads + one batch span query + the venue preload + the eager anchor);
§6b is internally consistent, one source per value, all six row states plus the no-request state
resolving on both schedule tabs and the Request Status tab.

**Batch 1 (proposal) is FROZEN at this entry** — 8 rounds, 2026-11-03 → 2026-11-04.

## design Round 1 — 2026-11-04

<!-- Scope: design.md (head 02cd2db) against the FROZEN proposal (M1–M17 + §6b), the explore brief
     and the prototype. Every code claim was re-read at head bcab5f5. No M-decision is re-opened;
     the blockers are omissions/contradictions inside design.md itself. -->

### 🔴 Must fix
- **D1 — the page's identity landed nowhere.** `@section('title')` (`replacement-home.blade.php:6`),
  the H1 + description include (`:194`) and proposal §3 item 8's `descriptionHtml`
  (`ui-page-header.blade.php:21-23`, escaped) were absent from every section, from §7's file table and
  from §10's commits. Every `/My Replacements/` title assertion the design itself writes would be red,
  and the surviving description told users to click the badge anchor M6 deletes.
- **D2 — the modal's Original Class tab needed the occurrence's `room_name`, and §1.5's key list was
  closed without it** (only `replacementVenueName` existed). §6b requires `room_code — room_name` on
  both schedule tabs.
- **D3 — "All 14 are `null` when `$latest === null`" contradicted §1.6/§3.3/§4.3/§9**, and the
  no-request row is the majority row: one reading printed an empty badge and an empty Status
  Description, the other created the JS status map §6b law 2 forbids.

### 🟡 Should fix
- **S1** a `ClassCancellation.applyLedger` row has no request block → `undefined` in four call sites.
- **S2** §1.8's budget understated the existing reads (6 + 1 + 3, and `venues` 3×), so §8.1 case 8 was
  impossible as written (`replacement_request_slots`/`venues` = 1 + 1).
- **S3** bucket D had a second reversal: `ui-regression.spec.ts:322-334` pins `#summaryMy*` ids and a
  "Courses" card M9 deletes; the five cards' `valueId`s were never named.
- **S4** `replacement-home-status-badges.spec.ts` receipts 2/5/6 still asserted the badge-anchor
  navigation and `#qvArrangeLabel === 'View Request'`, both deleted by M6/M7.
- **S5** `ui-regression.spec.ts:166` would go live for the first time and assert `'Subject Code'`, which
  the merged modal has no row for.
- **S6** `ReplacementHistoriesTest::createRequest()` stamps no `ClassException`, so four re-pointed
  tests would see `payload = []`; and the cancel fixture's `14:00 – 14:30` is its own slot range, not
  proof of the anchor fallback.
- **S7** `#resultCount`'s `M` was defined **before** the completed exclusion, contradicting the
  completed lane's C4 law and the design's own "both come from the same array".
- **S8** §7 dropped proposal 7c's copy edit (`ReplacementArrangement.php:1214` still says "My Request
  History").
- **S9** §4.4 copied `closeModal()` verbatim — on the merged page that is the shared `#classModal`
  helper, so a stale `#quickViewModal` would keep a Cancel button against a cancelled row.
- **S10** §3.4's A′ premise was imprecise: the spec matches by `lecturer`, and it needs a needs-action
  first row or `#qvArrangeBtn` is hidden and it times out.
- **S11** two rewritten assertions could pass vacuously (an empty `forEach`; `>= 1` after a 90% row
  loss).
- **S12** the two schedule tabs would print two different time formats, neither matching the brief's
  12-hour `~` + duration-in-words.

### ✅ Confirmed
- `spanFromAnchor()` is `span()`'s step 2 field for field and is reachable after both junction deltas;
  `spansForRequests()` really reads the junction only.
- `effectiveStatus()`'s third parameter is inert for `toRow()`/the API (`toRow():131` is the only call
  site in `app/`), so law 5 holds and `completed` stays in one place at zero query cost.
- The two added select columns are the right two; `submitted_at` is `NOT NULL`.
- `buildRow()`/`rowFor()` is one shaping path and the returned row carries what the two repainted
  widgets need; no other `cancelRequest` caller exists.
- The retirement is clean (no reference outside its own file, the mapper's comments and the deleted
  view), and §7's ordering genuinely closes the `class_exists` silent-legacy window.
- §3.4's payload-in-display-order does keep A′ green; §5.2's `||` fallback is the right mechanism;
  M4–M17 all land on something concrete; §6's CSS map is complete and token-only.

**Verdict: FAIL** — three blocking omissions, twelve non-blocking, four optional. All folded in
`design.md` after this entry; round 2 confirms them.

## design Round 2 — 2026-11-04

<!-- Confirmation pass. Scope: the three round-1 blockers and the twelve should-fix items only.
     §1.1–1.4, §2.2–2.4, §5.1, §5.2, §6 and §7's URL rows were cleared in round 1. Every fold was
     re-checked against the code at head 693f967, not against round 1's own text. -->

### 🔴 Fixed
- **D1** all three identity surfaces named at the right lines (`:6`, `:194`, `ui-page-header:21-23`),
  with a §7 row and a commit. Residual → A1/A7.
- **D2** `venueName` = `$session->venue?->room_name`, nullable, emitted for every row; tab 1 renders
  `CODE — Name` with the code-only fallback. `venue` is already eager-loaded (`:51`).
- **D3** the split counted exactly: nine request-only keys null, five status keys filled from the
  `null` row, `venueName` request-independent = the 15 keys. §1.5 ↔ §1.6 ↔ §3.3 ↔ §4.3 ↔ §9 agree;
  `requestStatus = null` keeps `isHandled()` false, so a no-request row never lands in the handled
  group.

### 🟡 Addressed
- **S1** accessors named (one residual → A2). **S2** arithmetic 6+1+3+1+1 = 12 and "venues 3×" correct
  (count still short → A4). **S3** five ids implementable (`ui-summary-bar:31` renders `valueId`).
  **S4** receipts 2/5/6 have successors (3/4 mis-described → A5). **S5** `'Class ID'` is real
  (per-entry needed → A6). **S6** the premise holds (inline creates uncovered → A3).
- **S7** matches the live call (`:588` passes `visibleBaseRows().length`;
  `ui-common.js:1698` prints `Showing <len> of <total>`). **S8** `:1214` verified verbatim. **S9**
  `hideQuickView()` is the right closer (`:785-788`) and the shared `closeModal()` really is
  `#classModal`; the guard is null-first and shape-independent. **S10/S11/S12** all verified.

### 🟡 Residuals — all folded before the freeze
- **A1** the partial's `@php` block needs `$descriptionHtml = $descriptionHtml ?? null;` — a bare
  `@if` throws for `[]` callers (`replacement-arrangement.blade.php:984`) and white-screens that page;
  the copy is written inline; `$chips` is a third key, not a second.
- **A2** the tab title reads the **short** form → a third accessor `statusShortOf(c)`.
- **A3** the three inline `ReplacementRequest::create()` calls (`:352, :381, :387`) need the exception
  stamp too, not just `createRequest()`.
- **A4** the absolute count is **15**, not 12 (`Semester::active()` + `holidaysForJs()` twice); the
  constancy claim and case 8 are unaffected.
- **A5** receipts 3/4 are the D7 em-dash and D8 ordering laws; the days extractor must keep a
  days-specific selector inside `.col-original`, and receipt 1's `hasArrange` probe becomes
  `.btn-icon` (the icon-only cell deletes `.btn-action`).
- **A6** `:166` is shared by six `modalPages` entries → expected text/selector move into the tuple;
  and §3.7 retires **three** ids, the one worth asserting absent is `#summaryMyCourses`.
- **A7** §7 gained a Commit column: the identity and `requestHref()` rows ship in commit 2, the URL
  rows in commit 3.
- **A8** the tips block `:200, :202` repeats the deleted sentence → rewritten (§2.1c).
- **A9** `requestHref()`'s three live readers named (`:383`, `:643`, `:649`).

### 🔴 Outstanding
- None.

**Verdict: PASS** — D1/D2/D3 closed; S1–S12 closed with the nine one-line residuals above, all folded
before the freeze. **Batch 2 (design) is FROZEN at this entry.**

## specs Round 1 — 2026-11-04

<!-- Scope: specs/replacement-page-merge/spec.md (head 97e23c1) against the FROZEN proposal and
     design. No M decision and no design [D] re-opened. Findings are coverage (a requirement with no
     demonstrable fact), checkability (a fact that passes while the behaviour is wrong, or that can
     only be greened by breaking a frozen law), and two contradictions with the frozen set. -->

### 🔴 Must fix
- **SP1 — S3.4 could only be greened by editing the files M2 freezes.** Its scope was "`app/`,
  `resources/` or `public/js/`", which contains the seven `mock-data.js` link fixtures,
  `notif-row.blade.php:96`'s strict `===` on the stored link, and the `ui-design-templates` copy —
  only two of the four frozen consumers were excepted. An apply agent chasing green would edit
  `notif-row:96` and every stored bell link would stop highlighting: the exact regression M2 exists to
  prevent.
- **SP2 — S16.1 demanded a 2-hour range that no longer exists.** After `CancelPendingRequest` the
  junction is deleted, so the fallback prints the anchor's 30-minute cell; §6b, design §1.3 and
  design §8.1 case 5 all say `14:00 – 14:30`. Greening it literally meant preserving the span across
  a cancel — touching `CancelPendingRequest` (proposal §3 item 2: unchanged) or the schema (§4: out of
  scope).

### 🟡 Should fix
- **SP-1** S8.4 restated a mechanism ("one filtered list feeds both") and `Classes to Arrange` had no
  fact at all. **SP-2** S12 never proved the comparator runs. **SP-3** S16.3 dropped "the junction is
  read exactly once" and its fixture (1 vs 4 *pending*) could pass with a per-row page.
- **SP-4/5/6** M7's facts strip, the venue-name fallback and a non-vacuous tab-3 content were
  unasserted; S7.5 ("no timeline") was green before the work started.
- **SP-7** `Rejected` was the one M5 label with no badge-level fact. **SP-8** the retired list had
  receipts for 3 of ~10 items. **SP-9** it also **invented** a retirement neither frozen artifact
  decided (the keyboard-shortcuts pop-up, live via `?`, `:839-842`).
- **SP-10** M10's copy-email clause had no surface on this page. **SP-11** §6b law 5 ("the API
  agrees") had no requirement. **SP-12** M8's "the Completed sentence carries the count" and the
  status-keyed map were missing. **SP-13** S14.4 did not distinguish the hidden-row case. **SP-14**
  M16's `mrh-filters` hazard had no reload fact. **SP-15** the rotate→arrange verb was unproven.
- **SP-16** four sentences a non-native reader parses twice (S6.1, S6.3, R13, R16).

### ✅ Confirmed
- Every M lands somewhere and none is re-opened: M1→R1/R2 · M2→R3 · M3→R4 · M4→R5 · M5→R2 · M6→R6 ·
  M7→R7 · M8→R7/R16 · M9→R8 · M10→R9 · M11→R5/R11 · M12→R10+retired · M13→R11 · M14→R12 · M15→R13 ·
  M16→R14 · M17→R15.
- The six labels, the seven column names and order, the five card labels, the seven select values, the
  1500 px number, the three tabs and the two reset laws match the frozen artifacts character for
  character, em dashes included.
- S1.1–S1.4, S5.3, S10.3, S13.1–S13.2 are observable facts; S2.1–S2.4 pin both halves of the
  one-status rule; S3.1–S3.3 and S14.1–S14.3 are demonstrable from outside.

**Verdict: FAIL** — 2 blocking, 16 should-fix, no decision conflict. All folded after this entry.
SP-9 was resolved by **keeping** the keyboard-shortcuts pop-up (design §2.1 gained a "kept, copy
updated" line): neither frozen artifact decided to remove it, and deleting a live feature on a
spec's authority alone is scope creep.

## specs Round 2 — 2026-11-04

<!-- Confirmation pass. Scope: the two round-1 blockers and the sixteen should-fix items only.
     Every fold re-checked against the repo at head 9391dbb, not against round 1's own text. No
     M-decision and no design [D] is re-opened. -->

### 🔴 Fixed
- **SP1 — closed; S3.4 is now checkable *and* complete on the frozen side.** The frozen set was
  enumerated whole by grep: 11 occurrences — the seven `mock-data.js` link fixtures
  (`:1003,1009,1015,1021,1071,1095,1101`), `notif-row.blade.php:96` (`$n->link ===
  '/my-request-history-ui'`, exact string, no query), `NotificationEmitter.php:64,88` (both write
  that bare path, so the `===` still matches) and the mock template `:1916`. Both halves of S3.4 are
  greppable and neither can be greened by editing the other.
- **SP2 — closed, and `14:00 – 14:30` is right for a rejected request.** `RejectRequest.php:132-134`
  deletes the junction and fills only status/approver/decided/reason — the anchor FK survives, and
  `SubmitReplacementRequest.php:50,99-101,146` pins that anchor to the FIRST 30-minute cell. No
  change to `CancelPendingRequest`, `RejectRequest` or the schema is needed.

### 🟡 Addressed (16/16, re-verified against the code)
- **SP-1** S8.3/S8.4 are countable. **SP-2** S12.2 proves the comparator and its direction matches
  `ui-common.js:1594-1599` + the `date/asc` default. **SP-3** S16.3 carries both halves on a
  4-approved-junction fixture. **SP-4/5/6** S7.6 matches design §4.2's `factsHtml`; S7.7's state is
  reachable (`room_name` is `nullable()`); S7.5 is positive containment, not green-before-work.
- **SP-7** S2.5 covers all six labels. **SP-8** S17.4/S17.5 falsifiable against today's copy
  (`:194` "click a status badge", `:202` "opens that request"). **SP-9** the invented retirement is
  gone; the pop-up is kept by design §2.1.
- **SP-10** S9.3 is greppable (no `📋`, no `copyEmail` in the merged view). **SP-11** S16.6's basis
  verified (`ApiReadController:156` → `toRow():131` → `effectiveStatus()`, scoped to the lecturer's
  own requests). **SP-12** S2.2 + R2's prose. **SP-13** S14.4. **SP-14** S15.4. **SP-15** S6.6.
  **SP-16** all four sentences read cleanly.

### ✅ Confirmed
- R16 ↔ S16.1–S16.6 one-to-one; R2 ↔ S2.5 character for character; R7 ↔ S7.5/6/7; R9 ↔ S9.3;
  retired ↔ S17.1–S17.5. The seven columns, five cards, seven select values, 1500 px, three tabs and
  two reset laws still match the frozen artifacts.
- **Citation correction (round 1's log, not the spec):** `📋` lives at
  `request-approval.blade.php:548,725,998` (`copyEmail()` defined `:485`), not `:463-465`. Recorded so
  nobody edits the wrong block later.

### 🟡 Residuals — folded before the freeze
- **R1** R3's producer list was **weaker than proposal §3 item 7b**: it omitted the server-side
  landing/redirect targets (`ReplacementArrangement.php:78,79` `FROM_LANDING_ROUTES`, `:201`'s `??`
  default) and the two toast **labels** (`ui-common.js:4420,4423`). A tasks list built from the spec
  could have left `:201` untouched, turning two A″ PHPUnit assertions red — whose cheapest green is
  reverting `:201`, exactly the failure proposal §6 warns about.
- **R2** S8.4 said "already-arranged", which would exclude **rejected/cancelled** rows' hours if read
  as `requestId !== null`; now "handled (pending, approved, completed)" — the words R6/design use.
- **R3** S16.3's fixture now says "4 approved requests on 4 of the same occurrences", so the row count
  is equal and only the request count differs — the per-row implementation this lane deletes can no
  longer pass it.
- **R4** S16.2 now says "the rejection reason and no Remark row", not "only the rejection reason".
- **R5** S12.1 states the load-time default (Original Class active with ▲) and S12.2 splits the two
  clicks into one fact each.

### 🔴 Outstanding
- None.

**Verdict: PASS** — both blockers closed and correct, all sixteen should-fix items verified, the
spec internally consistent, and no scenario unachievable without touching a frozen law. The five
residuals (all wording/coverage) are folded. **Batch 3 (specs) is FROZEN at this entry.**

## tasks Round 1 — 2026-11-04

<!-- Scope: tasks.md (head 4f0466e) against the frozen proposal (M1–M17, §3, §6, §6b), design and
     specs. No M decision and no design [D] re-opened. Findings are: elements no task lands,
     sequencing that cannot execute, receipts that cannot be produced (or are wrong), and two places
     where the list was NARROWER than a frozen requirement. Every count was measured at that head. -->

### 🔴 Must fix
- **T-1 — the `<tr>`'s two ids were emitted by no task, and four specs read them.** The home view sets
  **no** data attribute today (`grep` → 0) while the page it replaces sets `row.dataset.id`
  (`my-request-history.blade.php:570`); `applyNotifHighlight` selects `tr[data-id]` (`:329-331`). T8
  added only the matcher. Without `data-id` + `data-request-id`, S3.3 and S14.1–S14.4 cannot pass,
  and T19's `tr[data-request-id]` rewrites select zero rows.
- **T-2 — the new canonical URL would have been gated by no test.** `RouteGateMatrixTest` pins only
  the two old URLs (`:34,36,46,48`); `/my-replacements` was in no task, though proposal §3 item 11
  requires it.
- **T-3 — T6's only receipt could not run in commit 1.** Case 10 asserts all three URLs, but
  `/my-replacements` does not exist until T14 (commit 3), so the count read 9/10 at the moment it was
  to be quoted.
- **T-4 — the list was narrower than proposal 7c: the toast BODIES keep naming the retired page.**
  `ui-common.js:4419,4422` read "…view it in My Request History." — the most-read entry-guard message
  still naming a page that no longer exists, which is the failure §7 argues against for
  `ReplacementArrangement.php:1214`.
- **T-5 — T16's grep receipt was red by construction.** `ReplacementRequestMapper.php:17,282` name the
  class in comments, and no task (or file in that commit) owned them.

### 🟡 Should fix
- The cancel flow was half-owned: the `!ok` branch, the `row === null` branch, the
  `disabled`-while-in-flight guard (which does not exist today) and the second toast label.
- The `?? null` in `ui-page-header` was the trap and no receipt touched a `[]` caller.
- Three small surfaces had no owner: `pageKey`, `qvArrange()`'s handled branch (a **fourth**
  `requestHref()` reader, `:768`), and the five card value colours.
- The comparator's explicit status rank / `'9999'` / arrow repaint were compressed into one line; and
  S5.2–S5.4, S9.3, S11.1–S11.2, S12.1–S12.3 had no receipt anywhere.
- `--path=replacements` matches 1 of 3 routes; `npm run test:e2e` does not exist (`package.json:5-10`);
  T1's "byte-identical" was proven for two of six keys; T3's receipt pointed at the wrong task; T18's
  red window was unstated; A′'s fixture precondition was in no task; T7's `mock_fallback` was an env
  flag, not a query param.

### ✅ Confirmed
- **The 11-occurrence arithmetic is exact:** 25 today − 14 edits = 11, file for file.
- Commit boundaries match design §7/§10, and the `class_exists` trap is stated in ordering rule 3.
- T10's CSS scope is complete, token-only, and invents no definition twice.
- T17's fixture work is right: `createRequest()` plus the three inline creates; and
  `SpanReservationTest::spanFixture()` already stamps the exception, so its re-point needs none.
- The gates state the DB rules (testing DB by `DB_DATABASE` only, never `--env=testing`, never
  `migrate:fresh`, never a demo write), "never push", and the staging rule.

**Verdict: FAIL** — 5 blocking, 10 should-fix. All folded after this entry.

## tasks Round 2 — 2026-11-04

<!-- Confirmation pass. Scope: tasks.md (head e057880) — the five round-1 blockers and the ten
     should-fix items. proposal.md / design.md / specs/ are frozen; no M/[D] decision and no spec
     requirement is re-opened. Every fold re-read against the repo at this head. -->

### 🔴 Fixed
- **T-1** the two ids are emitted by T12, and the premise holds: the home view sets **no** data
  attribute today (`:516-583`), the page it replaces does (`my-request-history.blade.php:570`), and
  T8's precedence (`data-request-id` first) is consistent with `applyNotifHighlight:329-331`.
- **T-2** `/my-replacements` joins `ALL_ROUTES` (`:29-39`) and `LECTURER_ONLY` (`:43-51`); the four
  test methods (`:100-141`) make "lecturer + student gates" exactly what the insertion yields.
- **T-3** the deferral is a **sequencing note, not a design change**: design §8.1 case 10 still runs
  inside this lane, in the T14 step, because the third route cannot exist before commit 3.
- **T-4** the toast **bodies** (`:4419`, `:4422`) are named alongside the label/href pair
  (`:4420`, `:4423`), so proposal 7c is met for the most-read entry-guard message.
- **T-5** the two mapper comments (`:17`, `:282`) are in T16's file list; a case-sensitive sweep
  returns exactly those two plus the class, the route entry and two test files — and `resources/`
  has zero hits, so T16's receipt can go green without touching another surface.

### 🟡 Addressed (all ten)
- The cancel flow (the `!ok` and `row === null` branches, the `disabled`-while-in-flight guard,
  "new — it does not exist today", confirmed absent at `my-request-history.blade.php:690-714`); the
  `ui-page-header` trap with the only live `[]` caller as its receipt; `pageKey`, `qvArrange()`'s
  fourth reader (`:768`) and the card colours; the comparator's rank/`'9999'`/arrow detail; the
  S5.2–S5.4 / S9.3 / S11 / S12 receipts; `route:list --json` = 3; `npm test`; T1's six keys; T3's
  pointer to T6 case 3; T18's red window; A′'s fixture precondition; T7's env flag.

### 🔴 Outstanding
- **TR2-1 — T15's second receipt was red by construction, and its cheapest green would edit a frozen
  file.** `grep -rn "My Request History"` returned 7 at the point of quotation (two files belong to
  T16, a later task in the same commit) and 4 permanently: `notif-row.blade.php:53` (a comment),
  `replacement-arrangement.blade.php:2243` (the comment between T15's own two edits),
  `ui-design-templates/my-request-history-UI-design-template.blade.php:4,159` (frozen mock copy).
  Greening it literally means editing the frozen template and a byte-compared partial — the SP1/T-5
  failure mode this lane had already fixed once.

### 🟡 Should fix
- **TR2-2** `9/9`/`10/10` ignored case 3's six-row provider. **TR2-3** "written but
  `markTestSkipped`-free" had a dropped "not". **TR2-4** the bare `{}` probe throws at
  `opts.daysLeft` (`:2483`). **TR2-5** the `--json` count of 3 needs `APP_MOCK_FALLBACK` off.
  **TR2-6** the docblock also has a stale "9 UI routes" line (`:16`). **TR2-7** the mapper's second
  comment carries the pinned-shape rationale — rewrite, don't delete. **TR2-8** T12's scenario list
  read exhaustive but omitted S6.1–S6.5, S9.1–S9.2, S16.x, S17.x.
- 💡 T13 counted seven widths and listed six; T12 quoted only the failure toast; T2's receipt named a
  Playwright spec without naming the run.

### ✅ Confirmed
- The five blocker folds are substantively right and each was re-read at the code; the 11-occurrence
  arithmetic reconciles exactly (7 + 1 + 2 + 1); T11 owns the `?? null` **and** the receipt that
  proves it; T18's red window is stated with the anti-revert warning; commit boundaries still match
  design §7/§10 (including the two commit-2 rows) and the `class_exists` trap is still in ordering
  rule 3; the DB rules and "never push" are intact.

**Verdict: FAIL** — no round-1 blocker survives and every should-fix item is folded; the one
remaining item is a receipt text (TR2-1). All folded after this entry: the residue is stated
explicitly, `:2243` joined T15's line list, the two test counts are quoted as PHPUnit row counts, the
`not` is restored, T7's probe carries a stub `opts`, the route count states its precondition, the
docblock's `:16` is named, T16 rewrites rather than deletes, a scenario→task map closes TR2-8, and
the three 💡 are taken.

## tasks Round 3 — 2026-11-04

<!-- Confirmation pass. Scope: tasks.md (7a9660b + 370147e's T15 toast clause) — TR2-1 and TR2-2…TR2-8.
     Counts re-measured at that head: "My Request History" = 12 (app 2 · resources 6 · public/js 4);
     the old URLs = 25. -->

### 🔴 Fixed
- **TR2-2 … TR2-8 all folded**, re-read at the code: the PHPUnit row-count wording (`design.md:807` =
  six states, 10 cases → 15 rows); the restored `not`; the stub `opts` (the first thing the legacy body
  touches is `opts.daysLeft`, `ui-common.js:2483`; `isHandled`/`actionCell` are guarded at `:2489,:2493`);
  the `APP_MOCK_FALLBACK` precondition (`routes/web.php:89,91-93`); docblock `:16`; the rewrite-not-delete
  of `ReplacementRequestMapper.php:282-284`; and the scenario→task map.
- **The T15 toast clause is correct in every detail.** `show(message, undoCallback, duration, linkText,
  linkUrl)` is `:2643`; `null` keeps A5's inert Undo; both bodies keep the substrings the guard spec pins
  (`:82` used at `:133,:203,:240`; `:145`), so A″'s only guard edit is `:134`'s href.

### 🔴 Outstanding
- **TR3-1 — the spaced-copy receipt was not the residue at the step that quotes it.** It said "exactly
  four" while the command returns **6** at T15's own step (the class docblock and the blade's `:6/:166`
  are T16's, later in the same commit) and **3** after T16 — and it listed
  `replacement-arrangement.blade.php:2243` as a survivor while the same task rewrites it. A PI who ran it
  would see 6 with nothing to reconcile, and the cheapest green is editing `notif-row` or the frozen
  template.

**Verdict: FAIL** — one receipt, nothing else.


## tasks Round 4 — 2026-11-04

<!-- Final gate for Batch 4. Scope: the ONE sentence rounds 1–3 left open — T15's spaced-copy receipt
     (tasks.md head 05f9e02). Every count re-measured with a case-sensitive grep over
     `app resources public/js`, exactly the scope the receipt states. -->

### 🔴 Fixed
- **TR3-1 — closed, and every number is exact.** `My Request History` → **12** (app 2: the class
  docblock, `ReplacementArrangement.php:1214`; resources 6: the frozen template `:4,:159`,
  `notif-row:53`, `my-request-history.blade.php:6,:166`, `replacement-arrangement.blade.php:2243`;
  `public/js` 4: `ui-common.js:4419,4420,4422,4423`). T15's six edits remove exactly those six and add
  none → **6**. T16 deletes the class and the blade → **3**, exactly `notif-row:53` + the frozen
  `:4,:159`. The three-state form (12 → 6 → 3) is the fix: no number is quoted at a step that cannot
  produce it, and `:2243`'s "must not appear" is stated.
- **`:2243` is T15's.** It is the middle line of a real three-line comment (`:2242-2244`) in the submit
  success branch, beside `:2241` (the toast) and `:2245` (the `location.href`); `:2242`/`:2244` carry no
  name.
- **The toast guard is mechanically achievable.** `_present()` writes `msgEl.textContent` (`:2672`) and
  `linkEl.textContent`/`href` (`:2700-2701`) into the single shared nodes of
  `layouts/ui-template.blade.php:71-80`; `dismiss()` only strips `.visible`. The fixture exists exactly
  as quoted (`arrangement-entry-guard.spec.ts:45-46` session 99 / week 8, `:124` pending,
  `:130` landing), and `:133`/`:145` really are substring assertions.
- **URL arithmetic untouched:** 25 − 14 = the 11 frozen, file for file; T15's own count is 13 because
  `requestHref()` is commit 2's.

### 🔴 Outstanding
- **TR4-1 — the capitalised state words turn three assertions in the receipt's own spec red, and no task
  owns them.** `tasks.md` now writes `APPROVED` / `PENDING`; `arrangement-entry-guard.spec.ts:82` pins
  `TOAST_TEXT = 'already has a pending replacement request'` (`:133`, `:240`) and `:145` pins
  `'already has an approved replacement'`. Playwright's string matchers forward `ignoreCase` with no
  default-true, so all three go red the moment T15 lands. Proposal §6's A″ rows for that file are
  `:134,:144,:231` only, and T18's red window named only `ReplacementArrangementTest` — so T23's gate
  would be red with nothing in the plan to green it, and the cheapest green (restoring the old casing)
  silently reverses the change.

### 🟡 Outstanding
- **TR4-2** the receipt did not say whether T15 *observes* the two texts or *adds* the assertions, in a
  spec that is red at that step for three other reasons — the hazard is greening `:134` by reverting
  `ui-common.js:4420`.
- **TR4-3** no `tasks Round 3` entry existed in this log; it is supplied above.
- 💡 `:2243` "carries the page **name**", not the same sentence; and "12 today" also holds at T15's
  step, because commits 1–2 touch no matching line.

**Verdict: FAIL** — the receipt is now numerically airtight and TR3-1 is closed; the one blocker is a
three-word side effect of the caps, not the receipt's structure.

## tasks Round 5 — 2026-11-04

<!-- Final gate for Batch 4. Scope: the TR4-1 fold and the two round-4 should-fix items in tasks.md
     (head 3245961). proposal.md / design.md / specs/ are frozen; no M/[D] decision and no spec
     requirement is re-opened. Every claim re-read at the repo. -->

### 🔴 Fixed
- **TR4-1 — the capped state words have an owner, and every citation checks out.** `:82` is
  `const TOAST_TEXT = 'already has a pending replacement request';`, read at `:133`
  (`toContainText`), `:203` (`not.toContain`) and `:240`; `:145` hardcodes
  `'already has an approved replacement'`. The live bodies (`ui-common.js:4419,4422`) are lowercase
  and Playwright's `toContainText` is case-sensitive, so the caps turn exactly `:133`, `:145`, `:240`
  red. T18 names `:82`/`:133`/`:240` and `:145`, and both new strings are literal substrings of T15's
  bodies.
- **`:203` is the vacuous usage, correctly kept out of T18.** `:197` loads unblocked, so
  `showEntryGuardToast` returns at `:4417` and the shared `.toast-message` node is empty; the
  `not.toContain` passes with either casing.
- **The extra lines are in the right commit and leave the frozen counts alone.** T18 sits in commit 4
  (design §10's "§8.2 + the remaining bucket A″ strings"); proposal §6's A″ table still has exactly
  7 rows, and the two casing lines are labelled "this lane's own copy change", not A″ rows.
- **No contradiction with ordering rule 4 or with the one-commit rule.** Rule 4 forbids reverting
  `ReplacementArrangement.php:201`; T18's sentence forbids a *different* revert for a *different* line
  set — both point the same way. T18 edits expected values only, in commit 4; commit 3 stays atomic.

### 🟡 Addressed
- **TR4-2** the receipt is actionable: the fixture (`:122-135`, session 99 / week 8, run env in the
  docblock `:29-33`), the two nodes read separately (`#toastBar .toast-message`,
  `#toastBar .toast-link` — the only two nodes the toast writes, `layouts/ui-template.blade.php:73,77`,
  `_present():2672,2700-2701`), and the instruction not to touch that file's URLs.
- **TR4-3** the round-3 entry is in this log; the history is R1–R5, append-only.
- **Unaffected, re-measured:** the URL residue at T15's step is exactly **11** (25 − 13 −
  `requestHref()`); the copy trail still starts from 12 in those three directories; T15's five-argument
  `toast.show` matches `:2643`.

### 🟡 Residuals — all folded before the freeze
- **TR5-1** T18's anti-revert sentence named one producer for three rows. It now lists every red line
  and its delta: `:133/:145/:240` ← `ui-common.js:4419-4423` · `:134` ← the link href · `:144` ←
  `ReplacementArrangement.php:201` (rule 4) · `:231` ← `replacement-arrangement.blade.php:2245`.
- **TR5-2** `submission-conflict-refresh.spec.ts:84,111` is red in the same window; T18 now names it.
- 💡 why plain caps: `showRich()` takes no `linkUrl` (`:2651`), so it would drop the management link.
  And `:203` is marked vacuous, so nobody later "fixes" it.

### 🔴 Outstanding
- None.

**Verdict: PASS** — TR4-1 closed with every line verified at the code and the caps preserved as the
user's decision; both windows named, no frozen count moved, and no revert path left without either a
prohibition or a green-keeping receipt. **Batch 4 (tasks) is FROZEN at this entry.**


---

## Apply round — 2026-11-04 (PI session)

**Verdict: APPLIED with two named residuals.** Every task T1–T22 was implemented against the frozen
artifacts; no batch was rewritten, and no M-decision was re-opened.

| Commit | Tasks | The receipt that proves it |
|---|---|---|
| `e70c1ea` | T1–T6 | payload **10 tests / 97 assertions** (twice); `SpanReservationTest` 13/13 (76) incl. the six-key span assertion; `request-completed-status` 3/3 on `:8030` proving the `effectiveStatus()` third parameter is inert |
| `6856577` | T7–T13 | 7 headers · 0 badge anchors · 5 icon cells · pending row 3 tabs · cancel → `Cancelled by YOU` + `14:00 to 14:30` · 1440 px count hidden / 1600 px shown · 390 px cards · arrangement page `0 .page-desc` · hermetic **9 → 7** cells |
| `ab65f9a` | T14–T17 | spaced-copy receipt **exactly 3**; old URLs only the **11 frozen** occurrences; `MyRequestHistory` grep **0**; payload **11/11 (106)** incl. the three-URL case; **47/47** across five files |
| `d3f6f5b` | T18 + part of T19/T20 | `ReplacementArrangementTest` **20/20** — the A″ red window closed by editing the expectation, not the redirect |
| `c4d0ad3` | the rest of T19 | badges receipts **7/7** with a planted approved fixture (10 with setup), `request-completed-status` 3/3, MRH tooltip block re-pointed to the payload tips |
| docs commit | T21–T22 | both changelogs name the canonical URL and the alias contract; handover §0 carries the lane state |

**Gates (T23), measured:** `pint` passed · **PHPStan level 7 → 0 errors** · `node --check` ok ·
full suite **336 passed / 1849 assertions** (baseline 325/1729 — the payload file's 11 tests).
Browser gate: `request-completed-status` 3/3, `replacement-home-status-badges` 7/7,
`notifications-island` untouched.

**Residuals, stated so they are not mistaken for done:**

1. **CORRECTED 2026-11-04.** The sentence above originally claimed the `ui-regression.spec.ts` edits
   were "in the working tree". They were not — the file's entire uncommitted diff was the other
   lane's guard shell, so nothing of this lane's was staged *or* written. The user caught it. Fixed
   in **`3d61cef`**: `#modalOverlay` → `#quickViewModal` for `/my-request-history-ui`, the expected
   label is now per-page (`Class ID` on the merged page, `Subject Code` on the four untouched ones),
   and TC-7.2's three retired card ids became a data-independent law (rows = payload minus hidden
   Completed; cards numeric; the four status cards can't tally more rows than exist). Measured on
   `:8030` with a planted fixture, swept afterwards: **TC-3.x 6/6 + TC-7.2 pass**. The two URL
   assertions in `tests/submission-conflict-refresh.spec.ts` were committed by the user in
   **`956b666`**, split cleanly out of the guard shell.

   **FOLLOW-UP LANE DONE — `e2c2d7a`** (was: "still open"). `playwright.config.ts` sets no default
   `storageState`, so `ui-regression.spec.ts` had been browsing as a **guest**, and its `if (visible)`
   cases took the "nothing there" branch and passed while asserting almost nothing. Now
   `test.use({ storageState: '.auth/staff-5425.json' })` (the student row logs in as 24DFT0001
   itself), and the stale expectations were read off the code: Section 3 asserts that *some*
   `.modal-overlay` opens and names the class (fixed-position overlays report `offsetParent === null`,
   so the obvious visibility check is a trap); Section 2's week-memory rows use the real keys
   (`weekNav-weekSelect` for my-timetable/student — `storageKey` is null there — and
   `cohortTimetableWeek`, not `cohortTimetableState`); TC-2.5 picks an OFFERED week instead of writing a
   key the bookable-week filter overrides; TC-7.1 picks the selects by index and asserts the split-card
   contract. **Measured on `:8030`: 28 passed / 0 failed** (10 failed with the session wired in, 14
   without it). The other lane's guard shell is still uncommitted and the staged diff contains none of
   it.
2. On a **bare freshly-seeded** testing DB, `replacement-home-status-badges` receipt 6 is annotated
   and skipped (no handled row exists); it was proven green with the planted fixture. A skip is not
   a receipt, so this is recorded rather than quoted as a pass.
3. The one user-facing string this lane invented (design fixed the *method*, not the text): the
   no-request tip `No request has been made for this class yet — it still needs arranging.`
