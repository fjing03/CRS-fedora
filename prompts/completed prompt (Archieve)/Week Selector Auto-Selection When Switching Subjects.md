## UI Update — Week Selector Auto-Selection When Switching Subjects

**Page:** `replacement-arrangement`  
**Staff:** 5425  
**Element:** `#weekSelector`

### Current Behaviour

When switching subjects, the Week Selector automatically selects the conflicted week.

**Example:**

1. Open:
   `replacement-arrangement?session=67&week=14&from=replacement-home`
2. Current subject: **AMSE2003 (L)**
   - Week 14, Thu, 24 Dec 2026
   - 9:00 AM–11:00 AM @ B100
   - DSF2 (S1) G1
3. Switch subject to **BMSE2163 (P)**.
4. New conflict schedule:
   - Week 14, Thu, 24 Dec 2026
   - 12:00 PM–1:00 PM @ B010
   - RSD2 (S1) G2 + RSD2 (S1) G1

**Problem:** The `#weekSelector` automatically selects Week 14 because it is the conflicted week.

### Required Behaviour

When switching subjects, update the `#weekSelector` selection using this priority:

1. **Previous Viewed Week:** Keep the week the user was viewing before switching subjects, if it is still bookable for the newly selected subject.
2. **Earliest Bookable Week:** If the previous week is no longer bookable, automatically select the earliest bookable week.

**Important:**
- Do not automatically select a conflicted week.
- Check bookability against the newly selected subject's actual booking constraints.
- Apply this behaviour when switching subjects, not just for the example above.
- Preserve the existing subject-switching and booking logic.

### Verification

Test the following scenarios:

1. Previous week is still bookable → keep that week selected.
2. Previous week is conflicted or unavailable → select the earliest bookable week.
3. Multiple weeks are conflicted → skip them when finding the earliest bookable week.
4. No bookable weeks are available → handle this correctly without selecting an unavailable week.

**Scope:** Update only the `#weekSelector` auto-selection behaviour when switching subjects. Do not modify unrelated UI or booking rules.

---

## Verified anchors (2026-11-07 night, read from the code — no edits made)

1. **Where the week jumps.** `applySubjectChange()` in
   `resources/views/livewire/replacement-arrangement.blade.php` (~line 3614–3660) auto-pins the
   *earliest conflicted occurrence* of the new subject, then calls
   `weekNav.selectWeek(firstConflictRow.week)` and `saveCurrentWeek()`. That single call is the
   behaviour this brief wants removed.
2. **It is deliberate, and pinned twice.** SDD `occurrence-week-pin-truthfulness` T18 (user
   decisions 2026-10-19/20) and `arr-slot-original-venue-sync` round 3 (user report 2026-11-01)
   both rely on the auto-pin + auto-commit. `tests/arrangement-conflict-autoselect.spec.ts`
   asserts *"the grid jumped to the occurrence's week"* — that witness must change with this brief.
3. **`?week=` does NOT land the grid.** `ARRIVAL-GRID-NO-PIN` (user law 2026-10-20): the URL pair
   `?session=…&week=…` carries which occurrence is being replaced; pinned arrivals always land on
   the **Earliest Bookable** week. So "the selector picked week 14 because the URL said 14" is not
   the mechanism, and a URL/selection mismatch after a switch is not a real risk.
4. **"Bookable" has exactly one meaning today, and it is date-only:**
   `weekHasBookableSlot(weekData, i)` in `public/js/ui-common.js` (line ~150) — not Sunday, not a
   holiday, not past the 3-working-day cut-off. It never looks at the subject, the venue or the
   conflicts, so on its own it cannot answer requirement 2 of the brief.
5. **The selector hides unbookable weeks**, and `WeekNavigator.selectWeek()` (ui-common.js ~520)
   silently steps to the nearest *visible* week when the requested one fails its `weekFilter`, and
   stays put when none is visible. A "keep this week" call must therefore be decided **before**
   calling `selectWeek()`, never inside it.

Also relevant: the "Earliest bookable" action already computes the destination —
`firstBookableDay(weekData)` → `{week, day}` — and its button/tip live in `partials/ui-week-nav`.

## Decisions still open (grill was started, then paused by the user 2026-11-07 night)

Answer Q9 = **9A**: a short `/sdd-explore` (write the anchors above down properly) →
`/sdd-propose` → apply. Lane slug: `arrangement-week-keep-on-subject-switch`.

| # | Question | Options | Recommendation |
|---|---|---|---|
| 1 | What happens to the auto-pin (pre-selected class to replace + original-room sync)? | A stop only the week jump · B also prefer a conflict inside the kept week · C drop the pre-selection | **A** (smallest change; keeps the 2026-10-19/20 + 2026-11-01 rulings) |
| 2 | What does "bookable for the new subject" mean? | A the existing date-only test · B ≥1 genuinely free cell under the CURRENT venue · C free in ANY eligible venue | **B** (matches "actual booking constraints") |
| 3 | A kept week that also holds the new subject's conflict row (normal — the original class itself paints a conflict) | A still bookable (only a week with NO free cell is unbookable) · B treat as unbookable | **A** |
| 4 | What counts as the "previous viewed week"? | A whatever the selector shows at switch time (nav / saved memory / earliest-bookable landing) · B only a week reached by hand | **A** |
| 5 | The kept week is hidden in the selector (holiday-only / past the cut-off) | A treat as unbookable → earliest bookable · B keep it and reveal it | **A** |
| 6 | No bookable week at all (late semester) | A leave the week unchanged, silent · B last week · C week 1 | **A** (silent-by-decision, the 2026-10-20 ruling) |
| 7 | Scope | A subject switch only · B also the venue switch | **A** (the brief's own scope line) |
| 8 | Receipts | A rewrite the one witness in `arrangement-conflict-autoselect.spec.ts` + add the brief's 4 scenarios there · B a new spec file | **A** |

Nothing has been implemented; no file outside this brief was touched by the grill.
