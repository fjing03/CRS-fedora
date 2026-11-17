---
name: Class Replacement System
description: A lecturer-facing tool for moving a class that cannot run, plus the read-only timetables that show the result.
colors:
  bg: "#0D1B2A"
  surface: "#1B2838"
  surface-variant: "#243447"
  on-surface: "#E0E6ED"
  on-surface-variant: "#9EAAB8"
  primary: "#004D98"
  primary-container: "rgba(0, 77, 152, 0.35)"
  on-primary-container: "#6BA3E0"
  secondary: "#A50044"
  tertiary: "#DBA800"
  tertiary-container: "rgba(219, 168, 0, 0.35)"
  error: "#C62828"
  error-container: "rgba(198, 40, 40, 0.35)"
  success: "#2E7D5A"
  success-container: "rgba(46, 125, 90, 0.35)"
  warning: "#D4880F"
  outline: "rgba(155, 170, 190, 0.20)"
  outline-strong: "rgba(155, 170, 190, 0.35)"
  paper-bg: "#FFFFFF"
  paper-surface-variant: "#E4E8EE"
  paper-normal-border: "#2E7D5A"
  paper-replacement-border: "#004D98"
  paper-conflict-border: "#C62828"
typography:
  page-title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1.2
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.45
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "11px"
    fontWeight: 600
    lineHeight: 1.3
  micro:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "9px"
    fontWeight: 500
    lineHeight: 1.25
rounded:
  xs: "4px"
  sm: "8px"
  md: "10px"
  lg: "12px"
  xl: "14px"
  xl2: "16px"
  pill: "999px"
components:
  badge:
    backgroundColor: "{colors.surface-variant}"
    textColor: "{colors.on-surface-variant}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "3px 10px"
    height: "22px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.lg}"
    padding: "14px 16px"
  modal:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.xl2}"
    padding: "18px 22px"
    width: "420–680px"
  filter-chip:
    backgroundColor: "{colors.primary-container}"
    textColor: "{colors.on-primary-container}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "3px 10px"
    height: "32px"
  event-block-mine:
    backgroundColor: "{colors.success-container}"
    rounded: "{rounded.sm}"
  event-block-others:
    backgroundColor: "{colors.success-container}"
    rounded: "{rounded.sm}"
  print-block:
    backgroundColor: "{colors.paper-bg}"
    textColor: "{colors.on-surface}"
    rounded: "0"
    padding: "4px 5px"
  print-day:
    backgroundColor: "{colors.paper-surface-variant}"
    textColor: "{colors.on-surface}"
    width: "78px"
---

## Overview

An internal faculty tool for TAR UMT (Sabah): a lecturer arranges a replacement for a class that
cannot run, a programme leader approves it, and a student reads the result. Dark theme is the
default; a light theme mirrors every token. The visual language is deliberately **macOS-flavoured
institutional**: quiet surfaces, one saturated accent, macOS-style layered shadows, generous
radius, and a single two-axis grammar on every timetable (colour = status, border weight =
ownership).

The product's defining rule is that **the interface proves the slot instead of promising it**, so
the palette's job is never decoration — green/blue/red/amber always mean a class status, and the
only saturated large areas on the site are the timetable blocks themselves.

Everything below is extracted from `public/css/theme.css` and the shipped components. No visual
decision here is new; this file records what exists.

## Colors

Semantic roles, never raw hues. Always reference a token — a hex in a component is a bug.

- **Surfaces**: `--color-bg` (page), `--color-surface` (cards, modals), `--color-surface-variant`
  (inputs, chips, banded rows, table headers). Dark is the default; `.light` overrides the same
  names, so a component written against tokens is correct in both themes with no extra rules.
- **Status vocabulary** (the only place saturated colour appears): **success/green** = normal class
  and *available*; **primary/blue** = replacement, selection, and any primary action;
  **tertiary/amber** = pending; **error/red** = conflict *and* public holiday, deliberately the same
  colour because both mean "this class cannot run"; **secondary** exists in the palette and is
  reserved, not used for status. Each has a `-container` fill (low alpha) and an `on-` foreground.
- **Outlines**: `--color-outline` for hairlines, `--color-outline-strong` for the ownership world
  (see Components) and for a chip's border.
- **The paper palette is separate and fixed**: `--p-*` tokens are light in *both* themes, because
  paper is paper. Never derive a print colour from a screen token.

## Typography

One family, system-first (`-apple-system`, `Inter`, `Segoe UI`, …). No display face; this is product
UI, and a second family would be noise.

A deliberately tight scale, measured from the shipped CSS: **22** page title, **15/14** section and
card headings, **13** body and control text, **12** dense table text, **11** labels and metadata,
**10** micro-labels, **9/8** paper-only notes. Weight carries hierarchy more than size: 700 for
titles and status, 600 for values and labels, 400–500 for prose.

Rules that hold: titles 22px/700, no display sizes anywhere; tabular numbers stay tabular; the
printed sheet uses its own 8px–11px steps and never borrows a screen size.

## Layout

