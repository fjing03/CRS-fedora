# Review log — modal-responsive-width

## Proposal — 2026-11-13 (draft, self-reviewed against the prototype)

Batch: `proposal.md`. Nothing frozen yet. Reviewed against the live
`theme.css`, `ModalConsistencyTest`, the prototype measurements and the seven
user rulings.

### Facts verified before writing

- `.modal` = 420px, `.modal--detail` = 680px, `.modal--cancel` = 588px,
  `.modal--guide` = 520px, `.modal--summary` = 420px.
- `ModalConsistencyTest` pins `max-width: 680px` by regex, **not** by rendering.
- `cancelled-slot-reuse` A17 says "keep 680px details width"; that lane has
  **0 tasks ticked** and no UI work started.
- No existing test asserts a rendered modal width.

### 🔴 Fixed during drafting

- **My own earlier claim was wrong.** I told the user the tablet cap was 560px.
  Measured: at 900px the details pop-up is **680**, because
  `.modal.modal--detail` outranks the generic tablet rule. Corrected in the
  proposal and design so the error is not carried into apply.
- The first prototype pass left 601–768 as a **half-sheet** — the shipped
  768 sheet rules still applied. Design §3 now mandates explicit dialog
  declarations for that band, and spec R2 pins 601/700/768.

### 🟡 Addressed

- Ceilings explicitly preserved per variant, so "all modals" cannot be read as
  "give Cancel Class a 420 cap".
- `fit-content` on paragraph bodies documented as **correct**, not a defect.
- Footer fix recorded as **two** causes (wrapper not stretching + `.btn-danger`
  missing); fixing one is called out as insufficient.
- `min-height: 0` on the body identified as the load-bearing line for pinning.
- Test rewrite framed as *rewrite*, never delete.
- Sheet flush kept safe-area inset.

### 🔴 Outstanding

- **Not yet reviewed by a second pass.** Proposal is `draft`.
- Live apply not approved; user's standing instruction is "do not edit my
  system code file first".

## Prototype trial receipt — 2026-11-13

Applied to `ui-prototype/my-timetable-mobile-preview.html` only. No live file
touched. User response: "yea good" (prototype approved).

### Measured geometry

| Width | Shape | Modal w | Side gap | Bottom gap | × | Buttons |
|---|---|---|---|---|---|---|
| 390 | sheet | 390 | 0 | 0 | 48 | 356 / 356 |
| 600 | sheet | 600 | 0 | 0 | 48 | 566 / 566 |
| 601 | dialog | 420 | 91 | 263 | 40 | 75 / 126 |
| 700 | dialog | 420 | 140 | 263 | 40 | 75 / 126 |
| 768 | dialog | 420 | 174 | 325 | 40 | 75 / 126 |
| 1024 | dialog | 420 | 302 | 509 | 40 | 75 / 126 |
| 1360 | dialog | 420 | 470 | 280 | 30 | 75 / 188 |

### Before/after (Class Details)

| Width | Before | After |
|---|---|---|
| 1360 | 680 | 420 |
| 1100 | 680 | 420 |
| 1000 | 680 | 420 |
| 900 | 680 | 420 |
| 769 | 680 | 420 |

Per tab at 1360: Class Information 420, Schedule 420, Status 478.
Per variant at 1360: details 420, cancel 540, guide 520, summary 420.

### Pinning proof (390×420, 18 injected filler rows)

`bodyScrollable: true`, `modalScrollable: false`, `bodyScrolled: true`,
`headerPinned: true`, `footerPinned: true`, `headerStillVisible: true`,
`footerStillVisible: true`, `closeStillVisible: true`.

### Not yet proven

- No live-CSS verification: everything above is the prototype's own `<style>`
  layer over the real `theme.css`, not the shared file itself.
- No run of `cancel-class`, `class-modal-affordances`,
  `session-arrival-pin-guard`, `venue-db`, `request-approval` or the
  confirmation specs against the change.
- No phone-notch/safe-area simulation.
- Dark theme not screenshotted at the new widths.

## Review Round 1 — 2026-11-13 (proposal/design/spec/tasks vs the real codebase)

Batch: all four artifacts. Nothing frozen yet. Read against
`public/css/theme.css`, `resources/views/partials/ui-*.blade.php`, the page
modals in `replacement-home`, `request-approval` and
`replacement-arrangement`, and `ModalConsistencyTest`.

### 🔴 Blockers found

**R1.1 — Source order makes the 601+ rule lose at 601–768.**
`theme.css` line 4013 opens `@media (max-width: 768px)` which sets
`.modal { width:100%; max-width:100% }`, and it closes around line 4071.
The base `.modal` is line 1926. If the new `@media (min-width: 601px)` block is
placed beside the base rule (~1944), then at 601–768 **both** media queries
apply and the *later* one wins — the phone block. The 601–768 band would stay a
sheet, exactly the bug the prototype hit. The dialog rules must be appended
**after line 4071**, or they will be silently overridden.
→ Design §3 updated with an explicit placement requirement.

**R1.2 — The full-width footer list is incomplete.**
Real footers use classes the design did not list:

| Modal | Footer buttons |
|---|---|
| `#confirmModal` (arrangement) | `btn btn-outline`, `btn btn-primary`, `btn btn-primary` |
| intent-session | `btn-close-modal`, `btn-confirm` |
| cancel-class | `btn-outline`, `btn-danger`, `btn-action`, `btn-outline` |
| request-approval | `btn-danger`, `btn-approve`, `btn-outline` |

