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

## Apply — T1 approval + baseline measurements, 2026-11-14

**Approval.** User, 2026-11-14: explicit "yes. anything that u aint understand u can
ask me , before execute." to editing live `public/css/theme.css`. Recorded in
`sdd.yaml` (`implementation_approved: true`, `implementation_approved_at`).
Apply-time HEAD: **`bc99181`** (`ui: fit the too-soon hover label and explain the
rule on the venue page`) — the prompt's `6b23cc4` was stale; the sibling session
had landed two more commits.

**Tree state at T1.** `git status --porcelain -- public/css/theme.css` **empty**;
`wc -l` = **5421** (prompt said 5405). `grep -c fit-content` = **0**.
Drifted anchors re-derived: base `.modal` **2017**, `@media (max-width: 768px)`
sheet block **4184–4240** (`.modal` at 4200), tablet `max-width: 560px` cap
**4535**, `.modal.modal--summary { max-width: 100% }` **1920**.

**T3b deferred** (user, 2026-11-14): `replacement-arrangement.blade.php` carries
another session's uncommitted work. Skipped, unticked, recorded in `sdd.yaml`
`deferred`.

### Baseline geometry — measured, not assumed

Probe: `probes/modal-measure.mjs` (read-only, `:8030`, storage states from
`tests/auth.setup.ts`). Each pop-up is opened ONCE at 1024 and then the viewport
is resized, so every width measures the same real content; the modal-in scale
animation is allowed to settle (450 ms) before reading. Heights 900.
Request Approval's queue is empty on the testing lane (staff 5770's requests were
removed after the manual-test revert), so its **shell** is force-shown and
reported as shell-only.

Rendered width `px` (max 100%…) at each viewport:

| Variant | 390 | 600 | 601 | 700 | 768 | 1024 | 1360 |
|---|---|---|---|---|---|---|---|
| Class details | 350 | 560 | 561 | 660 | 680 | 680 | 680 |
| Guide | 350 | 520 | 520 | 520 | 520 | 520 | 520 |
| Summary explain | 350 | 560 | 561 | 660 | 728 | 420 | 420 |
| Cancel this Class | 350 | 560 | 560 | 560 | 560 | 560 | 588 |
| My Replacements details (Quick View) | 350 | 560 | 561 | 660 | 680 | 680 | 680 |
| Request Approval details (shell) | 350 | 560 | 561 | 660 | 680 | 680 | 680 |

Computed `max-width` confirms the ceilings, and confirms §4.4:

- `.modal--detail` = **680px** at every width (0,2,0 outranks the 560 tablet cap)
- `.modal--cancel` = **560px** at 601–1024, **588px** at 1360 — so re-declaring a
  literal 588 inside a `min-width: 601px` block would **grow** cancel at
  601–1024. Apply keeps **560** in that band and restores **588** only from 1025.
- `.modal--guide` = 520 everywhere ≥ 600; `.modal--summary` = 100% at ≤ 768
  (so 728 at 768), 420 at ≥ 1024 — the design's flat "summary 420" is wrong below
  769, as §4.4 warned.

Sheet band before the change: `.modal-overlay` keeps `padding: 20px`, so at 390
the sheet is 350 wide with 20px side and bottom gaps (it floats, it does not
dock) — the reported defect, reproduced.

## Verify — 2026-11-14 (T9)

### Rendered widths, after (px)

| Variant | 390 | 600 | 601 | 700 | 768 | 1024 | 1360 |
|---|---|---|---|---|---|---|---|
| Class details | 390 | 600 | 420 | 420 | 420 | 420 | 420 |
| Guide | 390 | 520 | 520 | 520 | 520 | 520 | 520 |
| Summary explain | 390 | 600 | 420 | 420 | 420 | 420 | 420 |
| Cancel this Class | 390 | 560 | 420 | 420 | 420 | 420 | 420 |
| My Replacements details | 390 | 600 | 561 | 660 | 680 | 680 | 680 |
| Request Approval details (shell) | 390 | 560 | 420 | 420 | 420 | 420 | 420 |

