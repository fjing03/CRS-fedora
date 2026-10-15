# Design: venue-page-book-affordance

**Frozen proposal:** `.sdd/changes/venue-page-book-affordance/proposal.md` (4 rounds, frozen 2026-10-13).
**Layer:** 100% client-side on `resources/views/livewire/venue-timetable.blade.php` + Playwright test files. Zero PHP/backend changes.
**Source of truth:** `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php` — structure byte-faithful; wording per the user-decision copy table in the proposal.

## D0 — Port inventory (frozen → live mapping)

| Frozen element | Template lines | Lands in live blade as |
|---|---|---|
| `.available-tooltip` CSS | :178–212 | page inline `<style>` (see D2-CSS) |
| `.cell-available`/`.cell-no-fit` hover labels | :315–317 | page inline `<style>` (reworded, see D3) |
| Tooltip markup (`#availableTooltip`, `#tooltipText`, `#tooltipBookBtn`) | :461–463 | blade HTML after the summary bar, before `#classModal` |
| Free-cell wiring (role/aria/tabIndex/dataset/click/focus) | :873–884 | `cellRender` free branch |
| Too-soon read-only branch | :861–866 | `cellRender` new branch before available |
| `showAvailableTooltip` / `hideAvailableTooltip` | :1046–1079 | page `<script>` (adapted, see D2) |
| Scroll-hide listeners | :1082–1083 | page `<script>` (window + `#gridScroll`) |
| `bookVenue` | :1085–1099 | page `<script>` (adapted: no `code`/`cohort`) |
| `BOOK_SPAN = 4` | :1044 | page `<script>` const (== consumer default `BLOCK_SPAN`, arrangement `:1190`) |
| `B`/Escape keydown + `focusedCell` | :1101–1117 | page `<script>` (coexists with existing `closeOnEsc(closeModal)`) |
| No-fit click toast | :1047–1050 | reused as-is — global `toast` from `ui-common.js:2603` (`#toastBar` in layout `:68`) |

Shared infra already present (no changes): `theme.css` `.cell-available` :2077 (parameterized `--hover-label`), `.cell-too-soon` :2197 (own label *"Min. 3 working days ahead"* :2199), `ui-common.js` `isSlotTooSoon` :130, `hours` :586, `generateWeekData` :53, `buildTimetableGrid` (page provides `cellRender`), `toast` :2603.

## D1 — cellRender free-branch rewrite

Branch order in `cellRender` (venue blade :196–236) becomes:

1. PH/Sunday branch — **unchanged** (incl. the `offMine` exception).
2. Event branch — **unchanged** (colspan continuation `info.occupied` untouched).
3. **NEW too-soon branch** (frozen :866–871): `else if (isSlotTooSoon(weekData, currentWeek, di))` →
   `div.className = 'cell-content cell-too-soon';` — read-only: no tabIndex, no listeners, no tooltip. Placed *after* event/occupied and *before* available, exactly as frozen and as the arrangement page does (:1682/:1689).
