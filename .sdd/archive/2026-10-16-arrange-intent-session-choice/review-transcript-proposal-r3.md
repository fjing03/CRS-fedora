## 🔍 Review Report: arrange-intent-session-choice (proposal.md, round 3)

*This round re-reviews proposal.md only; nothing else is frozen for this round other than the four round-2 findings carried in (note: `review-log.md` contains only the round-1 entry — round-2 findings were received verbally; see 💡).*

### Resolution audit of the four round-2 findings

**🔴 — label template/example omitting W{n} → VERIFIED FIXED.**
Scope item 5, last bullet, now reads: template `"{module} {name} ({type}) · W{n}, {day abbr}, {DD Mon YYYY} · {HH:MM}–{HH:MM}"` and the example `"AMCS2093 Advanced Web Programming (P) · W5, Thu, 22 Oct 2026 · 14:00–16:00"`. The example contains `· W5, Thu, ...` and is byte-identical to the user-frozen string (same separators: `· ` after `(P)`, `, ` after `W5` and `Thu`, ` · ` before the time range, en-dash `–` in `14:00–16:00`). Template↔example fields align field-by-field (module=AMCS2093, name=Advanced Web Programming, type=(P), n=5, day=Thu, date=22 Oct 2026, span=14:00–16:00).

**🟡 — payload facts can't render the label → RESOLVED with one small caveat (see 🟡-new below).** Scope item 2 now carries `day-of-week, start/end times` in the per-session fact list, explicitly "NOT in today's `coursesPayload`". Combined with the producer-owned booked-week context (`currentWeek`), the template's `{day abbr}` and `{HH:MM}–{HH:MM}` are renderable.

**🟡 — "payload session order" under-determined → RESOLVED.** Item 2 now mandates `sessions` as an **array** canonically ordered by `(day_of_week, start_time, id)`, and explicitly defines the preselection rule's "payload session order" as *this* array order — removing the object-keyed-numeric-id ambiguity. Coherent with item 1's auto-preselection bullet and "user-confirmed Q2; no closeness heuristics".

**🟡 — fallback base unstated → RESOLVED.** Item 4 now defines `intentWeek` as the intent's **0-based** week index, date-resolved by `resolveBookingIntent`, and states the symmetric conversion: producer `currentWeek` (0-based) → `week = currentWeek + 1` at handoff; fallback → `week: intentWeek + 1` for `pinnedOccurrence`. Both conversions match `pinnedSessionPayload`'s existing 1-based convention; the "both are 0-based, both hand off 1-based" sentence makes the base unambiguous.

### 🔴 Critical Issues (implementation blockers)

*None found.* The three newly-added fragments (label text, array ordering, intentWeek base) do not contradict any frozen boundary (explore-brief, user decisions, non-goals) or any existing scope item.

### 🟡 Should Fix (non-blocking but recommended)

- **Label's `{DD Mon YYYY}` is not an explicit fact:** the item-2 fact list carries `day-of-week` + start/end but not the **date itself**. It *is* derivable (day-of-week × booked-week base date, which `VenueTimetable` already computes for grid rendering), so this is not the round-2 render-blocker — but pin the derivation in design (or add "occurrence date in the booked week" to the emitted facts) so the label builder has one unambiguous source instead of assuming derivation context. One sentence; prevents a design-stage "where does the date come from?" stall.

### ✅ What's Done Well

- All four round-2 fixes are surgical edits in the right places, not rewrites — scope, non-goals, impact, and success criteria remain internally consistent with them.
- The 0-based↔1-based conversions are stated symmetrically in **both** directions (producer handoff and consumer fallback bridge), which closes the whole class of off-by-one ambiguity at once.
- The `(day_of_week, start_time, id)` tuple includes `id` as tiebreaker, so ordering is total — no two equal keys.
- The `week` semantics pin (item 3) and the round-1 fixes (duration-fit, pinnedOccurrence bridge, venue-book contract change) all remain intact and untouched by this round's edits.

### 💡 Optional Suggestions

- Append a brief round-2 entry (verdict BLOCKED + the four findings) to `review-log.md` — the archive trail currently shows round 1 only, which will confuse `/sdd-archive` later.
- `day_of_week` encoding (0=Sun–6, 1=Mon–7, or name) is left to design — fine, but worth one word there since it's now an ordering key and a label input.

### ⚖️ Review Verdict

All four round-2 findings are genuinely fixed in the current text; the three 🟡 resolutions are coherent and mutually consistent (array order ↔ preselection rule ↔ fallback first-compatible-occurrence; 0-based bases ↔ 1-based handoffs). The only residual item is the 🟡 date-derivation caveat, which belongs in design.md and does not block freezing the proposal.

**VERDICT: READY TO FREEZE** (🔴 list: none)
