All checks complete — tasks.md read in full, cross-checked against frozen design D0–D6, the 7-round review-log tail, the frozen proposal's six in-scope items, and ground-truth (phpunit.xml/.env/tests/auth.setup.ts) for the DB and limiter claims.

## 🔍 Review Report: arrange-intent-session-choice (tasks.md, confirmation round 1)

*This round reviews tasks.md ONLY. proposal.md (3 rounds) and design.md (7 rounds incl. R4-confirmation, 4 soft-freeze amendments) are FROZEN — reviewed here solely for consistency, never challenged.*

### ✅ (1) Coverage map — design + proposal → tasks

Every design section and proposal in-scope item maps to ≥1 task:

| Frozen artifact item | Task(s) | Verified |
|---|---|---|
| D0 ruleMap + contract test | T1 (`ruleMap(): array` D0-shaped + "the D0 contract test"), T2 (both blades hydrate map — D6 💡-4 bridge) | ✓ |
| D1 service (`payloadFor`, id-keyed map + `order` array, week facts, ordering tuple, delegation) | T1 (full facts list, `(day_of_week, start_time, id)` tuple, `coursesPayload` delegates, shape preserved + additive `order`), T2 | ✓ |
| D2 producer (wire method, partial, wiring, 8-param URL, cancel, zero-fit) | T3, T4, T5 | ✓ |
| D6 raw `room_type` page-data field | T2 (`venuesPayload` per-venue raw `room_type`) | ✓ |
| D3 fallback (cancelled gate, 2a–2e, no-match banner, pin bridge, bare `?venue=` untouched) | T7 | ✓ |
| D3 item 4 five-branch copy set | T8 (all five branches + precedence + additive helpers + attributed-toast text + byte-frozen strings from proposal item 5 + D3) | ✓ |
| D4 tests (all 3 specs incl. amendments) | T6, T9, T10 regression set | ✓ |
| D5 docs/hygiene | T6 + T8 changelogs (dated 2026-10-15), T11 | ✓ |
| D6 accepted-risk items needing code | T2 (the round-1 D6 amendment: two page-data facts, no write-path) | ✓ |
| Proposal item 3 (8-param URL incl. `code`/`cohort`/`week=currentWeek+1`) | T5 (T7 names `session`-param short-circuit side) | ✓ |
| Proposal item 5 attribution toast | T7 (`:2674`) + T8 (text) | ✓ |
| Gates + closure | T10, T11 | ✓ |

**All 4 R4 soft-freeze amendments are carried into tasks:** ① double-jump landing assertion → T6 ("exactly one week jump — the double-jump collapse"); ② byte-frozen copy strings → T4 + T8 ("byte-frozen"); ③ decidable filter predicate → T3 verbatim (`venue.rawRoomType ∈ ruleMap[session.type]` + B005 pattern); ④ wire-method ownership pin → T3 verbatim (`payloadFor(auth()->id(), $week1)`).

### ✅ (2) Ordering safety — verified

- **T1 precedes both consumers** (T2 delegation, T3 component payload): Phase 0 → Phase 1. ✓
- **T3–T5 producer precedes T7/T8 consumer**: Phase 1 → Phase 2. ✓
- **Stale-cache ritual**: named in the ⛔ GUARDS header ("after any blade edit", 2 separate commands) and restated in-line in T5. T4/T7/T8 are blade-edit tasks covered by the header rule. ✓
- **Demo-DB guard**: present in guards. **Name discrepancy ground-checked**: tasks say `class_replacement_fresh`; AGENTS.md generically says `class_replacement` — but `.env:26` confirms `DB_DATABASE=class_replacement_fresh` and `phpunit.xml:29` confirms `class_replacement_testing`. **Tasks match the actual workspace; not an issue.** ✓
- **TC70-style destructive-write re-check (from tasks.md text)**: T6's new spec = open modal, preselect, **Cancel/Escape no-op**, zero-fit probe (read-only payload probe, skip-with-note), URL-equality assert, and the post-[Arrange] landing assertion **stops at consumer render** (pinned week + booking auto-selected — a client-side pre-selection, no `consumeBookingIntent`-beyond-boot submit). T9 = hand-typed URL navigation + copy-branch asserts — **no submit anywhere**. `venue-book.spec.ts` change only routes the existing U2a-T3 navigation through the modal (contract change, documented) — no new write assertion. All PHPUnit fixtures in `class_replacement_testing` per the guard. **Confirmed: zero new destructive-write E2E paths against the demo DB.** ✓

### ✅ (3) ≤2h plausibility + file paths — verified vs frozen design