4. **Available branch replaces** the current `cell-empty` branch (frozen :873–884):
   ```js
   const div = document.createElement('div');
   div.className = 'cell-content cell-available';
   if (hi + BOOK_SPAN > hours.length) div.classList.add('cell-no-fit');
   div.tabIndex = 0;
   div.dataset.day = di;
   div.dataset.hour = hi;
   div.setAttribute('role', 'button');
   div.setAttribute('aria-label', 'Available slot: ' + day.abbr + ' ' + hours[hi]);
   div.addEventListener('click', function (ev) { focusedCell = div; showAvailableTooltip(ev, di, hi); });
   div.addEventListener('focus', function () { focusedCell = div; });
   td.appendChild(div);
   ```
   `BOOK_SPAN = 4` (2h — the consumer's default block span) decides `cell-no-fit`; the consumer re-evaluates fit honestly per subject (`evaluateBookingFit`), so the page-side check is advisory-only for the default case.

**Dead CSS removal:** the inline tint rules `.lw-page … .cell-empty` / `:has(.cell-empty)` / `.cell-empty:hover` (:16–22) are deleted — no `.cell-empty` is ever painted on this page again (ui-common's default renderer `:800` is unused here because the page always provides `cellRender`). The `td:has(.cell-content)` positioning comes from `theme.css:2063` and applies to the new cells automatically.

**Clock-anchor bridge (required for lead-time honesty — reviewer round-1 🔴):** the layout loads the mock-data bridge on every page (`layouts/ui-template.blade.php:47`), which sets `MockData.mockNow = new Date(2026, 9, 5)` (`mock-data.js:62`). Every other real-data page nulls this anchor (`replacement-arrangement.blade.php:1184`, `replacement-home.blade.php:679`, `replacement-history.blade.php:158`, `my-request-history.blade.php:327` all do `MockData.mockNow = null;`) — the venue page never had to, because it had no too-soon logic to feed. The new branch calls `isSlotTooSoon` (`ui-common.js:130` → `getTodayMs()`), which resolves "today" from that anchor while the **consumer** uses the real clock: under the Oct-5 anchor the producer's lead-time cutoff is Oct 8, the consumer's is Oct 16 — every free cell dated Oct 8–15 would render bookable on the venue page and then be rejected as too-soon on arrival, and the page *opens* on the anchor's week (Week 3), i.e. precisely the mismatch window. **Decision:** add the established one-line bridge `MockData.mockNow = null;` to the venue blade's script head (4 in-repo precedents). **Declared behavior change** (recorded in the changelog + verified live): the venue page opens on the **real** current week and its week arrows/Today behaviour follow the real date instead of the frozen demo anchor. This is a data-anchor bridge patch only — no UI design change, §10.0 bridge allowance.

## D2 — Tooltip: CSS, markup, JS

**CSS (page inline `<style>`):** `.available-tooltip` block (frozen :178–212) ported with one exception — the box-shadow keeps the frozen literal `0 4px 16px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06)` (§10.0-friendlier `var(--shadow-md/lg)` exist, but the 99.9%-frozen-UI rule wins and the arrangement page ships the same class of literal shadows :64/:68 as approved precedent; recorded in the changelog). Separately, the frozen hover-label block (:315–317) is ported with the user's reworded strings: `.cell-available { --hover-label: 'Arrange ?'; }` and `.cell-available.cell-no-fit { --hover-label: 'Too late to book'; cursor: not-allowed; }`. `.cell-too-soon`'s shared label is untouched.

**Markup:** `#availableTooltip` (`:461–463` verbatim, `p#tooltipText` + `button.btn-book#tooltipBookBtn`) placed after the summary bar partial and before `#classModal`, mirroring the frozen layout position.

**JS — inside the existing `DOMContentLoaded` closure** (:110–308), exactly where the existing `buildTimetable`/`openModal` live — so `venueInfo` (:126, closure-only, never exported to `window`), `weekData` (:114) and `currentWeek` are all in scope:
- `showAvailableTooltip(ev, di, hi)` — frozen logic: no-fit guard first (`hi + BOOK_SPAN > hours.length` → `toast.show('A booking needs 120 minutes — not enough time left in the day.')` + return, no tooltip); fills `#tooltipText` = `"Arrange a replacement in " + venueInfo.code + " on " + dayName + ", " + dateStr + " at " + time + "?"` using `weekData[currentWeek].days[di].abbr/.date` and `hours[hi]` (live vars already exist: `venueInfo` :126, `weekData` :114, `currentWeek` via `weekNav.currentWeek`); wires `#tooltipBookBtn.onclick = () => bookVenue(venueInfo.code, dateStr, time)`; positions at `ev.target`'s rect (+4px below), fallback centred.
- `hideAvailableTooltip()` + scroll listeners on `window` and `document.querySelector('.grid-scroll')?.` (frozen :1082–1083; the wrapper id is `gridScroll` per `partials/ui-grid-table.blade.php:2`).
- `bookVenue(venueCode, date, time)` — frozen :1085 verbatim **minus** the `code`/`cohort` appends (no subject context on the live page): clears matching `bookingIntentCancelled`/`bookingIntentDismissed` sessionStorage keys, then `window.location.href = '/replacement-arrangement?venue=…&date=…&time=…&from=venue-timetable'` (all `encodeURIComponent`d).
- `keydown` listener: `Escape` → `hideAvailableTooltip()`; `b`/`B` with `focusedCell.classList.contains('cell-available')` → `showAvailableTooltip(null, +dataset.day, +dataset.hour)`. Coexists with the existing `closeOnEsc(closeModal)` (both fire harmlessly; frozen had the same pairing).

## D3 — Copy set (user decisions, 2026-10-13)

Exactly the proposal's table: legend tip (same Available entry, same colour, tip text only), `Arrange ?`, tooltip sentence, `Arrange` button, `Too late to book` — plus the hint line (*"Click any green empty slot to arrange a replacement in this venue"* replacing the Slice-B placeholder at :102). All five deviations from frozen wording are recorded in the changelog postscript with the decision date.

## D4 — Handoff contract (consumer untouched)

Unchanged, verified live 2026-10-13 on both honest branches: `resolveBookingIntent` (arrangement `:2472`) parses the URL, snaps venue/week, `evaluateBookingFit` + `consumeBookingIntent` auto-select or fall back honestly. Key formats byte-match (`venue|date|time`; `hours`/`generateWeekData` shared). This design adds **no** consumer code. Known deferred follow-up (explicitly out of scope, belongs with `full-block-reservation`): the *"is conflicted"* wording when a booked venue is matrix-ineligible for the picked subject.

## D5 — Test plan

**Scaffolding (one login per run, limiter-safe):**
- New `tests/auth.setup.ts`: UI-login as staff `5770` (the type-labels precedent + demo-fixture user), save `.auth/staff-5770.json`.
- `playwright.config.ts`: add `setup` project (`testMatch: '**/auth.setup.ts'`), chromium project gets `dependencies: ['setup']`; storageState is **not** set globally — opted in **per file** via `test.use({ storageState: '.auth/staff-5770.json' })` so existing specs' own login flows are untouched.
- `.auth/` added to `.gitignore`.
- `tests/venue-timetable.spec.ts` gains `test.use({ storageState: … })` at describe level + TC46 reword; `tests/venue-book.spec.ts` (new) uses it too.

**⛔ Task-level guard (restated from the frozen proposal): TC70 must not execute in ANY Playwright run before its disposition lands.** It is a mock-era test whose intended flow drives a real, destructive, non-idempotent write (`CancelClass` → `class_exceptions` + `audit_logs`, unique `(class_session_id, week_number)`) against the fragile demo DB. **Accurate mechanism on today's page** (reviewer round-1 🟡): TC70 expects `#eventModal`/`#cancelClassOverlay`/`#confirmCancelClassBtn` (:709–715) while the live blade's modal is `#classModal` and carries zero cancel-class machinery — so an authenticated run **dies at :709 before ever reaching the write**. The guard therefore stands as defense-in-depth against anyone "repairing" TC70's selectors into a live destructive flow; retirement is the correct disposition regardless. Concretely: the authenticated baseline runs `--grep-invert "TC70"`; TC70 is retired in the same change **before** the full-suite gate.

**Reconciliation sequence (venue-timetable.spec.ts, 65 tests):**
1. Scaffolding lands → baseline run **excluding TC70, on the pre-change blade** → per-test results recorded in review-log (settles TC49/TC51 attribution: auth-redirect vs missing elements).
2. Dispositions per the frozen proposal, extended (round-2-confirmation-B 🟡): **every** baseline-red template-era test gets a pre-decided disposition, never improvised under gate pressure. Beyond the tests listed in the frozen proposal, two more clusters are provably red today and stay red post-port:
   - **TC12/TC13** (`:123/:140`) expect `#favStar` — the live blade documents the fav star as **stays dropped** (blade :58–59, documented divergence) → **retire with a decision note; never modify the page to satisfy a test** (adding the star would violate the 99.9%-UI rule and the blade's own recorded decision).
   - **TC39–TC44** (`:371–424`) assert `#eventModal` / `.modal-footer [data-cancel-class-btn]` vs the live blade's `#classModal` with zero cancel-class machinery → re-point `#eventModal`→`#classModal`; retire any assertion the live blade cannot satisfy (e.g. cancel-machinery footer checks) with a decision note.
3. Dispositions per the frozen proposal: TC46 reworded to the Arrange regex `/Arrange a replacement in .+ on .+, .+ at .+/`; TC49–TC51 retired (banner is a non-goal; expect confirmation from baseline); TC70 retired on its destructive-write grounds (or re-pointed to a read-only assertion / testing-DB feature test — retire is the default); waking count-guards TC33/45/47/48/54/55 audited against the ported frozen ids (`availableTooltip`/`tooltipBookBtn`, tabIndex, role) — inherited where they match, fixed where the copy differs.
4. Full-suite gate last (`npx playwright test`): zero unexpected failures.

**New `tests/venue-book.spec.ts`** (≤6 tests, one storageState session, distinct slots per test to avoid sessionStorage bleed):
1. Free cell (future week, weekday, outside lead-time) — hover exposes the `Arrange ?` overlay (class + `getComputedStyle(::after).content`).
2. Click free cell → `#availableTooltip` visible, text matches `"Arrange a replacement in B110 on … at …?"`, `#tooltipBookBtn` says **Arrange**.
3. Click **Arrange** → URL carries `venue/date/time/from=venue-timetable`; arrangement page renders the booking-intent banner (consumer live — re-asserted end-to-end).
4. Too-soon branch: cells in the **real current week** (probed dynamically — the week containing today, thanks to the clock-anchor bridge; target non-weekend/non-holiday weekday cells — PH/Sunday rows render `cell-ph`/`cell-sun`, occupied cells render blocks) render `cell-too-soon`, no tooltip on click, no tabIndex.
5. `B` on a Tab-focused free cell opens the tooltip; Escape and grid-scroll hide it.
6. No-fit cell near day end (e.g. last slot): hover label `Too late to book`, click shows the shared toast, no navigation.

Test-plan precision notes: Playwright gives each test a fresh browser context, so booking-intent sessionStorage bleed exists only *within* a test across navigations — distinct slots per test covers it. Positive `--grep` filters apply to the setup project too, so single-TC verification must include `--project=setup` (or use file-level filters); the baseline's `--grep-invert "TC70"` is safe. Changelog nit (server-computed, PHP out of scope): the `sumAvailable` card copy (*"…can be booked"*) counts lead-time cells that now render `cell-too-soon` — noted for a future copy pass, not this change.

**Existing spec updates:**
- `tests/venue-db.spec.ts:88` — the `.cell-empty` `toHaveCount(0)` assertion becomes vacuous after the port; re-pointed to `toHaveCount(0)` on `.cell-available` for the holiday Monday row (same edit cost, keeps it meaningful: no bookable cells on a holiday).

## D6 — Inherited frozen quirks (explicit decisions)

- **`focusedCell` staleness after client-side week nav** (detached node; `B` then shows the new week's dates at the old position): **inherited** — byte-faithful default; the frozen template behaves identically. A cheap optional guard (`buildTimetable` sets `focusedCell = null`) is listed in tasks as an optional hardening the implementer may apply with a changelog note.
- **Document-level B/Escape keydown vs typing in inputs** (stale `focusedCell` + typing `b` pops the tooltip): **inherited**, same rationale.

## D7 — Dependencies & file inventory

- **Runtime:** 1 file — `resources/views/livewire/venue-timetable.blade.php`.
- **Tests:** `playwright.config.ts`, `tests/auth.setup.ts` (new), `tests/venue-timetable.spec.ts`, `tests/venue-book.spec.ts` (new), `tests/venue-db.spec.ts` (1 assertion).
- **Config/docs:** `.gitignore` (+`.auth/`), `page-changelogs/venue-timetable-ui-changelog.md`.
- **Untouched:** `theme.css`, `ui-common.js`, the arrangement page, all PHP, the demo DB (no new data requirements — B110's real Monday 11:00/Tuesday 09:00 sessions and the shared calendar suffice; tests pick free future-week slots dynamically rather than hard-coding occupancy).
- **Live verification:** stale-cache restart ritual (two separate commands) after the blade edit; then the venue page hover → tooltip → Arrange → landing canary on `:8000` as 5770 — plus the declared clock-anchor behavior check: the page must open on the **real** current week (2026-10-13's week), not the mock anchor's Week 3.
