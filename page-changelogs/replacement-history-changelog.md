## 2026-11-11 — the pop-up becomes the staff pop-up (Replacement Details)

The user's ask: the student-side modal should follow the same design and layout as the equivalent
staff-side modal, with four checks named — the footer, the removal of the Conflict Reason tab, the
course line in the subtitle, and "identify any other inconsistencies". Written and applied as the SDD
lane `student-modal-staff-parity` (`.sdd/changes/student-modal-staff-parity/`).

| | Before | After |
|---|---|---|
| Title | `BMIT2013 — Class Replacement` | **`Replacement Details`** |
| Subtitle | the module name alone | **`BMIT2013 — Web-Based Integrated Systems (Lecture)`** |
| Conflict | its own tab, long form | **a strip above the tabs**, `Conflict  Public Holiday — Deepavali Holiday (In Lieu)` |
| Tabs | Course · Original → Conflict Reason → Requested → Status | **Requested Slot Schedule · Original Slot Schedule · Class Info · Request Status (Approved)** |
| Slot rows | Week · Day · Date · Countdown · Time · Duration · Venue | **Class ID #24** / **Request ID #43** · Week · Day & Date (countdown in brackets) · Time & Duration · Venue |
| Footer | jump injected LEFT, Close RIGHT | **Close LEFT (outline) · Go to My Timetable RIGHT (blue)**, never flipping |

*The footer was the actual defect.* The jump used to be injected at the FRONT of a
`justify-content: space-between` rail, so on a row with a request it sat left of Close and on a row
without one there was no jump at all — the two buttons changed places. It is a static, gated button now
(`#stJumpTimetableBtn`), shown only when the timetable really carries the new slot.

Three facts had to travel to the page for the new **Class Info** tab: the class's cohorts (the staff
`DFT2 (S1) G1` convention, promoted out of a private method into `App\Support\CohortLabel` so there is
ONE formatter), its student count, and the short status the tab label names. Display facts only — a
student still never receives the lecturer's leave reason.

Two checklist items from `docs/Modal inconsistent Report checklist.md` were folded in, by the user's
choice: **B9** (the status tab label carries the short status) and **B5/B6** naming (`Request ID`,
`Class ID`). **B8** and **B10** were deliberately left out and are still open.

### The width, and the phone

Four tabs need 606 px, and the details-modal body only gave 570 px at the old 616 px shell — the fourth
tab was cut off. The user's ruling: **expand the modal**. The ONE-width law was kept, not broken, so
`.modal.modal--detail` and the three shells that set it moved **616 → 680 px** together.

On a phone the rail still needed 402 px inside 304 px, so the last tab was unreachable; the user asked
for it fixed in the same change. `.modal-tabs` now **wraps below 520 px** — every tab visible at any
width. Measured on the demo data (student `24RSD0012`, 10 rows, desktop 1440 and phone 390): all four
tabs render, nothing clips, the page never scrolls sideways, 0 console errors.

Receipts: `ReplacementHistoryRowTest` (+2 — the three new row keys and the short status),
`StudentReplacementHistoryParityTest` (re-pointed: the tab labels, the static footer, and the absence
of the `Course` / `Conflict Reason` headings), `ModalConsistencyTest` (the 680 law and every shell
agreeing). Full suite 539/540 — the one red is the pre-existing `ReplacementHistoriesTest` stale test.

## 2026-11-11 — the conflict badge is the kind alone; the detail hovers

User ruling (2026-11-11): the badge names only the KIND — **Public Holiday** or **Class Cancelled** —
and the detail moved into the badge's own hover. Colours, classes and sorting are untouched.

| Row | Badge | Hover |
|---|---|---|
| Holiday | `Public Holiday` (red) | the day's own name — "Deepavali Holiday (In Lieu)", "Christmas Eve" |
| Cancelled class | `Class Cancelled` (grey) | the reason label — "Annual Leave", "Compassionate Leave", "Official Event" |

Applied in one place per side: the table cell, the details pop-up and the phone card all build from
the same `reasonBadge()` / `reasonCell()` (the shared `HtmlBuilder.replacementHomeCard` gained a
`reasonTip` hook so the phone card carries the same tip).

Measured on the demo data: 12 badges on the staff page (6 table + 6 phone cards), every one short and
every one carrying its detail tip; the pop-up prints the same short badge. The student page gets the
holiday name only (`Deepavali Holiday (In Lieu)` / `Christmas Eve` / `Christmas Day`) — a cancellation
reason is the lecturer's own business, so those badges carry no tip by design (user's answer C).

Tests: `SlotBorderAndReasonBadgeTest` pins the two functions and the card hook;
`tests/replacement-home-status-badges.spec.ts` receipt 8 now asserts the short text + the hover detail
(13/13 green on the testing lane).

## 2026-11-11 — a student notice lands on Class Replacements, row flashing

User report: clicking a student notice went to the student **timetable**. It now goes to **Class
Replacements** — the page where the class and its request state sit in one row — and names the row to
flash, exactly like the staff side.

**Shape (staff parity)**

| Notice | Lands on | Anchor it carries |
|---|---|---|
| about a request (approved / rejected) | `/student-class-replacements` | `?notif-hl=r<request_id>` |
| a cancelled class (no request) | `/student-class-replacements` | `?notif-hl=o<session_id>&notif-w=<week>` |

The row id on that page is `<sessionId>:<week>`, so the `o` anchor resolves straight to it; the `r`
anchor now works because each row carries `data-request-id` (the request it belongs to). The shared
highlighter pulses the row with the same three-pulse flash the staff pages use.

