# Review log — venue-cross-week-handoff

## Batch 1 (proposal + design + tasks, condensed single round) — Round 1 — 2026-10-20

Reviewer: pi subprocess `pi --model commandcode/z-ai/glm-5.3-flash
--tools read,grep,find,ls` (full output preserved in session; verdict
"Not ready to apply yet" — one 🔴 + four 🟡 + three 💡).

### 🔴 Fixed
- **The consumer edit at `:2590` was provably unreachable dead code** —
  the site sits behind `armIntentFallbackPrefill`'s `:2541`
  `if (urlParams.session) return;` short-circuit, mutually exclusive
  with a hydrated pin, so its drafted "server pin of the same session
  keeps ITS week" described an impossible event. Deleted from
  proposal's scope (the new NOT-in-scope bullet), design (D2 rewritten
  as "deliberately NOT edited" with the trace), tasks (former T3
  removed), and the explore-brief's code-chain claim corrected (the
  venue path's real re-pin = the T18 clobber at :2966, owned by
  `session-arrival-pin-guard`).

### 🟡 Addressed
- **Proposal's threat model re-stated honestly**: today's submit on the
  repro files the TRUE pair `{97, 8}` only via the T18 coincidence (97 =
  the module's earliest conflict) — the harms re-cast: (a) the strip
  lies, (b) the URL/server payload store a nonexistent pair, (c) the
  rescue does not generalise (a non-earliest cross-week pick would
  submit a different occurrence than picked).
- **D5 residual window declared**: with this change alone, a non-earliest
  cross-week pick still diverges (strip honest W14, pin clobbered to the
  earliest) until `session-arrival-pin-guard` lands; the tasks' changelog
  entries must say exactly this.
- **Task-set gaps**: the regression run now includes
  `tests/venue-book.spec.ts` (the 8-param assert twin) +
  `tests/default-pin.spec.ts` (the hydrated-pin boot rows); the impact
  table + tasks now record `venue-timetable-ui-changelog.md` (the
  standing changelog rule for the producer page).
### 🔴 Outstanding
- None (the reviewer's verdict condition — "fix 🔴1 + the two 🟡 accuracy
  items + the two cheap task gaps, then ready for freeze" — is satisfied
  above; frozen at the reviewer's own named-condition list).

## Confirmation round — 2026-10-20 (same batch, post-fix)

Reviewer confirmed every named fix against the live repo (the :2541/:2590
traces, the producer receivers, the honest threat model, the D5 residual,
the venue-changelog + venue-book + default-pin task items): ✅ CONFIRMED.

### 🔴 Fixed
- (none needed)
### 🟡 Addressed
- Stray CJK glyph in D5 cleaned ("that same W7 slot").
### 🔴 Outstanding
- None — **PASS** for `venue-cross-week-handoff` (apply under the
  sequencing constraint recorded in session-arrival-pin-guard).
