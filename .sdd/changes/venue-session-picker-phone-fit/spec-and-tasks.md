# Venue Timetable: fit Select a Session on phones

Status: applied and verified locally. The independent reviewer returned HTTP 429; the owner explicitly waived review for this fix.

## Scope and acceptance

- Limit changes to the Venue Timetable's Select a Session picker. Preserve the existing modal shell, copy, warning, timetable layout/breakpoints, session selection, and Arrange handoff.
- Constrain Subject and Session value columns so the controls can shrink. On narrow phones, stack their labels above the fields. Keep the full selected occurrence (type, week, date and time range) readable by wrapping the Session label, with the chevron always in view. The open Session menu and its options must also fit, still open upwards in the small sheet, and be usable by touch. Maintain the coarse-pointer 48px target floor for the touched form controls.
- Reuse the existing partial and CSS, scoped by `#intentPickers`; avoid shared modal geometry changes and new dependencies. Preserve the side-by-side form rows at 600px and wider.
- On protected testing, reproduce the existing 320/360/390/430 clipping with the live read-only B002 flow. Check those sizes plus tablet/desktop, short-height phone, and light/dark. Verify the trigger, chevron, selected text and open menu fit; tap a real option, then Cancel without confirming an arrangement. Run static checks and record the page changelog. No database or timetable behavior changes.

## Verification

Baseline at 390px: the Session trigger started at x=167 and ended at x=484 (`/tmp/crs-venue-critique/modal-fit.txt`, `modal-fit-390.png`). With the fix, the trigger, full label, chevron and open option fit and could be used at 320×568, 360, 390, 430, 600, 820 and 1440px across both themes. Subject and Session stack at ≤480px and remain side by side with their labels on wider views. The small-sheet menu still opens upward. Seven feature checks plus four authentication checks passed. Pint and PHPStan passed. Screenshots and logs: `/tmp/crs-venue-session-fit/`. The check ended at Cancel; there was no arrangement submission or business data change.
