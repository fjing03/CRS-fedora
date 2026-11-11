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

## 2026-11-10 (later) — Replacement Details: qvArrangeBtn uses the same glyph

**Follow-up (same day, user):** the **Replacement Details** modal's `#qvArrangeBtn` wore the same old
circular-arrow "rotate" glyph, at 14 px / stroke 2.5. It now draws the Arrange Replacement icon too —
15 px, viewBox `0 0 24 24`, stroke-width 2 — i.e. attribute-for-attribute the same SVG as the ACTION
column's primary button and as `#btnReplaceNow` in the Class Details modal.

Unchanged: the button's 197×34 `.btn-action` box, its primary skin, the `Arrange Replacement` label,
`onclick="qvArrange()"`, the show/hide rule (`isHandled(c)` hides it), and the `Close` / `Cancel
Request` / `Go to My Timetable` buttons.

Measured on `/my-replacements`, Replacement Details open on request #38, against `#btnReplaceNow`:
same four path/polyline children, `w:15 h:15 sw:2 aria-hidden:true`, icon box 15×15, zero console
errors. Siblings keep their own weights (`Cancel Request` ban and `Go to My Timetable` calendar are
14 px / 2.5, and both are hidden on this row).

## 2026-11-10 (later) — the ACTION column's icon is the Arrange Replacement glyph

User brief "UI Update — Action Icon Consistency". The ACTION column's primary icon button now draws
**exactly** the Arrange Replacement glyph of `#btnReplaceNow` in the Class Details modal — the same
two-arrow swap, 15 px, viewBox `0 0 24 24`, `stroke-width` 2, `aria-hidden="true"`.

It used to draw a circular-arrow "rotate" glyph at `stroke-width` 2.5, so the same trip to the
arrangement page wore two different icons depending on where you clicked.

What did NOT change: the 34×34 `.btn-icon.btn-icon--primary` box, its primary border/colour, the
tooltip `Arrange a replacement for this class`, the aria label, the `onclick`
(`event.stopPropagation(); goToReplacementFor(sessionId, week, 'replacement-home')`), and every other
button in the column (the ghost calendar for approved/completed and the ghost ban for pending).

Measured on `/my-replacements` (3 primary buttons) against `#btnReplaceNow` on `/my-timetable-ui`:

    {w:15, h:15, vb:"0 0 24 24", sw:"2", ariaHidden:"true",
     paths:["polyline:17 1 21 5 17 9","path:M3 11V9a4 4 0 0 1 4-4h14",
            "polyline:7 23 3 19 7 15","path:M21 13v2a4 4 0 0 1-4 4H3"]}

    ICON IDENTICAL: true   ·   box 34×34   ·   border rgb(0, 77, 152)   ·   zero console errors

The constant was renamed `ROTATE_SVG` → `ARRANGE_SVG` (one definition, one use) so the name matches
what it draws.

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

## 2026-11-10 — Modal consistency (#1–#4)

**Follow-up (same day, user ruling B4):** ONE separator in a modal subtitle — the **em dash**.
Replacement Details was the odd one out (`AMSE1003 · Software Engineering (Lecture)`); all four now
read `CODE — Name (Type)`. Measured: Class Details `AMSE1003 — Software Engineering (Lecture)`,
Slot Details `MPU-3232 — Entrepreneurship (Lecture)`, Request Details `AMSE2003 — Software Testing
and Quality Assurance (Tutorial)`, Replacement Details `AMSE1003 — Software Engineering (Lecture)`.

