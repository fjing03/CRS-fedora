# Explore brief — student-modal-staff-parity

**Date:** 2026-11-11 · **Mode:** explore only (no code, no tests) · **Page:** `/student-class-replacements`
**Reference:** the staff pop-up `#quickViewModal` ("Replacement Details") on `/my-replacements`

## The user's ask

> "The Student-side modal should follow the same design and layout as the equivalent Staff-side modal."
> Four required checks: footer (Close left / Go to My Timetable right, primary), remove the Conflict
> Reason tab, subtitle carries the course information in the staff format, plus "identify any other
> inconsistencies".

Every decision below was answered by the user in conversation on 2026-11-11.

## What was measured (demo data, read-only)

Student `24RSD0012`, row **BMIT2013 · Web-Based Integrated Systems**, occurrence `24:7`:

| | Staff pop-up | Student pop-up (today) |
|---|---|---|
| Title | `Replacement Details` | `BMIT2013 — Class Replacement` |
| Subtitle | `BMSE2163 — Software Engineering (Practical)` | `Web-Based Integrated Systems` |
| Strip above tabs | `Cohorts` · `Students` · `Conflict` | — none — |
| Tabs | Requested → Original → `Request Status (Rejected)` | `Course` → Original → **`Conflict Reason`** → Requested → `Request Status` |
| Opens on | Requested (when it exists) | `Course` |
| Original rows | Class ID · Week · Day & Date (countdown in brackets) · Time & Duration · Venue | Week · Day · Date · Countdown · Time · Duration · Venue |
| Footer | Close (outline) LEFT · Cancel/Arrange/Go RIGHT (`btn-action`) | Go to My Timetable (outline) LEFT · Close RIGHT |

The footer flips because the student page **injects** the jump button with
`insertAdjacentHTML('afterbegin', …)`, i.e. before Close, inside a `space-between` footer; on a row
with no request the button is not injected, so Close sits LEFT instead. Measured both states.

## Decided (the frozen decision record)

| # | Decision | The user's words |
|---|---|---|
| 1 | Title `Replacement Details`; subtitle `CODE — Name (Type)` | "A" (copy staff) |
| 2 | The `Course` tab is deleted; **Lecturer, Cohorts, Students move into a new `Class Info` tab** | "place Lecturer, Cohorts, Students into CLASS INFO modal tab" (changed mind from "lecturer in the subtitle") |
| 3 | A facts strip above the tabs holding **only the Conflict badge, printed in full** | "copy it exactly (a strip with just the conflict)" |
| 4 | Tabs open on **Requested Slot Schedule** when the row has one, else on **Class Info** | "B" |
| 5 | `Go to My Timetable`: static button on the **right**, primary blue, shown only for **approved/completed** | "A" |
| 6 | Fold in checklist item **B9** (status tab label carries the short status) | "A" |
| 7 | Fold in checklist items **B5/B6** (ID rows use the staff names: `Request ID`, `Class ID`) | "A" |
| 8 | The Status tab keeps today's content (badge + note + requested time); the "why was it rejected" line is **parked** | earlier ruling, restated |

Resulting shape (BMIT2013 example):

```
Replacement Details                                        ✕

BMIT2013 — Web-Based Integrated Systems (Lecture)

Conflict  Public Holiday — Deepavali Holiday (In Lieu)

[ Requested Slot Schedule ] [ Original Slot Schedule ] [ Class Info ] [ Request Status (Approved) ]

Footer:  [ Close ]                                [ Go to My Timetable ]
```

## Rejected approaches — and why

| Rejected | Why |
|---|---|
| Keep `Conflict Reason` as a tab | The user's explicit ask; staff keeps the conflict in the strip |
| Lecturer on a second subtitle line | The user changed their mind: it belongs in the `Class Info` tab |
| Strip holding Cohorts + Students + Conflict (staff's full strip) | Those three belong in the `Class Info` tab; only the conflict stays in the strip |
| Conflict folded into the `Class Info` tab | The user preferred staff's shape (a strip) once they saw it |
| Open on `Class Info` | "A student clicks that row to find out when and where the class moved" — staff's rule |
| Keep the jump visible for pending rows | A pending replacement is not in the timetable yet → a click that lands on nothing |
| Settle checklist **B8** (facts in two shapes) and **B10** (a holiday stated twice) in this lane | Both need their own ruling; would stall the pop-up the user already approved |

## Data flow — what the pop-up needs vs what the student page receives

`ReplacementHistory::occurrenceRow()` builds each row. Present today: `id`, `week`, `requestId`,
`code`, `name`, `type`, `lecturer`, `orig{week,date,day,start,end,duration,venue}`, `req{...}`,
`status`, `statusLabel`, `statusTip`, `conflictKind`, `holidayName`, `requestedAt`.

**Missing for the agreed design:**

| Needed | Source | Note |
|---|---|---|
| the class's cohorts | `$session->cohorts` (already loaded; staff prints `spacedCohortLabel`) | new row key |
| the class's student count | `$session->cohorts->sum('student_count')` (staff uses exactly this) | new row key |
| the short status for the tab label (B9) | `$vocabulary['short']` — already computed in the same method, just not exported | new row key |

Nothing else is missing: the conflict's holiday name, the request id, the session id and both slots
are all present.

## Known open items (deliberately parked, NOT in this lane)

1. **The cancellation reason never reaches a student** (their ruling earlier today). So a cancelled
   row's strip reads `Class Cancelled` with no detail, while staff read `Class Cancelled — Annual Leave`.
2. **The Status tab has no "why" line** — no rejection reason, no remark, no status sentence. Adding
   them needs the same payload change as item 1; parked as its own slice ("Slice B").
3. **Checklist B8** (facts shown as a strip on two modals and as rows on two others) — the student
   pop-up will now have a strip *and* a Class Info tab, which is the same shape-difference argument.
   Needs its own decision.
4. **Checklist B10** (a holiday stated several times) — the student pop-up will state it in the strip;
   if the Status tab ever gains a description, that becomes a second statement.
5. **Checklist B2 / B11 / B13** are different pop-ups (Request Details; student My Timetable; the
   Approve/Reject modals) and stay out of this lane.

## Files the lane will touch (for the design stage)

| File | Why |
|---|---|
| `resources/views/livewire/replacement-history.blade.php` | `openReplacementModal()` — title, subtitle, strip, tab set, slot rows, footer |
| `app/Livewire/ReplacementHistory.php` | `occurrenceRow()` — the three new payload keys |
| `page-changelogs/replacement-history-changelog.md` | the record |

No `theme.css`, no `ui-common.js`, no staff-side file: the shared `DetailModal.render()` already
supports `subtitle`, `factsHtml` and tabs, and `.btn-action` / `.btn-outline` already exist.
