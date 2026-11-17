# Amendment 1 — the Print modal (2026-11-16)

> **Status:** amendment to a frozen, applied and verified lane. `proposal.md`,
> `design.md`, `specs/` and `tasks.md` stay frozen; this file carries the delta.
> Nothing here touches the twelve receipts already earned — it re-points the ones
> that describe the entry point.

## 1. What the user asked for (2026-11-16, verbatim intent)

Clicking **Print** opens a dialog with:

1. **Show Normal Class** — the switch moves *into* the dialog. It is only relevant to
   printing, so it must not sit in the page chrome any more.
2. **Week selection** — `All Weeks (Whole Semester)`, or `Week 1` … `Week 14`.
   All Weeks prints the whole semester; a week prints that week only.
3. Consistent with the existing CRS UI, and the printing behaviour we already have is
   preserved.

And the follow-up ruling: **"B&C, but disable the switch only."** — All Weeks has one
fixed meaning (B: the switch applies to a single week; C: All Weeks always prints the
normal view), and where it does not apply the switch is **disabled, not hidden**.

## 2. Decisions

### A1 — The switch leaves the chrome

`partials/ui-week-nav.blade.php` loses `$showNormalToggle` and the
`.print-normal-toggle` markup entirely; the four pages stop passing the flag. The
`@media (max-width: 767px) { .print-normal-toggle { display: none } }` rule in
`theme.css` is **deleted**: the reason it existed (a sixth control in the phone week
row, where task 8's `T16d #3` measures 143px of box for a 144px label) is gone with the
control. **The phone limitation this lane recorded is withdrawn, not worked around** —
the switch is reachable at every width, in the dialog.

### A2 — One shared dialog, the existing vocabulary

New `resources/views/partials/ui-print-modal.blade.php`, included by the four pages
beside `ui-print-sheet`. It uses `.modal-overlay` / `.modal` / `.modal-header` /
`.modal-title` / `.modal-body` / `.modal-footer` — the same families the details
pop-ups use, so the "modal consistency" law covers it by construction — and paints only
with `theme.css` tokens. No new component family, no new dependency.

**The open dialog must not print, and this is not optional.** Every print now happens
*with the dialog open* (its own `Print` button is the trigger), and the print
chrome-hide block (`theme.css:7450-7486`) hides `.top-bar`, `.semester-bar`,
`.print-btn` … but not `.modal-overlay`/`.modal`. Without a rule the overlay paints
over the paper. So the dialog's overlay element carries **both** `.modal-overlay` (for
the house look) **and** `.print-modal-overlay`, and
`.print-modal-overlay { display: none !important }` joins that print block; a receipt
proves it (T31). **T30 owns this rule.**

### A3 — Contents and furniture

