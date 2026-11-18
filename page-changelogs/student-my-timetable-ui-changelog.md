## 2026-11-18 — the phone detail footer is one row again

At ≤520 the details pop-up footer used to stack a full-width Close bar above a lone
centred pill — three blocks rather than a footer. The wrappers now dissolve and the
footer is a single row: Close (or Cancel/Reject) sits at the left, the action at the
right, and a primary that cannot share the row takes a full-width line underneath.
Every footer target is at least 44px tall, 48 on touch — the 40px Cancel Class?
exception is gone. Above 520 nothing changes: same one row, same compact 34px buttons,
same order and focus order.

## 2026-11-18 — summary cards stop printing zero pending suffixes

A zero pending part now renders as the confirmed number alone: `10`, not `10+0`.
Positive tails, including fractional hours such as `+0.5`, retain their existing
markup and formatting. This applies to the desktop cards and those same nodes in
the phone Week summary sheet. Counts, formulas, colours and sheet relocation are
unchanged; no tooltip/toast positioning changes.

Implementation: shared `computeSummary()` and Venue's separate server-totals
writer each append `.sum-pending` only for a positive pending component.
Receipt: four focused read-only browser cases went red then green; combined
responsive/focused run **70 passed** (including three auth setups).
All four timetable consumers checked at 1440/390 touch in both themes (16 captures,
zero zero-valued tails). Synthetic positive/transition tests use browser-only
values, never database fixtures. Writing lifecycle receipts' display expectations
were updated but were not executed on protected manual data.

## 2026-11-18 — readable dark tabs and a touch-sized quiet exit

Shared `theme.css` now uses `--color-on-primary-container` for active-tab text in
dark mode only; the blue underline and light-mode text stay unchanged. Quiet
detail-footer exits (`btn-outline:not(.is-primary)` and `btn-close-modal`) honour
48×48 under `any-pointer: coarse`, including wide touch windows. Each family's
existing width, footer order and outline skin stay put; mouse geometry is untouched.
Primary/destructive actions and their behaviour are not changed.

Compact SDD `modal-visual-rules`: one independent review, PASS. Browser measurements
cover 390 touch and 1440 touch/mouse in both themes; representative before/after
values and source-cascade checks are in its combined artifact. No Playwright suite
rerun for this CSS-only change; no database content writes.

## 2026-11-17 — the printed page matches the prototype

Three things were still reading as "the screen's table on paper". They now match
`ui-prototype/timetabel print/Timetable Print — Prototype.pdf`:

- **The hour band** is tinted (`--p-surface-variant`) instead of white, 24px tall, 8px bold,
  centred, with the start time over a lighter, smaller end time — the prototype's numbers,
  not the old 8.5px on white.
- **The day column** is a 78px strip (it was 68) on the same tint, centred, 700 weight, with
  the date at 8px under the day. A public holiday prints its mark in the key's red at note
  size, as a sentence rather than a screen pill. The strip also stopped printing *displaced*:
  it was still the screen's sticky column, which pushed the whole table off the page edge.
- **The key moved to the bottom** of the sheet, under the table, where a reader checks a
  colour after reading the grid. Same three chips and the same ownership line, now with the
  prototype's 11px swatches.

Nothing about the screen changed, and the paper still prints one A4 landscape page.

## 2026-11-17 — the print switch says what it prints

Hovering (or tabbing to) **Show normal class only** in the Print dialog now says what the paper will
contain, in each state:

- **Off** — *Prints: Normal · Replacement · Conflict / Public Holiday* / *Cancelled and pending classes are never printed*
- **On** — *Prints Normal classes only*
- **All Weeks** — *All Weeks always prints Normal classes only* (it is disabled there, and the tip says why)

It is the same hover tip the rest of the site uses, and it changes the moment the switch or the week
changes instead of showing the state you just left.

## 2026-11-16 — the printer asks before it prints

The printer button now opens a small **Print** dialog instead of printing straight away. Two
settings live there, because they belong to printing and nothing else:

- **Week** — `All Weeks (Whole Semester)`, or any single week (each option carries its dates).
  `All Weeks` prints the whole semester's timetable; a week prints just that week, even if the
  page is showing a different one — the screen does not move.
- **Show normal class only** — one week's normal classes without replacements. On `All Weeks`
  the switch is **disabled and switched on**, because that is what All Weeks prints: the
  semester's normal timetable.

The switch used to sit beside the printer in the week row; it moved into the dialog, so the row
goes back to arrows, week, Today and Print. It is drawn with the same toggle the replacement
pages use in their filter bar — same 36 × 20 track, colours, focus ring and label — rather than
a control of its own, and `Cancel`, `Esc` or a click outside closes the dialog without printing.

## 2026-11-16 — the printer button prints a real sheet

The button in the week row has shown *"Printing is coming soon"* since the mock era. It now opens the
browser's own print dialog and prints a **paper** of the student's own week.

The header carries the student's name and number, `Cohort: <code>`, the semester and a two-part week
line, over a three-chip key. Each block shows the venue, the lecturer's surname and the course, with
the time at the foot. Pending and cancelled never print (they stay on screen); a public-holiday row
paints its blocks red; an empty Sunday row is not printed.

The **Normal Class** switch prints the semester's normal timetable (`Week 1 ~ 14 (Whole Semester)`).
The page stays read-only — nothing here is a control.

## 2026-11-14 (late) — the cards move behind one Summary button on a phone

At ≤768px this page's summary cards leave the flow and open from a bare bar-chart icon beside the help
"?". The sheet is the shared one (`partials/ui-summary-bar`), titled **Summary**, docked at the bottom
with the dim, a scroll lock of its own, and closes on ×, dim tap or Esc — focus returns to the button.
The cards are MOVED, not copied, so the ids, wording, colours and numbers (including the ⓘ explanation
dialog, which opens above the sheet) are exactly what they were. At ≥769px nothing changed. The button
hides itself when the page hides the cards (an empty filtered view). One shared JS module
(`SummarySheet`) and one CSS block drive it, so all seven card pages behave the same.

