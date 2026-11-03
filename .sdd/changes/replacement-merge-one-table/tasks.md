# Tasks — replacement-merge-one-table

Frozen order. Each task names its **receipt** — the thing that proves it, quoted with the real number
when the run happens. Nothing in this file starts until the three review rounds in `review-log.md`
have passed (they have: proposal R8, design R2, specs R2).

Commit boundaries follow `design.md` §10. The **URL rename is commit 3 and nothing may leak into it**;
the identity edit ships with the view in commit 2 (§7's Commit column).

---

## Commit 1 — `feat(my-replacements): the merged payload, the batch span path, and the six-state vocabulary`

- [ ] **T1 — extract `spanFromAnchor()`** (`app/Services/ReplacementRequestMapper.php`)
      Pull `span()`'s step 2 into `spanFromAnchor(?TimeSlot $anchor): ?array` returning the same six
      keys with the same casts (`week`, `day`, `venue_id`, `start`, `end`, `count: 1`); `span()` ends
      with `return $this->spanFromAnchor($r->replacementTimeSlot);`.
      *Receipt:* `SpanReservationTest` unchanged and green — it proves the extraction is byte-identical
      (`14:00 – 16:00` junction; `14:00 – 14:30` anchor).

- [ ] **T2 — `effectiveStatus()` takes the computed date** (same file)
      Third optional parameter `?string $replacementDate = null`; `$date = $replacementDate ??
      $this->replacementStrings($r, $semesterStart)['date'];`. Every existing caller untouched.
      *Receipt:* `grep -rn "effectiveStatus(" app/` shows `toRow():131` still passing two arguments,
      and the existing `request-completed-status` tests stay green.

- [ ] **T3 — the six-state vocabulary** (same file)
      `statusLabel(?string, ?string)`, `statusShort(?string)`, `statusSentence(?string, ?string)`,
      `statusTip(?string)`. Pure, no queries; `<n>` from `today()`; the `null` row returns
      `Need to Arrange` / the no-request sentence.
      *Receipt:* the T7 data-provider unit cases — six labels, six sentences, one `Completed — 1 day
      ago`, one `today.` guard.

- [ ] **T4 — the payload** (`app/Livewire/ReplacementHome.php`)
      Widen the `latestRequests` select by `remarks` + `rejection_reason` (§1.2, two columns, FK kept);
      add the batch reads (`spansForRequests` + one `venues` whereIn, skipped when empty); resolve
      `$span = $spans[$id] ?? $spanFromAnchor(...)`, `$venue = $venues->get(...) ?? anchorVenue`;
      compute `$strings`; emit §1.5's fifteen keys with the 9-null / 5-filled split and `venueName`;
      move the loop body into `buildRow()`; **emit the rows in display order** (group, date asc, id).
      *Receipt:* `MyReplacementsPayloadTest` cases 1–8, and case 8's statement count is equal for the
      1-request and the 4-approved-junction fixture, with `replacement_request_slots` read once.

- [ ] **T5 — `cancelRequest()` returns the fresh row** (same file)
      `cancelRequest(int $id): array` → `['ok' => bool, 'row' => ?array]`, delegating to
      `CancelPendingRequest` unchanged; `rowFor()` re-reads the occurrence through `buildRow()`.
      *Receipt:* payload test case 9 — `ok`, stored status `cancelled`, D6 release, one audit row, and
      `row['requestStatus'] === 'cancelled'`; an approved request returns `ok=false, row=null`.

- [ ] **T6 — the payload test file** (`tests/Feature/MyReplacementsPayloadTest.php`, NEW)
      The ten scenarios of `design.md` §8.1, including case 5 (`14:00 – 16:00` → after cancel
      `14:00 – 14:30` + `cancelled`), case 7 (unconflicted occurrence → no row) and case 10 (three
      URLs, query string intact).
      *Receipt:* the measured pass count of `--filter=MyReplacementsPayloadTest`, run twice.

## Commit 2 — `ui(my-replacements): one table, seven columns, and the tabbed modal`

- [ ] **T7 — `replacementHomeRow` merged branch** (`public/js/ui-common.js`)
      `if (opts.merged) return [seven cells]` with `typeLabel` declared for both branches; the
      no-`merged` path byte-identical.
      *Receipt:* `node --check` + the frozen `ui-design-templates` home copy still renders nine cells
      (open it with `mock_fallback=true` locally, then revert).

- [ ] **T8 — `applyNotifHighlight` fallback** (same file)
      `tryFind($sel, 14, $fallbackSel)` = `querySelector($sel) || querySelector($fallbackSel)`;
      `data-request-id` first, `data-id` second; same retry, single pulse, strip list.
      *Receipt:* `/request-approval-ui` and `/my-timetable-ui` highlights unchanged; the merged page
      pulses a `r<id>` arrival.

- [ ] **T9 — `DetailModal` facts strip** (same file)
      One optional key: `if (cfg.factsHtml) html += '<div class="detail-facts">' + cfg.factsHtml + '</div>';`
      immediately before the tabs block.
      *Receipt:* every other `DetailModal.render` caller (request-approval, my-timetable) renders
      unchanged — no `factsHtml` passed, no `.detail-facts` node.

- [ ] **T10 — the theme definitions** (`public/css/theme.css`)
      `.btn-icon` base (no `min-height`) + `--primary/--ghost/--danger`; `.badge-reason`;
      `.badge-holiday` moved from the page; `.badge-outline`; `.cell-sub` + `.cell-dash`;
      `.detail-facts/.df-item/.df-item--full/.df-label`. Tokens only, no hex.
      *Receipt:* `grep -n "#[0-9a-fA-F]\{3,6\}"` over the new block returns nothing, and the ≤768 px
      touch target still measures 48 px.

- [ ] **T11 — the view rewrite** (`resources/views/livewire/replacement-home.blade.php`)
      §2.1b/2.1c identity (title, H1, `descriptionHtml`, guide copy) · the seven-column `<thead>` with
      `data-sort`/`data-label`/`data-tip` · `HtmlBuilder.replacementHomeRow(c, {merged:true, …})` · the
      four page-owned cells · `#tableHead/#tableBody/#searchInput/#statusFilter/#hideCompleted/
      #resetFilters/#resultCount/#filterChips/#cardView/#paginationBar/#timetable` · the five summary
      cards with §3.7's ids · `#quickViewModal` with the facts strip, three tabs, `#cancelRequestBtn`
      (pending-only, `style.display`), `#qvArrangeBtn`/`#qvArrangeLabel`, `#cancelConfirmOverlay`
      moved from the retired view · `#keyboardModal` kept with updated copy.
      Delete: `requestHref()` **and its three readers** (`:383`, `:643`, `:649`), the ten badge
      classes, `.badge-link`, `.action-stack`, `#modalOverlay`, the urgency/venue/students columns.
      *Receipt:* `#tableHead th` count is 7; `grep -c "badge-link\|requestHref\|action-stack"` is 0;
      the page loads with no console error.

- [ ] **T12 — the page script** (same file)
      `myConflictedRows`/`visibleBaseRows` · `statusLabelOf/statusTipOf/statusShortOf` ·
      `statusBadge`/`actionCell` (rotate · ban · eye) · the merged comparator
      (group → column → `id`) · filters + chips + `mrh-filters` (with `week`) · Reset vs Clear all ·
      pagination + `initRpp` · cards · the pop-up builder (three tabs, tab 2 omitted when nothing is
      proposed, tab 3 exactly badge + sentence + [reason] + [remark]) · `openCancelConfirm(requestId,
      occurrenceId)` with the body fill · the cancel success path (patch `result.row`,
      `closeCancelConfirm()` + `hideQuickView()`, repaint, toast) · `jumpToRequestRow` (M16) +
      `&view=request` opening the modal from `payload`, not the DOM.
      *Receipt:* spec S6.6, S7.1–S7.7, S10.1–S10.3, S14.1–S14.4, S15.1–S15.4 demonstrated by hand on
      the testing DB.

- [ ] **T13 — page-local CSS** (same file, `@section('page-styles')`)
      Seven column widths (`.col-cohort 130 / .col-original 210 / .col-reason 150 /
      .col-requested 200 / .col-status 190 / .col-action 70`), the raised `min-width`, the urgency
      span **inside** `.col-original`, and the `max-width: 1499px` rule hiding `#resultCount`.
      *Receipt:* 1440 px → count hidden, pagination visible; 1600 px → both visible; the D7 em-dash
      receipt still reads a days-only value.

## Commit 3 — `refactor(my-replacements): the canonical URL, three route entries, and the retired page`

- [ ] **T14 — the route table** (`routes/web.php`)
      `/my-replacements` (nav `replacement-arrangement`), `/replacement-home-ui` (same),
      `/my-request-history-ui` (nav `replacement-history`), all three `component =>
      App\Livewire\ReplacementHome`, `legacy` = the home template, same middleware, **no redirect**.
      *Receipt:* `php artisan route:list --path=replacements` and each URL returns 200 as a lecturer.

- [ ] **T15 — the six live files + the copy** (`design.md` §7's commit-3 rows)
      `ReplacementArrangement.php:78,79` (`FROM_LANDING_ROUTES`), `:201` (the `??` default), `:1214`
      (the "see My Replacements" copy); `replacement-arrangement.blade.php:2241,2245`;
      `ui-common.js:3071,3072,3079` (values + default, **keys untouched**), `:4420,4423` (links **and**
      labels), `:1292` (`&view=request`); `ui-nav-bar.blade.php:18,19` → one item.
      *Receipt:* `grep -rn "/replacement-home-ui\|/my-request-history-ui" app resources public/js`
      returns **only** the 11 frozen occurrences (`mock-data.js` ×7, `notif-row:96`,
      `NotificationEmitter:64,88`, the mock template `:1916`) — quoted count.

- [ ] **T16 — retire the page** (`app/Livewire/MyRequestHistory.php`, its blade)
      Delete both. Nothing in `app/`/`routes/`/`resources/` may still name the class.
      *Receipt:* `grep -rn "MyRequestHistory" app routes resources` returns nothing.

- [ ] **T17 — re-point the tests that name it** (`ReplacementHistoriesTest.php`,
      `SpanReservationTest.php`)
      §2.5's rows: the component and payload key, the occurrence-row shape, the cancel return array,
      the completed/boundary pair; delete the batch-cancel test (M12). `createRequest()` **and** the
      three inline `ReplacementRequest::create()` calls (`:352,:381,:387`) gain the `ClassException`
      stamp.
      *Receipt:* the two files green, with the measured counts.

## Commit 4 — `test(my-replacements): the specs the merge reverses`

- [ ] **T18 — bucket A″ strings** (7 rows, `proposal.md` §6)
      `ReplacementArrangementTest:643,723` · `arrangement-entry-guard:134,144,231` ·
      `submission-conflict-refresh:84,111`.
      *Receipt:* `arrangement-entry-guard` + `submission-conflict-refresh` run on the testing DB and
      pass (not skip).

- [ ] **T19 — buckets B and D** (`design.md` §8.2)
      `replacement-home-status-badges` (receipts 1–7 incl. `.btn-icon` for `hasArrange` and the
      days-only selector for the em dash) · `request-completed-status` · `status-tooltips-filter-chips`
      · `request-cancel-pending` · `warning-keywords` · `ui-regression:151,166,322-334` (per-entry
      tuple for `:166`; the five new ids for `:322-334`).
      *Receipt:* each file's measured pass count on the testing DB.

- [ ] **T20 — bucket C leftovers** (`e2e/history.spec.js`, `e2e/smoke-my-request-history.spec.js`,
      `auth-wiring:294`, `full-suite:453-472,645,688-700,692`)
      §2.5's rows, with the `length > 0` guard and the fixture-grounded row minimum.
      *Receipt:* measured pass counts; the title assertion reads `/My Replacements/`.

## Commit 5 — `docs(my-replacements): changelogs + handover`

- [ ] **T21 — changelogs** `page-changelogs/replacement-home-changelog.md` and
      `page-changelogs/my-request-history-changelog.md` (the page retired, where its behaviour went,
      the canonical URL, the aliases), plus the nav.
      *Receipt:* both files name the canonical URL and the alias contract.

- [ ] **T22 — handover** `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` §0/§1/§4/§5.
      *Receipt:* §0 carries the lane's state and the frozen-batch table.

---

## Gates (run before the review round, quote the numbers)

- [ ] **T23 — gates**
      `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` ·
      `node --check public/js/ui-common.js` · `php artisan test` (full suite on
      `DB_DATABASE=class_replacement_testing`, expect ≥ the current baseline — quote the real number,
      never "all green") · `npm run test:e2e` on the testing DB for the merged page and the URL moves.
      **Never** `migrate:fresh`, never `--env=testing`, never write a demo DB.

- [ ] **T24 — review round after code** — verify the implementation against the frozen artifacts,
      record honestly in `review-log.md`, and leave `sdd.yaml` at `applied` or `verify-failed`.

---

## Ordering rules (do not reorder)

1. T1–T6 land before the view: the view reads the payload keys and the vocabulary.
2. T7–T13 land before the routes: the page must render on the old URLs first, so a red run is
   attributable.
3. T14–T17 are **one commit**: a deleted class with a live route entry silently renders the frozen
   legacy template (`routes/web.php:88-93`), and a test naming a deleted class is a red first pass.
4. T18–T20 may follow, but the A″ strings in T18 must not be "fixed" by reverting `:201` — that is the
   failure proposal §6 names.
5. Nothing is pushed. Nothing is staged outside this lane's files.