- **Content is a centred column inside `.lw-page`**, with the top bar fixed at 56px.
- **Spacing rhythm is 8-based** (4 / 6 / 8 / 10 / 12 / 14 / 16 / 20). There are no `--spacing-*`
  tokens; the rhythm is observed in the components, not centralised — worth promoting if a third
  page ever needs the same gaps.
- **Breakpoints**: **767/768** phone↔tablet, **600** card↔two-column cards, **1024** tablet→desktop
  touch sizes, **1280** the desktop timetable and its print button. Responsive behaviour is
  structural — grids collapse, chips become a sheet, tables become cards — never fluid typography.
- **Touch sizes are gated on capability, not width** (`@media (any-pointer: coarse)`): 48 × 48 for
  real controls, never for badges, and never width-gated. A mouse-only window keeps compact sizes at
  every width.
- **Two surfaces are exceptions with their own rules**: the timetable's `.tt-responsive` phone
  window (day chips + one day + a summary sheet) and the paper (A4 landscape, 8 mm, its own width).

## Elevation & Depth

Three macOS-style shadow steps only: `--shadow-sm` (resting cards), `--shadow-md` (hover, popovers),
`--shadow-lg` (sheets). Every shadow carries an offset and a soft blur — a zero-offset coloured halo
is not part of this system. Depth is used sparingly: the surface's own contrast does most of the
work, and a hover raises a card by one step, not three.

Modals and bottom sheets sit above a `--color-scrim` overlay. Layering is ordered by z-index with
named families (sheets 1200, an upper dialog 1400, tooltips 9999), and **one Escape closes one
layer** — that ordering is a behavioural rule the CSS must preserve.

## Shapes

Radius follows the token ladder: 4 (progress, tiny), 8 (buttons, blocks), 10 (inputs, dropdowns),
12 (cards), 14 (large panels), 16 (modals, sheets), 999 (badges, chips, toggles). Nothing sharp except
paper.

The **two-axis grammar** is the system's one piece of pure form language, and it must not drift:

- **Colour = status** — green normal/available, blue replacement/selection, amber pending, red
  conflict *or* public holiday.
- **Border weight = ownership** — **3px** for your own class, **0.5px** hairline for everyone else's,
  on the same fill. On paper the pair becomes **2px / 1px**, because hairline disappears in print.
- The replacement-arrangement grid is the documented exception: no ownership ring, colour alone says
  "cannot book", and its legend has four rows.

Bars are pills; nothing else is fully rounded.

## Components

- **Badge / status pill** — one shape for every text badge: `inline-flex`, centred, `border-box`,
  **min-height 22px**, `3px 10px`, pill radius, 12px/600. Filled and outlined badges must stand at the
  same height (the outline's border is inside the box). Badges are never touch targets and never
  inherit the 48px floor. A status pill is compact at every width.
- **Card** — `--color-surface`, 1px `--color-outline`, `--radius-lg`, `14px 16px`; hover raises to
  `--shadow-sm`. On a phone a card is the click target (`role="button"`, keyboard reachable).
- **Pop-up (modal)** — surface, 1px outline, `--radius-2xl`, one scrim, header pinned / body scrolls
  / footer pinned, width ceiling **680px** with a **420px** floor above 600px viewport, full-width
  sheet below it, 48px close on touch. One filled primary per footer, and **Close is never the filled
  primary**. Short tab labels; one fact per row in a 112px label column; the strip is badge-led.
- **Filter chip** — `--color-primary-container` fill, pill radius, a ~26px "×" inside it (the chip,
  not the ×, is the target). On a phone the chip row is deliberately **32px** tall rather than 48, a
  measured trade that keeps the row short.
- **Week row** — arrows (48px on touch), the week select, Today; the same component on every
  timetable page, and the row is load-bearing (its width decides whether a week label truncates).
- **Event block** — status fill + ownership border (see Shapes), time and facts inside, hover tip as
  a supplement and never as the only home of a fact.
- **Print sheet** — its own header, its own table, its own three-chip key at the **bottom**; 12-hour
  axis, day strip **78px**, tinted header band, day cells on `--p-surface-variant`, pending and
  cancelled never printed, and the sheet is a second table built by the same builder — not a
  restyled screen.

## Do's and Don'ts

**Do**

- Reach for a token; if a value is missing, add the token before adding the hex.
- Keep one grammar: status in colour, ownership in border weight, on every timetable surface.
- Gate touch size on `(any-pointer: coarse)` and keep mouse geometry untouched.
- Make the interface prove a slot (✓/⚠, blocked cells, disabled actions with their reason).
- Put a fact where the decision happens; use hover only as a supplement.
- Test both themes and, for anything printable, the paper palette too.

**Don't**

- Don't introduce a second badge, toggle, pop-up or chip design "just here".
- Don't use saturated colour for decoration, or red for anything except conflict/holiday/error.
- Don't gate a touch rule on width, and don't give a badge a 48px floor.
- Don't ship a hover-only affordance on a surface a touch device can reach.
- Don't style a screen element for print, or a print element for screen.
- Don't add a dependency, a font, or an icon set; the icon vocabulary is authored SVG in one stroke.
- Don't rewrite a prototype's look in a lane without recording why — the drawings are the reference,
  and their comments carry the intent.
