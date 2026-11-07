# Business Logic Update — Use Working Days

**Primary page:** `replacement-home` (My Replacements) · **Date:** 2026-11-07 · **For:** PI
**Status:** brief only — nothing implemented. Line numbers are from today's `HEAD`.
**Scope law:** implement **Section 1 only**. Section 4 is an **audit report** — no code for it until the user approves each row.

---

## 0. How to work on this (instructions for PI)

- **Section 1 is fully specified** (§2.3–§2.5). Implement exactly that; there is no design freedom left in it.
- Use **SDD explore → propose → apply only if you need it**. If your diff matches §2.5, say so in your report
  ("SDD not needed — mapped, 3 edits") and move on.
- **Do not implement Section 4.** Investigate, verify the table below, add anything missed, and **stop**.
  One row = one user decision.
- **Do not touch** anything outside the files listed in §3. No refactors, no renames, no formatting sweeps.
- Never push. One conventional commit. Update the page changelog.

---

## 1. What the user asked (verbatim)

> **Business Logic Update — Use Working Days**
>
> **Target:** Existing class date urgency display (e.g. `.cell-class-block`).
>
> ### 1. Update Urgency Calculation
> Replace calendar-day calculations with **working-day calculations**.
>
> | Original Class Date | Urgency Class | Colour | Display |
> | --- | --- | --- | --- |
> | Before today | `urgency-high` | Red | `Overdue (n Working Days Ago)` |
> | Today | `urgency-high` | Red | `Today` |
> | 1–7 working days ahead | `urgency-med` | Yellow | `n Working Days Left` |
> | More than 7 working days ahead | `urgency-low` | Green | `n Working Days Left` |
>
> Today is always `urgency-high` (Red), regardless of the time.
>
> **Working-day rules:** Monday–Friday only. Exclude Saturdays, Sundays and Sabah Public Holidays.
> Calculate dynamically using the actual current date. Do not count calendar days as working days.
>
> ### 2. Audit Other Business Logic
> Review the entire project for existing business logic that calculates or depends on days (overdue
> dates, deadlines, urgency) or hours (approval deadlines, time limits, booking restrictions). Identify
> where calendar days/hours are used today and whether they should follow TARUMT Sabah working
> days/hours instead.
>
> ### 3. Report Before Making Further Changes
> Provide a summary table: Business Logic · Current Rule · Suggested Working-Time Rule · Affected Page / File.
>
> **Important:** Implement Section 1 only. For other business logic, investigate and report your findings
> only. Do not change other rules until I review and approve them. Avoid unrelated modifications.
> Verify the updated urgency calculations, including weekends and public holidays.
>
> **Goal:** Gradually make all project time-related business rules consistent with TARUMT Sabah working
> days and working hours.

---

## 2. Section 1 — the facts, then the exact change

### 2.1 Where "class date urgency" lives today

There is exactly **one** live urgency display with `urgency-high / urgency-mid / urgency-low`:
**My Replacements**. The days-left text is printed **inside** the `.cell-class-block` of the
**Original Class** cell (`formatClassBlock()`), and the same text appears in the mobile card footer.

| Surface | Code |
| --- | --- |
| Desktop table, Original Class cell | `resources/views/livewire/replacement-home.blade.php:441-442` (`formatClassBlock`), fed to the merged row at `:699-703` |
| Mobile card footer | `resources/views/livewire/replacement-home.blade.php:771-778` → `HtmlBuilder.replacementHomeCard` (`public/js/ui-common.js:2727-2746`) |
| The count | `daysLeft(iso)` — `replacement-home.blade.php:497-502` |
| The colour class | `urgencyClass(days)` — `replacement-home.blade.php:504-507` |
| The words | `daysLeftLabel(days)` — `public/js/ui-common.js:2431-2444` |
| The colours | `.urgency-high / -mid / -low` — `replacement-home.blade.php:58-60` |

Today's rules (calendar days, `Math.ceil((target − today) / 86400000)`):

```js
function urgencyClass(days) {
    if (days <= 7) return 'urgency-high';    // ≤ 7 calendar days → red
    if (days <= 30) return 'urgency-mid';    // 8–30              → amber
    return 'urgency-low';                    // > 30              → GREY (not green)
}
// label: "Overdue (n days ago)" / "Today" / "1 day left" / "N days left"
```

