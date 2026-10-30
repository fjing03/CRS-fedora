# tasks — `submission-conflict-modal-refresh`

- [ ] **T1 (the keyword classes + the error face, theme.css):**
  - `.go-keyword` = `color: var(--color-success); font-weight: 700;
    text-transform: uppercase;` (next to `.warn-keyword` :2657).
  - `.ge-keyword` = `color: var(--color-primary); font-weight: 700;
    text-transform: uppercase;`.
  - the additive error face:
    `#confirmModal .modal.error-modal .modal-title { color:
    var(--color-error); }`,
    `#confirmModal .modal.error-modal .modal-header { border-bottom: 1px
    solid var(--color-error-container); }`,
    `#confirmModal .modal.error-modal .conflict-rows { border-color:
    var(--color-error-container); }`.
    NO existing rule retouched.
- [ ] **T2 (the error-style toggle, the shared shell):**
  `showConfirmModal` gains `opts.errorStyle` →
  `document.querySelector('#confirmModal .modal').classList.toggle('error-modal',
  !!opts.errorStyle)`; `hideConfirmModal` removes the class (the
  btn-danger reset pattern). No other call site changes.
- [ ] **T3 (the Submission Conflict modal rebuild, the :2211 block):**
  - the failed-row builder stays (the `label` line — venue · abbr+date ·
    time range; the 'A selection' fallback label intact).
  - **the intro line** (proposal pin 2's first element) renders ONCE at the
    top: `Some selections could not be <span class="warn-keyword">BOOKED</span>:` —
    the intro replaces the current plain intro text; the row list keeps its
    bordered container.
  - **the ge-keyword pin is INERT here (declarative):** SELECTED SLOT /
    BOOKING do not render in this modal's reachable states — the pin
    declares the palette mapping for any future line that says them; only
    NOTE uses `.ge-keyword` today (the round-1 🟡-1 fold).
  - **Type B detection:** `r.reason` contains the literal `runs into an
    existing booking` → the headline line `The block overlaps with an
    <span class="warn-keyword">EXISTING OCCUPIED BOOKING</span> at
    <first at-range humanised via to12h>.` rendered under the label line;
    if the FIRST `at HH:MM–HH:MM` regex misses, the raw `r.reason` renders
    as a single row line (no headline/label).
  - every other reason row = the RAW `r.reason` verbatim (no rewording).
  - the body close: the NOTE line (`<span class="ge-keyword">NOTE</span>:
    Another staff member may have booked this slot before your
    submission.`) + the footer line `Please <span class="go-keyword">REFRESH</span>
    this page and <span class="go-keyword">PICK</span> another available
    slot.` — both at the MODAL level (shared across all rows).
  - the opts: `{ danger: true, confirmLabel: 'Refresh Page', cancelLabel:
    'Close', errorStyle: true }`, the callback = `function() {
    hideConfirmModal(); location.reload(); }` — reload-only, no resubmit,
    the URL (session/week/from/code/cohort/venue/date/time/notif params)
    preserved by the reload itself.
  - the CONFLICT keyword: the title becomes `Submission
    <span class="warn-keyword">CONFLICT</span>` (red per the user's key
    palette; the shell renders the title via innerHTML ✓).
- [ ] **T4 (the gates):** pint ✓; phpstan-1G 0; the full `php artisan test`;
  the Playwright arrangement-family rows + `tests/venue-intent-modal.spec.ts`
  (--grep-invert "TC70"); a live receipt probe on the :8000 demo IF a
  conflict row is reachable read-only (the receipt = the modal shell's
  keyword classes + the error face render — the CONFLICT state itself is
  hard to force organically; the structural receipt = the paragraph + the
  opts read). NO push; no DB writes.
- [ ] **T5 (the docs):** `page-changelogs/replacement-arrangement-changelog.md`
  — the dated entry: the modal rebuild (both types), the Refresh Page
  behaviour (URL preserved, no resubmit), the keyword palette (warn=red /
  go=green / ge=blue — .info-keyword untouched), the error-face scope.