## 2026-11-14 — the help dialog says something useful now

The user's ask: *"the HOW TO USE THIS PAGE for student side is too 敷衍, please enhance it"* — the
dialog opened with one thin sentence and nothing else. No SDD lane (explicitly skipped); the shared
modal partial was already in place, so only its content changed.

| | Before | After |
|---|---|---|
| Lead line | "View your weekly class schedule across all sessions." | "Your weekly **class schedule** for the whole semester — every session you attend, across all your courses." |
| Items | none | Week (arrows, week list, Today) · Class details (click a block) · Colours (Normal / Replacement / Pending / Conflict, hover the legend) · the five numbers count the shown week, not the semester · Read-only, and Class Replacements says where each replacement stands |

Only text; the dialog's markup, button and behaviour are untouched.

## 2026-11-14 — summary card back spacing and structured explanation

The flip cards shipped on 2026-11-13 (see that entry) were too tight to read: the back text sat 7px from the top and left 48px of dead space below, with lines 1.8px apart. The 44px info button in the bottom-right corner was the cause. It now sits **top-right**, the block is vertically centred, bullets carry a 4px gap and the described text uses `line-height: 1.45`. Every line reserves 38px so it can never run under the button; 39px was the last value that did not re-wrap a bullet.

Below 390px the summary bar drops to **one column**. A 178px card with a 110px text column cannot hold three lines, so the rule was required rather than cosmetic. At 390px and above the layout is unchanged.

The explanation popup is no longer one dense paragraph. It now opens as an optional **formula strip**, then one line per term with the meaning muted underneath, then an optional footnote. The strip states the *relationship* between terms and never a live number, because every front value is written at runtime by the page's own summary code — a printed number would be wrong on any other week. Total Classes deliberately does **not** claim `= total`: conflict blocks are counted in that card's number but appear in none of its parts, so the sum would be false on any week with a red block.

Four bullets were shortened so each card stays at three lines on a phone, with the full wording kept in the popup: `Replacement confirmed` → `Confirmed` (My Replacements, Request Approval), `Lecturer must re-arrange` → `Arrange again` and `Approved or rejected` → `Approved/rejected` (Request Approval).

Verified on all six flip-card pages with a real browser: **48 page-and-width combinations** (320, 360, 375, 390, 414, 768, 1024, 1360px), every card at **3 lines or fewer**, text never under the button, above/below space matched within 0px, and one column at 320/360/375 stepping to two at 390. All **30 explanation popups** open with a formula or at least two term lines, checked again in dark theme. No page errors. `ThemeCssIntegrityTest` 3/3, `php -l` clean on every touched view, views cached.

No value, calculation, filter, `valueId` or card colour changed. No database, route or dependency change.

## 2026-11-14 — cancel the actual approved replacement

The shared Cancel Class flow now identifies an opened approved replacement by its request and actual scheduled day, rather than cancelling its original occurrence again. Single and mixed selections share the atomic release path. Success lists and counts come from saved server receipts; a stale selected class refuses the whole decision.

The cancelled request keeps its audit and original class history. Its active blue landing disappears, and My Replacements returns to Need to Arrange. No new status or second cancelled landing block is added. Existing manual records are not repaired automatically. Scoped checks and limitations are recorded in [the verification report](../.sdd/changes/approved-replacement-cancellation/verify-report.md).

## 2026-11-14 — hide only the own-approved cancelled original

A cancelled original now disappears only when its own replacement is Approved. Pending, rejected, withdrawn/cancelled, auto-rejected and no-request cases keep it. Another occurrence's approval cannot hide it. The server filters the exact source pair before summaries and layout; stored history and the approved landing's true span remain unchanged.

Checks: **122/122 backend tests, 2,659 assertions**, on a verified isolated PostgreSQL instance; **18/18** read-only planner/layout cases plus **3/3** auth setups. Actual approved request **19** checked on all four timetable pages at desktop/phone widths in both themes. Manual business records unchanged. Writing lifecycle expectations updated and collection-checked, not executed on the user's data. [Full receipts](cancelled-slot-reuse-changelog.md). Whole-feature Verify remains separate.

## 2026-11-14 — half-hour overlap alignment

Fixed the shared lane planner so a pending 1:00 PM ~ 2:00 PM block and a cancelled 1:30 PM ~ 2:30 PM block stack on separate rows, aligned to their real times. Touching times still share a row. No booking rule, colour, label or database change. The same helper serves personal, cohort, student and venue timetables.

Checks: **18/18** read-only planner/layout browser cases; the new independent oracle covers **1,764 range/status combinations in both input orders**. Existing responsive/theme/print checks pass. Actual staff 5770 request 19 was also checked without staging data, on desktop and phone in both themes. Details: [cancelled-slot reuse](cancelled-slot-reuse-changelog.md). Whole-feature Verify remains separate.

## 2026-11-13 — summary cards, guide modal, semester chrome, week select

> 2026-11-13, in progress: see [cancelled-slot reuse](cancelled-slot-reuse-changelog.md) for shared stacking, real room-cell availability, passing checks and remaining delivery gates. No demo cleanup or final Verify yet.

User brief: reuse the approved `ui-prototype/my-timetable-mobile-preview.html`
design instead of a second direction. SDD lane `my-timetable-ui-cleanup`.

