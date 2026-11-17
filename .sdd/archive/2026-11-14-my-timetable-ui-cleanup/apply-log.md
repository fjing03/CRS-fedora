# Apply log — my-timetable-ui-cleanup

## Records reconciled — 2026-11-14

**Completion:** T1, T2, T3, T4, T5, T6, T6b, T7, T8 — **27/27 ticked**. This entry
does not record new work. It corrects a stale file: the four changes shipped on
2026-11-13, but `tasks.md` still showed 0/27, `sdd.yaml` read `status: active`
with no approval line, and no apply-log existed. The lane read as an unstarted
plan while its work was live.

**How this was reconciled.** Each tick was checked against the code and then
against a rendered page, not inferred from the lane's own prose:

- Static: `flipCards => true` on exactly six pages; `.summary-section--flip
  .summary-card { min-height: 96px }` (16 scoped rules); `ui-guide-modal` included
  on six pages and `ui-guide-block` absent from all six; `SummaryCards` and
  `GuideModal` controllers present in `ui-common.js`; `.guide-toggle-icon` wired
  to `#guideModal` with `aria-haspopup`/`aria-controls`/`aria-expanded`;
  `.week-select--tinted` plus its `:hover` arm; `selectTinted` on the four
  timetable pages only.
- Removal proof: `#semesterProgress` and `#weekSubtitle` appear nowhere under
  `resources/views/livewire/`. Two remaining `semesterProgress` hits are in
  `resources/views/ui-design-templates/` — frozen reference templates, not pages.
  The `updateProgress()` / `updateWeekSubtitle()` calls remain in place and stay
  null-safe, as T6 required.
- Headless Chromium, staff `5425` (student `24DFT0001` for the student page),
  1360×900, 2026-11-14:

| Page | Flip cards | Card height | Info buttons | `.summary-points` | `#guideModal` | `#guideBlock` | `#semesterProgress` | Tinted select |
|---|---|---|---|---|---|---|---|---|
| My Timetable | yes | 96px | 5 | 5 | yes | no | no | yes |
| Student My Timetable | yes | 96px | 5 | 5 | yes | no | no | yes |
| Cohort Timetable | yes | 96px | 5 | 5 | yes | no | no | yes |
| Venue Timetable | yes | 96px | 5 | 5 | yes | no | no | yes |
| My Replacements | yes | 96px | 5 | 5 | yes | no | no | no *(intended)* |
| Request Approval | yes | 96px | 5 | 5 | yes | no | no | no *(intended)* |

- Guide modal opened from the header icon on My Timetable: visible, 1 `.page-desc`,
  4 guide items.
- Zero page errors across all six pages.
- Opt-in proof: `/student-class-replacements` — outside this lane's six — still
  renders 88px cards, no info buttons, and its inline `#guideBlock`. The change
  did not leak.

**Scope check.** No DB, route, dependency or payload change. No summary value,
filter, week navigation, class-detail modal or print path was touched. The callers
that were never opted in (`replacement-history`, `replacement-arrangement`) keep
their inline guide.

**Known gap, recorded rather than repaired.** This lane never recorded frozen
gates or an explicit approval line. It was proposed, reviewed, applied and
verified in a single pass on 2026-11-13, so `proposal`/`design`/`specs`/`tasks`
carry no `frozen` markers and there is no `implementation_approved` entry, unlike
later lanes. `review-log.md` holds the real evidence (proposal round 1, design/
specs/tasks round 1, and the My Replacements + Request Approval scope extension).
That history was left untouched; nothing was back-dated or invented.

## Authority

The user instructed this reconciliation on 2026-11-14 ("go task2"), after asking
whether the lane was outdated and being shown the measured mismatch. Authority
covers the lane's own record files only. It does **not** cover a commit, a push,
an archive move, or any code change — no code was modified.

## Files changed by this reconciliation

`tasks.md` (27 boxes ticked, serials and definition-of-done note added),
`sdd.yaml` (status `applied`, applied/reconciled dates, task counts, known gap),
`apply-log.md` (this file, new). `proposal.md`, `design.md`, `spec.md` and
`review-log.md` were deliberately not edited — they describe the plan and the
review, both of which were already correct.

## Not committed

Working tree only. `theme.css` also carries the separate `nav-right-align` change
(2026-11-14), which is unrelated to this lane and must not be swept into it.
