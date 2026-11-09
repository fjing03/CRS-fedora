# tasks — student-history-copy-polish

Small enough to land in one pass; each task is a string change except T3, which needs the user's pick
between the three options in `proposal.md` (recommendation: option 2).

- [ ] **T1** — empty state (`ui-empty-state` include): title "No replacements to show", text naming the
      cohort and the two things that can empty the list (another week, the COMPLETED toggle).
      *Done when:* a search with no match, and a week with no rows, both read sensibly; no "this week".
- [ ] **T2** — page description (`ui-page-header`): name the three states, or drop the enumeration.
      *Done when:* the sentence does not contradict a completed row on screen.
- [ ] **T3** — the Completed card: apply the user's chosen option (recommended option 2 — explain the
      zero in the card's hover text). *Done when:* the card is honest without changing what it counts.
- [ ] **T4** — receipts: source invariants in `StudentReplacementHistoryParityTest` (no "this week", no
      "confirmed or pending" without "completed"), plus one browser pass on a demo student.
- [ ] **T5** — changelog entry in `page-changelogs/replacement-history-changelog.md`; then ask the user
      about archiving the lane.
