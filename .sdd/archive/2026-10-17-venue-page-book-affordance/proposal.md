# Proposal: venue-page-book-affordance

**Bug-hunt item:** U2a (user-reported `Y/2a`) — the last remaining item from the original user bug report.
**Layer:** Frontend-only (venue timetable page). Zero PHP/backend changes.
**Template source of truth:** `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php` (the user's frozen design).

## Why

The live venue page was born (DB rewrite `fbe9eb7`) without the frozen design's booking affordance — free cells render a plain `.cell-empty` div with the comment *"booking arrives with Slice B"*, the hint line says *"Slot booking arrives with the replacement workflow (Slice B)"*, and the legend's Available tip promises booking *"arrives"* later. `git log -S availableTooltip` across all history confirms the live blade **never** had it — this is a first-time port, not a regression fix.

Meanwhile the **receiving side is already live and verified**: the arrangement page's `resolveBookingIntent` consumer (parses `?venue&date&time&from=venue-timetable`, jumps week/venue, renders the booking-intent banner, auto-selects or honestly falls back) was verified live at HEAD on 2026-10-13, on both honest branches (too-soon and conflicted). This change restores the producer side and completes the flagship demo journey: *scan venue page → click a free slot → Arrange → arrangement page opens pre-pinned → submit → pending block visible back on the venue page* (the pending block leg landed in `venue-pending-and-type-labels`).

## In scope

All ported from the frozen template, structure byte-faithful:

1. **Bookable free cells** — the `cellRender` free branch in `resources/views/livewire/venue-timetable.blade.php` switches from `.cell-empty` to the shared `.cell-content.cell-available` language (already in `theme.css:2077`, parameterized via `--hover-label`), with the frozen wiring: `tabIndex=0`, `role=button`, `aria-label`, `dataset.day/hour`, click → tooltip, focus → `focusedCell` tracking.
2. **Available tooltip** — frozen CSS `.available-tooltip` + `.btn-book` (template `:178–212`) ported into the page's inline `<style>` (page-inline precedent; tokens per §10.0), frozen markup (`:461–463`), and frozen JS `showAvailableTooltip` / `hideAvailableTooltip` (`:1046–1079`) incl. hide-on-scroll (window + `.grid-scroll`, `:1082–1083` — the wrapper exists via shared partial `partials/ui-grid-table.blade.php`).
   **No-fit click feedback:** the frozen no-fit path calls `toast.show('A booking needs 120 minutes — not enough time left in the day.')`. The live blade has no toast machinery of its own, but none is needed: `ui-common.js:2603` already constructs the global `toast` (`new ToastManager()`; `#toastBar` exists in the shared layout, `layouts/ui-template.blade.php:68`). The frozen call is reused as-is; the new spec covers the no-fit **click** path (hover label + toast), not just the hover.
3. **Arrange handoff** — frozen `bookVenue` (`:1085`): clears stale `bookingIntentCancelled`/`bookingIntentDismissed` sessionStorage keys for the slot, then navigates to `/replacement-arrangement?venue={code}&date={DD Mon YYYY}&time={HH:MM}&from=venue-timetable`. Both pages share `hours` (`ui-common.js:586`, 24h strings) and `generateWeekData()` (`ui-common.js:53`, `DD Mon YYYY` dates), so the contract consumes directly; the sessionStorage key format byte-matches the consumer's `bookingKeyFromParams`/`intentKey`. The frozen `code`/`cohort` params are simply omitted (the live page has no subject context; the consumer already handles their absence: *"will pre-fill when you next pick a subject"*).
4. **Read-only free cells** — two frozen rules, both with existing shared infrastructure:
   - **Too-soon**: free cells inside the 3-working-day window render `cell-too-soon` (no booking affordance, no tooltip, no keyboard) — `isSlotTooSoon` is shared (`ui-common.js:130`), `theme.css .cell-too-soon` exists (`:2197`, incl. its own informational hover label *"Min. 3 working days ahead"*), arrangement-page precedent (`replacement-arrangement.blade.php:1682/:1689`).
   - **No-fit**: a cell too close to day-end for a 4-slot (2h) block gets the `cell-no-fit` hover label — `BOOK_SPAN = 4` (template `:1044`) intentionally equals the consumer's default `BLOCK_SPAN = 4` (`replacement-arrangement.blade.php:1190`), so the page-side fit-check and the consumer's default agree.
5. **`B` keyboard shortcut** + **Escape** closes the tooltip (frozen `:1101–1117`); B works via Tab focus (no arrow-key nav needed — see non-goals).
6. **Copy set** — user decisions of 2026-10-13 (wording deviates from the frozen template BY USER DIRECTION; structure unchanged). All five strings:

| Where | Frozen text | New text (user-approved) |
|---|---|---|
| Legend tip (Available entry — same entry, same colour) | *Free slot — click to book this venue (Sunday, holiday and lead-time slots can't be booked)* | *Free slot — click to arrange a replacement in this venue (Sunday, holiday slots and slots less than 3 working days away can't be booked)* |
| Free-cell hover label (`--hover-label`) | `'Book ?'` | `'Arrange ?'` |
| Tooltip sentence | *Book B110 on Thu, 22 Oct 2026 at 14:00?* | *Arrange a replacement in B110 on Thu, 22 Oct 2026 at 14:00?* |
| Tooltip button | `Book` | `Arrange` |
| Too-close-slot hover label (`cell-no-fit`) | `'Not bookable'` | `'Too late to book'` |

   Plus, for consistency with the same decision: the hint line placeholder (*"Slot booking arrives with the replacement workflow (Slice B)."*) is replaced by the frozen hint's copy adapted to the arrange wording — *"Click any green empty slot to arrange a replacement in this venue"*.

## Explicitly out of scope (documented non-goals)

- **Mobile card list** — the live page never had one; on touch, tapping a grid cell is a click and opens the tooltip. Restoring the frozen mobile card surface is a separate, larger item if ever wanted.
- **Full arrow-key grid navigation** — the frozen template had it (template-era feature), but the live page never did; B works via Tab focus, which is in scope. Arrow nav can be its own future item.
- **"Booking for: course — cohort" banner** — frozen feature bound to the mock-era venue-page subject picker, which the real-data page doesn't have. Not applicable; omitted.
- **Tour / keyboard-hint help entries** — separate template-era feature.
- **Consumer-side changes** — the arrangement page's booking-intent consumer is live, verified, and untouched by this change. (Known follow-up, deferred to the next change `full-block-reservation` (Fix 3), which touches the same flow: the honest wording when a booked venue is matrix-ineligible for the picked subject currently reads *"is conflicted"* — truthful in effect, imprecise in cause.)
- **PHP/backend/schema** — none. The legend tip string lives in the blade's rendered legend array, not in backend logic.

## Impact scope

- **Code (1 file):** `resources/views/livewire/venue-timetable.blade.php` — cellRender free branch, page inline `<style>` (tooltip CSS; removal of the now-dead `.cell-empty` tint rules), inline `<script>` (tooltip JS, `bookVenue`, keydown), tooltip markup, hint line, legend tip string.
- **Shared files: zero.** `theme.css` (`.cell-available`/`.cell-too-soon` already exist), `ui-common.js` (`isSlotTooSoon`, `hours`, `generateWeekData`, `buildTimetableGrid` already shared; `cellRender` is page-provided), the arrangement page, and all backend/PHP are untouched.
- **Tests (reconciliation required):**
  - `tests/venue-db.spec.ts` — the `:88` `.cell-empty` assertion needs updating to the new cell language.
  - `tests/venue-timetable.spec.ts` — **65 tests targeting this same live page**, carrying a template-era test layer written against the post-port "Book" language. **🔴 Discovered red today, before this change — attribution corrected in round 2:** a 2026-10-13 scoped run of TC49/TC50/TC51 shows **TC49 + TC51 already failing** (2 failed / 1 passed). However, that probe ran **unauthenticated**, and `/venue-timetable-ui` is gated `auth + role:lecturer` (`routes/web.php:73–77`, asserted green for guests by `RouteGateMatrixTest`) — so unauthenticated runs land on the login page and the **whole 65-test file mass-fails unauthenticated**. The file carries **zero login scaffolding** (`beforeEach` just `goto`s the page) and has evidently been excluded from recent gate runs. The banners-vs-auth attribution for TC49/TC51 is therefore **unresolved** until an authenticated baseline exists. Reconciliation deliverables, in order:
    1. **Login scaffolding** for the file — one login per run via Playwright `storageState` (setup project or equivalent reuse), **never per-test logins** (65 logins would blow the Fortify 5/min limiter; the per-test-login pattern is only viable for small files like `venue-db.spec.ts` — 5 `loginAsStaff` call sites, each staff ID at most 3× per run).
    2. **Authenticated full-file baseline run, excluding TC70** (`--grep-invert "TC70"` or equivalent skip) — establishes each remaining test's true state and settles the TC49/TC51 attribution (auth-redirect vs genuinely missing template elements). **TC70 must NOT execute in ANY Playwright run before its disposition is decided** — once login scaffolding exists (deliverable 1), even an intermediate run verifying TC46's reword would execute TC70 authenticated, and the write commits server-side at the confirm click (`:715`) *before* TC70's `.cell-available` assertions fail at `:722`, so even a "failing" run mutates the demo DB. It drives the real cancel-class flow (`app/Actions/CancelClass.php:39–55` creates a `class_exceptions` row + an `audit_logs` row in a transaction) against the fragile demo DB, and is non-idempotent by schema (unique `(class_session_id, week_number)`, `CancelClass.php:17–18`) — it can pass at most once per occurrence and mutates real data on its first run per occurrence.** TC70's disposition is applied **before** the SC6 full-suite gate.
    3. **Dispositions applied to the baseline facts**:
    - **TC46** (`:453–460`) — tooltip regex `/Book .+ on .+, .+ at .+/` is a count-guarded no-op today; it **activates** the moment `.cell-available` lands and would fail against the user-approved Arrange wording. Must be reworded per the copy table above (this stands regardless of the baseline).
    - **TC49–TC51** (`:483–503`) — `#bookingBanner`/`#errorBanner` don't exist in the live blade and the banner is an explicit non-goal → expected **retire** with a decision note (confirm against the baseline; re-pointing to the arrangement page's booking-intent banner is out of scope here).
    - **TC70** (`:690–730`) — mock-era fixture assumption (its own comment: *"VERIFIED against public/js/mock-data.js §2.7"*); asserts freed slots render `.cell-available`. Beyond the cell-language mismatch, the decisive retirement grounds are that it performs a **real destructive write** (see deliverable 2) — a mock-era test driving `CancelClass` against the demo DB is unfit to run at all. Retire (or re-point to a read-only assertion / testing-DB feature test) with a decision note.
    - **TC33/TC45/TC47/TC48/TC54/TC55** — dormant count-guards that wake once `.cell-available` exists. They reference the frozen ids (`availableTooltip`/`tooltipBookBtn`, `tabIndex=0`, `role=button`) which the port preserves byte-for-byte — inherited as free coverage where they match the recorded copy; fix any that contradict it.
  - One new Playwright spec for the affordance + handoff (same login pattern as the reconciled file).
  - No PHP feature tests (no PHP behaviour change).
  - **Gate honesty note:** Playwright's own `--list` reports the full suite at **316 tests in 25 spec files** (12 `tests/*.spec.ts` + 13 `tests/e2e/*.spec.js`), while earlier gate records say "full Playwright suite" at ~120 tests — those runs were scoped subsets (now known to exclude this very file). This change's gate runs the true full suite, which is why the template-era layer surfaces now; the reconciliation above is what makes "suite green" achievable honestly. SC6 therefore requires *zero unexpected failures* rather than a pinned count.
  - **Impact note for deliverable 1:** the storageState scaffolding likely touches `playwright.config.ts` (setup project + project dependency) and/or adds a setup spec plus `.auth/` state files (gitignore-checked) — listed so `/sdd-apply` doesn't surprise.
