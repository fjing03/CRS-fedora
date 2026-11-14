# Apply log — modal-responsive-width

> Started 2026-11-14 · apply-time HEAD **`bc99181`** · lane status before apply:
> `frozen`, `implementation_approved: false`.

## T1 — approval and receipts

- **Approval.** User, 2026-11-14: explicit yes to editing live
  `public/css/theme.css` ("yes. anything that u aint understand u can ask me ,
  before execute."). `sdd.yaml` → `implementation_approved: true`,
  `implementation_approved_at: 2026-11-14`, `apply_head: bc99181`.
- **Tree at T1.** `theme.css` clean, **5421** lines (prompt said 5405),
  `grep -c fit-content` = 0. Anchors re-derived: base `.modal` 2017, sheet block
  **4184–4240**, tablet `560px` cap **4535**, `.modal.modal--summary { max-width:
  100% }` **1920**.
- **T3b deferred by the user 2026-11-14** — `replacement-arrangement.blade.php`
  carries another session's uncommitted work; revisit after that lands. Not
  ticked, recorded in `sdd.yaml` `deferred` and in the changelog.

## Baseline → after, measured (not eyeballed)

Probe: `probes/modal-measure.mjs` (read-only, `:8030`, auth from
`tests/auth.setup.ts`). Modal opened once at 1024 then resized, 450 ms for the
`modalIn` scale animation, viewport height 900. Request Approval's queue is empty
on the testing lane, so its **shell** was force-shown (DOM only) and is reported
as a shell.

Rendered px 390 / 600 / 601 / 700 / 768 / 1024 / 1360:

| Variant | before | after |
|---|---|---|
| Class details | 350 / 560 / 561 / 660 / 680 / 680 / 680 | 390 / 600 / 420 / 420 / 420 / 420 / 420 |
| Guide | 350 / 520 / 520 / 520 / 520 / 520 / 520 | 390 / 520 / 520 / 520 / 520 / 520 / 520 |
| Summary explain | 350 / 560 / 561 / 660 / 728 / 420 / 420 | 390 / 600 / 420 / 420 / 420 / 420 / 420 |
| Cancel this Class | 350 / 560 / 560 / 560 / 560 / 560 / 588 | 390 / 560 / 420 / 420 / 420 / 420 / 420 |
| My Replacements details | 350 / 560 / 561 / 660 / 680 / 680 / 680 | 390 / 600 / 561 / 660 / 680 / 680 / 680 |
| Request Approval details (shell) | 350 / 560 / 561 / 660 / 680 / 680 / 680 | 390 / 560 / 420 / 420 / 420 / 420 / 420 |

**No pop-up grows except the sheet dock** (350 → 390 at 390px, 560 → 600 for the
two 680-ceiling sheets at 600px), which is ruling 4 ("remove the overlay padding
so the sheet docks flush") and the point of the change, not a regression.

## Decisions taken during apply (and why)

1. **Placement (§4.4).** The `min-width: 601px` block was appended **after** the
   tablet `max-width: 560px` block (line 4535), i.e. after every modal media
   query in the file. Placed earlier, either the 600-sheet block or the 560
   tablet cap would win at 601–1024.
2. **Cancel ceiling split.** Re-declaring a literal `588` inside the 601+ block
   would raise Cancel this Class from **560 → 588** in 601–1024. It is therefore
   **560** in that band and **588** only from 1025px — today's numbers exactly
   (measured: 560 at 601–1024, 588 at 1360 before the change).
3. **Summary ceiling specificity.** `.modal.modal--summary` is `max-width: 100%`
   at ≤768 (line 1920, 0,2,0), so the 601+ ceilings are declared at **0,2,0**
   (`.modal.modal--<variant>`) — a bare `.modal--summary` would lose that band.
4. **Flush sheet vs "never wider".** R4 says the sheet is full width at 600;
   R1 says no pop-up may render wider than today. For variants whose ceiling is
   below the viewport (guide 520, cancel 560, the plain approval shell 560) those
   two cannot both hold. R1 wins (it is also proposal ruling 7: each variant
   keeps its ceiling). The details/summary sheets, ceilings 680, do fill 600.
   Recorded in `review-log.md` and the changelog.
5. **My Replacements details does not shrink above 768** — measured: its
   `.modal-tabs` rail's own width is 634px, so `fit-content` legitimately reaches
   the 680 ceiling. Removing the page-local pin (the §4.3 decision) is done; a
   narrower box would need the tab rail to wrap, which is a content change and
   out of scope. Reported to the user rather than silently "fixed".
6. **Pinning is global** (`display: flex` + `overflow: hidden` on `.modal`), as
   design §5 specifies, and the sheet block's own `overflow-y: auto` became
   `hidden` so only the body scrolls.

## Files changed

- `public/css/theme.css` — sheet range 768 → **600** with `padding: 0` on the
  overlay, 48×48 `.modal-close`, footer stretch + extended full-width button set
  + `.cancel-confirm-wrap`; new `@media (min-width: 601px)` dialog block
  (`fit-content`, floor 420, per-variant ceilings, dialog restoration); new
  `@media (min-width: 1025px)` cancel ceiling; three-region pinning.
- `resources/views/livewire/replacement-home.blade.php` — the inline
  `style="max-width:680px"` on `#quickViewModal .modal` removed (only that line).
- `resources/views/livewire/request-approval.blade.php` — `#modalOverlay .modal
  { max-width: 680px }` and the page-local `@media (min-width: 1025px) { .modal
  { max-width: 680px } }` removed, comments rewritten (only those declarations).
- `tests/Feature/ModalConsistencyTest.php` — the fixed-680 test rewritten to the
  bounded invariant (floor 420 + ceiling 680 + `fit-content`, no page re-pinning,
  no raised ceiling, no fixed width). Not deleted.
- `tests/modal-responsive-width.spec.ts` — new, 9 tests.
- `page-changelogs/modal-responsive-changelog.md` — one shared entry.

## Task status

| Task | State |
|---|---|
| T1 approval | done |
| T2 width + breakpoint | done |
| T3 footer + dock + × | done |
| T3b `#confirmModal` | **deferred by the user** (see above) |
| T4 pinned regions | done |
| T5 `ModalConsistencyTest` | done |
| T6 new spec | done |
| T7 existing suites | done (baseline compared) |
| T8 changelog + supersession note | done |
| T9 verify + commit | done |
| T10 archive | waiting on the user |