- Summary cards are now the 96px flip cards from the preview: left-aligned back
  face, up to three short bullets, and a 44px info button that opens the full
  explanation in a 420px modal. Hover flips on pointer devices, tap on touch,
  Enter/Space from the keyboard; closing the explanation restores the card and
  focus. The value ids and every calculation are untouched.
- The header guide icon opens a shared `How to use this page` modal containing
  the page description and all guide items. The inline guide bar is no longer
  rendered on this page; ×, Close, overlay and Escape close the modal.
- `#semesterProgress` (label, track, fill) and `.week-subtitle`/`#weekSubtitle`
  are removed. `updateProgress()` / `updateWeekSubtitle()` stay null-safe, so
  the existing calls are inert.
- `.week-select` now uses `var(--color-surface-variant)` at rest and
  `var(--color-surface)` on hover; text, chevron, height and focus ring are
  unchanged.

---

## 2026-11-11 — every summary card now explains its formula

User ruling (2026-11-11): every summary card must **say how its number is made**, in plain words.
All seven live pages (and the shared fallback map in `partials/ui-summary-bar.blade.php`) now carry
formula-first descriptions. Only the cards that show a `+N` mention it.

| Card | New description |
|---|---|
| Total Classes | Normal + replacement + pending classes this week. Cancelled classes are not counted. The small `+N` is the pending part. |
| Teaching Hours / Class Hours | Hours of your normal + replacement + pending classes this week (each slot = 30 minutes). Cancelled classes are not counted. The small `+N` is the pending part. |
| Replacements | Classes a replacement lecturer teaches for you this week. |
| Pending | Your classes still waiting for a replacement to be approved this week. |
| Conflicts | Classes that cannot run as scheduled — a clash, a cancellation, or a public holiday. |
| Cohort · My Teaching Classes | Classes you teach this week: normal + replacement + pending, each class counted on its own (Subject A (T) and Subject A (L) are two). The small `+N` is the pending part. |
| Venue · Available | Free slots you can book this week. Sundays and public holidays are not counted. |
| Venue · Occupied | Slots taken by other classes this week: normal + replacement + pending. The small `+N` is the part held by pending requests. |
| Replacements · Classes to Arrange | Your classes that still need a replacement arranged and have no request waiting. |
| Request Approval · Total Requests | Requests that match the filters you have set. |
| Class Replacements · Hours Awaiting Arrangement | Hours of those classes. Classes already arranged are not counted. |

Wording style: one short line, the formula first, the `+N` explained only where the card prints one.

Checked in the browser on every page: the text never overflows its card (0px on all 33 cards), values
unchanged by the wording (`9+1` / `12+1` on My Timetable W8), 0 console errors.

*Not applied to `/replacement-arrangement`: its summary bar is currently commented out in the blade (the
2026-11-11 legend commit), so no cards are displayed there — the copy is ready to drop in when it returns.*
## 2026-11-11 — the two timetable cards split finished + pending

User ruling (2026-11-11): **Total Classes = Normal + Replacement + Pending** and **Teaching Hours** = their
hours (cancelled classes excluded) — and both cards must say it the way the **Cohort** and **Venue** pages
already do: the finished part big, the pending part as a small `+N`, so the two parts always add up.

Both pages now pass the shared builder's existing `{ splitPending: true }` opt-in (`computeSummary`,
ui-common.js). No new maths, no new writer — the same one the Cohort page uses.

Measured on the demo data (Staff 5425, Week 8: 8 normal / 1 replacement / 1 pending / 2 cancelled):

| Card | Before | After |
|---|---|---|
| Total Classes | `10` | **`9`**`+1` — 9 finished + 1 waiting = 10 |
| Teaching Hours | `13` | **`12`**`+1` — 12 finished hours + 1 waiting hour = 13 |

Week 7 reads `11+0` / `14+0` and Week 9 `10+0` / `13+0` — the `+0` tail always renders, exactly like the
Cohort page (`8+0`, `11.5+0`), so the shape never jumps between weeks. Replacements, Pending and Conflicts
keep their plain numbers (a `+N` on the Pending card would be nonsense).

The student page (`student-my-timetable`) carries the same split: 24DFT0001, Week 8 → `11+0` classes /
`16+0` hours. 0 console errors on both pages.
## 2026-11-10 — Modal consistency (#1–#4)

**Follow-up (same day, user ruling B4):** ONE separator in a modal subtitle — the **em dash**.
Replacement Details was the odd one out (`AMSE1003 · Software Engineering (Lecture)`); all four now
read `CODE — Name (Type)`. Measured: Class Details `AMSE1003 — Software Engineering (Lecture)`,
Slot Details `MPU-3232 — Entrepreneurship (Lecture)`, Request Details `AMSE2003 — Software Testing
and Quality Assurance (Tutorial)`, Replacement Details `AMSE1003 — Software Engineering (Lecture)`.

**Follow-up (same day, user ruling A):** the **Subject** row left Class Information — the modal
subtitle above the tabs already prints `CODE — Name (Type)`, so the pop-up said it twice. Class
Information is now **Session ID · Lecturer · Cohort · Total Students** on all four timetable pages,
and the subtitle is built from ONE `subjectText` value. Measured on My Timetable, Cohort Timetable,
Venue Timetable and Student My Timetable: same subtitle, no Subject row, zero console errors.

**Follow-up (same day, user ruling B):** Slot Details stopped printing the room twice — the
**Room Name** and **Capacity** rows left Class Information, and the Schedule's **Venue** row now
carries them: **`B002 — Tutorial Room (35 seats)`** (code leads, the name loses the copy of the code
it already carries, seats optional). The same one wording applies everywhere a Venue row appears, so
My Timetable / Cohort / Student Class Details now read `B111 — Lecture Hall` instead of
`Lecture Hall B111`. Measured on room B002: five Class Information rows, `B002 — Tutorial Room
(35 seats)`, zero console errors.

