# Apply log — `filters-button`

**2026-11-15.** All four batches were frozen (proposal r4, design r3, specs r2, tasks r2). Applied T1–T23
in eight subagent runs, each verified before its task boxes were ticked. Committed as this lane's single commit (this lane only; the other sessions' work stays uncommitted — see §3.6). The hash is deliberately not quoted: amending the commit would invalidate it.

Server for every run: `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030` (testing DB).
The demo DB was never migrated, seeded or written; one read-only UI login on :8000 was used to *measure*
Request Approval (its testing-DB default view has no rows) — no business table was touched.

---

## 1. What landed

| file | change |
|---|---|
| `resources/views/partials/ui-filter-panel.blade.php` | **new** — the row (button · chips · count) and the panel frame (head: title · Reset slot · ×; `#filtersPanelBody` with `wire:ignore`) |
| `public/css/theme.css` | one new section after the chips block: row, button, overlay/panel, fields/labels, scoped toolbar neutralising, phone sheet + chip sizing, desktop card, print (+`.filters-panel{position:fixed}` at ≥769 — see §3.1) |
| `public/js/ui-common.js` | `FiltersPanel` (init/move/open/close/place/lock/Esc/trap/print), `updateWeekArrowsIn`, id-aware `prevWeekFilter`/`nextWeekFilter`/`updateWeekArrowState`, one-line hooks in `openNotifPanel()` + `SummarySheet.open()` |
| `livewire/replacement-home.blade.php` | toolbar hosted + moved; M15 deleted; magnifier dropped; labels; second week field; partial include; reset listener deleted; requested-week predicate + chip + saved view; `FiltersPanel.init()` |
| `livewire/request-approval.blade.php` | same shape with the **Original** week added above the requested one; include after the sort hint; reset listener deleted; original-week predicate + chip; **save/restore order fixed** (§3.2) |
| `livewire/replacement-history.blade.php` | toolbar hosted + moved; switch labelled; requested week added; partial include (no Reset, no count); requested-week predicate via `r.req.week`, chip + delegated branch, saved view, reveal path |
| `tests/helpers/filters-panel-helper.ts` | **new** — `openFiltersPanel` / `closeFiltersPanel` / `withFilters` |
| `tests/filters-button.spec.ts` | **new** — 20 receipts (T18/T19), expectations derived from `window.MockData` + the page's own predicate |
| four specs | `status-tooltips-filter-chips` (11), `week-filter-preserved` (6), `request-completed-status` (6), `summary-cards-button` (12) rewired through the helper + their pinned strings moved |
| `tests/e2e/full-suite.spec.js` | its two search tests now open the panel (the file is collected by `testDir`) |
| `tests/Feature/StudentReplacementHistoryParityTest.php` | `:143` allLabel, `:146` inverted to a positive assertion, `:149` split into three clause pins, docblock; `:144`/`:156` left green |
| three page changelogs | one dated entry each |

---

## 2. Evidence (all on `:8030`, testing DB)

| run | result |
|---|---|
| `tests/filters-button.spec.ts` (×5, incl. 3 consecutive) | **21 passed, 2 skipped** every time — the file holds **20** tests (`playwright test --list`: 23 in 2 files = 20 here + 3 `auth.setup.ts` logins), so 18 pass + 2 skips + the 3 setup logins = 21 passed |
| `status-tooltips-filter-chips` | 11 passed |
| `week-filter-preserved` | 6 passed |
| `request-completed-status` | 6 passed |
| `summary-cards-button` | 12 passed, **1 failed (fixture)** — §4.1 |
| `pages-parity` | 6 passed |
| `nav-identity` | 6 passed |
| `replacement-home-status-badges` | 12 passed, 1 skipped (its own long-standing skip) |
| `StudentReplacementHistoryParityTest` | 8 passed, 1 failed — **pre-existing**, §4.2 |
| measured, 390×844 default view | first card at **214 / 226 / 247px** (was 519 / 541 / 375); chips row **72px** (was 120–126); at 1440 the row is 34px |

The two skips in the new spec are **derived** (they re-enable themselves when the data exists):
1. no conflicted class in the testing DB carries a `replacementDate`, so "the requested week keeps the
   arranged row" has no arranged row to keep;
2. every occurrence of student `24DFT0001` has `r.req = null`, so the student requested-week positive case
   has nothing to keep.

