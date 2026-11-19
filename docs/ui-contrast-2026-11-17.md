# Contrast audit — both themes (2026-11-17)

**Status: PARTIALLY APPLIED (2026-11-19).** The live Summary Card numeric slice (C1–C3, C6, and
positive C8) is applied and browser-verified, including Cohort and Venue. Dark countdown text (C4–C5)
is also fixed: on both replacement lists it measures 6.93:1 red and 7.44:1 green on tables,
phone cards and details, and 5.89:1 / 6.32:1 on hovered rows. C7, C9 and control-boundary
findings remain open. The original measurements below are the baseline.

The owner approved a darker light-theme amber made from two existing foreground tokens
(`--color-on-warning-container` and `--color-on-tertiary`). Dark pending keeps its existing amber.
No status families, fills, borders or counts changed. The selected dark modal tab was fixed separately.

Verification: 28 Chromium contexts (seven pages × 1440 mouse/390 touch × light/dark).
All 140 card values and 52 positive-suffix samples met the size-specific targets. Dark Venue blue
1.79:1 → 5.64:1; light Pending 2.87:1 → 7.76:1; light `+N` on a tinted Total card 1.43:1 → 5.06:1.
Lowest measured final suffix was 4.76:1 (dark). Request Approval was checked with its existing
empty shell revealed in the browser; missing positive suffixes were sampled in the browser only.
See `.sdd/changes/summary-numeric-contrast/spec-and-tasks.md`.

**2026-11-17, later — the stated hold reason has expired.** That port landed (`e386106`, receipt
33 passed), so nothing now blocks §3 except the one owner decision in it (light amber has no
text-safe value). Two amendments while re-reading:

- **`.modal-tab.active` in dark is C1, not a tenth finding.** Measured rgb(0,77,152) on
  rgb(27,40,56) — i.e. `--color-primary` `#004D98` on `--color-surface` `#1B2838`, C1's exact pair at
  **1.79:1**. `theme.css:3005` sends the label to `--color-primary`, where C1 sends `.summary-value`
  to the same token. It joins the same fix (step 2 gains a fourth consumer); do not patch the tab alone.
- **C8 is the critique's `+0`** (`docs/critique-ledger-2026-11-17.md` already pairs them) — one change
  retires both the noise and the 1.43:1 failure.

The port unblocked re-measuring Cohort and Venue. The 2026-11-19 numeric slice above now covers
both pages' Summary Card values; the broader follow-up remains outstanding.

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
| C8 | summary cards, **light** | `.sum-pending` | the `+0` suffix, amber on a green tint | **1.43:1** | 4.5:1 (small suffix) |
| C9 | filter chips, **dark** | `.filter-chip` 12px, `.filter-chip-remove` 14px | primary-container text on its own fill | 4.04 / 4.22:1 | 4.5:1 |

**Excluded, with the reason:**

- **Login "Log in" at 1.38:1** — the button is `disabled` while the form is empty, at 0.75 opacity.
  WCAG 1.4.3 exempts inactive components. Not a defect.
- **`+N` at 4.04:1** — the old exclusion was incorrect. The suffix has its own font size and weight
  (13.64–16.12px/600 on the tested live flip cards), so its target is 4.5:1. Count it under C8.

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

## 3. The original plan (numeric slice applied; other consumers held)

1. **Add four text-safe status tokens**, defined per theme, each aliasing an existing passing value
   above — e.g. `--color-status-normal-text`, `--color-status-replacement-text`,
   `--color-status-pending-text`, `--color-status-conflict-text`. No new hue is invented; the
   palette gains four names, not four colours.
2. **Point three consumers at them:** `.summary-value` (C1, C2, C3, C6), the urgency labels
   `.urgency-high` / `.urgency-low` (C4, C5), and `.filter-chip` plus its remove glyph (C9).
3. **Fix `.summary-label` in light** (C7) with the muted text token already in the palette
   (`--color-on-surface-variant` on the tinted card reads 4.58:1 — verify on the real card fill,
   which is the part that measured 3.68).
