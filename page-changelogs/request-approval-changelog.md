## 2026-11-10 — Modal consistency (#1–#4)

**Follow-up (same day, user ruling B1):** every modal's **Close** is now the outline button —
transparent background, `--color-outline` hairline, `--color-on-surface` text, i.e. it matches
Replacement Details' Close exactly. Class Details / Slot Details used a filled chip before, so the
same word wore two skins. `.btn-close-modal` keeps its name (it is the close hook the markup and the
specs select) — only its skin changed.

**Follow-up (same day, user ruling B7):** one glyph in the Slot Validity row — the round badge
already paints the tick, so the words beside it no longer repeat it. The row read
`(green ✓) ✓ Slot available`; it now reads `(green ✓) Slot available`. Same for the red warning
case, which no longer prints a second ⚠.

**Follow-up (same day, user ruling B5):** no IDs in modal subtitles — the Request Details subtitle
read `#28 · AMCS2093 — …` and the request number also has its own **Request ID** row in the Request
Status tab. The subtitle is now `AMCS2093 — Operating Systems (Lecture)`. Measured: subtitle has no
`#`, the Request ID row still carries it.

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
- (No Replace Now button on this page — the PL queue's actions are unchanged.)
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

The pass above put the row's own code, full name and class type on every cell. The user removed it the
same day: the column is explained **once, on its header** ("Subject or Course affected by the conflict"),
and the cells carry no hover at all — the cell already prints code and name, so the tip only repeated
itself. `UiLabels.courseTip()` is gone; `UiLabels.courseTipSubject` (the header sentence) stays.
`CourseColumnTipTest` was rewritten to assert the absence, so a future pass cannot quietly add it back.

## 2026-11-09 (user brief) — the COURSE column: shorter label, richer hover text

The column was `COURSE CODE & NAME` with the hover text "Course affected by the conflict". It is now
**`COURSE`**, and its hover text names the actual row:

```
AMSE2003, Software Testing and Quality Assurance (T) — Subject or Course affected by the conflict
```

- **The header label** loses the two-part promise (the cell already prints both) on Request Approval
  and on My Replacements — the table on both pages, one rename.
- **The hover text** is composed from the row's own data: the subject code, the full course name from
  the module record, and the class type — `(L)` / `(T)` / `(P)`, the same code form the cell prints.
  Request Approval's cell needed nothing new in the payload (`courseCode` / `courseName` / `classType`
  were already on every row); My Replacements' shared row builder passes `code` / `name` / `type`.
- **The description after the em dash is the user's own sentence**, "Subject or Course affected by the
  conflict" (their wording, 2026-11-09), and it lives in exactly one place —
  `UiLabels.courseTipSubject` in `ui-common.js` — with `UiLabels.courseTip(code, name, type)` composing
  the whole line. Both page headers ask the same constant, so the two tables cannot drift apart.
- **Nothing else moves**: sorting on the column still works (the field is unchanged), the search/filter
  haystack is untouched, and no booking, badge or modal logic was touched.

Measured on the demo data (staff 5425): both pages read `COURSE`, the header hover prints the sentence,
and row hovers print e.g. `AMSE1003, Software Engineering (L) — Subject or Course affected by the
conflict` and `AMSE2003, Software Testing and Quality Assurance (T) — …` (the T row proves the type is
the row's own). Sorting re-orders, the search filters 3 → 1 → 3 (Request Approval) and 4 → 2 → 4 (My
Replacements), zero console errors.

