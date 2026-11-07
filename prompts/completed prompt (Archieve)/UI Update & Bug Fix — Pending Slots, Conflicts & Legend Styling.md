# UI Update & Bug Fix — Pending Slots, Conflicts & Legend Styling

**Page:** `replacement-arrangement`
**Reference Pages:** `cohort-timetable` and `venue-timetable-ui`

## 1. Pending Slots — Yellow / Warning

Use the same yellow warning colour for both pending slot types. Distinguish them by border thickness only.

| Slot Type | Colour | Border |
|---|---|---:|
| Pending (You) | Yellow / Warning | 3px |
| Reserved by Others (Other's Pending) | Yellow / Warning | 0px |

## 2. Conflict Slots — Red / Error

Use the same red error colour for all conflict types. Distinguish them by border thickness only.

| Conflict Type | Colour | Border |
|---|---|---:|
| Own Conflicts | Red / Error | 3px |
| Cohort Conflicts | Red / Error | 0px |
| Venue Conflicts | Red / Error | 0px |

## 3. Legend Bar Styling

- Make the `replacement-arrangement` legend visually consistent with the existing `.legend-bar` on `cohort-timetable` and `venue-timetable-ui`.
- Reuse their existing colours, indicators, labels and visual style.
- Ensure legend indicators accurately represent the actual timetable slot styles.

## 4. Bug Fix — Incorrect Pending Slot Ownership Styling

**Problem:** Sometimes, another staff member's pending slot (`.cell-content.cell-reserved`) is incorrectly displayed with the wrong ownership styling.

**Example:** Logged in as Staff `5425`, Staff `5770`'s pending slot sometimes appears with yellow warning styling that should indicate the current user's own pending slot.

**Investigation & Fix:** Check how the system identifies the current user's own pending slots versus other staff members' pending slots; inspect ownership checks, CSS classes and rendering conditions; ensure border thickness is determined by actual ownership data, not colour alone; fix any inconsistent styling after page load, refresh or timetable updates.

## 5. Verification & Requirements

- Test Staff `5425` viewing Staff `5770`'s pending slots, and vice versa.
- Verify all pending and conflict types use the correct colours and border thickness.
- Ensure the legend and actual timetable slots always match.
- Preserve existing slot labels, booking restrictions and functionality.
- Reuse existing styling where possible.
- Avoid unrelated changes.

**Expected Visual Rule:** 3px border = current user's own pending slots and own conflicts; 0px border = other staff's pending slots, cohort conflicts and venue conflicts.

---

## Grill answers (2026-11-06)

1A ownership rides the busy tuple · 2A one resolver, not first-arm-wins · 3A the amber `tertiary`
family (not `--color-warning`) · 4A 0px is literally no border · 5A every red cell is one
"Unavailable" · 6A six legend rows with real-class swatches · 7A this page's vocabulary in the
cohort/venue style · 8A payload feature test + one browser receipt.

Lane: `.sdd/changes/arrangement-slot-ownership-legend/`.
