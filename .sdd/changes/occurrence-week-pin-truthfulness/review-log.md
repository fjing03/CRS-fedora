# Review log — occurrence-week-pin-truthfulness

## proposal Round 1 — 2026-10-19
### 🔴 Fixed
 - The incident narrative re-based on the reviewer's code-truthful reading: the REAL defect = the stale `window.currentWeek` desync (blade:99 re-synced only by the page wrappers, not by load()/Today/shortcuts) + NO occurrence-fact check anywhere; the brief's §(a) answer and F4's wording were amended (handoff week = the fact's week; window.currentWeek loses its last reader) — declarative edits (nothing frozen yet).
### 🟡 Adopted into the proposal/design duties
 - F1's single fact-lookup primitive (unify conflictFacts/conflictMapFor); the week bound aligns to week_count; the Route-C SQL escrow pointer; F5 verified premised (VenueTimetable lacks the original* keys).
### Verdict: proposal FROZEN (per reviewer: "with the two text amendments landed, ready").
