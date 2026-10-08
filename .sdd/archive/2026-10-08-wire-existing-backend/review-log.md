# Review log — wire-existing-backend

## Batch 1 (proposal) Round 1 — 2026-10-08

Reviewer: sdd-reviewer subagent (`ses_ee63b4947ffeFQ2uPQIqnniMWl`). Baseline: explore-brief.md; no frozen artifacts yet.

### 🔴 Fixed

- **[W1-1] Nav "filter the static list" cannot produce the student nav.** The static 6-item list has zero student-page entries and nav keys collide across roles (`my-timetable`, `replacement-history` each shared student/lecturer). Filtering would leave students a one-item nav while criterion 2's negative assertion still passed. **Fix:** What item 2 reworded to per-role whitelists with role-specific hrefs (student: cohort + `/student-my-timetable-ui` + `/replacement-history-ui`; lecturer: my-timetable/cohort/venue/replacement-arrangement/request-history; PL: + request-approval); criterion 2 gained the positive containment assertion.

### 🟡 Addressed

- **[W1-2] PL nav had zero coverage** → criterion 1 now asserts 5425's (is_pl) nav contains Request Approval + lecturer items; `NavIdentityTest` scope extended to three roles.
- **[W1-3] D4 "before AND after" was compressed** → What item 4 now mandates the pre-edit baseline capture (current panel HTML for 5425 + student) explicitly.
- **[W1-4] Legacy `MockData.currentUser.name` ownership consumers** (venue template :782/:795, my-request-history :316) → named in What item 3 as accepted-deferred (B/C), so the sweep cannot "discover" them as new scope.
- **[W1-5] Mock session-age script** (partial :118–140, "BACKEND WIRE LATER") → explicitly deferred in Out of scope.
- **[W1-6] Changelog placement arbitrated** → `page-changelogs/auth-wiring-changelog.md` (closest domain log; `backend-automated-by-ai.md` rejected as a Phase-2 seeder log). Criterion 7 updated.
- *(optional, applied)* Criterion 5 wording spells the standing gates; criterion 4 adds the student-side retrieval example.

### 💡 Reviewer discovery folded in

- `User` model already provides `displayName()` / `loginId()` / `initials()` / `isStudent()` / `isLecturer()` — What item 5 now mandates reusing them (no new display logic); verified first-hand at app/Models/User.php:41–93.

### 🔴 Outstanding

- (none — re-review follows)

## Batch 1 (proposal) Round 2 — 2026-10-08

Reviewer: sdd-reviewer subagent (continuation, `ses_ee633e94affey0fppVNqP3oEw9`).

### ✅ Verdict

**PASS — 0 🔴.** [W1-1] whitelist fix verified line-by-line against the frozen rbac spec + current middleware (all 8 routes placed correctly; colliding keys disambiguated per role; `/replacement-arrangement` correctly excluded as non-nav).

### 🟡 Soft-freeze fixes (applied in this pass, per reviewer)

- **[N1]** Accepted-deferred consumer enumeration corrected to **three**: added `CohortTimetable-UI-design-template` (:363/:375); criterion 6 updated.
- **[N2]** Item 5's `initials()` claim corrected: `displayName()`/`loginId()` implement the D2 name/id contract; `initials()` (first+last letter, no id fallback) accepted as 3a display behavior — brief D2's two-word fallback clause superseded; `Lecturer (PL)` line = the only new display logic (`$user->lecturer->is_pl`, null-safe).

**Proposal.md is FROZEN as of this entry.**

## Batch 2 (design) Round 1 — 2026-10-08

Reviewer: sdd-reviewer subagent (`ses_ee62fa27effeahTz3BtIPOtTH8`).

### 🔴 Fixed

- **[B2-1] §4's retrieval tuple was factually wrong against the seed.** `BMIT5678 + B105` belongs to lecturer **5770** (seeder :149–168), not 5425 — the error was inherited from the brief §1.4 example and frozen criterion 4's "e.g." phrasing, and the design hardened it into an executable check. **Fix:** §4 step 4 pins `5425 → BMIT9012 + B107`. **Declarative note on the frozen proposal:** criterion 4's *example* is factually wrong (same inheritance); the criterion's substantive requirement ("real DB session rows, not mock-only") is unaffected — no unfreeze needed, correction recorded here per the soft-freeze rule.

### 🟡 Addressed

- **[B2-2]** §5 pins the page per test (`/my-timetable-ui` ×2, `/student-my-timetable-ui`) — negative assertions are only safe on the role's own page (cohort pages legitimately render other lecturers' sessions incl. 5770's rows).
- **[B2-3]** §4 restart sequence now carries the deviation rationale (targeted kill; `pkill -9 php` would take down phpunit/other php processes).
- **[B2-4]** Student retrieval pinned exactly: 25RSD0001 ∈ `RSD1(S1)G1` → `BMIT2222 + B101` (seeder :423–432).
- **[B2-5]** `backend-automated-by-ai.md` row marked clearly subordinate to the arbitrated primary.
- **[B2-6]** Guest 302 sample routes named — one per bucket (student-only/lecturer-only/PL-only).

### 🔴 Outstanding

- (none)

## Batch 2 (design) Round 2 — 2026-10-08

Reviewer: sdd-reviewer subagent (continuation, `ses_ee62453a3ffeW6mO0uiV3fverl`).

### ✅ Verdict

**PASS — 0 🔴.** [B2-1] correction verified seed-accurate (BMIT9012+B107 = 5425 at seeder :211–220; BMIT5678+B105 = 5770 at :149–158); frozen criterion-4 example error correctly handled via declarative note (no unfreeze — example-agnostic substantive requirement); all 5 🟡s substantively addressed; no new issues.

**Design.md is FROZEN as of this entry.**

## Batch 3 (tasks) Round 1 — 2026-10-08

Reviewer: sdd-reviewer subagent (`ses_ee6209608fferPXM63mj1L33gE`). Full coverage map verified (every frozen obligation owned; corrected tuples re-verified against seeders first-hand; composer.json confirms lint:check ≡ pint --test so the gate compression is faithful).

### ✅ Verdict

**PASS — 0 🔴, 0 🟡.** Ordering safe (baseline → edits → test → live verify → gates → records), STOP-guard on T1.4 retrieval gaps, scope clean, no push.

**Tasks.md is FROZEN as of this entry. All 3 batches frozen — apply may begin.**

## Verify pass — sdd-verify — 2026-10-08

Reviewer-verifier subagent (self-run per standing instruction). Criteria 1–6 **PASS** (first-hand cross-checked: partial source, routes↔whitelist alignment, seeder tuples, sweep line pins); criterion 7 PARTIAL by design ordering (archive = T6.4). **StudentMyTimetable navItems override — declarative-correction handling adjudicated correct:** the decision (`$navItems ?? <whitelist>`) was never abandoned; what was removed is a stale Slice-A stopgap at one caller, and frozen criterion 2's positive assertions *mandate* the removal. Design §2/§7 corrections recorded HERE per [V-2]: design §2's premise "no caller does today" was false (StudentMyTimetable:75 passed a 2-item list); §7 file table gains `app/Livewire/StudentMyTimetable.php` — Modify. T5's asset-level method note: session-age script range shift (:118–140 → :130–161) is insertion-consequence, content unchanged. **VERDICT: PASS — 0 🔴**, with 2 record-completion conditions ([V-1] subordinate changelog row — appended this pass; [V-2] this entry).
