# Proposal — rearrangement-toolbar-tooltips (LEAN PATH)

> **Process waiver (user, 2026-10-31):** the user judged this a small
> change and approved the **lean path** — ONE proposal.md, no
> design/specs/tasks batches, no `@sdd-reviewer` rounds (precedent:
> `notif-ghost-retract-strip`'s logged SDD waiver). Source of truth:
> `prompts/pi-prompt-UIUX Update Replacement Arrangement Toolbar.md`
> (the user's ask + today's Q&A rulings).

## Problem

On `/replacement-arrangement`'s `.toolbar.toolbar-restructure`, the three
main controls explain themselves only visually (or not at all): the
conflicted-slot picker trigger, the week select (the shared partial's
`selectTip` is NOT passed by this page), and the subject select (no tip).
The user wants three hover tooltips, all DYNAMIC, reusing the existing
tooltip design.

## Scope (verbatim from the prompt file, rulings included)

All three are `data-tip` attributes — **visible texts unchanged** (user
ruling 2026-10-31):

| # | Element | `data-tip` (exact) |
|---|---|---|
| a | `#slotTriggerText` (slot-dd trigger, ~:1014) | `Conflicted SLOT Picker : ({TYPE}) Week {N}, {Day}, {Date}, {Time Range} @{Venue}` — e.g. `Conflicted SLOT Picker : (L) Week 14, Thursday, 24 Dec 2026, 9:00 AM - 11:00 AM @ B100` |
| b | `#weekSelector` (via `ui-week-nav`'s EXISTING `selectTip` param) | `Select the WEEK that you want to arrange the replacement in` (user's wording — static) |
| c | `#subjectSelector` (~:1007) | `Conflicted SUBJECT Selector : {Code} - {Name}` — e.g. `Conflicted SUBJECT Selector : AMSE1003 - Software Engineering` |

Formats (from the prompt file): time `9:00 AM - 11:00 AM` (spaces around
the hyphen), date `24 Dec 2026`, venue CODE only (`@ B100`), normalized
`Thursday, 24 Dec 2026` (the prompt example's stray space is a typo).

## The 4 hidden decisions (recorded in lieu of design/specs/tasks)

1. **Empty states → FAIL-OPEN (no `data-tip`).** No conflicted slot
   selected (trigger reads "Select a slot to replace", `#slotPicker`
   starts `display:none`) or subject = "" → the attribute is OMITTED, never
   a half-built or stale tip. Same fail-open law as the frozen
   `status-tooltips-filter-chips` maps.
2. **Dynamic refresh = single-source assembly.** The tip is assembled from
   the SAME variables, at the SAME code sites, that write the visible
   texts (this is the proven pattern from that change):
   - a): every site that writes `#slotTriggerText.textContent`
     (re-grep at apply — ~:3414, ~:3677 reset, ~:3578, ~:3843) sets or
     clears the tip right there.
   - c): every `onSubjectChange()`/populate path (re-grep at apply —
     ~:1912, ~:2386, ~:2807, ~:2925, ~:3996) sets/clears from the
     selected option's code + name.
   - b): ONE static `selectTip` passed from the `@include` at ~:1038 —
     no JS. `prevTip`/`nextTip` untouched; other pages byte-identical
     (the param defaults to null).
   Two tiny page-local helpers (`slotTipAttr`, `subjectTipAttr`) keep the
   attribute strings in one place per element — no new shared code.
3. **Type letter = the slot's REAL type field, reported honestly.** This
   sits next to the deferred Bug-2 `(P)→(L)` strip lore: build from the
   data as-is, do NOT force a letter, and report to the user what the
   demo data actually yields (if it yields `P` where `L` is expected,
   that's a data finding for them — out of scope to patch).
4. **Reuse only.** Shared `data-tip` tooltip mechanism (above-positioned,
   `\n` → multi-line) — zero new tooltip CSS/JS, no new deps, no
   PHP/DB/route changes. The subject/slot tooltips are single-line, so
   `\n` isn't needed for a)/c).

## Out of scope

- The visible trigger/labels, the slot panel contents, week-nav arrows,
  booking logic, `.slot-dd-tooltip` (a separate existing element).
- The UNCOMMITTED `submission-conflict-refresh` hunk in the same blade —
  do not touch or stage it.
- Reviving deferred Bug 2 (type-letter correctness beyond honest render).
- Any other page passing `selectTip`.

## Success criteria (checked AFTER your apply approval)

- Hovering each control shows exactly the tip table above with LIVE
  values; switching subject/slot updates the tip (and clears it on
  empty); visible texts byte-identical; week nav + booking flow untouched.
- Playwright receipt (`tests/rearrangement-toolbar-tooltips.spec.ts`)
  green; `node --check` on any touched JS + pint/phpstan house gates;
  `page-changelogs/replacement-arrangement-changelog.md` updated; ONE
  conventional commit; **NOT pushed**.

## Status

FROZEN by lean path — awaiting the user's explicit apply command.

---

## APPLIED — 2026-10-31 (receipt green)

- b) `selectTip` passed at the `@include` (:1038); arrows untouched.
- a) `applySlotTip(slot)` at ALL five trigger writers (arrival anchor :2661,
  auto-pin :3445, picker reset :3620, revert-fallback :3708, pick :3896);
  exact tip format per the prompt file; fail-open on the placeholder.
- c) `applySubjectTip(sel)` bound at boot + `onSubjectChange` + the
  confirm-cancel revert + the dropdown rebuild; plain-hyphen tip format
  re-assembled from the em-dash option text; fail-open clears on empty.
- **Same-day user report + fix:** the boot-tail anchor restore was CLOBBERING
  the auto-pin's tip (the virtual anchor carried no type) — fixed honestly by
  shipping the REAL `session_type` (display-only) in `pinnedSessionPayload`
  (the apply's ONE PHP line) + the anchor carrying type/day. Arrival now tips
  the full slot.
- One superseded behaviour (flagged): the old `syncSubjectTip` empty-state
  hint ("Select a subject first to trigger the timeslots selector") is gone
  by the fail-open law.
- Gates: pint ✓ · phpstan(1G) 0 ✓ · page-script node --check ✓
- Receipt: `tests/rearrangement-toolbar-tooltips.spec.ts` 3/3 GREEN
  (+ clear-guards-cross-week regression green — one rerun after a 429
  rate-limit chatter, not a code issue).