**Arrival**: a row hidden by the saved week filter or by *Exclude COMPLETED Status* is revealed first
(the staff page's `jumpToMyRow` does the same), otherwise the flash would land on nothing. On the phone
layout the card is pulsed instead of the (hidden) table row.

Unchanged on purpose: the STORED link (old rows keep `/student-my-timetable-ui…` and are re-pointed at
read time), the read/unread stamping, the inactive/stale gates, the hide-in-place law for
`completed` notices, and every role's access.

Measured on a throwaway copy of the demo data (nothing written to the demo DBs):
  · 24RSD0012, notice #203 (approved, request 27): week filter was pinned to 7 → after the click the
    page opened on week 14 with the row `tr[data-request-id="27"]` flashing.
  · 25DSF0024, notice #265 (cancelled class, session 26): landed on the page with `tr[data-id="26:8"]`
    flashing.
  · both notices were stamped read; no other notification changed; 0 console errors.

Receipts: `tests/Feature/NotificationsBridgeTest.php` — a new test pins the landing page + both anchors
+ the untouched stored link, and the two pinned S8 tests were re-pointed to the new carrier (`notif-w`
now rides the anchor instead of the URL). 41/41 in that class.
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
## 2026-11-11 — a blank week box must not empty the table or the chip

User report: after cancelling a pending request the Active Filters bar showed
`Original Slot Week: ×` — the chip with **no words in it** — while the week box itself read
**All Original Slot Weeks**.

**Cause (measured).** The week `<select>` is built by JavaScript, so a page re-render can leave it
momentarily **unselected** (`value ''`, `selectedIndex -1`). Three readers treated that as a real week:

| reader | with a real week | with an unselected box |
|---|---|---|
| the chip | `Original Slot Week: Week 8 · 9 Nov 2026 ~ 15 Nov 2026` | `Original Slot Week: ` (blank) |
| the table filter | that week's rows | **0 rows** — "No classes currently need attention" |
| the ‹ › arrows | step week by week | ‹ does nothing |

The box then fell back to All a moment later, but the chip and the table stayed behind — the chip was
showing a filter that no longer existed.

**Fix — one reader, one labeller, one settled restore.**

- `ui-common.js` gained `weekFilterValue(id)` (**an empty box is not a week** — it reads as `all`) and
  `weekFilterLabel(id)` (the option's own text, else the `weekRanges` label for that value, so a chip
  can never print blank).
- `restoreWeekSelects()` no longer gives up when the saved value is empty or gone: if the box has lost
  its selection it settles on the neutral `all` option (else the first one) instead of staying on `-1`.
- `renderFilterChips()` / the table filter / `saveFilters()` read through those two helpers, and an
  empty value means **no week chip at all** — the truth, since no week filter is applied.

Measured on `/my-replacements` (demo data, nothing written):

    week 8 selected   → box "Week 8 · 9 Nov 2026 ~ 15 Nov 2026", 3 rows, chip names the week
    box unselected    → box "(nothing selected)", 6 rows (all), NO week chip     [was: 0 rows, blank chip]

The same three readers were fixed on Request Approval and Class Replacements, where the identical
pattern existed. `tests/week-filter-preserved.spec.ts` U3 now also forces the unselected state and pins
"no week chip + rows still there"; its two stale `Original Class Week:` assertions (left behind by the
SLOT rename) were corrected. Spec run: **6/6 under the blessed testing-lane invocation**.

## 2026-11-10 (later) — the pop-up tabs are ORIGINAL SLOT SCHEDULE and REQUESTED SLOT SCHEDULE

The four detail pop-ups named their two slot tabs after the *class*: `Original Class Schedule`
and `Proposed Replacement Schedule`. They now follow the column names — **`ORIGINAL SLOT
SCHEDULE`** and **`REQUESTED SLOT SCHEDULE`** — on Request Details, Replacement Details and the
student's Class Replacement pop-up (whose tabs were `Original Slot` / `Requested Slot` and now carry
the same "Schedule" word).

| pop-up | before | now |
|---|---|---|
| Request Details | `Proposed Replacement Schedule` · `Original Class Schedule` | `Requested Slot Schedule` · `Original Slot Schedule` |
| Replacement Details | `Proposed Replacement Schedule` · `Original Class Schedule` | `Requested Slot Schedule` · `Original Slot Schedule` |
| Student Class Replacement | `Original Slot` · `Requested Slot` | `Original Slot Schedule` · `Requested Slot Schedule` |

The left-most tab is unchanged (the requested slot still leads when one is proposed), the tab keys
(`replacement` / `original`) are unchanged, and the table columns stay the short `ORIGINAL SLOT` /
`REQUESTED SLOT`. The auto-group bucket heading in `ui-common.js` also says `Original Slot` now.

Measured: Request Details `["Requested Slot Schedule","Original Slot Schedule","Request Status
(Pending)"]`; Replacement Details the same with `(Rejected)`; student pop-up `["Course","Original Slot
Schedule","Conflict Reason","Requested Slot Schedule","Request Status"]`.

NOT changed: the notification tip text ("Original Class Schedule: Week 8 · …"), which is a stored
message body, not a tab. Renaming it would leave old rows reading one way and new rows another.

## 2026-11-10 (later) — the slot columns are ORIGINAL SLOT and REQUESTED SLOT

The two slot columns were named "Original Class" and "Requested Replacement" on the staff pages
(the student page already said "Slot"), so the same table read two ways depending on who was looking
at it. Now all three pages say **ORIGINAL SLOT** and **REQUESTED SLOT**.

The duration text also left both columns: the cell used to print `(1 hour)` / `(2 hours)` beside the
time range, which the range already implies. Both slots now read date (+ week) → countdown → time
range → venue, nothing else.

Everything that names those columns follows them:

| | before | now |
|---|---|---|
| Sort hint (Request Approval) | `… Original Class, Requested Replacement …` | `… Original Slot, Requested Slot …` |
| Week dropdown, "all" option (Request Approval) | `All Requested Replacement Weeks` | `All Requested Slot Weeks` |
| Week dropdown, "all" option (My Replacements, Student) | `All Original Class Weeks` | `All Original Slot Weeks` |
| Active-filter chip (Request Approval) | `Requested Replacement Week: …` | `Requested Slot Week: …` |
| Active-filter chip (My Replacements, Student) | `Original Class Week: …` | `Original Slot Week: …` |
| Header tooltips | `Original class the request refers to …` | `Original slot the request refers to …` |

The filter **values**, the saved view state and every hand-off key are untouched — only the visible
words changed, so filtering behaves exactly as before. The detail modals' tabs (`Original Class
Schedule`, `Proposed Replacement Schedule`) are NOT table columns and were left alone.

Measured: Request Approval headers `Original Slot · Requested Slot`, 0 `class-duration` spans in the
tbody, cell reads `Thu, 24 Dec 2026 (Week 14) 11:00 AM ~ 12:00 PM B100`; My Replacements
`Fri, 13 Nov 2026 (Week 8) 11:00 AM ~ 1:00 PM B111`; Student 10 rows, 0 durations, headers already
`Original Slot · Requested Slot`.

## 2026-11-10 (later) — the Active Filters bar gets the staff pages' "Clear all"

User request: the student page must carry the same Clear-all chip as the staff side, in the
same place. Markup is byte-identical to `replacement-home` / `request-approval`:
`<button type="button" class="filter-chip filter-chip-clear-all" data-tip="Remove every active
filter" onclick="clearAllFilters()">Clear all</button>` — pushed **last** in the bar and only
while at least one chip is showing (the staff guard `if (chips.length > 0)`).

`clearAllFilters()` resets the two filters this page owns (week → `all`, `Exclude COMPLETED
Status` → off), removes the saved `replacement-history-view` key so a reload cannot bring them
back from storage, and re-renders once. Because this page's whole script runs inside a
`DOMContentLoaded` closure, the function is exported (`window.clearAllFilters`) — the same
requirement that the chip × bug exposed earlier today.

