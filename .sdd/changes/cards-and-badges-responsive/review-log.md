# Review log — cards-and-badges-responsive

## 2026-11-17 — round 1 (compact lane, single artifact)

Reviewer: `commandcode/z-ai/glm-5.3-flash`, read-only, against the brief + the live code.
Verdict **not ready**: 4 × 🔴 blocking, 10 × 🟡, 2 × 💡.

Blockers, and what they changed:

1. **T4 was wrong twice.** I claimed "one shared string, so all three pages or none". In fact
   `filters-button` (frozen 2026-11-15) ruled the pill on the **two staff pages** with
   `Showing X of Y …`, and **no count at all on the student page**; `updateResultCount` is
   parameterised; the student page has no `#resultCount`; and the brief grants the new wording to
   the student page only. T4 is split: **T4a** builds the student pill as new work with a new count
   source, **T4b** leaves the staff pills alone and puts the wording question to the user.
2. **AC3/AC4 were unachievable** while `theme.css:5065-5076` still carries the pre-2026-11-15,
   **width**-gated 40px badge floor that `my-coding-rules.md` #9 superseded. New decision **T7**:
   remove `.badge` from it, move `.page-btn` into the capability query, and record the visible
   consequence (narrow mouse windows lose the 40px badges).
3. **T11b contradicted the brief's placement.** The brief says the touch block goes in each page's
   own `<style>`; I had put an **unscoped** `button, select, input` block in `theme.css`, which would
   have reached the nav bar, the bell and every other page. T11b now follows the brief and is
   scoped per page.
4. **AC5's −3px fought the shipped card's −2px.** The drawing disagrees with itself. The lane now
   settles on one number (−3px) and updates the card rule, instead of shipping an AC that a
   higher-specificity selector would silently defeat.

Also taken: `max-width: 100%` in the badge values (long cohort codes), `var(--radius-pill)` instead
of a literal `999px`, the badge-consumer audit (`.badge-sm`, `.badge-capacity`, the stale comment at
`theme.css:5897`), the real-box AC (`elementFromPoint`), "no type size moves", "print unaffected",
keyboard open, the brief's measured card heights, the regression widths corrected from 1440 to
**1600**, and AC4 named against all three pages with a measurement method.
