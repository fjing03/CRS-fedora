# Proposal — `venue-intent-ghost-success-spot` (the user's live bug report, 2026-10-23)

**The report:** staff 5770 — the venue-page modal "Arrange a replacement in
B002 on Fri, 30 Oct 2026 at 12:00 → AMCS2093 (L) W8, Nov 9 11:00~13:00 →
Arrange" lands on the arrangement page with the success toast
"Pre-selected from your venue booking — click the block to adjust."
**but ZERO cells pre-selected.**

**Root (this session's live receipts, `/tmp/b002-*` probes + code reads):**
1. The boot's first consume (`applyUrlParams` → onSubjectChange → the
   canPlaceBlock read) judged a PRE-REBUILD grid — the venue's REAL occupancy
   (B002 Fri 13:00–16:00 `cell-occupied`) was not painted yet, so
   canPlaceBlock returned TRUE; `selectBlock` marked success, cleared
   `pendingBookingIntent`/`bookingIntentMemory`, saved
   `selectedSlotsByVenue[B002][week5]`, and fired the success toast.
2. The boot's own `buildTimetable()` (init tail, :3578) then painted the REAL
   grid and `loadCurrentWeek()` re-restored: `selectedBlock = null`, the
   saved entry re-read — but the restore guard needs ALL span cells
   `cell-available`; Friday 13:00 is NOT (the user's FYI: 13:00–14:00 RED).
   The guard fails → silent despawn. No banner, no toast, no cells.
3. The 2e re-consume tail (`!selectedBlock && pendingBookingIntent…`)
   cannot rescue: `pendingBookingIntent` was already spent by the ghost.

**The fix (ONE strike, mirroring the trusted 2e pattern):** in the boot tail
AFTER `buildTimetable()`, before the 2e tail: if a `selectedBlock` exists,
verify its span cells against the REAL grid; on a mismatch (a ghost):
- drop the ghost (clear the saved slot entry + `selectedBlock = null`),
- **re-arm** the intent from the untouched `urlParams` facts via
  `resolveBookingIntent()` (the sessionStorage cancel rule stays honored),
- `evaluateBookingFit()` speaks the honest manual banner — WITH the kind
  attribution clause from `venue-intent-banner-kind` (a6ef02c) — + the pulse.
No new toast on any path; the success toast may fire only when the real grid
confirms the block survives. Out of scope: the occupied-vs-own wording, the
lead-time banner texts.

## Verify
- The exact repro URL: the ghost despawns + the manual banner (with the
  attribution clause) shows + the pulse runs. Cells stay unselected.
- The honest-success control (a truly placeable slot): the toast fires AND
  the cells persist (no regression on the C2/A6 placements).