**Follow-up (same day, size only):** every **modal-footer button is now exactly 34 px** — the
size the Close buttons already used (the footers carried 31 / 32 / 34 / 37 px before). The rule is
scoped to `.modal-footer`, so tables, cards and toolbars keep their own sizes; no button was re-ordered,
re-coloured or re-worded. Measured: 34 px on Class / Cancel Class / Request / Approve / Replacement /
Slot modals, no clipped labels, zero console errors.

The details modals now share one shell and one set of rows across the system (user brief
"UI/UX Update — Modal Consistency"; the same change landed on Class Details / Slot Details /
Request Details / Replacement Details).

- **Width** — every details modal is **616 px**, the same as the Replacement Details modal
  (`theme.css` `.modal.modal--detail` was 480).
- **Timeline rail removed** — `.modal-timeline`, its "ask SV" note and the dot styles are gone,
  with `DetailModal.timeline()` and the pending-rail config that fed it.
- **Subtitle carries the class type** — `AMSE1003 — Software Engineering (Lecture)`.
- **Replace Now** is a primary (blue) button with a swap icon on the staff timetable pages
  (students have no such button).
- **Class Information** — leads with **Session ID** (`#99`, the `class_sessions` id; `sessionId`
  still wins over the grid's row key), then **one Subject row**
  (`AMSE1003 — Software Engineering (Lecture)`), Lecturer, Cohort, Total Students. The separate
  Subject Name / Class Type / Class Ref rows and the "Subject Code" label are gone.
- **Schedule** — **Venue** (code + name), **Week**, **Day & Date** (`Mon, 9 Nov 2026`),
  **Time & Duration** (`9:00 AM ~ 11:00 AM (2 hours)`). The separate Day / Date / Start / End
  rows are gone. The class modals now receive the viewed week, so the Week row is real data.
- **One wording for the facts** — `ModalFacts.dayDate / timeDuration / venue` in `ui-common.js`;
  `venueLine()` and `request-approval`'s `venueText()` delegate to it, so a room name that already
  carries its code is never printed twice (`Tutorial Room B002`, not `B002 — Tutorial Room B002`).
- **No "NaN minutes"** — `durationWords()` and `rangeHours()` refuse a non-number instead of
  printing it.

Verified with Playwright on the demo data (`:8010`), 22/22 checks: widths 616 on all four pages,
subtitle with the type, zero timeline elements, Replace Now blue + icon, the new row order and
formats on both schedules of Request Details and Replacement Details, no NaN, no duplicated venue
code, zero console errors. Pinned by `tests/Feature/ModalConsistencyTest.php` (9/9).

## 2026-11-08 (Student-side health check S6) — the top-bar logo keeps you inside the app (all four timetable pages)

The logo calls `navigateHome()`, which goes to `window.PAGE_HOME`, filled from the `homeUrl` a page
hands the shared layout. My Replacements, Arrangement, Approval and History already passed their role
home; the four **timetable** pages (staff My Timetable, Cohort, Venue, and this student page) never
did, so their logo fell back to `/` — the public welcome page, whose only signed-in control is a bare
`/dashboard` placeholder. A student clicking the logo left the app.

All four now pass their role home. Measured on the demo data — `PAGE_HOME` is `/my-timetable-ui` on
the six staff pages and `/student-my-timetable-ui` on both student pages; clicking the logo on
`/venue-timetable-ui` lands on My Timetable and on the student timetable it stays put; zero console
errors.

Receipts: `tests/Feature/LogoHomeUrlTest.php` (2 invariants — every page using the shared layout passes
`homeUrl`, and the timetable pages point at their own role home; red-check: removing one fails both),
pint passed, phpstan 0, `view:cache` OK.

## 2026-11-08 (Student-side health check, S1 + S4 + S5) — real "Today", and `?week=` arrivals

**S1 — the page's "Today" was 5 Oct 2026.** The mock-era anchor (`public/js/mock-data.js`
`mockNow: new Date(2026, 9, 5)`) was never cleared on this page — the other seven live pages all
clear it — so the Today button jumped to Week 3 and no day in the real week carried the badge.
One line in the data bridge (`MockData.mockNow = null;`), plus a guard test that every live page
clearing-free bridge must have it (`MockNowClearedTest`) and a browser receipt
(`tests/student-today.spec.ts`). After: lands on the real week, `Sun 8 Nov 2026` badged Today,
Today keeps it.

**S4 — a notification can now land on its own week.** Student notification links used to be the bare
page (a "class cancelled, week 14" notice dropped the student on the current week). The emitter now
writes `/student-my-timetable-ui?week=N`, and `StudentMyTimetable::mount()` honours it:
`?week=9` opens Week 9, `?week=14` opens Week 14, and a missing / junk / out-of-range value keeps
today's week. Measured that the param wins over the remembered week (browse to Week 3, then arrive
with `?week=14` → Week 14). Focused tests: `StudentTimetableWeekParamTest` (4) + the link assertion in
`NotificationsBridgeTest`.

**S5 — dead parameter removed.** The component passed `notifCount => 0` to the layout; nothing read it
(the bell island computes its own unread total).

Receipts: `LocalCalendarRulesTest` unrelated; suites 41/41 focused, pint passed, phpstan 0, browser
with zero console errors; `/tmp/opencode/student-today-fixed.png`, `/tmp/opencode/student-week-param.png`.

## 2026-11-04 — the page description speaks the same visual language as My Replacements

Minor UI update (user, all pages): the `.page-desc` line now bolds and uppercases its key terms with
the existing `.info-keyword` class — the convention the merged My Replacements page introduced. The
words are unchanged; only the emphasis markup is new (`description` → `descriptionHtml`), so the
rendered sentence reads identically.

