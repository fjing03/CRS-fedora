# tasks — `cancelled-blocks-stay-visible`

(≤ 2 h each. Reads against the frozen proposal + design D1–D9.)

- [ ] **T1 — the server read-model** (design D1): the `$approvedByPair` map
  (0 extra queries — `$requests` already loaded); the single
  `$exceptionRow` keyBy (the bool map DIES); the exception'd branch = the
  cancelled render (status/cancelledReason-label/proposed/occurrenceFact)
  + the same-week amber emission + ALWAYS `continue`; the `replaced`
  dead-arm note in ReasonLabel; NO `ApproveRequest` change (D4 STRUCK).
- [ ] **T2 — the label law** (design D3): NEW
  `app/Support/ReasonLabel.php` (the OVERRIDES + titleCase); the
  `ReplacementHome::titleCase` + `ApiReadController::$reasonMap`
  delegations (the 3-consumer receipt); `replaced` marked dead-but-
  harmless.
- [ ] **T3 — the client** (design D5): the FOUR ui-common touch
  points (the :731/:788 day-event filters — cancel the cancelled discard;
  :805 the class arm; :650 the plain `(Cancelled)` note) + the FIFTH:
  the cohort blade's statusClassFn arm (`:508` re-grep).
  `eventStatusLabel` :2338 = NO EDIT (the existing plain 'Cancelled' arm
  is the locked tip source).
- [ ] **T4 — the summary re-machine** (design D6): the :890 tally term +
  `myTeachingStats` gating + the THREE blade filters removed.
- [ ] **T5 — the tests** (design D7): NEW
  `CancelledBlockStaysVisibleTest` (the 4 receipts) + the
  `TimetableWiringTest` empty-state receipt upsert. NO fixture re-keys.
- [ ] **T6 — record-keeping** (the D9 gates): the changelogs
  (my-timetable, cohort, student, venue receipt-only) + pint + phpstan-1G
  + phpunit (the gate shape 257+1+4=262) + `node --check` — NO Playwright
  (the user's manual regime); ONE `feat:` commit; NEVER push.
