# Review log — venue-status-parity

> Harness note: this pi session has no `@sdd-reviewer` subagent tool, so each
> batch was self-reviewed against the frozen baseline in-session (the skill's
> gate is honoured in substance, not by delegation).

## Batch 1 (proposal) Round 1 — 2026-11-02
### 🔴 Fixed
 - scope drifted to "reuse the whole shared timeline builder" → frozen back to the
   narrow, verified fixes the user approved (no rewrite of VenueTimetable::render()).
 - #11 was written as a numbers change → corrected to "keep counting, fix the look"
   per the user's A.
### 🔴 Outstanding
 - (none)

## Batch 2 (design) Round 1 — 2026-11-02
### 🔴 Fixed
 - the first draft hand-rolled a second exception/approved map set; replaced by the
   existing `LectureSessionCatalog::factContext()` contract (no duplicated law).
 - the derived-pass suppression rule is now stated as ONE sentence with the pending
   case explicit (bug #3's root cause).
### 🔴 Outstanding
 - (none)

## Batch 3 (tasks) Round 1 — 2026-11-02
### 🔴 Outstanding
 - (none)