# Changelog — Student My Timetable

## [2026-10-23] The week memory is session long, per page (SDD week-memory-until-logout, user law 2026-10-20)

The page adopts the exact my-timetable wiring: the 6-arg ctor
`(…, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) })`
(derived key `weekNav-weekSelect` unchanged) and save()/load() at
`weekmem-weekNav-weekSelect@<login_week_stamp>` in **sessionStorage** —
the old cross-login localStorage memory never survives a login anymore.
Contract: fresh visit in-session = TODAY (the server's
`currentTimelineWeekIndex()`); navs save this page's own version; a
revisit resumes it; a fresh login's new stamp makes the previous
session's keys match nothing. Receipts (one live walk, M5 — the real
logout from a staff session 4363 then login HERE as the seed student
`25RSD0001`): her page lands on TODAY and her own first nav adds HER
key (+1) while the staff leftover sits inert — `week-memory-until-logout.spec.ts`
8/8 incl. setup; pint ✓ · phpstan-1G 0 ✓ · phpunit 230/230 ✓.

## [2026-10-10] Frozen-design parity: real identity, keyword-span cards (SDD all-pages-design-parity)

- **Identity fix (bug)**: the page never set `MockData.currentUser`, so the shared modal's own-request comparison ran against the mock-data default persona — a student's own pending request could never match. Now set from the authenticated student.
- **Card descriptions** adopt the frozen keyword spans; **tooltip bug fixed** (stale `tooltipExtra` dropped — was rendering the lecturer twice); print button on week-nav; summary filters cancelled.
- Verified: phpunit 130/130 · pages-parity 3/3 (identity value pin — default persona overridden) · records-intact.

---


## [2026-10-09] Nav label correction (SDD student-nav-label-parity)

Correction to the postscript below: the second nav item reads **"Replacement History"** (not "Request History" as originally written) — the shared whitelist label was renamed for cross-page parity; see `auth-wiring-changelog.md`.

## [2026-10-09] Nav postscript (SDD student-nav-remove-cohort)

The top nav on this page now shows **2 items** (My Timetable, Request History) — Cohort Timetables was removed from the student side; see `auth-wiring-changelog.md` for the whitelist change and `cohort-timetable-ui-changelog.md` for the route gate. This page remains the students' FR 1.2 own-cohort view.

## [2026-08-31] Wired to real data via Livewire (SDD wire-backend-into-refactored-ui, Slice A)

Page served by `App\Livewire\StudentMyTimetable` — strictly read-only (FR 1.5–1.8). Student nav (2 items) preserved through the component's layout data. Semester chip in header shows the real cohort code (FR 1.2 scoping via `students.cohort_id`).

### Mock → real mappings
| Mock | Real |
|---|---|
| `rsd3g2Base` weekly repeat | own-cohort `ClassSession` per week (via `session_cohorts`) |
| `studentTimetable.cancelledFlags` | `class_exceptions` rows (cancelled weeks excluded server-side) |
| `notificationCount: 3` badge | `notifCount: 0` until notifications exist (Slice C, FR 1.9) |

### Divergence duty
- Pending/approved request statuses (FR 1.3) now derive from `replacement_requests` overlays (pending → amber occurrence "Pending since…", approved → blue replacement at the new day/time/venue).


---

## [2026-08-31] Sync upstream/fjing UI refactor (merge fd8c403)

Merged `upstream/fjing` (267 commits `cfc1bb1..f44cc5c`) into `fedora-backend`. Policy: **theirs-first for UI**; backend-only files kept local. Brings the page to upstream's latest state on top of the TASK-006 nav swap documented below. Verification: `migrate:fresh --seed` green, PHPStan 0, PHPUnit 94/94, smoke 12/12 routes 200 (`/student-my-timetable-ui` 200).

### Files Changed
- `resources/views/ui-design-templates/student-my-timetable-UI-design-template.blade.php` — conflict → **theirs**
- Shared: `theme.css`, `ui-common.js`, `mock-data.js`, `layouts/ui-template`, `partials/ui-nav-bar` — **theirs**

---

## [2026-08-24] Nav swap: Request History → Upcoming Replacements (TASK-006)

Student role drops "Request History" (Ch1 §1.1.4 view-only — status is already
visible via timetable colors per FR 1.3) and gains "Upcoming Replacements"
(FR 1.4). The `/my-request-history-ui` route stays for lecturers/PLs.

### Files Changed

#### `resources/views/ui-design-templates/student-my-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Line 6 (`navItems`) | Replaced | `['key'=>'replacement-history', …]` → `['key'=>'upcoming-replacements','label'=>'Upcoming Replacements','href'=>'/upcoming-replacements-ui']` |

New page stub + route + `MockData.upcomingReplacements` dataset recorded in
`upcoming-replacements-ui-changelog.md`; UI build spec in
`todo list/upcoming-replacements-ui-plan.md`.

---

## [2026-08-16] Today button now persists week selection

The "Today" button now saves the current week to `localStorage` (via `WeekNavigator.jumpToToday()` calling `this.save()`), consistent with arrow/dropdown navigation. Previously, clicking Today would jump the view but not persist — a page refresh would revert to the old week.

### Files Changed

#### `public/js/ui-common.js`

| Location | Change | Detail |
|---|---|---|
| `WeekNavigator.jumpToToday()` | Updated | Now calls `this.save()` after updating the UI, matching the behavior of `prevWeek()`/`nextWeek()`/`selectWeek()`. |

---

## [2026-08-15] Event hover tooltip shows lecturer instead of venue

The event-block tooltip previously showed `name · venue` (venue already on the block). It now shows the **lecturer** (`name · Prof. Dr. Khoo Teik Huat`) — the key info a student doesn't get from the block. Uses the new `tooltipExtra(event)` option on the shared `buildTimetableGrid`.

