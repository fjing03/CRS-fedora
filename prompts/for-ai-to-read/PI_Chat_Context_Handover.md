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
| Branch / remotes | `fedora-backend` → `origin` = `fjing03/CRS-fedora.git` (**72 commits ahead**, see Push state); `upstream` = `FjingXR/class-replacement-system` (**pull-only**) |
| Push policy | **NEVER `git push`** — the user pushes. One commanded exception, 2026-11-02; afterwards the rule resumed. |
| Push state | `origin/fedora-backend` sits at **`39b2bb7`**; **72** commits are unpushed (2026-11-07 night, newest `ecec7f0`). Re-check: `git rev-list --count origin/fedora-backend..HEAD`. The user pushes. |
| AI context layout | Root **`AGENTS.md`** is a bootstrap pointer → this folder holds `AGENTS.md`, `CodingMAIN.md` (the spec), `my-coding-rules.md` (quick rules), `FR&NFR copy.md` (read-only without the user's approval), this file, and `history/`. |
| Servers | `:8000` = `CRS-fedora-backend` / **`class_replacement_fresh`** (dev). `:8001` = `../CRS-demo` / **`class_replacement_viva`** (viva twin, `APP_DEBUG=false`). `:8030` = disposable AI/E2E lane on `class_replacement_testing` only. |
| Database trap | `class_replacement_demo` is the **older, empty** twin (0 requests) — not the viva DB. |
| Demo data (2026-11-07 evening, measured) | `class_replacement_fresh`: **14 requests** — 24 rejected, 25 cancelled, 26 + 27 approved, 28 pending, 30–34 cancelled, 35 pending, 36 cancelled, 37 + 38 **rejected** (37/38 = the user's own duration reports); **110 notifications** (6 hidden), **3** `class_exceptions`, 10 slot rows. Requests 33–38 carry today's date (2026-11-07); **37 and 38 are the user's own duration reports** (submit → reject, 2-hour proposals). `class_replacement_demo` stays the pristine deck: **0 requests** / 0 notifications. Both DBs now carry `replacement_requests.replacement_span` (the additive 2026-11-07 migration); `fresh` has all 14 rows filled (2:x5, 4:x9 after 37/38), `demo` has none to fill. Receipt: `docs/demo-db-data-changes.md`, 2026-11-07 row + the two `-pre-span-20261107-191721` dumps. |
| Test DB | `class_replacement_testing` — disposable; `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed` is fine **there only**. |
| Ops trap | `opcache.enable_cli=On` with `revalidate_freq=180` → a long-running `artisan serve` can serve a `.php` file's old bytecode for ~3 min. Use **`scripts/dev-serve.sh [port]`** (kills that port, clears views, starts with `revalidate_freq=0`). |
| Gates | `composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G` · full phpunit · `node --check` for JS. **Never** `composer run types:check` (crashes at 128M). |
| Suite | Last full PHPUnit run **422 tests / 2352 assertions** (2026-11-07 evening) — **419 green + 3 date-dependent errors** proven identical on unmodified HEAD (a Saturday run: their `today()+1 day` fixture becomes day 6/Sunday, which the `day_of_week 0–5` CHECK refuses; see §5 item 9a). Before that: **400 / 2137** (the arrangement-ownership lane) and **394 / 2093** (2026-11-06). Browser suites are separate and NOT part of that number — quote them by name: `venue-book` 19 (18 green + the known `U2a-T3` hover flake), venue neighbours 61 passed / 2 skipped, `cancel-class` 16 passed / 4 skipped, `status-tooltips-filter-chips` **10**, `replacement-home-working-days` **7**, `replacement-home-status-badges` 17 passed / 3 skipped (three replacement-home specs together), `notifications-island` 10, `notifications-island-testing-db` 6, `notif-row-inactive-hide` 5, `week-filter-preserved` 3, `request-cancel-pending` 1, `arrangement-slot-ownership` 6 (run it ALONE). |
| SDD lanes | **38** lane dirs under `.sdd/changes/` (measured 2026-11-07 night) — 33 older finished ones + `venue-slot-validation` and `notif-inactive-hide-sender` (applied 2026-11-05) + `arrangement-slot-ownership-legend`, `replacement-span-persistence` and `arrangement-cell-state-labels` (applied 2026-11-07) — **102** archived. Archive only on the user's word. |
| **Auto-reject rule (2026-11-06)** | A **pending** request whose proposed start is within — or exactly — **3 working hours** is rejected by the system. Working time = TARUMT Sabah hours, **Mon–Fri 08:30–17:30**, weekends + the semester's `holidays` excluded (`App\Support\WorkingHours`, `MIN_GAP_MINUTES = 180`). Write leg `App\Actions\AutoRejectRequest` (cancel-family release, `approver_id` NULL, audits `details.auto`); trigger `php artisan requests:auto-reject` (`--dry-run`) scheduled every 5 min — **never** a write-on-read. The Pending badge's `data-tip` states the deadline (proposed start − 3 working hours) on both pages; the **lecturer arm names the rule** since the 2026-11-06 UI update — *"Auto-Reject Rule: If This Request Has Not Been Approved At Least 3 Working Hours (…deadline…) Before The Proposed Class Start Time, It Will Be AUTOMATICALLY REJECTED."* (emphasis = capitalisation, a tip is textContent; the PL arm is unchanged). |
| **The notification tooltips speak one vocabulary (2026-11-06)** | Every live row's `data-tip` is composed by `App\Support\Notifications\NotificationTip` — labels / Subject / schedules / `Rejection Reason` / `Reason` / `Request ID`, holiday-first reason in the Original line's parentheses, `Automatically Rejected by System` when `approver_id IS NULL`, students without the Staff lines, legacy `title — body` only for an unknown kind. One extra batch (`holidays`); the `replacement_requests` one-query law holds. Receipts: `NotificationsBridgeTest` +8, `notifications-island.spec.ts` 10/10, demo hover `/tmp/opencode/notif-tip-hover.png`. |
| **A cancelled request displays as "Need to Arrange" (2026-11-06)** | A lecturer's own cancelled pending request is DISPLAYED as no active request on My Replacements: `badge-outline status-none` reading **Need to Arrange** (no-request tip), an em dash in REQUESTED REPLACEMENT, and a details pop-up with **no Proposed Replacement Schedule tab** (no Request ID/Week/Day/Date/Time/Duration/Venue). One normalisation in `ReplacementHome::buildRow()` — status→null + strings blanked. The RECORD is untouched (still `cancelled`, audited, slots released); history + notifications still say *Cancelled by YOU*. The status filter offers **no Cancelled option** (nothing on the page can carry that state); a filter saved before the removal is mapped onto Need to Arrange at restore. Receipts: `request-cancel-pending.spec.ts` (write), `replacement-home-status-badges.spec.ts` receipts 9 + 10. |
| **A JS-owned control survives a Livewire round-trip (2026-11-06)** | The week `<select>`s have no server-side options, so a page-component re-render used to morph them empty — Approve/Reject blanked the Week filter and its chip printed a bare `Week: `. Guard: `withWeekLivewireGuard()` in `ui-common.js` (snapshot before `pageLivewire().call()`, restore options + value **before the caller's `.then()`**), plus a `Livewire.hook('commit')` net. Receipt: `tests/week-filter-preserved.spec.ts` (U1 Approve, U2 Reject, U3 Cancel Pending). |
| **The submit action's own guards** | Since 2026-11-06 `SubmitReplacementRequest` enforces what only the page did: FR 2.17's 3-working-day notice (`slot_too_soon`, `WorkingHours::noticeCutoff()`) **and** D2's holiday landing ban (`holiday`). Sunday needs no arm (no Sunday slot rows). `tests/Feature/SubmitGatesTest.php` is the receipt file (10 cases). |
| **The arrangement grid colours by state, rings by owner (2026-11-06)** | Lane `arrangement-slot-ownership-legend`. A cell's class comes from ONE resolver (`classifyCell()`, exported on `window`) fed by BOTH arms: the venue payload's `status 1/3/4` and the busy vectors' **6th tuple key `mine`** (engine rows: lecturer vector `true`, cohort class rows `false`, cohort make-up rows `proposer_id === viewer` via the new optional `?int $viewerId`). Precedence: own pending → others' pending → own conflict → any block → too-soon/available. Amber = pending, red = conflict; **3px = yours, no border = others'** (0px replaces the cohort/venue 0.5px hairline). Twins (my class that also carries my cohort) are merged in `busyByWeekPayload()` with `mine = OR(twins)` so the tooltip keeps one block. Legend = six rows whose swatches carry the real cell classes + the hint `thick border (3px) = yours · no border = others'`; tips are TEXT (capitals = emphasis, `\n` = line break, never `<br>`). The bug it kills: the engine arm had no ownership test, so another staff member's pending painted "Pending (You)" whenever the selected subject shared that cohort (measured: request 28 / staff 5770 on B111 W9 in cohort 4's vector). Receipts: `arrangement-slot-ownership.spec.ts` 6/6 (run it ALONE — `week-filter-preserved` re-seeds `TestingFixtureSeeder`, which deletes every request), `MakeUpBusyVectorTest`, `ReplacementArrangementTest`, `UiLegendBarTest` 4/4. |
| **A day countdown counts WORKING days (2026-11-07)** | One rule for every countdown the app prints (My Replacements' Original Class cell + its details pop-up, and Request Approval's REQUESTED REPLACEMENT cell). Words: `Today` · `1 Working Day Left` · `N Working Days Left` · `Overdue (N Working Day(s) Ago)` — **`Today` is the only relative word** (no Yesterday/Tomorrow). Bands: past + today red (`--color-error`), **1–7 amber** (`--color-warning`), **8+ GREEN** (`--color-success` — the far band used to be grey; user law). Working days = Mon–Fri minus the timetable's own holiday rows; the walk is symmetric (a Friday read on a Saturday = `Overdue (1 Working Day Ago)`) and clamps ±1 when a calendar gap contains no working day. ONE holiday derivation for the whole page (`workingDayHolidayKeys()` — the cancel-class window reads it too, its private copy deleted, `8274ba0`). One band rule (`urgencyBandClass(days)`) and the `.urgency-*` colour rules live in **`theme.css`**, not a page. Placement: the class-date countdown rides the Original Class row everywhere; the **make-up** countdown rides the requested replacement date **only while the request is pending** (approved/completed print `—`). Receipts: `tests/replacement-home-working-days.spec.ts` 7/7, `replacement-home-status-badges` receipt 3 rewritten, `status-tooltips-filter-chips` T5.RA-4. |
| **A request keeps its own length (2026-11-07)** | A replacement slot is a **30-minute cell**, so a 1-hour proposal covers two and a 2-hour one four. The run lives in `replacement_request_slots`, and EVERY release action (`RejectRequest`, `CancelPendingRequest`, `AutoRejectRequest`) DELETEs those rows to free the cells — leaving the anchor (`replacement_time_slot_id`, the span's FIRST cell) as sole evidence. `replacement_requests.replacement_span` (additive nullable smallint, 2026-11-07) now stores the width at submit, and `ReplacementRequestMapper::spanFromAnchor($anchor, $span)` grows the end from the anchor's start (`span × 30` min); a null width keeps the old single-cell shape. The junction still wins when present, so approved rows are byte-identical. ONE reader — never a second copy of the anchor shape (the notification tip had one, `NavNotifications::$anchorSpan`, and told a 2-hour rejection `11:00 AM ~ 11:30 AM`; `602a585`). Backfill for old rows: `php artisan crs:backfill-replacement-span` (dry run default, `--commit`, `--force` on demo DBs; reads the audit log — the decision entry's `slots` list, else the `submitted` count). Receipts: `tests/Feature/ReplacementSpanTest.php` 8/8, `MyReplacementsPayloadTest` case 5, `NotificationsBridgeTest` +1. |
| **The arrangement cell says WHY it is blocked (2026-11-07)** | User brief *UI Update — Hour-Cell Hover Labels* (grilled 1A 2A 3A 4A 5B 6A). The hour cell's hover label names the reason, from DATA: `Your Pending` · `Other Pending` (ownership) · `Venue Conflict` (grid `status 1`) · `Cohorts Conflict` (a row whose 7th tuple key `arms` contains `cohort`) · `Your Conflict` (a non-pending row with `mine`) · `Your & Cohorts Conflict` (both); `Occupied` survives only as the fallback for a row naming no arm. ONE resolver `classifyState(facts)` → `{cls, label}`; `classifyCell()` keeps its old string contract (the ownership receipt's 12 verdicts pass untouched) and `cellStateLabel()` returns the words. The words are a real `<span class="cell-state-label">` in the cell (2 lines, 11 px, clipped inside) and the CSS `::after` overlay is switched off ONLY where that span exists (`:has()`), so venue-timetable is untouched. The engine's four range mappers now carry `arms` (`own`|`cohort`), and `dedupeBusyRanges()` UNIONS the arms of merged twins (a class that is mine AND the cohort's keeps both reasons). The legend is SIX rows — Available · Your Current Selection · Your Pending · Other Pending · Your Conflict · **Others Conflict** (the last groups every non-yours red, tip = the old two-line VENUE/COHORT CONFLICT copy; do not re-split). The cell's tooltip sits ABOVE the cell (`data-tip-pos="left"` pin removed) and its own-block tag reads `👤 Your conflict`. Receipts: `arrangement-slot-ownership.spec.ts` 7/7 (run it ALONE), `ReplacementArrangementTest` (7-key tuples + arm union), screenshots `/tmp/opencode/label-*.png`, `legend-6rows.png`, `tip-*.png`. |
| **FR 2.17 is server-side** | Since 2026-11-06 `App\Actions\SubmitReplacementRequest` refuses a slot inside the 3-working-day notice window (`slot_too_soon`) — the grid's `leadTimeCutoff()` was only the browser's word. Calendar: `WorkingHours::noticeCutoff()` / `isBookableOnDate()`, the same class as the auto-reject sweep, so grid "Bookable from …", the gate and the pending-deadline tooltip agree (measured 2026-11-06: Fri 6 Nov → **12 Nov** on both sides, 98/98 semester days). |
| **Venue page slot rules** | Since 2026-11-05: hover asks ONE question (**one clear hour**, `MIN_BOOK_SLOTS = 2`, never derived from a class), and a class's duration is judged only AFTER it is picked (Arrange gate → `#slotWarningOverlay`, no navigation). Lane `venue-slot-validation`; commits `daf11c6` + `bee6176`. |
| **The merge lane** | `replacement-merge-one-table` — **APPLIED 2026-11-04**, archived `07274c1`: `e70c1ea` (payload/mapper), `6856577` (view), `ab65f9a` (routes + retired page), `d3f6f5b` + `c4d0ad3` (specs). The merged page is **My Replacements** at `/my-replacements`, with `/replacement-home-ui` and `/my-request-history-ui` as alias route entries. |
| **Cancel Class** | **FINAL since 2026-11-05** — undo gone from the UI, the JS and the server (`23c627c`); the inbox marks stale notices inactive at read time instead of offering dead clicks (`7b7f0d9`); four final-decision modals carry *"This action cannot be UNDONE."* (`12c644a`). |
| **E2E write-guard** | Live and fail-closed: `tests/helpers/receipt-target-guard.ts` + the three guarded specs (`b924552`, lane `receipt-target-guard` applied `7bbce9f`). Its one unverified receipt is `T3d` — the submission-conflict suite is not green; see §5. |
| **Date & time format (2026-11-07)** | One standard, shipped in batches 1–4 (`10a51b0`, `5120a98`, `f420215`, `1dc3edb`): date `5 Nov 2026` (no leading zero, day first), time `2:00 PM`, range `2:00 PM ~ 4:00 PM` (`~` joiner; hyphen acceptable but never mixed in one element; `–` is not a joiner). Day word `Thu` in compact lines / `Thursday` in prose. 24-hour grid axis and short notification tips stay. Emails carry the full date + 12-hour range. Full audit: `docs/date-time-format-audit-2026-11-07.md`; only #30 (API chip) remains, deferred. |
| Language | **B1-level English only** — no other language, ever. |
| Approval rule | Investigate first. **No code edit without the user's explicit per-task approval.** |

---

## 1. Latest sessions — 2026-11-03 → 2026-11-07

### 2026-11-07 (night) — the arrangement cell names its reason; the tip moves above

Three follow-ups on one page, each grilled/decided by the user the same night.

**1. The hover label says WHY (SDD `arrangement-cell-state-labels`).** `Occupied` / `Pending` /
`Reserved` became `Venue Conflict`, `Cohorts Conflict`, `Your Conflict`, `Your & Cohorts Conflict`,
`Your Pending`, `Other Pending`. Answers that shaped it: **1A** the reason must be DATA (each busy
row gained a 7th tuple key `arms`, and `dedupeBusyRanges()` unions the arms of twins — the tooltip's
`🏛/👥/👤` tags still parse the holder text, the cell does not); **2A** a fourth red word for
"your own class only", which the brief omitted; **3A** a real `<span class="cell-state-label">`
(so `<br>` works) with the old `::after` overlay retired via `:has()`; **4A** 11 px, two lines,
clipped inside the cell — measured unclipped at 390/768/1024/1440 px. One resolver
`classifyState()`; `classifyCell()` unchanged for the ownership receipt.

**2. The legend (5B, then the user's own revision).** First eight rows; the user then kept five and
added **Others Conflict** (the previous row's wording) — six rows, the non-yours red family grouped,
the CELL still naming the reason.

**3. Two wording/placement asks.** The cell tip now sits ABOVE the cell (`data-tip-pos="left"` pin
removed — it used to lay a multi-line block over the day columns; the engine flips it below only on
the first row), and the tip's own-block tag reads `👤 Your conflict`, not `👤 Own conflict`.

Receipts: `arrangement-slot-ownership.spec.ts` **7/7** (six labels from crafted facts with real
tuple shapes; real hovers on an own pending / another's pending / an own-class cell asserting the
text, the box inside the cell, no clipping, `::after` = none, the tip ABOVE, the tag wording and an
untouched `data-tip`; a grid-wide sweep; the six-row legend), `ReplacementArrangementTest` 7-key
tuples + arm union. Full suite 423 tests: 420 green + the 3 Saturday-only fixtures. Commits
`358ad2a`, `e6e4866`, `4ecfe48`, `ecec7f0` (plus `a0b7790` for the earlier docs sync).

### 2026-11-07 (later) — working-day countdowns, the PL Urgency column removed, and a request that keeps its length

Four user reports, all shipped the same evening. Commits: `1ae378b`, `9ff4813`, `fa50a95`,
`8274ba0`, `efeea89`, `705637a`, `7c9cef6`, `c24d72d`, `602a585`.

**1. The class-date countdown now counts WORKING days** (`1ae378b`). The words, the bands
(red / amber 1–7 / **green 8+**, the far band was grey) and the placement are in §0 and
§2 #27. New `ui-common.js` helpers: `workingDayHolidayKeys()`, `workingDayKey()` (local
`Y-m-d`, never `toISOString`), `workingDayParse()`, `isWorkingDayDate()`,
`workingDaysBetween()`, `workingDaysDiff()`. **A typo caught in the brief:** it said
`urgency-med`; the page's real class is `urgency-mid` (the first amber render had no rule
at all — no colour, no nowrap). The new spec asserts both bands resolve, so a typo cannot
land silently again.

**2. Request Approval lost its Urgency column entirely** (`9ff4813`): badge, sort arm,
`All/Urgent/Normal` chips, the active-filter chip, row stripes, the phone-card line and the
compare-card badge are gone; the age pill stays (`5 days ago · 4+ days old`, no urgency
claim). Mistake caught and fixed in the same commit: the first cut also deleted
`compactCohort()`/`venueText()`, so the queue rendered no rows — both restored.

**3. Two countdown placements** (`fa50a95`, `efeea89`): the row pop-up carries the same
number as the table, and a PENDING request prints its make-up countdown beside the
requested date **on both pages** (`HtmlBuilder.replacementBlock()` gained an opt-in
`pendingCountdown`; only Request Approval passes it). The `.urgency-*` rules moved from
My Replacements' page `<style>` into `theme.css` and the band choice became the shared
`urgencyBandClass()`. **Caught while verifying:** that page's bridge never cleared
`MockData.mockNow`, so the countdown measured from the frozen demo anchor (Mon 5 Oct 2026)
and printed `24 Working Days Left` for a 6 Nov slot — the page now sets
`MockData.mockNow = null` like every other live page.

**4. A decided request keeps its real length** (`7c9cef6`, demo `c24d72d`, tip `602a585`).
Reported as "0.5 hours" on #24 and as the `col-requested` cell of #37. Cause and cure in §0
and §2 #29: the release actions delete the junction, the anchor is ONE 30-minute cell, so
the width is now stored on the request (`replacement_span`) and one reader
(`spanFromAnchor()`) owns the shape. **A test pinned the defect as a law**
(`MyReplacementsPayloadTest:403` asserted the 30-minute anchor for a rejected row) — it now
keeps that assertion only as the LEGACY null-width case, with a stored-width leg beside it.
`EnforcementEngineValidateSlotTest` had also been left stale by the 2026-11-07 type-aware
lab roster (three rows still expected AMCS1013/BMIT1173 `L` to book a lab) — fixed
separately in `705637a`, which is why the full suite is green again apart from the three
weekend-only fixtures.

**Suite after all of it:** 422 tests / 2352 assertions — 419 green + the 3 Saturday-only
errors (§5 item 9a). Demo DBs migrated + backfilled with a snapshot, row-count receipt and
log entry (13 rows on `fresh`; `demo` had none to fill).

### 2026-11-07 — date & time format: audit, then three shipped batches

- **The audit.** `docs/date-time-format-audit-2026-11-07.md` read every live render path (Blade,
  `ui-common.js`, the PHP feeders, the mail views) and listed 30 findings. The user approved them
  all, with three refinements: #19 (grid event block) uses `~`; #28 (week dropdown) approved; #30
  (API chip) deferred until `/api/v1` is unparked. Rulings: `~` is the range joiner (a hyphen is
  tolerated but no element mixes joiners); the en dash is not a joiner; day words are `Thu` in
  compact lines and `Thursday` in prose; the 24-hour grid axis and short notification tips stay.
- **Batch 1 (`10a51b0`)** — same-screen contradictions: Quick View proposed time 12-hour; the
  arrangement block cards; the approve/reject compare cards (shared `classBlock` /
  `replacementBlock`); the venue arrange question and booking subtitle.
- **Batch 2 (`5120a98`)** — joiner drift: My Replacements cancel confirm + mobile card, history
  `to` → `~`, arrangement error/toast/slot lines, grid event block, mobile history card,
  `NotificationTip` schedule lines. Four unlisted en-dash spots were swept in the same pass.
- **Batch 3 (`f420215`)** — padded/month-first dates: the shared week scaffold and
  `DateHelper.fmt/fmtShort` unpad (`5 Nov 2026`); day headers, week dropdown, mobile chip and
  lead-time note follow; semester chip becomes `21 Sep 2026 ~ 27 Dec 2026`; picker label/tip
  `Thu, 24 Dec 2026`; venue session rows `Mon, 9 Nov 2026`; the holiday skip compares the same
  unpadded shape.
- **Batch 4 (`1dc3edb`)** — out-of-page surfaces: the request card's Date line gains its comma
  (`5 Nov 2026, 2:00 PM`), and all three emails now carry the date + 12-hour range —
  `Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM` — through the shared
  `ReplacementRequestMapper::mailSchedule()/mailDate()/mailTime()`. The page payload keeps its ISO
  date + 24-hour span; `EmailQueueTest` pins the rendered shapes. **#30 (API chip) stays deferred**
  until `/api/v1` is unparked.
- **Also 2026-11-07 (`fe26b10`)**: the Request Approval urgency count now measures the **proposed
  replacement** date (`urgencyDate(r) = r.replacementDate || r.classDate`), not the original class;
  the header tip and `tests/status-tooltips-filter-chips.spec.ts` follow.
- Verification for the three batches was code-level (`node --check`, `view:cache`, Pint, PHPStan)
  plus `NotificationsBridgeTest` 36/36 (241 assertions) for batch 2; the user waived browser runs
  for date/time changes.

### 2026-11-06 — notification row tooltips, one vocabulary (grilled brief)

- The user's UI update *Organise Notification Row Hover Tooltips* came with a `grill-me`; eight
  decisions were asked and answered before any code (G1 students, G2 the `awaiting` reminder, G3 the
  proposer's own copy, G4=B one `Original Class Schedule:` label, G5 `approver_id IS NULL` = automatic,
  G6=A holiday-first reason, G7 the user's date/time/ID formats, (a) no `Conflict Reason:` line,
  (b) `Automatically Rejected by System`).
- Implemented as one pure composer (`NotificationTip`) + `ClassTypeLabel` (the PHP twin of the JS
  `UiLabels`): the island classifies the kind from the tile type + stored title and passes the maps it
  already loads. ONE new batch (`holidays`) — the one-`replacement_requests`-query receipt stays green.
- Eight Feature cases pin the exact lines per kind; the demo hover shows the real thing
  (`/tmp/opencode/notif-tip-hover.png`: the 6-line new-request tip).
- Two stale browser fixtures were fixed on the way (both red since the 2026-11-05 "panel opens on Active
  only" default): `notifications-island-testing-db.spec.ts` asserted inactive rows without switching that
  filter off. 6/6 green now. Full PHPUnit **394 / 2093**.

### 2026-11-06 — the Pending tooltip names the rule (UI update)

- User UI update *Pending Status Tooltip*: `.badge.status-pending`'s tip must spell out the rule, not
  only when it fires. Only the **lecturer** arm changed (`ReplacementRequestMapper::statusTip()`); line 1
  and the PL voice are byte-identical.
- New second line: *Auto-Reject Rule: If This Request Has Not Been Approved At Least 3 Working Hours
  (Friday deadline) Before The Proposed Class Start Time, It Will Be AUTOMATICALLY REJECTED.* The
  deadline is still the row's own `approvalDeadlineLabel()` value (proposed start − 3 working hours on
  the `WorkingHours` calendar, holidays included), so it stays dynamic per request.
- Emphasis rides capitalisation (`Auto-Reject Rule:`, `3 Working Hours`, `AUTOMATICALLY REJECTED`)
  because a `data-tip` is `textContent`; an assertion now forbids `<` in the string so nobody turns it
  into HTML. Badge class/colours/click behaviour untouched.
- Receipts: `AutoRejectPendingRequestsTest` pins both lines + the two emphasis substrings;
  `status-tooltips-filter-chips.spec.ts` (new `MRH_PENDING_TAIL_RE`) **9/9** on the demo DB; measured on
  the testing fixture (proposal Fri 6 Nov 14:00 → deadline `Fri, 6 Nov 2026, 11:00 AM`),
  screenshot `/tmp/opencode/pending-tip.png`.

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
- Follow-up (user: "highlight that cancelled tr … reuse the Earliest Bookable and today-btn highlight
  function/js"): `flashRowPulse(el, tone)` gained an optional tone, and `flashCancelledRow()` pulses the
  just-cancelled row **and** its phone card in `bookable-flash--warning`
  (`@keyframes bookable-pulse-warning`, `--color-warning`, same 0.5 s × 3). One mechanic, two tones —
  the green arm is byte-identical. Receipt: `request-cancel-pending.spec.ts` captures the class through
  a MutationObserver and asserts the computed `bookable-pulse-warning / 0.5s / 3`.
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
- **The lab booking roster matches the timetable, per module AND session type**
  (2026-11-03 ruling, then corrected 2026-11-07 by SDD `lab-access-type-aware`;
  decision recorded in `docs/venue-restrictions.md` R3). The two flat arrays are
  ONE map, `LectureSessionCatalog::LAB_ACCESS` (`module => ['L'?, 'T'?]`);
  `b006_allowed_module_codes` is derived from its keys. A listed type may book a
  lab only when that type's own seeded session already sits in one — six
  modules lost lab access on their L, AMCS1013 keeps T (B006) and loses L.
  `TimetableSeedInvariantsTest::test_t8_…` fails, in BOTH directions over
  `(module, type)`, if a future import drifts from the roster.

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
| 22 | **A rule the page enforces must also exist in the action** (2026-11-06). FR 2.17's 3-working-day notice was JavaScript-only; `SubmitReplacementRequest` now refuses `slot_too_soon` on the same `WorkingHours` calendar, and the failure copy reuses the grid's own sentence. When you add or touch a booking rule: action first, then the page, then one test — never the browser alone. |
| 23 | **A JS-owned control must survive a Livewire round-trip** (2026-11-06). Anything the server renders EMPTY but JavaScript fills (the week selects, `#filterChips`, table bodies) is deleted by the morph. The week selects are guarded centrally (`withWeekLivewireGuard()` around `pageLivewire().call()`; restore synchronously, never in a `setTimeout` — the caller's `.then()` reads the value first). Prefer that over `wire:ignore`, which would also freeze server-driven attributes. |
| 24 | **A cancelled request is displayed as "no active request"** (user brief 2026-11-06). On My Replacements an owner-cancelled occurrence shows **Need to Arrange**, an em dash in REQUESTED REPLACEMENT and no Proposed Replacement Schedule tab — one normalisation in `buildRow()` (status → null, replacement strings blanked), never three separate view patches. The RECORD is not rewritten: `cancelled`, `decided_at`, audit rows, released slots and the notification stay, and the history/notification surfaces still read *Cancelled by YOU*. `cancelled` means "the owner did it" because `CancelPendingRequest` is the only writer and it is ownership-checked. Other statuses are untouched. **Follow-up (`remove that option`):** the status filter no longer offers **Cancelled** — nothing on the page can carry that state — and a filter saved before the removal is mapped onto Need to Arrange when restored. **Follow-up (highlight):** the just-cancelled row pulses in the WARNING tone (`flashRowPulse(el, 'warning')` — the Earliest/Today mechanic, never a second animation; the phone card carries it too). |
| 25 | **The notification tooltip has ONE vocabulary** (user UI update 2026-11-06, after a grill): every live row is composed by `NotificationTip` — Subject / schedules / reasons / `Request ID`, labels omitted when their facts are missing, the reason holiday-first inside the Original line's parentheses, `Automatically Rejected by System` for a system rejection (`approver_id IS NULL`), students without the Staff/Requested-At lines, and the legacy `title — body` only for a kind with no layout. A `data-tip` stays TEXT. |
| 26 | **Dates and times speak one format** (user-approved 2026-11-07, `docs/date-time-format-audit-2026-11-07.md`; batches 1–3 shipped). Date `5 Nov 2026` (day first, no leading zero); time `2:00 PM`; range `2:00 PM ~ 4:00 PM` — `~` is the joiner, a hyphen is acceptable but one element never mixes joiners, and the en dash `–` is not a range joiner (it means "no data" / a break). Day words follow their container: `Thu` in compact lines, `Thursday` in prose and expanded modal rows. Keep the 24-hour grid hour axis (a scale) and the short notification tips (no year). New surface → use this standard; never re-pad a day or move the month first. |
| 27 | **A countdown counts WORKING days, and its bands are red / amber / GREEN** (user 2026-11-07, brief *Urgency, Countdown & Date/Time Consistency*). Words: `Today` · `1 Working Day Left` · `N Working Days Left` · `Overdue (N Working Day Ago)` — `Today` is the ONLY relative word (Yesterday/Tomorrow were tried and reverted). Past + today red, **1–7 amber, 8+ green** (the far band was grey). Working days = Mon–Fri minus the timetable's holiday rows; ONE holiday derivation (`workingDayHolidayKeys()`) feeds both the countdown and the cancel-class window. One band rule (`urgencyBandClass()`) + the `.urgency-*` rules in `theme.css`; a page never re-colours a countdown. The class-date countdown lives on the Original Class row (table, phone card, pop-up); the make-up countdown rides the requested date **only while pending** — approved/completed print `—`. |
| 28 | **The PL queue has no Urgency notion at all** (user 2026-11-07). The column, its sort, the `All/Urgent/Normal` chips, the active-filter chip, the row stripes, the phone-card line and the compare-card badge are REMOVED — do not reintroduce them. The request-age pill stays and speaks only about waiting time (`5 days ago · 4+ days old`), never urgency. The lecturer page and the PL page count down to different dates **by design** (class date vs proposed slot); the answer to *the two pages disagree* was to delete the PL rule, not to align numbers. |
| 29 | **A request keeps its own length** (user 2026-11-07, requests #24 / #37 / #38). `replacement_requests.replacement_span` stores the block width in 30-minute cells at submit; `ReplacementRequestMapper::spanFromAnchor()` is the ONE place that turns a junction-less request into a range, and a null width keeps the legacy single-cell shape. The junction still wins when it exists. Never hard-code `count = 1` anywhere else — a second copy of that shape is exactly how the notification tip kept printing `11:00 AM ~ 11:30 AM` for a 2-hour rejection. Old rows are filled by `php artisan crs:backfill-replacement-span` (audit-derived; dry run by default). |
| 30 | **The arrangement cell names its reason; the tip sits above it (2026-11-07).** The hover label is one of `Your Pending` · `Other Pending` · `Venue Conflict` · `Cohorts Conflict` · `Your Conflict` · `Your & Cohorts Conflict` (`Occupied` = fallback only), decided by ONE resolver (`classifyState()`) from DATA: `mine` (ownership), the busy rows' 7th tuple key `arms` (`own`/`cohort`, unioned when twins merge) and the grid payload's `status 1` (the room). The words are a real span in the cell — never the CSS `::after` overlay — so a `<br>` is legal there (11 px, 2 lines, inside the cell). The LEGEND is six rows and groups every non-yours red into **Others Conflict**: do not re-split it into Venue/Cohorts rows, and do not rename `Your Pending`/`Other Pending` back to `Pending (You)/(Others)`. The cell's tooltip is placed ABOVE the cell (the default engine mode; no `data-tip-pos`) and its own-block tag says `Your conflict`. |

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

**Display & data laws (2026-11-07)**
- **A live page must set `MockData.mockNow = null`.** `mock-data.js` pins the demo clock to
  Mon 5 Oct 2026 for the frozen templates; a live page that forgets this line measures every
  date from October. The countdown bug that printed `24 Working Days Left` for a 6 Nov slot
  was exactly that one missing line on `/request-approval-ui`.
- **An explicit `get([...])` column list silently returns NULL for the column it omits.**
  `ReplacementHome`'s request query listed its columns by hand; after `replacement_span`
  landed it was not listed, so the page kept reading the anchor. When a model gains a
  display column, grep for the queries that enumerate columns.
- **A hover label that must wrap cannot be a CSS `::after`.** `content:` holds one line and no
  `<br>`, so the arrangement cell's state words are a real `<span class="cell-state-label">` and
  `:has()` retires the old overlay exactly where the span exists. A 40 px cell fits two lines at
  11 px; a longer string clips INSIDE the cell (never outside it) — verified at 390/768/1024/1440 px.
- **One reader per fact.** A range, a label, a tip or a countdown is computed in ONE place
  (`ReplacementRequestMapper::spanFromAnchor()`, `statusTip()`, `NotificationTip`,
  `urgencyBandClass()`, `workingDayHolidayKeys()`, `classifyState()`). A local copy of the same
  shape is how `NavNotifications::$anchorSpan` kept printing 30-minute rejections after the fix
  shipped — and how a cell label would drift from the tooltip's why-tags.
- **A test can pin a defect as a law.** `MyReplacementsPayloadTest:403` asserted the
  30-minute anchor for a rejected row, i.e. it protected the bug. When a fix contradicts a
  green assertion, read the assertion's intent before believing it.

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
- A full-suite number is **PHPUnit only** (394/2093 at 2026-11-06). The data-dependent specs (request-approval,
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
| FR 2.17 / the notice gate | `app/Actions/SubmitReplacementRequest.php` (the `slot_too_soon` and `holiday` gates, both right after the anchor lookup), `app/Support/WorkingHours.php` (`noticeCutoff()`, `isBookableOnDate()`), `app/Livewire/ReplacementArrangement.php` (`conflictText` arm), `public/js/ui-common.js` (`leadTimeCutoff`, `isSlotTooSoon`, `renderLeadTimeNote`), `tests/Feature/SubmitGatesTest.php` (renamed from `LeadTimeGateTest` when the holiday leg joined it: 6 notice + 4 holiday cases) |
| Cancel Class + the inbox rules | `app/Livewire/NavNotifications.php` (`exceptionMap()`, the two read-time classifiers), `resources/views/partials/notif-row.blade.php` (`$rowTip`), `public/js/ui-common.js` (`showCancelToast`, `needsLoadToast`, `markLoadToastShown`, `CancelClassModal`) |
| Date & time format (audit + batch log) | `docs/date-time-format-audit-2026-11-07.md` — approved 2026-11-07; batches 1–4 shipped (`10a51b0`, `5120a98`, `f420215`, `1dc3edb`); only #30 (API chip) deferred |
| Lanes already landed | `prompts/pi-replacement-home-status-badges.md`, `prompts/pi-request-completed-status.md` |
| The approved picture of the merged page | `../ui-prototype/replacement-merged-preview.html` (outside the repo, never staged) |
| Superseded session diaries | `history/2026-10-31-to-2026-11-03-addenda.md` |

---

## 5. Open tasks (state at 2026-11-07)

1. **[USER] Push** — **67** commits sit on `fedora-backend` past `39b2bb7` (newest `602a585`, 2026-11-07 evening).
   Nothing is pushed by an agent, ever.
1b. **[USER, the archive word]** Lanes APPLIED and verified but still in `.sdd/changes/`:
   **`replacement-span-persistence`** (`7c9cef6` + `602a585`, demo receipt `c24d72d`) and
   **`arrangement-cell-state-labels`** (`358ad2a`, `e6e4866`, `4ecfe48`, `ecec7f0`) — plus the older
   `arrangement-slot-ownership-legend` and `lab-access-type-aware`. They move to `.sdd/archive/` only
   on the user's word.
1a. ~~**[AGENT] Date/time batch 4**~~ **SHIPPED 2026-11-07** (`1dc3edb`): #22 and #29. Only #30
   (API chip `d-M-Y`) remains, deferred until `/api/v1` is unparked.
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
9a. **[AGENT, weekend-only]** Three tests fail on a **Saturday** run and only then
    (`MyReplacementsPayloadTest::test_completed_is_derived_and_the_boundary_is_today`,
    `ReplacementHistoriesTest::test_merged_page_keeps_today_and_future_approved_requests_as_approved`,
    `ReplacementHomeTest::test_home_reads_the_effective_status_per_the_date_matrix`): their
    `today()->addDay()` fixture is Sunday when today is Saturday, and `class_sessions` /
    `time_slots` carry a `day_of_week BETWEEN 0 AND 5` CHECK. Proven identical on stashed HEAD
    (2026-11-07) — pre-existing, not a regression. Cure is the fixture law: a `now()`-relative
    fixture must not land on a Sunday (or `Carbon::setTestNow` a weekday).

10. **[LOW]** `?week=` deep links still land on the remembered week (accepted); three reference
    templates under `resources/views/ui-design-templates/` still carry `Exclude Completed` and a
    `ClassCancellation.undo(entry)` call — they are design references, not loaded pages.