Receipt: `tests/Feature/CourseColumnTipTest.php` — 4/4 source invariants (both labels renamed, both
headers take the shared constant, both row builders compose the tip from real data, and the sentence
appears once in the stylesheet's JS). **Red-check:** restoring the old label + static tip fails 3 of 4.
`pint` passed, `phpstan` 0, `view:cache` OK.

## 2026-11-07 (night) — Proposed tab first, and the class type in the title

User brief *Modal Tab Order & Class Type Display*, parts 1 + 2 (display-only; SDD waived).

| Element | Before | Now |
|---|---|---|
| Tab order | Original Class Schedule · Proposed Replacement Schedule · Request Status | **Proposed Replacement Schedule · Original Class Schedule · Request Status** |
| Default tab on open | Original Class Schedule | **Proposed Replacement Schedule** |
| Modal subtitle | `Request Details #28 · AMCS2093 — Operating Systems` | **`Request Details #28 · AMCS2093 — Operating Systems (Lecture)`** |

The order is the default (`DetailModal.render()` activates index 0); the status tab stays
last. A row with no proposal keeps Original first — the tab cannot be invented for a
request that never proposed a slot.

The class type comes from the request's own session: the payload already carried
`classType` (the mapper's `classRow()` key, `L`/`T`/`P`), and the label is
`UiLabels.classType(…, 'long')` — the PHP twin is `App\Support\ClassTypeLabel`. So the
modal prints the full word (**Lecture / Tutorial / Practical**), never `(L)`/`(P)`, and it
cannot drift from the table card's `[Lecture]`.

Receipts (testing DB, `--workers=1`): `request-approval.spec.ts` (the old 3D block now pins
Proposed first + `active`, Original not active, and the subtitle's `(Lecture)` shape) plus
`status-tooltips-filter-chips.spec.ts` 34/34 across five specs.

Two test-law repairs found on the way (both pre-existing, neither caused by this brief):
the reject card's joiner assertion still expected `9:00 AM to 10:00 AM` (the 2026-11-07
law is `~`), and both reseed helpers only cleared the FK trail for fixture ids `10/24/25/26`
— a spec that creates its own request made `TestingFixtureSeeder` die on
`audit_logs_replacement_request_id_foreign`. Both reseeds now clear the whole trail.

## 2026-11-07 (night) — the week filter is about the REQUESTED week

User brief *Week Filter Labels & Request Status*, parts 1–4 (this page).

| Surface | Before | Now |
|---|---|---|
| Active chip | `Week: Week 7 · …` | **`Requested Replacement Week: Week 7 · 2 Nov 2026 ~ 8 Nov 2026`** |
| "All" option | `All Weeks` | **`All Requested Replacement Weeks`** |
| Column header | `STATUS` | **`REQUEST STATUS`** |
| The filter | matched the **original class** date | matches the **requested replacement** date |

The filter change is the real one: `filterData()` called `isInWeek(r.classDate, week)`; it now
calls `isInWeek(r.replacementDate, week)`. So a request whose class sits in week 14 but whose
proposal lands in week 7 belongs to **week 7** on this queue — that is the slot the PL is being
asked to approve. The sort hint and the select's tooltip follow ("Pick the Requested Replacement
week to display").

`weekFilter-preserved.spec.ts` U1/U2 moved with it (they used to pick week 14, the fixture's
ORIGINAL week; they now pick week 7, the fixture's requested week) — the point of those receipts
(a decision does not blank the week filter) is unchanged.

Receipts: `status-tooltips-filter-chips.spec.ts` **T5.RA-5** (the two labels, the header, the chip,
week 7 lists request 26 while its original week 14 does not, and clearing restores it; it resets
the fixture itself under the fail-closed guard), `week-filter-preserved` U1/W2/U3 **14/14 green**
with the status-tooltips file.

## 2026-11-07 (night) — the pending countdown takes its own line

User brief *UI Update — Urgency Label & Date Display*, part 1. The REQUESTED REPLACEMENT cell's
pendency countdown sat beside the date (`Fri, 6 Nov 2026 (Week 7) Overdue (1 Working Day Ago) 2:00 PM
~ 3:00 PM`); it now gets the line under the date, so every row reads date / urgency / time:

```html
<span class="class-day-date">Fri, 6 Nov 2026 (Week 7)</span><br>
<span class="urgency-high">Overdue (1 Working Day Ago)</span><br>
<span class="class-time">2:00 PM ~ 3:00 PM</span> <span class="class-duration">(1 hour)</span>
```

The change lives in `HtmlBuilder.replacementBlock()`'s opt-in `pendingCountdown` (My Replacements'
cells are rendered by its own page code and follow the same shape). This page's ORIGINAL CLASS
column carries no countdown — it was removed with the Urgency column on 2026-11-07 — so there is
nothing to move there. Words, bands, colours and the working-day calculation are unchanged; the
pop-up on this page never printed a countdown and still does not.

Receipt: `status-tooltips-filter-chips.spec.ts` T5.RA-4 (15/15 with the working-days spec).
Screenshot `/tmp/opencode/urgency-lines-pl.png`.

## 2026-11-07 — a rejected or cancelled request keeps its real length (1 hour, not 30 minutes)

Reported on request **#24** (and the same shape on `/my-replacements`): the requested
replacement read `1:30 PM ~ 2:00 PM (0.5 hours)` for a **1-hour** class, and request **#37**
(`Fri, 13 Nov 2026 (Week 8) 10:30 AM ~ 11:00 AM (30 minutes)`) for a **2-hour** class.

**Cause (measured, not assumed).** A replacement slot is a 30-minute cell, so a 1-hour proposal
covers two cells and a 2-hour one four; the run lives in `replacement_request_slots`. Every
release action — `RejectRequest`, `CancelPendingRequest`, `AutoRejectRequest` — DELETEs those
rows (that is how the cells become bookable again), leaving only the anchor
(`replacement_time_slot_id`, the span's **first** cell). `spanFromAnchor()` assumed `count = 1`,
so a decided row could only ever read 30 minutes, with a truncated end.

The audit log still held the truth: `#24` → `{"slots":[34412,34413]}` (two cells = 1 h),
`#37` → four `submitted` entries (2 h). Nothing was mis-calculated at submit time; the record
was simply destroyed at decision time.

**Fix (SDD `replacement-span-persistence`, user decision 2026-11-07 "A").** The request now
stores its own block width: additive nullable `replacement_requests.replacement_span` (number
of 30-minute cells), written by `SubmitReplacementRequest`. `spanFromAnchor($anchor, $span)`
grows the end from the anchor's start by `span × 30` minutes. Junction rows still win when they
exist (approved rows are byte-identical), and a null width keeps the old single-cell shape.

`sessions`/`toRow`/`buildRow` all read the stored width; `ReplacementHome`'s explicit column
list had to include it (an omitted column silently reads NULL — caught by the new test).

**Backfill.** `php artisan crs:backfill-replacement-span` (dry run by default, `--commit` to
write, `--force` needed on demo databases) fills old rows from the audit log: the decision
entry's `slots` list first, else the count of `submitted` entries. Unresolvable rows stay null
and are listed.

**Receipts.** `tests/Feature/ReplacementSpanTest.php` **8/8**; `MyReplacementsPayloadTest` case 5
gained the stored-width leg (legacy null still pins 30 min); `ReplacementFlowTest` (submit writes
1), `EmailQueueTest` (an explicit 4-cell block stores 4). Browser on the testing DB: request #24
now reads `Fri, 13 Nov 2026 (Week 8)  2:00 PM ~ 3:00 PM (1 hour)`.

## 2026-11-07 (user) — a PENDING row prints how soon its make-up starts

The requested column carried only day, date, week, time and room. For a request
still waiting for a decision, the one number that matters is how soon that slot
begins — so a **pending** row now prints the same countdown My Replacements uses,
beside the slot's own date:

```
Fri, 6 Nov 2026 (Week 7)  Overdue (1 Working Day Ago)
```

- Working days, the same bands and words as the lecturer page
  (`Today` · `1 Working Day Left` · `Overdue (1 Working Day Ago)`), red / amber /
  green from the shared `theme.css` rules — approval and rejection rows carry
  **no** countdown (their plan is settled, and a rejected/cancelled one has no live
  slot).
- `HtmlBuilder.replacementBlock()` gained an opt-in `pendingCountdown`; only this
  page passes it, so the frozen templates and every other caller are byte-identical.
- The three `.urgency-*` colour rules moved from My Replacements' page-local style
  block into **theme.css**, and the band choice became the shared
  `urgencyBandClass(days)` — one colour mapping for every countdown, no second
  copy. `daysLeftLabel()` / `workingDaysDiff()` were already shared.
- **Live-clock fix caught while verifying:** this page's bridge never cleared
  `MockData.mockNow`, so the new countdown measured from the frozen demo anchor
  (Mon 5 Oct 2026) and printed `24 Working Days Left` for the 6 Nov slot. The
  page now sets `MockData.mockNow = null` like every other live page, and the same
  row correctly reads `Overdue (1 Working Day Ago)`.

**Receipts** (testing DB, `:8030`): `tests/status-tooltips-filter-chips.spec.ts`
**14 passed** — the new T5.RA-4 asserts a pending row carries a well-formed
countdown and every other status carries none (status read from the page's own row
data; the requested `<td>` has no class hook). The removal guard T5.RA-2 lost its
approve-modal click on purpose — that click decided a real request and ate the
pending fixture the next test needed; the file stays read-only.
Screenshot: `/tmp/opencode/pl-pending-countdown.png` (#26 Pending →
`Fri, 6 Nov 2026 (Week 7)  Overdue (1 Working Day Ago)`; #24 Rejected → no number).

## 2026-11-07 (business logic, user law) — the Urgency column is gone

The user's decision: Approve Requests no longer judges urgency. The lecturer page
already has the one urgency rule (working days), and a second, differently-tuned
rule on the PL page made the same request read red in one place and green in the
other. Measured before the removal: request 28 (pending) counted red on My
Replacements and green here, because this page counted to the **proposed slot**
while the lecturer page counts to the **class date**.

Removed, all of it (the whole urgency notion on this page):

- the **Urgency column** and its badge (`Normal — 5 days left`),
- its **header sort** (and “Urgency” from the sort hint),
- its **tooltip** (`Urgent — 2 days left before the Proposed Class`),
- the **All / Urgent / Normal filter chips** (`#urgencyFilter`) and the
  `Urgency: …` active-filter chip with them,
- the **row stripes** (`.row-urgent` red / `.row-soon` amber left borders),
- the **phone card line** (`Urgency:` badge + tip),
- the **badge in the Approve/Reject compare card**,
- the age pill's `· Urgency: Urgent` tail — it now reads
  `5 days ago · 4+ days old`, keeping its own waiting-time colour,
- the now-dead helpers (`urgencyLevel/Class/Short/Label/Days/Date/Matches/Tip`,
  `setUrgencyFilter`, the `urgencyFilter` state + its save/restore, and the
  page's `MockData.urgencyReferenceDate` bootstrap) and their CSS.

**Kept untouched:** the request-age pill and its colours, the status vocabulary
and tips, the proposed-slot validity badge, the summary cards, sorting by the
real columns, the Approve/Reject flows, and `HtmlBuilder.requestCard`'s optional
urgency block (no live caller passes it any more; the frozen mock template keeps
its own copy, as always).

**A caught mistake, recorded on purpose:** the first cut deleted the block that
also held `compactCohort()` and `venueText()` (they shared the section header),
so the page rendered no rows at all — the receipt failed on “the queue renders at
least one status badge”, and both helpers were restored immediately afterwards.

**Receipts** (testing DB, `:8030`, `TestingFixtureSeeder` rows):
`tests/status-tooltips-filter-chips.spec.ts` **9/9** — its old urgency test
(T5.RA-2: badge↔tip words, the Overdue shift, the TODAY string, the Urgent chip
keeping overdue, the header tip copy) is now a **removal guard**: no badge, no
Urgency header, no chips, no stripes, no `Urgency:` in the age tip, and no badge
in the approve modal; T5.RA-3 keeps the filter-chip law and the card's status tip
and asserts the card carries no urgency. Browser read: 9 headers, 0 urgency
badges/chips/stripes, no console errors; screenshots
`/tmp/opencode/pl-no-urgency.png` and `/tmp/opencode/pl-no-urgency-card.png`.
The table still scrolls horizontally inside its wrapper (pre-existing: 1561 px of
columns in a 1450 px wrapper, `overflow-x: scroll`).

## 2026-11-07 (date/time batch 4, `docs/date-time-format-audit-2026-11-07.md`) — the phone card's Date line gains its comma

The mobile request card printed `Date: 5 Nov 2026 2:00 PM`; it now reads `Date: 5 Nov 2026, 2:00 PM`,
matching every other date-plus-time surface. Desktop layout, tooltips and actions are untouched.

## 2026-11-07 (date/time batch 1, `docs/date-time-format-audit-2026-11-07.md`) — one range joiner in the queue

The Approve / Reject compare cards printed `2:00 PM to 4:00 PM`, while the Request Details modal
already used `~`. `HtmlBuilder.classBlock()` and `replacementBlock()` now join with `~`, which also
aligns the two table columns (they share the same builders) — `2:00 PM ~ 4:00 PM` and
`1:30 PM ~ 3:30 PM`. Thresholds, colours, labels, the Urgency column and every other page are
untouched.

## 2026-11-07 — Urgency now counts down to the **proposed replacement**, not the original class

The Urgency column was measuring the wrong date. A request whose original class was months away
but whose make-up slot was tomorrow read `Normal — 90 days left`; one whose original class had
already passed but whose replacement was still ahead read `Overdue`. The queue is a decision about
the **proposed replacement**, so that is the date the badge (and its tooltip) now count from.

- `urgencyDate(r)` = `r.replacementDate || r.classDate` — one helper, used by the badge label, the
  badge tip, the row tint, the Urgent/Normal filter and the Urgency sort. The fallback keeps a row
  with no proposal (null `replacementDate`) printing a real badge instead of `NaN`; no current row
  in the queue relies on it.
- Levels, colours and thresholds are untouched: past = Overdue, ≤3 days = Urgent, 4+ = Normal, same
  calendar-day count (`Math.ceil`), same badge text (`Urgent — 2 days left`, `Urgent — today`,
  `Overdue — 5 days past`).
- Tip wording is unchanged (`… left before the Proposed Class.` / `… past the Proposed Class.`) — it
  now describes the date it is actually counting. Only the column-header helper text changed:
  "Days until the **original class**" → "Days until the **proposed replacement class**".
- The mobile cards ride the same rule through `HtmlBuilder.requestCard()`, which now passes
  `r.replacementDate || r.classDate` into the page's `urgencyLevel()`.
- `tests/status-tooltips-filter-chips.spec.ts` shifts `MockData.urgencyReferenceDate` against
  `replacementDate` now (and pins the new header tip).

Not changed: the Original Class column, the Week filter (still keyed to the original class date),
status vocabulary, sorting order outside the Urgency column, and every other page.

## 2026-11-05 — the decision modals name the request, and say `Lecture` in full

The 2026-11-05 UI/UX brief (Update 3) on the **Rejection Reason** and **Approve Request** modals. Both confirm
steps are fed by one shared `approvalCard()`, so the card changes below land in both; the title and the presets
belong to the rejection modal alone.

| Item | Before | Now |
|---|---|---|
| Subject | `Operating Systems [L]` | `Operating Systems [Lecture]` |
| Rejection title | `Rejection Reason` | `Rejection Reason (#28)` |
| Lecturer row | circle avatar + 📋 copy-email icon | name + staff ID only |
| Preset chips | `Venue unavailable` … | `Venue Unavailable` … (Title Case, inserted text included) |
| Instruction | `Please provide a reason for rejection (required):` | `Please Provide a Reason for Rejection (Required):` |
| Original card | date · time · `(2 hrs)` | date + week · time · `B110` |

- The full word comes from the existing `UiLabels.classType(type, 'long')` (`L/T/P` → `Lecture/Tutorial/Practical`),
  never a local map, and the payload's `classType` stays the raw code.
- The request ID is read from the request that was opened; a **bulk** reject has no single ID, so its title stays
  plain `Rejection Reason`. The reject modal's card no longer repeats the id (the title carries it now); the Approve
  modal keeps its `#28` chip because its title carries no id.
- The avatar and the copy-email icon are gone from the modal card only — the table rows and the Request Details
  modal keep theirs, and the dead `.lecturer-avatar` / `.approval-lecturer .copy-icon` rules went with them.
- **Shell width:** the two decision modals widen from 420 px to **616 px** — the same width the Request Details
  modal already used (`#modalOverlay .modal`), so all three pop-ups on this page match. At 616 the cards keep
  `date + week` on one rendered line for every week of the semester (the longest real string,
  `Wed, 30 Sep 2026 (Week 14)`, needs 600 px; 570 wrapped both cards, and the Replacement card loses 3 px to its
  green edge). Tablet (≤1024 px → 560 px) and phone (bottom sheet) caps are untouched.
- The Original card is an **opt-in** variant of `HtmlBuilder.classBlock(r, { originalCard: true })`: duration dropped,
  the venue code alone on its own line (user follow-up 2026-11-05: no `Venue Code:` label — just `B110`). Every other
  caller (grids, frozen templates, My Replacements) stays byte-identical; the Replacement card is untouched.
- With the 616 px shell each compare card is 226 px wide, so `Mon, 9 Nov 2026 (Week 8)` stays on one rendered
  line — the brief's three-part shape holds for every week of the semester (checked up to `Wed, 30 Sep 2026
  (Week 14)`).

**Measured** live on the demo DB, request **#28** (Operating Systems, L, Mon 9 Nov 2026 11:00–13:00, B110):
title `Rejection Reason (#28)`; subject `Operating Systems [Lecture]`; 0 avatar / 0 copy-icon nodes; chips
`Venue Unavailable · Insufficient Notice · Slot Conflict · Lecturer Unavailable · Other`; instruction exact; the
Original card reads `Mon, 9 Nov 2026 (Week 8)` / `11:00 AM to 1:00 PM` / `B110`; no console errors.
`tests/request-approval.spec.ts` **10/10** (the new U3-T9g opens both modals, asserts all six updates, and proves the
pending queue is untouched).

## 2026-11-05 — one irreversible line on every final decision

The user's ruling: every confirmation for a decision that cannot be taken back must say so, in the same words as
Cancel Class. A shared partial (`partials/ui-irreversible-line.blade.php`, promoted by the 3rd-duplication rule)
carries the single sentence:

> This action cannot be **UNDONE**.

`UNDONE` in capitals, bold, **warning** amber (`--color-warning` through the existing `.warn-action` keyword). It now
appears directly above the buttons in **four** modals:

| Modal | Before |
|---|---|
| Approve Request | no warning at all |
| Reject Request | no warning at all |
| Cancel Replacement Request | a red uppercase `THIS ACTION CANNOT BE UNDONE.` |
| Cancel Class | the amber line (the model for this change) |

Reversible actions deliberately get nothing — submitting a request (a pending request can be cancelled), hiding a
notification (it can be unhidden), logging out.

The old red `.confirm-irreversible` style is gone with its last user; the red must not compete with the word that
now has one colour everywhere.

**Measured** in a live browser on the demo data: all four modals render `"This action cannot be UNDONE."` with the
keyword at `rgb(212, 136, 15)` / weight `700` / 14 px line, no page errors.

## 2026-11-04 — the venue line drops the room name, keeps the pin

User: remove the room name from **Original Class** and **Requested Replacement** so the cells read
`B010` instead of `B010 — Computer Lab B010`. **The pin icon stays** (user, same day, after the first
pass removed it).

Both cells are built by the shared `ui-common.js` builders, which paint venue through `uiVenueLine()`.
That helper is now `uiVenueLine(code, name, opts)` and prints the code alone when `opts.venueCodeOnly`
is set — pin still leading. The page passes `venueCodeOnly: true` at its two call sites (`:739`
classBlock, `:743` replacementBlock). No third argument anywhere else → every other caller is
byte-identical.

`.cell-sub` is kept — 12 px, muted — so the venue still reads as a sub-line and the row height does not
move.

**Verified** against the demo DB in one browser session at 1600 px:

| check | result |
|---|---|
| Original Class | pin `B010` |
| Requested Replacement | `✓` pin `B010` |
| room name present in either cell | **no** — no `—` in the sub-line |
| `svg` inside either `.cell-sub` | **yes, 1** (the pin) |
| computed style | 12 px, muted token — unchanged |
| row shape | 11 `<td>`, height 122 px (before/after render probe: 81 px / 81 px) |
| **detail modal** | `Venue · B010 — Computer Lab B010` — **unchanged**, as required |
| `/my-replacements` | pin + code — **unchanged** |

## 2026-11-04 — the header is the shared one now

User: this page's header did not look like the rest of the app. It was the last hand-rolled header —
a bare `<h1>` and `<p class="page-desc">` with no `.page-title`, no semester chip, and none of the
partial's spacing.

**Replaced with `@include('partials.ui-page-header', …)`** — the same partial every other page uses.
Title and description content unchanged; the description keeps the `.info-keyword` emphasis from the
previous entry.

**The chip needed one line of script.** The partial always emits `<span class="semester-chip"
id="semesterChip">`, and every other page fills it from `MockData.semester.chipText` — this page never
did, so an empty pill would have appeared. It now fills at init like the others, showing
`202605 · 21-Sep-2026 ~ 27-Dec-2026`.

**Verified against the other pages** by reading the three headers in one browser session at 1440 px:

| | title | chip | description |
|---|---|---|---|
| Request Approval | `.page-title` 22 px / 700 | `202605 · 21-Sep-2026 ~ 27-Dec-2026`, 248 px | `.page-desc` 14 px |
| My Replacements | same | same, 248 px | same |
| My Timetable | same | same, 248 px | same |

No JS errors after the change; no other page touched.

## 2026-11-04 — the page description joins the sweep

The all-pages `.page-desc` update skipped this page because its header is hand-rolled rather than the
shared `ui-page-header` partial — it had a bare `<p>` and no `.page-desc` class at all. Both fixed
here, without touching the header's structure: the paragraph keeps its place, gains the class, and
emphasises its key terms with the same `.info-keyword` used on the other five pages.

`Review and manage **REPLACEMENT REQUESTS** from **LECTURERS IN YOUR PROGRAM**.` — wording unchanged.

## 2026-11-04 — the status filter's label takes Title Case

Minor UI update (user), matching My Replacements. The select's label (its `data-tip`, now also an
`aria-label` so the control has an accessible name) reads **`Filter by Request Status`** instead of
`Filter by request status`. Nothing else changed — same options, same default (`Pending`), same
filter logic.

## 2026-11-04 — the toggle label now reads the same as My Replacements

Minor UI update (user). The toolbar toggle said `Exclude Completed`; it now says
`Exclude <span class="info-keyword">COMPLETED</span> Status` — the exact wording and markup the merged
My Replacements page uses, with the existing `.info-keyword` styling (no new class, no new colour).

Only the label changed. The checkbox id, its checked-by-default state, `toggleHideCompleted()` and
every other control on the page are untouched.

**Follow-up (same day, user):** the chip that toggle produces in the Active Filters bar now says
**`Exclude COMPLETED Status`** too, so the chip and the control that made it agree. It is plain text
like the My Replacements chip (no `.info-keyword` inside a chip) — verified by reading both pages:
`/request-approval-ui` renders `Status: Pending · Exclude COMPLETED Status · Clear all`, and the
toggle's innerHTML is byte-identical to `/my-replacements`.

## 2026-11-04 — consistency pass, part 2: the cells and the schedule rows (1h, 3E-3)

**1h — the two table cells now read exactly like My Replacements.** `HtmlBuilder.classBlock()` and
`replacementBlock()` gained an opt-in `{ merged: true }` mode: duration in **words** (`(1 hour)`)
and the **pin-icon venue line** (`B010 — Computer Lab B010`), with the Original Class cell carrying a
venue at all for the first time. The PL-only slot-validity badge stays, ahead of the venue in the
Requested Replacement cell. Every frozen template and legacy caller omits the flag and renders
byte-identically — that is why the mode is a flag rather than a rewrite.

The three bits the merged page had page-locally (`hoursToWords`, the range→hours parse, the venue
line) are now shared helpers in `ui-common.js` (`uiHoursWords`, `uiHoursFromRange`, `uiVenueLine`)
instead of a third copy on this page.

**3E-3 — the schedule tabs match the same shape.** `Start Time` + `End Time` collapse into ONE
`Time` row (`9:00 AM ~ 11:00 AM`), so each tab is Week · Day · Date · Time · Duration · Venue (plus
Slot Validity on the replacement tab) — the merged pop-up's order.

**§4 — the reference pass.** With the merged page live, this file was re-read side by side against
it; batches A–C above *are* that diff. Matching today: status vocabulary + tooltips, compact cohort
codes, the icon-led cells with duration in words, the pop-up shell (facts strip + three tabs, 616 px),
the one-`Time`-row schedule, `code — name` venues, the email copy contract, the `data-tip` singleton
and the destructive-action confirm. Kept different on purpose (PL-only): ten columns, the
slot-validity badge, the `Approved/Rejected Requests` cards and the Approve/Reject footer.

**Receipts:** live row — Original `Thu, 24 Dec 2026 (Week 14)` / `12:00 PM to 1:00 PM (1 hour)` /
`B010 — Computer Lab B010`; Requested `Fri, 13 Nov 2026 (Week 8)` / `1:00 PM to 2:00 PM (1 hour)` /
`✓ B010 — Computer Lab B010` · `request-approval.spec.ts` 6/6 (U3-T9d now asserts the single `Time`
row and the absence of `Start Time`/`End Time`) · `status-tooltips-filter-chips.spec.ts` 9/9 ·
`pages-parity.spec.ts` 6/6 · `replacement-home-status-badges.spec.ts` 11/11 (the merged page is
unaffected by the shared-helper change).

## 2026-11-04 — consistency pass, part 2: the pop-up shell (3A, 3D)

The **timeline is gone** from this pop-up, the old *Request Info* tab is absorbed by a fixed **facts
strip** (the merged page's M7 shell), and the tabs are now `Original Class Schedule` ·
`Proposed Replacement Schedule` (absent when nothing is proposed) · `Request Status (<short>)`, with
the status **last**.

**Facts strip** — Staff (Proposer) `Name (staffId)` with the shared copy-email contract · Cohorts
(compact, joined ` + `) · Students (`A + B = total` through the shared `cohortBreakdown`) · and,
while the request is pending, the slot **Conflict** badge. Those are the four things a PL decides
with; the old tab's subject code/name already live in the pop-up subtitle and the requested
timestamp moved into the status tab, so nothing was lost.

**Status tab** — the badge plus the shared one-sentence description, then `Requested At`,
`Rejection Reason` (rejected only), `Remark`, and `Reviewed By` / `Reviewed At` when decided (the old
*Review* tab folded in — metadata, not a history rail).

**Width** — the shared `.modal` base is 420 px, which cannot fit three tabs; this one pop-up is now
**616 px**, the same shell as My Replacements (phones are unaffected — `width:100%` still wins below
the max-width).

**Receipts:** `request-approval.spec.ts` 6/6 (U3-T9c now asserts the strip, the three tabs and the
absence of `.modal-timeline`; U3-T9d follows the renamed tab) ·
`status-tooltips-filter-chips.spec.ts` 9/9 · live on `:8000`: a pending row shows
`Staff / Cohorts / Students / Conflict` and the three tabs above; a rejected row drops the Conflict
fact and its status tab carries `Rejection Reason` + `Reviewed By/At`.

## 2026-11-04 — consistency pass, part 2 begins: the status vocabulary and the room names (1f, 3B, 3C)

Source: `prompts/request-approval-ui CONSISTENCY.md` (part 2, batch A). **SDD waived** —
display-only, one page plus one additive payload key set, no schema and no behaviour change.

| # | Change |
|---|---|
| **1f** | The queue badge, its tooltip, the pop-up row and the mobile card now read the **shared M5 vocabulary** (`ReplacementRequestMapper::statusLabel/Short/Sentence/Tip`, carried on the row as `requestStatusLabel/Short/Sentence/Tip`). The page-local `statusTooltip()` **3-key map** and the local `statusDescMap` are deleted. |
| **3B** | The pop-up's *Status* row prints the same label (`Pending — Waiting for Approval`), and *Status Description* prints the shared sentence instead of the local map. |
| **3C** | Both schedule tabs print **`code — room name`** (`B010 — Computer Lab B010`): `toRow()`/`classRow()` now carry `replacementVenueName` and `venueName`, and `ApiReadController::myRequests()` strips both so `/api/requests/my` keeps its pinned key set. |

**The bug 1f closes:** the old 3-key map covered Pending / Approved / Rejected only, so a
**Completed** (or Cancelled) badge rendered with **no tooltip at all**. All five decided states now
carry their copy, and `statusTip()` gained an `$audience` parameter for this one reason — M5's strings
are written for the lecturer ("your request"), while the PL reviews the same states from the other
side of the decision. The lecturer's strings are byte-identical to the approved M5 text; the PL voice
keeps the copy the page already shipped ("You have APPROVED this request.").

**The API guard this needed:** nothing pinned `/api/requests/my`'s key set — `SpanReservationTest`
asserts `replacementTime`'s *value* and the e2e spec reads values, so a leaked `replacementVenueName`
would have passed every existing test. `tests/Feature/MyRequestsApiShapeTest.php` now pins the exact
23-key row and proves the two view-only keys are stripped.

**Receipts:** `MyRequestsApiShapeTest` 2/2 (new) · `MyReplacementsPayloadTest` 12/12 · live on `:8000`
— badge `Pending — Waiting for Approval` + tip `This request is AWAITING your review and decision
(Approve or Reject).`, pop-up `Status / Status Description` from the payload, both tabs
`B010 — Computer Lab B010` · `status-tooltips-filter-chips.spec.ts` 9/9 (RA-1 rewritten: no
`[data-tip]` filter, so a tip-less badge fails) · `request-approval.spec.ts` 6/6.

## 2026-11-03 — consistency pass, part 1 (table, cards, schedule rows) — display only

Source: `prompts/request-approval-ui CONSISTENCY.md`. **SDD waived** by the user (display-only,
one page, no schema, no business logic, no payload change). Part 2 — everything that has to match
the merged **My Replacements** page — waits for the `replacement-merge-one-table` lane; the split
and the reasons are listed at the end.

**Table**

| # | Change |
|---|---|
| 1a | **Students column removed** from the table (the student count stays in the pop-up's *Original Class* tab and in the Approve/Reject context card) |
| 1b | **Cohort column added** immediately before *Original Class* — one **compact code per line** (`RSD3(S1)G2`, `RSD2(S1)G2 / RSD2(S1)G1`), not sortable (a multi-cohort row has no single value) |
| 1c | `Lecturer` → **Staff (Proposer)** |
| 1d | `#` → **Request ID**, and the cell now prints the **real request id** (`#26`) instead of the row counter — the header would otherwise be a lie |
| 1e | The urgency badge now carries the level **and** the remaining days: `Normal — 52 days left`, `Urgent — 2 days left`, `Urgent — today`, `Overdue — 5 days past`. Same category, same badge styling; the tooltip keeps its fuller sentence, and the Requested-age tooltip keeps the bare word |
| 1g | `Proposed Replacement` → **Requested Replacement** (header + the sort hint line) |

**Summary cards** — `Approved` → **Approved Requests**, `Rejected` → **Rejected Requests** (ids
unchanged, so every count and filter still works).

**Request detail modal**

- **Week row** added to *Original Class* and *Replacement Class* (`Week 14` / `Week 7`), derived
  from the date + semester start — no new payload key.
- **Duration** now also on the *Replacement Class* tab, in words (`1 hour`, `2 hours`) from the
  requested time range. The *Original Class* tab already printed words.
- **Cohort(s)** in the modal is the **same compact codes joined with ` + `**
  (`RSD3(S1)G2 + RSD3(S1)G3`) — the table stacks them, the modal joins them (the user's own
  format ruling 2026-11-03).
- **Footer**: the **Close** button is gone (the header ✕, the overlay click and Esc still close),
  and **✕ Reject** now sits **bottom-left** with Approve bottom-right.

**Not applied yet — they depend on `replacement-merge-one-table` (do not lose this list):**
1f status-badge wording (Pending/Completed) · 1h the two schedule cells matching *My Replacements* ·
3A replacing the timeline with the facts strip · 3B modal status wording · 3C the one-row schedule
block (Date · Time · Venue `B103 — Tutorial Room`) · 3D the three-tab order · 3E's remaining half.
All seven need the merged page's vocabulary (`ReplacementRequestMapper` status helpers, M5) or its
modal shell (M7), which that lane is building right now. Doing them early would create a second
copy that has to be deleted.

**Measured side effect (honest):** the table is ~150 px wider (Cohort column + the longer urgency
text). At 1400 px it already scrolled before this change; at **1600 px it used to fit exactly and
now needs ~40 px of horizontal scroll**. `.grid-scroll` does scroll, so every column stays
reachable (verified by scrolling to the Actions buttons).

**Tests** — `tests/request-approval.spec.ts` U3-T9c now asserts the header set (no *Students*, no
*Lecturer*, no *Proposed Replacement*; Cohort directly before Original Class) and that the Request-ID
cell prints `#<id>` and every cohort cell holds compact codes (9/9 green);
`tests/status-tooltips-filter-chips.spec.ts` RA-2 follows the new badge text (9/9 green);
`tests/ui-regression.spec.ts` + `tests/nav-identity.spec.ts` 16/16 green. No PHP file changed.

## 2026-11-03 — the approved row can read **Completed** (no code change on this page)

SDD lane `.sdd/changes/request-completed-status/` (C1/C2/C3). Nothing in
`request-approval.blade.php` was edited — this page reads `r.status` straight from
`ReplacementRequestMapper::toRow()`, which now returns the **effective** status.

What changes on screen, by itself:

| Place | Before | After (a request approved for a class that has already happened) |
|---|---|---|
| Row badge | `Approved` | `Completed` (dot stays `dot-success`) |
| Status tooltip | "You have APPROVED this request." | "Replacement conducted" (already mapped) |
| **Approved** summary card | counted it | does **not** count it |
| **Reviewed** summary card | counted it | still counts it (that card matches `Approved`, `Rejected` **and** `Completed`) |
| Exclude Completed toggle | never hid anything | hides it when ticked (default state unchanged) |
| Status filter | never matched a row | `Completed` now matches |

The derived status is **not** stored: the row's `status` column stays `approved`, so the approval
and rejection paths, the queue buckets and the one-active-request index are untouched.

Verified (no edit): the `Completed` option in `#statusFilter` (:259), the toggle markup
(:268–270) and its filter (:655), the `Approved`/`Reviewed` counts (:756–758), and the status dot
(:953). `ApprovalWorkflowTest` gained `test_mapper_reads_a_past_approved_request_as_completed`
(the row reads `Completed` while its bucket stays `queue`) and
`test_mapper_never_guesses_completed_without_a_readable_date` (an unreadable replacement date keeps
`approved`).

# Request Approval — changelog

## 2026-11-02 — visible demo reminder above the timeline rail

The shared `DetailModal.timeline()` now prints one line of plain text directly
above every rail: **"the timeline function got error -- ask SV does this
useful/helpful for Them"**. On this page that rail is the one with the
permanently empty **Viewed** step (no `viewedAt` source exists) — see
`prompts/extra tasks/task-02-replacement-history-timeline.md`. The note is a
demo aid, not product copy; styled by `.modal-timeline-note` (muted token,
italic). Delete it with the rail rebuild.

## 2026-10-30 (SDD `status-tooltips-filter-chips`) — status/urgency tooltips, the real Overdue badge, filter-bar upgrade

**a) Status tooltips** (the user's frozen wording; shared `data-tip`,
**ABOVE** the element): page-local `statusTooltip()` map + `statusTipAttr()`
tag the queue-table badge (keeps its `onclick`), the detail-modal Status
row, and the **mobile card** (new `opts.statusTip` into the shared
`HtmlBuilder.requestCard` — absent opt byte-identical). Tips (the 3
statuses this queue renders): Pending → "This request is AWAITING your
review and decision (Approve or Reject)." / Approved → "You have APPROVED
this request." / Rejected → "You have REJECTED this request."

**b) The Overdue urgency level (the user's sanctioned badge-logic change —
presentation only; status logic/DB/queue untouched).** `urgencyLevel()`
now consumes `urgencyDays()` (`d < 0 → 'overdue'`); `urgencyClass` →
`urgency-overdue`, `urgencyLabel` → **Overdue**; new solid-red pill
`.urgency-overdue { background: var(--color-error); color:
var(--color-on-error) }` (tokens only — same red family, one severity
step up from Urgent's translucent container); overdue rows keep the
`row-urgent` highlight; **the Urgent filter still matches overdue rows**
(new `urgencyMatches()` — the silent-drop law; the all/urgent/normal
button set NOT extended; Normal filtering unchanged). Dynamic urgency
tooltips via `urgencyTip(r)` (N computed at render, singular "day", N=0 →
"Urgent — the Proposed Class is TODAY.") on the confirm-modal badge, the
table cell, and the mobile card (`opts.urgencyTip`); the Urgency
column-header tip synced to the three-level copy ("past = Overdue (solid
red), ≤3 days = Urgent (red), 4+ days = Normal") so it stays truthful.
Age chips flow the new label ("… · Urgency: Overdue" — reads correctly).

**c) Filter bar** — `#clearFilters` → **`#resetFilters`** (behaviour
unchanged) with the tip "Reset all filters to DEFAULT"; the bar label
`Filters:` → **`Active Filters:`** (now identical to my-request-history);
a **Clear all** chip (`filter-chip filter-chip-clear-all`, neutral dashed
pill in theme.css, tip "Remove every active filter") rides the chips
innerHTML rebuild while any chip exists and triggers the EXISTING
`resetFilters()` verbatim (one function, two triggers — real reset, never
a visual hide; the default `Status: Pending` + `Exclude Completed` chips
legitimately remain). Individual ✕ chip removes untouched. Verified:
pint + phpstan(1G) + `tests/status-tooltips-filter-chips.spec.ts` (see the
change-level log).


## 2026-10-25 (the user's part 2) — the Exclude Completed chip + hover hints on EVERY toolbar element

**a) Chip parity with my-request-history:** `renderFilterChips()` now pushes
the **Exclude Completed** chip while the toggle is on — same label, same
position (last), same ✕-remove behaviour (the ✕ unchecks
`hideCompletedToggle` and calls `toggleHideCompleted()`, which refreshes
state + table + summary + saved filters). The chip-row label became
conditional (`chips.length > 0` … like my-request-history) so no orphan
"Filters:" renders when every filter is off.

**b) Hints on every toolbar element** — the shared `data-tip` attribute
(`initDataTipTooltips` renders it FIXED **ABOVE** the element; existing UI
design, no new mechanism): search → "Search by course code, name, or
lecturer" (this page's haystack = `courseCode + courseName + lecturer` ✓
honest), status select → "Filter by request status", urgency chips →
"Show requests of any urgency" / "Show only urgent requests" / "Show only
normal requests", week-nav → "Previous week" / "Pick the Original Class week to display" /
"Next week" (via NEW optional `prevTip`/`selectTip`/`nextTip` params on the
shared `partials.ui-week-nav` — **null by default, so every other page
renders byte-identical**), toggle → "ON = completed requests are excluded
from the list", Reset Filters → "Reset all filters" (verbatim
my-request-history's), result count → "Rows matching the current filters".


## 2026-10-25 (the user's consistency fix) — the toggle says "Exclude Completed"

The completed-exclusion toggle now speaks the SAME words as
`/my-request-history-ui` (identical function → identical label): the visible
label `Hide Completed` → **`Exclude Completed`** — in the live toolbar
(`request-approval.blade.php`) AND its design-template mirror
(`request-approval-UI-design-template.blade.php`, so template ↔ live stay in
sync). Internal identifiers (`hideCompletedToggle`, `toggleHideCompleted()`,
the `hideCompleted` state) are not user-visible and were left alone. No test
locks either wording. Noted, NOT part of this fix: my-request-history also
renders an `Exclude Completed` filter chip when the toggle is active —
request-approval's chip row has no chip for this toggle (possible follow-up).


## 2026-10-18 — SDD `span-aware-approval-flow` slice C+D (the REAL page)

The route `/request-approval-ui` stops serving the D10 mock the moment
`App\Livewire\RequestApproval` exists (`routes/web.php`'s `class_exists`
branch — no route edit). The REAL page is a byte-for-byte port of the frozen
template with only the documented BRIDGE marks:

- **One page, real rows** — FCFS pending (`submitted_at` ASC, FR 3.2) + the
  decided bucket (approved/rejected; the `Completed` filter stays an honest
  EMPTY state — the out-of-scope leg). `reviewedBy = approver.name`,
  `reviewedAt = decided_at` (the decided row's detail modal).
- **ONE shared validity predicate** — the queue's 🟢/⚠️ badge and the
  approve/reject actions read the same `ApprovalValidity::evaluate` (FR 3.4;
  2-case map: 'already decided' / the existing slot_not_available named-time
  copy).
- **The §10.0.6 promotion**: the request→row mapper is now ONE shared
  `App\Services\ReplacementRequestMapper` (was: ApiReadController's
  `myRequests()` closure + MyRequestHistory's `requestRow()` — the third
  consumer is the PL queue). Its span read is DATA-DRIVEN (junction min/max
  for pending AND approved, legacy anchor fallback for junction-empty rows of
  ANY bucket — never bucket-keyed (spec RR1).
- **Approved-bucket junction switch (Q3=A)** — the approved display reads the
  junction's true range everywhere: timeline replacement block,
  MyRequestHistory, API `myRequests()`, the venue-slots API marker block
  (full-run state 4), the PL queue's decided bucket, and student
  `ReplacementHistory` rows. span-1 rows stay byte-identical.
- **The decide surface** — Approve (optional note → `details.approver_note`)
  / Reject (reason mandatory SERVER-side; the frozen chips are UI-only;
  `Other` = any typed text stored verbatim). **NO undo** (user ruling
  2026-10-17): decisions are final; the confirm popups carry the frozen
  copy. Bulk = sequential FCFS via the same real actions; a stale row fails
  politely (its conflict toast) while the rest decide; ONE shared reject
  reason covers every ticked row.
- **The emails** — ReplacementSubmitted (submit-side, FR 4.15) /
  ReplacementDecided (FR 2.13, dispose + reason) / ReplacementApprovalNotice
  (FR 1.9, cohort students) — ALL queued (FR 4.16, database connection),
  capture-at-queue-time (the mail body is a snapshot; Rejected rows never
  re-read a deleted junction). Dev/demo = `MAIL_MAILER=log` — NOTHING really
  sends (user ruling A-now/B-later on the in-app panel: the frozen 🔔 panel
  STAYS mock-fed; its real-data wiring is a planned follow-up mini-SDD).
- **The D7 original-release** — approve stamps
  `class_exceptions(official_event)` for the original occurrence (guard: no
  write when an exception exists — the unique pickets it; a racing stamp
  CONTINUES via savepoint) + frees the original occurrence's OWN booked
  venue rows (`available`, version-checked, identity-pinned — the old
  room+time becomes bookable by others per Objective-3's letter). Visible
  on week pages only post-holiday (the W14 case is a holiday week).

## 2026-10-18 — T18/T19 postscript (the email infrastructure)

- `tests/Feature/EmailQueueTest.php` — T18's two families: the enqueue
  assertions (per-PL submitted copies, decided + herteroés cohort-notice with
  the roster count, decided-only for reject, the Q13 decide-own case) + the
  NFR 1.4 REAL-worker drain (NO Mail::fake / NO Queue::fake; the jobs-table
  row is REAL, `queue:work --once` consumes it inside one cycle, elapsed
  <1 min) — the drain fixture drops the cohort attach so exactly ONE job
  runs.
- `docs/queue-and-emails.md` — the runbook (dev = log mailer, jobs persist
  until the worker claims them, failed_jobs stock behavior, the SMTP flip being
  a `.env` change only).

## 2026-10-18 (user report fix) — STUDENTS "undefined" + the dead detail modal

The live page reported two issues, same root: the queue bucket's rows lacked
the COHORT METADATA (`totalStudents` / `cohorts` / `cohortCounts`) —
`renderRow` printed `undefined` in the STUDENTS column and
`openModalById` threw on `cohorts.join()` inside the detail-modal builder
(silently deadening the click → popup). **Fix:** the shared mapper's
`toRow` now carries the queue bucket's cohort trio (SPACED labels +
values-array counts + the summed total — `ReplacementHistoriesTest`-pinned
byte shapes are untouched for the other buckets). Smoke: new
`U3-T9c` pins STUDENTS numeric + row-click → modal opens/closes.

## 2026-10-18 (user request) — Request Details modal copy pass

The modal's Original/Replacement tabs now print:
- CONSISTENT HUMAN DATES — '24 Dec 2026' (never the raw ISO; the
  "17 Oct 2026, 9:02 AM" pattern family);
- hours in WORDS — '1 hour' / '2 hours' (was '1h');
- the time SPLIT — Start Time and End Time are separate rows on BOTH tabs
  (the replacement range "12:00 PM to 1:00 PM" split, 12h form);
- the Slot Validity ✓ with its FULL sentence ('✓ Slot available' / '⚠ …' +
  the conflict reason when stale) — the tooltip-only description is gone.

## 2026-10-18 (user request 2) — DAY row in the Replacement Class tab

The Replacement Class tab now carries a **Day** row (Date / Day / Start Time /
End Time / Venue / Slot Validity — the same order as the Original Class tab),
the weekday derived from the replacement's ISO date. `U3-T9d` pins it.

## 2026-10-18 (user report) — the PL nav badge was 1 HERE, 8 EVERYWHERE ELSE

The badge (`window.navPendingBadge`) had TWO sources disagreeing: the D10-era
`MockData.approvalRequests` mock seed carried **8 fake pending rows** (so
every untouched page's `updateNavBadge()` printed 8), while the REAL page
bridged the payload → **1** (the demo's true pending count is 1 — request
#8). Fix (the shared nav partial is the single source now):

- `partials/ui-nav-bar.blade.php` renders the badge count SERVER-side from
  `ReplacementRequest::where('status','pending')->count()` (PL-only; hidden
  when 0) AND emits `window.PENDING_NAV_COUNT = <n>;` (the `{!! !!}` form —
  the first cut's `{{ }}` HTML-escaped it, the browser never executed it).
- `ui-common.js::updateNavBadge(count)` reads the REAL global first (the
  queue's fresh count after a decision), then the layout's server value, and
  only falls back to the mock-era count for pages that never get the
  partial's global — so the fake 8 can no longer reach a PL navbar.
- The approval page re-derives the count from each wire response
  (`applyDecisionResponse`), so the badge tracks the live queue after
  approve/reject/bulk.
`U3-T9e` pins the accuracy + cross-page consistency (approval page vs
my-timetable page = the same real number).

## 2026-10-27 · Detail modal Total Students = A+B=total (BMSE3153-class-info)
The Original Class tab's "Total Students" row now renders the shared
`cohortBreakdown(r)` (ui-common.js) — multi-cohort rows show `A + B = total` from
the payload's positional `cohortCounts` (ReplacementRequestMapper already emitted
that field; mock `requests` carried it too — no payload work needed). Single-cohort
rows keep the plain count. List-table "Students" column stays the plain number
(compact column, not a modal row).

## 2026-10-30 (lane `ra-result-count-showing-of`) — the result counter matches the house format

The result counter rendered a bare runtime count (`X result(s)`) via a
page-local `updateResultCount()` shadow — every other list page uses the
shared `updateResultCount(cfg)` helper's `Showing X of Y results` format.
The user ruled: match my-request-history.

- FIX: the page-local function DELETED; the single renderTable() call site
  calls the SHARED helper with the frozen cfg
  (`elId: 'resultCount', data: currentFiltered, total:
  MockData.approvalRequests.length, label: 'results'`) — the identical
  wording/spacing. The static `#resultCount` span stays EMPTY (server-driven
  data; no baked default — renderTable() fills it on first paint).
- The Y truth: the span-aware payload's pending + decided rows
  (unscoped, the frozen span-aware behavior); Cancelled/Completed filter
  selections honestly show 'Showing 0 of Y results'; approve/reject flips a
  row INTO the decided bucket and renders a fresh payload — Y recomputed at
  call time.
- Receipt: the structural (the Playwright receipt waived by the user this
  session — recorded in the lane's review-log): the local def gone, ONE
  shared call with the frozen cfg, ui-common.js untouched; suite 277/277;
  pint/phpstan green.

## 2026-10-30 (lane `filter-clear-all-off`) — Clear all takes Status: Pending OFF too

Request Approval's Clear all previously ran `resetFilters()` verbatim — the
page default = Status: Pending + hideCompleted on, so the two default
exclusion chips SURVIVED the click (Active Filters: stayed). The new
`clearAllFilters()` = the resetFilters body with `statusFilter = 'all'` +
BOTH hideCompleted statements flipped (the variable the chip branch reads +
the checkbox the ✕ sets) — the bar renders EMPTY, the controls at OFF. The
Reset Filters button keeps the DEFAULT law (Status: Pending + hideCompleted
on — the tip 'Reset all filters to DEFAULT') byte-identical. Same receipts
as the sibling page (the status-tooltips spec 9/9, pint + phpstan 0, 277/277).

## 2026-11-06 — Pending badge tip names the auto-reject deadline

The queue's `.badge.status-pending` tooltip gains a second line from the shared
vocabulary (`ReplacementRequestMapper::statusTip`, PL voice): *If you have not
Approved it by <deadline>, it will be Automatically Rejected.* The deadline is
the proposed start minus 3 working hours (Mon–Fri 08:30–17:30, weekends and
semester holidays excluded) — the same clock `php artisan requests:auto-reject`
uses. Full write-up + receipts: `status-tooltips-filter-chips-changelog.md`
(2026-11-06). Every other status tip is byte-identical.

## 2026-11-06 — the Week filter survives Approve/Reject (the morph was eating it)

User report: after approving or rejecting, the **Week** dropdown went blank while its filter chip stayed
and printed a bare `Week: `.

**Cause (measured, not guessed).** The week selects have no server-side options — `populateWeekSelect()`
builds them from `weekRanges`/`weekData`. A page component's round-trip re-renders the whole view, and
Livewire morphs the DOM from the server's HTML, where that `<select>` is empty: the options are deleted
and the value collapses to `''`. `renderFilterChips()` then rebuilt the chip from the now-empty select,
so `selectedOptions[0]` was `undefined` and the label fell back to the empty value. Measured pre-fix with
a non-writing round-trip (`approve` on an unknown id): `options 15 → 0`, `value '8' → ''`, chip
`Week: ×`.

**Fix.** `public/js/ui-common.js` now snapshots every registered select's value before a round-trip and
restores the options (from the same cfg) plus the value after it:

- `withWeekLivewireGuard()` wraps `pageLivewire()`'s `call()`, so the restore lands **before** the
  caller's `.then()` — which is what rebuilds the chips. (A deferred restore is too late: the chip is
  written first and keeps the blank label.)
- a `Livewire.hook('commit')` net covers every other round-trip that re-renders a page component
  (`wire:click` and friends).
- Only the JS-built options and the value are restored — attributes still morph, so
  `cohort-timetable`'s server-driven `disabled` keeps working. No `wire:ignore`, no per-page code.

Nothing else changes: the chosen week still drives the filter, the chip label, the arrows and the
persisted filter state.

Receipt: `tests/week-filter-preserved.spec.ts` — U1 Approve, U2 Reject, U3 Cancel Pending
(my-replacements), each selecting week 14, acting through the page's own modal, then asserting the
dropdown still has 15 options / value `14` / label `Week 14` **and** that the chip still names the week.
Red before the fix (U1 + U2), green after; guarded to the testing DB because it writes.