**Follow-up (same day, user ruling B1):** every modal's **Close** is now the outline button —
transparent background, `--color-outline` hairline, `--color-on-surface` text, i.e. it matches
Replacement Details' Close exactly. Class Details / Slot Details used a filled chip before, so the
same word wore two skins. `.btn-close-modal` keeps its name (it is the close hook the markup and the
specs select) — only its skin changed.

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
- (This page's Arrange Replacement actions are unchanged; only its detail rows follow.)
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

## 2026-11-09 (same day, addendum) — the COURSE cell loses its hover text

Same removal as Request Approval: the column is explained on its header only, and the row cells carry no
hover text. `replacementHomeRow` no longer emits a tip. `venueLine()` / `durationWords()` moved to
`ui-common.js` in the same pass — the student Replacement History now prints the identical slot block, so
the two formatters live in one place instead of a second copy.

## 2026-11-09 (user brief) — the COURSE column: shorter label, richer hover text

Same lane as Request Approval: the column is now **`COURSE`** (was `COURSE CODE & NAME`), and hovering
a row's course cell shows that row's own subject:

```
AMSE1003, Software Engineering (L) — Subject or Course affected by the conflict
```

The cell's visible text, the sort field and the row-click pop-up are unchanged; only the hover text is
new, composed by the shared `UiLabels.courseTip` (one sentence, one em dash, the class type in the
`(L)` form the cell already prints). See `request-approval-changelog.md`, same date, for the full
record and the receipt.

## 2026-11-08 (user request) — a subject with no real name reads "Subject AMSE2003"

Four seeded modules store their code as the name — **AMSE2002, AMSE2003, AMSE2013, MPU-2302**
(`module_name` == `module_code`) — so anywhere the UI printed `CODE` + name the row read
**"AMSE2003 AMSE2003 (L)"**, and the class-detail modal read "AMSE2002 — AMSE2002" with
"Subject Name: AMSE2002".

One rule on the model now answers every display payload: `Module::displayName()` returns the real
name, or **"Subject AMSE2003"** when the name is blank or merely repeats the code (the same
"no proper name" test the notification tips already used). Applied at the timetable timeline, the
README-style payload builders (My Replacements, Arrangement, the venue pages, the student history,
the request mapper, the API read adapter, the venue availability feed).

Measured on the demo data: My Replacements now reads `AMSE2003 Subject AMSE2003 (L)` /
`(T)`, and the class-detail modal shows `Subject Name: Subject AMSE2002` (title `AMSE2002 — Subject
AMSE2002`). Modules with real names are untouched (`AMSE1003 Software Engineering (L)`).

Receipts: `tests/Unit/ModuleDisplayNameTest.php` (7 — a real name kept, the code repeated, an empty
name, whitespace only, trimming, a hyphenated code, and a module with no code at all); pint passed,
phpstan 0, `view:cache` OK; the wider payload/notification run is 114/116 with only the two known
weekend-only fixture errors.

**Follow-up, same day (user's second word: "just give them a random subject name").** The four
nameless modules got real titles in the dataset's single source
(`dataset/import/course-titles.php`), so 42/42 codes are named:

| Code | Title |
|---|---|
| AMSE2002 | Software Design and Architecture |
| AMSE2003 | Software Testing and Quality Assurance |
| AMSE2013 | Software Requirements Engineering |
| MPU-2302 | Bahasa Kebangsaan (the user's own wording) |

A second sweep of all 42 titles found nothing else using its code as a name; the only leftover was
`MPU-2212 = BAHASA KEBANGSAAN A` — the one ALL-CAPS title, beside the new `MPU-2302 Bahasa Kebangsaan`.
It now reads `Bahasa Kebangsaan A`. Two titles repeat on different codes on purpose (`Problem Solving
and Programming` = AMCS1013 + BMCS1013, `Software Engineering` = AMSE1003 + BMSE2163 — the A/B
prefixes are the same subject in two programmes).

The demo rows were updated in place (title-only `UPDATE`s, snapshots + row-count receipts in
`docs/demo-db-data-changes.md`; rehearsed on the testing DB first, where the importer reports 0
modules without a title). Measured: My Replacements reads
`AMSE2003 Software Testing and Quality Assurance (L)` and the modal reads
`AMSE2002 — Software Design and Architecture`. `Module::displayName()` stays as the safety net for any
future nameless module.

## 2026-11-07 (night) — the pop-up opens on the Proposed Replacement Schedule

User brief *Modal Tab Order & Class Type Display*, part 1 (display-only; SDD waived).

| Element | Before | Now |
|---|---|---|
| Tab order | Original Class Schedule · Proposed Replacement Schedule · Request Status | **Proposed Replacement Schedule · Original Class Schedule · Request Status** |
| Default tab on open | Original Class Schedule | **Proposed Replacement Schedule** |

`DetailModal.render()` activates index 0 of its `tabs` array, so the ORDER is the
default — no new option, no second mechanism. The Proposed tab is only pushed when the
row actually carries a replacement (`c.replacementDate`), so a **Need to Arrange** row
still leads with Original Class Schedule and keeps exactly two tabs; nothing is invented
for a request that never proposed a slot.

The subtitle already named the class type (`AMSE2003 · AMSE2003 (Practical)`) through
`UiLabels.classType(type, 'long')` — unchanged.

Receipts (testing DB, `--workers=1`): `replacement-home-status-badges.spec.ts` receipt 5
(the law: Proposed first when the tab exists) + receipt 6 (a handled row opens with
Proposed **active**) 13/13; `replacement-home-working-days.spec.ts` now clicks Original
explicitly before reading the class-date countdown (the pop-up no longer opens there)
8/8; `request-cancel-pending.spec.ts` 2-tab law for a cancelled row unaffected.

## 2026-11-07 (night) — the week filter says WHICH weeks it means

User brief *Week Filter Labels & Request Status*, part 1/4 (this page).

| Surface | Before | Now |
|---|---|---|
| Active chip | `Week: Week 8 · …` | **`Original Class Week: Week 8 · 9 Nov 2026 ~ 15 Nov 2026`** |
| "All" option | `All Weeks` | **`All Original Class Weeks`** |

The filter itself is untouched — this page has always matched the **original class
date**. The select's own tooltip already said "Pick the Original Class week to display" and
still does. The shared `populateWeekSelect()` gained an opt-in `allLabel` (default `All Weeks`
for every other caller).

Receipt: `week-filter-preserved.spec.ts` **U3** (now asserts both labels beside its existing
cancel-keeps-the-filter law); `status-tooltips-filter-chips` 14/14 together.

## 2026-11-07 (night) — the countdown owns its own line; the pop-up brackets it

User brief *UI Update — Urgency Label & Date Display*.

**Table cells (both columns).** The `n Working Days Left` label used to sit BESIDE the date; it now
takes the line under it, so every block reads date / urgency / time:

```html
<span class="class-day-date">Mon, 9 Nov 2026 (Week 8)</span><br>
<span class="urgency-mid">1 Working Day Left</span><br>
<span class="class-time">9:00 AM ~ 11:00 AM</span> <span class="class-duration">(2 hours)</span><br>
… venue
```

Both My Replacements columns (`formatClassBlock`, `requestCell`) and Request Approval's requested
column (`HtmlBuilder.replacementBlock`'s `pendingCountdown`) follow the same shape. A LANDED row
(approved/completed) keeps its inline em dash — it has no countdown to place, and an empty line
would read as a missing value. Words, colours, bands and the working-day calculation are untouched.

**Pop-up.** The date row now brackets the countdown — `Date  9 Nov 2026 (1 Working Day Left)` — in
both tabs (Original Class + Proposed Replacement, pending only). Same rule for `Today` and
`n Working Days Left`; a landed row keeps `9 Nov 2026 —`. **An OVERDUE label follows an em dash
instead** (user answer B, same night): its own words already carry brackets, so doubling them read
badly — `6 Nov 2026 — Overdue (1 Working Day Ago)`.

**Receipts** (testing DB): `tests/replacement-home-working-days.spec.ts` **8/8** — the new case
asserts the markup order (`date</span><br><urgency>` … `</span><br><class-time>`), that the old
inline shape is gone, the second rendered line is a countdown, and the pop-up's date row matches
`^\d{1,2} \w{3} \d{4} \((Today|Overdue \(…\)|… Working Days? Left)\)$`;
`replacement-home-status-badges` 13/13 and `status-tooltips-filter-chips` 15/15 across the two
replacement-home/PL files. Screenshots `/tmp/opencode/urgency-lines-home.png`,
`urgency-lines-pl.png`, `urgency-modal.png`.

## 2026-11-07 — a rejected request keeps its real length (the anchor is one cell)

Same defect as the Request Approval entry of this date: the release actions delete the junction
rows, and the anchor is ONE 30-minute cell, so a rejected 1-hour or 2-hour proposal displayed
`(30 minutes)` with a truncated end.

`replacement_requests.replacement_span` now carries the block width (written at submit,
`span × 30` minutes read back through `spanFromAnchor()`), and this page's request query selects
it — the explicit `get([...])` list had silently dropped it, which the new test caught. A row
with no stored width (pre-column data the backfill could not resolve) keeps the old anchor shape.

Receipt: `tests/Feature/MyReplacementsPayloadTest.php` case 5 — the legacy null still reads
`14:00 – 14:30`, and the same row with `replacement_span = 4` reads `14:00 – 16:00`.

## 2026-11-07 — one holiday-date list for the countdown and the cancel window

The page code derived the semester's holiday **dates** twice: once for the
cancel-class window (`ClassCancellation._holidayDateKeys()`) and once for the new
class-date countdown (`workingDayHolidayKeys()`). Both read the same payload
(`MockData.semester.startDate` + `MockData.holidays` `{week, dayIndex}`) and both
produced unpadded `y-m-d` keys, so they agreed — but a holiday added later could
have been updated in one and not the other, making the cancel rule treat it as an
ordinary day while the countdown skipped it.

`ClassCancellation._holidayDateKeys()` is now a one-line delegate to
`workingDayHolidayKeys()`; the private arithmetic copy is deleted (user decision:
delete, not "keep in step" comments).

Receipts: `tests/cancel-class.spec.ts` **16 passed / 4 skipped** — including
`S4 — own class on a holiday day shows no cancel button (Deepavali in-lieu
Monday)`, which is the direct proof that the cancel rule still reads the shared
list — plus the working-days spec 7/7. No behaviour change intended or observed.

## 2026-11-07 — the details pop-up carries the same countdown

The row pop-up ("Replacement Details") showed the dates but no countdown, while the
table and the phone card did. It now prints the same words, colour and placement law:

- **Original Class Schedule → Date** — `9 Nov 2026  1 Working Day Left` (amber), the
  working-day countdown to the class date; a landed row (approved / completed) shows
  `9 Nov 2026 —` instead, exactly like the table cell.
- **Proposed Replacement Schedule → Date** — the make-up countdown
  (`6 Nov 2026  Overdue (1 Working Day Ago)`) **only while the request is pending**,
  matching the Requested Replacement column.

Same helpers as the table (`daysLeft` → `workingDaysDiff`, `urgencyClass`,
`daysLeftLabel`, `countdownHidden`), so the pop-up can never disagree with the row it
describes. Receipts: the three replacement-home specs **17 passed** (the pop-up is
exercised by the status-badge receipts), and a browser read of all five rows:
`#99 → 9 Nov 2026 1 Working Day Left`, the three 24/25 Dec rows → `32 Working Days Left`,
and the pending row's replacement tab → `6 Nov 2026 Overdue (1 Working Day Ago)`;
no console errors. Screenshot: `/tmp/opencode/modal-countdown.png`.

## 2026-11-07 (business logic, user law) — the class countdown counts WORKING days

**What changed on My Replacements** (display only — no PHP, no payload, no database):

| Situation | Colour | Text |
|---|---|---|
| class date passed | red (`urgency-high`) | `Overdue (2 Working Days Ago)` / `Overdue (1 Working Day Ago)` |
| class date is today | red | `Today` |
| 1–7 working days ahead | amber (`urgency-mid`, `--color-warning`) | `1 Working Day Left` / `7 Working Days Left` |
| 8+ working days ahead | **green** (`urgency-low`, `--color-success` — was grey) | `8 Working Days Left` |

- **Working days** = Mon–Fri and not one of the semester's own holiday rows (the
  same set the timetable greys out and the booking lead-time already skips).
  `workingDaysDiff()` sits in `public/js/ui-common.js` next to `daysLeftLabel()`
  and resolves holiday **dates** from `MockData.semester.startDate` +
  `MockData.holidays` (`{week, dayIndex}`) — never a hard-coded calendar.
  Dates are parsed by hand (`new Date(y, m-1, d)`), never `new Date('YYYY-MM-DD')`,
  so no UTC drift in +08:00.
- **The past walk is the future walk reversed**, so the distance is symmetric.
  **Clamp:** if the calendar distance is non-zero but no working day lies between,
  the result is ±1 — `0` still means "today" and only "today". Consequence, by
  design: a class **tomorrow** that falls on a Sunday or a holiday reads
  `1 Working Day Left`, and Friday read on a Saturday reads `Overdue (1 Working Day Ago)`.
- **Countdown placement (user law):** the class-date countdown now hides only on a
  **landed** row (approved / completed) — pending, rejected, cancelled and
  unarranged rows keep it (a pending row is exactly where "how soon" matters; it
  used to show a bare `—`). The **make-up countdown lives in the Requested
  Replacement column**, beside the date it measures, and only while the request is
  **pending**. `HtmlBuilder.replacementHomeRow/Card` gained an optional
  `hideCountdown` predicate; the legacy/mock callers that pass only `isHandled`
  behave byte-identically.
- **The phone card** passes the same predicate, so the card and the table agree.

**Brief typo caught by the screenshot:** §2.5 returned `'urgency-med'`, but the
page's class is `urgency-mid`. The first render therefore had an amber band with
no rule at all (no colour, no `nowrap` — the date line wrapped mid-label). Fixed
to `urgency-mid`; the new spec asserts the amber band resolves to
`--color-warning` and the far band to `--color-success`, so a typo like that can
never land silently again.

**Receipts** (testing DB, `:8030`, `TestingFixtureSeeder` rows):
`tests/replacement-home-working-days.spec.ts` **7/7** — the walk skips weekends
(tomorrow-Sunday → 1) and the Deepavali-in-lieu row (Mon 9 Nov → 1), the 7/8
boundary, both Christmas rows adding nothing, the label set
(`Today` · `1 Working Day Left` · `2 Working Days Left` · `Overdue (1 Working Day Ago)`),
no `0 Working Days…` anywhere, the green far band, the amber mid band, and the
rendered cells matching the new vocabulary.
`replacement-home-status-badges.spec.ts` receipt 3 was rewritten (it encoded the
old "every handled row shows an em dash") — **17 passed / 3 skipped** across the
three replacement-home specs. Screenshot: `/tmp/opencode/working-days-table.png`
(`Mon 9 Nov` → amber `1 Working Day Left`, `24/25 Dec` → green
`32 Working Days Left`, and a pending row whose make-up date is past →
`Overdue (1 Working Day Ago)` in the requested column).

**Still to come** (agreed, not done here): the Approve Requests Urgency column,
its sort, its tooltip and the All/Urgent/Normal chips are to be removed, and the
age pill's `· Urgency: …` line with them.

## 2026-11-07 (date/time batch 2, `docs/date-time-format-audit-2026-11-07.md`) — the cancel-request confirm joins with `~`

The "facts you are about to give up" block printed `2:00 PM – 4:00 PM` (en dash) for both the
original and the proposed slot. Both now read `2:00 PM ~ 4:00 PM`, matching the table, the Quick
View tabs and the rest of the page; the mobile card footer follows the same change. Wording, order
and layout are untouched.

## 2026-11-07 (date/time batch 1, `docs/date-time-format-audit-2026-11-07.md`) — the Proposed tab reads 12-hour, like the Original tab

Quick View → Proposed Replacement Schedule printed the raw payload `13:30 ~ 15:30`, while the
Original tab one click away printed `2:00 PM ~ 4:00 PM`. The row now uses the shared
`DateHelper.format12hRange(c.replacementTime, ' ~ ')`, so both tabs show one clock face
(`1:30 PM ~ 3:30 PM`). The table cells were already fixed in `024d0c6`; nothing else on the page
changed.

## 2026-11-07 — the class-time range reads as one range, on one clock face

`ORIGINAL CLASS` and `REQUESTED REPLACEMENT` had drifted apart: the original printed
`2:00 PM to 4:00 PM` (12-hour, the word "to"), while the requested cell printed the raw
payload `13:30 – 15:30` (24-hour, spaced en dash). Both now use the same 12-hour range with
the `~` joiner — `2:00 PM ~ 4:00 PM` / `1:30 PM ~ 3:30 PM` — matching the row's own
Quick View tabs. `DateHelper.format12hRange()` gained an optional separator argument
(default stays `' to '`, so every other caller is byte-identical); the requested cell
passes `' ~ '`. No other cell, page or rule changed.

## 2026-11-05 (UI/UX, user brief `UIUX Update — My Replacements After Submit.md`) — `?new-req=` arrival: the freshly submitted row pulses

**Change:** the page now understands a third arrival param, `?new-req=<id>[,<id>…]`, and
flashes the row(s) it names with the **same** three-pulse highlight as the Today /
Earliest-bookable jump — `tryFind('tr[data-request-id="…"]', 20)` →
`flashRowPulse()` → `@keyframes bookable-pulse` (0.5 s × 3, 4 px ring, 63 % of
`--color-success`). **No new CSS, no new keyframe, no lasting tint** (user decision).

New `jumpToNewRequest(ids)`, beside `jumpToMyRow()` / `jumpToMyOccurrence()`:

1. look the id up in `currentFiltered` by `requestId`;
2. not found → clear **search, status and week** and rebuild, then look again. The
   clearance is **for this visit only** — `saveFilters()` is deliberately NOT called, so
   the lecturer's saved filters come back next time. The inputs are set programmatically
   (no `change` events, which would persist). **`Exclude COMPLETED Status` stays ticked**:
   a new request is `pending`, which that filter shows, so it can never be the culprit
   (and un-ticking would flood the list with completed rows);
3. still not found → **silent** — no toast. The old "hidden by your current filters"
   message is exactly what this update removes;
4. found → page it into view, then flash it with **no fallback selector**: flashing the
   wrong row would be worse than not flashing.

The param is captured **synchronously** in the existing `DOMContentLoaded` arrival block
(before `restoreFilters()` and behind the 400 ms skeleton, like `pendingHl`) and stripped
immediately with `history.replaceState`, so a refresh or Back never replays the pulse.

**Measured** (`tests/my-replacements-after-submit.spec.ts`, testing DB): arrival
`?new-req=1` → row `data-request-id="1"` carried `.bookable-flash`, its cell reported
`bookable-pulse 0.5s × 3`, exactly one row flashed, URL clean afterwards, reload silent;
with `mrh-filters` saved as `status=rejected` the row still flashed, `#statusFilter` read
`all`, and the stored filters were untouched.

## 2026-11-05 — the cancel-request warning joins the one-line standard

`#cancelConfirmOverlay` used a red uppercase `THIS ACTION CANNOT BE UNDONE.`; it now includes the shared
`partials.ui-irreversible-line` — *This action cannot be UNDONE.* with `UNDONE` in the warning amber — so Cancel
Request, Cancel Class, Approve and Reject all read identically. The four fact rows below it are untouched.

## 2026-11-04 — the jump's flash is louder

`Go to My Timetable` paints the shared pulse, so it inherits the same day's change: **3 flashes**, faster
(0.50 s each), and **40% brighter** (ring 4 px at 63 %). Measured on a real jump: 3 pulses counted, peak
opacity 0.629, peak ring 4.0 px, highlight present ~1.6 s.

## 2026-11-04 — one action on a landed row: the jump, not the eye

Follow-up on the user's word: the eye (View) button is gone from the ACTION column. An **Approved** or
**Completed** row now carries **one** button — the calendar jump, tooltip `Go to My Timetable`.

**Nothing was lost.** Clicking anywhere on the row still opens the details modal (measured: a click on the
row body opens `#quickViewModal`), so the eye was a second door to a page you were already standing on.
`EYE_SVG` was removed with it — no dead icon left behind.

**The column went back to 6 %** and Conflict Reason back to 22 %: the extra width existed only to fit two
icon buttons. Measured at 1920 / 1600 / 1440 / 1366 / 1280 / 1100 / 900 / 820 px — 0 overflowing cells,
0 page overflow, the single button inside its cell at every width.

Two specs used the eye as the door to the modal; both now click the row body and assert the one button's
`aria-label` is `Go to My Timetable`.

Measured jump (unchanged): row button → Week 7, block 26 flashed once. Full suite 340/340.

## 2026-11-04 — `Go to My Timetable`, from the row and from the details modal

User order: for **Approved** and **Completed** replacements, a way to land on the replacement's own
block on My Timetable and see it flash once. Two entry points, **one function** — the row's icon-only
button and the modal's `Go to My Timetable` both call `goToReplacementInTimetable(requestId, week)`, so
the navigation and the highlight cannot drift apart.

- **ACTION column:** a calendar icon button (tooltip and accessible name `Go to My Timetable`) beside the
  existing eye button, on Approved / Completed rows only. Pending, rejected and "need to arrange" rows keep
  exactly the buttons they had. The gate is the payload's **`requestStatus`** — the status the server
  resolved — not the badge's text.
- **Details modal:** the same button with its text, bottom-right; `Cancel Request` and `Arrange
  Replacement` are already hidden for these statuses, so it takes that corner. Hidden for every other status.
- **Layout:** `.col-action` 6 % → 10 % and `#timetable .col-reason` 22 % → 18 % so two icon buttons stay
  inside the column. Measured at eight widths: 0 overflowing cells, 0 page overflow, nothing clipped.

Measured: row button → Week 7, block `data-rid="26"` flashed once; modal button → identical; a request
whose week does not hold it → **nothing flashes** (never a neighbour); an unknown id → nothing flashes and
no error. Full suite 340/340.

## 2026-11-04 — the page accepts a class-level arrival

A notification about a **cancelled class** carries no request id, so its arrival uses
`?notif-hl=o<sessionId>&notif-w=<week>` (the request-based `r<id>` arm is unchanged). The page matches
the row on **session + week** — never on the row-id formula — pages it into view and flashes it with the
shared pulse. A class that is not on this lecturer's page is a silent miss, exactly like the request arm.

The arrival is still captured synchronously at `DOMContentLoaded` (the shared highlighter strips the
params 150 ms later), and the params are gone from the address bar afterwards, so a refresh repeats
nothing. Measured: `o67&notif-w=14` flashed row 6714; a session/week with no row flashed nothing and
raised no error.

## 2026-11-04 — the page description speaks the same visual language as My Replacements

Minor UI update (user, all pages): the `.page-desc` line now bolds and uppercases its key terms with
the existing `.info-keyword` class. **This page already did it** (it is where the convention came
from) — nothing to change here; the entry exists so the sweep is recorded on every page it touched.

## 2026-11-04 — the Request Status filter names itself

Minor UI update (user). Two strings, nothing else:

* the select's label (its `data-tip`, now also an `aria-label` for screen readers) reads
  **`Request Status`** instead of `Filter by request status`;
* the default option reads **`All Request Status`** instead of `All Statuses`. `value="all"` is
  unchanged, every other option is unchanged, and the filter behaves exactly as before.

The filter chip takes its text from the selected option, so it still shows the chosen option's own
label — only the default wording moved.

## 2026-11-04 — the table stops painting outside its columns

User's responsive-layout bug report: at narrower widths the COURSE CODE & NAME cell spilled over
its neighbour, and headers/cells were clipped or crossed into the next column.

**Cause.** `theme.css` makes every `.timetable td` `white-space: nowrap`, and the merged page's
`table-layout: fixed` gives each column a fixed percentage. Four columns had already opted out of
nowrap (Original Class, Conflict Reason, Requested Replacement, Request Status) — **the two that did
not were Course Code and Cohort, plus the whole header row**. Their content was simply wider than
their box, so it painted over the column to its right. Measured on the merged page: **68 px of
overflow** in Course Code at 1280 px wide, 56 px at 1440, 3 px at 1920, and **19 px for the header
label** "COURSE CODE & NAME".

**Fix.** Course Code, Cohort and the header row now wrap inside their own column, and long unbroken
tokens break instead of pushing (`overflow-wrap: anywhere`). The countdown ("5 days left") is pinned
to one line so the narrower Original Class column cannot break it mid-phrase. **No width, share,
order, markup or behaviour changed** — the percentages still sum to 100 % and the desktop table is
structurally identical.

**Measured after (overflowing cells at 1920 / 1600 / 1440 / 1366 / 1280 / 1100 / 900 / 820): 0 at
every width.** Below 1280 px the table keeps its `min-width` and scrolls horizontally inside
`#gridScroll` (510 px of scroll at 820 px) while the page itself stays at 0 horizontal overflow;
at 768 px and below the card view takes over, so the table is never squeezed or hidden.

## 2026-11-04 — Confirm Cancellation: a warning face, and the facts you are about to lose

User's UI/UX request on the merged page (`/my-replacements`). Display only — the cancellation
workflow, the write path and the buttons are untouched.

**The icon.** The Confirm Cancellation modal now opens with a **yellow warning face** (a frown face
drawn at 44 px), coloured from `var(--color-warning)` only. `.modal-icon` is a reusable shell so any
other destructive confirmation can adopt it without new markup.

**The copy, in the order the user gave it:**

* `Are you sure you want to **CANCEL** this Replacement Request?` — sentence case except the
  capitalised title, with **CANCEL** in bold warning yellow.
* `THIS ACTION CANNOT BE UNDONE.` — bold, uppercase, error red.
* A four-row definition list, labels bold + uppercase and values normal:
  **COURSE** · **COHORT** · **ORIGINAL CLASS SCHEDULE** · **PROPOSED CLASS SCHEDULE**
  (`Week 7, Tue, 3 Nov 2026, 1:00 PM – 2:00 PM @ B009` → `Week 7, Wed, 4 Nov 2026, 2:00 PM – 2:30 PM @ B009`).

Cohorts join with ` & ` here (the modal's own convention), course names carry the canonical short
type code `(L)`/`(T)`/`(P)`, and **both** schedules read on one clock face — the replacement range
arrives as 24-hour (`14:00 – 14:30`) and is converted, so the modal never mixes `1:00 PM` with
`14:00`.

**Dimensions.** The modal is wider — **635 px**, grown 15 % twice at the user's request (420 → 480 →
552 → 635) because the four labelled rows kept wrapping a venue code onto its own line. Above 520 px
the label sits beside its value; on a phone the list becomes one column and the label sits above its
value. Measured: 635 px at 1440 px wide, 350 px at 390 px, `scrollWidth === clientWidth` in both (no
horizontal overflow). Buttons, ordering and the confirm handler are unchanged.

## 2026-11-04 (follow-up) — column shares: the code column gives back 40%, Conflict Reason takes 30% more

User ruling (2026-11-04): **`COURSE CODE & NAME` −40%**, **`CONFLICT REASON` +30%**. The shares are
now code 11% · cohort 10% · original 18% · reason 22% · requested 16% · status 17% · action 6% —
still 100%, and the two percent the trim freed went to **Original Class**, the column that does most
of the wrapping (its `days left` line stopped breaking onto a second row everywhere above 1600 px).

| Window | Before (code · cohort · original · reason · requested · status · action) | After |
|---|---|---|
| 1280 | 225 · 125 · 212 · 206 · 200 · 206 · 75 | **137** · 125 · 225 · **275** · 200 · 212 · 75 |
| 1440 | 243 · 135 · 230 · 223 · 216 · 223 · 81 | **149** · 135 · 243 · **297** · 216 · 230 · 81 |
| 1920 | 329 · 183 · 311 · 302 · 293 · 302 · 110 | **201** · 183 · 329 · **403** · 293 · 311 · 110 |

The holiday badge (`Public Holiday — Deepavali Holiday (In Lieu)`) is on one line from ~1500 px up and
wraps to two lines below that; the course column still prints its name on one line at 1440+.

One trap worth remembering, hit while making this land: the reason/status opt-out has to be
`#timetable .col-reason`, **not** `#timetable td.col-reason`. Under `table-layout: fixed` the `<th>`
is what decides the column width, so a td-only selector leaves the header unstyled and the browser
hands that column whatever it likes — which looked exactly like "the percentage was ignored".

## 2026-11-04 (follow-up) — the details pop-up is 10% wider

`#quickViewModal`'s shell went from `max-width: 560px` to **`616px`** (user ruling: +10%), so the
facts strip and the schedule rows stop reflowing on the longest content. Verified live: 616 px at
1440, and the phone layout is untouched (342 px — full width minus the overlay's 20 px padding — at
390 px). Nothing inside the pop-up changed, and the shared `.modal` base keeps 420 px, so the
keyboard-shortcuts and cancel-confirmation pop-ups are exactly as they were.

## 2026-11-04 (follow-up) — the table grid: every column has a share, the code column stops hoarding

`.col-code` was the only column without a definite width (`width: auto`), so the auto table layout
handed it every spare pixel — **704 px** in a 1920 px window — while the multi-line cells stayed
pinned at their own hints, and at 1280 the long holiday badge squeezed everything else to make room
for itself (294 px for one badge). The table now uses `table-layout: fixed` with a share for every
column — code 18% · cohort 10% · original 17% · reason 17% · requested 16% · status 16% ·
action 6% — so the grid is the same shape at every width and content wraps inside its own column.

| Window | Before (code · cohort · original · reason · requested · status · action) | After |
|---|---|---|
| 1280 | 168 · 116 · 171 · **294** · 198 · 227 · 73 | 225 · 125 · 212 · **206** · 200 · 206 · 75 |
| 1920 | **704** · 130 · 210 · 294 · 200 · 227 · 73 | **329** · 183 · 311 · 302 · 293 · 302 · 110 |

Two side effects, both intended: the code column can no longer balloon, and the badge columns are
the only cells that opt out of the table's `white-space: nowrap` (`.timetable td` outranks a bare
class, so the opt-out is `#timetable td.col-reason` / `.col-status`). Below ~1500 px a long holiday
badge therefore takes **two lines** (`Public Holiday — Deepavali Holiday (In Lieu)`), which grows the
row from 81 px to 101 px at 1280; at 1920 every badge is back on one line. Nothing else on the page
changed — the header, the sort, the cards and the pop-up are untouched.

## 2026-11-04 (follow-up) — the Conflict Reason badge says *which* conflict again

The merge kept the badge's two **colours** but dropped its **words**. The design authority
(`explore-brief.md` row 4) asks for two shapes:

- `Public Holiday — <calendar label>` in error red (`.badge-holiday`) — e.g. `Public Holiday — Christmas Eve`
- `Class Cancelled — <reason>` in neutral grey (`.badge-reason`) — e.g. `Class Cancelled — Medical/Sick Leave`

**What was broken:** the badge printed the raw `conflictReason`, so a holiday read `Public Holiday`
with no hint which day, and a cancellation read only `Medical/Sick Leave` with no verb saying what
happened to the class.

**Fix.** `ReplacementHome::buildRow()` now emits `holidayName` — the `holidays` row's `label`, `null`
for every non-holiday reason. The page's new `reasonText(c)` composes the sentence once, and both
surfaces that draw the badge (the table cell and the pop-up's facts strip) already went through the
one `reasonBadge(c)`, so they cannot drift. The phone card is built by the shared
`HtmlBuilder.replacementHomeCard`, which gained an optional `opts.reasonText` hook — mock and legacy
callers omit it and render exactly as before.

An exception stamped `public_holiday` with **no** calendar row behind it keeps the prototype's
literal fallback `Public Holiday — Semester holiday` (user's ruling), so the badge never invents a
day name.

**Receipts:** `MyReplacementsPayloadTest` case 11 pins the payload (the calendar label, the literal
fallback, and `null` for a cancellation); `replacement-home-status-badges.spec.ts` receipt 8 pins the
composed text on the row and in the facts strip, and receipt 5 pins it on the phone card.

## 2026-11-04 — **My Replacements**: one table, one row per class, three addresses

SDD lane `.sdd/archive/replacement-merge-one-table/` (M1–M17), commit `6856577` + `ab65f9a`.

**What the page is now.** The lecturer's two pages (`Replacement Home` and `My Request History`)
became **one**: `/my-replacements` lists **classes**, not requests — one row per
`(class_session_id, week_number)` — and the row's state is its **newest** request. A class with no
request still appears, as `Need to Arrange`. The old URLs **`/replacement-home-ui` and
`/my-request-history-ui` remain route entries for the same page** (never redirects), so stored
notification links, `?blocked=`, `?notif-hl=` and the post-submit redirect all keep working;
`my-request-history-ui` keeps `'nav' => 'replacement-history'` (harmless).

**Seven columns:** Course Code & Name · Cohort (one per line, not sortable) · Original Class (date,
week, days-left, time, duration, venue) · Conflict Reason · Requested Replacement · Request Status ·
Action. The Action cell is **one icon**: rotate = arrange, ban = cancel a pending request, eye = open
the details. **The badge is no longer a link** — the row is the door.

**Six labels, one server map** (`ReplacementRequestMapper::statusLabel/statusShort/statusSentence/
statusTip`): `Need to Arrange` (outlined) · `Pending — Waiting for Approval` · `Approved` ·
`Rejected` · `Cancelled by YOU` · `Completed — <n> days ago`. `completed` is still derived, never
stored; the count uses the server's `today()` so the badge and the status cannot disagree.

**The pop-up** is `#quickViewModal`: a fixed facts strip (cohorts, students, conflict) above
`Original Class Schedule` · `Proposed Replacement Schedule` (absent when nothing is proposed) ·
`Request Status (<short>)` with one sentence, the Rejection Reason (rejected only) and the Remark
(when present). No history block, no timeline.

**Cancelling** is one request at a time from the row's ban icon or the pop-up footer, through the
moved **Confirm Cancellation** overlay, always via `CancelPendingRequest`; the row is then **re-read
from the server** — after the release it shows the anchor range (`14:00 – 14:30`), never an assumed
value.

**Five cards** follow the filters: Classes to Arrange · Class Hours to Replace · Pending Requests ·
Approved Requests · Rejected Requests. `Showing N of M classes` hides below 1500 px (M15); the
pagination bar still reports it. The nav's two lecturer items became one — **My Replacements**.

**Retired:** `MyRequestHistory` (class + view), the bulk-cancel bar, the `Quick Cancel` /
`Requested At` columns, `#modalOverlay`, `requestHref()` and its readers, the ten reason badge
classes (now two: `.badge-holiday` / `.badge-reason`), the urgency/venue/students columns.

**Verified:** payload 11/11 (106 assertions), span 13/13, histories/home/route-gate 47/47,
`ReplacementArrangementTest` 20/20, badges receipts 7/7 on the disposable testing DB,
request-completed-status 3/3, zero page errors at 1440 / 1600 / 390 px.

## 2026-11-03 — a finished replacement reads **Completed** (derived, never stored)

SDD lane `.sdd/changes/request-completed-status/` — the user's nine locked decisions (C1–C9).

**The rule (C1/C2).** An `approved` request whose **replacement class date is strictly before
today** now reads **Completed**. It is worked out when the page reads the row — nothing is written,
no column, no migration, no audit entry, no backfill. A class on Tuesday reads Completed on
Wednesday; a class **today** still reads Approved all day (the comparison is by date, not by the
clock). If the replacement date cannot be resolved the status stays **Approved** — the app never
guesses.

**One helper (C3).** `ReplacementRequestMapper::effectiveStatus()` is the only place the rule
lives. It feeds all four surfaces: this page's payload, My Requests, the Request Approval page and
the JSON API (`toRow()` is shared by the PL page and the API). This page also gains the two columns
the helper needs (`replacement_time_slot_id` was missing from the narrow select — without it the
rule would have been silently dead here).

**New: the Exclude Completed toggle (C4).** Same label, same tooltip
(`ON = completed requests are excluded from the list`), **checked on every load** — this page keeps
no saved filters and has no Reset button, so nothing is stored in `localStorage`. While it is
checked a Completed row is not listed, the result count drops, and the **Total** card counts only
what is on screen (the two My cards already read the filtered set).

**The badge (C5).** A completed row's Action cell is a **Completed** badge linking to
`/my-request-history-ui?notif-hl=r<request_id>`, exactly like Pending/Approved. No new badge code:
the badges lane's `statusBadge()` already renders any non-rejected/cancelled status, and
`.status-completed` (`theme.css`) already exists. A completed row is a *handled* row — no Arrange
button, `—` in Days Left, sorted with the handled group.

**No notification (C7)**, no schema change, no demo-database write (C9).

**Receipts.** `ReplacementHomeTest` 11/11 (a new date-matrix test: week 5 = yesterday →
`completed`, week 6 = today → `approved`, week 7 = tomorrow → `approved`, plus the "stored status
untouched" assertion); the browser receipt on the disposable testing DB (toggle checked on load →
the Completed row absent → untick → the badge appears with its `?notif-hl=r<id>` href → re-tick →
gone). The demo DB has **no approved request at all**, so nothing on `:8000` can read Completed
today — the toggle renders and is checked there, and the row set is unchanged (read-only run).

**Already true of the viva twin.** `class_replacement_viva` holds request 27, approved at week 7,
whose replacement date is **2026-11-03 — today**. By C2 it reads **Approved today** and
**Completed from 4 Nov**, so the viva date itself shows the Completed state without any data
change.

## 2026-11-03 — the row keeps its request status (badges, not disappearance)

SDD lane `.sdd/changes/replacement-home-status-badges/` — the user's nine locked decisions
(D1–D9), implemented as approved.

**D1 — the reversal.** A conflicted occurrence used to vanish from this table the moment its
request went **pending** or **approved** (an agent fix from 2026-10-11, never a user
ruling). It now **stays**, and its Action cell carries the request's state. Rejected and
cancelled rows were already returning; they are unchanged in that respect.

**D2 — the Action cell matrix.**

| Newest request | Action cell | Days Left | Sort group | Click |
|---|---|---|---|---|
| none | Arrange Replacement | real countdown | needs action | arrangement page |
| `pending` | **Pending** badge (a real `<a>`) | `—` | handled | My Requests, `?notif-hl=r<id>` |
| `approved` | **Approved** badge (a real `<a>`) | `—` | handled | My Requests, `?notif-hl=r<id>` |
| `rejected` | muted **Rejected** label **stacked above** the Arrange button | real | needs action | the button → arrangement page |
| `cancelled` | muted **Cancelled** label **stacked above** the Arrange button | real | needs action | the button → arrangement page |

The label above the button is **not** clickable (one target in a 150 px cell). The destination
lives in one helper (`requestHref(c)`) so the planned one-table merge edits a single line.

**D3 — no Completed state.** Nothing in the app writes `completed`; this lane does not invent
one. The handled predicate is deliberately "not rejected/cancelled", so a future fifth state
degrades safely without being advertised.

**D4 — three strings only.** The subtitle, the guide item ("**Track progress** — …") and the
empty state ("No classes currently need attention."). The three summary cards are untouched
and remain conflict totals.

**D5 — quick view + cards.** A handled row's footer button becomes **View Request** (same
destination, label in its own span so the icon survives), the modal gains a **Replacement
Status** row, and the mobile card shows the same badge — its tap goes to My Requests.

**D6 — the page-jump lives in My Requests only** (see that changelog). `applyNotifHighlight`
and the bell are untouched.

**D7/D8 — days + order.** A handled row shows `—` in Days Left, but the sort key still reads
the real date, so a row does not jump when it becomes handled. Rows that need action come
first; the chosen column sorts **within** each group (the group key is the first branch of
the existing comparator — a pre-sort would be undone by the column comparator).

**D9 — the holiday keeps its cause.** The approval flow stamps an `official_event` exception
when the occurrence had none. On a calendar-holiday occurrence that stamp no longer rewrites
the cause: the row keeps **Public Holiday**. Trade-off (accepted): a lecturer who deliberately
chose "Official Event" for a holiday week also reads "Public Holiday" — the two cases share
one enum value and cannot be told apart in the data.

**Payload.** `ReplacementHome` now loads the **newest** request per occurrence in every
status (`orderBy submitted_at, id` → keyed map, last wins) and adds `requestId` +
`requestStatus` to every row; the row still exists only when the occurrence really is
conflicted. No schema change, no migration, no demo-DB write.

**Shared builders.** `HtmlBuilder.replacementHomeRow/Card` gained **optional** callbacks
(`isHandled`, `actionCell`, `statusBadge`) whose defaults reproduce today's output — the
legacy mock template renders unchanged.

**Receipts.** `ReplacementHomeTest` **10/10** (the old hide test became
`test_home_keeps_occurrences_with_an_active_request_and_attaches_its_status`, plus newest-request,
holiday-precedence and null-status cases); the full suite and the read-only browser spec are
quoted in the SDD review log. Browser, live on `:8000` as 5425: two handled rows
(`?notif-hl=r26`, `?notif-hl=r27`) carry a **Pending** badge and `—`, three needs-action rows
keep their Arrange button and sit **first**, the badge lands on My Requests and the matching
`tr[data-id]` pulses (`bookable-flash` observed 662 ms after arrival), the 390 px card badges
navigate, and the quick view reads **View Request** + a *Replacement Status* row.

**Honest gap:** the page-jump's *page 2* case cannot be receipted on this dataset — it needs
more than 10 requests for one lecturer and the demo user has 4. The navigation + highlight
half is receipted automatically; the page-flip itself is verified by reading
`jumpToRequestRow` and remains a named manual path until a lecturer has >10 rows.

## 2026-11-02 (user-ordered, temporary) — the "Just cancelled" chip is switched OFF pending a recency rule

**Why.** The chip says **"Just"** but has no time limit: its `sessionStorage`
ledger entry (`classCancellationLedger`) has no TTL, so the badge and its Undo
can sit on a row for hours (the only limits are Undo, submitting a replacement
for that subject, the row leaving the list, or the tab closing). A "just"
that can be three hours old is a false claim, so the chip is **off** until the
recency window lands.

**What changed.** The render block in `resources/views/livewire/replacement-home.blade.php`
(the S13 `cxlEntries.some(...)` branch that appends
`<span class="just-cancelled-chip">` + `.just-cancelled-undo` to the Original
Class cell) is **commented out** under a `TEMPORARILY DISABLED 2026-11-02`
marker that names the follow-up prompt
(`prompts/pi-prompt-just-cancelled-chip-ttl.md`).

**What stayed.** Everything else is untouched — the `.just-cancelled-chip` /
`.just-cancelled-undo` CSS, the `ClassCancellation` ledger (append/replay/
consume), the 5-second undo toast, `undoCancelledByRowId()`, and the row's
cancelled/holiday state with its reason badge. The page lists the same rows
with the same data; the row simply no longer wears the chip.

**Receipt (read-only, demo DB never written).** With a matching ledger entry
seeded into the browser (`sessionId 99` + `matchKey.week 7` → the W8
AMSE1003(L) row): **before** = 1 `.just-cancelled-chip` + 1 `.just-cancelled-undo`;
**after** = 0 and 0, with the same 4 rows and zero page errors.

**Restore.** Delete the `// ` prefixes (git holds the original block verbatim),
add the 30-minute recency predicate, and re-run the four receipt cases listed in
the prompt.

## 2026-11-02 (user ask 2026-10-31) — "My Courses" renamed to **My Subjects**

**What changed.** The third summary card's label and back-face description:
`My Courses` → `My Subjects`; "Number of your different **courses** affected by
the conflicts." → "Number of your different **subjects** affected by the
conflicts." Display text only.

**Not changed (deliberate).** The card's internal hooks — `valueId =>
'summaryMyCourses'` and `class => 'card-courses'` — stay exactly as they were.
`public/js/ui-common.js` writes that value by id, so renaming the id would be a
silent breakage for zero user value. The value itself, the page's JS, the
`card-courses` default description fallback in
`partials/ui-summary-bar.blade.php`, and the other two cards are untouched.

**Why "subject" wins.** The system's own vocabulary is subject-first (the
arrangement page's "Change Subject?" guard, the subject dropdown, the
`MockData.subjects` family); "course" was the odd one out on one page.

**Receipt.** Live: `.summary-card.card-courses .summary-label` reads
`My Subjects`, `#summaryMyCourses` still resolves (value 2), and the back-face
description reads "…your different subjects…".

# Changelog — Replacement Home Dashboard

## 2026-10-19 (SDD qv-arrange-null-order-fix) — the Quick-View Arrange button works again

The Replacement Details modal's **Arrange Replacement** button was a silent
no-op: `qvArrange()` called `hideQuickView()` (which nulls `qvCurrent`)
BEFORE reading the lead for the handoff URL — the dereference threw and
killed the navigation. Restored the frozen template's capture-then-hide
shape (:588–594 — capture `sessionId`/`week` first). Regression spec:
`tests/replacement-home-qv.spec.ts`.
## [2026-10-08] conflictedClasses seeded with real subject codes & titles

- `MockData.conflictedClasses` (`public/js/mock-data.js`): all 14 rows' `code`/`name`/`cohorts`/`totalStudents` now mirror §2.2 `courses[]` — fake `BMIT5555`-style codes replaced by 11 real, dropdown-eligible codes (`AMCS2093`, `AMCS1013`, `AMIS1003`, `BMCS1013`, `BMIT1173`, `BMIT2013`, `BMIT2073`, `BMIT2154`, `BMIT3084`, `BMMS1743`, `MPU-3133`).
- Every row's code now passes `buildSubjectDropdown`'s two filters (code in `courses[]` + ≥1 conflict/cancelled slot in `cohortTimetable.events`), so the table row → quick-view → **Arrange Replacement** handoff pre-selects the subject on `/replacement-arrangement` (previously: blank subject + locked grid).
- Cohort labels keep this array's spaced convention (`'DFT2 (S1)'`, `'RSD2 (S1) G2'` — cf. `formatCohortLabel`); `type`/`date`/`day`/`time`/`venue`/`duration`/`conflictReason` stay session-level instance facts (unchanged).
- Verified live: data audit (14/14 rows consistent with `courses[]`, 0 problems), search by code & name, 3-row handoff (`AMCS2093`/`BMCS1013`/`MPU-3133`) pre-selects + `subjectInfo` coherent, quick-view Class tab shows code/name/type/cohorts/students, pagination "Showing 1-10 of 14" / page 2 intact, 0 console errors.

## [2026-10-02] Empty state above summary + summary auto-hides when empty

- The `ui-empty-state` include moved **above** the "Summary Dashboard" strip in the DOM.
- When the filtered conflicted-class list is empty, the whole `#summarySection` (5 cards + hint) is now **hidden** — previously only `#summaryBar` was hidden while the hint stayed visible.
- Uses the new shared `syncSummarySection(visible)` helper in `ui-common.js` (§10.0 rule 7 promotion — same pattern on my-request-history, replacement-home, request-approval, upcoming-replacements).
- Verified: default view shows summary; "ZZZNOFIND" search shows the empty state and hides the summary; clearing restores both. 0 console errors.

## [2026-08-31] Sync upstream/fjing UI refactor (merge fd8c403)

Merged `upstream/fjing` (267 commits `cfc1bb1..f44cc5c`) into `fedora-backend`. Policy: **theirs-first for UI**; backend-only files kept local. Verification: PHPStan 0, PHPUnit 94/94, smoke 12/12 routes 200 (`/replacement-home-ui` 200).

### Files Changed
- `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php` — **theirs**
- Shared: `theme.css`, `ui-common.js`, `mock-data.js`, `layouts/ui-template`, `partials/ui-nav-bar`, `partials/ui-logout-modal` + `public/js/logout-modal.js`, `public/css/macos-design.css` — **theirs**

---

## [2026-08-16] Replaced hardcoded inline styles with shared utility classes

Refactored keyboard shortcut hint text to use `.hint-text` class from `theme.css`.

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Line | Before | After |
|---|---|---|
| 215 | `style="margin-top:12px;font-size:11px;color:..."` | `class="hint-text" style="margin-top:12px;text-align:center"` |

---

## [2026-08-15] Pass class duration to replacement-arrangement

The "Arrange" navigation now includes the conflicted class `duration` (hours) as a `duration=` query param, so the replacement page can match the selection length. (Replacement-home already had `c.duration` in its data.)

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `goToReplacementWith()` | Updated | Accepts optional `duration` and appends `&duration=` to the URL. |
| Card click / keydown / quick-view | Updated | Pass `c.duration` / `qvCurrent.duration`. |

---

## [2026-08-15] Quick-View: status description as its own row

Added a **Status Description** row alongside the days-left badge ("Urgent — arrange a replacement soon" / "Approaching — plan a replacement" / "Within normal lead time").

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| `quickView()` | Updated | Added `Status Description` row after `Status`. |

---

## [2026-08-15] Quick-View refinements: general title, status badges, split rows

### Summary

- **General title** — modal header now "Replacement Details" (was the course name); course code/name moved to subtitle.
- **Status badges** — days-left urgency badge + conflict-reason badge as body rows (was a header badge).
- **Split rows** — Start/End Time + Duration split; removed grouped "Time" and "Days Left" rows.
- **Close button** — normal gray `×`; removed header `modal-status-badge`.

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Modal shell | Updated | Removed header `qvStatusBadge`. |
| `quickView()` | Updated | General title, badge rows, split time/duration. |

---

## [2026-08-15] Quick-View modal redesign: unified detail sheet

### Summary

The Quick View (Replacement Details) modal now uses the shared `DetailModal` "Detail Sheet": identity header (title + subtitle + conflict badge), single flat group, definition rows without per-row borders. Same fields preserved. Modal body is JS-generated.

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Location | Change | Detail |
|---|---|---|
| Modal shell | Updated | `.modal-title#qvTitle` + `.modal-status-badge`; body emptied; overlay-click close added. |
| `quickView()` | Rewritten | Uses `DetailModal.render`. |
| `hideQuickView()` | Updated | Uses `DetailModal.close()`. |

---

## [2026-08-15] Page-specific summary card descriptions

Added page-specific `description` text to each summary card (Total Conflicted / Venues Affected / Students Affected / Duration Hours / Distinct Courses) instead of relying on the shared generic descriptions. (`replacement-home-UI-design-template.blade.php` summary bar.)

---

## [2026-08-13] Phase 3 UX Enhancement: Collapsible Guide Block

### Summary

Added an expandable guide block with page-specific workflow instructions.

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 136-143 | Added | `@include('partials.ui-guide-block')` with 5 workflow tips |

---

## [2026-08-13] Phase 3 UX Enhancement: Table Header Tooltips

### Summary

Added hover tooltips to all column headers for better usability.

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-13 | Lines 315-323 | Added | `tip` property to each column definition with user-friendly descriptions |
| 2026-08-13 | Lines 325-330 | Modified | `makeSortableHeader()` now uses `col.tip` for `title` attribute |

---

## [2026-08-10] Phase 2 Template Migration: Inline helpers → Shared OOP classes

### Summary

Migrated inline `computeWeek()` to `getWeekNumber()`, `weekRangeLabel()` to `DateHelper.weekRangeLabel()`, refactored `buildTable()` and `renderCards()` to use `HtmlBuilder.replacementHomeRow()` and `HtmlBuilder.replacementHomeCard()`.

### Files Changed

#### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-10 | Lines 270-292 | Removed | Deleted inline `computeWeek()` and `weekRangeLabel()` function definitions |
| 2026-08-10 | Lines 250, 309, 415, 446, 483 | Replaced | `computeWeek(c.date)` → `getWeekNumber(c.date)` |
| 2026-08-10 | Line 452 | Replaced | `weekRangeLabel(w)` → `DateHelper.weekRangeLabel(w)` |
| 2026-08-10 | Lines 337-361 | Refactored | `buildTable()` now uses `HtmlBuilder.replacementHomeRow(c, opts)` |
| 2026-08-10 | Lines 370-395 | Refactored | `renderCards()` now uses `HtmlBuilder.replacementHomeCard(c, opts)` |

#### `public/js/ui-common.js`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-08-10 | HtmlBuilder class | Added | `HtmlBuilder.replacementHomeRow(c, opts)` — constructs replacement-home table row |
| 2026-08-10 | HtmlBuilder class | Added | `HtmlBuilder.replacementHomeCard(c, opts)` — constructs replacement-home card HTML |

---

### Changed
- **Touch targets**: Upgraded from 44×44px to 48×48px (8dp) in `theme.css` for all mobile buttons/links. Pill-tab padding also increased to 12px.

## [2026-08-06] Bug Fix: qvArrange() Navigation Broken

### Fixed
- **`qvArrange()` JS bug**: `hideQuickView()` nullified `qvCurrent` before reading its `code`/`date`, causing a `TypeError`. Fix: saved values to local vars first, then hide, then navigate.

## [2026-08-03] Mobile Responsive: Quick View Bottom Sheet + Touch Targets

### Added
- **Quick View modal → bottom-sheet on mobile (≤768px)**: Modal slides up from bottom, 80vh max height, scrollable body, drag handle bar, full-screen backdrop, full-width buttons in footer
- **Touch targets**: Upgraded from 40px (tablet) to 44px (WCAG 2.5.5) on mobile for week arrows, modal buttons, and arrange button
- **Toolbar week-nav**: Full-width stacked layout on mobile (search above, week picker below)

### Changed
- Modal animation: bottom-sheet slide-up on mobile (replaces centered scale on small screens)

## [2026-08-03] Refactor: Rows Per Page promoted to shared OOP component

### Changed
- **Rows Per Page (RPP)**: Removed local CSS/HTML/JS; now uses shared `partials.ui-rpp` Blade partial + `initRpp()` from `ui-common.js` + `.rpp-wrapper`/`.rpp-select` from `theme.css`
- RPP moved from standalone div below pagination-bar to **inside** `.pagination-bar` (consistent with my-request-history)
- Default changed from 5 to 10; options now `[10, 25, 50, 'all']`; localStorage key `'rpp-page-size'`

## [2026-08-03] UX Refinement: Remove Filter Presets, Standardise Modals

### Removed
- **F2 – Filter Presets (localStorage)**: Removed My Filters panel (Save/Apply/Delete buttons), associated CSS, and JS functions (`saveFilter`, `applyFilter`, `deleteFilter`, `loadFilterPrefs`)

### Changed
- **Keyboard Shortcuts Modal**: Replaced custom inline-styled modal (`.modal-box`, `z-index:3000`) with standard `.modal-overlay` + `.modal` pattern from `theme.css` (consistent with my-request-history, replacement-arrangement)
- **Quick View Modal**: Replaced custom inline-styled modal with standard `.modal-overlay` + `.modal` pattern; fields now use `.modal-field` / `.modal-field-label` / `.modal-field-value` classes
- Both modals now toggle via `.show` class instead of `style.display`, and use `.modal-close` (✕) button + `.btn-close-modal` footer button
- Removed unused custom CSS: `.my-filters-panel`, `.btn-outline`, `.quick-view-*`, `.table-body tr` cursor/hover

## [2026-08-03] UX Enhancement: Rows Per Page, Filter Presets, Keyboard Shortcuts, Quick View Modal, Column Consolidation

### Added
- **F1 – Rows Per Page Selector**: Dropdown below pagination with options [5, 10, 25, 50]; default 5 rows per page; resets to page 1 on change
- **F2 – Filter Presets (localStorage)**: My Filters panel with Save/Apply/Delete buttons; persists search, filter, and sort state to localStorage (`replacementHomeFilterPrefs`); restores on page load
- **F5 – Keyboard Shortcuts**: Help icon button in toolbar; `?` global keydown opens modal; shortcuts: `/` focus search, `Esc` clear filters, `←`/`→` page nav, `Enter` quick view
- **F6 – Quick View Modal**: Click any table row to open modal showing full class details (12 fields); closes on OK, X, backdrop click, or `Esc`
- **F7 – Column Consolidation**: Merged 14 columns → 9 columns; date/time/week merged into "Original Class" column using `formatClassBlock()`; Type merged as `(L)`/`(T)` suffix in Course Code column

### Changed
- Table now displays 9 columns (was 14): #, Course Code & Name, Original Class, Days Left, Venue, Students, Affected Cohorts, Conflict Reason, Action
- Sort by "Original Class" sorts by underlying date value
- Keyboard shortcuts don't trigger when input/textarea/select is focused

## Files Changed

### `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-20 10:00 | — | New page created | Replacement Arrangement Home Dashboard — 944-line standalone Blade template with top nav, searchable/filterable/sortable 11-column table, pagination, conflict reason badges, "Arrange Replacement" action buttons |
| 2026-07-20 10:30 | CSS (`.filter-dropdown option`) | Fix filter dropdown dark mode | Added explicit `background`/`color` overrides for dropdown options to fix low-contrast text in dark mode |
| 2026-07-20 11:00 | HTML (toolbar) + JS | Remove reason filter | Commented out reasonFilter dropdown HTML; removed JS references; extended `.search-input` width from 260px to 420px |
| 2026-07-20 11:30 | CSS + HTML + JS | Add summary dashboard | 4 stat cards below table (Total Conflicted, Venues Affected, Students Affected, Duration Hours) with `updateSummary()` JS; hidden when empty |
| 2026-07-20 11:45 | HTML (card 4) | Replace Need Action with Duration Hours | Changed 4th card from `filtered.length` to sum of `c.duration` across filtered rows |
| 2026-07-20 12:00 | CSS + HTML + JS + mock data | Add Distinct Courses card | Added 5th card; entries 2 & 10 now share codes with entries 1 & 9 (12 distinct / 14 total); grid → `repeat(5, 1fr)`; padding compressed |
| 2026-07-20 12:15 | CSS (`.summary-bar`) | Remove gap between summary cards | Removed `width: 80%` + `justify-self: center` root cause; cards fill cells edge-to-edge; `gap: 4px` |
| 2026-07-20 12:30 | CSS (`.summary-bar`) | Revert card narrowing | Removed `max-width: 720px` auto-centering; bar back to full 1fr width |
| 2026-07-20 13:00 | CSS + JS columns + mock data | Add Affected Cohort(s) column | Inserted between Students and Conflict Reason (12 columns); `cohorts` array on all 14 entries; rendered with `<br>`; table min-width → 1170px |
| 2026-07-20 13:30 | CSS + JS helpers + columns | Add Days Left / Urgency column | Inserted between Day and Time (13 columns); `daysLeft()` + `urgencyClass()` helpers; color-coded (≤7d red, 8–30d teal, 31+ default); table min-width → 1270px |
| 2026-07-20 13:45 | JS (`sortState`) | Default sort by Date ascending | Changed `sortState.field` from `''` to `'date'`, `dir` to `'asc'`; Date column shows sort arrow on page load |
| 2026-07-20 14:00 | HTML + CSS | Add sort hint | Added "Click **Date** or **Course Code & Name** to sort" between toolbar and table; `.sort-hint { text-align: left }` |
| 2026-07-20 14:30 | CSS + JS columns + helpers | Add Week column | Inserted between Type and Date (14 columns); `computeWeek()` relative to semester start (Aug 31, 2026); `.col-week { width: 80px }`; table min-width → 1350px |
| 2026-07-20 14:45 | HTML (toolbar) + JS | Add week dropdown | Added `filter-select` dropdown dynamically populated via `populateWeekDropdown()` from unique weeks in data |
| 2026-07-20 15:00 | HTML + JS + CSS | Remove date range filter | Removed From/To date inputs, `matchesDate` logic, date event listeners, `.filter-input`/`.filter-label` CSS |
| 2026-07-20 15:30 | CSS (`.filter-select`) | Fix weekFilter dark mode | Added `html.dark .filter-select { color-scheme: dark; }` to force native dark dropdown rendering, overriding GTK green tint |
| 2026-08-02 12:00 | HTML + JS (full page) | Centralised mock data (Task 6) | Removed 14-row inline `conflictedClasses` array; replaced with `MockData.conflictedClasses` reference; standardised `computeWeek()` and `weekRangeLabel()` to use `MockData.semester.startDate`; replaced static semester chip with dynamic `#semesterChip` populated from `MockData.semester.chipText` |
| 2026-08-02 | `@section('page-scripts')` | Centralised mock data (Task 9) | `conflictedClasses` moved to `mock-data.js` (page-specific, not shared). Week computation standardized to `MockData.semester.startDate`. Semester chip now reads `MockData.semester.chipText` dynamically. |
| 2026-08-02 | CSS + HTML + JS | Mobile card view | Added `.replacement-card` / `.rc-header` / `.rc-body` / `.rc-footer` card styles; added `#cardView` container; added `renderCards()` JS function; on mobile (≤768px) grid/pagination/sort-hint hidden, card-view shown; cards show course code, conflict reason badge, name, date/time/venue, days left, cohorts |
| 2026-08-03 | CSS + JS columns + cells | F7: Column consolidation (14 → 9) | Merged Week/Date/Day/Time/Duration into single "Original Class" column using `formatClassBlock(c)` + `dayAbbr()` helpers; merged Type into Course Code as `(L)`/`(T)` suffix; added `.cell-class-block` / `.class-day-date` / `.class-time` / `.class-duration` CSS; removed `.col-type`/`.col-week`/`.col-date`/`.col-day`/`.col-time`/`.col-duration`; added `event.stopPropagation()` on action button; sort hint updated |
| 2026-08-03 | CSS + HTML + JS | F1: Rows Per Page selector | Added `state.rpp` (default 5), `setRpp(n)` function, `rppSelect` dropdown (5/10/25/50), CSS for `.rows-per-page`/`.rpp-label`/`.rpp-select`; replaced `pageSize` with `state.rpp` in offset/slice/paginate; resets to page 1 on change; hidden on mobile |
| 2026-08-03 | HTML + JS | F5: Keyboard Shortcuts modal | Added help icon button in toolbar; added `#keyboardModal` overlay with 7-row shortcuts table (/, Esc, →, ←, Enter, ?); added `showKeyboardShortcuts()`/`hideKeyboardShortcuts()` functions; added global `keydown` listener for `/` (focus search), `Esc` (close modal or clear filters), `?` (open modal), `ArrowRight`/`ArrowLeft` (page navigation); added `clearAll()`/`goNextPage()`/`goPrevPage()` helper functions |
| 2026-08-04 | — | Refactored: replaced inline page-header/week-nav/empty-state/grid-table/modal with `@include('partials.…')` (OOP Phase 1) | Page uses `ui-page-header`, `ui-week-nav`, `ui-grid-table`, `ui-empty-state` partials. |

### `routes/web.php`

| Timestamp | Location | Change | Detail |
|-----------|----------|--------|--------|
| 2026-07-20 10:00 | Line ~25 (new) | New route | Added `Route::get('/replacement-home-ui', ...)` returning the standalone template view |
| 2026-08-13 | `@section('page-styles')` | macOS-style update (Phase 4) | `.badge-emergency-leave` color from `white` to `var(--color-on-error)`. `.btn-replace-now` font-weight from `600` to `500`. |
| 2026-08-13 | Lines 214–225 | macOS modal button order | Reordered quick-view modal footer: Close button moved before Arrange Replacement button — matches macOS dismiss-left / action-right convention. |
| 2026-08-13 | `public/js/mock-data.js` | Week filter fix | Semester `startDate` shifted from `2026-06-15` to `2026-07-27` so mock data dates (relative to today) fall in filterable weeks. `parseDate` in `ui-common.js` fixed: `.split("-")` → `.split(" ")` to match space-separated date format. |
| 2026-08-15 | `<th>` headers | Header hover tooltips | Switched from native `title` to JS `initHeaderTooltips()` with a fixed-position tooltip div — tooltips appear above headers, avoids `overflow:hidden` clipping on `.grid-wrapper`. |

### Postscript — sweep-fixes-round-1 (2026-10-06, F-1)

**Negative days-left fixed across all three surfaces** (pre-submission sweep F-1):

1. `daysLeft()` now runs on the shared demo anchor `DateHelper.getTodayMs()` (was the raw
   browser clock — diverged from the lead-time pages; same one-line go-live pattern).
2. New shared `daysLeftLabel()` (ui-common, 3rd-duplication promo): `<0 → "Overdue"`,
   `0 → "Today"`, singular "1 day left", else "N days left" — rendered by the table
   DAYS LEFT cell, the mobile `.rc-footer` card, and the quick-view modal Status row
   (user decision: label Overdue and KEEP the row + button; the arrangement page's
   lead-time banner explains why no weeks are selectable).
3. Modal Status Description gains the negative branch: "Class date has passed — a
   replacement can no longer be arranged automatically" (was "Urgent — arrange soon" for
   −31 days). Modal urgency classes unified onto the defined vocabulary
   (`urgencyClass()` → urgency-high/mid/low); the undefined `.urgency-urgent/warning/normal`
   dead classes are gone. User decision noted in `.sdd/changes/sweep-fixes-round-1`.

### Postscript — sweep-fixes-round-3 (2026-10-06, sortable Days Left + Venue)

**Days Left** and **Venue** columns are now sortable (user request) via the shared
`makeSortableHeader` house pattern: Days Left sorts on the computed urgency value —
ascending = Overdue/most-urgent first, descending = nearest-to-today first (this
page's conflicted classes are all past-dated in the demo dataset, so both directions
order within the Overdue block; ordering is identical to Original Class since
Days Left derives from the date — the header is the affordance users think in).
Venue sorts alphabetically, grouping the same rooms. Students / Cohort(s) /
Conflict Reason stay non-sortable (cohorts = multi-value like My Request History;
Conflict Reason already has a dedicated filter dropdown).

### Postscript — copy-fix-overdue (2026-10-06)

**Overdue Status Description corrected** (user catch): the detail modal said
"a replacement can no longer be arranged automatically" — false, the Overdue row's
Arrange button works and the arrangement page books the passed class into any
upcoming week (the 3-working-day rule applies to the chosen *slot*, not the passed
class date). New copy: "Class date has passed — you can still arrange a replacement
in any upcoming week". Supersedes the round-1 F-1 wording.

**Addendum (2026-10-06, user ask):** the Overdue label now carries the day count —
shared `daysLeftLabel()` (ui-common) renders **"Overdue (N days ago)"** with singular
handling, so the table cell, card view and detail modal all show it; `.col-urgency`
widened 100→150px to fit without wrapping. Diff computed against the `mockNow` anchor
like every other lead-time value.

---

## [2026-10-07] Quick-view modal grouped into tabs

Replacement Details quick-view now splits its 15 flat rows into 3 tabs:
**Status** (urgency badge, description, conflict reason) / **Class**
(code, name, type, cohorts, students) / **Schedule** (week, day, date, times,
duration, venue). Same tab component as my-request-history's Request Details.
Blade: `replacement-home-UI-design-template.blade.php` `quickView()`.

### Postscript — cancel-class-enhancement (2026-10-08, SDD change `cancel-class-enhancement`)

Lecturers can now cancel their own classes — status normal with an end time
in the future (real clock) — directly from any class modal via the shared
CancelClassModal (`partials/ui-cancel-class-modal`) with a mandatory enum
reason (6 values incl. Other + detail; OOP: `ClassCancellation` in
`ui-common.js`). A cancelled class is auto-appended to the requires-replacement
list and carries a "Just cancelled" chip (home page only); the sessionStorage
ledger (`classCancellationLedger`) replays the state across pages, with an
undo toast (12 s) on any landing page until undone/arranged.

### Postscript — toast snooze + chip Undo (2026-10-08, SDD-waived micro-fix)

User feedback: the cancellation undo toast re-appeared on every refresh after
being ✕-closed. The ✕ now snoozes the toast for the browser session (per-entry
`toastSnoozed` flag in the ledger; a NEW cancellation toasts again) — the
cancelled state + chip still replay. Undo stays reachable: the "Just cancelled"
row now carries an Undo icon button (`.just-cancelled-undo`, restores the class
everywhere). Shared pieces: `ToastManager.close()` hook (`ui-common.js`),
layout ✕ → `toast.close()`.

### Postscript — cancelled replacement classes join the list (2026-10-08, SDD-waived extension)

Following the my-timetable extension (replacement-status classes are now
cancellable — FR 2.16), a cancelled confirmed-replacement class flows into
the requires-replacement list with the usual "Just cancelled" chip + undo
button; undo restores it as a replacement class (priorStatus preserved),
not as a normal one.

### Postscript — card keywords + close tooltips (2026-10-09, SDD: warning-modal-keywords)

The three summary-card flip-backs joined the keyword language: "My Conflicted Classes" warns
in red (**NEED A REPLACEMENT ARRANGEMENT**), the hours/courses cards use bold-uppercase
keywords in the default color. The keyboard-shortcuts and quick-view modal ✕s gained
"Close" data-tip tooltips.

### DB wiring — real data, Slice B task 2.2 (2026-10-10, SDD: wire-backend-into-refactored-ui)

The page now serves real DB data via `App\Livewire\ReplacementHome` (D10 registry
swap; legacy mock template untouched for the fallback). Contract per the
replacement-flow-wiring spec: list own classes, conflicted included, with
start-a-replacement from details (FR 2.4) + access to request history (FR 2.15)
and venue timetables (FR 2.14); my request counts by status.

- **Rows** = the lecturer's weekly class templates (server-ordered day/time),
  each with Course, Day & Time, Duration, Venue, Students, Cohort(s) and a
  Status cell: `badge-conflict` "Venue restriction" (live B005 × Diploma
  derivation, shared with My Timetable via the promoted
  `ResolvesTimetableTimeline::venueRestrictionConflict`/`baseEvent` helpers),
  `badge-conflict` "N cancelled wks" (class_exceptions, FR 2.16 output),
  `badge-pending` "N active requests" (own pending/approved), else
  `badge-normal` "Normal".
- **Search** — `wire:model.live.debounce.350ms`, module code/name, URL-persisted.
- **Actions** — row click opens the shared class-detail modal (Replace Now in
  footer, any class is replaceable); per-row "Arrange Replacement" button
  navigates directly. Both use the shared `goToReplacement` URL contract
  (`?code=&cohort=&day=&start=&end=&originalVenue=&duration=&from=replacement-home`)
  that the arrangement page (2.3) consumes.
- **Summary cards** (own scope): My Pending / Approved / Rejected Requests +
  My Conflicted Classes — server-rendered values (ui-summary-bar now accepts
  an optional `value`; mock JS filled `0` placeholders).
- **Divergences from the mock (documented):** sort controls, pagination and the
  days-left urgency column are mock-era (rows are 14-week templates, not dated
  occurrences); keyboard-shortcuts + quick-view modals dropped (§10.0 rule 6).
  Empty state renders server-side (ui-empty-state gained an optional
  `visible` flag).
- Verified: ReplacementHomeTest (5 wiring tests — scoping, conflict flag,
  counts, cancelled weeks, search); full suite green.

### Postscript — RESTORED to the frozen UI design, byte-for-byte (2026-10-10)

The first wiring pass rebuilt the page as a simplified server-rendered table —
wrong call. **The page is now the frozen mock template, byte-for-byte** (the
sortable conflicted-classes table, week filter, sort hint, pagination, mobile
card view, quick-view modal with Status/Class/Schedule tabs, keyboard
shortcuts, and the 3-card summary — My Conflicted Classes / My Hours to Cover /
My Courses — all computed client-side as designed). The ONLY change is the
data source: a bridge swaps `MockData.conflictedClasses` for real rows in the
exact mock row shape (`window.PAGE_DATA.conflictedClasses` — one row per
conflicted/cancelled weekly occurrence: FR 2.16 enum reasons title-cased to
the designed badge labels ('Annual Leave'…), Public Holiday from the real
semester calendar, Venue Restriction from the live B005 × Diploma derivation),
plus real `semester`/`holidays`/`currentUser` and `mockNow = null` so
Days Left runs on the real clock. Verified live on the demo DB
(/replacement-home-ui): 4 real AMCS2093 occurrences with PH badges, Days
Left urgency, summary cards correct.

### Fix — the list now respects active requests + occurrence-identity handoff (2026-10-11)

Live demo testing caught two gaps:

1. **Replaced occurrences stayed listed.** The bridge listed every conflicted
   occurrence with no awareness of replacement requests. Per the frozen D1
   ("one active request per block occurrence"), an occurrence with a
   pending/approved request now leaves the list — and returns automatically
   if the request is rejected or cancelled. Bridge-level filter; the frozen
   table/cards/summary run unchanged on the filtered rows.
2. **The handoff carried strings, not identity.** Cards passed
   `?code=…&date=…` which the arrangement page had to reverse-engineer. The
   handoff now carries the occurrence's real identity —
   `?session={id}&week={n}&from=replacement-home` — the same D1 pair the
   request row keys on (user decision 2026-10-11: "use ID"). Rows carry
   `sessionId` + `week`; the quick-view Arrange button and card/keyboard
   handlers all use them.

Also: the go-to-arrangement builder is now shared (`goToReplacementFor` in
ui-common.js) — promoted from the three per-page param schemes per the
promote-on-3rd-duplication rule.

### Fix — the TABLE view's "Arrange Replacement" still passed code+date (2026-10-12)

User report: clicking the table row's Arrange button demanded "select a
subject first". The shared row builder (`UITable.replacementHomeRow` in
ui-common.js) still emitted the OLD scheme — `goToReplacementWith(code,
date)` — which the new `(sessionId, week)` function misread as a pin, so the
server resolved no occurrence and the arrangement page arrived unpinned.
(The card view + quick-view were fixed on 2026-10-11; this shared builder
was the one caller left behind.) It now emits
`goToReplacementFor(sessionId, week, 'replacement-home')` — live-verified:
the button navigates `?session=97&week=8` and the page arrives fully pinned
(subject, slot, venue, grid week).

## 2026-10-13 — P sessions labelled "(P)" not "(T)" (SDD venue-pending-and-type-labels, B1)

Table rows, card view, and the quick-view modal now use the shared `UiLabels.classType`
three-way map — Practical sessions print "(P)"/"Practical" (AMCS2093 #39/#49 canary);
L/T output byte-identical. Automated: tests/type-labels.spec.ts.
## 2026-10-27 (SDD class-cancel-db-persist) — the home chip Undo deletes the REAL row + the 5 new badge leaves
**The badge law:** `badgeClass` + the page badge CSS gain the 5 new vocab
leaves — Hospitalisation/Compassionate/Paternity = `--color-primary-container`
family, Maternity = `--color-tertiary-container` (the medical sibling),
Other = `--color-secondary-container` (the neutral grey). Tokens only
(§10.0). `NB: legacy 'Medical Leave' seed rows keep their badge via an
alias key; the modal label is the law — 'Medical/Sick Leave'.
**The titleCase law (server):** ReplacementHome + ApiReadController map
`medical_leave` → 'Medical/Sick Leave' and `other` → 'Other' verbatim (the
drift receipt retired — the modal label EQUALS the home badge now).
**The chip Undo:** `undoCancelledByRowId` awaits
`ClassCancellation.undoViaServer` (the shared bridge) while
pending-disabling the chip's Undo button — ok → the row/chip/button
vanish + 'Class restored.'; fail → the honest hint, the button
re-enables, the cancel (and the real DB row) stays.

## 2026-10-27 · Quick view "Students" = A+B=total (BMSE3153-class-info)
The quick-view modal's Class tab "Students" row now renders the shared
`cohortBreakdown(c)` (ui-common.js). **Payload** (`ReplacementHome.php`): the
conflict-quick-view rows gain `cohortCounts` — positional with `cohorts`, real
`cohorts.student_count`. Third copy of the helper ⇒ promoted to ui-common.js
(my-request-history + request-approval refactored onto it).
## 2026-10-27 — page-data shard + receipt (the debug-mode 500 fix)
Includes the shared `partials/page-data-script.blade.php` — a
>60KB payload now shards into ≤16KB scripts, re-joined via JSON.parse;
the page's PAGE_DATA contract is unchanged. (The bug's full writeup
lives in the arrangement changelog.)

## 2026-11-06 — Pending badge tip names the auto-reject deadline

The merged page's `.badge.status-pending` tooltip gains a second line from the
shared vocabulary (`ReplacementRequestMapper::statusTip`, lecturer voice): *If the
Programme Leader has not Approved this Request by <deadline>, it will be
Automatically Rejected.* Deadline = proposed start minus 3 working hours
(Mon–Fri 08:30–17:30, weekends and semester holidays excluded). Two lines via
`\n` (the shared data-tip engine renders textContent, so `\n` + pre-line, not
`<br>`). Full write-up + receipts: `status-tooltips-filter-chips-changelog.md`
(2026-11-06).

## 2026-11-06 — Cancel Pending no longer blanks the Week filter

Same defect as `request-approval-ui` (see that changelog for the measurement): after **Cancel** on a
pending request, the Week dropdown lost its options because the page component's re-render morphs the
select from the server's empty HTML, and the chip (rebuilt from the empty select) printed `Week: `.

The fix is shared, not per-page: `ui-common.js` snapshots the week selects before a round-trip and
restores options + value after it (`withWeekLivewireGuard()` around `pageLivewire().call()`, plus a
`commit`-hook net). This page needed no change of its own — `cancelRequest` goes through
`pageLivewire().call()`, so the guard brackets it.

Receipt: `tests/week-filter-preserved.spec.ts` U3 (week 14 selected → cancel → the row's status flips,
the dropdown still reads `Week 14` and its chip still names the week). It also asserts the cancel's own
toast, so it cannot pass vacuously.

## 2026-11-06 — a request its owner cancelled displays as "Need to Arrange"

User brief *Cancelled Pending Requests*. When a lecturer cancels their own pending request, the merged
occurrence no longer advertises a proposed replacement that no longer exists.

| Surface | Before | Now |
|---|---|---|
| Status badge | `Cancelled by YOU` (`status-cancelled`) | **`Need to Arrange`** (`badge-outline status-none`) |
| Badge tip | *You MANUALLY CANCELLED this request.* | *No request has been made for this class yet — it still needs arranging.* |
| REQUESTED REPLACEMENT | the released proposal (date · time · venue) | `—` (`<span class="cell-dash">`) |
| Details pop-up | Proposed Replacement Schedule tab (Request ID, Week, Day, Date, Time, Duration, Venue) | **no proposed tab** — 2 tabs: Original Class Schedule + `Request Status (Need to Arrange)` |

**One normalisation, no view code.** `ReplacementHome::buildRow()` flips a `cancelled` status to `null`
and blanks the replacement strings (plus `remarks`/`rejectionReason`) before the six-state vocabulary
runs. Everything the brief asks for follows from that, because the view already renders the no-request
state for an absent date: the badge class + tip + sentence, the dash, the missing Proposed tab, the
visible Arrange button and the "needs action" sort group.

**Why `cancelled` == "the owner cancelled it".** `CancelPendingRequest` is the only writer of that
status and it refuses anyone but the proposer (FR 2.10), so a stored cancellation is by definition a
manual one. Nothing else changes:

- the record keeps `status = 'cancelled'`, `decided_at`, its audit rows (`action = 'cancelled'`), the
  released slots and the notification — history is not rewritten;
- `requestId` still rides the payload, so the row's identity (`data-request-id`) and the notification
  jump keep working;
- Rejected / Approved / Completed / Pending rows are byte-identical, and the student history page and
  the notification feed read the DB status directly, so they still say **Cancelled by YOU**.

### Follow-up (same day, user's word): the **Cancelled** filter option is gone

With cancelled requests displaying as *Need to Arrange*, no row on this page can carry that state any
more, so the status filter's `Cancelled` option could only ever match nothing. It is removed —
the filter now offers exactly the five states the page can show (`All Request Status`, Need to Arrange,
Pending, Approved, Rejected, Completed).

A filter **saved** before the removal still names it (`localStorage['mrh-filters'].status ===
'cancelled'`), so `restoreFilters()` maps that one value onto `none` — the state those rows became.
Restoring a value the `<select>` no longer has would blank the dropdown **and** match no row; the
mapping is what keeps an old browser session honest. `STATUS_RANK`/`isHandled()` keep their defensive
`cancelled` arms (a payload can still carry the state through another surface); only the filter option
went away.

Receipt: `replacement-home-status-badges.spec.ts` receipt 10 — the option list is exactly those six
values, and a pre-removal saved filter (`status: "cancelled"`) restores to `Need to Arrange` and shows
only no-request rows. Verified red without the mapping, green with it (13/13 on the demo DB).

Receipts: `tests/request-cancel-pending.spec.ts` (write, testing DB — cancel a real pending request,
then assert the badge text/classes/tip, the `.cell-dash`, two modal tabs with no `Proposed Replacement
Schedule` and no `Request ID`, the DB still `cancelled` with 0 junction rows and ≥1 audit row, and the
same state on a fresh load; the released span is bookable again). `tests/replacement-home-status-badges
.spec.ts` receipt 9 is the read-only law (this page never prints `Cancelled by YOU`). Unit pins
updated: `MyReplacementsPayloadTest` cases 5 + 9, `ReplacementHistoriesTest`, `SpanReservationTest`
(the anchor-after-release law now re-pointed at a rejected row, which is where that fallback is still
visible).

### Follow-up (same day): the cancelled row pulses in the **warning** tone

User's word: *"highlight that cancelled tr, after cancel request with yellow/warning color, reuse the
Earliest Bookable and today-btn highlight function/js."*

So the receipt now lands in the table itself: the instant the cancel succeeds, that occurrence pulses —
the **same** mechanic as the Earliest-bookable / Today jumps (`flashRowPulse()` in `ui-common.js`: one
class, a reflow restart, `3 × 0.5 s`), with a **tone** argument instead of a second implementation.

- `flashRowPulse(el, tone)` — `tone === 'warning'` adds `bookable-flash--warning`; omitted = the
  shipped green family, byte-identical for every existing caller (earliest / today / notification /
  Go-to-My-Timetable / after-submit `?new-req=`).
- `theme.css` gains `@keyframes bookable-pulse-warning` + two specificity arms:
  `tr.bookable-flash.bookable-flash--warning > td` and `.replacement-card.bookable-flash.bookable-flash--warning`.
  Same 4 px / 63 % ring, colour `color-mix(var(--color-warning) 63%, transparent)` — so it follows the
  light/dark token automatically. The tone means *"this is the request you just cancelled"*, not
  *"this is bookable"*.
- `replacement-home.blade.php`: `flashCancelledRow(requestId)` runs right after the `buildTable()` of the
  success path and flashes the table row **and** the phone card (the card now carries
  `data-request-id`, so the card view — where the table is hidden — gets the same receipt). It never
  scrolls: the user is already looking at the row they acted on.

Receipt: `tests/request-cancel-pending.spec.ts` watches the table from BEFORE the click (MutationObserver
— the class lives only one run), then asserts the pulse landed on `data-request-id="<the cancelled
request>"`, carries `bookable-flash--warning`, and that the computed animation is
`bookable-pulse-warning / 0.5s / 3`. Measured screenshot of the ring: `/tmp/opencode/cancel-pulse-row.png`.
The green arm is unchanged — `my-replacements-after-submit.spec.ts` still reports
`{"name":"bookable-pulse","dur":"0.5s","count":"3"}`.