So three things change: **how the number is counted** (working days), **the thresholds**
(1–7 amber, ≥ 8 green) and **the low colour** (grey → green).

**Do not touch**: `.cell-class-block` itself (it is shared with the Approve Requests compare
cards), `HtmlBuilder.replacementHomeRow`'s legacy 9-column branch, the frozen
`resources/views/ui-design-templates/*` copies, or the Approve Requests badge
(`request-approval.blade.php` — that is audit row 2, not this task).

### 2.2 The working-day calendar (reuse, do not invent)

The app already has **one** working-day definition, in two places:

- **Server:** `app/Support/WorkingHours.php` — Mon–Fri, 08:30–17:30, holidays = the semester's
  `holidays` rows resolved through `semesters.start_date` (`holidays()` `:212-235`).
- **Client:** `leadTimeCutoff` / `isSlotTooSoon` (`public/js/ui-common.js:95-148`) and
  `ClassCancellation._holidayDateKeys()` (`:4203-4219`), which already derive holiday **dates** from
  `MockData.semester.startDate` + `MockData.holidays` (`{week, dayIndex, label}`).

"Sabah Public Holidays" therefore means **exactly the holiday rows the timetable greys out** — never a
hard-coded calendar, never a second list. On the live My Replacements page,
`MockData.semester` and `MockData.holidays` are filled from the page payload
(`replacement-home.blade.php:1212-1214`, `ReplacementHome.php:144-151`) and
`MockData.mockNow = null` (`:1215`), so "today" is the **real** clock via `getTodayMs()`
(`ui-common.js:2418-2425`).

### 2.3 The new rules

Classify by **calendar date** first, then count **working days**:

| Class date vs today | Class | Colour | Text |
| --- | --- | --- | --- |
| earlier calendar date | `urgency-high` | `var(--color-error)` | `Overdue (n Working Days Ago)` / `Overdue (1 Working Day Ago)` |
| same calendar date | `urgency-high` | `var(--color-error)` | `Today` |
| 1–7 working days later | `urgency-med` | `var(--color-warning)` | `n Working Days Left` / `1 Working Day Left` |
| 8+ working days later | `urgency-low` | `var(--color-success)` | `n Working Days Left` |

- **Today is high regardless of the clock time.** The page is date-only; a 9:00 AM class that has
  already ended is still "Today" and still red.
- Capitalisation follows the user's table literally (`Working Days Ago`, `Working Days Left`).
  If the user later prefers the old sentence case, it is a one-string change — do not decide it here.
- The **number** is a working-day count, never a calendar-day count.

### 2.4 The counting algorithm (put it in `ui-common.js`, share it)

Add next to `daysLeftLabel` (≈ `public/js/ui-common.js:2431`). Free functions, same style as
`leadTimeCutoff`:

```js
/* The semester's public-holiday DATES as 'Y-m-d' keys — MockData.holidays carries
   week + dayIndex only, so resolve through semester.startDate (same arithmetic as
   generateWeekData / ClassCancellation._holidayDateKeys). Missing payload → empty set. */
function workingDayHolidayKeys() { ... }

/* Mon–Fri and not a holiday. Accepts a Date or a 'Y-m-d' string. */
function isWorkingDayDate(d) { ... }

/* Signed working-day distance: working days in (from, to] when to >= from,
   negated when to < from. Both are local midnights. */
function workingDaysBetween(fromDate, toDate) { ... }

/* The My Replacements number: signed working-day distance from getTodayMs()
   to the class date. Accepts the payload's 'YYYY-MM-DD'. */
function workingDaysDiff(iso) { ... }
```

Counting law:

- **Future** — count each Mon–Fri day in `(today, classDate]` that is not a holiday.
- **Past** — the same walk in reverse: each Mon–Fri day in `(classDate, today]` that is not a holiday.
- **Same date** — `0`.
- Forward and backward walks must agree (the distance is symmetric), so one function can serve both.
- Weekend and holiday days contribute nothing; the class date itself counts only when it is a working day.
- **Clamp to 1** whenever the calendar distance is non-zero but the working count lands on 0
  (e.g. a class on a Sunday, or Sat → Mon where Mon is a holiday). Never print `0 Working Days…`;
  `0` means "Today" and only "Today". This is a degenerate case the grid normally forbids — document it
  in the test.

