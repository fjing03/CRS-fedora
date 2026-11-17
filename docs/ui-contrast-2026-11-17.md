# Contrast audit — both themes (2026-11-17)

**Status: HELD.** Findings measured and recorded; nothing applied. The token fix waits until the
Cohort/Venue responsive port (survey P1 #2) lands, because it touches `theme.css`.

**Method.** Rendered, not eyeballed. Playwright walked every text node on seven surfaces in both
themes, resolved each element's effective background by compositing its ancestor stack, and computed
WCAG 2.1 contrast. Thresholds: 4.5:1 for normal text, 3:1 for large (≥24px, or ≥18.66px bold).
14 colour groups failed. Two were excluded after inspection (below).

**Surfaces audited** at 1440: My Timetable · My Replacements · Request Approval · Replacement
Arrangement · Class Replacements (student) · Student My Timetable · Staff login.
**Not audited: Cohort Timetable and Venue Timetable** — another session is mid-port there; re-run
this audit on both once that lands.

---

## 1. What fails

| # | Surface(s) | Element | Text | Measured | Needs |
|---|---|---|---|---|---|
| C1 | summary cards, all list pages + both timetables, **dark** | `.summary-value` 26px/700 | replacement blue `#004D98` on surface | **1.79:1** | 3:1 |
| C2 | same, **dark** | `.summary-value` | conflict red `#C62828` | **2.65:1** | 3:1 |
| C3 | same, **dark** | `.summary-value` | normal green `#2E7D5A` | **2.98:1** | 3:1 |
| C4 | My Replacements, Class Replacements, **dark** | `.urgency-high` 13px/700 | red, e.g. *Overdue (6 Working Days Ago)* | **2.65:1** | 4.5:1 |
| C5 | same, **dark** | `.urgency-low` 13px/500 | green, e.g. *26 Working Days Left* | **2.98:1** | 4.5:1 |
| C6 | summary cards, **light** | `.summary-value` 26px/700 | warning amber `#D4880F` on white | **2.87:1** | 3:1 |
| C7 | summary cards, **light** | `.summary-label` 11px/600 | grey on a tinted card, e.g. *Total Classes* | **3.68:1** | 4.5:1 |
| C8 | summary cards, **light** | `.sum-pending` | the `+0` suffix, amber on a green tint | **1.43:1** | 4.5:1 |
| C9 | filter chips, **dark** | `.filter-chip` 12px, `.filter-chip-remove` 14px | primary-container text on its own fill | 4.04 / 4.22:1 | 4.5:1 |

**Excluded, with the reason:**

- **Login "Log in" at 1.38:1** — the button is `disabled` while the form is empty, at 0.75 opacity.
  WCAG 1.4.3 exempts inactive components. Not a defect.
- **`+N` pink badge at 4.04:1** — this is the same `+0`/`+N` suffix that the critique's P3 already
  calls noise; see §3, where removing it removes two of these failures.

## 2. The pattern

Nine of nine are one mistake: **a status colour, which exists to fill a block, used as text.** The
palette already contains the passing answer for every one of them — the `on-*-container` foreground
of the same family:

| Status | Dark text value | On `--color-surface` | Light text value | On `--color-surface` |
|---|---|---|---|---|
| Replacement | `#6BA3E0` (`--color-on-primary-container`) | **5.64:1** | `#003366` | **12.6:1** |
| Normal | `#81C78A` (`--color-on-success-container`) | **7.44:1** | `#1B5E3A` | **7.75:1** |
| Conflict | `#EF9A9A` (`--color-on-error-container`) | **6.93:1** | `#8B1A1A` | **9.29:1** |
| Pending | `#DBA800` (`--color-on-tertiary-container`) | **6.83:1** | `#8A6D00` | **4.92:1** |

## 3. The fix (held)

1. **Add four text-safe status tokens**, defined per theme, each aliasing an existing passing value
   above — e.g. `--color-status-normal-text`, `--color-status-replacement-text`,
   `--color-status-pending-text`, `--color-status-conflict-text`. No new hue is invented; the
   palette gains four names, not four colours.
2. **Point three consumers at them:** `.summary-value` (C1, C2, C3, C6), the urgency labels
   `.urgency-high` / `.urgency-low` (C4, C5), and `.filter-chip` plus its remove glyph (C9).
3. **Fix `.summary-label` in light** (C7) with the muted text token already in the palette
   (`--color-on-surface-variant` on the tinted card reads 4.58:1 — verify on the real card fill,
   which is the part that measured 3.68).
4. **C8 disappears** when the `+0` suffix stops printing (critique P3) — one change, two failures
   gone.

**One gap that needs a decision, not a guess:** the **light** amber family has no text-safe value
(the darkest amber, `#8A6D00`, is the tertiary container's text). Either pending text borrows that
value in light, or the palette gains a genuine `--color-on-warning`-family text value. That is a
colour decision and belongs to the owner.

**Re-run this audit** on Cohort and Venue after the responsive port, and on all seven surfaces after
the fix, to confirm the ratios above land in the built result — the numbers here are computed from
rendered pages, so they are checkable the same way.

## 4. What this does not cover

- Cohort and Venue (in flight elsewhere).
- Any surface behind a role not in the demo data (no CSR account exists).
- Non-text contrast: borders, focus rings and the thin ownership hairline were **not** measured here.
  The 0.5px hairline is deliberate and decorative-but-meaningful; it is out of scope for the 4.5:1
  rule, but a separate non-text audit (3:1 against adjacent colour) would be honest work.