- Title `Print timetable`, a subtitle naming the sheet (`Weekly class schedule`,
  `Venue occupancy`, … the page's own `sub`).
- Field **Week**: the select, options built at open time (§A4).
- Field **Show normal class only**: the switch, with the explanation that All Weeks
  always prints normal classes (§A5).
- Footer: `Cancel` and `Print`. `Esc`, a backdrop click and `Cancel` close it; only
  `Print` prints.

### A4 — The week list is built from the page's own data

`PrintSheet` builds the options from a `weeks()` callback each page passes to
`init()` (`{value, label}` list), so no page hand-writes fourteen weeks and the labels
carry that page's own date ranges. Option `all` first (`All Weeks (Whole Semester)`),
then `1`…`semester.weeks`. A page whose data is not loaded yet passes an empty list and
the select shows only `All Weeks`.

### A5 — What each combination prints (the ruling)

| Week | Show normal class | What prints |
|---|---|---|
| All Weeks | **disabled, shown ON** | the deduplicated semester union of **normal** classes (today's switch-on paper) |
| a single week | off | that week exactly as the screen shows it (today's Print) |
| a single week | on | that week's normal classes only |

The switch is **disabled and checked** for All Weeks, because that is what the paper
is: a state the reader can see, not a hidden override. Its label says so
(`All weeks prints normal classes only`).

Today's behaviour is preserved on all four pages, but not all in the same row: My,
Student and Cohort keep rows 1 and 2 (today's switch-on paper and today's Print), while
the **Venue** page's switch-on paper is today's *current week filtered to normal* — that
is **row 3** (single week + switch on), and A6's All Weeks is the one deliberate
behaviour change in this batch.

### A6 — Venue's All Weeks is the semester union too

Frozen **PR-16 already requires "a deduplicated union of all weeks" on the sheet**, so
the applied venue code is what deviated from the spec; A6 resolves a spec-vs-design
contradiction rather than creating one. `design.md` §3.3 gave the venue page no semester union ("one teaching week IS the
semester's pattern") because its normal view printed *the current week* filtered. A
dropdown labelled `All Weeks` cannot print one week: that would be a lie in the
heading. So the venue joins the other three — `printSemesterSet()` over all weeks,
deduplicated by `sessionId|di|start|end|code`, normal only. A room's whole-semester
occupancy pattern is exactly what a room sheet is for, and a class that meets on
alternate weeks prints once, which is correct.

### A7 — The screen never moves

Choosing Week 3 while the page shows Week 9 prints Week 3 and changes **nothing** on
screen: not `#timetable`, not the week select, not the arrows, not the URL, not the
summary cards. Re-proved byte-identical, the same way Batch D proved the toggle.

### A8 — Nothing prints until Print is pressed

The header button becomes an opener: `window.print()` is called from exactly one place,
the dialog's `Print` button. That keeps one code path for the paper and makes the
existing "one print per click" receipt a two-step assertion rather than a new contract.

### A9 — The paper's heading follows the choice

All Weeks → `Week 1 ~ 14` + `(Whole Semester)`, no day dates, the key hidden (as
today). A single week → `Week N of 14` + ` · <range>`, the key shown, the holiday tint
kept (that day is a holiday whatever the switch says).

The empty panel keeps **PR-11's two titles verbatim** (`No classes this week.` /
`No normal classes.`) — no spec edit — and only its supporting sentence names the
choice (`All 14 weeks carry no normal class.` / `Week 3 carries no classes.`), so the
assertion T31 writes is on that sentence, never on the pinned titles.

### A10 — The dialog remembers for the life of the page

Last choice and switch state persist while the page is open (as the switch does today),
and a reload returns to `(the week on screen, switch off)`. No storage, no cookie, no
server round trip.

### A11 — The *funnel* rebuilds the paper, not each exit

Two exits in `buildTimetable()` already rebuild the sheet (`cohort-timetable.blade.php`
`:601-606` the no-cohort return, `:609-622` the empty week — the frozen design's
`:524`/`:534` are pre-apply numbers). But **two user-reachable paths never get there**:
`onFacultyChange`'s no-taught-cohort branch (`:343`, `showGuidance()`) and
`onCohortChange`'s clear branch (`:357-359`, `showGuidance(); return;`), and
`showGuidance()` (`:474-489`) clears only the screen grid (`tableHead`/`tableBody`) —
never `#printTimetable`. So a cohort shown, then cleared, leaves that cohort's paper
printable: exactly the "sheet that lies" failure this amendment must prevent.

The fix is the funnel, not the exits: **`showGuidance()` itself calls
`buildPrintSheet()`**, which covers all four paths at once (the two early returns keep
their own calls, which are then harmless repeats).

The `Print` button is **disabled when the page has nothing to print** — on the Cohort
page that means *no cohort selected* (`selectedCohortId === null`), not an empty week
list: `weekData` always holds 14 entries (`:254`), so "the list is empty" is dead
reasoning.

### A12 — Touch and keyboard

The select and the switch are real form controls with labels; `Esc` closes; focus lands
on the select when the dialog opens and returns to the header button when it closes.

The switch's ~90 lines of base CSS (`theme.css:7108-7198`, including the 48×48
coarse-pointer rule at `:7186-7188`) are **retargeted, not deleted**: the component is
renamed `.print-modal__switch` inside the dialog and keeps its hit box, its focus ring
and its disabled look. Only the four wrapper rules that placed it in the week row are
removed. **T30 owns the retarget.**

## 3. Spec delta (against `specs/timetable-print/spec.md`)

| PR | Change |
|---|---|
| **PR-01** | **Rewritten.** The header button *opens the dialog*; `window.print()` runs from the dialog's `Print` only — one print per confirm. (Its label stops promising `Print this week`, because the dialog now chooses the weeks: `Print timetable`.) |
| **PR-02** | **Rewritten.** The switch's home is the dialog; the phone-scope narrowing (stand down below 768) is **withdrawn**; All Weeks disables the switch, which reads ON. |
| **PR-04** | **Declarative.** "the two hidden print surfaces" (`spec.md:71`) becomes **three** — the dialog is a hidden print-adjacent surface too. |
| **PR-11** | **Unchanged** — its two titles stay verbatim; only the supporting sentence gains the week (A9). |
| **PR-18** | **Unchanged** — page breaks (repeating `thead`, unsplit rows, no split key, no summary strip) are untouched by this amendment. |
| **PR-21** | **New.** The dialog: title, subtitle, week select (`All Weeks` + each week with its dates), the switch and its disabled rule, `Cancel`/`Print`, `Esc`/backdrop, focus behaviour, remembrance, the venue's union, and **the rule that an open dialog never prints**. |
| PR-06/07/09/12/13/14/19 | Unchanged — the paper's own shape, palette and dependency rules are untouched. |

### Frozen clauses this amendment supersedes (declarative pointers)

| Where | Clause | Now |
|---|---|---|
| `spec.md` PR-01 | "the printer button … calls `window.print()`" | opens the dialog (PR-01 rewritten) |
| `spec.md` PR-04 | "the two hidden print surfaces" | three: header, sheet, dialog |
| `proposal.md` §"four screen changes" #2 | "the Normal Class toggle appears on the page" | it appears in the dialog |
| `proposal.md` AC14 | the four allowed changes | the same four — the switch is re-homed into the dialog, and the dialog folds into #1 (the button's behaviour) and #4 (the hidden print surfaces, now three) |
| `design.md` §8.1 | "the action becomes `window.print()`" | the action opens the dialog; `window.print()` runs on confirm |
| `design.md` §8.2 | "Home: inside the shared `partials/ui-week-nav.blade.php`" | Home: `partials/ui-print-modal.blade.php` |
| `design.md` §3.3 | "Venue has no semester union — stated here so nobody invents a union for Venue" | **superseded by A6** (and by frozen PR-16, which already demanded the union) |
| `venue-timetable.blade.php:1638-1640` | the code comment repeating §3.3 | rewritten by T29 |

## 4. What the receipts must give back

| Receipt | Impact |
|---|---|
| `timetable-print.spec.ts` (12) | **Exactly three** cases touch the entry point, not five: case 1's exact `aria-label`/`data-tip` = `Print this week` (`:82-83`), the PR-02/PR-16 toggle case, and the 390/768/1280 boundary case (which is **reversed** — the switch is now reachable at 390 *in the dialog*). The other nine build the sheet directly and never click. **+4 new cases**: the dialog opens with the screen's week selected; a week other than the screen's prints that week and leaves `#timetable` byte-identical; All Weeks disables and checks the switch; an open dialog does not print. |
| **`venue-timetable.spec.ts` TC05 (`:74`, aria at `:89`, click at `:91`)** | **The one receipt that pins the button** — it stubs `window.print`, clicks `.print-btn` and counts `__printCalls`, and asserts `aria-label = 'Print this week'`. Under A8 the click opens the dialog and nothing prints, so this **breaks**; it is re-pointed to "the click opens the dialog, the dialog's Print is the call" (and a real print is still counted exactly once). |
| `timetable-callers-pin.spec.ts` (6) | The paper is byte-identical for the same choice, so the pin must still pass; the *trigger* is now two clicks. Any step that assumed one click is re-pointed (declarative, no capture change). |
| `timetable-responsive.spec.ts` (36) | **`T16a` item 6 does NOT assert the button prints** — it asserts print-media *visibility* of the paper surfaces (`:563-608`) and never clicks. It is expected to stay green; if it moves at all it is because the switch left the row, not because of the dialog. Re-run, expect 36/36. The tuned phone week row only gains room. |
| `pages-parity`, other `venue-*` | Untouched by design; re-run, expect identical. |
| PDFs | Re-exported: Week 9 (as today), Week 3 chosen from a page showing Week 9, All Weeks, and the dialog itself at 390 and 1280. |

## 5. Tasks

| Task | Work |
|---|---|
| **T27** | Remove the switch from `ui-week-nav` and the four page flags; delete the ≤768 rule; add `partials/ui-print-modal.blade.php`; include it on the four pages |
| **T28** | `PrintSheet`: `open()`/`close()`, the week list from `weeks()`, state `{week, normal}`, `rebuild()` fed by state, the disabled rule, focus handling, `Esc`/backdrop, remembrance |
| **T29** | The four `build(normal)` → `build({week, normal})`, each deriving from the chosen week's own events/days; venue's All Weeks becomes `printSemesterSet` and its §3.3 comment is rewritten; **cohort's `showGuidance()` rebuilds the paper** (A11) |
| **T30** | CSS: the dialog's two fields, the retargeted switch (keeping its 48×48 hit box, focus ring and disabled look), the **`.print-modal-overlay` rule in the `@media print` block**, tokens only, both themes; the paper needs no change |
| **T31** | The spec work in §4 — **rewrite 3 (timetable-print), add 4, re-point 2 (TC05 + the pin's trigger)**, never T16a-6 |
| **T32** | The five PDFs / two dialog screenshots in §4 |
| **T33** | Four changelogs, apply log, verify-report delta, `sdd.yaml` |

**Effort:** ~6 hours, one session. **Risk:** low — the paper is untouched; every
change is in the entry point and the derivation's input.

## 6. What is NOT in scope

- No change to the paper's layout, palette, key, dense rule or fit pass.
- No change to what counts as printable (pending/cancelled still never print).
- No new week data, no server change, no route change, no Livewire property.
- No archiving of the lane until this amendment is applied and verified.