### Files Changed

#### `resources/views/ui-design-templates/student-my-timetable-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `buildTimetable()` | Updated | Passes `tooltipExtra` returning `event.lecturer`. |

#### `public/js/ui-common.js` + `public/css/theme.css`

Shared: `buildTimetableGrid` now sets `dataset.tip2` from `cfg.tooltipExtra`; `.event-block::after` reads `attr(data-tip2)`.

---

## [2026-08-15] Class detail modal redesign: unified detail sheet

The Class Detail modal now uses the shared `DetailModal` "Detail Sheet" via the shared `openClassModal()` (single flat group, definition rows, identity header). No page-specific changes needed.

---

## SDD Change: `student-my-timetable-ui`
**Date:** 2026-08-02
**Scope:** View-only weekly timetable for students + rule #6 CSS promotion + 14 UI enhancements

---

## Files Created

### `resources/views/ui-design-templates/student-my-timetable-UI-design-template.blade.php` (588 lines)
- New student page template — view-only weekly grid for RSD3(S1)G2 cohort
- Reads from `MockData.cohortTimetable.rsd3g2Base` + `rsd3g2Flags`
- Cancelled-filter pipeline using `MockData.studentTimetable.cancelledFlags`
- Holiday conflict derivation from `MockData.holidays`
- 14 enhancements: Today button, week subtitle, date-range select, heatmap bar, empty state, keyboard nav, modal status timeline, dynamic notif count, event `.meta` pocket, semester progress bar, week-change transition, event hover tooltip, copy course code, smooth scroll to grid

### `resources/views/partials/ui-legend-bar.blade.php`
- Shared legend bar partial (4 items: Normal Class, Replacement, Pending, Conflict)
- Uses `var(--color-secondary/primary/tertiary/error)` tokens
- Consumed by MyTimetable, CohortTimetable, and Student page

---

## Files Modified

### `public/js/mock-data.js`
- Added `MockData.studentTimetable` section (`activeCohort`, `cancelledFlags`, `notificationCount`)
- Week 4 cancels ALL 7 events → triggers empty state
- Updated holidays comment: "CONSUMED BY CohortTimetable + Student My Timetable"

### `resources/views/partials/ui-nav-bar.blade.php`
- Added `$navItems` default block (5 items with key/label/href)
- Replaced hardcoded `<a>` tags with `@foreach ($items as $it)`
- Added `$notifCount` parameter (default `3`) + `id="notifBadge"`

### `resources/views/layouts/ui-template.blade.php`
- Forward `$notifCount ?? 3` to `@include('partials.ui-nav-bar', ...)`

### `public/css/theme.css`
- Appended ~460 lines of promoted CSS (blocks 4.2–4.19)
- Promoted from MyTimetable + CohortTimetable inline: legend, summary-card, modal-shell, semester-bar, time-column, hour-header, hour-cell+event-block
- New enhancement CSS: today-btn, week-subtitle, heatmap-bar, keyboard-focus, status-timeline, semester-progress, week-change-transition, event-tooltip, copy-toast
- Standardized `.week-select` min-width to 160px, `.modal-footer` to `flex-end`

### `resources/views/ui-design-templates/MyTimetable-UI-design-template.blade.php`
- Replaced inline legend → `@include('partials.ui-legend-bar')`
- Removed inline CSS blocks 4.2–4.8 (now in theme.css)
- Deleted dead `.today-highlight` CSS
- Kept page-specific: `.btn-replace-now`, `.btn-cancel-class`, `.cancel-overlay`, `.modal-footer-left/right`, `.modal-footer{space-between}` override

### `resources/views/ui-design-templates/CohortTimetable-UI-design-template.blade.php`
- Replaced inline legend → `@include('partials.ui-legend-bar')`
- Removed inline CSS blocks 4.2–4.8 (now in theme.css)
- Deleted dead `.today-highlight` CSS
- Kept page-specific: `.badge-*` variants
- Did NOT touch inline holiday rule `d===3&&w===3`

### `routes/web.php`
- Added `GET /student-my-timetable-ui` route

---

## Key Decisions
- **Cancelled classes hidden entirely** — design decision (no FR mandates it); keeps student view uncluttered
- **Week 4 cancels ALL 7 events** — ensures empty-state message (item 17) is testable
- **`MockData` is read-only** — events reconstructed via spread copy, `meta: {}` pocket for future backend fields
- **Keyboard nav is mock-phase only** — full WCAG compliance deferred to Sprint 3
- **`$navItems` parametrization** — backward compatible; existing pages pass only `$activeNav`

## Lint/Type Check
- PHPStan: 20 pre-existing errors in Models/Providers/Seeders (none in files we modified)
- Pint: not run (timed out; no PHP files modified in our changes — all Blade/JS/CSS)

---

## [2026-08-10] Phase 2 Template Migration: Inline helpers → Shared OOP classes

### Summary

Migrated inline `fmtShort()` function to `DateHelper.fmtShort()` from `ui-common.js`. Removed 3-line inline function definition.

### Files Changed

#### `resources/views/ui-design-templates/student-my-timetable-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-10 | Lines 61-63 | Removed | Deleted inline `fmtShort(d)` function definition |
| 2026-08-10 | Line 98 | Replaced | `fmtShort(startDate)` → `DateHelper.fmtShort(startDate)` |

#### `public/js/ui-common.js`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-10 | DateHelper class | Added | `DateHelper.fmtShort(d)` static method — returns "DD Mon" format |
| 2026-08-10 | DateHelper class | Added | `DateHelper.weekRangeLabel(weekNum)` static method — returns responsive week range label |

---

