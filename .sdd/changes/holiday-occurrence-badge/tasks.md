# Tasks — holiday-occurrence-badge

## T1. The strip attribute (≤1h)
- [ ] In `renderTitleSummary()` (blade ~:2574–2609): compute
      `holidayTip` before the innerHTML assembly — the
      `(anchorSlot.week + 1, anchorSlot.day)` lookup over
      `MockData.holidays`; the literal law
      `· {label}[ holiday] — no class` (` holiday` suffix only when the
      label lacks it case-insensitive; first match wins; `— `
      assembled as the literal em-dash matching the surroundings).
- [ ] Attach `data-tip="${escHtml(holidayTip)}"` on the EXISTING
      `<span class="cs-val">` inside the existing ternary — NO wrapper,
      NO icon, NO other change; the `.cs-val.cs-none` branch gains
      nothing.
- [ ] `php -l`; the visible `textContent` of `#subjectInfo` provably
      unchanged (diff receipt at verify).

## T2. Receipts (≤1h)
- [ ] Playwright (read-only) hover receipts + screenshots:
  - W14 Thu anchor (`?session=38&week=14`): `· Christmas Eve holiday —
    no class`; screenshot `/tmp/hol-w14-thu.png` + the tooltip element
    text snippet.
  - W14 Fri anchor: `· Christmas Day holiday — no class` (the
    session/day pair picked at apply time from the live DB).
  - W8 Mon anchor (a day-0 session picked at apply time, e.g. cs 71):
    `· Deepavali Holiday (In Lieu) — no class`.
  - A non-holiday anchor: NO `data-tip` on the occurrence span.
- [ ] `#subjectInfo` `textContent` identical pre/post in the same probe.

## T3. Gates + bookkeeping (≤0.5h)
- [ ] `composer run lint:check` + phpstan (Blade-only change — expect
      0 drift) + the full suite (≥265/265, unchanged).
- [ ] Stale-serve ritual + the :8001 sibling restart before probing.
- [ ] `page-changelogs/replacement-arrangement-changelog.md` entry.
- [ ] ONE commit: `ui: the conflict-schedule occurrence's holiday truth rides a hover tooltip only`
      (no push).
