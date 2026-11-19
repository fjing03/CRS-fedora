# Venue Timetable: make the legend easier to find and read

Status: applied and verified. The owner approved the batch-specific review waiver after the independent reviewer returned HTTP 429 without a verdict.

## Scope and acceptance

- Below 1280px, rename the existing Venue sheet opener and title to **Legend & summary** so customers can find the colour key without looking under a heading that only says summary. Keep one legend and the existing sheet, subtitle, counts and grid. At 1280px and above, keep the inline legend.
- Use Venue-only helper copy, **Hover over a legend item to see what it means.** on mouse-only and **Tap a legend item to see what it means.** when a coarse pointer is available. Keep the existing swatches, status names, meanings and tooltip interactions. A baseline tap really opens the existing Available tip, but it is 741px wide in a 320px viewport. Let Venue legend tips opt in to wrapping within the viewport; do not change unrelated tips. Remove the 50% opacity from the Venue helper only; measure at least 4.5:1 in light and dark as a readability yardstick. Do not change Summary Card colours or other pages' copy.
- Reuse the existing optional shared partial parameters pattern, selectors and theme tokens. Confirm the new label fits at 320px, the sheet opens with genuine touch, the inline key remains on desktop, and the correct hover/tap guidance works. Check one non-Venue caller stays unchanged, plus print and Earliest bookable receipts. No business data changes.

Baseline: the desktop legend helper measured 2.10:1 light and 2.67:1 dark (`/tmp/crs-venue-critique/contrast.txt`); below 1280px the legend lived under the **Week summary** caption.

## Verification

The helper now measures 5.64:1 light and 6.32:1 dark. Chromium verified 320/390/820/1280px touch and 1440px mouse, including the full Available tip, the single relocated legend below 1280px, the inline legend above, and no wrap setting carried over to the capacity tip. Cohort retains its existing wording. All 18 scoped browser tests passed (excluding repeated auth setup), covering contrast, Earliest bookable, both summary sheets and print isolation. Pint, PHPStan and JS syntax checks passed. Captures and logs: `/tmp/crs-venue-legend/`.
