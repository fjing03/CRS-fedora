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

