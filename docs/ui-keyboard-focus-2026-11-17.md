# Keyboard & focus audit (2026-11-17)

**Status: HELD.** Measured by driving the pages with the keyboard; nothing applied. The two fixes that
matter live in `ui-common.js` (`DetailModal`), which the Cohort/Venue port is holding.

**Method.** Tab-walked 26 focus stops per surface, comparing each element's computed outline,
box-shadow, border and background while focused against its unfocused state; then tested Escape
layering, dialog focus placement, dialog tab-trapping, focus restoration, the phone-width day chips,
positive `tabindex`, and the presence of a skip link. Three surfaces: My Replacements, My Timetable,
Replacement Arrangement — plus the details pop-up and the print dialog.

---

## What holds up

- **Visible focus is real, everywhere tested.** 78 tab stops across three surfaces, **zero** without a
  visible change. The focus-ring discipline in the design file is not aspirational; it ships.
- **No positive `tabindex`** anywhere — tab order is DOM order, which is the robust choice.
- **One Escape closes one layer.** Verified on the print dialog and the details pop-up. The rule the
  design file states is true.
- **The phone day chips survive a week change.** Focus lands back on the week arrow, inside the grid,
  with the element still in the document — the failure mode I expected (focus lost to `<body>` after a
  DOM rebuild) does **not** happen here.

## What fails

### K1 — The details pop-up opens with focus still on the page, and never traps it. *(P1)*

| Check | Result |
|---|---|
| Focus moved into the dialog on open | **no** — `activeElement` is `<body>` |
| Tabs that left the dialog (of 14) | **12** |
| Focus restored to the row that opened it, after Escape | **no** — back to `<body>` |

The pop-up declares `aria-modal="true"`, but behaves like an overlay: a keyboard user opens it and
their next Tab goes into the page *behind* it, and closing it drops them at the top of the document.
A screen-reader user is told the rest of the page is inert while the keyboard can still reach it —
that contradiction is the defect.

*Fix:* on open, focus the dialog's first control (the close button or first tab); trap Tab/Shift-Tab
inside; on close, return focus to the element that opened it. Three small changes in `DetailModal`,
the same three every modal needs.

### K2 — No skip link; every page starts with eight global stops. *(P2)*

The first eight Tab stops on every surface are the five nav items, the theme toggle, the bell and the
logout button — before any page content. There is no skip-to-content link.

*Fix:* one focus-visible-only link at the top of the layout that jumps to `.lw-page`. It costs a line
of markup and a rule that reveals it on focus.

### K3 — Closing a layer does not return focus. *(P2, same fix as K1)*

Related but worth separating, because it also affects the print dialog: after Escape, focus is on
`<body>`, so the user re-walks the page to get back where they were.

## What this does not cover

- Keyboard **shortcuts** were not exercised (the arrangement page claims arrows, `[` `]`, Ctrl+Z and a
  `?` overlay); that is behaviour, not focus, and belongs with a functional pass.
- Screen-reader output (names, roles, announcements) was not tested — only focus.
- Cohort and Venue were not audited (in flight elsewhere).
- Touch-only affordances were not tested for keyboard parity.
