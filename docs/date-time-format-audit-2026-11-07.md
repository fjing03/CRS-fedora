# Audit — date & time formats

**Date:** 2026-11-07
**Scope:** every user-visible date and time string in the TARUMT Class Replacement System —
table cells, modals, tooltips, toasts, cards, chips, notification rows and tips, emails.
**Status:** **reviewed and approved 2026-11-07** — everything agreed except #30, which is
deferred until the API is unparked. No code has been changed by this pass; §1's two column fixes
are already shipped.

**Method:** read the live render paths rather than the frozen mock templates — Blade views under
`resources/views/livewire`, the shared helpers in `public/js/ui-common.js`, the PHP formatters that
feed them (`app/Concerns`, `app/Services`, `app/Support`), and the three mail views. Line numbers are
against `main` as of `fe26b10`.

---

## Review outcome — 2026-11-07

The user reviewed every section and **approved all findings**, with these refinements:

- **§7 #19** — the grid event block switches from the hyphen to `~` (previously "acceptable as-is").
- **§9 #28** — approved. It is the same one-line helper change as #21, so the week dropdown, day
  headers, grid subtitle and mobile week chip unpad together.
- **§9 #30** — **dropped** for this pass. The API is commented out; align it with #27 in the commit
  that unparks the API.
- **§7 #20** — the 24-hour grid axis is kept; **§8 #25** — the notification tooltip keeps the short
  form (no year added).

No code changed yet. The next step is the implementation pass in the §10 order.