Type safety: parse `'YYYY-MM-DD'` manually (`new Date(y, m-1, d)`), **never** `new Date('YYYY-MM-DD')`
(UTC drift would move "today" by a day in +08:00). Use `getTodayMs()` for the anchor.

### 2.5 The exact diff

**A. `resources/views/livewire/replacement-home.blade.php`**

1. `daysLeft(iso)` (`:497-502`) → delegate to `workingDaysDiff(iso)` (keep the name `daysLeft`; the
   HtmlBuilder option and both call sites keep working). Update the comment: working days, not calendar.
2. `urgencyClass(days)` (`:504-507`) →
   ```js
   function urgencyClass(days) {
       if (days <= 0) return 'urgency-high';   // overdue AND today
       if (days <= 7) return 'urgency-med';
       return 'urgency-low';
   }
   ```
   (`daysLeft` is signed; `0` = today.)
3. `.urgency-low` (`:60`) → `color: var(--color-success);` (keep `font-weight: 500; white-space: nowrap;`
   unless the weight looks wrong at green — do not change the other two classes).

**B. `public/js/ui-common.js`**

4. Add the four helpers from §2.4.
5. `daysLeftLabel(days)` (`:2434-2444`) → the new words, singular/plural:
   ```js
   if (days < 0) return 'Overdue (' + n + (n === 1 ? ' Working Day' : ' Working Days') + ' Ago)';
   if (days === 0) return 'Today';
   return days + (days === 1 ? ' Working Day Left' : ' Working Days Left');
   ```
   Keep the function name (two HtmlBuilder call sites use it: `:2708`, `:2734`) and update its doc comment.
   The old `1 day left` special case disappears — the plural rule covers it.

**C. Mobile card** — no edit: it already calls `opts.daysLeft` / `opts.urgencyClass` (`:771-772`) and
`daysLeftLabel`, so it inherits A1/A2/B5.

**D. Frozen templates** — no edit (`ui-design-templates/*` keeps its own copy).

Nothing else changes: sorting still sorts by date, handled rows still show `—`, the payload is untouched,
no PHP is touched.

---

## 3. Verification

### 3.1 Worked examples (use these as the test table)

Semester: starts **Mon 21 Sep 2026** (Week 1 Mon). Seeded holidays:
**Week 8 Mon = Mon 9 Nov 2026** (*Deepavali Holiday (In Lieu)*), **Week 14 Thu = Thu 24 Dec 2026**
(*Christmas Eve*), **Week 14 Fri = Fri 25 Dec 2026** (*Christmas Day*).

With **today = Sat 7 Nov 2026** (real clock on the day this brief was written):

| Class date | Working days | Class | Text |
| --- | --- | --- | --- |
| Sat 7 Nov (today) | 0 | high | `Today` |
| Fri 6 Nov (yesterday) | 0 → clamp 1 | high | `Overdue (1 Working Day Ago)` |
| Wed 4 Nov | 2 (Thu 5, Fri 6) | high | `Overdue (2 Working Days Ago)` |
| Mon 5 Oct | 24 | high | `Overdue (24 Working Days Ago)` |
| Mon 9 Nov (**holiday**) | 0 → clamp 1 | med | `1 Working Day Left` |
| Tue 10 Nov | 1 | med | `1 Working Day Left` |
| Wed 11 Nov | 2 | med | `2 Working Days Left` |
| Wed 18 Nov | 7 (boundary) | med | `7 Working Days Left` |
| Thu 19 Nov | 8 (boundary) | low | `8 Working Days Left` |
| Thu 24 Dec (**holiday**) | — | low | holiday contributes 0, the count still runs |

With the demo anchor **today = Mon 5 Oct 2026** (what the mock-era page used):

