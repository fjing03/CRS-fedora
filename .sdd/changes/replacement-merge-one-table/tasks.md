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
      *Receipt:* `SpanReservationTest` unchanged and green proves the **time** half (`14:00 – 16:00`
      junction; `14:00 – 14:30` anchor). It reads only those two strings, so add one assertion on the
      other four keys (`week`, `day`, `venue_id`, `count`) — otherwise "byte-identical" is a claim about
      two of six.

- [ ] **T2 — `effectiveStatus()` takes the computed date** (same file)
      Third optional parameter `?string $replacementDate = null`; `$date = $replacementDate ??
      $this->replacementStrings($r, $semesterStart)['date'];`. Every existing caller untouched.
      *Receipt:* `grep -rn "effectiveStatus(" app/` shows `toRow():131` still passing two arguments;
      the `request-completed-status` **Playwright** spec green **on the testing DB** (a skip is not a
      receipt — T23's own rule), quoted with its pass count.

- [ ] **T3 — the six-state vocabulary** (same file)
      `statusLabel(?string, ?string)`, `statusShort(?string)`, `statusSentence(?string, ?string)`,
      `statusTip(?string)`. Pure, no queries; `<n>` from `today()`; the `null` row returns
      `Need to Arrange` / the no-request sentence.
      *Receipt:* T6 **case 3** (the six-state data provider), one `Completed — 1 day ago`, one `today.`
      guard. T6 is written later in this same commit — the count is quoted once T6 exists.

- [ ] **T4 — the payload** (`app/Livewire/ReplacementHome.php`)
      Widen the `latestRequests` select by `remarks` + `rejection_reason` (§1.2, two columns, FK kept);
      add the batch reads (`spansForRequests` + one `venues` whereIn, skipped when empty); resolve
      `$span = $spans[$id] ?? $spanFromAnchor(...)`, `$venue = $venues->get(...) ?? anchorVenue`;
      compute `$strings`; emit §1.5's fifteen keys with the 9-null / 5-filled split and `venueName`;
      move the loop body into `buildRow()`; **emit the rows in display order** (group, date asc, id);
      `pageKey` `replacementHome` → `myReplacements` in the `extends()` call (`:170`); one comment line
      saying `remarks`/`rejection_reason` are display-only (§1.2).
      *Receipt:* `MyReplacementsPayloadTest` cases 1–8, and case 8's statement count is equal for the
      1-request and the 4-approved-junction fixture, with `replacement_request_slots` read once.

- [ ] **T5 — `cancelRequest()` returns the fresh row** (same file)
      `cancelRequest(int $id): array` → `['ok' => bool, 'row' => ?array]`, delegating to
      `CancelPendingRequest` unchanged; `rowFor()` re-reads the occurrence through `buildRow()`.
      *Receipt:* payload test case 9 — `ok`, stored status `cancelled`, D6 release, one audit row, and
      `row['requestStatus'] === 'cancelled'`; an approved request returns `ok=false, row=null`.

- [ ] **T6 — the payload test file** (`tests/Feature/MyReplacementsPayloadTest.php`, NEW)
      `design.md` §8.1 cases **1–9** in this commit, including case 5 (`14:00 – 16:00` → after cancel
      `14:00 – 14:30` + `cancelled`) and case 7 (unconflicted occurrence → no row).
      **Case 10 (three URLs, query string intact) cannot run here** — `/my-replacements` does not exist
      and `/my-request-history-ui` still resolves the retired class until T14. It is **not** written in
      this commit and no skipped placeholder is added; it is written in the T14 step, when the third
      route exists.
      *Receipt:* `--filter=MyReplacementsPayloadTest` → **every case green**, run twice — quote the row
      count PHPUnit prints (case 3 supplies six of them); do not quote a flat "9/9".
      **Note for T4/T5:** their receipts cite this file, which this task creates later in the same
      commit — a red count mid-commit is expected, not a broken payload.

## Commit 2 — `ui(my-replacements): one table, seven columns, and the tabbed modal`

- [ ] **T7 — `replacementHomeRow` merged branch** (`public/js/ui-common.js`)
      `if (opts.merged) return [seven cells]` with `typeLabel` declared for both branches; the
      no-`merged` path byte-identical.
      *Receipt:* `node --check`, plus a hermetic check — with a **stub opts**
      (`{index:1, daysLeft:()=>0, urgencyClass:()=>'', badgeClass:()=>'', formatClassBlock:()=>''}`, which
      today's body needs at `:2483`), `replacementHomeRow(c, stub)` returns **9 cells** and
      `replacementHomeRow(c, {...stub, merged:true, reasonCell:()=>'', requestCell:()=>'',
      statusCell:()=>'', actionCell:()=>''})` returns **7**. The bare `{}` throws — it is not a valid
      probe. The legacy template is the env flag `APP_MOCK_FALLBACK` (`config/app.php:29`, needs `.env`
      + `config:clear`), which flips **every** route to legacy: verify it reverted, never stage `.env`.

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
      §2.1b/2.1c identity (`@section('title')`, the H1, the header include with `descriptionHtml`, the
      guide copy) · **`partials/ui-page-header.blade.php:1-5` gains
      `$descriptionHtml = $descriptionHtml ?? null;`**, and `:21-23` renders it `{!! !!}` · the
      seven-column `<thead>` with
      `data-sort`/`data-label`/`data-tip` · `HtmlBuilder.replacementHomeRow(c, {merged:true, …})` · the
      four page-owned cells · `#tableHead/#tableBody/#searchInput/#statusFilter/#hideCompleted/
      #resetFilters/#resultCount/#filterChips/#cardView/#paginationBar/#timetable` · the five summary
      cards with §3.7's ids · `#quickViewModal` with the facts strip, three tabs, `#cancelRequestBtn`
      (pending-only, `style.display`), `#qvArrangeBtn`/`#qvArrangeLabel`, `#cancelConfirmOverlay`
      moved from the retired view · `#keyboardModal` kept with updated copy.
      Delete: `requestHref()` **and its four readers** (`:383`, `:643`, `:649`, and `qvArrange()`'s
      handled branch `:763-772` — its `:768` call would be a `ReferenceError` at tap time), the ten
      badge classes, `.badge-link`, `.action-stack`, `#modalOverlay`, the urgency/venue/students
      columns.
      *Receipt:* `#tableHead th` count is 7; `grep -c "badge-link\|requestHref\|action-stack"` is 0;
      the page loads with no console error; **and `/replacement-arrangement` still renders** with its
      header printing no description — the only receipt that catches a missing `?? null`, because that
      page and the frozen template call the partial with `[]`.

- [ ] **T12 — the page script** (same file)
      `myConflictedRows`/`visibleBaseRows` · `statusLabelOf/statusTipOf/statusShortOf` ·
      `statusBadge`/`actionCell` (rotate · ban · eye) · **every `<tr>` carries both ids**
      (`tr.dataset.id = c.id`, `tr.dataset.requestId = c.requestId || ''`) · the merged comparator with
      design §3.4's **explicit status rank** (`null 0 · rejected 1 · cancelled 2 · pending 3 ·
      approved 4 · completed 5`), `replacementDate` null → `'9999'`, tie-break `id`, and the
      `data-label` arrow repaint · filters + chips + `mrh-filters` (with `week`) · Reset vs Clear all ·
      pagination + `initRpp` · cards · the pop-up builder (three tabs, tab 2 omitted when nothing is
      proposed, tab 3 exactly badge + sentence + [reason] + [remark]) · `openCancelConfirm(requestId,
      occurrenceId)` with the body fill (re-read from the row set at open time) · the cancel success
      path (patch `result.row`, `closeCancelConfirm()` + `hideQuickView()`, repaint, toast
      `Request #<id> cancelled.`) · **the
      `!ok` branch** (close both, `This request can no longer be cancelled.`) · **the `row === null`
      branch** (patch nothing, close both, toast, no throw) · **the guards**: `pendingCancelId = null`
      **before** the call, plus the button `disabled` while in flight (new — it does not exist today) ·
      `jumpToRequestRow` (M16) + `&view=request` opening the modal from `payload`, not the DOM.
      *Receipt:* spec S5.2–S5.4, S6.6, S7.1–S7.7, S9.3, S10.1–S10.3, S11.1–S11.2, S12.1–S12.3,
      S14.1–S14.4, S15.1–S15.4 demonstrated on the testing DB; plus a **double-click** on
      `#confirmCancelAction` issuing exactly **one** `cancelRequest` call and exactly **one** audit
      row (the guard, which no spec fact covers).

- [ ] **T13 — page-local CSS** (same file, `@section('page-styles')`)
      Six fixed column widths plus a flexible code column (`.col-cohort 130 / .col-original 210 /
      .col-reason 150 /
      .col-requested 200 / .col-status 190 / .col-action 70`), the raised `min-width`, the urgency
      span **inside** `.col-original`, the five card value colours
      (`.card-conflict/-duration/-pending/-approved/-rejected`, page-local, tokens only), and the
      `max-width: 1499px` rule hiding `#resultCount`.
      *Receipt:* 1440 px → count hidden, pagination visible; 1600 px → both visible; the D7 em-dash
      receipt still reads a days-only value.

## Commit 3 — `refactor(my-replacements): the canonical URL, three route entries, and the retired page`

- [ ] **T14 — the route table** (`routes/web.php`)
      `/my-replacements` (nav `replacement-arrangement`), `/replacement-home-ui` (same),
      `/my-request-history-ui` (nav `replacement-history`), all three `component =>
      App\Livewire\ReplacementHome`, `legacy` = the home template, same middleware, **no redirect**.
      `RouteGateMatrixTest.php` gains **`/my-replacements`** (lecturer + student gates, like its
      siblings `:34,36,46,48`) and its docblock — **`:21-22` and `:16`** (line 16 says "over the 9 UI
      routes × 4 actors"; it becomes 10) — proposal §3 item 11. **The deferred payload case 10 lands
      here too** (three URLs, identical payload, query string intact): it is the only place all three
      routes exist.
      *Receipt:* with `APP_MOCK_FALLBACK` **off** (`routes/web.php:91-93` turns every action into a
      `Closure` when it is on, and the count would be 0), `php artisan route:list --json` → **3** routes
      whose action is `App\Livewire\ReplacementHome` (`--path=replacements` matches only the
      canonical, so it cannot be the receipt); each of the three URLs returns 200 as a lecturer;
      `RouteGateMatrixTest` pass count quoted; `MyReplacementsPayloadTest` fully green — **quote the
      row count PHPUnit prints** (case 3 is a six-row data provider, so the suite is ~15 rows, not 10).

- [ ] **T15 — the six live files + the copy** (`design.md` §7's commit-3 rows)
      `ReplacementArrangement.php:78,79` (`FROM_LANDING_ROUTES`), `:201` (the `??` default), `:1214`
      (the "see My Replacements" copy); `replacement-arrangement.blade.php:2241,2243,2245` (the comment
      at `:2243` carries the same sentence);
      `ui-common.js:3071,3072,3079` (values + default, **keys untouched**), `:4419,4422` (the toast
      **bodies**), `:4420,4423` (links **and** labels), `:1292` (`&view=request`);
      `ui-nav-bar.blade.php:18,19` → one item.
      **The toast's duplicated page name goes, and the body gets shorter (user decision, 2026-11-04).**
      Today the body ends with the page name **and** the link is labelled with it, so the message reads
      `… in My Request History. My Request History` (`toast._present()` writes body and link to two
      separate nodes: `msgEl` / `linkEl`). The body keeps the fact only — no page name, no instruction —
      and the link carries the name once; the two state words are capitalised (the app's emphasis habit,
      and `toast.show` writes `textContent`, so plain caps rather than a keyword span):
      ```js
      // :4419 (approved)
      toast.show('This class already has an APPROVED replacement.', null, 8000,
          'My Replacements', '/my-replacements');
      // :4422 (pending)
      toast.show('This class already has a PENDING replacement request.', null, 8000,
          'My Replacements', '/my-replacements');
      ```
      *(The old body's "track or withdraw it" hint goes with the tail — the link's destination says the
      same thing, and the user asked for the shorter body.)*
      *Receipt (URLs):* `grep -rn "/replacement-home-ui\|/my-request-history-ui" app resources public/js`
      returns **only** the 11 frozen occurrences (`mock-data.js` ×7, `notif-row:96`,
      `NotificationEmitter:64,88`, the mock template `:1916`) — quoted count.
      *Receipt (the toast):* the rendered guard toast contains the page name **once** — the body has no
      page name, the link label carries it.
      *Receipt (the spaced copy):* `grep -rn "My Request History" app resources public/js` is **12**
      today. This task's own edits remove **six** (the two bodies `:4419,:4422`, the two labels
      `:4420,:4423`, `ReplacementArrangement.php:1214`, and the comment at `:2243`), so **at this step
      it returns 6** — `MyRequestHistory.php:15` and `my-request-history.blade.php:6,:166` are
      **T16's** to delete, later in the same commit. **Quote it again after T16: it returns exactly 3**
      — `notif-row.blade.php:53` (a comment) and the frozen
      `ui-design-templates/my-request-history-UI-design-template.blade.php:4,159`. `:2243` must **not**
      appear (this task rewrites it). Never green the 3 by editing `notif-row` or the frozen template:
      one is compared byte for byte and the other is the `mock_fallback` reference (spec R3).
      *Receipt (the toast says the name once):* in the guard spec's layer-A pending fixture (session 99,
      week 8), after the blocked redirect read the two nodes **separately** —
      `#toastBar .toast-message` = `This class already has a PENDING replacement request.` and
      `#toastBar .toast-link` = `My Replacements`. The existing guard specs assert only a substring
      (`arrangement-entry-guard.spec.ts:133,145`), so a page name restored into the body would stay
      green; this receipt is the only guard on the "once" rule.

- [ ] **T16 — retire the page** (`app/Livewire/MyRequestHistory.php`, its blade)
      Delete both, **and rewrite the two comments that still name it** —
      `app/Services/ReplacementRequestMapper.php:17` (`+ MyRequestHistory::requestRow)`) and `:282`
      (`// the legacy key set only — the existing consumers (MyRequestHistory :65-76, ApiReadController)`
      `are pinned.`). Strip the class name and the now-false "existing consumers" clause, **keep the
      pinned-shape rationale** (three keys vs six) — it is the only statement of why the two helpers
      differ. Both lines are inside this commit's file list; leave them and the receipt is red by
      construction. (No conflict with §6b law 5: that governs the derivation in `toRow()`, which this
      task does not touch.)
      *Receipt:* `grep -rn "MyRequestHistory" app routes resources` returns **nothing** (after the two
      comments), and `grep -rn "Livewire\\MyRequestHistory\|MyRequestHistory::class" app routes` is 0.

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
      **Known red window:** `ReplacementArrangementTest:643,723` runs on **every** PHPUnit pass (no
      testing-DB gate), so it is red from T15 until this task — the split design §10 makes. Do not
      green it by reverting `ReplacementArrangement.php:201` (ordering rule 4).
      *Receipt:* `ReplacementArrangementTest` pass count quoted; `arrangement-entry-guard` +
      `submission-conflict-refresh` run on the testing DB and pass (not skip).

- [ ] **T19 — buckets B and D** (`design.md` §8.2)
      `replacement-home-status-badges` (receipts 1–7 incl. `.btn-icon` for `hasArrange` and the
      days-only selector for the em dash) · `request-completed-status` · `status-tooltips-filter-chips`
      · `request-cancel-pending` · `warning-keywords` · `ui-regression:151,166,322-334` (per-entry
      tuple for `:166`; the five new ids for `:322-334`).
      **A′ is kept, not rewritten:** `replacement-home-qv.spec.ts` needs at least one **needs-action**
      row first in payload order, or its `#qvArrangeBtn` click (`:38`) hits a hidden button and times
      out — the fixture precondition design §3.4 records.
      *Receipt:* each file's measured pass count on the testing DB, A′ included.

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
      never "all green") · `npm test` (`npx playwright test` — there is **no** `test:e2e` script,
      `package.json:5-10`) with the testing DB env, for the merged page and the URL moves; a "skipped"
      run is not a receipt.
      **Never** `migrate:fresh`, never `--env=testing`, never write a demo DB.

- [ ] **T24 — review round after code** — verify the implementation against the frozen artifacts,
      record honestly in `review-log.md`, and leave `sdd.yaml` at `applied` or `verify-failed`.

---

## Scenario → task map (the ones a task's own receipt does not name)

| Spec scenarios | Proved by |
|---|---|
| S1.1–S1.4 | T6 cases 1, 2, 7 |
| S2.1–S2.5 | T3 + T6 case 3 (the six-row provider), case 4 |
| S3.1–S3.4 | T6 case 10 (T14 step) + T15's two greps |
| S4.1–S4.4 | T11 (title, H1) + T15 (nav) + T14 (the arranging page highlights it) |
| S5.1–S5.4 | T11 (`th` count 7, no checkbox) + T12 (cohort lines, non-sortable header) |
| S6.1–S6.5 | T19 (`replacement-home-status-badges` rewrite) + T12 (action cell) |
| S6.6 | T12 |
| S7.1–S7.7 | T12 |
| S8.1–S8.4 | T11 (ids) + T12 (card values) |
| S9.1–S9.2 | T19 (card tap opens the modal) + T12 (one confirm overlay) |
| S9.3 | T12's S9.3 receipt (grep: no second implementation) |
| S10.1–S10.4 | T12 + T6 case 9 (D6 release, one audit row) |
| S11.1–S11.2, S12.1–S12.3 | T12 |
| S13.1–S13.2 | T13 (1440 / 1600) |
| S14.1–S14.4 | T12 |
| S15.1–S15.4 | T12 |
| S16.1–S16.3 | T6 cases 5, 6, 8 |
| S16.4 | T5 + T6 case 9 |
| S16.5–S16.6 | T6 case 2 / case 10 + the API assertion in T6 |
| S17.1–S17.3 | T16 + T20 (the alias spec, the retired nav) |
| S17.4–S17.5 | T11 (the shell and copy greps) |

## Ordering rules (do not reorder)

1. T1–T6 land before the view: the view reads the payload keys and the vocabulary.
2. T7–T13 land before the routes: the page must render on the old URLs first, so a red run is
   attributable.
3. T14–T17 are **one commit**: a deleted class with a live route entry silently renders the frozen
   legacy template (`routes/web.php:88-93`), and a test naming a deleted class is a red first pass.
4. T18–T20 may follow, but the A″ strings in T18 must not be "fixed" by reverting `:201` — that is the
   failure proposal §6 names.
5. Nothing is pushed. Nothing is staged outside this lane's files.
