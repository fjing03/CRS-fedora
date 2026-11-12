# Review log — student-modal-staff-parity

**Note on the reviewer:** `@sdd-reviewer` is not available in this harness session, so the batch-1
review below is a **file-based self-review** against `explore-brief.md` (the review baseline), done by
re-reading the artifacts line by line. It is recorded here rather than silently skipped.

## Batch 1 (proposal) — Round 1, 2026-11-11

### 🔴 Fixed
- **The footer defect was described as "taste".** It is not: the student footer flips sides between a
  row with a request and a row without one (measured both states). `proposal.md` now names it as an
  outright defect in the *Why* table, so the implementer treats it as a bug, not a preference.
- **`Class Info` would have rendered empty on the demo data.** The proposal originally listed the tab
  without its data dependency; `In scope` #3 and #8 now say explicitly that `cohorts` and
  `totalStudents` must be added to the row payload, or the tab shows blanks.
- **"Remove the Conflict Reason tab" could read as "drop the information".** The acceptance section now
  states where the fact goes (the strip), so a reviewer cannot accept a build that loses it.

### 🟡 Addressed
- The B9 and B5/B6 folds were implied by the checklist reference; they are now explicit decisions #6
  and #7 with the exact labels (`Request Status (Approved)`, `Request ID`, `Class ID`).
- The parked items are listed as non-goals with the reason each was parked, so a later reviewer cannot
  mistake them for oversights.

### 🔴 Outstanding
- _(none)_

**Verdict: batch 1 passes — `proposal.md` frozen.** Design, specs and tasks are NOT written yet: the
user asked for explore + propose only, and apply waits for their approval.

## Batch 2 (design) — Round 1, 2026-11-11 (self-review, file-based)

### 🔴 Fixed
- **The cohort label would have become a third copy of one formatting rule.** `spacedCohortLabel()`
  is a `private` method on `ReplacementHome`; the design's first draft said "mirror it". Fixed by
  promoting it to `app/Support/CohortLabel.php` and having `ReplacementHome` delegate (output
  byte-identical) — the lane is now 4 files + 1 new class, and the cost is one `return`.
- **`Class Info` had no defined source for its two new facts** — the design now names them exactly
  (`cohorts`, `totalStudents`, `statusShort`) with their in-file origins, and pins `—` for an unknown
  count instead of `0`.

### 🟡 Addressed
- Tab activation: the design uses the existing `DetailModal.selectTab('class-info')` rather than
  re-ordering tabs per row, so the tab strip never jumps around between rows.
- `Request Status` degrades to the bare label when `statusShort` is absent (old payload snapshots).

### 🔴 Outstanding
- _(none)_

**Verdict: batch 2 passes — `design.md` frozen.**

## Batch 3 (specs) — Round 1, 2026-11-11 (self-review, file-based)

### 🔴 Fixed
- **R5 needed the "never swap sides" clause.** The whole point of the lane is the measured flip
  between rows with and without a request; the first draft only described the two buttons' styles.
  R5.4 now states the invariant.
- **R2.2/R2.3 split was missing.** A single "full text" requirement would have let a build print the
  long form for a cancellation too, which can never happen for a student (no reason in the payload).
  The requirements now separate the holiday and the cancellation cases.

### 🟡 Addressed
- Scenario S5 (390px) added so the shared mobile footer behaviour is covered by the spec, not only by
  the tasks.

### 🔴 Outstanding
- _(none)_

**Verdict: batch 3 passes — `specs/student-modal-parity/spec.md` frozen.**

## Batch 4 (tasks) — Round 1, 2026-11-11 (self-review, file-based)

### 🔴 Fixed
- **T1 had no receipt.** Promoting a private helper to a shared class could silently change the staff
  payload; the task now demands the before/after cohorts array be identical.
- **The commit's staging list was implicit.** The house rule (never stage another session's files) is
  now spelled out, naming the parked table-width edit and the other session's `arrangement-mobile-*`
  files.

### 🟡 Addressed
- The known red test (`ReplacementHistoriesTest`) is named in T5 so a future reader does not treat it
  as a regression from this lane.

### 🔴 Outstanding
- _(none)_

**Verdict: batch 4 passes — `tasks.md` frozen. Lane is ready to apply on the user's word.**