Sheet geometry after, at 390 / 600 (class details): side gap 0 / 0, bottom gap
0 / 0, radius 14/0, × 48×48, footer buttons 340/340 and 550/550 (each pair
equal, filling the footer's own content box — my-timetable keeps a 24px footer
padding). At 601–1360: symmetric 16px radius, side gaps 91/140/174/302/470,
bottom gaps > 0, footer `row`, × 40px (601–1024) and 30px (1360).

Pinning (390×420, 18 injected filler rows): body `scrollHeight` 883 vs
`clientHeight` 152, `scrollTop` 731 after scrolling to the end; modal
`scrollTop` **0**; header y 85 and footer y 303 **unchanged**; × and footer still
on screen. No horizontal clipping anywhere (`scrollWidth <= clientWidth + 1`,
including the 561px-wide My Replacements dialog at 601 where its tab rail
shrinks from 634 to 515 with no overflow).

### §4.3 outcome (report separately, as asked)

- **Request Approval details**: 561 / 660 / 680 / 680 / 680 → **420** at 601 /
  700 / 768 / 1024 / 1360 (the page-local ID-specificity pin and the 1025+
  media block are gone).
- **My Replacements details**: 390 → 390, 600 → 600 (flush sheet); above 768 it
  still measures **680**, because the pop-up's own tab rail is 634px wide and
  `fit-content` therefore reaches the ceiling. The pin is removed; the width is
  now content-driven. Making it narrower would require the tab rail to wrap,
  which the frozen scope forbids ("tabs untouched"). Flagged to the user.

### Conflict resolved in favour of R1 (recorded, not silently reinterpreted)

R4 asks for a full-width sheet at 600; R1 forbids any pop-up growing. For the
variants whose ceiling is below the viewport (guide 520, cancel 560, the plain
Request Approval shell 560 — all capped by the tablet rule) both cannot hold.
R1 (and proposal ruling 7, "each keeps its existing ceiling") wins: those keep
**exactly today's widths** at 600. Only the 680-ceiling sheets (class details,
summary) fill 600. The new spec asserts this honestly instead of pinning a
number that would force the pop-ups wider.

### Test receipts

| Run | Result |
|---|---|
| `tests/modal-responsive-width.spec.ts` (new) | **9/9 passed** (34.9s) |
| `ModalConsistencyTest` | **14/14 passed**, 86 assertions |
| Existing modal suites (cancel-class, class-modal-affordances, session-arrival-pin-guard, venue-db, request-approval, replacement-home-qv, confirm-guards) — after | **17 failed / 26 passed / 4 skipped** (4.3m) |
| Same suites at **HEAD files** (theme.css + both blades restored, then put back) | **20 failed / 23 passed / 4 skipped** (4.2m) |

So the change removes three failures and adds none: every failure in the "after"
run is present in the baseline run too. They are data/environment failures — the
testing lane's request queue is empty after the manual-test revert (request
35/39/49-dependent specs) and the Fortify limiter (5/min) trips when several
specs log in inside one run (`page.fill('input[name="login_id"]')` timeout). Not
relaxed, not touched.

Full backend suite: **760 tests, 759 passed, 7,631 assertions** (630s). The single
error is `CancelClassBatchTest::test_a_pending_request_is_closed_with_its_class`
— `duplicate key value violates unique constraint "time_slots_no_double_book_idx"`
(venue 158, Thu 15:00, week 13). It **passes on its own** (1/1) and touches no
CSS, so it is an order-dependent fixture collision against the testing DB's
leftover rows, not this change. Recorded rather than retried into green.

### Not verified

- No real iOS/Android device: safe-area insets are read from `env()`, not
  simulated.
- 320px was added as an extra manual check only (outside the frozen acceptance
  set) and is covered by the phone sheet rules; not asserted in the spec.
- Dark theme was not screenshotted at every width (tokens only, no new colours).
