# review-log — cohort-venue-replace-now

(Batches append below.)

## Batch 1+2+3 (proposal/design/tasks) Round 1 — 2026-10-24 (reviewer: glm-5.3-flash, sdd-reviewer prompt)

Reviewed together (proposal NEW; explore-brief baseline; design+tasks were
pre-drafts shown for coherence — the reviewer's blockers folded in before
any apply).

### 🔴 Fixed
 - D1/T1 blocker: the cohort page NEVER maintains `window.currentModalEvent`
   — added the init + the openModal assignment to design D1 + tasks T1
   (without it the button click throws ReferenceError on fresh load).
 - D3/T3/D4-row-5 blocker: the route row pinned the temporary `-ui`
   redirect — repointed to the CANONICAL `/cohort-timetable`
   (routes/web.php :37; the :99 `-ui` redirect is scheduled for removal).

### 🟡 Addressed
 - Proposal: dropped the `?.id ?? ?.sessionId` composite (venue `id` is the
   SLOT id — a wrong-identity trap); replaced with the per-page table.
 - Explore-brief: annotated the superseded "raw pass" instruction point;
   annotated the nonexistent `lectuer` typo as DO-NOT-TOUCH.
 - Design D4: pinned that class-modal-affordances.spec.ts contains no
   venue hand-URL week assertion (reviewer's grep receipt) — the re-run is
   the conditional tripwire only.
 - Venue Week-1 no-op cure noted (the `!week` guard ate the 0-based 0) —
   the venue changelog gets both honest fixes.

### Verdict (reviewer): ready to move into implementation once the two blockers land; they landed above.

## Apply-phase receipts — 2026-10-24/25 (T1–T5)

### Apply refinements (declared in design.md as needed)
- **D1 gate formula refined (apply-phase receipt):** the conflict arm
  reads the page's OWN isConflict definition — `(dayHoliday) || status ===
  'conflict'` — the cohort baseEvent stamps ONLY the venue-rule verdict;
  holiday weeks (AMSE2003 (T) W14 Dec 24) stay status-normal in the
  payload. Frozen artifact updated (declarative: same intent "the venue
  gate family", the venue payload bakes holidays into status so the two
  pages agree on TRUTH).
- **T1 gained TWO blockers beyond the R1 list:** (a) the page's week navs
  never mirrored `window.currentWeek` (the inline button reads the
  GLOBAL; the stale boot week handed off the wrong pair) — fixed with the
  venue :814—826 parity lines; (b) discovered at the venue page: see
  below.
- **T2 grew to THREE fixes (the user's law understated its blast):**
  (1) the include-order bug — the venue page defined its modal-footer
  section AFTER the shared partial's include, so the Replace Now button
  HAS NEVER RENDERED since the class-modal-affordances freeze (its test's
  `!r || display==='none'` assert accepted an absent button — a frozen-receipt
  hole); (2) the `+1` week-arg fix (the frozen plan; the W1-no-op cure
  noted); (3) the venue payload's derived-conflict rows (the holiday pass
  ~:461) lacked `sessionId` — `goToReplacementFor`'s !sessionId guard
  swallowed the click (the additive row at :469, same contract as :249).
- **One live incident during the venue edit (honest record):** the
  apply-phase footnote contained the literal `@hasSection` text — Blade
  compiles directives inside HTML comments → an unterminated conditional
  → the page 500'd (ParseError) for ~3 probes; reworded, page healthy
  (14 wkOpts + the button at boot + rows). The incident note documenting
  the no-@-directives-in-comments rule is now PART of the blade comment.

### T5 gate receipts (2026-10-25)
- the new spec ×2: 8/8, 8/8 (both browser rows include the handoff URL asserts).
- the class-modal-affordances tripwire: 6/6 green (no re-anchor needed —
  the reviewer's grep held).
- pint passed · phpstan-1G 0 · phpunit 258/258 (assertions 1381).
- BOTH changelogs dated (cohort + venue-timetable-ui).
- ONE conventional commit; NEVER push.

### Verdict: T1–T5 LANDED — ready for /sdd-verify.
