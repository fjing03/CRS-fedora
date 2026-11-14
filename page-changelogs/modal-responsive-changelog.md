## 2026-11-14 — the two arrows in the pop-up now line up

The Subject and Session controls are the same width and share a right edge, but
their arrows did not line up: the select drew its chevron 12px inside the border
while the session picker padded 24px, so the two arrows sat **13px apart**
(measured). The picker's right padding is now 12px, matching the select, and the
two chevrons are the same 10×6 shape at the same inset.

Checked in a real browser at 1440 (both themes): both arrows 13px from the outer
edge, boxes unchanged in width and height. `ModalConsistencyTest` 16/16 with the
inset pinned.

## 2026-11-14 — the Select a Session pop-up: list no longer cut off, one arrow

Two defects the user found in the shared "Select a Session" pop-up.

**The session list was cut off.** Opening the picker showed one row and hid the
rest behind the pop-up's edge. Cause: the pinned layout added earlier today makes
the pop-up clip its own body, and the list is an absolutely positioned layer
inside that body — measured 53px hidden. While the picker is open, both clippers
step aside, so every row is visible and clickable; closing restores them exactly
as they were. On a phone the sheet sits flush on the screen edge, so there the list
opens **upward** instead — measured at 390: both rows fully on screen.

**Two different arrows on one form.** The Subject control is a native select, so the
browser drew its own arrow at the padding edge while the Session picker beside it
drew a custom chevron inset 12px. Inside a pop-up both controls now draw the same
10×6 chevron at the same inset, reusing the stylesheet's existing (sanctioned)
arrow — no new colour, light theme included. Page-level filter selects are
untouched, because the rule is scoped to pop-ups.

Checked in a real browser at 1440 (dark and light) and 390: every picker row
visible, the two arrows identical in shape and inset, nothing else moved.
`ModalConsistencyTest` **16/16** with the two new pins.

## 2026-11-14 — the facts strip stops leaving an empty gap

Details pop-ups still showed a dead strip of empty space beside their short rows.
The cause was the grey facts box at the top (Staff · Cohorts · Students ·
Conflict): held on one line, it wants about 900px, so it pushed the pop-up to its
**680px ceiling** while the rows underneath only needed a few hundred.

The strip now takes the pop-up's width without deciding it. The pop-up sizes to
its tabs and rows, and the grey box's items wrap onto a second row when they must
(that is the approved behaviour). Measured on a clone of the real Request Details
pop-up, same content: **626px → 513px**, with the strip still spanning the box.
Class and slot details have no strip, so they do not move.

One maximum was raised in the same breath, as approved: **Confirm Cancellation**
`635` → `680`, matching the shared ceiling. It is still a maximum, so it only
grows if its own content is wider than 635.

Correction worth recording: **Select a Session was never the 560 pop-up** — it
already uses the shared details style, so its ceiling is already 680. The `560`
lived on `#slotWarningOverlay` ("Slot Too Short"), a different pop-up in the same
file; the user then raised that one to `680` too (2026-11-14), so every pop-up in
that partial now carries the shared ceiling.

Checks: `ModalConsistencyTest` **15/15**, 92 assertions (the strip rule, the two
shells, no re-pinned ceiling). No content, wording, colour or database change.

## 2026-11-14 — pop-ups size to their content, and the sheet is phones only

Every details pop-up used to be pinned at **680px** whatever it held, and a
768px tablet got a phone bottom sheet with 591px of empty overlay above it. Both
are gone. **680px is now a ceiling, not a fixed width.**

Above 600px a pop-up is a centred dialog that takes `width: fit-content` under a
**420px floor** and its own existing ceiling. Below 601px it is the bottom
sheet — and only there. Between 601 and 768 the pop-up is now a normal dialog
with symmetric corners and a one-row footer; the page behind it keeps its own
768px layout, which is deliberate.

Widths measured in a real browser before and after (viewport → px):

| Pop-up | 390 | 600 | 601 | 700 | 768 | 1024 | 1360 |
|---|---|---|---|---|---|---|---|
| Class details | 350 → **390** | 560 → **600** | 561 → **420** | 660 → **420** | 680 → **420** | 680 → **420** | 680 → **420** |
| Summary explain | 350 → **390** | 560 → **600** | 561 → **420** | 660 → **420** | 728 → **420** | 420 → 420 | 420 → 420 |
| Guide | 350 → **390** | 520 → 520 | 520 → 520 | 520 → 520 | 520 → 520 | 520 → 520 | 520 → 520 |
| Cancel this Class | 350 → **390** | 560 → 560 | 560 → **420** | 560 → **420** | 560 → **420** | 560 → **420** | 588 → **420** |
| My Replacements details | 350 → **390** | 560 → **600** | 561 → 561 | 660 → 660 | 680 → 680 | 680 → 680 | 680 → 680 |
| Request Approval details | 350 → **390** | 560 → 560 | 561 → **420** | 660 → **420** | 680 → **420** | 680 → **420** | 680 → **420** |

At 390 and 600 the sheets now **dock flush**: side gap 0, bottom gap 0, top
radius only. The phone footer finally fills the sheet (the two wrappers did not
stretch, three button families were missing from the full-width list, and the
destructive Cancel sat inside a wrapper that right-aligned), the × is a 48×48
target, and the header and footer are **pinned** — with long content only the
body scrolls, so the title, the × and Close/Arrange never leave the screen.

Two things worth stating plainly:

- **My Replacements details still reads 680 above 768.** Its tab rail's own
  content needs ~634px, so `fit-content` legitimately reaches the ceiling. The
  page-local 680px pin is gone (that is what this change removed); whether that
  rail should wrap into two rows is a content decision, not this one.
- **A narrow-ceiling pop-up is not stretched to full width at 600.** Guide (520),
  Cancel this Class (560 in the 601–1024 band, 588 above) and the Request
  Approval shell (560, the tablet cap) keep exactly today's widths — widening
  them to the viewport would grow pop-ups this change promised never to grow.
  The reference details sheet, whose ceiling is 680, is the one that fills 600.

The arrangement page's confirm dialog is the known exception: it keeps its own
page-local sheet rules for now, so on that one page the pop-up stays a sheet at
601–768. That reconciliation is deferred by the user (the file carries another
session's uncommitted work) and is recorded in the lane's `sdd.yaml`.

The `cancelled-slot-reuse` note A17 ("keep 680px details width") is superseded by
this entry: 680 is the ceiling, not the width. That lane's frozen files were not
edited.

Checks: new `tests/modal-responsive-width.spec.ts` **9/9** (R1–R6 at 390 / 600 /
601 / 700 / 768 / 1024 / 1360, plus the 390×420 pinned-scroll case and both
pages whose 680 pins were removed); `ModalConsistencyTest` rewritten to the
bounded invariant, **14/14**; the existing modal suites went from **20 failed /
23 passed** at HEAD to **17 failed / 26 passed** — the same 17 data-dependent
failures remain (the testing lane's request queue is empty and the Fortify login
limiter trips on long runs), and three cancel-class cases now pass. Full backend
suite: **760 tests, 759 passed**; the one error
(`CancelClassBatchTest::test_a_pending_request_is_closed_with_its_class`,
duplicate `time_slots` key) is an order-dependent fixture collision that passes
on its own and predates this change. No database, route, dependency or content
change; colours untouched.