Measured on the demo student 24RSD0009: with Week 8 + the toggle on, the bar reads
`Original Class Week: Week 8 · …` + `Exclude COMPLETED Status` + **Clear all** (last, correct
class and tooltip) over 2 rows; one click → `chips: []`, week `all`, toggle off, 10 rows, and
the bar (and the chip itself) disappears. Zero console errors.

Note on reload: the page's DEFAULT view is "exclude completed ON", so reloading after Clear all
brings that one chip back. **User ruling (2026-11-10, answer A): leave it that way** — Clear all
clears the current view, and a reload returns the page's documented default (the staff pages
behave the same with their own default). Do not make it sticky.

Receipts: `StudentClassReplacementsFixesTest` **4/4** (the markup byte-for-byte, the
`chips.length > 0` guard, the window export, and what the reset touches), red-checked by
removing the button (fails `test_the_clear_all_chip_matches_the_staff_pages`).

## 2026-11-10 — three bug fixes (user)

**1. No Today button on this page.** The week-nav include already passed
`'showTodayBtn' => true`; it now passes `false`, and the dead `#todayBtn` handler (the old
AD-10 block that jumped the week filter to today) went with the button. The arrows, the week
select and the filter behaviour are untouched — measured: `#todayBtn: 0`, `.today-btn: 0`,
arrows: 2, `#weekFilter` present.

**2. A filter chip now removes itself immediately.** Symptom: clicking the `×` on
**Exclude COMPLETED Status** turned the toggle off but the chip stayed. Cause: the chip's
inline `onclick` called `saveView()` — a function that lives inside the page's
`DOMContentLoaded` closure, so the inline handler threw a ReferenceError *after* unchecking
the box. Fixed properly rather than by exporting more names: the chips now carry
`data-remove="weekFilter|hideCompleted"` and ONE delegated listener on `#filterChips` does
the work, so it can see every page function. The week chip moved to the same delegate.
Measured: before × `chips: ["Exclude COMPLETED Status"]` → after × `chips: [],
toggleOn: false`; ticking the toggle again brings the chip back; setting Week 8 adds the
week chip (2 rows) and removing it restores all 10 rows; a table row still opens its pop-up.

**3. The LECTURER column carries the title.** It printed a bare name; it now prints
`Pn. Lee Yee Fong`, `En. Lim Jia Zheng`, `Dr. …`, from the real `users.honorific` column via
the ONE reader `User::displayName()`. A lecturer with no honorific still reads as the bare
name (no stray space).

Receipts: `ReplacementHistoryRowTest` +2 (title present / absent), new
`StudentClassReplacementsFixesTest` **2/2** (no Today button; chips use one delegate), both
**red-checked** — restoring `showTodayBtn => true` and re-adding the inline chip handler each
fail their test. Targeted PHPUnit 32/32, pint passed, phpstan 0, `view:cache` OK, page script
`node --check` OK, zero console errors on the demo student `24RSD0009`.

**Card labels + hours meaning (user, same day — supersedes R8):** the first two cards now read
**`Classes Awaiting Arrangement`** and **`Hours Awaiting Arrangement`**, and the hours number counts the
class hours of **only the rows with no live request** — an arranged class is no longer "awaiting", so its
hours do not sit in that number. R8 ("every visible row, arranged or not") is retired by this ruling;
measured on `24DFT0001`, the card reads `6` from five unarranged rows of 2+1+1+1+1 hours, while the old
total of every visible row would have been 8.

**Card order (user, same day):** `Class Hours Affected` moves to the **second** slot, right of
`Awaiting Arrangement` — the two numbers a student reads first are "how many classes still need arranging"
and "how much teaching that is". New order: `Awaiting Arrangement` · `Class Hours Affected` · `Pending` ·
`Approved` · `Completed`. Display only; the counts are unchanged, and the parity test now pins the order.

**Follow-up the same day (the H1 the rename missed):** the tab title, the nav label and the address
were renamed in `758a7bc`, but the on-page `<h1 class="page-title">` comes from a different line — the
`ui-page-header` include's `title` argument — and it kept saying "Replacement History" on both addresses.
It now reads **Class Replacements**, and `StudentReplacementHistoryParityTest` pins that include line so the
three names cannot drift apart again.

## 2026-11-09 (SDD `student-class-replacements`) — the page lists conflicted CLASSES, and is now "Class Replacements"

**User brief:** "New Idea — Student Replacement History Redesign". Eight grilling rulings, all the
user's; proposal, design and tasks frozen after three review rounds.

**What the page was.** It listed *requests* — so it could only show classes somebody had already asked to
move. A class the lecturer cancelled, or one that falls on a public holiday next week, was invisible until
a request existed, even though the student's timetable said the class was gone.

**What it is now.** One row per **conflicted class occurrence** — a `class_exceptions` cancellation **or** a
calendar holiday, request or no request. Measured on the demo data that is 6–11 rows per student, most of
them holiday rows; the demo has very few exceptions because lecturers create those for their own sessions.

| Area | Ruled behaviour |
|---|---|
| Rows | cancelled class **or** holiday, arranged or not (R1) |
| Statuses | no request / cancelled request / rejected request → **`Awaiting Arrangement`**; otherwise the staff labels `Pending — Waiting for Approval`, `Approved`, `Completed — 1 day ago` (R2) |
| Week filter | keys on the **class's** week; All-option **`All Original Class Weeks`** (R3) |
| Toggle | `Exclude COMPLETED Status`, ON by default, decided by the **make-up date** (R4) — a class with no make-up never counts as completed, so it stays listed |
| Identity | **Class Replacements** at **`/student-class-replacements`**; `/replacement-history-ui` stays alive as a second address (R5) |
| Read-only | no action column, no arrange/cancel/approve; the pop-up carries ONE control — **Go to My Timetable** — only on a row with a requested slot (R6) |
| Cards | `Awaiting Arrangement` · `Pending` · `Approved` · `Completed` · `Class Hours Affected`; `Total` dropped (R7/R8) |
| Hours | the class hours of **every visible row**, arranged or not (R8) |

**One word differs by audience.** `ReplacementRequestMapper::statusLabel()` gained an optional `$audience`
argument; `'student'` prints `Awaiting Arrangement` where the staff page prints `Need to Arrange` (an
instruction nobody on a read-only page can follow). Every other label is shared on purpose —
`statusVocabulary()` forwards the audience so a page asks once. Staff bytes unchanged.

**The jump.** `goToReplacementInTimetable()` gained an optional base path (both staff call sites untouched)
and `applyReplacementHighlight()` now accepts `/student-my-timetable-ui` as well as `/my-timetable-ui` — the
grid already stamps `data-rid` on replacement blocks on both pages. Measured: the button lands on the
student timetable, switches to Week 8 and pulses the block carrying request 27.