Page uses `ui-page-header`, `ui-week-nav`, `ui-grid-table`, `ui-empty-state`, `ui-class-detail-modal` partials.
| 2026-08-13 | `public/css/theme.css` (shared) | macOS table fix | `.timetable`: `border-collapse:collapse` → `separate` + `border-spacing:4px`; removed 1px cell borders; hover uses `var(--color-surface-variant)`; removed zebra striping. `.badge` border-radius 6px→8px. |
| 2026-08-14 | Page styles | Offday-slot today-cell fix | Added `.today-cell.offday-slot { background: transparent; }` override so PH/Sunday empty cells are not red when today. |
| 2026-08-14 | `prevWeek/nextWeek/selectWeek` | WeekNavigator delegation | Local nav logic replaced with `weekNav.prevWeek()/nextWeek()/selectWeek()`; `buildTimetable()` syncs `currentWeek = weekNav.currentWeek`. Removed manual select-index/subtitle/progress/arrow/save updates. |

## Post-2026-10-02 cross-reference

The badge feed added with this page's build (`$notifCount` with server default `3` on the nav bar, `MockData.studentTimetable.notificationCount`, and the page's `notifBadge.textContent` echo) was removed by the `notifications-panel` change — the bell badge is now computed by `refreshNotifBadge()` in `ui-common.js`; see `notifications-panel-changelog.md`.

## [2026-10-03] Disabled print icon on the week-nav toolbar

Shared `ui-week-nav` gained an opt-in `'showPrint' => true` arg rendering a printer icon-button
(inline SVG, `.print-btn` in theme.css), right-aligned at the toolbar edge via `margin-left: auto`.
Enabled stub: click fires the shared `toast.show('Printing is coming soon')` bottom-left toast bar;
`title="Coming soon"` native tooltip on hover. No JS beyond the one-liner onclick.

### Postscript — sweep-fixes-round-1 (2026-10-06, F-11)