- `resources/views/partials/ui-intent-session-modal.blade.php` (T4 ↔ D2) ✓
- `App\Services\LectureSessionCatalog` (T1 ↔ D1) ✓
- `tests/venue-intent-modal.spec.ts` (T6 ↔ D4) ✓, `tests/intent-fallback.spec.ts` (T9 ↔ D4) ✓
- All other paths (`ReplacementArrangement.php`, `VenueTimetable.php`, both blades) match frozen anchors. No task names a file absent from design/proposal.

### ✅ (4) Fortify limiter math — ground-verified, safe

`.auth/staff-5770.json` is produced by a single `auth.setup.ts` login per project run and consumed via `test.use({ storageState: ... })` by every spec file (venue-book, venue-timetable already do this). The two new specs opt in the same way → **zero additional logins per run**, per-file logins well within any 5/min limiter. T6's "(storageState one-login)" and T9's "(storageState)" match the house pattern exactly. ✓

### ✅ (5) Fallback bootstrap ordering — exact match with design D3

T7 reproduces D3 faithfully: **immediately after `applyUrlParams()`, before `__bootDone` (`:3227`)** ✓; FIRST step = `intentCancelled` gate ✓; 2a legacy urlParams family hydration → 2b inline span consequences (`BLOCK_SPAN`/`MAX_SELECTION` clamp + `urlSpanLocked = true`) → 2c pin bridge (`window.pinnedOccurrence`, week +1) → 2d **reset flags → arm attribution → `sel.value = code; onSubjectChange()` → `:2712` = boot's ONLY pick**, toast at `:2674` → 2e staleness tail ✓; manual-pick-never-overridden ✓; bare `?venue=` untouched ✓; session-param short-circuit pinned in T9 ✓. Sequencing matches the round-6 resolution exactly.

### 🔴 Critical Issues (implementation blockers)

**None.** No defect found that would redirect implementation, miss a frozen requirement, or contradict a frozen artifact.

### 🟡 Should Fix (non-blocking; quick soft-freeze amendments)

1. **T10 gate command typo**: `vendor/bin/phpsan analyse` → should be `vendor/bin/phpstan analyse --memory-limit=1G` (the guards header has it right; T10's inline copy is corrupted). It fails loudly (command not found), so it can't silently mislead — but a frozen-gate line should be byte-correct the way the byte-frozen user strings are.
2. **T4 zero-fit: only one message state named.** D2 item 6 requires the partial to render TWO notice states — the base zero-compatible message **and** the duration-extension variant (*"…and none of them fit before the day end ({end time})."*). T4 names only "byte-frozen message" singular. An implementer building from tasks alone could omit the second state and force a second blade-edit round (and a second changelog entry). One clause added to T4 closes it.
3. **T7 is the heaviest task in the change and is realistically >2h**: cancelled gate + full 2a–2e hydration pipeline + staleness tail in one checkbox — this is the exact region all six design fix-rounds fought over. Recommend an optional split ("T7a: gate + 2a–2c", "T7b: 2d–2e + banner wiring") so a mid-flight truncation can't land a half-pipeline state. Same reasoning rubs on T1 (extraction + ordering + week facts + delegation + contract tests), but T1's units are mechanical; I rate only T7 as genuinely risky.

### 💡 Optional Suggestions

- **T7 anchor drift vs D3**: T7 cites the hydration pattern as `:3141–3155`; D3 2a says `:3147–3156`. Same pinned-hydration family (both anchors appear across frozen rounds); cosmetic — implementation reads code, not anchors.
- **T5 omits D2 item 5's focus restoration** ("[Cancel]/Escape … restores focus to the triggering cell"). Design is authoritative; a half-clause in T5 would prevent an a11y detail from being forgotten.
- **T10 regression list omits D4's "room-matrix behavior in arrangement specs"** (D4 names it; T10 lists venue-db/venue-timetable/type-labels + the three in-scope suites). One added name.
- **T11's "changelog inventory complete"** could name the two D5 files explicitly (`venue-timetable-ui-changelog.md` + the arrangement changelog) so the closure check doesn't rely on memory of T6/T8.

### ⚖️ Review Verdict

Substantively complete, correctly ordered, hazard-honest, limiter-safe, and faithful to all four soft-freeze amendments. The three 🟡 items are one-word-to-one-clause fixes (typo, missing message-variant clause, optional task split) — none changes direction or scope, all fixable as soft-freeze amendments without unfreezing anything.

**VERDICT: CONFIRMED — tasks FROZEN eligible, 0 outstanding 🔴**
