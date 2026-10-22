# Review log — week-memory-until-logout

## Batch 1 (proposal + design + tasks, condensed single round) — Round 1 — 2026-10-20

Reviewer: pi subprocess (glm-5.3-flash; full output in session).
Verdict: "Not ready for /sdd-apply — three blockers; none invalidate the
core mechanism".

### Done-marked-blockers (fixed)
1. **The venue's "saves but never loads" claim was false** — the page's
   `weekNav.load()` exists at :766 (wired since the 2026-10-09 venue
   SDD); the round-0 brief missed it (a read-slice miss). Fixed across
   the brief/proposal/design/T4: the venue = NO new wiring, the :766
   load simply becomes stamp-scoped via the mechanism.
2. **The cohort's "neither saves nor loads" was half false** — its
   prev/next/select handlers (:399/:405/:411) call the CLASS methods,
   which already save (ui-common :353/:367/:384); the only gap = its
   first `load()`. Fixed: the gap table, design D3, and T5 (the
   placement BEFORE the first paint + the WHY).
3. **D5's bare add-init sessionStorage.clear() would boobytrap the
   centerpiece row** — add-init runs at every document start; row 2
   would see TODAY at every hop. Fixed: the MARKER-GUARDED once-per-tab
   add-init (init at the first document, the trip unguarded) + the
   three sessionStorage facts recorded (survives reloads / not carried
   by storageState / own-context-per-tab; never hop via a second tab).

### Addressed-(should-fix)
4. The register-auto-login path mints no stamp: the save() side is now
   gated SYMMETRICALLY on a non-empty stamp (a today-only session,
   honest both ways); "not-logged-in edge" replaced (all 4 pages
   auth+role-gated; the register path = the only reachable
   empty-stamp case).
5. ui-regression.spec TC-2.x's venue row (a localStorage slot assert)
   newly breaks: the design commits to UPDATING that row's expectation
   to the session key; its other three stale rows (keys that never
   existed) = documented pre-existing staleness, left + reported.
6. D6 resolved from the seeds: the demo student identity = the seed
   invariant (25RSD0001; TimetableSeedInvariantsTest T-6 + the login
   placeholder; passwords are not pasted into artifacts) and the
   student's role_lifetime = 30 days; staff get 403 on the student
   route, so a live student row needs an in-spec student login (option
   B) WITHOUT touching auth.setup; option A (the class-level receipt)
   stays the default; both presented at apply.
7. T8 commits to the student changelog entry (the file exists) and pins
   the LIVE cohort changelog (cohort-timetable-changelog.md — verified:
   its head carries today's last-visit-dd entry).
8. D5 row 1 asserts the weekSelector (never window.currentWeek — a
   documented deliberate desync; the implementer instructed not to fix
   it) + the cohort row pre-selects the faculty+cohort pair (the
   TC-2.x pattern) before asserting.
9. T3 spells the exact 6-arg call shape for my/student (three null
   placeholders; the derived key 'weekNav-weekSelect' preserved; their
   per-page invariant rides on role gating).

### Outstanding
- None — the named surfaces are fixed as the verdict's own condition
  ("then this batch can pass"); frozen for apply.
