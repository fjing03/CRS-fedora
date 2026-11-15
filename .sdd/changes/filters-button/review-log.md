# Review log — `filters-button`

Append-only. One entry per reviewer round.

---

## proposal Round 1 — 2026-11-14 (night)

Artifacts under review: `proposal.md` (nothing frozen). Baseline: `explore-brief.md`, the source brief
`prompts/pi-prompt-filters-button.md`, the three blades, `ui-common.js`, `theme.css`.

**Verdict: not ready — three blockers, all inside the proposal text.**

### 🔴 Fixed
- **§5.5 misstated Request Approval's Reset.** It said "everything off"; the code (`:1256–1263`)
  restores the page **defaults** — status `Pending`, exclusion **on**, rows-per-page 10. "Everything
  off" is a different function (`clearAllFilters()`) behind the chips' Clear-all (the 2026-10-30
  ruling). Corrected, and the student page's missing Reset is specified as page defaults too.
- **M15 was unaddressed.** My Replacements hides `#resultCount` below 1500px (`:106–110`, "the
  pagination bar still reports it"). A count pill in the chips row would inherit that hide, which
  contradicted the test plan's "the pill matches". Now a named decision (1b) with three options, and
  the test step is conditioned on the answer.
- **A spec this lane breaks on purpose was not named.** `tests/status-tooltips-filter-chips.spec.ts`
  pins `Active Filters:` and `Exclude COMPLETED Status`; the lane changes both. Named as amended in
  the same commit.

### 🟡 Addressed
- `partials/ui-week-nav.blade.php` is shared by five other pages: the lane wraps it per page and does
  not edit it; the arrows travel with the moved toolbar; their panel colour is a scoped rule.
- The aria claim was backwards: `aria-label` **beats** `<label>`, so controls gaining a visible label
  lose their `aria-label` (otherwise the old string is what a screen reader reads).
- One overlay at a time now covers the **summary sheet** as well as the bell (all three pages include
  `ui-summary-bar`, whose sheet is also z 1200).
- The "payload already has both weeks" claim was fiction (`originalWeek`/`requestedWeek` do not exist);
  the requested week is derived from `replacementDate`.
- "3 small markup edits per page" undercounted; now six touch points on the two staff pages.

### 🔴 Outstanding
- none from this round.

---

## proposal Round 2 — 2026-11-14 (night) — lane STOPPED here by the user

**Verdict: not ready — one new blocker, introduced by the round-1 fixes. Not frozen.**

### 🔴 Outstanding (fix before design)
1. **§5.6 "no spec pins the option text" is false.** The `All Weeks` rename breaks assertions in
   **three** files that §6 does not amend:
   - `tests/status-tooltips-filter-chips.spec.ts:380` — `'All Requested Slot Weeks'`
   - `tests/week-filter-preserved.spec.ts:141` — `'All Original Slot Weeks'`
   - `tests/Feature/StudentReplacementHistoryParityTest.php:143` — the `allLabel` source string
   Worse, `week-filter-preserved.spec.ts:140` records a **2026-11-10 user ruling** that re-worded the
   option to "SLOT". This lane therefore quietly reverses a week-old ruling, so it must be surfaced as
   a decision (keep "SLOT" or go to "All Weeks"), not called spec-safe.

### 🟡 Outstanding (small)
2. §3 still says "each row has the original occurrence `week` plus a `replacementDate`". Request
   Approval rows carry **no `week` key at all** (`:983` says so); the original slot is a **date** and the
   week is derived. Core conclusion (no requested-week field; derive from `replacementDate`) is right.
3. §4 counts "five" touch points on the student page; it is **three** (its toolbar has only the week
   include and the toggle — no search, status or count).

### ✅ Confirmed resolved from round 1
Reset semantics, M15 decision, the named spec amendment, the week-nav treatment, the aria precedence,
the overlay-coexistence rule, and the payload correction — each verified against the code.

---

## Lane state at stop — 2026-11-14

- `explore-brief.md` and `proposal.md` exist; **proposal is NOT frozen** (round 2 failed).
- No `design.md`, no specs, no `tasks.md`, **no code**. The source brief asked for explore + propose
  only, so the stop is inside the brief — but it is *before* a freeze, not after.
- Carried to 2026-11-15 with the four user decisions still open (student Reset, M15 count pill, the
  "All Weeks" wording vs the 2026-11-10 ruling, and the second-week dropdown staying out).

---

## Round 2 blockers resolved by user decision — 2026-11-14 (night)

The two items this lane could not settle alone were handed to another chat and came back as
"follow the prototype":

1. **Count pill (M15) — retire the hide rule.** `#resultCount` becomes a chips-row pill visible at every
   width; on screens wider than 1500px the pagination bar repeats the same number, and that duplicate is
   accepted. §2.9 and §6 step 4 no longer carry an open question.
2. **Week wording — "All Weeks".** The 2026-11-10 "SLOT" ruling is deliberately reversed, on the ground
   that the visible "Original slot week" / "Requested slot week" label now carries that meaning. The three
   pinned strings (`status-tooltips-filter-chips.spec.ts:380`, `week-filter-preserved.spec.ts:141`,
   `StudentReplacementHistoryParityTest.php:143`) are amended in the same commit — §6 names all three.

Also fixed from round 2's small items: §3 no longer claims a `week` key exists on Request Approval rows
(it carries dates; both weeks are derived), and §4's per-page touch count is corrected.

**Nothing blocks the proposal now.** It has not been re-reviewed or frozen yet — that is the next step,
and the user has not asked for it.

---

## proposal Round 3 — 2026-11-14 (night)

**Verdict: not ready — three leftover-text blockers, all created by the broadened scope.**

### 🔴 Fixed
- **§5.5 still wrote a Reset for the student page**, contradicting the user's decision 1. Reset is now
  stated as staff-only, with the student page's Clear-all chip named as its only bulk action.
- **§2.4 and §6 step 4 assumed a student Reset.** §2.4 now says "the two staff pages only", §6 step 4
  says "the student page has **no Reset**".
- **The parity-test amendment named one line of a file that pins the old premise in four places.**
  `StudentReplacementHistoryParityTest.php` is now amended as a whole: `:143` (allLabel), `:144` (chip
  text), `:146` (the now-false "does not contain Requested Slot Week:"), `:148` (single-week predicate),
  plus its docblock and test name.

### 🟡 Addressed
- §3's "no filter gains a new option" now carves out the new second week control explicitly.
- §5.5 says "both weeks → All Weeks" on the two staff pages (there are two dropdowns now).
- The automated plan gained item (h) for the new filter (narrowing, its chip, both weeks cleared, saved
  view restored).
- §7.2 (magnifier) and §7.3 (sort hint) are no longer open asks: both are carried into `design.md` with
  the stated answer and flagged there, since each changes one page's look.

---

## proposal Round 4 — 2026-11-14 (night) — FROZEN

**Verdict: ready to freeze**, with one one-word carry-along.

All seven round-3 items verified resolved. One slip introduced by the (h) addition: (h) said "Reset
clears both weeks" per page, but the student page has no Reset. Qualified with the freeze — "(Reset on
the two staff pages, Clear-all on the student page — it has no Reset)".

**`proposal.md` is FROZEN.** Declarative-only changes from here; any decision-level change needs the
full chain unfrozen. `design.md` has not been written.

---

## design Round 1 — 2026-11-15

**Verdict: not ready — two blockers, ten should-fixes.**

### 🔴 Fixed
- **Deleting `#resetFilters` from the toolbar would crash both staff pages' boot.** The pages bind it by
  id (`replacement-home:1419`, `request-approval:1368`); a `null` there throws at `DOMContentLoaded` and
  every listener wired after it dies. §4.2 now pins the two deletions, and §7.1/§7.2 no longer say
  "keep the listeners".
- **Reset on Request Approval also returns rows-per-page to 10** (proposal §5.5) — added to the
  behaviour table, which is what verify tests against.

### 🟡 Addressed
- resize never closes the panel (was a §3 vs §6.3 contradiction);
- the switch's id differs per page (`#hideCompleted` / `#hideCompletedToggle`) — pinned in §1 and §4.2;
- `#filterChips` node identity across open/close added as behaviour row 7b (proposal's automated (g));
- My Replacements keeps its own count tip ("Classes matching…");
- Request Approval's include goes **after** the sort hint, which stays on the page (proposal §7.3);
- reset/clear re-sync both week navs' arrows;
- the no-argument arrow behaviour is pinned to today's (first pair in document order) because four
  timetable pages call it with no `#weekFilter` on the page at all;
- the student requested-week compare is cast (`Number(r.req.week)`);
- the acceptance numbers gained a measurement condition;
- a stacking-context fallback is written for the trigger-at-1300 trick (move the overlay to `body`,
  never raise the overlay's z-index).

---

## design Round 2 — 2026-11-15

**Verdict: not ready — four items resolved, two new blockers created by those fixes.**

### 🔴 Fixed
- **Request Approval's existing week nav would go stale.** The new Original field renders above the
  requested one, so the page's no-argument arrow calls (`:911`, `:1330`) would re-sync the wrong nav;
  both now pass `'weekFilter'`, and reset/clear sync both ids.
- **§7.3 still carried the uncast student predicate** while §4.3 demanded the cast — aligned.

### 🟡 Addressed
- §9's acceptance numbers rewritten as a per-page table with a measurement condition and a
  no-regression bound for the student page (375px today, so a flat "≤400" would have measured nothing
  there);
- the `:empty{display:none}` divergence gets a soft-freeze note in the frozen proposal §5.2.

---

## design Round 3 — 2026-11-15 — FROZEN

**Verdict: ready to freeze** once two one-liners landed; both applied with the freeze.

Verified: the arrow-call pins match the real call sites, the student `-1`/no-`-1` base difference is
correct against `ReplacementHistory.php`, the §9 condition, the soft-freeze note and the two §4.1
traceability deltas.

### 🟡 Fixed with the freeze
- §9's condition said the default chips row is empty apart from the button and the count. It is not:
  the exclusion chip and its Clear all show by default, and the student page has no count. Reworded.
- Two **reveal** paths would have been blinded by the new filter: `jumpToNewRequest()`
  (`replacement-home:1234`) and `revealNotifRow()` (`replacement-history:798`) only cleared the
  original week, so a saved requested week could hide the very row the arrival wanted to show. Both now
  clear it and re-sync its arrows.

**`design.md` is FROZEN.** Next batch: `specs/`.