| Class date | Calendar (old) | Working (new) | Class old → new |
| --- | --- | --- | --- |
| Wed 14 Oct | 9 | 7 | mid → med (same colour, new number) |
| Thu 15 Oct | 10 | 8 | mid → low (**threshold flip**) |
| Fri 16 Oct | 11 | 9 | mid → low |
| Mon 12 Oct | 7 | 5 | high → med (**colour flip**) |
| Tue 13 Oct | 8 | 6 | mid → med |
| Fri 2 Oct | −3 | −1 | high → high, text changes |

The two "flip" rows are the ones a reviewer should eyeball: 7 working days must be **amber**,
8 must be **green**, and today/past must stay **red**.

### 3.2 Automated

- **New** `tests/replacement-home-working-days.spec.ts` (read-only, GET + DOM):
  - open `/my-replacements`;
  - in `page.evaluate`, stub `MockData.mockNow` (e.g. `new Date(2026, 10, 7)`) and
    `MockData.semester` / `MockData.holidays` with the four rows above, then assert
    `workingDaysDiff('2026-11-10') === 1`, `workingDaysDiff('2026-11-19') === 8`,
    `workingDaysDiff('2026-11-04') === -2`, and the label/class for each;
  - assert the padlock rows: 7 → `urgency-med`, 8 → `urgency-low`, 0 → `urgency-high`, −1 → `urgency-high`;
  - assert `daysLeftLabel(1) === '1 Working Day Left'` and `daysLeftLabel(-1) === 'Overdue (1 Working Day Ago)'`;
  - one end-to-end row assertion: after `MockData.mockNow = <a row's date + 3 working days>` and
    `renderTable()` (or the page's own re-render entry point), the row's Original Class cell shows
    `3 Working Days Left` with class `urgency-med`. Restore `mockNow` afterwards.
- **Must stay green, unmodified:** `tests/replacement-home-status-badges.spec.ts`
  (its regex `/days? left|Overdue|today/i` still matches "Working Days Left"), and
  `tests/request-completed-status.spec.ts:116` (completed row shows no urgency).
- `php artisan view:cache && php artisan view:clear` — the Blade still compiles.

### 3.3 Manual

1. `/my-replacements`: an overdue row is red and reads `Overdue (n Working Days Ago)`; a row 3 working
   days out is amber; a row two weeks out is green.
2. A row whose date is a **Saturday/Sunday** or a **holiday** never claims `0 Working Days…`.
3. Narrow the window to the mobile layout: the card footer shows the same words and colour.
4. Sort by Original Class still re-orders by date; handled rows still show `—`.
5. No console errors.

---

## 4. Section 2 — audit (report only, NO code)

Produce this table in your final report, **verified line-by-line**, and mark each row with a status:
`already compliant` / `needs decision` / `bug`. Add rows you find that are missing here.

| # | Business logic | Current rule | Suggested working-time rule | Affected page / file | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | My Replacements days-left / urgency | Calendar days (`Math.ceil`); ≤7 red, 8–30 amber, >30 grey | §2 of this brief: working days; today/past red, 1–7 amber, ≥8 green | `replacement-home.blade.php:58-60,441-442,497-507`; `ui-common.js:2434-2444` | **implementing now** |
| 2 | Approve Requests urgency badge | Calendar days against `MockData.urgencyReferenceDate`; `overdue` / `≤3 Urgent` (red) / `≥4 Normal` (green); label `Normal — N days left`; tip "N days left before the Proposed Class" | Count working days too; decide whether the 3-day → `Urgent` threshold survives or becomes 1–7 / ≥8 like row 1. The two pages would otherwise disagree on the same date | `request-approval.blade.php:431-486,567,732,751` | needs decision |
| 3 | Request-age pill (`3 days ago`) | Calendar days against the **real clock** (`new Date()`), not `getTodayMs()`; Today / Yesterday / N days ago; ≤1 green, 2–3 amber, 4+ red | Anchor on `getTodayMs()` (the page's own today) and count working days, so "3 days ago" skips the weekend | `ui-common.js:3127-3160`; used at `request-approval.blade.php:732`, `:907` | needs decision |
| 4 | Booking notice / lead time | **Already** 3 working days (Mon–Fri + semester holidays), client (`leadTimeCutoff`) and server (`WorkingHours::noticeCutoff`) mirrored | Keep. Only note that JS anchors on `MockData.mockNow` while the server uses `now()` | `ui-common.js:95-148`; `WorkingHours.php:67-109`; `SubmitReplacementRequest.php:99-105` | already compliant |
| 5 | Cancel-class window | **Already** 2 working days back, holiday-aware | Keep | `ui-common.js:4176-4242` | already compliant |
| 6 | Auto-reject pending requests | **Already** 3 working HOURS before the proposed start, 08:30–17:30 Mon–Fri, holidays skipped; sweep every 5 min | Keep | `WorkingHours.php:31-146`; `PendingRequestExpiry.php`; `AutoRejectRequest.php`; `routes/console.php:22` | already compliant |
| 7 | "Earliest Approval Time" tooltip | **Already** proposed start − 3 working hours | Keep | `ReplacementRequestMapper.php:481-507` | already compliant |
| 8 | Office hours vs grid window | Rule clock is 08:30–17:30, but every timetable grid draws **08:00–18:30** | Decide: the grid is a drawing window (leave it) or should reflect office hours (clamp). Do not change now | `WorkingHours.php:40-42`; `ui-common.js:756-761`; `VenueTimetable.php:447` | needs decision |
| 9 | Week numbering / semester weeks | Calendar weeks from `semesters.start_date` (`getWeekNumber`, `currentWeekIndex`, `generateWeekData`) | Not a working-time SLA — no change suggested | `ui-common.js:35-90`; `ResolvesTimetableTimeline.php` | already compliant |
| 10 | Holiday-date derivation (two copies) | `WorkingHours::holidays()` (PHP) and `ClassCancellation._holidayDateKeys()` (JS) + the new §2.4 helper would be a third copy of the *set* (same source data) | Keep one server list; in JS, have `ClassCancellation` call `workingDayHolidayKeys()` when we next touch it (optional cleanup, not now) | `WorkingHours.php:212-235`; `ui-common.js:4203-4219` | needs decision (cleanup) |
| 11 | Demo clock | `MockData.mockNow` pinned to Mon 5 Oct 2026 on mock pages, `null` (real clock) on live pages | Not a rule; leave until the go-live TODO | `mock-data.js:48-64`; `replacement-home.blade.php:1215` | informational |

For each `needs decision` row, state the **user-visible difference** in one sentence and give a
recommendation — the user will approve or reject row by row. Do **not** implement any of them.

---

## 5. Guardrails

- Files you may touch: `resources/views/livewire/replacement-home.blade.php`,
  `public/js/ui-common.js`, the new spec, and `page-changelogs/replacement-home-changelog.md`.
- Do **not** touch the frozen `ui-design-templates`, `theme.css`, `mock-data.js`, the PHP
  `WorkingHours` class, the Approve Requests page, or the cancel/lead-time logic.
- No new dependency, no new CSS token, no inline colour — `var(--color-success/warning/error)` only.
- No behaviour change for handled rows (`—`), sorting, filters or the summary counters.
- If the clamp rule from §2.4 produces a label you think is misleading in practice, **report it** — do not
  invent a new label.

---

## 6. Delivery

- Changelog entry in `page-changelogs/replacement-home-changelog.md`: the new counting law, the
  threshold table, the grey→green change, and the clamp edge case.
- Commit shape:
  `ui(replacement-home): count class-date urgency in working days`
- The Section 4 table goes in the **report**, not in the commit message.
- Never push.

---

## 7. Acceptance checklist

- [ ] Overdue rows: red, `Overdue (n Working Days Ago)`, n skips weekends/holidays.
- [ ] Today: red, `Today`, whatever the class time.
- [ ] 1–7 working days: amber, `n Working Days Left`.
- [ ] 8+ working days: **green**, `n Working Days Left`.
- [ ] 7 → amber and 8 → green (the two boundary rows in §3.1).
- [ ] A class on a seeded holiday (Mon 9 Nov 2026) is not counted as a working day.
- [ ] Mobile card matches the table row (same words, same colour).
- [ ] No `0 Working Days…` anywhere; today is the only `Today`.
- [ ] Existing replacement-home specs pass unchanged; no other page's urgency changed.
- [ ] Section 4 table delivered as a report; **no code** for rows 2–11.
- [ ] Nothing outside §5 in the diff; nothing pushed.
