# Explore brief — holiday-occurrence-badge (2026-10-27)

## Provenance
The investigation's related-find C4: the conflict-schedule strip advertises
an occurrence that under the holiday rules CANNOT meet. Receipts:
- Strip (live, 5425): `Week 14 · Thu, Dec 24 2026 · 12:00 PM–1:00 PM @ B010`.
- `holidays` table: W14 day 3 = "Christmas Eve", day 4 = "Christmas Day".
- The importer DROPS holiday-day classes (W14: 91 rows absent — cs 39/41
  Thu/Fri classes among them; also W8: 18 Monday classes = Deepavali
  in-lieu, 62 rows). `validateSlot` rejects holiday days server-side
  (`holidayDays()` check) — so the strip's claim is a phantom occurrence.

## The user's rulings (verbatim intent, 2026-10-27)
1. Q3 → **B**: "keep it but stamp it with a holiday badge … so the strip
   tells the truth instead of going silent".
2. Clarified: **"for Q3, i ONLY allow u to edit or add it's hovertooltip,
   other design keep-as-it"** — the ONLY sanctioned change is adding a
   hover tooltip carrying `· {Holiday label} — no class`
   (e.g. `· Christmas Eve holiday — no class`).
3. Q2 → the **Earliest-Bookable default landing stays**; `?week=` does
   NOT move the selector (no week-anchor change anywhere).

## The exact contract
- The strip occurrence line stays byte-identical visually: NO icon, no
  emoji, no extra text, no color/styling change.
- The ONLY addition: a `data-tip` on the occurrence-line element when the
  occurrence's `(week+1, dayIndex)` matches a `MockData.holidays` entry
  (`{week, dayIndex, label}` — `holidaysForJs()` shape, already shipped
  in `PAGE_DATA` on every render): tip text = `<label> — no class`.
- Normal occurrences get NO tooltip. The attribute is re-derived on every
  `renderTitleSummary()` pass (the anchor changes → the tip follows).
- No server change; `validateSlot`'s holiday-day rejection remains the
  hard backstop.

## Rejected approaches
- A (silent suppression): looks like a bug — "where did my class go".
- A visible badge icon on the line: EXPLICITLY excluded by the user's
  clarification (icons/emoji banned on the visible line).
- Holiday-aware validation of the pinned occurrence/submit: server rule
  already covers it — out of scope.

## Open questions (for design.md)
1. Which element carries the tip: the whole `subjectInfo` strip vs the
   occurrence line's own span? (Prefer the narrowest element that covers
   the `Week 14 · Thu, Dec 24 …` text.)
2. Multi-day/above-viewport tooltip mechanics = the existing
   `initDataTipTooltips()` layer — confirm it binds dynamically inserted
   attributes (it is idempotent/global; verify receipt in apply).
