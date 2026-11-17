# Review log — hover-only-copy-touch

Append-only. Each round records what was fixed, what was addressed, and what is outstanding.

## proposal Round 0 — 2026-11-17

Not yet run. The proposal and this lane's metadata were written after the measurement in
`docs/ui-hover-only-2026-11-17.md`, and after one apply attempt that the owner reverted.

Baseline for the first round: `docs/ui-hover-only-2026-11-17.md` (§2 inventory, §5 correction) is the
evidence file; nothing in this lane is frozen yet.

### 🔴 Outstanding
- Reviewer round 1 not yet run.

---
## proposal Round 1 — 2026-11-17

Reviewer: separate read-only pi subprocess (glm-5.3-flash, tools read/grep/find/ls). Verdict: **not ready to
apply**, three blockers. All five claim-set items were independently verified first.

### 🔴 Fixed
- **`#clearTip` does not exist.** It sits inside a Blade comment (`{{-- Clear ALL is COMMENTED OUT … --}}`,
  since 2026-11-10). The proposal had two live carriers and four strings; there is **one** carrier. Scope,
  tasks and AC-4/AC-5 now reference `#submitTip` only; `#clearTip` is recorded as excluded and untested.
- **`#submitTip`'s tip is state-aware.** `updateCounter()` (`:3484`) rewrites `data-tip` between the disabled
  reason and the action tip, and toggles `#submitRequestBtn.disabled` (`:3479`) in the same call. A static
  `aria-label` would contradict the visible tip in one state. A task now covers the sync in both branches,
  AC-5 tests **both** states, and §3's cost story is restated (it is no longer "four strings").
- **Phase split contradicted itself** between proposal §2 and `sdd.yaml` (`scope_phase1` deferred the
  btn-tip work that AC-3/4/5 test). `sdd.yaml` now has `scope_in_this_lane` / `scope_phase2` / `scope_excluded`
  matching the proposal exactly.

### 🟡 Addressed
- §1(b) no longer claims two carriers holding a statement; it states one, and notes the tip is a *reason*
  only while Submit is disabled.
- Badge count aligned and named: `badge-holiday`, `status-rejected`, `badge-outline` (three).
- AC-3 now names its precondition: the dismissal tap must land on a **non-focusable** target, or the test
  gives a false pass.
- AC-1 now says "in any case".
- §8 carries the recorded open question (exemption vs lane) instead of leaving it only in `sdd.yaml`.

### 🔴 Outstanding
- None.

### Note
The reviewer's optional point is kept as written in §4: "Open the slot's tooltip" is borderline jargon, but
it names no gesture and has already been seen rendered. The fallback wording is recorded, not adopted.