`.btn-confirm`, `.btn-primary` and bare `.btn` were missing, so "Arrange",
"Confirm" and the confirm-dialog buttons would stay narrow on the phone.
→ Design/§4 list extended to `.btn`, `.btn-primary`, `.btn-confirm`.

**R1.3 — `#confirmModal` has its own page-local sheet block.**
`replacement-arrangement.blade.php` lines 1718–1760 (inside
`@media (max-width: 768px)`) set `body.arrangement-mobile-mode #confirmModal
.modal { overflow-y: auto }` plus a `position: sticky` header. That is a
second, more specific scroller and it contradicts both pinning and the 601
cut-off on that page. At 601–768 that page's confirm dialog would remain a
sheet with its own scroll.
→ Tasks must reconcile that block (page-local edit) or explicitly accept the
one-page exception. Decision required at apply time.

**R1.4 — `.cancel-confirm-wrap` blocks the Cancel button's stretch.**
The cancel-class footer wraps the destructive button in
`.cancel-confirm-wrap { align-items: flex-end }`, so `width:100%` on the child
will not fill the sheet — the same class of bug as the wrapper issue fixed for
`-left`/`-right`. It must join the stretch list.

### 🟡 Addressed

- **× size table was wrong.** At 769–1024 the tablet rule
  (`@media (max-width:1024px)`) sets `.modal-close { min-height:40px }`, so × is
  **40px**, not 30px. 30px only above 1024. Design §1 corrected.
- **`.modal--detail .modal-body` keeps `14px 22px 18px`** (specificity 0,2,0)
  and beats the design's generic `.modal-body { padding: 16px 22px }`. Not a
  bug, but the design's padding claim is only true for non-detail modals.
- **Inline ceilings already exist and are preserved**: `replacement-home` has
  inline `max-width:680/635/420px`, intent's slot-warning has `560px`, and
  request-approval has `#modalOverlay .modal { max-width:680px }` (ID
  specificity). These keep working as ceilings; `fit-content` is free to shrink
  under them.
- **`.modal--summary`/`--guide` are declared before `.modal`** but at higher
  specificity (0,2,0), so their 420/520 ceilings hold regardless of order.
- Confirm-dialog footers have **no** `-left`/`-right` wrappers; `align-items:
  stretch` on the column footer stretches the buttons directly. Fine.
- `.modal`'s `overflow-y: auto` becomes `overflow: hidden`; the `modalIn`
  transform animation is unaffected.

### 🔴 Outstanding

- R1.1, R1.2, R1.3, R1.4 need to be reflected in the artifacts before freeze.
- Still no second-reviewer pass and no live verification.

## Freeze — 2026-11-13

All four artifacts frozen. **No system code was edited at any point** —
verified: `grep -c "fit-content" public/css/theme.css` = **0**.

### Frozen chain

| Artifact | Review | Frozen |
|---|---|---|
| `proposal.md` | PASS (round 1 + codebase review) | yes |
| `design.md` | PASS (round 1 + 4 blockers fixed) | yes |
| `specs/modal-responsive/spec.md` | PASS | yes |
| `tasks.md` | PASS | yes |

Review Round 1 fixed four blockers before freeze:

1. **Source order** — the 601+ block must be appended after `theme.css` line
   4071, or the shipped phone block overrides it at 601–768.
2. **Footer class list** — `.btn-confirm`, `.btn-primary` and bare `.btn` were
   missing; without them "Arrange" and every confirm-dialog button stays narrow.
3. **`#confirmModal` page-local scroller** — `replacement-arrangement.blade.php`
   1718–1760 has its own `overflow-y: auto` + sticky header; new task T3b.
4. **`.cancel-confirm-wrap`** — its `align-items: flex-end` blocks the
   destructive button's stretch.

Plus two corrections to my own earlier statements: the 769–1024 × is **40px**
(not 30), and the footer bug has **three** causes (not two).

### Implementation gate — BLOCKED, by design

Live apply is **not approved** and is **blocked** on `theme.css` ownership:

- **Six lanes claim `theme.css`**: `cancelled-slot-reuse` (applying),
  `my-timetable-ui-cleanup`, `summary-card-detail-copy`,
  `student-modal-staff-parity`, `timetable-print-v2`, and this one.
- `cancelled-slot-reuse` is genuinely mid-run: `ui-common.js` modified within
  the last 30 minutes (`layoutTimetableDay`), plus new untracked backend files
  (`ReleaseOriginalOccurrenceSlots.php`, `CancelledSlotCleanupEvidence.php`,
  `CancelledSlotCleanupFingerprint.php`) and modified `CancelClassBatch.php`,
  `ReleaseRequestSlots.php`, `SubmitReplacementRequest.php`.
- Its **A17 says "Keep 680px details width"** — directly opposed to this
  change. Its A19 will append lane styles to the same file.
- `student-modal-staff-parity` is the lane that raised 616 → 680, so it owns
  the same declaration.

CSS has no semantic merge: two agents appending to `theme.css` produce
whatever the write order allows, and one can silently clobber the other.

### Resume condition

Re-check `theme.css` ownership before applying. Apply when no other lane is
actively writing that file. Then follow T1 → T10 in order.

### State at freeze

- Prototype: applied and approved ("yea good") — `ui-prototype/my-timetable-mobile-preview.html`
- System code: **untouched**
- Commits: none
- Push: none