**Copy that died with the rewrite** (absorbing the parked `student-history-copy-polish` lane): the empty
state no longer says "this week" / "the selected week" (the default view is all weeks), the page description
names conflicted classes instead of "confirmed or pending", and the `Completed` card's hover text explains
why it reads 0 while the toggle is on.

**Also fixed while here:** the shared modal's overlay calls `closeModalOutside(event)` from an inline
attribute, and this page never exported that function — every overlay click threw
"closeModalOutside is not defined". It is exported now (pre-existing on this page, surfaced by the new
button's click path).

**Receipts.** `ReplacementHistoryRowTest` rewritten — 9/9 (holiday with no request; cancelled class, no leave
type; pending prints its real span; cancelled and rejected leave the class unarranged; the latest request
wins; completed follows the make-up date with the clock frozen; a normal occurrence is not a row; the
original slot is the class's own). `ReplacementMapperStatusLabelTest` new — 3/3. `StudentReplacementHistoryParityTest`
rewritten — 9/9 source invariants (columns, five cards, the unarranged badge and rank, the forbidden
vocabulary, filter basis, toggle, read-only + the one control, route/label). Nav and route tests moved to
the new address with the alias still gated. Browser: both demo students, desktop and 390 px, zero console
errors.

**Honest notes.** The list is holiday-heavy by ruling; an unarranged class never becomes "past" (R4's
accepted consequence); and the student page now speaks two vocabularies on purpose (`Awaiting Arrangement`
here, `Need to Arrange` on staff) — one mapper arm, no drift.

## 2026-11-09 (user brief "Student Side UI Update") — the student history joins the staff table

Fourteen grill rulings, all from the user the same day. The page now speaks the staff My Replacements
table's language while keeping the student's own permissions (view only, no actions, no cohort column).

**Table:** six columns, in the user's order — `COURSE` · `LECTURER` · `ORIGINAL SLOT` · `CONFLICT
REASON` · `REQUESTED SLOT` · `REQUEST STATUS`. The row-number `#` column is gone (it lied as soon as
you sorted), `SUBJECT` became `COURSE` (two-line cell: code, then name with `(L)`; hover explained
once on the header, not repeated per cell), `NEW SLOT` became `REQUESTED SLOT`, and `STATUS` became
`REQUEST STATUS`.

**Both slot columns** print the staff 4-line block: day, date and academic week → the countdown →
time with duration → venue icon and code. The countdown follows the staff law exactly: the ORIGINAL
slot always carries one (the class is what still needs a slot); the REQUESTED slot carries one only
while the request is PENDING — once decided, a past slot would read "Overdue" for a replacement that
already happened. Urgency is the shared working-day calendar, holidays included.

**CONFLICT REASON** is the CLASS's cause, computed with the staff page's own rule (exception reason
title-cased, `official_event` on a holiday occurrence reading as the holiday). **A student never sees
the leave type**: a calendar holiday keeps its real name (`Public Holiday — Deepavali Holiday (In
Lieu)`), every other exception reads the bare `Class Cancelled` (user ruling — "Medical Leave" is the
staff member's private business). No cause at all prints an em dash, never a guess.

**PENDING rows now show the slot they asked for** (was `Awaiting PL approval`). The request holds a
real slot, and the PL's approval confirms THAT slot rather than choosing a new one — so the column
prints it, with the Pending badge still saying it is unconfirmed. The pop-up's slot tab follows.

**REQUEST STATUS** uses the staff badge design and the staff's long labels, server-built: `Pending —
Waiting for Approval`, `Approved`, `Completed — 1 day ago`. The student-only `Replacement` word and
the extra `Past` chip are gone. `COMPLETED` is the derived status — the requested DATE has passed —
so badge, filter and summary can never disagree.

**Toolbar:** `Show Past` became the staff's `Exclude COMPLETED Status` (ON by default, same tip, same
remembered state; an old saved `showPast` migrates). The page gained the Active Filters bar the
student side never had, naming the requested-replacement week and that toggle. The All-Weeks option
reads `All Requested Slots Weeks` (the user's wording, same day) because that is the date the filter keys on.

**Cards:** the mobile card keeps its anatomy but STACKS the two slots (each is 4 lines now) and adds
the conflict reason; the summary card `Past` is `Completed`, counted by the same date rule.

**Parked by ruling (not bugs):** cancelled/rejected requests stay off this page (2026-11-08 ruling);
the staff toolbar's search, status filter, result count, reset and rows-per-page stay on the staff
page (§8 report only). SDD exploration findings are reported separately.

Receipts: `ReplacementHistoryRowTest` rewritten — 6/6 (the class's own week/day/time; the requested
run's true span; a pending row now ships its slot; completed derived from the requested date with the
clock frozen; a holiday named; a leave type never reaching the student). New
`StudentReplacementHistoryParityTest` — 7/7 source invariants (column order, no `#`, the staff toggle,
the All-Weeks wording, the Completed card, the `status-*` badge, the countdown gate, the guarded
reason). Browser: both demo students on desktop and 390 px, zero console errors; hours card, chips,
week chip and pop-up verified.

## 2026-11-08 (Student-side health check S7, user's option A) — the page opens on All Weeks

The week filter used to open on the **current** week, and it hides every other week. A student whose
only replacement sits later saw an empty table and `0 / 0 / 0` cards on arrival — measured on both
demo students: 24RSD0009 (approved replacement in week 8) and 24DFT0001 (pending request in week 9)
both opened on "Week 7" with 0 rows.

Now a first visit opens on **All Weeks**: 1 row and `Total 1 · Upcoming 1 · Hours 1` (RSD) /
`Total 1 · Pending 1 · Hours 2` (DFT). Unchanged, on purpose:

- **"Show past" still starts off**, so the list does not grow with history until asked.
- the **week arrows** and the **Today** button still narrow the view to one week on demand
  (Today = this week, rows 0 when nothing happens this week — an explicit choice, not the default);
- a **saved filter still wins** over the default (the page remembers the last choice, and that is a
  deliberate one).

Receipts: `tests/Feature/ReplacementHistoryDefaultWeekTest.php` (2 source invariants: the default is
`'all'`, and it is no longer `currentWeekIndex()`; red-check fails both), browser receipts on both
demo students with zero console errors (`/tmp/opencode/audit-history-allweeks-24RSD0009.png`,
`…-24DFT0001.png`), pint passed, phpstan 0, `view:cache` OK.

## 2026-11-08 (Student-side health check S10 + S11) — the Original Slot is the class's own, and the New Slot shows its whole run

Two wrong values on one row (request 27, BMSE2163 P), found by re-reading the brief:

**S10 — the Original Slot column described the replacement, not the class.**
It printed `Week 8 · Fri, 13 Nov 2026, 12:00 PM ~ 1:00 PM`, because the payload stored
one `week` + `di` (the replacement slot's) and the column reused them. The class actually sits in
**week 14, Thursday 24 Dec 2026**, 12:00–13:00. A new `originalWeek` carries the class's own week,
`di`/`start`/`end` now come from the class's own day and times, and the four readers (sort key,
desktop table, mobile card, detail modal) use them. Side effect: the **Hours** card counted the
replacement's length, not the class's.

**S11 — the New Slot time was cut at the first 30-minute block.**
`1:00 PM ~ 1:30 PM (1 hr)` for a 13:00–14:00 replacement: the duration was computed from the true run
(two cells) while the time string was read from the anchor cell alone. The string is now built from
the same span.

Now the row and the modal read exactly what the staff side reads:
`Original = Week 14 · Thu, 24 Dec 2026, 12:00 PM ~ 1:00 PM @ B010` and
`New = Week 8 · Fri, 13 Nov 2026, 1:00 PM ~ 2:00 PM @ B010`.

Receipts: `tests/Feature/ReplacementHistoryRowTest.php` (3 tests: the class's own week/day/blocks,
the new slot's full run, and a pending row that keeps the original slot but shows no new slot).
Red-check: restoring the old mapping fails all 3. Browser on the demo data — row + modal correct,
Hours card 1, pending row (DFT student) shows its class's real week 8, zero console errors. pint
passed, phpstan 0, `view:cache` OK.

## 2026-11-08 (scope ruling — no code change) — the page lists PENDING + APPROVED only, by decision

A Student-side health check (SDD `student-side-health-check` S3) found that cancelled and rejected
requests never appear here — the component's own `whereIn('status', ['pending','approved'])`. Example
from the demo data: a cohort with one approved and one cancelled request shows **one** row.

**The user's ruling: keep it that way (option A).** The reasoning: the timetable already shows the
cancelled class as a red block with its reason, so a student is never misled about whether the class
runs; this page is a list of *outcomes*, not of every attempt. The staff equivalent (My Replacements)
still keeps cancelled rows, because a lecturer acts on their own request — a role difference, not a
bug.

Do not "fix" this without a new ruling.

## 2026-11-07 (date/time batch 3, `docs/date-time-format-audit-2026-11-07.md`) — slot dates lose the leading zero

The Original/New Slot dates read `5 Nov 2026` (was `05 Nov 2026`), matching the rest of the app.
Same shared day scaffold as the grids, so the date, week tag and sorting are unchanged.

## 2026-11-07 (date/time batch 2, `docs/date-time-format-audit-2026-11-07.md`) — Original/New Slot join with `~`

Both slot columns and the detail modal's Time row printed `12:00 PM to 1:30 PM`. They now read
`12:00 PM ~ 1:30 PM`; the mobile history card follows. Dates, week tags, sorting and the empty
states are untouched.

## 2026-11-04 — the page description speaks the same visual language as My Replacements

Minor UI update (user, all pages): the `.page-desc` line now bolds and uppercases its key terms with
the existing `.info-keyword` class — the convention the merged My Replacements page introduced. The
words are unchanged; only the emphasis markup is new (`description` → `descriptionHtml`), so the
rendered sentence reads identically.

# Changelog — Upcoming Replacements (Student)

## [2026-11-02] Timeline rail temporarily removed (extra task 02)

The modal's "Submitted / Under Review / Awaiting Replacement" rail printed a
**fake date** — `01 Sep 2026, 09:15 AM` — because its time came from the mock
`MockData.cohortTimetable.rsd3g2Flags` map (`:329`), which the backend ships
empty (`ReplacementHistory.php:59`), so the fallback literal always won. The
modal's own *Requested At* row (`:383`) was correct all along, which is what made
the rail look plausible. The rail is **commented out** (no fake data beats a
missing decoration) and the proper fix — one dynamic rail driven by
`submitted_at` / `decided_at`, shared with the lecturer and class-detail modals —
is parked as `prompts/extra tasks/task-02-replacement-history-timeline.md`.
No other page, payload or data behaviour changed; the modal keeps its Status
group, Requested At row and Remarks.

## [2026-10-09] Shared nav whitelist (SDD student-nav-label-parity)

This page previously passed its own `navItems` override ("Student My Timetable" / "Replacement History" — mock-era labels), which made the nav differ from the rest of the student side. The override is deleted: the top nav now comes from the shared role whitelist — **My Timetable** + **Replacement History** — identical on both student pages. The page body remains mock-era static until Wave 3b wires the `ReplacementHistory` component.

## [2026-08-24] Stub page + route + mock data (TASK-006 Phase 1)

Student role drops "Request History" and gets "Upcoming Replacements" instead
(Ch1 §1.1.4 view-only; FR 1.4 upcoming replacement details). UI design deferred to
Sprint 3 per `todo list/upcoming-replacements-ui-plan.md`.

### Files Created

#### `resources/views/ui-design-templates/upcoming-replacements-UI-design-template.blade.php`
- Stub template: `@extends('layouts.ui-template')` with the 2-item student `navItems`
  (Student My Timetable + Upcoming Replacements), `pageKey='upcomingReplacements'`
- Reuses `partials.ui-page-header` + `partials.ui-empty-state` ("UI design pending")
- Semester chip + notif badge init copied from sibling student page pattern

#### `page-changelogs/todo list/upcoming-replacements-ui-plan.md`
- Implementation Details doc for the upcoming UI build (data contract, status→color map,
  OOP reuse inventory, openClassModal wiring, layout sketch, hard rules, checklist)

### Files Modified

| File | Change |
|---|---|
| `routes/web.php` | Added `GET /upcoming-replacements-ui` → stub view (`activeNav='upcoming-replacements'`) |
| `public/js/mock-data.js` | New §2.12 `upcomingReplacements` — 3 rows keyed to real `rsd3g2Base` courses + `rsd3g2Flags` weeks (BMIT7074 w1 approved, BMIT2233 w2 pending/no venue yet, BMIT5678 w7 approved); grid-compatible `di/start/end` index space |
| `resources/views/ui-design-templates/student-my-timetable-UI-design-template.blade.php` | Nav item `replacement-history` → `upcoming-replacements` |

### Key Decisions
- `/my-request-history-ui` route kept — lecturers/PLs still use it; only the student nav link is replaced.
- Status vocabulary mirrors timetable legend: `replacement`=approved-upcoming (blue), `pending`=awaiting PL (yellow) — `.badge-replacement` / `.badge-pending`.
- Pending rows carry null new-slot fields so the UI can show "Awaiting PL approval" without inventing data.

## [2026-10-02] Cross-page refactor — shared `syncSummarySection` helper

The summary auto-hide logic added above was promoted to `ui-common.js` (`syncSummarySection(visible)`, §10.0 rule 7) and is now used by my-request-history, replacement-home and request-approval too; this page's local 2-liner was replaced with the shared call. Behavior unchanged (re-verified: default/empty/restore all pass).

## [2026-10-02] User revision — empty state above summary; summary hidden when empty

- The `ui-empty-state` include moved **above** the summary strip in the DOM.
- When the view is empty, `#summarySection` (5 cards + hint) is **hidden entirely** — nothing to summarize (house precedent: my-request-history hides its summary bar on empty); it restores on any non-empty view. Verified: Week 7 → empty state shown + summary hidden; Week 10 → summary back + empty state hidden (and positioned above summary in the DOM); 0 console errors.

## [2026-10-02] User revision — modal slots as data-label/data-value rows

Slot info in the modal is no longer one long string: each fact gets its own definition row, grouped per slot — **Original Slot**: Week 12 / Day Wednesday / Date 14 Oct 2026 / Time 2:00 PM to 4:00 PM / Duration 2 hrs / Venue A104 — and the same block for **New Slot** (pending: single "Awaiting PL approval" row with the muted PL explanation). Modal groups are now Class · Original Slot · New Slot · Status (badge + Requested At/Remarks + past Status Note). Full day names from the row data; time/duration math shared with the list (**Duration rows now "x hours y minutes"** — singular/plural, zero parts omitted: "2 hours", "1 hour 30 minutes"). The transient `.slot-line` chip CSS was removed (unused). Verified: approved modal = 4 groups × (4+6+6+2) rows, pending = (4+6+1+2), 0 console errors.

## [2026-10-02] User revision — modal slot rows as full-width blocks

Long slot strings no longer cram into the narrow label/value column: Original/New Slot rows stack (label above, value below) and the slot renders as a full-width highlighted chip (`.slot-line`, page-scoped CSS using `:has()` so only rows carrying it are affected — other rows keep the standard label/value layout). Pending New Slot keeps the inline muted PL explanation inside the chip. Verified: 2 slot rows stack column-direction, chip spans the modal body width, 0 console errors.

## [2026-10-02] User revision — modal slimmed (no Requested By; Awaiting tooltip)

- **"Requested By" row removed** (the lecturer already appears in the Class group) — the pending-only "Request" group dissolved; "Requested At" moved into the Replacement group. Modals now have exactly 2 sections: Class · Replacement.
- **"Awaiting PL approval" shows its description inline** (user: a modal should show detail, not hide it behind hover): the value now reads "Awaiting PL approval *— the Programme Leader (PL) has not confirmed this replacement; the new slot is not decided yet*" with the sentence in the shared `detail-value--muted` variant (replaces the previous tooltip attempt).
- Verified: pending modal = 2 groups / 8 rows (Requested By gone, tooltip live), approved modal unchanged, 0 console errors.

## [2026-10-02] User revision — modal grouped by category

The detail modal on upcoming-replacements-ui showed ~14 flat rows (overwhelming). Added an **additive `cfg.groups` option to the shared `openClassModal`** (one `DetailModal.section` per group; omit → original flat behavior, verified intact on student-my-timetable). The page now renders 3 tidy sections — **Class** (code, name, type, lecturer) · **Replacement** (status badge incl. holiday-Conflict remap parity, Original/New Slot with the new date format via shared `fmtSlot`, Status Note for past rows, Remarks) · **Request** (pending only: Requested At/By + Submitted→Under Review→Awaiting timeline). Dropped: redundant Status Description row; no View Full Request (AD-12 unchanged). `fmtSlot` hoisted to a top-level page helper shared by list + modal. Verified: pending/approved/conflict modals, 3 groups render, 0 console errors.

## [2026-10-02] User revision — summary cards v2 (Upcoming/Past/Hours replace Lectures/Tutorials)

Lectures/Tutorials cards dropped as low-value; the strip is now **Total · Upcoming · Pending · Past · Hours** (Approved removed — it duplicated Upcoming whenever Show Past is off). Upcoming = confirmed replacements with week ≥ current week (blue); Past = confirmed & already taken (**grey** `card-hours` variant — green read too close to Total); Hours = Σ slot durations of the visible rows (neutral). All still derive from the same visible predicate (AD-11). Verified: Week 10 = 2/0/2/0/3.5 · All+Past = 18/2/9/7/34 · All no-Past = 6/2/4/0/11 · Week 12 = 2/2/0/0/4; 0 console errors.

## [2026-10-02] User revision — slot time format

Original/New Slot cells (table + mobile cards) now render as: **`Week 14 · Fri, 30 Oct 2026, 12:00 PM to 1:30 PM (1.5 hrs) @ B103`** — full "Week N" prefix, `Fri,` day comma, 12-hour times joined with "to" (shared `to12h`), computed duration from the 30-min index space (`(end−start+1) × 0.5 hrs`, "1 hr" singular handled), `@ venue`. One shared `fmtSlot` helper serves both table (withWeek=true) and cards (withWeek=false — the card's week chip already carries the week). Verified: pending + approved rows, both duration variants present in data (1.5/2 hrs), 0 console errors.

## [2026-10-02] Promoted to shared — closeModal (§10.0 rule 7)

Audit triggered promote-on-3rd: the `ui-class-detail-modal` partial hardcodes `onclick="closeModal()"`, forcing every page to define a global — 7 pages did, 3 byte-identical. **Additive promote:** one shared `closeModal()` (targets `#classModal`) added to `ui-common.js` Modal-helpers block; existing pages' local copies override it (they load later, so nothing breaks); upcoming-replacements dropped its copy and keeps only the `closeModalOutside`/`closeOnEsc` wiring (those helpers were already shared). Remaining 6 pages' copies = recorded tech debt for a future cleanup change. Verified: shared function active on the page, modal × / `closeModal()` / Esc all close correctly, 0 console errors.

## [2026-10-02] User revision — persisted view state

Week selection + Show Past toggle now persist across refreshes via `localStorage` (`upcoming-replacements-view`, house pattern cf. my-request-history `saveFilters`/`restoreFilters`). Restored on load after the shared `populateWeekSelect` (validated against existing option values; `all` persists too); arrows recompute post-restore; Today button still returns to the current week and persists that. First-ever visit (no saved state) still lands on the current week, preserving the frozen load-state criterion. Verified: Week 3 + past → reload keeps both; All Weeks → reload keeps; Today → reload stays on Week 10; 0 console errors.

## [2026-10-01] User revision — slot dates, subject type, # column

- **Original/New Slot cells show real dates + week**: `Wk 10 · Thu 01 Oct 2026, 14:00 – 16:00 · A106` (dates derived from `generateWeekData()[r.week].days[di]` — built once per render; pending new slots stay "Awaiting PL approval"). Mobile cards show the same dates (week digit stays in the card chip).
- **Subject shows code · name (Type)**: e.g. `BMIT7075 · Mobile Application Development (L)` — table and cards.
- **Week column removed; `#` row-number column at leftmost** (per-view sequence 1…N).
- Modal unchanged (it already has dedicated Day/Date rows). Verified all three in browser, 0 console errors.

## [2026-10-01] User revision — status-badge tooltips

All status badges (table + mobile cards) now carry house-pattern `data-tip` tooltips (shared `initDataTipTooltips` hover system, zero new CSS/JS): **Replacement** → "Confirmed — attend the new slot shown", **Pending** → "Awaiting PL approval — the new slot is not confirmed yet", **Past** → "This replacement has already taken place". The **Show Past** toggle got a tooltip too ("Show replacement classes from weeks that have already passed"). Verified all on hover, 0 console errors.

## [2026-10-01] User revision — "All Weeks" option in the week selector

Week selector gains an **All Weeks** option (page-side only — prepended after shared `populateWeekSelect` so the value-based Week-10 load state is preserved; no `ui-common.js` changes). Safe with the shared arrow helpers because they are `selectedIndex`-based: at All, `‹` disables and `›` walks into Week 1; Today still jumps back to the current week. `renderUpcoming` branches on `value === 'all'` (shows all rows subject to the Show-Past predicate, sorted week → day → time). A **Week column** (digit only, e.g. `10`) was added to the table and a matching digit chip to mobile cards so All-view rows stay identifiable. Verified: load state intact (Week 10, 2 rows); All + toggle off = 6 future rows (6/2/4/3/3); All + past = 18 rows (18/9/9/10/8); arrows/Today/card chip all correct; 0 console errors.

## [2026-10-01] User revision — 5 summary cards (Combo A)

Summary strip expanded from 2 to **5 cards**, still derived from the same visible-rows predicate as the list (AD-11): **Total** (`card-total`, green) · **Approved** (`card-approved`) · **Pending** (`card-pending`) · **Lectures** (L count, `card-replacement` blue) · **Tutorials** (T count, `card-hours` neutral). Reuses existing theme.css color variants only (zero new CSS); Lectures/Tutorials get explicit hover descriptions via the partial's `description` override (their borrowed classes' defaults describe other meanings). Verified: Week 10 = 2/0/2/1/1, Week 12 = 2/2/0/2/0, Week 2 (past) = 3/0/3/2/1; 5 cards fit mobile without overflow; 0 console errors.

## [2026-10-01] User revision — table on desktop, summary below, Today pinned to week nav


Three user-directed tweaks to the Sprint-3 build (all page-scoped; no shared files touched):

| # | Change | Implementation |
|---|---|---|
| 1 | Desktop shows a **table** instead of cards; cards become **mobile-only** | `@include('partials.ui-grid-table', [...])` (house pattern, cf. my-request-history) with JS-built thead (Subject / Original Slot / New Slot / Lecturer / Status) + tbody rows (`role="button" tabindex="0"`, click/Enter/Space → same modal); `.upcoming-list` hidden on desktop, shown ≤768px where `#upcomingGridWrapper` hides (`.card-view` switching pattern) |
| 2 | Summary bar moved **below** the table/cards | `ui-summary-bar` include relocated after the list containers |
| 3 | Today button **sticks to the right of the week nav** (was floating mid-toolbar via shared `space-between`) | Page-scoped `.toolbar { justify-content: flex-start; }` override — week-nav → Today → Show Past group left-aligned |

Re-verified: desktop table renders with correct rows/badges/Past chip, row click + keyboard open the modal, empty week hides the table, mobile keeps cards + 44px targets, summary below on both breakpoints, 0 console errors.

## [2026-10-01] Sprint 3 build — real Upcoming Replacements UI (TASK-006 complete)


Built the student-facing page for real per SDD change `upcoming-replacements-ui`
(stub → full page). Two plan corrections surfaced during SDD — the §4 modal
snippet was missing required `event.start`/`event.end`, and the days API is
`generateWeekData()[w0].days` (the plan's `weekDays()` call was pseudocode) —
both recorded in `todo list/upcoming-replacements-ui-plan.md` §4.

### Files Modified

| File | Change |
|---|---|
| `resources/views/ui-design-templates/upcoming-replacements-UI-design-template.blade.php` | Full rebuild of the Phase-1 stub: toolbar (shared `ui-week-nav` + today button + show-past `.toggle-wrapper`), `ui-summary-bar` (Approved/Pending counts, student-context description), JS-rendered card list, `ui-empty-state`, shared `ui-class-detail-modal`. Page CSS (`.upcoming-card*`, ≤768px single column). Page JS — top-level `renderUpcoming`/`openReplacementModal`; init calls `populateWeekSelect('weekFilter', {selected: currentWeekIndex()+1})`; page-side today wiring per AD-10; delegated card click/Enter/Space (`.upcoming-card` is `<button>`); page-side `closeModal`/`closeModalOutside`/`closeOnEsc` per house pattern. Removed the inert `#copyToast` div (stub leftover). |
| `public/js/mock-data.js` | §2.12 `upcomingReplacements` 3 → 18 rows: 8 new pending ids 4–11 + 7 new replacement ids 12–18, all keyed to real `rsd3g2Base` slots + `rsd3g2Flags` weeks/dates so grid ↔ list never contradict. **Data fix:** pre-existing row id 3 (wk7 BMIT5678) `originalTime` corrected '09:00 – 12:00' → '09:00 – 11:00' — grid formula `hours[end+1]` for di2/start2/end5 gives 11:00, and the row's own modal recomputed 11:00, so the card contradicted its modal. |
| `public/css/theme.css` | +1 class `.badge-past` — neutral Past qualifier chip using `--color-surface-variant`/`--color-on-surface-variant`, §10.0 rule 2 (no new hex/rgb). |
| `page-changelogs/todo list/upcoming-replacements-ui-plan.md` | Status flipped to built (plan rule 8); two plan corrections appended to §4. |
| `page-changelogs/todo list/todo-list.md` | TASK-006 status `in_progress` → `completed`. |

### Key Decisions
- Load state defaults to the current week (Week 10 = 2 pending cards, 0 approved / 2 pending),
  selected via `populateWeekSelect`'s `selected` option rather than post-init dropdown writes.
- Pending modal `requestedAt` comes from `rsd3g2Flags` with the grid-default fallback
  '01 Sep 2026, 09:15 AM'; list modals carry no "View Full Request" footer button (students see
  status, not requests — staff-only affordance).
- Past (completed) replacements stay queryable: show-past toggle in the toolbar, neutral
  `.badge-past` chip + Past chips on cards, Status Note in the modal — approved-upcoming view
  stays the default.
- Page-side `closeModal`/`closeModalOutside`/`closeOnEsc` follow the house pattern of sibling
  student pages; only page-specific JS (`renderUpcoming`, `openReplacementModal`) lives in the page.
- Fully keyboard accessible: cards are real buttons (Tab + Enter/Space), delegated click handler.
- Mobile ≤768px: card grid collapses to a single column.

### Footnote
Pre-existing wk1 BMIT7074 row points at new venue B005, which is a Lab (P-only) carrying a
T class — pre-existing Phase-1 data, out of scope for this change.

### Verification
All 9 proposal §6 criteria pass in-browser:
- [x] Load state — Week 10 default, 2 pending cards, summary 0 approved / 2 pending
- [x] Pending modal `requestedAt` from flags with grid-default fallback '01 Sep 2026, 09:15 AM'
- [x] No "View Full Request" footer on list modals
- [x] Show-past toggle + Past chips + modal Status Note
- [x] Summary bar recomputes with filters (Approved/Pending)
- [x] Unflagged weeks show the empty state
- [x] Keyboard accessible (Tab to cards, Enter/Space open modal, Esc closes)
- [x] Mobile ≤768px renders single-column cards
- [x] 0 console errors

## Post-2026-10-02 cross-reference

The hardcoded badge feed built with this page (`notifBadge.textContent = … notificationCount`, `'notifCount' => 3`, and the server-side "3" echo) was removed by the `notifications-panel` change — the bell badge is now computed by `refreshNotifBadge()` in `ui-common.js`; see `notifications-panel-changelog.md`.

### Postscript — sweep-fixes-round-3 (2026-10-06, RENAME + sorting)

1. **Page renamed** "Upcoming Replacements" → **"Replacement History"** (route
   `/replacement-history-ui`, nav key `replacement-history`, pageKey
   `replacementHistory`): the list was never only upcoming — Pending + Confirmed +
   Past rows with `Show Past` and the Past summary card. Legacy localStorage view
   state under `upcoming-replacements-view` migrates once to
   `replacement-history-view`. Changelog + plan doc `git mv`'d to match.
2. **Sortable columns** (house pattern via shared `makeSortableHeader`): Subject,
   Original Slot (week→day→time), New Slot (awaiting-PL rows sort last), Lecturer,
   Status (Pending first, then confirmed; chronological tiebreak). `#` stays a row
   number and re-numbers under any sort; desktop table + mobile cards share one
   ordered array. Sort state is session-only.
3. **Fix: `All Weeks` de-duplicated** — the page historically hand-inserted its
   `All Weeks` option via a page-side IIFE *after* `populateWeekSelect`; when the
   shared helper's `includeAll` flag was switched on for this page both ran and the
   dropdown showed two. Single writer now = `populateWeekSelect(…, { includeAll: true })`
   (the flag already existed); the IIFE is gone, which also means the option survives
   the 768-breakpoint re-populate (the hand-inserted one silently vanished there).

**Addendum (2026-10-06, later same day):** the duplicate `All Weeks` fix above
superseded the page's own insert-IIFE entirely; default load keeps the current week
("Week 11") and a previously saved `All Weeks` view restores from VIEW_KEY.

---

## [2026-10-07] Class modal groups → tabs

The class detail modal's four grouped sections (Class / Original Slot /
New Slot / Status) now render as a **tab bar** via the shared
`renderModalGroups` helper instead of one long stacked body.

### Postscript — card flip-back keywords (2026-10-09, SDD: warning-modal-keywords)

The five summary-card flip-backs use the new keyword language — **YOUR COHORT**, **STILL
AHEAD**, **ALREADY TAKEN PLACE**, **HOURS** in the default color; **WAITING FOR PL APPROVAL**
in red (attention card).

### DB wiring — cohort-scoped approved replacements, Slice B task 2.4 (2026-10-10, SDD: wire-backend-into-refactored-ui)

Now serves real data via `App\Livewire\ReplacementHistory` (D10 registry swap;
legacy mock template untouched for the fallback). Contract (FR 1.4): approved
requests whose original class covers MY cohort, replacement slot week ≥
current, view only (RBAC — students never act on requests).

- **Columns** — Course, Original Class (day/time + replaced week), Replacement
  Class (`badge-replacement` + day/time), Week, Venue, Approved By.
- **Scoping** — `whereHas('classSession.cohorts', my cohort)` + slot week ≥
  current timeline week; ordered by slot week/day/time (join on time_slots).
- **Divergences from the mock (documented):** week-nav + show-past toggle and
  sort controls are mock-era — the wired contract pins "week ≥ current" (FR
  1.4) server-side; the upcoming-only list is the honest fixed scope.
- Verified: ReplacementHistoriesTest (cohort scoping, upcoming-only, view-only
  — no action affordances); full suite green (156/156).

### Postscript — RESTORED to the frozen UI design, byte-for-byte (2026-10-10)

The first wiring pass rebuilt the page as a simplified static table — wrong
call. **The page is now the frozen mock template, byte-for-byte**: week
navigator + Today, Show Past toggle, sortable desktop table / mobile cards
(original slot → new slot with the arrow anatomy), the 5-card summary strip
(Total/Upcoming/Pending/Past/Hours), and the class-detail modal — all
client-side as designed. The only changes:

- **Data bridge** — `MockData.replacementHistory` ← real rows for MY cohort
  (approved + pending — the frozen design shows pending as "Awaiting PL
  approval" with no confirmed new slot; FR 1.4), in the exact mock row shape
  (0-based week, 30-min slot indices, 'HH:MM – HH:MM' ranges, full day names).
  The frozen week/upcoming filter runs client-side on the real rows.
- **Two DATA patches (documented inline)**: the cohort chip and the
  empty-state text render the student's REAL cohort label instead of the mock
  persona's hardcoded 'RSD3(S1)G2'.
- `MockData.cohortTimetable` ← an inert empty flags map (the mock-persona
  flag lookup path stays safe); `mockNow = null` → real clock.
- View only, unchanged: students never act on requests (RBAC).
- Verified live on the demo DB (student 25DFT0001): frozen toolbar/chips/
  empty state with the real cohort label, zero console errors; the populated
  scoping covered by ReplacementHistoriesTest on the disposable DB.

## 2026-10-13 — Detail-modal "Class Type" canonical 3-way (SDD venue-pending-and-type-labels, B1)

Modal row now uses `UiLabels.classType(r.type, 'canonical')` — keeps the "(L)"-suffix
style, P renders "Practical (P)" instead of "Tutorial (T)".
