# Review log — my-timetable-responsive-prompt

Append-only. Batch order: proposal → design → specs → tasks, each frozen before the next.

## proposal Round 1 — 2026-11-14

Reviewer: independent read-only (`commandcode/z-ai/glm-5.3-flash`), system prompt in
`/tmp/mt-resp/reviewer-system.txt`.

### 🔴 Fixed
- `buildTimetableGrid()` has **five** live callers, not four: the fifth is
  `replacement-arrangement.blade.php` through the legacy `cellRender` path. The proposal's
  "shared by four / the other three stay byte-identical" was wrong and its pin test would have
  missed the arrangement page. §6 and the risk table now name all five.
- §3 item 4 (day heading + Week summary button) carried no width qualifier and would have
  silently answered the brief's open §13c ("should Week summary be reachable from desktop").
  Now scoped below 1280px, and a new §9 lists all three §13 questions as **not decided here**,
  including the print button's narrow-width fate (§13a).

### 🟡 Addressed
- The print button's quoted string: the toast is `Printing is coming soon` (no period) and its
  hover tip is `Coming soon`. Corrected.

### 🔴 Outstanding
- None.

## proposal Round 2 — 2026-11-14

### 🔴 Fixed
- **The block tooltip migration had been dropped from scope.** The brief (§2) makes it part of
  the task: today the block tip is the CSS `::after { content: attr(data-name) … attr(data-tip2) }`
  hack (`theme.css:3848`) and `initDataTipTooltips()` only reads `data-tip`. §3.7 now assigns the
  `data-tip2 → data-tip` conversion (same composed words) plus the two missing control tips, scoped
  through the opt-in flag so the other four callers keep the old path. The risk table gained the
  matching row.

### 🟡 Addressed
- `review-log.md` was empty while `sdd.yaml` said `review_rounds: 0`; round 1 is now recorded.
- "iterated five times" was ambiguous (the brief's rounds 1–5 belong to the print page); reworded.

### 🔴 Outstanding
- None.

## Scope change — 2026-11-15 (user, not a review round)

The user asked whether Student My Timetable belongs in this lane. Decision: **yes, as phase B**,
because the two pages are one page with different data — both blades carry 0 `@media` blocks, include
the same six partials and call the same `buildTimetableGrid()` (student 252 lines, staff 352, and the
student blade is a strict subset of the staff blade's wiring).

Recorded in the proposal as §3b, with §1 (why), §5 (the other two timetable pages stay out), §6 (the
read-only constraint and the lockstep rule), §7 (one risk row), §8 (phase-B done criteria) and §9d
(the one new open question: what a student agenda card says, since its block status carries Pending
and approved Replacement). `sdd.yaml` gained `phase_b_page` / `phase_b_decided`; `page:` still names
the staff page, so nothing downstream has to change shape.

Consequence for the review: the proposal is **not frozen** (`proposal_review: pending`,
`proposal_frozen: false`), so widening it now costs one paragraph, not a re-freeze. The brief
(`../ui-prototype/my-timetable-responsive-prompt.md`) still names My Timetable only; §3b is the
authority for the student page, and phase B asks the brief's author before it contradicts it.

