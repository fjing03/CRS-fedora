# Review log — arrival-grid-no-pin

## Batch 1 (proposal + design + tasks, condensed single round) — Round 1 — 2026-10-20

Reviewer: pi subprocess (glm-5.3-flash; full output in session).
Verdict: "Ready to proceed into implementation after the four yellow
fixes — none reopens a decision." (No blockers found.)

### Fixed (yellows, all four)
1. **S2 retune overstatement removed**: S2 asserts only strip facts +
   the pin + the headcount + the trigger — NO landing asserts; the
   retune = S1 ONLY (D2's row, T2, the proposal's wording).
2. **The combined pinned+intent priority made explicit**: combined
   URLs (session+week AND venue+date+time — the A6 family) resolve-jump
   win the landing (the booked-date week); the pinned-earliest rule = for
   INTENT-LESS pinned arrivals. Recorded in the brief, D1's shipped
   comment (the loud "ALWAYS" reworded), D3's boundary block, T6's
   changelog contract, and the tasks' T1 comment rewrite.
3. **D4's add-init discipline spelled**: memoryless rows remove BOTH
   keys (arrangementWeek + the legacy currentWeek — the F-11 pair);
   row 2 = the delete-derive-reseed two-hop; row 2 ALSO asserts
   arrangementWeek STILL equals the seeded value after the pinned
   landing (the strongest rent-is-dead receipt).
4. **Stale commentary now covered by the tasks**: (a) T1 rewrites the
   Q2-A boot comment at the arrangement blade (its priority-chain +
   entries clauses are stale); (b) T2 rewrites S1's inline T6-B
   landing comment; (c) T3 rewords the earliest suite's header chain.

### Addressed (optionals)
- The proposal's mojibake fixed (selectWeek(pinnedIdx)).
- The anchor drifts reposted to the live counts (:3534 / :3550).
- D4 row 2's arrangementWeek-stays-seeded assert added (see fix 3).
- The degenerate end-of-semester edge documented (no bookable week
  = the pinned arrivals stand on week 1 via the if(eb) fall-through).

### Outstanding
- None — the verdict's own condition ("ready after the four yellow
  fixes") is satisfied; frozen for apply.