4. **C8's zero case disappears** with the `summary-zero-pending` lane (2026-11-17, another session) —
   but note what that lane does **not** do: it *preserves* positive suffixes, and `.sum-pending` is
   `--color-tertiary` (amber) in **both** themes. So a positive `+N` in the light theme still paints
   amber on the card and measured 1.43:1 on the light tinted Total card. The zero-suffix work did
   not close positive C8; the approved pending foreground in the numeric slice does.

**Light amber decision resolved for card numbers and positive suffixes:** the existing warning
foreground alone reached only 3.94:1 on the light tinted Total card. The approved darker blend
reaches 5.06:1 there. This does not change warning or pending colours elsewhere.

**Re-run this audit** on Cohort and Venue after the responsive port, and on all seven surfaces after
the fix, to confirm the ratios above land in the built result — the numbers here are computed from
rendered pages, so they are checkable the same way.

## 4. What this does not cover

- Cohort and Venue outside the Summary Card numeric slice verified on 2026-11-19.
- Any surface behind a role not in the demo data (no CSR account exists).
- Non-text contrast: borders, focus rings and the thin ownership hairline were **not** measured here.
  The 0.5px hairline is deliberate and decorative-but-meaningful; it is out of scope for the 4.5:1
  rule, but a separate non-text audit (3:1 against adjacent colour) would be honest work.

---

# Non-text contrast (same audit, 2026-11-17 — also HELD)

Same measure, second rule: **WCAG 1.4.11**, which asks 3:1 for the boundary of a control *against the
background behind it* — not against the control's own fill. The first pass compared border to fill and
was wrong; these numbers are the corrected ones.

## Real failures

| Element | Light | Dark | Needs |
|---|---|---|---|
| `.search-input` (search field) | fill **1.10:1**, border **1.24:1** | ≈1.2–1.5:1 | 3:1 |
| `#statusFilter` (status select) | fill **1.10:1**, border **1.24:1** | ≈1.2–1.5:1 | 3:1 |
| `.rpp-select` (Rows select) | fill **1.11:1**, border **1.24:1** | ≈1.2–1.5:1 | 3:1 |
| `.filters-btn` | border **1.24:1** | **1.44:1** | 3:1 |
| `.week-select` (week picker) | border **1.24:1** | **1.44:1** | 3:1 |

**One problem, not five:** the neutral fill (`--color-surface-variant`) and the hairline outline
(`--color-outline`) are only ~1.1–1.5:1 away from the surface they sit on, so a field's *edge* is
almost invisible — in both themes. A user with low vision can't see where the input begins; the
label above it is doing all the work. The fix is a token decision: give **form controls** their own
border token at `--color-outline-strong` or darker (which measures 1.9–2.3:1 — still short, so it
needs a genuine new value), or rely on a filled control whose fill reaches 3:1 against the page.
This is the one finding that needs a real colour decision, not an alias.

## Checked and dismissed — with the reason

- **`.page-btn`, `.summary-info`, `.filter-chip-remove` at 1:1** — these are *bare* controls with no
  fill and no border; they are identified by their glyph and label, and 1.4.11 does not demand a
  boundary when something else identifies the control. The `×` glyph itself measures 4.22:1 (see C9),
  which is a text-level miss, not a boundary one.
- **`.event-block` border at 2.98:1 against the cell** — ownership is carried by *weight* (3px vs a
  0.5px hairline), not by colour, and the block is identified by its fill and its text. Watching
  item, not a failure. The 0.5px hairline is deliberate and sits outside 1.4.11's scope.
- **`.filter-chip` fill at 1.5:1 against the chip bar** — the chip is named by its own text, which
  passes once C9 is fixed.

## Known gap in this section

**Icon glyph colour was not measured separately** except the `×` (4.22:1). Icon-only controls
(`.summary-info`, the guide "?", week arrows, the theme toggle) carry meaning through their glyph, so
they are non-text contrast subjects in their own right and belong in the re-run.

**Still to do for non-text contrast:** re-measure Cohort and Venue control boundaries; re-measure after any boundary-token fix; add icon glyphs. No non-text fix has been applied.