---

## 3. Where the implementation departed from the frozen artifacts (and why)

**3.1 `.filters-panel` needed `position: fixed` at ≥769.** Design §3 says the desktop card is fixed, but
the §5 CSS snippet omitted the property, so `_place()`'s inline `left/top` were ignored and the card
rendered at the overlay's origin (measured top−bottom = −179px at 769). One property added inside the
≥769 block; design §5 is the document that was wrong, not the code.

**3.2 Request Approval's saved view was never restored — a pre-existing bug, fixed here.** The boot order
is `initRpp({onChange: saveFilters})` **before** `SkeletonLoader.with(… restoreFilters() …)`, so the
rows-per-page init saved the just-populated defaults over the stored view before anything read it (it hit
the original week filter too, so it predates this lane). Fixed with a `filtersRestored` gate: saves are
ignored until the restore has run. The receipt that found it (`6. Request Approval restores both weeks
after a reload`) now passes.

**3.3 A third arrival path the design missed.** `jumpToMyRow()` on My Replacements computes
`otherFiltersActive` from status/search/original-week only, so a row hidden *solely* by the new requested
week would have been blamed on the completed switch, which it would then needlessly untick. The new week
is now part of that predicate (design §7.1 named `jumpToNewRequest` and `revealNotifRow`, not this one).

**3.4 T20's parity pin.** The task asked for one composed predicate string; the page wraps it across three
lines, so the test pins the three clauses separately (a whole-line pin would be brittle). Same intent,
recorded here as a deviation.

**3.5 The chips-row number.** See design §9 / spec R5 — corrected to the measured 72px. No layout was bent
to reach the guessed 44px.

**3.6 A third session's edit was swept in and then removed from the commit (verify round 1 caught it).**
Request Approval's `#statusFilter` sits inside the toolbar this lane rewrote, so the *other* session's
uncommitted removal of the `Cancelled` option rode along in the first commit — out of scope on two counts
(proposal §3 "none is dropped", spec R3 "keeps its options"). The commit now keeps the option as HEAD had
it; the removal is back to being their uncommitted change. Lesson recorded: a hunk that overlaps another
session's edit has to be reconciled line by line, not staged as a block.

**3.7 `#resultCount` and `#filterChips` moved into the partial.** The pages no longer emit them; exactly one
of each exists per staff page, and none on the student page (asserted).

---

## 4. Red tests that are not this lane

**4.1 `summary-cards-button` T11a** — "the filter really repaints the cards" expects `#summaryNeeds` to
change when Status = `Need to Arrange`. On the testing DB all five of staff 5770's conflicted classes have
`requestStatus: null`, so that filter keeps all five and the number cannot move (the spec's own comment
says "4 → 2 on the demo data"). The chip paints and the table lists five — the wiring is correct; the
fixture cannot demonstrate the change. Needs a fixture with mixed statuses, not a code change.

**4.2 `my-replacements-after-submit.spec.ts` — 1 red** (`#infoTotal` expects `4 of 4 slots`, reads `0 of 4`):
its pinned cell is fenced in the testing DB (`cell-reserved`) and the spec's own header says the DB must be
re-seeded between runs. Not caused by this lane: it reads `#infoTotal` (a page-level number, not a moved control), and the cell its
precondition needs is fenced in the testing DB.

**4.3 `StudentReplacementHistoryParityTest::test_the_route_and_label_are_the_ruled_ones`** — pins
`'title' => 'Class Replacements'`; the working tree (another session's uncommitted header work) now says
`Student Class Replacements`, and the page changelog records that rename. Outside this lane's scope.

**4.4 One stale changelog figure was corrected:** the three page changelogs first carried "22 passed, 1
skipped" (a number from before the fixture-robust rewrite); they now say 21/2, matching the receipts.

Also observed and left alone: `request-approval.spec.ts` (6 red) needs a pending request, and the testing
DB currently has none (statuses: approved 1, cancelled 1, rejected 2); `ui-regression`'s arrangement
receipts and TC-3.x/TC-7.2 are the known parked reds (stale `Subject Code` pin, card-sum fixture).

---

## 5. Deliberate non-edits

`partials/ui-week-nav.blade.php` (byte-identical), `ui-design-templates/*`, the summary cards, the page
headers, the guide dialogs, `docs/demo-db-data-changes.md`, and the other sessions' dirty files.
