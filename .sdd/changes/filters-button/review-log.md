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
