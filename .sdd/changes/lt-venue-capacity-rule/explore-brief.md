# Explore brief — lt-venue-capacity-rule

## User ruling (2026-10-19, session verbatim)
> "Lecture Hall & Tutorial Rooms are allow to have L/T."

Combined with the standing capacity rule: a venue admits an L/T session when
**capacity suffices**. Labs (both `lab` and `cisco_lab`) keep **P-only**.

## Receipts — why the current strict map is wrong
- `CodingMAIN.md` §Venue types: "Tutorial Rooms (cap ≤ 35): B100–B109, B014–B018, B002 — **L/T sessions**".
- DB `venues.allowed_session_types`: every tutorial = `'L,T'`; B110/B111 = `'L'`; labs = `'P'`; B006 = `'P'`.
- Real-timetable audit (tinker, 2026-10-19):
  - **23 distinct L sessions run in tutorial rooms** today (AMSE2003 among them — the demo's own booking question exposed the mismatch).
  - **0 T sessions have ever run in B110/B111** (the new ruling makes LH T-tolerant going forward; today's data carries none — consistent, no legacy T-in-LH to honor).

## Where the L/T rule gates code today (the surfaces this change touches)
1. `app/Services/MatrixIntersectionEngine::eligibleVenues()` — the SUBMIT-SIDE
   gate: keys on the **CSV column** `allowed_session_types`. Consequence today:
   L-in-tutorial is legal there (CSV has L), but **T-in-LH is engine-blocked**
   (LH CSV = 'L') — contradicts the ruling.
2. `app/Services/LectureSessionCatalog::ruleMap()` — the data-rules map
   (`L → ['lecture_hall']`, `T → ['tutorial']`), consumed by the
   Select-a-Session modal + the arrangement fallback. Strict map — contradicts
   the ruling on both rows.
3. `resources/views/livewire/replacement-arrangement.blade.php::venueEligible()`
   — client dd filter (display vocabulary `Tutorial`/`LectureHall`/`Lab`):
   `T → Tutorial`, `L → LectureHall` strictly. Contradicts the ruling.

## Rejected approaches (and why)
- **Edit the demo DB's `venues.allowed_session_types` (e.g. LH → 'L,T')** —
  rejected: the demo DB is never re-seeded/mutated by the agent; the column is
  also upstream-imported data whose divergence must stay VISIBLE, not be
  papered over with a data patch.
- **Hardcode per-room special cases in the engine** — rejected: rule-as-data
  (`ruleMap()`) is the established home (arrange-intent-session-choice); a
  room-name switch would fork the law.
- **Widen `P` or B006 behaviour while here** — rejected: out of scope; R3's
  B006 networking widening stays PLANNED-NEW in its own future change.

## Final solution shape (frozen in design.md)
- The **canonical room_type rule map becomes the single law** for
  L/T/P eligibility at booking time; the CSV column stays as reference
  inventory (it still drives harmless display surfaces) but stops gating.
  - `L → ['tutorial', 'lecture_hall']`
  - `T → ['tutorial', 'lecture_hall']`
  - `P → ['lab', 'cisco_lab']` (unchanged — no B006 creep)
- Capacity gate unchanged (`headcount <= capacity`), all layers.
- B005 no-Diploma stays DOC-ONLY (no engine enforcement sneaks in here).
- Consumers iterate the map arrays — widening a row touches NO consumer code.

## Known open questions
None carried forward. Capacity threshold semantics, B005/B006 scope, and test
contract deltas are decided in design.md (the user's own ruling + the repo's
standing rules cover them).
