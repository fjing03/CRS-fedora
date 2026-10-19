# Review log — occurrence-week-pin-truthfulness

## proposal Round 1 — 2026-10-19
### 🔴 Fixed
 - The incident narrative re-based on the reviewer's code-truthful reading: the REAL defect = the stale `window.currentWeek` desync (blade:99 re-synced only by the page wrappers, not by load()/Today/shortcuts) + NO occurrence-fact check anywhere; the brief's §(a) answer and F4's wording were amended (handoff week = the fact's week; window.currentWeek loses its last reader) — declarative edits (nothing frozen yet).
### 🟡 Adopted into the proposal/design duties
 - F1's single fact-lookup primitive (unify conflictFacts/conflictMapFor); the week bound aligns to week_count; the Route-C SQL escrow pointer; F5 verified premised (VenueTimetable lacks the original* keys).
### Verdict: proposal FROZEN (per reviewer: "with the two text amendments landed, ready").
## design Round 1 — 2026-10-19
### 🔴 Fixed (round 2 edits)
 - D-P0 re-shaped: factContext (batched) + occurrenceFact (pure accessor) + factsIndex + weekBound; conflictFacts/conflictMapFor collapse onto it (the ONE-source covenant; no per-pair query storm).
 - D-P1: the flat `$fail('occurrence_not_conflicted')` pre-write loser (after invalid_week, before slot + before create) + the conflictText() copy map destination; impact-table additive rows declared.
 - D-P4 re-grained EXACT-PAIR: occurrenceFact stamped per event in the week loop (with the pair's week RIDING the stamp — the handoff reads fact.week; no undefined deref); the LOWEST/multi-button residue deleted; the display branch stays on holidaysJs (both bound by week_count); window.currentWeek's initializer pair protected by note.
 - D-P5: with(classSession.venue) + the venue-less guards; D-P8: both-statuses keys assertion + the bookable-slot isolation; D-P2: the contextJson dual-key note.
### 🟡 Addressed (round 3)
 - The proposal's soft-freeze wording "(the fact's week list)" → "(the fact's own week)" (the multi-week phrasing retired).
### Verdict: design FROZEN (zero 🔴; the stamp-week fix landed in the same edit round).
## specs Round 1 — 2026-10-19
### 🟡 Addressed
 - R1's "guest" → "GUARANTEED"; S5's garble → the `?week=14` keyed handoff.
 - S6 (the approve chain → the D3 stamp of the ORIGINAL week + the no-free control), S7 (each fact-kind's button scenario), S8 (the seeder-premise assert in the first fact test) appended.
 - D-P2's design gained the positional row-push-before-continue note (the results[i]↔blocks[i] contract).
### Verdict: specs FROZEN (conditionally-ready items landed; zero 🔴).
## tasks Round 1 — 2026-10-19
### 🔴 Fixed
 - T6's invented button-title clause removed (no new copy — the display stays token-frozen).
 - T10 rewritten: the S5 BOTH-mode gate/handoff rows + S7's page-side kinds, the exception-kind-at-PHP-level note (T5's skip clause) so no Playwright round hunts an unrenderable button.
### 🟡 Addressed
 - T2's loser order pinned (ownership → invalid_week → invalid_span → fact loser; before the lookup + create).
 - T8's S1 = a TWO-block positional-contract test.
 - T11 names the EXISTING changelog files (venue-timetable-ui-changelog.md — no stray file).
 - T5 instance-call style + the exception-skip note; T1's factsIndex dead-code-purge comment; T12's TC70 + slice names; the D-P7 no-over-edit standing row.
### Verdict: tasks FROZEN (zero 🔴; ready for /sdd-apply).
