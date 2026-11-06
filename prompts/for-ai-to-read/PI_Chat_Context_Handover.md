# PI Chat Context Handover — TAR UMT Class Replacement System

> **Restructured 2026-11-03.** The six session addenda that used to sit here
> (§0.5 – §0.10, ~540 lines) were moved verbatim to
> **`history/2026-10-31-to-2026-11-03-addenda.md`** — archived prompts and SDD
> review logs still cite those section numbers, so they keep their names there.
> Everything in this file is either current state, a standing ruling, or a
> pointer. Paths are repo-root-relative unless they start with `../`.
>
> **Every volatile number below is marked with its date.** Re-check it before you
> quote it; a stale count has caused wrong decisions before.

---

## 0. Quick Status (read this first)

| Item | State |
|---|---|
| Branch / remotes | `fedora-backend` → `origin` = `fjing03/CRS-fedora.git` (**10 commits ahead**, see Push state); `upstream` = `FjingXR/class-replacement-system` (**pull-only**) |
| Push policy | **NEVER `git push`** — the user pushes. One commanded exception, 2026-11-02; afterwards the rule resumed. |
| Push state | `origin/fedora-backend` sits at **`39b2bb7`**; **10** commits are unpushed (2026-11-06, newest `65188bd`). Re-check: `git rev-list --count origin/fedora-backend..HEAD`. The user pushes. |
| AI context layout | Root **`AGENTS.md`** is a bootstrap pointer → this folder holds `AGENTS.md`, `CodingMAIN.md` (the spec), `my-coding-rules.md` (quick rules), `FR&NFR copy.md` (read-only without the user's approval), this file, and `history/`. |
| Servers | `:8000` = `CRS-fedora-backend` / **`class_replacement_fresh`** (dev). `:8001` = `../CRS-demo` / **`class_replacement_viva`** (viva twin, `APP_DEBUG=false`). `:8030` = disposable AI/E2E lane on `class_replacement_testing` only. |
| Database trap | `class_replacement_demo` is the **older, empty** twin (0 requests) — not the viva DB. |
| Demo data (2026-11-05) | `class_replacement_fresh`: **5 requests** — 24 rejected (session 40 W14), 25 cancelled (session 38), 26 **approved** (session 40 W14), 27 pending (session 38), 28 pending (session 97 W8) — **77** notifications (**3** hidden), **2** `class_exceptions` (session 24 W7 medical_leave, session 40 W14 official_event), 8 slot rows. Two test-residue cleanups the same day (the Test-2 cancellation, and request 29 + its 2 notices + 5 audit rows): both net-zero, both logged. |
| Test DB | `class_replacement_testing` — disposable; `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed` is fine **there only**. |
| Ops trap | `opcache.enable_cli=On` with `revalidate_freq=180` → a long-running `artisan serve` can serve a `.php` file's old bytecode for ~3 min. Use **`scripts/dev-serve.sh [port]`** (kills that port, clears views, starts with `revalidate_freq=0`). |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` · full phpunit · `node --check` for JS. **Never** `composer run types:check` (crashes at 128M). |
| Suite | Last full PHPUnit run **386 tests / 2031 assertions** (2026-11-06, after `40ffb5f`; +27 = the auto-reject rule's 17 clock/sweep cases + the 10 submit-gate cases). Browser suites are separate and NOT part of that number — quote them by name: `venue-book` 19, venue neighbours 61 passed / 2 skipped, `cancel-class` 12 passed / 4 skipped, `status-tooltips-filter-chips` 9, `notif-row-inactive-hide` 5. |
| SDD lanes | **35** lane dirs under `.sdd/changes/` (**33** older finished ones + `venue-slot-validation` and `notif-inactive-hide-sender`, both `status: applied` 2026-11-05), **101** archived. Archive only on the user's word. |
| **Auto-reject rule (2026-11-06)** | A **pending** request whose proposed start is within — or exactly — **3 working hours** is rejected by the system. Working time = TARUMT Sabah hours, **Mon–Fri 08:30–17:30**, weekends + the semester's `holidays` excluded (`App\Support\WorkingHours`, `MIN_GAP_MINUTES = 180`). Write leg `App\Actions\AutoRejectRequest` (cancel-family release, `approver_id` NULL, audits `details.auto`); trigger `php artisan requests:auto-reject` (`--dry-run`) scheduled every 5 min — **never** a write-on-read. The Pending badge's `data-tip` states the deadline (proposed start − 3 working hours) on both pages. |
| **A cancelled request displays as "Need to Arrange" (2026-11-06)** | A lecturer's own cancelled pending request is DISPLAYED as no active request on My Replacements: `badge-outline status-none` reading **Need to Arrange** (no-request tip), an em dash in REQUESTED REPLACEMENT, and a details pop-up with **no Proposed Replacement Schedule tab** (no Request ID/Week/Day/Date/Time/Duration/Venue). One normalisation in `ReplacementHome::buildRow()` — status→null + strings blanked. The RECORD is untouched (still `cancelled`, audited, slots released); history + notifications still say *Cancelled by YOU*. The status filter offers **no Cancelled option** (nothing on the page can carry that state); a filter saved before the removal is mapped onto Need to Arrange at restore. Receipts: `request-cancel-pending.spec.ts` (write), `replacement-home-status-badges.spec.ts` receipts 9 + 10. |
| **A JS-owned control survives a Livewire round-trip (2026-11-06)** | The week `<select>`s have no server-side options, so a page-component re-render used to morph them empty — Approve/Reject blanked the Week filter and its chip printed a bare `Week: `. Guard: `withWeekLivewireGuard()` in `ui-common.js` (snapshot before `pageLivewire().call()`, restore options + value **before the caller's `.then()`**), plus a `Livewire.hook('commit')` net. Receipt: `tests/week-filter-preserved.spec.ts` (U1 Approve, U2 Reject, U3 Cancel Pending). |
| **The submit action's own guards** | Since 2026-11-06 `SubmitReplacementRequest` enforces what only the page did: FR 2.17's 3-working-day notice (`slot_too_soon`, `WorkingHours::noticeCutoff()`) **and** D2's holiday landing ban (`holiday`). Sunday needs no arm (no Sunday slot rows). `tests/Feature/SubmitGatesTest.php` is the receipt file (10 cases). |
| **FR 2.17 is server-side** | Since 2026-11-06 `App\Actions\SubmitReplacementRequest` refuses a slot inside the 3-working-day notice window (`slot_too_soon`) — the grid's `leadTimeCutoff()` was only the browser's word. Calendar: `WorkingHours::noticeCutoff()` / `isBookableOnDate()`, the same class as the auto-reject sweep, so grid "Bookable from …", the gate and the pending-deadline tooltip agree (measured 2026-11-06: Fri 6 Nov → **12 Nov** on both sides, 98/98 semester days). |
| **Venue page slot rules** | Since 2026-11-05: hover asks ONE question (**one clear hour**, `MIN_BOOK_SLOTS = 2`, never derived from a class), and a class's duration is judged only AFTER it is picked (Arrange gate → `#slotWarningOverlay`, no navigation). Lane `venue-slot-validation`; commits `daf11c6` + `bee6176`. |
| **The merge lane** | `replacement-merge-one-table` — **APPLIED 2026-11-04**, archived `07274c1`: `e70c1ea` (payload/mapper), `6856577` (view), `ab65f9a` (routes + retired page), `d3f6f5b` + `c4d0ad3` (specs). The merged page is **My Replacements** at `/my-replacements`, with `/replacement-home-ui` and `/my-request-history-ui` as alias route entries. |
| **Cancel Class** | **FINAL since 2026-11-05** — undo gone from the UI, the JS and the server (`23c627c`); the inbox marks stale notices inactive at read time instead of offering dead clicks (`7b7f0d9`); four final-decision modals carry *"This action cannot be UNDONE."* (`12c644a`). |
| **E2E write-guard** | Live and fail-closed: `tests/helpers/receipt-target-guard.ts` + the three guarded specs (`b924552`, lane `receipt-target-guard` applied `7bbce9f`). Its one unverified receipt is `T3d` — the submission-conflict suite is not green; see §5. |
| Language | **B1-level English only** — no other language, ever. |
| Approval rule | Investigate first. **No code edit without the user's explicit per-task approval.** |

---

## 1. Latest sessions — 2026-11-03 → 2026-11-06

### 2026-11-06 — a cancelled request stops advertising its dead proposal (user brief)

- Brief *Cancelled Pending Requests*: after the owner cancels, the occurrence must read **Need to
  Arrange** (badge), show `—` in REQUESTED REPLACEMENT, and lose the whole **Proposed Replacement
  Schedule** tab in the pop-up — without touching the record.
- Implemented as ONE normalisation in `ReplacementHome::buildRow()`: an owner-cancelled row's status
  becomes `null` and its replacement strings (plus `remarks`/`rejectionReason`) are blanked. The view
  already renders the no-request state for an absent date, so the badge class/tip/sentence, the dash,
  the missing tab, the visible Arrange button and the needs-action sort group all follow — **no view
  or JS change at all**.
- `cancelled` == "the owner cancelled it" because `CancelPendingRequest` is the only writer of that
  status and it is ownership-checked (FR 2.10). Untouched: the record, `decided_at`, the audit rows,
  the released slots, the notification; `requestId` stays on the payload for the row identity and the
  notification jump. Rejected/Approved/Completed/Pending rows are byte-identical.
- Tests moved with the law: `MyReplacementsPayloadTest` cases 5 + 9, `ReplacementHistoriesTest`,
  `SpanReservationTest` (its anchor-after-release leg is now re-pointed at a **rejected** row, where
  that fallback is still visible — the cancelled row no longer displays a slot at all).
- Receipts: `request-cancel-pending.spec.ts` green (real submit → cancel → badge/dash/modal/DB/audit
  asserted, fresh load agrees, span bookable again); `replacement-home-status-badges.spec.ts` **12/12**
  on the demo DB (new receipt 9); `week-filter-preserved` + `my-replacements-after-submit` +
  `arrangement-entry-guard` 13/13; full PHPUnit **386 / 2053**.
- Follow-up on the user's word ("remove that option"): the status filter's **Cancelled** option is
  gone (All / Need to Arrange / Pending / Approved / Rejected / Completed), and `restoreFilters()` maps
  a pre-removal saved `status: "cancelled"` onto `none`, so an old browser session neither blanks the
  dropdown nor shows an empty list. Receipt: badges spec receipt 10 (red without the mapping).
- Also fixed while using it: `request-cancel-pending.spec.ts` had a strict-mode violation of its own
  (the shared irreversible line carries TWO `.warn-action` spans — CANCEL and UNDONE — so the UNDONE
  assertion needed `.last()`). That spec had been red since the CANCEL keyword landed.

### 2026-11-06 — the Week filter no longer blanks after a decision (bug report)

- Report: after **Approve/Reject** on `request-approval-ui` and **Cancel Pending** on `my-replacements`
  the Week dropdown went blank while its chip stayed, printing a bare `Week: `.
- **Cause, measured:** the week selects have NO server-side options — `populateWeekSelect()` builds them
  from `weekRanges`/`weekData`. A page component's round-trip morphs the DOM from the server's HTML
  (empty `<select>`), so the options were deleted and the value collapsed to `''`; the chip, rebuilt
  from the empty select, lost its label. Pre-fix receipt (non-writing round-trip, `approve` on an
  unknown id): `options 15 → 0`, `value '8' → ''`, chip `Week: ×`.
- **Fix (shared, no per-page code):** `withWeekLivewireGuard()` — `pageLivewire()` now returns a Proxy
  whose `call()` snapshots every registered select's value and restores options + value **before the
  caller's `.then()`** (that callback rebuilds the chips — a deferred `setTimeout` restore is too late,
  measured). A `Livewire.hook('commit')` net covers other round-trips. Attributes still morph, so
  `cohort-timetable`'s server-driven `disabled` is untouched; no `wire:ignore`.
- Receipt: `tests/week-filter-preserved.spec.ts` (guarded, writes on the testing DB; reseeds
  `TestingFixtureSeeder` per test, clearing `audit_logs`/`notifications` first because the FK blocks it):
  **3/3 green**, and **red before the fix** for U1/U2/U3 (U3 only went red once it also asserted the
  cancel's own toast — a silent no-op had been passing it).
- Neighbours re-run green: `my-replacements-after-submit` + `arrangement-entry-guard` (13 passed),
  `venue-book` **18/19** — `U2a-T3` fails **identically on the unmodified HEAD** (hover-tooltip race;
  pre-existing flake, logged in §5).

### 2026-11-06 — FR 2.17 gets a server leg (the grid stops being the only guard)

- The user's challenge was correct on the rule, wrong on the conflict: the grid already refuses a
  too-soon cell (**3 working days**, `leadTimeCutoff()`), and the auto-reject rule closes the *later*
  decay (a request legally made 3 working days out that the PL never decides). Different moments,
  different units, no overlap.
- But the notice lived **only in JavaScript**: `SubmitReplacementRequest` had no lead-time check, so
  a direct submit could create a Pending row for a slot hours away — the exact row the sweep would
  then have to clean up. Now the action refuses it: the slot's DATE must be at least the **3rd
  working day** after today (weekends + holidays skipped, the 3rd working day selectable, today
  never), failure **`slot_too_soon`**, mapped to the grid's own sentence.
- One clock: `WorkingHours::noticeCutoff()` / `isBookableOnDate()`. Measured with Playwright on the
  demo DB (read-only): the page prints *"Bookable from Thursday, 12 Nov 2026 onward"* and the
  server's cutoff is **2026-11-12**; all **98** semester days cross-checked JS vs server → **0
  mismatches**.
- Receipts: new `tests/Feature/LeadTimeGateTest` 6/6; full suite **382 / 2022**.
- Fixture consequence (kept honest, not hidden): `tests/TestCase::bookablePairs()` is now the shared
  helper, the two scanning suites scan bookable pairs only, `SpanReservationTest`'s fixture semester
  starts two weeks back, and the two fixed-calendar suites (`ReplacementArrangementTest`,
  `OccurrencePinTest`) pin `Carbon::setTestNow('2026-09-14')`.
- **The sibling hole was closed the same day** (`40ffb5f`): D2's holiday rule now lives in the action
  too (`holiday`), so a crafted submit can no longer land a makeup on a public holiday. Sunday needs
  no arm — the grid has no Sunday slot rows. Precedence: notice gate first, so a holiday inside the
  window answers `slot_too_soon`.

### 2026-11-06 — the auto-reject rule (one clock, two faces)

- **The rule.** A **Pending** request whose proposed start is within — or exactly — **3 working
  hours** is rejected automatically; further out it stays Pending; no other status is ever looked
  at. Working time = **Mon–Fri 08:30–17:30** on the TARUMT Sabah clock, with the semester's own
  `holidays` rows excluded — so Friday 16:30 → Monday 09:30 is **2 hours** (reject) while
  → Monday 11:00 is **3.5 hours** (stay). The brief's example table is the unit contract
  (`tests/Feature/WorkingHoursTest`, 7 cases).
- **`app/Support/WorkingHours.php`** — the one office-hours clock: `gapMinutes()` (working minutes
  between two instants; a weekend proposal starts counting at the next opening bell; a past start has
  0) and `subtractWorkingMinutes()` (the inverse, used for the deadline). Holidays resolve through
  `semesters.start_date` + the `holidays` week/day rows — no second holiday list.
- **`app/Actions/AutoRejectRequest.php`** — reuse of the cancel family's release (version-checked
  `pending→available`, junction DELETE) with the rejected stamp: `approver_id` stays **NULL**,
  audits ride the allowed `rejected` action with `details.auto = true` and the PROPOSER as `user_id`
  (`audit_logs.user_id` is NOT NULL; there is no system user). No mail — the feed derives the
  rejection from the status.
- **`app/Services/PendingRequestExpiry.php`** + `php artisan requests:auto-reject` (`--dry-run`),
  scheduled every five minutes. Deliberately **not** a write-on-read: a page render must never
  mutate the demo DB, so the sweep only ever runs from the command.
- **The badge tooltip** (both pages, one vocabulary): a pending row's `data-tip` gains a second line
  naming the deadline — `If the Programme Leader has not Approved this Request by Fri, 13 Nov 2026,
  10:00 AM, it will be Automatically Rejected.` (the PL reads *If you have not Approved it by …*).
  The deadline is computed from the row's OWN `replacementDate`/`replacementTime` strings, so it
  costs no second junction read (the merged page's constant-statements law still passes).
- **Trap worth keeping:** the brief said `<br>`, but `data-tip` is rendered with `textContent`
  (+ `pre-line` on a `\n`) — HTML would print literally. The tooltip is two newline-joined lines.
- Receipts: full suite **376 / 1998**; `status-tooltips-filter-chips.spec.ts` **9/9** on the demo DB
  (:8010, read-only — its pending receipts now pin the two-line SHAPE, a fixed date would rot);
  demo DB `--dry-run` → *Scanned 2 pending request(s); would auto-reject: none* (both demo requests
  sit 3.5 h out — the brief's own "remain Pending" row). Nothing written to any demo DB.

### 2026-11-05 — the notifications panel's two follow-ups

- **`3f4a440`** — the panel now opens on **actionable unread**: `activeOnly` defaults to `true`
  (`unreadOnly` already was), so decided/superseded/undone rows are one toggle away. 25 panel
  constructions in `NotificationsBridgeTest` that assert inactive-row markup now pass
  `activeOnly: false` explicitly instead of riding a default.
- Same commit: **every disabled control shows `cursor: not-allowed`** (one `theme.css` rule for
  `button/.btn/input/select/textarea:disabled`, still no `pointer-events: none` so tooltips
  survive). `#notifMarkAll` needed its own `:disabled` line — its long-standing **ID** rule
  (`cursor: pointer`) out-specifies any class. Measured, not assumed: computed cursor read
  `pointer` until the ID got the state.

**2026-11-05 — the cancel-final chain, the E2E write-guard, and the toast order (8 unpushed commits):**
- **`7b7f0d9`** — the inbox stops offering dead clicks *and* Cancel Class becomes final (the user
  chose option B after a row-highlighting investigation). Two read-time rules in
  `NavNotifications`: a request-less `update` row whose `class_exceptions` row is gone →
  `inactive_reason = 'undone'`; an `approved`/`rejected` row older than the newest request for the
  same class+week → `'superseded'`. Both render disabled with their own tooltip. **Never a data
  edit, never a delete, never a re-link.**
  - Bug found while wiring it: **`Eloquent\Collection::merge()` re-keys and silently drops string
    keys** — the keyed exception map emptied and live cancellations were mislabelled "undone". Fixed
    with a plain array (`exceptionMap()`); two Feature tests now create their `class_exceptions` row
    the way `CancelClass` does in production.
  - Bug 2 found: fresh cancel notices carried **no jump target** — the target derivation only
    understood the legacy `/my-timetable-ui` link while new notices use `/my-replacements`.
- **`23c627c`** — the retired undo path deleted (`cancelClassUndo()`, `class_cancel_undone`, the
  `priorStatus` ledger field, `UNDO_TOAST_MS`, the `?restored=1` toast); `showUndoToast()` became
  `showCancelToast()`. The ledger `exceptionId` and the generic toast `undoCallback` API stay —
  other features use them.
- **`12c644a`** — one shared irreversible sentence, `partials.ui-irreversible-line`, on FOUR modals
  (Approve, Reject, Cancel Request, Cancel Class); the old red `.confirm-irreversible` CSS deleted.
- **`2156172`** — a refused cancellation explains itself again: `_refreshGate()` re-armed the button
  and rewrote `#cancelReasonHint` AFTER the refusal copy was written, erasing it. Gate first, message
  second, on a freshly queried node (the Livewire morf can detach it).
- **`ce31746`** — `tests/cancel-class.spec.ts` rewritten for the no-undo app (12 passed / 4 skipped):
  an `openFirstCancellable()` live-deck walker instead of hardcoded week/class, S6 rebuilt as a real
  two-tab race (a frozen clock lets `auto-logout.js` submit the logout form — measured), and a
  corrected receipt: a cancelled block does NOT disappear, it re-renders as `event-conflict` with a
  `(Cancelled)` marker.
- **`b924552` + `7bbce9f`** — the `receipt-target-guard` lane applied: the write-capable specs now
  REFUSE (exit 1 at collection) instead of soft-skipping when the blessed testing env is missing;
  one UI-regression confirm got an in-test throw (it writes a real request row).
- **`1156685`** — found by the user in the manual conflict walkthrough: the blocked arrival
  (`?blocked=request-pending`) showed a *cancellation* reminder and swallowed the entry-guard
  message. Now the blocked arrival speaks first (the note is deferred, not spent), and the reminder
  is **one-shot** (`markLoadToastShown()` writes `toastShown`), so a note the app can never clear
  cannot nag on every load.

**2026-11-05 (later) — after-submit highlight, the YOU relabel, and the archive pass (4 more commits):**
- **`a29f694`** — *My Replacements After Submit*: the submission success toast
  (`✓ Submitted — Pending Approval` + `View →`) and its 1.2 s pause are gone; the redirect
  carries `?new-req=<id>` and the new row pulses with the shared `bookable-pulse` (3 × 0.5 s).
  Server: `submitBlocks()` returns `request_id` (additive). Client: `jumpToNewRequest()` +
  a synchronous, self-stripping param. A hiding filter is cleared **for the visit only**
  (`saveFilters()` deliberately not called); "Exclude COMPLETED" stays ticked; nothing found =
  silent. Receipt: `tests/my-replacements-after-submit.spec.ts` (guarded) — 510 / 564 ms to
  navigate, zero toasts, one row flashed, saved filters intact.
- **`476406a` + `43bda16`** — the Event Hover Tooltip relabel: the own-class segment of
  `data-tip2` reads **`YOU`** instead of `ME` on the cohort and venue timetables (two different
  ownership mechanisms — name comparison vs the server's `mine` flag), and the two reference
  mock-ups under `ui-design-templates/` were brought along. Zero `ME` tooltip labels remain.
- **The archive pass**: seven finished lanes moved to `.sdd/archive/` (`git mv`, `status:
  archived`, `archived: 2026-11-05`). `.sdd/changes/` is down to **33** dirs, all older finished
  lanes; `.sdd/archive/` holds **101**.

**2026-11-04 — the merge lane: planned, then applied and archived (seven commits, unpushed at the
time of writing):**
- All four SDD batches passed review (**proposal 8 rounds**, **design 2**, **specs 2**,
  **tasks 5**), then PI applied them: `e70c1ea` (payload + vocabulary), `6856577` (the view),
  `ab65f9a` (URLs, routes, retirement), `d3f6f5b` + `c4d0ad3` (the reversed specs), `008e941`
  (changelogs + handover). Archived at `07274c1` — `.sdd/archive/replacement-merge-one-table/`
  (`sdd.yaml`: `archived`). Gates quoted: PHPUnit 336/336 (1849 assertions, baseline 325/1729),
  pint, PHPStan 0, `node --check`.
- The three residuals were closed: `3d61cef` + `e2c2d7a` (the `ui-regression` selectors and a
  real session for that file), the completed-status sibling fixture, and the user's approval of
  the invented tooltip sentence (`26a5fa6`).
  The log is `review-log.md`; the working list is `tasks.md` (five commits, 24 tasks, every
  receipt a number).
- The reviews earned their keep: they caught the replacement **time** being read from the first
  30-minute cell instead of the whole booking, a dash printed for cancelled/rejected requests that
  *did* have a slot, the no-request row (the majority) losing its badge and sentence, the page
  having **no name anywhere**, and a URL check whose only green was editing the frozen notification
  files.
- **A live bug was fixed early** (`9939d08`, user chose "now" over "with the merge"): the entry
  guard toast printed the page name twice ("… in My Request History. My Request History"). The body
  is now one short sentence with the state word in caps, and the merge's commit 3 (`ab65f9a`) then
  renamed the link to **My Replacements** → `/my-replacements` (`ui-common.js:4457`, `:4460`).
- Line references in `design.md` were **re-measured** (`335f454`) after another session moved the
  mapper's timezone handling into `App\Support\LocalTime`: everything from `effectiveStatus()`
  onward moved up **2** lines. `review-log.md` keeps its own numbers as the snapshot each round saw.
- Housekeeping the same day: the prompt folders settled (`21c258d` — toolkit / archive / open
  tasks), the ugly-toast screenshot gitignored (`1f92c1d`).

**Later on 2026-11-03 — the merged replacement page (docs only, nothing pushed):**
- The user finalised the **one-table** design: the page is **My Replacements** at the new
  canonical URL **`/my-replacements`** (one row per class occurrence, seven columns, status
  in the tab label and as a badge, no HISTORY block, icon-only actions, five cards). Both
  `/replacement-home-ui` and `/my-request-history-ui` stay as aliases. Full record: `.sdd/archive/replacement-merge-one-table/explore-brief.md`.
- **Static prototype** (discussion instrument, never staged, outside the repo):
  `../ui-prototype/replacement-merged-preview.html` — loads the live `theme.css` from
  `:8000`, so the dev server must be running to see real colours.
- **Proposal frozen after 4 review rounds** (`ccaa7ba`, log in
  `.sdd/archive/replacement-merge-one-table/review-log.md`): round 1 failed on the
  cancel-path owner and an unsourced test inventory; round 3 failed on three unbucketed
  test files; round 4 passed. Next batch: `design.md`.
- **New implementing brief:** `prompts/pi-replacement-merge-one-table.md` — the merge lane's
  PI prompt, written from the frozen proposal.
- Reversals recorded the same day, so they are not re-done: the page says **Course** (a
  `Subject` rename lasted an hour), **`Exclude COMPLETED Status`**, cohorts stack **one per
  line**, and the toolbar count hides **below 1500 px**.

**Landed earlier today (docs only, nothing pushed):**
- Ruling **14** added below: a public-holiday class can never be cancelled.
- Two implementing briefs committed, in dependency order:
  1. `prompts/pi-replacement-home-status-badges.md` — `0b11d36`. Keep a pending/approved
     row on the Replacement Home and swap its Arrange button for a clickable status
     badge that jumps to My Requests.
  2. `prompts/pi-request-completed-status.md` — `850db2e`. Derive **Completed** for an
     approved request whose replacement day has passed; hide completed rows by
     default on all three pages. **Must not start before lane 1 is merged.**
- This handover was restructured to six sections; the addenda moved to `history/`.
- **The lab booking roster now matches the timetable** (user ruling, SDD waived
  by the user, decision recorded in `docs/venue-restrictions.md` R3): BMIT2013
  plus the other eight subjects whose seeded L/T already sit in a lab joined
  both `lab_access_module_codes` and `b006_allowed_module_codes`. A new seed
  invariant (`TimetableSeedInvariantsTest::test_t8_…`) fails if a future import
  drifts from the roster.

**In flight, other session:** `.sdd/changes/notif-recipient-rulings/` (proposal frozen
and approved with two refinements; batch-2 design). Product code untouched as of this
date, and its files are dirty in the tree — see §3.

**Parked (do not start unless the user names it):**
- The four timeline rails — `prompts/extra tasks/task-02-replacement-history-timeline.md`
  (a visible placeholder note prints above each rail, `b58c884`).
- The Chapter 5 browser-evidence run (`prompts/ch5-playwright-evidence-8030.md`);
  Phase 1 (`CRS_BASE`) landed in `2f3b0be`, the run itself is not done.
- BUG-01/02/03/05/06 verdicts; the footer lane; the `receipt-target-guard` test lane.
- The viva demo data decision (both DBs are thin, not the old populated story).

---

### 2026-11-05 (notifications panel) — **`notif-inactive-hide-sender`** (the inline brief)

- An **inactive row** now carries its own **Hide** in `.nr-right` (the shelf's `Unhide` shape, the
  same `hideOne()` gate); active rows never do. `App\Support\TitleCase::of()` projects the stored
  sentence at render (minor words stay low; `ID`, `#28`, acronyms untouched) — 15 unit cases.
- **Sender line**: `sender_self` / `sender_line` computed per render from the R5 maps (proposer for
  `submitted`/`awaiting`, approver for decisions, the class lecturer for class rows — no extra
  query). The subtitle reads **`by Surayaini Binti Basri (5425)`** / **`by YOU`**, and the two
  self-owned kinds take **Your** in the title (*Your Scheduled Class Cancelled*, *Your Replacement
  Request Submitted*) — recognised by type **or** the stored sentence, because the emitters write
  both as `update`.
- **Zero unread** no longer reshapes the header: the pill reads **0** and Mark-all stays visible but
  `disabled`. The bell badge keeps its own rule (hidden at zero).
- Receipts: guarded `tests/notif-row-inactive-hide.spec.ts` (5 passed), `NotificationsBridgeTest`
  28/28 with the four-state sender contract rewritten, full suite **359 / 1914**. Trap worth
  remembering: several notification specs in ONE Playwright process can trip Fortify's 5/min login
  limiter — the new spec uses the setup's `storageState` instead of logging in.

### 2026-11-05 (venue slot validation + archive pass)

- **`c5dd0c4`** — archived the seven finished lanes (`mytimetable-jump-highlight`,
  `notif-click-behaviour`, `receipt-target-guard`, `replacement-home-status-badges`,
  `request-completed-status`, `notif-recipient-rulings`, `ui-footer-global`). `.sdd/changes/`
  40 → 33, archive 94 → 101.
- **`daf11c6`** — `venue-timetable` bookable cells: honest "no room" state, class-driven late
  cells, Earliest-bookable button + chip, the invented "2-hour gap" number deleted.
- **`bee6176`** (`venue-slot-validation`, SDD lane applied) — the user's follow-up report: with a
  14:00 class, a free 13:00 cell still printed "no classes can run here" *before* a class was
  picked, because the dropdown's default subject is a 2-hour lecture. The page now splits the two
  questions:
  **hover = one clear hour** (`MIN_BOOK_SLOTS = 2`, no class assumption; the `minClassSlots`
  plumbing is deleted), **after the class is picked = does THIS class fit?**
  (`confirmIntentSession` gate → `#slotWarningOverlay`, no navigation). The pop-up lists every
  session with `data-slots` / `data-fit` and pre-selects the first fitting row.
- **`913419e`** `test:` — `venue-cross-week-handoff` S1 pinned Tue 03 Nov 2026, which had turned
  read-only (`cell-too-soon`) as the clock passed it; the fixture now uses the last semester week
  (`pickSpanFreeCell`) with the same assertions.
- New standing rulings: **the hover floor is one hour and is never derived from a class**; **a
  duration verdict is only allowed after the class is picked**; **a page function used by an
  inline `onclick=` in a shared partial must be exported on `window`** (the page script runs
  inside a `DOMContentLoaded` closure).

## 2. The user's standing rulings (do not change without approval)

| # | Ruling |
|---|---|
| 1 | **Notifications events — ALL FIVE** (corrected 2026-11-04 to match R1–R4, `notif-recipient-rulings`): submit→PLs + one confirmation row for a **non-PL** proposer (a PL proposer gets only their queue row); approve→proposer (**one merged** `Replacement class updated` row, R3) + cohort students; reject→proposer; class-cancelled→**owner + cohort students only** — the department-PL fan-out was **REVERSED** by R4 (2026-11-03), so the old `notifyDeptPls` wording here is dead; pending reminder→PLs (24 h, deduped until read, re-armed daily) — **designed but DORMANT**: `notifications:remind-pending` is not scheduled (user decision 2026-11-04). |
| 2 | **Real-time = Livewire `wire:poll` (option A)** — NOT Reverb/websockets. Poll 10 s. |
| 3 | **Notification lifecycle**: rows never deleted; an inactive target renders the row **disabled** (no href) + tooltip `This notification is inactive because the request was cancelled.` |
| 4 | **Email/S**: SV approved **Gmail SMTP** ("CAN") for demo/testing with the user's personal Gmail; NFR 7.2 may be revised by the USER (their own doc edit). The SMTP flip = an ops step outside code lanes. |
| 5 | **mock-data.js the FILE STAYS** (retire consumers lane by lane; dormant rows = spec anchors). |
| 6 | **B005 no-Diploma REPEALED** (b005-diploma-allowed) — Diploma cohorts may use B005; AMIT2034 renders clean. |
| 7 | **B006 networking priority** still enforced; P→labs rule. |
| 8 | **Receipts strategy** = disposable test-DB suite + hand-run demo seed SQL (never agent-run booking flows on the demo DB). |
| 9 | **ONE feed on every page** (supersedes the category-aware caught-up hotfix — `notif-unified-feed` retired the per-page tabs 2026-10-29): all staff pages show the same recipient-wide feed, newest first, and the caught-up view is always the single line "You're all caught up". The pill/badge = the **actionable-unread** total (D5: `read_at` null AND not inactive), computed per render, never moved by the view filters. |
| 10 | **No bell on the replacement-arrangement page** = ACCEPTED BY DESIGN (BUG-04). |
| 11 | **`?week=` never moves the week selector** (ARRIVAL-GRID-NO-PIN) = WONTFIX (user decision). |
| 12 | **Demo story** (viva ~week of 7 Dec 2026, UNCONFIRMED, on this MSI machine): timetables + booking + cancel class + approve + bells + emails; auth = verbal only; students demo the "replacement class got updated". |
| 13 | **THE pageLivewire() LAW (the hotfix)**: page actions use `pageLivewire()`, NEVER `Livewire.first()` (the bell islands render first). |
| 14 | **A class that falls on a public holiday can NEVER be cancelled** (user-confirmed 2026-11-03 — the correct behaviour, not a gap). Rule **S4** rejects `day.holiday` in the shared guard `Cancellation.isCancellable()` (`public/js/ui-common.js` — grep the function name; the line numbers drift every lane), enforced at the button, the confirm path and the stale-button sweep. Browser proof: `tests/cancel-class.spec.ts` **S4** (the block's modal opens on the Deepavali in-lieu Monday but offers no cancel action). Do **not** "restore" the missing button; a holiday class is handled by arrangement, not cancellation. |
| 15 | **Cancel Class is FINAL** (user 2026-11-05: *"i give up all UNDONE function"*). No undo button, no undo callback, no server undo route — removing them was the fix, not a regression. Every final-decision confirm modal (Approve, Reject, Cancel Request, Cancel Class) shows *"This action cannot be UNDONE."* Reversible actions get nothing. |
| 16 | **Toast order**: a `?blocked=request-pending\|request-approved` arrival shows the ENTRY-GUARD message first, and any pending cancellation reminder is deferred (neither shown nor spent) to the next page load. The cancellation reminder is **one-shot** — one reminder per cancellation, never one per load. |
| 17 | **Stale notifications are marked inactive AT READ TIME**, never edited: `undone` (a request-less cancel notice whose `class_exceptions` row is gone) and `superseded` (a decision notice older than the newest request for the same class+week). Badge = D5 (actionable unread), so an inactive row leaves the badge but stays on the list. |
| 18 | **A slot is judged by the class the user picks, not by a guess** (user brief `Venue Timetable Slot Validation.md`, 2026-11-05). On `/venue-timetable-ui` the hover answers ONE question — *is there at least one clear hour?* (`MIN_BOOK_SLOTS = 2`; `cell-no-room` = "Not Enough Slot" when the next class starts inside that hour, `cell-no-fit` = past day end). It is **never** derived from the arrange pool (the old `minClassSlots` plumbing is deleted). The real duration is checked only AFTER a session is picked, in `confirmIntentSession()` → `#slotWarningOverlay` (span or day-end copy), which never navigates. The pop-up lists every session with `data-slots` / `data-fit` and pre-selects the first fitting row; no verdict is shown before the pick. |
| 19 | **The notification row speaks plainly** (inline brief, 2026-11-05): an inactive row can be hidden from the feed (Hide in `.nr-right`); every title reads **Title Case** at render (the stored sentence is never rewritten); the subtitle is the **sender** line — `by YOU` when the reader is the actor, else `by Name (staff_id)` — and a self-owned cancellation/submission says **Your** in the title; at zero unread the pill reads **0** and Mark-all stays visible but **disabled** (the bell badge still hides at zero). |
| 20 | **A pending request has a 3-working-hour window — and it is the SYSTEM that closes it** (user brief `Auto-Reject Pending Requests.md`, 2026-11-06). Working time = TARUMT Sabah hours, Mon–Fri 08:30–17:30, weekends and the semester's `holidays` excluded; the boundary itself rejects (`gap ≤ 180` minutes), anything further out stays Pending, and no other status is ever considered. **`App\Support\WorkingHours` is the only clock** — the deadline a tooltip promises and the moment the sweep compares are one computation, and neither may hardcode its own hours. The sweep is `php artisan requests:auto-reject` (scheduled 5 min, `--dry-run` safe) — **never** triggered from a page render (that would be a write-on-GET against the demo DB). An auto-rejected row keeps `approver_id = NULL` and is audited with `details.auto = true`, because no human decided. The Pending badge's `data-tip` always names the deadline (both pages, one vocabulary). |
| 21 | **The notifications panel opens on "Active only"** (user, 2026-11-05): `activeOnly` defaults ON beside `unreadOnly`, so the first view is actionable unread and the decided rows are one toggle away. A **disabled control is visible but inert** and shows `cursor: not-allowed` everywhere (one rule for `button`/`.btn`/`input`/`select`/`textarea`, no `pointer-events: none` so a disabled icon keeps its tooltip); an ID selector that sets `cursor: pointer` must restate `:disabled` itself. |
| 24 | **A cancelled request is displayed as "no active request"** (user brief 2026-11-06). On My Replacements an owner-cancelled occurrence shows **Need to Arrange**, an em dash in REQUESTED REPLACEMENT and no Proposed Replacement Schedule tab — one normalisation in `buildRow()` (status → null, replacement strings blanked), never three separate view patches. The RECORD is not rewritten: `cancelled`, `decided_at`, audit rows, released slots and the notification stay, and the history/notification surfaces still read *Cancelled by YOU*. `cancelled` means "the owner did it" because `CancelPendingRequest` is the only writer and it is ownership-checked. Other statuses are untouched. **Follow-up (`remove that option`):** the status filter no longer offers **Cancelled** — nothing on the page can carry that state — and a filter saved before the removal is mapped onto Need to Arrange when restored. |
| 23 | **A JS-owned control must survive a Livewire round-trip** (2026-11-06). Anything the server renders EMPTY but JavaScript fills (the week selects, `#filterChips`, table bodies) is deleted by the morph. The week selects are guarded centrally (`withWeekLivewireGuard()` around `pageLivewire().call()`; restore synchronously, never in a `setTimeout` — the caller's `.then()` reads the value first). Prefer that over `wire:ignore`, which would also freeze server-driven attributes. |
| 22 | **A rule the page enforces must also exist in the action** (2026-11-06). FR 2.17's 3-working-day notice was JavaScript-only; `SubmitReplacementRequest` now refuses `slot_too_soon` on the same `WorkingHours` calendar, and the failure copy reuses the grid's own sentence. When you add or touch a booking rule: action first, then the page, then one test — never the browser alone. |

---

## 3. Safety laws, traps, and ways of working

**Database**
- Never `migrate:fresh` / `migrate:refresh` / `db:wipe` / reseed a demo DB. Any demo
  write is **user-gated per write**, snapshot-first (`pg_dump -Fc`, `pg_restore --list`
  must pass, dumps in `backups/`), and logged in `docs/demo-db-data-changes.md`.
- **⛔ `--env=testing`**: no `.env.testing` exists, so it silently boots the *demo* DB.
  Select the testing database only with `DB_DATABASE=class_replacement_testing`.
- **Schema migrations on a demo DB are additive only** — add a nullable column or an
  index; never `change`/`drop`/data statements. Snapshot → rehearse on the testing DB →
  print the target → plain `migrate` → row-count receipt + log.
- **Never stage `docs/demo-db-data-changes.md`** — it is another session's write log,
  unless that log *is* the task.

**Destructive tests**
- **⛔ TC70**: `tests/venue-timetable.spec.ts:690–730` must never execute until it is
  retired — it writes against the demo DB. Run that suite with
  `--grep-invert "TC70"`.
- **The write-guard is live (2026-11-05).** Every writing spec is fail-closed through
  `tests/helpers/receipt-target-guard.ts`: `abortUnlessTestingTarget(spec, { requireAllowSql })` as
  the first statement after the imports (aborts at COLLECTION — no login, no SQL), or
  `throwUnlessTestingTarget(name)` on the first line of one dangerous test. Exact strings only:
  `CRS_MODE=testing` + `CRS_BASE=http://127.0.0.1:8030` (+ `CRS_ALLOW_SQL=1` for SQL fixtures).
  There are no defaults and no soft skips any more — a bare run is a loud exit-1 refusal that
  prints the blessed command. Blessed form:
  `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test <spec>`,
  after `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`.
  Never loosen the guard to make a run pass; re-seed between write-receipt runs.
- **Inline `onclick=` + a page script inside a `DOMContentLoaded` closure** = the markup cannot see
  the function unless it is exported (`window.closeSlotWarning = closeSlotWarning;`). The symptom is
  a modal that opens and then throws *"… is not defined"* when you close it — that is what the venue
  slot warning did for one round (2026-11-05). Check the `window.` export block at the end of the
  page's script before blaming the markup.
- **Browser fixtures rot with the clock.** `venue-cross-week-handoff` S1 pinned *Tue 03 Nov 2026*;
  once that date passed, the whole week rendered read-only (`cell-too-soon`) and the spec could not
  even click. Date-independent fixtures pick the LAST semester week instead (`913419e`). When a
  spec fails on a click that used to work, check the calendar before checking the code.
- **`data-tip` is TEXT, not HTML.** `initDataTipTooltips()` (ui-common.js) writes `textContent` and
  only adds `pre-line` when the string contains a real newline — so a brief that says `<br>` must be
  rendered as `"line one\nline two"`, or the tag prints literally (auto-reject deadline, 2026-11-06).
  Keep tooltip copy in ONE server-side source (`ReplacementRequestMapper::statusTip()`), never a
  page-local copy.
- **Per-row additions must not add a junction read.** The merged page pins "the junction is read
  exactly once per render, whatever the request count" (`MyReplacementsPayloadTest` case 8). A new
  per-row value rides data that is already resolved (the auto-reject deadline uses the row's own
  `replacementDate`/`replacementTime` strings), never a fresh `span()` call inside `buildRow()`.
- **A date-dependent gate makes old fixtures illegal.** FR 2.17's server leg (2026-11-06) broke five
  suites at once because their fixtures submitted to weeks that are now inside/behind the notice
  window. The cure is `tests/TestCase::bookablePairs()` for scanning fixtures (date-derived, cannot
  rot), a `now()`-relative semester for self-contained fixtures, and `Carbon::setTestNow()` for suites
  whose weeks are fixed — never a widened gate. A new calendar rule means auditing every fixture that
  submits.
- **Fortify's login limiter is 5/min.** Multi-context specs plus a few manual logins in the same
  minute produce a login that "does nothing" (no redirect). Wait a minute before concluding a spec
  is broken.

**Runtime**
- **No queue worker is running.** Queued mailables pile up in `jobs`. The dev repo is
  on the `log` mailer (harmless); the viva repo is on `smtp` and holds **27 stale jobs**
  that must be purged (gated write) before the first worker run. `failed_jobs` was 0.
- After any Blade/PHP edit: `scripts/dev-serve.sh 8000` (never combine `pkill` and the
  serve start in one shell string). The `:8001` twin restarts separately:
  `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`.
- The dev `.env` Gmail App Password was exposed in a chat paste on 2026-11-03; the user
  declined rotation. Rotate if they ever ask.

**Livewire + JS**
- A page component's re-render morphs the DOM from the server's HTML: JS-filled containers
  (`#weekFilter` options, `#filterChips`, `#tableBody`) come back EMPTY unless guarded. The week
  selects are snapshot+restored around `pageLivewire().call()` (2026-11-06). If a restore has to land
  before a caller's `.then()`, do it inside the wrapper — `setTimeout` runs after the `.then()`.
- Spec caution: a test that only asserts "nothing broke" can pass because the action never ran. Assert
  the action's own receipt (toast, row left/status flip) too — that is how the Cancel case was caught.

**Receipts and honesty**
- No claim without a receipt. Fresh in-flow login (staff `input[name="login_id"]`),
  screenshots + DOM snippets. Distinguish CONFIRMED from SUSPECTED.
- Playwright gotchas: run `.cjs` helpers from the repo cwd (`package.json` is
  `"type": "module"`); avoid `ctx` name collisions; `:visible` is not a CSS selector;
  add `?_=` cache-busters.
- A full-suite number is **PHPUnit only** (386/2053 at 2026-11-06). The data-dependent specs (request-approval,
  warning-keywords) must be re-run after any data change.

**SDD**
- Every code change runs proposal → reviewer rounds (max 5) → freeze → apply → verify →
  archive. Small fixes may combine proposal+design+tasks in one batch.
- Reviewer: the `sdd-reviewer` subagent. The legacy CLI recipe (`pi --model …glm-5.3-flash`)
  is in git history, not needed.
- Frozen artifacts take declarative-only edits; a decision-level change unfreezes the
  chain. Archive only on the user's word.
- Known-exception lanes that shipped without `.sdd/changes/` are recorded in the lane's
  changelog; do not invent retroactive SDD dirs.

**How to talk to this user**
- Simple everyday English, B1, no jargon dumps. Decisions as options with their main
  risks and benefits plus a recommendation. **One clear question at a time**, phrased
  "So the question in plain terms: …". Never start an approval-gated action before a yes.
- Conventional commits (`feat:`/`fix:`/`ui:`/`oop:`/`refactor:`/`style:`/`docs:`/`test:`),
  one lane per commit series; commit only the files the task touches.

---

## 4. Where the truth lives

| Question | Look here |
|---|---|
| Architecture, FR/NFR, conventions, git policy | `CodingMAIN.md` (the spec) |
| Quick coding rules, UI colour canon | `my-coding-rules.md` |
| The requirements as the code sees them | `FR&NFR copy.md` (49 FR / 26 NFR, `v2026-11-02`; read-only without approval) |
| What changed on a page, with dates | `page-changelogs/<page>-changelog.md` |
| Lane history, design decisions, review logs | `.sdd/changes/` and `.sdd/archive/` |
| Reusable prompt **templates** (paste-ready; no project state) | `prompts/general&reusable prompt references/` — `PI-PROMPT-TEMPLATE.md`, the two propose templates, the UI-page spec, the context-transfer prompt |
| Demo-DB writes, restores, snapshots | `docs/demo-db-data-changes.md`, `backups/README.md` |
| Viva runbook, queue/mail | `docs/viva-demo-runbook.md`, `docs/queue-and-emails.md` · **what to code next** → `docs/coding-todo.md` |
| **The lanes just applied** | `venue-slot-validation` — `.sdd/changes/venue-slot-validation/` (status `applied` 2026-11-05; the venue hover/gate split, receipt in `review-log.md`). `receipt-target-guard` — same folder (status `applied`; one leg open: `T3d`). Merge lane: `.sdd/archive/replacement-merge-one-table/` (archived `07274c1`). |
| **The venue-page slot rules** | `resources/views/livewire/venue-timetable.blade.php` (`MIN_BOOK_SLOTS`, `spanBlocked()`, `nextEventStartAfter()`, `refreshSessions()`, `confirmIntentSession()`, `showSlotWarning()`), `resources/views/partials/ui-intent-session-modal.blade.php` (`#slotWarningOverlay`), `app/Livewire/VenueTimetable.php` (`bookableCandidates()`, `intentSessionOptions()`) |
| **The week-select guard** | `public/js/ui-common.js` — `snapshotWeekSelects()`, `restoreWeekSelects()`, `withWeekLivewireGuard()` (used by `pageLivewire()`), the `commit`-hook net; receipt `tests/week-filter-preserved.spec.ts` |
| **The auto-reject rule** | `app/Support/WorkingHours.php` (the office-hours clock: `gapMinutes()`, `subtractWorkingMinutes()`, `holidays()`, `MIN_GAP_MINUTES = 180`), `app/Actions/AutoRejectRequest.php` (the release + the system stamp, `approver_id` NULL), `app/Services/PendingRequestExpiry.php` (the sweep), `app/Console/Commands/AutoRejectPendingRequests.php` (`requests:auto-reject --dry-run`, scheduled in `routes/console.php`); the deadline rides `ReplacementRequestMapper::approvalDeadlineLabel()` + `statusTip()` |
| FR 2.17 / the notice gate | `app/Actions/SubmitReplacementRequest.php` (the `slot_too_soon` and `holiday` gates, both right after the anchor lookup), `app/Support/WorkingHours.php` (`noticeCutoff()`, `isBookableOnDate()`), `app/Livewire/ReplacementArrangement.php` (`conflictText` arm), `public/js/ui-common.js` (`leadTimeCutoff`, `isSlotTooSoon`, `renderLeadTimeNote`), `tests/Feature/LeadTimeGateTest.php` |
| Cancel Class + the inbox rules | `app/Livewire/NavNotifications.php` (`exceptionMap()`, the two read-time classifiers), `resources/views/partials/notif-row.blade.php` (`$rowTip`), `public/js/ui-common.js` (`showCancelToast`, `needsLoadToast`, `markLoadToastShown`, `CancelClassModal`) |
| Lanes already landed | `prompts/pi-replacement-home-status-badges.md`, `prompts/pi-request-completed-status.md` |
| The approved picture of the merged page | `../ui-prototype/replacement-merged-preview.html` (outside the repo, never staged) |
| Superseded session diaries | `history/2026-10-31-to-2026-11-03-addenda.md` |

---

## 5. Open tasks (state at 2026-11-06)

1. **[USER] Push** — 18 commits sit on `fedora-backend` past `39b2bb7` (the cancelled-request display change is newest, 2026-11-06).
   Nothing is pushed by an agent, ever.
2. ~~**[USER, waiting on the archive word]** Seven finished lanes~~ — **ARCHIVED 2026-11-05**:
   `mytimetable-jump-highlight`, `notif-click-behaviour`, `receipt-target-guard`,
   `replacement-home-status-badges`, `request-completed-status`, `notif-recipient-rulings`,
   `ui-footer-global` now live in `.sdd/archive/` with `status: archived` + `archived: 2026-11-05`.
   **Still open:** whether `.sdd/archive/README.md` should exist at all, the ~33 older finished
   lane dirs left in `.sdd/changes/`, and `venue-slot-validation` itself (applied 2026-11-05,
   archive on the user's word).
3. ~~**[AGENT, ready on the word]** `prompts/request-approval-ui CONSISTENCY.md` **part 2**~~
   **SHIPPED 2026-11-04** (1f, 3B, 3C, 3A, 3D, 1h, 3E-3, §4 — `page-changelogs/request-approval-changelog.md`
   + the `ui(request-approval)` commits through `fc217a5`). The brief is archived as of 2026-11-05
   (`prompts/completed prompt (Archieve)/`). Nothing left here.
4. **[AGENT, low]** `tests/venue-book.spec.ts` **U2a-T3** is flaky on the hover tooltip
   (`#availableTooltip.show #tooltipBookBtn` never becomes visible in a 5 s window). It fails the same
   way on unmodified HEAD (18/19), so it is a pre-existing hover-race, not a regression — worth a
   timeboxed look when the demo drills are re-run.
5. **[AGENT]** `receipt-target-guard` `T3d` — `tests/submission-conflict-refresh.spec.ts` is **not
   green** and never has been recorded green: it fails inside its own staged-arrival flow (a login
   redirect timeout, then `#weekSelector` absent on the landed page). Decide: diagnose it timeboxed,
   or quarantine it as `test.fixme()` with the reason recorded. The guard itself is unaffected
   (it refuses at collection, independently).
6. **[AGENT, queued]** The "Just cancelled" chip TTL prompt had no committed file last checked —
   re-create it if the user still wants the 30-minute display-only TTL.
7. **[USER]** Confirm the viva date (anchored: week of 7 Dec 2026) and decide the viva demo data —
   both DBs are thin; a populated story needs user-gated SQL, snapshot-first, logged.
8. **[USER/OPS]** Purge the viva repo's 27 stale jobs before any worker run.
8a. ~~**[AGENT, needs the word]** the submit action's missing holiday check~~ **CLOSED 2026-11-06**
   (`40ffb5f`): D2's landing ban is in `SubmitReplacementRequest` (`holiday`), test file
   `tests/Feature/SubmitGatesTest.php`.
8b. **[USER, decision]** The auto-reject sweep only runs from `php artisan requests:auto-reject`
   (scheduled every 5 min). Nothing runs the scheduler in dev, so a demo must run the command by
   hand. If the user wants the rule to bite WITHOUT that step, the honest option is to also sweep
   once at SUBMIT time (a POST, never a page render) — that needs their word, because it changes the
   submit flow to "your request was accepted and immediately rejected" when the slot is too soon.
9. **[AGENT, demo week]** Chapter 5 browser-evidence run and the report; NFR benchmarks; the UAT
   script (the coding side of these is tracked in `docs/coding-todo.md`).
10. **[LOW]** `?week=` deep links still land on the remembered week (accepted); three reference
    templates under `resources/views/ui-design-templates/` still carry `Exclude Completed` and a
    `ClassCancellation.undo(entry)` call — they are design references, not loaded pages.
