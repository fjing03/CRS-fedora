# Venue Timetable: open room information by tap or click

Status: applied and verified locally. The independent reviewer returned HTTP 429; the owner explicitly waived review for this fix.

## Scope and acceptance

- Turn the existing room info chip into a disclosure, using the same info glyph and the current venue's served description. One tap or click opens the note; another closes it. Use the same behavior on phone, tablet and desktop, without depending on hover. No info chip for rooms with no description.
- Prefer a native disclosure within the existing room controls. Preserve the capacity chip, room picker, timetable and booking workflow. The note fits within the viewport and can wrap; the info trigger is at least 48×48px whenever a coarse pointer is present, including wide touch. Use only existing theme tokens and do not change the Arrangement page.
- Confirm the B002 description by genuine tap on phone, iPad-sized and wide touch views, including 320px; verify mouse click, open/close, no horizontal overflow, and that changing to a genuinely served room shows its own note (or no info chip when absent). Inspect light/dark renders and run static checks. No business data changes.

Baseline: `/tmp/crs-venue-critique/interactions.jsonl` shows the 32×22px icon did not disclose text on touch; mouse hovering did.

## Verification

A tap opens and closes the genuine B002 note at 320, 390, 820 and 1280px, and a click does the same at 1440px. At 820, switching to B006 closes the disclosure and tapping shows that room's own description. The full coarse-pointer target is hittable (including at both sides), the Previous week arrow no longer overlaps it at 1280, and the note fits without page overflow in both themes. The focus was read-only. The room info and earliest-jump Playwright receipts passed all 15 checks including auth. Pint and PHPStan passed. Captures and logs: `/tmp/crs-venue-room-info/`.