- **Docs:** `page-changelogs/venue-timetable-ui-changelog.md` postscript (incl. the five-string user-decision record).

## Success criteria

1. Free cells (not Sunday/holiday, outside the 3-working-day window) show the frozen hover overlay with **"Arrange ?"**, are Tab-focusable, and open the tooltip *"Arrange a replacement in {code} on {day}, {date} at {time}?"* with an **Arrange** button; `B` opens it on the focused cell; Escape and scroll close it.
2. Clicking **Arrange** lands on `/replacement-arrangement?venue=…&date=…&time=…&from=venue-timetable`, where the live consumer renders the booking-intent banner and snaps venue/week (verified consumer behaviour — re-asserted by the new spec).
3. Sunday/holiday cells stay `cell-ph`/`cell-sun`; too-soon cells render `cell-too-soon` with **no booking affordance** (no tooltip, no keyboard — the shared informational hover label *"Min. 3 working days ahead"* from `theme.css:2199` remains); near-day-end cells show the **"Too late to book"** hover label, and clicking one gives the frozen toast feedback via the shared global `toast`.
4. Legend tip and hint line show the new wording; no legend entry or colour changes (the Available entry keeps its swatch; only its tip text changes).
5. Gates stay green: pint ✓, phpstan 0, phpunit 172/172 (no PHP change); live verification via the stale-cache restart ritual on the venue page (hover → tooltip → Arrange → landing).
6. **Playwright reconciliation:** after the deliverables above (login scaffolding → authenticated baseline excluding TC70 → dispositions), `npx playwright test` (the true full suite — Playwright's own count: 316 tests in 25 spec files) runs with **zero unexpected failures** — TC46 asserts the Arrange wording, **TC49–TC51 are retired/re-pointed per the baseline facts, TC70 per its destructive-write grounds**, the waking count-guards (TC33/45/47/48/54/55) either pass against the ported frozen wiring or are fixed to it, and the file's login scaffolding keeps every future run authenticated. The template-era red is resolved as part of this change, not left behind it.
7. Changelog postscript records the change and the five-string user-decision table.

## Risks / mitigations

- **Visual parity of the free-cell class swap** (`.cell-empty` → `.cell-content.cell-available`): both are `--color-success-container` at rest; the hover changes from the current dark-green tint to the frozen "Arrange ?" overlay — which *is* the intended frozen look. The inline `.cell-empty` tint rules (`:16–22`) become dead and are removed in the same change.
- **Auth gate + Fortify login limiter (5/min per login_id|type|ip)** — `/venue-timetable-ui` is gated `auth + role:lecturer` (`routes/web.php:73–77`), so **every** venue-page spec needs a login (correcting the round-1 draft, which wrongly claimed guest-reachability). The new spec and the reconciled `venue-timetable.spec.ts` log in as staff with **one login per run** via Playwright `storageState` reuse; small files' per-test logins (venue-db: 5 `loginAsStaff` call sites, each staff ID at most 3× per run) stay within the limiter, but a 65-test file must never login per test. The handoff-landing assertion reuses the same storageState session — no second login.
- **sessionStorage bleed between spec assertions**: booking-intent keys are per-origin sessionStorage; the spec uses distinct slots per assertion or clears storage between checks (design detail in design.md).
- **Inherited frozen quirks** (design will record an explicit inherit-or-guard decision for each, keeping byte-faithful as the default):
  - `focusedCell` references a detached node after client-side week nav — pressing `B` then opens the tooltip with the new week's dates at the old position (the frozen template has the same behaviour).
  - The `B`/Escape keydown listener is document-level and only checks `focusedCell`'s class — typing `b` while a stale `focusedCell` exists (e.g. focus moved to an input) can pop the tooltip (frozen behaviour too).
- **Template-era layer state unknown until baseline** (TC49/TC51 verified failing on 2026-10-13, but that probe was unauthenticated — cause attribution, auth-redirect vs missing elements, is settled only by the authenticated baseline run). Surfaced by this change's true-full-suite gate; resolved by the reconciliation deliverables rather than worked around.
