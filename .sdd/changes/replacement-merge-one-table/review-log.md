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