Week persistence key namespaced to `studentTimetableWeek` (was the shared generic
`currentWeek` — pages silently overwrote each other's saved week). Legacy-value migration
handled once in `WeekNavigator.load()`; shared sorting/labels untouched.

### Postscript — sweep-fixes-round-3 (2026-10-06, rename + logo)

Student nav item renamed to "Replacement History" → `/replacement-history-ui` (page
rename, see that changelog). Logo click now lands on Student My Timetable (homeUrl
param) instead of the welcome view.

### Postscript — holiday-badge-generic-label (2026-10-07, shared ui-common)

Day-header holiday badges now read generically as **PUBLIC HOLIDAY** (markup
text `Public Holiday`; the badge CSS already uppercases it), with the specific
holiday name — e.g. `Deepavali Holiday (In Lieu)`, `Christmas Eve` — shown on
hover via the shared `data-tip` tooltip system. Label-less holiday flags show
no tooltip. One-line change in `HtmlBuilder.dayHeader` (ui-common.js:2155);
applies to every timetable page via the shared builder.


---

## [2026-10-07] Class modal grouped into tabs (shared DetailModal taxonomy)

Same as my-timetable: `openClassModal` auto-groups rows into Class Information /
Schedule / Status tabs via `renderModalGroups` (ui-common.js).

---

## [2026-10-07] Confirmed replacement modal shows the replaced original class

Shared `openClassModal` enhancement — replacement blocks show an
**Original Class** tab when the event carries `replacedFor` or a date-shaped
remarks string (rsd3-g2 flags now date the prior week's same weekday).

---

## [2026-10-07] Conflict blocks now render red (was: unstyled grey)

A conflict-status block (e.g. BMIT2073, "Lecturer on leave" week) rendered with
no status class because the shared default classifier lacked a conflict
branch — the block in your paste (`event-block span-4` with no `event-*`
class) is exactly that. Fixed in `buildTimetableGrid` (ui-common.js): conflict
events now get `.event-conflict` (red, §10.0 legend A).

---

## [2026-10-08] Copy + dead-code cleanup (audit follow-ups)

- Conflicts card description said "Scheduling **overlaps**" — now
  "Scheduling clashes and public holidays" (matches what the card counts).
- Dropped the fabricated `'01 Sep 2026, 09:15 AM'` fallback for pending
  `requestedAt` — every pending flag in `rsd3g2Flags` already carries its real
  timestamp; the fallback was dead and invented a date if one ever didn't.
- Removed unused `WEEK_KEY` / `loadSavedWeek()` / `saveWeek()` (week
  persistence runs through `WeekNavigator` directly).

### Postscript — snooze rule extended (2026-10-08, U3 decision)

The undo toast now shows for **5 s** (was 12 s) and **auto-dismissing after the
full display also snoozes** it — surviving the whole toast counts as "seen",
same as clicking ✕. Only navigating away mid-display (timer killed) leaves the
entry unsnoozed, so the toast legitimately re-shows on the next load.
`ToastManager.show` gained an `onAutoDismiss` hook alongside `onManualDismiss`;
`UNDO_TOAST_MS` 12000 → 5000. Live-verified all three paths, 0 console errors.

### Postscript — undo feedback + grid rebuild (2026-10-08, U1/U2)

Clicking the undo toast's **Undo** now works as it looks: `ToastManager`
dismisses the undo bar **before** running the callback (U1 — the callback's
"Class restored." toast was previously wiped ~0 ms after appearing), and the
callback rebuilds whichever grid is on screen after `ClassCancellation.undo()`
(U2 — every timetable page's `buildTimetable()` / replacement-home's
`buildTable()`; the class reappears without a manual reload). Confirmation
toast shows last, over the rebuilt grid. Live-verified on my-timetable,
student page, and replacement-home; 0 console errors.

### Postscript — conflict blocks made unmistakable (2026-10-08)

`.event-conflict` (global, theme.css) upgraded from a plain red tint to
a **2px solid `--color-error` border + diagonal caution stripes**
(`repeating-linear-gradient` over the container tint, token-only via
`color-mix` — adapts to dark theme). Same meaning, same red per §10.0;
just impossible to mistake for an ordinary block at grid glance.
Applies to every page rendering conflicted classes (cohort, venue,
my timetable, student). No legend or test changes needed; verified live
on cohort (dft2s1 W4 AMCS2093) and venue (B110 W4), suite 124 passed.

### Postscript — conflict & PH unified to one loud red (2026-10-09)

On personal timetables everything shown is the viewer's own, so the old
split (conflicted = loud striped `event-conflict`, public-holiday = quiet
plain tint) was meaningless noise. Default builder (`ui-common.js`) now
sends **both** conflict-status and PH-day classes to the loud
`event-conflict` treatment — one red, one meaning: "this class will not
run as scheduled". The shared legend's default conflict swatch reuses the
real `event-conflict` class so the legend shows the exact styling
(Cohort/Venue pages keep their own owner-gated legend split: loud = yours,
quiet tint = others'). Verified: my timetable W8 Monday (Deepavali
in-lieu) + W14 (Christmas) PH classes and student page W1/W5 conflicts +
W8 PH all render loud; suite green.

### Postscript — diagonal hatching removed; loud red = 3px border only (2026-10-09)

The 45° caution stripes on `.event-conflict` proved too busy — reverted to
a plain `--color-error-container` tint, with the border thickened
2px → **3px** so own/personal conflicted + public-holiday classes still
read unmistakably (owner-gating unchanged: loud border = yours, quiet
plain tint = others' on cohort/venue; personal pages all loud). Legend
swatches follow automatically. Verified live on all four pages.

### Postscript — block tooltips show lecturer + status (2026-10-09)

- Event-block hover tooltips now read `"Subject · Lecturer · Status"`
  (e.g. `BMIT2073 · Ts. Norshikin Binti Zainal Abidin · Conflict`). The
  lecturer-only `tooltipExtra` hook was removed — the shared builder in
  ui-common now appends lecturer + run-status (`eventStatusLabel()`)
  uniformly on every timetable page.

### Postscript — §10.0 two-axis block language (2026-10-09)

- **Every block now carries the loud 3px border** — everything on this page
  is the viewer's own, so ownership borders don't apply here (the 3px vs
  0.5px hairline split only matters on the cohort/venue browse pages).
- Status colours stay: Normal = success green, Pending = tertiary,
  Replacement = **primary blue (kept on personal pages only)**,
  Conflict/Holiday = error red with its 3px border.
- Legend unchanged at 4 chips (Normal / Replacement / Pending /
  Conflict / Public Holiday); swatches now reuse the real block classes so they show
  the exact fill + border styling.

### Postscript — card flip-back keywords (2026-10-09, SDD: warning-modal-keywords)

The five summary-card flip-backs use the new keyword language — **YOUR TIMETABLE**, **CLASS
HOURS**, **DIFFERENT LECTURER** in the default color; **BEING PROCESSED** and **SCHEDULING
CLASHES AND PUBLIC HOLIDAYS** in red (attention cards).
## 2026-10-27 (SDD cancelled-blocks-stay-visible) — the cancelled block renders red for students
One shared trait change — the student timetable inherits the cancelled
block (red, `(Cancelled)` note) with the same lifecycle (stays through
pending; vanishes at the PL approval). The summary filter re-cut the
same way.

## 2026-11-15 — the same responsive timetable as My Timetable (read-only)
SDD lane `my-timetable-responsive-prompt`, phase B. One day of cards on a phone, two or three
days on a tablet, the week row pinned under the top bar, and a Week summary sheet with the
legend, the five cards and the semester range. Above 1280 nothing moved.

What stays this page's own: a card opens the details pop-up and nothing else (no cancel
wiring, no footer action), the cohort chip stays in the header, the summary card still reads
**Class Hours**, and a request's state is colour plus its date note — no new word. The
header's summary ⓘ is gone here too: below 1280 the Week summary button is the only door, and
`SummarySheet` is switched off on this page so the two cannot fight over `#summarySection`.

The cancelled tooltip keeps the short `CANCELLED`; the details pop-up's Status row reads
`Class Cancelled`, and a card announces its status in words to a screen reader.

### Prototype comparison, same day — the class pop-up names the cohort
The details pop-up now shows a **Cohort** row (the signed-in student's own group), using the shared
modal's existing extra-field hook. It deliberately stops there: the mockup also shows "Total Students",
and that headcount is other students' business, not the reader's.

### Two bugs fixed, same day — TODAY's date, and a holiday's classes
- **TODAY** lands on today's day at every width, not only in today's week (the strip used to keep the
  weekday you were reading, so "Thursday 26 Nov → TODAY" showed Thursday 19 Nov on a phone or iPad).
- **A public holiday** keeps its class cards, with the holiday notice above them: 9 Nov 2026 shows both
  its 11:00–13:00 class and its notice, instead of the notice alone below 768px.

### A pending proposal replaces the "Cancelled" word
- A cancelled block or card that already says **(Proposed ⏳)** no longer also says **✕ Cancelled**.
  A plain cancelled class keeps its word; a conflicted class with a proposal keeps both.

### Round 2 against the prototype — same nine fixes
- Week dropdown sized per band, every phone label fitting; tablet heading with both dates; a plain
  empty day that names its date; one panel for a whole empty week with the next class inside; a
  content-height Week summary with a Close at the foot; the help dialog in the prototype's layout.

### Lane closed
- The `my-timetable-responsive-prompt` lane was **archived 2026-11-16** to
  `.sdd/archive/2026-11-16-my-timetable-responsive-prompt/`. No behaviour change.