**Batch status:** batch 1 shipped in `10a51b0` — #1, #7, #13, #14, #17, #18. Batch 2 shipped in
`5120a98` — #2, #3, #4, #8, #9, #19, #23, #24, plus four joiner-drift spots the audit missed (the
arrangement picked-slot toolbar line, the slot-picker trigger/tip/row label, and the Cancel Class
success summary). Batch 3 shipped in `f420215` — #5, #6, #10, #11, #21, #27, #28, plus the coupled
lead-time note/holiday-skip unpadding. Batch 4 remains open (#22, #29); #30 stays deferred.

---

## 0. The reference standard (de facto, already the majority)

| Kind | Shape | Example |
|---|---|---|
| Date | `j M Y` — day without leading zero, short month, 4-digit year | `5 Nov 2026` |
| Day name — compact | `D` — table cells, cards, chips, picker labels, tooltip data lines | `Thu` |
| Day name — prose | full word — sentences, questions, toasts | `Thursday` |
| Date + weekday (compact) | `D, j M Y` | `Thu, 5 Nov 2026` |
| Date + weekday (prose) | `l, j M Y` | `Thursday, 5 Nov 2026` |
| Time | 12-hour, uppercase meridiem, no leading zero on the hour | `2:00 PM` |
| Time range | `2:00 PM ~ 4:00 PM` (`~`, one space each side) | `2:00 PM ~ 4:00 PM` |
| Date range | `5 Nov 2026 ~ 11 Nov 2026` | — |
| Date + time | `5 Nov 2026, 2:30 PM` | — |
| Relative (prose) | `3 days ago` | — |
| Relative (compact list) | `3d ago`, `2h ago`, `Yesterday` | — |

Three rulings that go with it:

1. **`~` is the range joiner.** A plain hyphen (`2:00 PM - 3:00 PM`) is also acceptable to the user,
   so existing hyphen ranges are not treated as broken — but a single element must not mix joiners.
2. **The en dash `–` is not a range joiner.** It already means "no data" / a parenthetical break in
   this UI (`B103 — Tutorial Room`, `—` for empty cells). Time ranges should not borrow it.
3. **Day words follow their container.** A compact list or data line says `Thu`; a sentence the user
   reads says `Thursday`. The split is about the shape of the line, not the widget:
   - **Compact (`Thu`)** — table cells, mobile cards, chips, slot-picker labels, the data half of a
     tooltip (`Week 3 · Thu, 5 Nov 2026`), notification tip lines.
   - **Prose (`Thursday`)** — toasts, questions and full sentences
     (`Arrange a replacement in B103 on Thursday, 5 Nov 2026 at 2:30 PM?`).
   - **Expanded detail-modal rows** already print the full word (`Day: Thursday`) on both pages and
     are treated as prose — consistent, no change proposed.

---

## 1. Already fixed (reference implementation)

| Page | Location | Before | Now |
|---|---|---|---|
| My Replacements | `ORIGINAL CLASS` cell — `replacement-home.blade.php:447` | `2:00 PM to 4:00 PM` | `2:00 PM ~ 4:00 PM` |
| My Replacements | `REQUESTED REPLACEMENT` cell — `replacement-home.blade.php:458` | `13:30 – 15:30` | `1:30 PM ~ 3:30 PM` |

Commit `024d0c6`. `DateHelper.format12hRange(t, sep)` gained an optional separator; the default stays
`' to '`, so every other caller is byte-identical.

---

## 2. My Replacements (`/my-replacements`) — same page, still inconsistent 
### User Comment : Agree ALL
| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 1 | Quick View → **Proposed Replacement Schedule → Time** (`replacement-home.blade.php:835`) | `13:30 ~ 15:30` (24-hour) | `1:30 PM ~ 3:30 PM` | **High** — most visible leftover |
| 2 | Cancel Request confirm → original / proposed line (`:921-927`) | `2:00 PM – 4:00 PM` (en dash) | `2:00 PM ~ 4:00 PM` | Medium |
| 3 | Mobile card footer → original class (`ui-common.js:2750`) | `2:00 PM – 4:00 PM` | `2:00 PM ~ 4:00 PM` | Medium |

Correct on this page, no action: Quick View → Original Class → Time (`:818`), `Requested At`
(`formatDateTime`), the two table cells from §1.

---

## 3. Replacement History (`/replacement-history`)
### User Comment : Agree ALL
| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 4 | Original Slot / New Slot cells and the detail modal (`replacement-history.blade.php:180`, `:358`) | `12:00 PM to 1:30 PM` | `12:00 PM ~ 1:30 PM` | **High** — same column type as §1, different joiner |
| 5 | Slot date, from `weekDays[…].days[…].date` (`ResolvesTimetableTimeline.php:109`, `d M Y`) | `05 Nov 2026` (padded) | `5 Nov 2026` | Low |
| 6 | Week filter labels (`ui-common.js:85`, `rangeShort`) | `05 Nov ~ 11 Nov` (padded) | `5 Nov ~ 11 Nov` | Low |

---

## 4. Replacement Arrangement (`/replacement-arrangement`)
### User Comment : Agree ALL
| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 7 | Selected-block card and event block (`replacement-arrangement.blade.php:1453`, `:1565`) | `2:00 PM – 4:00 PM` | `2:00 PM ~ 4:00 PM` | **High** |
| 8 | Duplicate-conflict error line (`:2217`) | `2:00 PM–4:00 PM` (no spaces) | `2:00 PM ~ 4:00 PM` | Medium |
| 9 | Booking toast (`:2261`) | `at 1:00 PM–2:00 PM` | `at 1:00 PM ~ 2:00 PM` | Medium |
| 10 | Auto-pick summary line (`:2171`) | `Thu, 05 Nov 2026 — 2:00 PM ~ 4:00 PM` (em dash before range) | `Thu, 5 Nov 2026, 2:00 PM ~ 4:00 PM` | Low |
| 11 | Conflicted-slot picker label + hover tip (`:3831`, `:3971`; date label from `LectureSessionCatalog.php:471`, `M j`) | `Monday, Nov 9` | `Mon, 9 Nov 2026` | Medium — month-first is unique to this surface; both are compact data lines, so `Mon` is correct under the day rule |
| 12 | Grid hour axis (`ui-common.js:856`) | `08:00`, `09:00` (24-hour) | keep — it is a scale, not prose (same call as §7 #20) | Decision |

---

## 5. Request Approval (`/request-approval`)
### User Comment : Agree ALL

| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 13 | Approve/Reject compare cards — Original (`ui-common.js:2552`) | `2:00 PM to 4:00 PM` | `2:00 PM ~ 4:00 PM` | **High** — the same page's detail modal already uses `~` |
| 14 | Approve/Reject compare cards — Replacement (`ui-common.js:2591`, `:2599`, `format12hRange` default) | `1:30 PM to 3:30 PM` | `1:30 PM ~ 3:30 PM` | **High** |
| 15 | Detail modal — Original / Proposed Time (`request-approval.blade.php:1095`, `:1114`) | `2:00 PM ~ 4:00 PM` ✅ | keep | — |
| 16 | `Requested At` / `Reviewed At` (`formatDateTime`) | `5 Nov 2026, 2:30 PM` ✅ | keep | — |

Note: the Urgency column changed date source on 2026-11-07 (`fe26b10`) but it displays only relative
day counts, not dates — out of scope for this audit.

---

## 6. Venue Timetable (`/venue-timetable`)
### User Comment : Agree ALL

| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 17 | "Arrange a replacement in …" click toast (`venue-timetable.blade.php:465`) | `on Thu, 05 Nov 2026 at 14:30?` | `on Thursday, 5 Nov 2026 at 2:30 PM?` | **High** — 24-hour **and** an abbreviated day inside a sentence |
| 18 | Booking-session modal subtitle (`:561`) | `Thu, 05 Nov 2026 · 14:30` | `Thu, 5 Nov 2026 · 2:30 PM` | **High** — compact data line, so `Thu` stays |

The grid's own event labels already use 12-hour (`:925-926`); only these two prose strings leak 24-hour.

---

## 7. Shared timetable grid (My Timetable / Student / Cohort / Venue)
### User Comment : Agree ALL; #19 use '~'

| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 19 | Event block (`ui-common.js:1012`) | `2:00 PM - 4:00 PM` | `2:00 PM ~ 4:00 PM` | **Approved** — use `~` (user, 2026-11-07) |
| 20 | Hour axis (`ui-common.js:856`) | `08:00` (24-hour) | keep — a scale, not prose | **Approved** — keep 24-hour |
| 21 | Day header date (`ui-common.js:2609` + `day.date`) | `Thu 05 Nov 2026` (padded) | `Thu 5 Nov 2026` | Low |
| 22 | Class quick-view modal (`ui-common.js:2653`, `requestCard`) | `Date: 5 Nov 2026 2:00 PM` (no comma) | `5 Nov 2026, 2:00 PM` | Low |
| 23 | Mobile history card (`ui-common.js:2664`, `myRequestCard`) | `2:00 PM – 4:00 PM` | `2:00 PM ~ 4:00 PM` | Medium |

---

## 8. Notifications (bell panel + tips)
### User Comment : Agree ALL

| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 24 | Notification tip schedule line (`NotificationTip.php:269`) | `2:00 PM–4:00 PM` (unspaced en dash) | `2:00 PM ~ 4:00 PM` | Medium |
| 25 | Row age (`nav-notifications.blade.php:26-31`), tooltip absolute (`:38-39`) | `5m ago` / `Yesterday`; tooltip `5 Nov, 2:30 PM` (no year) | compact list is fine; keep the short form | **Approved** — no year added |
| 26 | Tip prose (`nav-notifications.blade.php:47-55`) | `5 minutes ago`; `5 Nov 2026, 2:30 PM` ✅ | keep — different surface, deliberately wordier | — |

The compact and verbose relative formats are two vocabularies, but they never appear in the same
sentence; this looks intentional and is listed only so the decision is explicit.

---

## 9. Semester chip, server labels, emails
### User Comment : Agree #27,#29; #28 #30 not sure, need explain.

| # | Location | Current | Suggested | Priority / decision |
|---|---|---|---|---|
| 27 | Semester chip (`ResolvesTimetableTimeline.php:66`) | `21-Sep-2026 ~ 27-Dec-2026` | `21 Sep 2026 ~ 27 Dec 2026` | Medium — the only hyphenated/padded date on screen |
| 28 | Week dropdown (`ui-common.js:85`, `range` / `rangeShort`) | `05 Nov 2026 ~ 11 Nov 2026` (padded) | unpad to match §0 | **Approved** — same helper change as #21 |
| 29 | Submitted / decided email (`SubmitReplacementRequest.php:239-240`, mail views) | `13:30 – 15:30`, no date line | `Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM` | Medium — first thing a lecturer sees |
| 30 | `ApiReadController.php:83-96` chip text | `d-M-Y` | align with §27 when the API is unparked | **Deferred** — API commented out; do it in the unpark commit |

---

## 10. Rollout order (approved 2026-11-07)

1. **Same-screen contradictions** — #1 (Quick View 24-hour), #13/#14 (approve cards), #7 (arrangement
   blocks), #17/#18 (venue 24-hour). These are the ones a user can point at as "wrong" without any
   design discussion.
2. **Joiner drift** — #2, #3, #4, #8, #9, #23, #24. Mechanical, one-line each.
3. **Padded / month-first dates** — #5, #6, #10, #11, #21, #27, #28. A single shared decision
   (`j M Y` everywhere) covers all of them.
4. **Out-of-page surfaces** — #22, #29, #30. Emails need their own wording pass.

Everything here is a display-layer change. No schema, no stored value, no calculation is touched.

---

## 11. Already consistent — no action

Day words were audited alongside the dates; these already obey §0 ruling 3:

| Surface | Current | Verdict |
|---|---|---|
| Table cells, mobile cards, day headers (`formatClassBlock`, `requestCell`, `dayHeader`, `classBlock`) | `Thu, 5 Nov 2026` | compact → `Thu` ✅ |
| Detail modal `Day` rows (`replacement-home.blade.php:816`, `:833`; `request-approval.blade.php:1093`, `:1112`) | `Thursday` | expanded row, treated as prose ✅ |
| Notification tip day line (`NotificationTip.php:262-263`) | `Week 3 · Thu, 5 Nov 2026` | compact data line ✅ |
| "Earliest bookable" tooltip (`replacement-arrangement.blade.php:4451`) | `Thursday, 5 Nov 2026 (Week 3)` | prose sentence ✅ |
| Venue booking subtitle (`venue-timetable.blade.php:561`) | `Thu, 05 Nov 2026` | compact ✅ (only the time/year need the fix in #18) |

The only day-word defect found is **#17**, where an abbreviation sits inside a sentence.

---

## Appendix — decisions

- [x] **Settled 2026-11-07:** day words follow their container — `Thu` in compact lists/data
      lines, `Thursday` in prose (§0 ruling 3).
- [x] **Settled 2026-11-07:** `~` is the range joiner for new work; the hyphen stays acceptable
      where already used, but no element mixes the two (#19 approves the grid event block's move
      to `~`).
- [x] **Settled 2026-11-07:** the grid hour axis keeps 24-hour time (§7 #20).
- [x] **Settled 2026-11-07:** notification tooltips keep the short form, no year (§8 #25).
- [x] **Settled 2026-11-07:** emails adopt the 12-hour time + full date standard (#29).
- [ ] **Deferred:** #30 (API chip text) — revisit in the commit that unparks `/api/v1`.
