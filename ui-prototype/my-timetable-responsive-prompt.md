# Prompt — Make My Timetable responsive (phone → desktop)

> **Paste-ready build prompt.** Written 2026-11-12 from the approved prototype
> `ui-prototype/my-timetable-mobile-preview.html` (throwaway; keep it as the visual reference).
> Updated 2026-11-13 for rounds 1–5, the print-preview help dialog and the screen dense mode.
> Updated **2026-11-15**: the card's three tiers and the subject name leaving the card (§5), the
> short tab labels and the one-filled-primary footer in the details sheet (§8), and the student
> page as **phase B** (§15) with its own reference prototype.
> Do not re-litigate them; if something is missing, ask **one** question with a recommendation
> instead of guessing.

---

## 1. Task card

| Field | Value |
|---|---|
| Page | **My Timetable** (lecturer landing page) |
| Live view | `resources/views/livewire/my-timetable.blade.php` |
| Address | `/my-timetable-ui` (students: `/student-my-timetable-ui` — phase B, §15) |
| Goal | The page works at **320px → desktop** without changing the desktop design |
| Visual fidelity | Desktop (≥1280px) must stay **~97% identical** to today |
| Out of scope | Backend behaviour, DB writes, new endpoints, new dependencies, a restyle |
| Reference | `ui-prototype/my-timetable-mobile-preview.html` (open it at 320/390/768/1024/1280+) |
| Phase B reference | `ui-prototype/student-my-timetable-mobile-preview.html` (the same preview with the student deltas applied; §15) |

---

## 2. Non-negotiables (repo rules)

- Colours come **only** from `public/css/theme.css` custom properties. No hex/rgb in page CSS.
- Reuse `theme.css`, `public/js/ui-common.js`, the Flux icons, and the existing partials
  (`ui-grid-table`, `ui-week-nav`, `ui-legend-bar`, `ui-summary-bar`, `ui-page-header`).
- No new dependencies. No `MockData` writes. No fabricated counts — cards show payload numbers.
- **≥1280px keeps the current weekly grid, legend, summary cards and print button.**
  Explicit exceptions: remove the inline guide and page description (move both into Help), remove
  Public Holiday labels inside event blocks, put Cancelled / Conflict below the time range, and
  **remove the week subtitle and the semester progress bar** (user decision 2026-11-12).
  Summary cards keep their compact dimensions and flip to three-line explanations (§9).
  Responsiveness is a layout adaptation, not a redesign.
- Existing system design is the default for every component, including confirmation dialogs.
  Do not replace existing radios with dropdowns, invent a new modal layout, or change its icons/colours.
  The standalone prototype duplicates markup only to run without Laravel; the real implementation
  must reuse the shared partials/controllers, not copy the prototype's mock handlers.
- After the change: update `page-changelogs/my-timetable-changelog.md`, restart via
  `scripts/dev-serve.sh`, run `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`.
- Never push. Never touch the demo DBs for this task.

### Shipped surface components (do not invent substitutes)

| Area | Use |
|---|---|
| Nav | `<a class="nav-item" href>` items (theme styles anchors), plus `.notif-btn` + `.notif-badge` bell and `.theme-toggle` with `data-tip` / `data-tip-pos="left"` |
| User panel | `.user-role` reads `Lecturer` / `Lecturer (PL)` / `Student` — no sample suffixes |
| Print | `.print-btn` with the printer SVG and `data-tip="Open print preview"`; links to the sibling print prototype (§14), never a `⎙` glyph |
| Empty state | `.empty-state` + `.empty-icon` + `<h3 class="empty-title">` + `.empty-text`; empty week reads **`No classes this week` / `All classes for this week have been cancelled.`** |
| Legend | `.legend-item` + `data-tip` (shared tooltip engine); no `tabindex`, no click toast, no separate attribute name |
| Toast | `.toast-bar` > `.toast-content` > `.toast-message`, toggled by `.visible`, 5 s, bottom-left |
| Summary | compact front/back cards; hover/tap/keyboard flips to three-line explanations (§9) |

**Tooltips** ride the one shared engine (`initDataTipTooltips()` in `ui-common.js`): a single
`.data-tip-tooltip` node, above the element, `data-tip-pos="left"` for the top bar, `\n` switches
to `pre-line`, and keyboard focus shows the same tip. Already in the system: Today
(`Go to current week`), Print (originally `Coming soon`, now `Open print preview` for the linked prototype), the bell (`Notifications`), the theme toggle
(`Switch between light & dark mode`), and each legend item. **Added by this task (user, 2026-11-12)**
on both surfaces: the week arrows (`Previous week` / `Next week`), the week select
(`Pick the week to display`), and every class block — subject name · lecturer · STATUS,
e.g. `Software Engineering · En. Lim Jia Zheng · NORMAL`. The block tip uses the system's own
composed shape (`tipLecturerFn` + `eventStatusLabel().toUpperCase()`), so wiring the real grid means
turning its existing `data-name` / `data-tip2` into a rendered `data-tip`, not inventing new text.
Do not add `title` next to `data-tip` — that double-tools.

---

## 3. Breakpoints (agreed)

| Width | Layout |
|---|---|
| **< 768px** | One day, vertical agenda of class cards |
| **768–1023px** | Two-day time grid (30-minute rows) |
| **1024–1279px** | Three-day time grid |
| **≥ 1280px** | Current full weekly grid, with only the explicit exceptions in §2 |

- The visible tablet days follow the **selected day** and clamp at the week edges
  (selecting Sunday shows the last 2–3 days, never empty columns).
- Changing week keeps the same weekday where possible.
- Phone shows one day in time order; **empty time gaps are not drawn**.

---

## 4. Fixed header area (phone + tablet)

- The week row (arrows, week select, Today) and the day strip stay **fixed** while the list scrolls.
- **Today** lives in that fixed area; it is the only way back to the current date.
- Phone week label: `Week 9 · 16 Nov ~ 22 Nov` (both months, no year).
  Tablet/desktop: `Week 9 · 16 Nov 2026 ~ 22 Nov 2026`. Crossing 768px relabels without losing selection.
- Week select uses `background-color: var(--color-surface-variant)` at rest and
  `var(--color-surface)` on hover; preserve its existing chevron, text, height and focus ring.
  **Status: shipped 2026-11-13** in `theme.css` `.week-select` / `.week-select:hover`.
- Today follows `ui-today-btn`: `.today-btn`, the concentric-circle target icon,
  and `<span class="today-btn-label">Today</span>`. Do not substitute a calendar or custom button skin.
- Phone semester chip shows `202605 Semester` only; full dates live in **Week summary**.
- Day strip: **border = the day you are viewing**, **small dot = today**. Do not use an underline.
- Tapping the already-selected day does nothing (Today handles returning).

---

## 5. Class card (phone)

Stacked, content-sized, consistent padding — never a fixed equal height.

**Three tiers by size, and one grey — never a second colour language (user, 2026-11-15):**

```
┌──────────────────────────────────────────┐
│ 9:00 AM ~ 11:00 AM                 B111  │  ← PRIMARY    time + venue code (14px, 700)
│ BMIT2201 (L)                             │  ← SECONDARY  code + type (12px, 500, grey)
│                          ✕ Cancelled     │  ← TERTIARY   the note line, right (11px, 500, grey)
└──────────────────────────────────────────┘
```

- **Primary = the time range and the venue code.** Same size, same weight, the block's own text
  colour. These are the two facts a student reads to know where to be and when.
- **Secondary = the course code + type**, 12px/500 in `--color-on-surface-variant`.
- **The subject name is not on a card below 1280px** (user, 2026-11-15). It stays in the hover tip,
  the details sheet title and the card's `aria-label`; the card prints the code and type only.
- **Tertiary = one note line, right-aligned**, 11px/500 grey. It carries exactly one of
  `(Pending since 12-Nov-2026)` · `(Replaced for 05-Nov-2026)` · `✕ Cancelled` · `⚠ Conflict`.
- **Only Cancelled and Conflict have `.preview-status` icon + word labels inside blocks.** On a
  phone card and a tablet block (below 1280px) the label sits on the tertiary note line rather than
  under the time — the user's instruction of 2026-11-15 is scoped to below 1280px. **At ≥1280px the
  desktop grid block keeps its shipped order** (code, venue, time, then the word, then the note),
  because the desktop look must stay ~97% identical to today. Either way the word keeps the block's
  own status colour and never gets pill styling. No Public Holiday label inside any event block;
  retain holiday day headings, red treatment, holiday panel and details-modal status.
- Pending keeps its dashed outline and the system note `(Pending since 12-Nov-2026)` when supplied.
  Replacement keeps its solid side bar and `<span class="ev-note">(Replaced for 05-Nov-2026)</span>`.
  Do not use `Replaces …`. **No status word for Pending or Replacement** — colour plus the date
  note carries it (the student page's reading of the same rule is §15 and the lane's §9d).
- **Never** show cohort or student count on a card; they belong in the class sheet.
- Status is never conveyed by colour alone — the card's `aria-label` names the state and the subject.
- Tablet uses the same order one step down: time/venue 11px, code 10px, note 9.5px. A narrow tablet
  block (two lanes side by side) lets the venue fall to a second line on the right rail rather than
  shrink the time range.
- All tap targets ≥ 44px. Body text ≥ 12px; the two lower tiers are deliberately smaller.
  Never shrink text to force one line.

---

## 6. Day heading and hints

- Date heading on the left, **class count as secondary text beneath it** (e.g. `5 classes this day`).
- A compact **Week summary** icon button on the right (accessible name `Week summary`).
- **Next class hint appears only on empty days and public holidays** — never above a populated list.
- The hint is a **button**, laid out as: caption `◷ Next class →`, then `Mon, 7 Dec · 9–11 AM` and a venue chip.
  Tapping it jumps to that week and day and brings the day into view. No course code, no repeated year.

---

## 7. Empty and special states

| State | Treatment |
|---|---|
| Empty day | `.empty-state` + `.empty-icon` + `h3.empty-title` `No classes on <weekday>` + next-class button |
| Public holiday | `.empty-state` on the error-container palette: `Public Holiday · <label>`, the full weekday, next-class button |
| Empty week | `No classes this week` + `All classes for this week have been cancelled.` + next-class button; controls stay visible |
| Cancelled class | X badge, dimmed course text |
| Conflict class | Warning badge, strong outline |

Controls are never hidden in an empty state. Use the shipped `.empty-state` markup, never a new
empty component; only the next-class hint is an addition and it rides inside that block.

---

## 8. Class details sheet

- Tapping a card opens a **bottom sheet on phones**, a centred dialog on tablet/desktop.
  The phone sheet is **theme.css's own dock**: `80dvh`, `--radius-xl` top corners, the overlay's
  20px gutter. Do **not** add a grabber bar or drag-to-close — the real page has neither.
- Shows: session, lecturer, cohort, total students, venue (with room name and seats), week,
  day and date, time and duration, status and remarks.
- Footer actions appear **only when allowed**: `Arrange Replacement` (cancelled / conflict / holiday),
  `Cancel Class?` (normal). No action row otherwise.
- Match `ui-class-detail-modal`, `openClassModal` and `theme.css` at ~97%:
  - `.modal.modal--detail` (680px), `title` + subtitle `CODE — Name (Type)`, and the footer
    `Close` (`.btn-close-modal`) + page action in `.modal-footer-left` / `.modal-footer-right`.
  - **Type scale stays the shipped one**: 14px `.detail-value`, 15px `--strong`, 128px `.detail-label`,
    16px row gap, shipped `.modal-tab` padding. Do not shrink them to win phone space.
  - Heights: `85vh` desktop / `80dvh` phone from `theme.css`. Nothing else.
  - Phone footer keeps the theme's **stacked column**, full-width buttons.
  - **Close is never the filled primary** (user, 2026-11-15 — reverses the 2026-11-14
    "Close takes the fill when it is alone" rule for this pop-up): only a page action may
    fill; `Close` always wears the grey `.btn-close-modal` skin, even when it is the only
    button in the footer.
  - **The student page is read-only** (phase B, §15): no Arrange, no Cancel, no
    `View Full Request`, no `Requested At` / `Requested By` rows. Its footer is `Close` alone.
  - The × carries `data-tip="Close"`.
  - Tabs follow the shared buckets with the **short labels** (user, 2026-11-14) in this order:
    **Class Info → Schedule → Original → Status** (no tabs at all when a group is empty;
    `Original` only exists for a confirmed replacement). The section captions keep the long
    names — `Class Information`, `Original Slot` — the same as the shipped variable names.
  - `Status` row is `badge badge-<status>` straight from the status
    (`public-holiday` spells `badge-public-holiday`), text from the canonical status labels.
    **A cancelled class reads `Class Cancelled`, not `Cancelled`** (user, 2026-11-15) — the
    bare word can be taken as the *request* being cancelled. The block's note line keeps the
    short `Cancelled`, because a one-hour desktop cell is too narrow for the long form.
  - **`.badge-cancelled` has no skin in `theme.css` today** — `badge-conflict` exists,
    `badge-cancelled` does not, so the row printed a transparent pill. The prototype gives it
    **`.badge-reason`'s two tokens** (`--color-surface-variant` / `--color-on-surface-variant`) —
    the grey chip `Class Cancelled` already wears on My Replacements. **Never red:** `--color-error`
    is Public Holiday's and Conflict's (§10.0), so a cancelled class on the same red would blur the
    signal. Porting means adding that class to `theme.css` (mirroring `.badge-reason`, **not**
    `.badge-conflict`); do not ship the transparent pill.
  - **Remarks is the record's own remark or `—`** — never a note/label fallback.
  - Own **pending** adds `Requested At` + `Requested By` before Remarks, and a
    `View Full Request` link (`.btn-action`) in the footer-right; another lecturer's pending gets neither.
  - **Confirmed replacement** adds the Original Slot rows: Original Day (weekday from the date),
    Original Date (strong), Original Start Time (strong), Original End Time, Original Venue,
    Original Venue Name, plus Original Conflict only when the payload carries a reason.
  - Subject appears once in the subtitle; venue appears once, with room name and seats.
    Multi-cohort student count reads `14 + 14 = 28`.
- Modal badges use one pill geometry: 24px height, `0 10px` padding, pill radius,
  12px/600 centred nowrap text, with existing semantic colours unchanged. Prevent the shared
  ≤1024px `.badge` touch-target rule from stretching non-interactive status badges.
- Closes by × , backdrop tap, or Escape.
- **Closing restores the previous scroll position and focus** — the page must never jump to the top.
- **Cancel this Class? must mirror the existing system**, not the earlier prototype dropdown:
  - Reference `resources/views/partials/ui-cancel-class-modal.blade.php`, `CancelClassModal`
    in `public/js/ui-common.js`, and `theme.css` (`.modal--cancel`, max-width 588px).
  - Class Information heading and grey `dl.cancel-details`: Course, Week, Day & Date, Time
    with duration, Venue, Cohort(s), Students. Use a full-width `.modal-divider` before reasons.
  - Eight radio reasons from the existing enum, two per row, one per row below 520px.
    Heading `Cancellation Reason` has the shared red required asterisk; no extra instruction line.
  - Other remains the eighth radio. It reveals `Other reason *` and the full-width `.modal-textarea`
    below the entire radio grid. Preserve its text when switching reasons; blank Other cannot submit.
  - Footer: `No, Keep Class` left; `.cancel-confirm-wrap` right, with warning-coloured
    `CANNOT UNDONE` directly above `Yes, Cancel Class`, only while Yes is enabled.
  - Preserve the current optional same-day section: folded and unticked initially, with class/request
    rows, select-all, individual checks and approved make-up note. Real data/eligibility remains owned
    by the existing controller; the prototype uses local fixtures only.
  - Real page preserves existing confirmation/success states and backend rules, including no Undo.
    Prototype only shows a truthful preview-complete message; it never changes real or mock class status.

---

## 9. Week summary sheet (phone + tablet)

- Holds what the phone header hides: the **legend bar** and the **five summary cards**, plus the full
  semester date range.
- Reuse the real markup and classes (`legend-bar`, `summary-bar`, `card-total`, `card-hours`,
  `card-replacement`, `card-pending`, `card-conflict`) so colours and borders match desktop exactly.
- Cards are **96px high**, keeping the existing centred number, label and status colours.
  **Keep the current widths and column layout unchanged.** Restore the shipped front/back flip:
  hover on desktop, tap on touch screens, Enter/Space on keyboard. Each back also has a small
  **info icon button** opening the full explanation; the icon must not toggle the flip.
- **Status: shipped 2026-11-13** on My, Student, Cohort, Venue Timetable, My Replacements and
  Request Approval via the shared `ui-summary-bar` partial's `flipCards` flag; the explanation
  modal is `#summaryExplainModal`, and its 44px info button is `.summary-info`. The remaining
  `ui-summary-bar` caller (`replacement-history`) keeps the older cards until asked.
- The `+N` pending tail stays **clearly smaller than the main number** (≈62% size, weight 600,
  tertiary colour, 1px gap) — it is a secondary part, never a second headline. Keep the existing
  `span.sum-pending` markup; size it with a page-level rule, not a `theme.css` change.
- Explanations use **short, left-aligned bullet lists**, not centred paragraphs.
  Bold the key facts; use existing semantic tokens for Normal (secondary), Replacement (primary),
  Pending (tertiary), and exclusions/conflicts (error). No new colours or all-caps paragraphs.
- **Every bullet must teach something real** — what the number counts, how the clock or approval
  works, or which grid colour it matches. No filler such as `Selected week only`, no slogan lines,
  no bullet that only restates the card label. Three bullets per card, maximum.
- Shortened copy for the fixed-size backs (2026-11-12):
  - Total Classes: `Normal + blue` · `+N = pending` · `No red blocks`.
  - Teaching Hours: `Teaching time` · `30 min / slot` · `+N = pending`.
  - Replacements: `New class time` · `PL approved` · `Blue blocks`.
  - Pending: `Awaiting PL` · `Not confirmed` · `+N on totals`.
  - Conflicts: `Cancelled/clash` · `Or a holiday` · `Needs make-up`.
- Use semantic `ul` / `li` on the back, 12px text, three short lines maximum. Both faces stay
  absolutely positioned so descriptions cannot stretch the cards. Do not clip, ellipsize or
  shrink text to fit; shorten wording instead. Keep the smaller +N and status colours.
- Apply to desktop and the two-column cards inside Week summary. A tap flips back without
  opening another overlay unless the info button is tapped. The summary hint says
  `Hover or tap a card to learn more`.
- The info button has a 44px touch target, an accessible name and `Full explanation` tooltip.
  Keep the current card widths and 96px height. Its full-description popup uses the existing
  modal shell, max-width 420px, and useful coloured/bold bullet points explaining the count,
  exclusions and relevant actions. The Pending explanation states **3 working hours or less**,
  not strictly “under 3”. No filler or claim that a replacement means a different lecturer.
- From Week summary, `Back to summary` restores the flipped card and focuses its info button;
  do not stack overlays. From desktop, Close returns focus to the original info button.

---

## 10. Help and accessibility

- Remove the inline `How to use this page` details block and the header's `.page-desc` at **all widths**.
  Keep the existing help icon. Its modal starts with the exact page-description paragraph (including
  `.info-keyword` spans), then the browsing and class-details guidance. No duplicate surface guide.
- **Status: shipped 2026-11-13** on My, Student, Cohort, Venue Timetable, My Replacements and
  Request Approval via the shared `partials/ui-guide-modal.blade.php` (`#guideModal`). The header
  icon opens it with `GuideModal.open(this)`; `.guide-block` is no longer rendered on those six
  pages. `replacement-arrangement` and `replacement-history` still use the inline guide until
  they are asked for.

- Focus is visible everywhere; the sheet traps focus while open and returns it on close.
- Day strip buttons expose `aria-pressed` and `aria-current="date"` for today.
- Summary and legend explanations are reachable by keyboard, not hover only.
- Respect `prefers-reduced-motion`.

---

## 11. Acceptance checklist

- [ ] 320 / 360 / 390 / 430px: one day, no horizontal scrolling, no clipped text.
- [ ] 768px: two days; 1024px: three days; edges clamp correctly at Mon and Sun.
- [ ] 1280px+: pixel-comparable grid, legend, summary and print, except explicit §2 changes.
- [ ] Week arrows, week select, Today and day strip all work at every width.
- [ ] All five class states render correctly, including on a public holiday.
- [ ] Card → sheet → close returns to the same scroll position.
- [ ] Next-class button lands on the right week and day.
- [ ] Help owns both description and guide at every width; no duplicate inline text.
- [ ] Only Cancelled / Conflict have block labels — **on the note line below 1280px, below the time
      at ≥1280px**; Public Holiday remains on day headings.
- [ ] A phone card prints the time + venue, then the code and type — **no subject name**; the name
      is in the hover tip, the sheet subtitle and the card's `aria-label`.
- [ ] Week format, background and Today icon/label match §4 at all breakpoints.
- [ ] Cancel this Class? matches the shipped shell, class box, radio grid, Other field and footer.
- [ ] Class Details keeps the shipped type scale, heights, phone dock and stacked phone footer.
- [ ] Staff page: pending shows Requested At/By + a `View Full Request` link, and a replacement adds
      the `Original` tab. **Student page: neither** — no request facts, and the footer is `Close` alone.
- [ ] Prototype remains standalone and read-only; real implementation uses existing controllers.
- [ ] Summary cards keep their current width and 96px height; hover/tap/keyboard flips both ways.
- [ ] Each back fits three short lines plus an info button, including at 320px.
- [ ] Info opens the full explanation without flipping; Back to summary restores the card.
- [ ] No hex/rgb in page CSS; no new dependency; changelog updated.
- [ ] Week subtitle and semester progress bar are absent at every width.

---

## 12. Real system — later (user decision 2026-11-12)

The prototype drops two blocks the live page still renders. When the real page is touched:

- Remove `<div class="week-subtitle" id="weekSubtitle">` and its fill code from
  `resources/views/livewire/my-timetable.blade.php`.
- Remove the `#semesterProgress` block (`.progress-label` / `.progress-track` / `.progress-fill`)
  and its width/label updates.
- Same blocks also exist in `student-my-timetable.blade.php` and `venue-timetable.blade.php` —
  ask before touching those pages; this decision names **My Timetable** only.
- **Status: shipped 2026-11-13.** The user's UI Update authorised the same removal on all four
  timetable pages. `updateProgress()` / `updateWeekSubtitle()` are null-safe, so their calls were
  left in place. `cohort-timetable` never had either block.
- Do not delete the shared `.semester-progress` / `.week-subtitle` CSS from `theme.css` while any
  page still renders them.
- Update `page-changelogs/my-timetable-changelog.md` with the removal.

---

## 13. Deliberately not decided yet

Ask before implementing any of these:

- Whether the print button returns on tablet (guide stays in Help).
- Whether a phone user can reach Cohort/Venue timetables without opening the nav drawer.
- Whether Week summary should also be reachable from the desktop page.

---

## 14. Linked print prototype (user-approved 2026-11-12)

- My Timetable's Print button opens `timetable-print-preview.html` in the same tab.
  It must print the **selected week’s actual mock classes**, not the print page’s unrelated demo.
- Pass a versioned JSON snapshot in the URL fragment (`#snapshot=…`): week/day, semester,
  lecturer, theme, seven dates/holiday labels, 22 half-hour cells (8 AM ~ 7 PM), and the selected
  week’s event times/status/notes. Do not pass real request records, credentials or arbitrary return URLs.
- No storage, API calls, backend code or new dependencies. The fragment stays in the browser;
  it is not sent to a local HTTP server. Works for `file://` and local HTTP.
- The print page validates the snapshot before use, escapes text, matches the week/date/identity
  labels in its header and footer, and preserves cancelled/holiday red blocks and replacement notes.
  It keeps the fixed light paper palette even when the screen theme is dark.
- Back returns to the fixed sibling `my-timetable-mobile-preview.html`, restoring the selected
  week/day and current light/dark theme through checked query parameters.
- An invalid snapshot shows a clear message before displaying the print page’s own sample data.
  Opening the print page directly or choosing its Student/Cohort/Venue demos still works as before.
- This link is **prototype-only**. It does not wire either prototype into the real application.
  The real implementation plan lives in `.sdd/changes/timetable-print-v2/`; its dense print mode is
  TP-20. Keep the prototype's screen shell, help dialog, `?sample=busy` fixture and on-screen dense
  rules out of the real page.
- Checks: weeks 1/8/14, back navigation, both themes, holidays, status/notes, the final 7 PM end,
  normal-only toggle, and malformed snapshots. Browser verification remains paused unless requested.
- The print page mirrors the My Timetable header: the inline page description is removed and the
  help icon button (`#previewHelp`, `.preview-button.preview-icon-button`, `#i-help`, `data-tip`)
  opens a native `<dialog id="helpDialog">`. The dialog starts with the page description
  (`#pageDesc`) and includes the same **Browsing**, **Class details** and **This preview**
  sections; it updates when the page changes. The button shows its `data-tip` “How to use this page”
  tooltip, and the help button and dialog are hidden in print. Do not put the description back in
  the visible header.
- Print-layout correction: the hour-header row is **24px**, separate from the **76px day rows**.
  Do not apply day-row height to every `tr`; that wastes space and can send the footer onto its
  own page. Keep the footer together with a small 6px top gap, not a flex auto margin when printed.
- Round 3: the day cell shows **Public Holiday [H1]** (then H2, etc.), while the full holiday
  name appears below the grid with its matching reference, weekday and date. Dates keep
  `.date-label` on one line; the short holiday marker may wrap inside the cell if needed.
  Holiday notes stay visible under Normal only; only pending-request notes are hidden.
- Round 1 confirmed by the user's re-exported PDF: Week 8 fits on one A4 landscape page;
  Monday's holiday name stays inside its day cell.
- Round 2: blocks of one hour or less keep course code/type on one line (10.5px), with two
  complete time lines (`2:00 PM` / `~ 3:00 PM`) instead of leaving AM/PM on a line of its own.
  Other class text and the day-row height stay unchanged.
- Pending blocks show `Pending [n]`; the full submitted-date note is printed below the legend,
  keyed by that number, day, course and venue. Notes are hidden by Normal only, alongside the
  corresponding blocks. Replacement, Cancelled and Conflict retain written status labels for
  black-and-white printing; holiday is identified in the day header. Keep replacement date notes.
- Round 3 confirmed by the user's PDF: tidy `Public Holiday [H1]` reference, matching holiday note
  below the grid, separate pending numbering, and one-page output.
- Round 4: very long course codes (e.g. `BMCS3404-ADVANCED (P)`) wrap inside narrow blocks with
  `overflow-wrap: anywhere`; do not restore `white-space: nowrap` there. Normal-length codes still
  fit on one line. A week with no classes hides the blank grid and prints
  **No classes this week.** with a short explanation.
- Round 5: a very full printed week uses `.sheet.dense` (more than 32 classes, or more than 6
  pending/replacement notes combined). Dense mode tightens the class blocks **on screen and in
  print** — 3px/4px padding, 2px border, zero flex gap, line-height 1.0 — so the on-screen busy
  sample reads like the printed page. Print additionally pulls the notes and footer closer
  (1px / 2px gaps). Day rows keep the normal 76px minimum, so a week with no replacements does not
  grow. This keeps a 48-class stress week with 12 pending and 12 replacement notes on one A4
  landscape page. Do not reduce font sizes or force one page by shrinking type. Do not force the
  screen sheet to the printed 794px height: screen type is larger, so the screen sheet stays
  taller even in dense mode.
  **Prototype-only: do not copy the flat `2px` border into the real page.** The real Cohort/Venue
  grid encodes ownership as `3px` (yours) vs `0.5px` (others'), and the printed legend explains
  that difference; a blanket border would make the key false. The real plan's dense mode tightens
  padding, gap and child line heights only (`.sdd/changes/timetable-print-v2`, TP-20).
- Re-export Week 8 after round 5 to confirm the holiday reference and one-page layout, then check
  one long-code block, an empty week, and the 48-class stress week. Browser verification remains
  paused unless requested.
- Temporary busy sample (added at the user's request): open the print page and press
  **Busy week sample**, or open `timetable-print-preview.html?sample=busy`. It shows Week 8
  (9–15 Nov 2026) with 48 mock classes across Mon–Sat (8 per day), 12 pending and 12 replacement
  notes, and Monday's holiday. The button toggles back to the normal sample. This is test data
  only; it is not in My Timetable or the real system. Before shipping, remove
  `busySampleTimetable()`, `#busySampleBtn`, the `.sample-btn` CSS, and the `?sample=busy`
  initialisation/handler.

---

## 15. The student page — phase B (user, 2026-11-15)

`resources/views/livewire/student-my-timetable.blade.php` is **the same page with different
data**: 252 lines against the staff blade's 352, the same partials (`ui-page-header`,
`ui-guide-modal`, `ui-week-nav`, `ui-grid-table`, `ui-legend-bar`, `ui-summary-bar`, plus the
shared `ui-class-detail-modal`) and the same `buildTimetableGrid()`. Everything in §3–§11 is
built in that builder, those partials and `theme.css` — not in the blade — so phase B is one
more blade edit on finished, flagged components. Phase A (the staff page) must be able to ship
alone.

The visual reference is **`ui-prototype/student-my-timetable-mobile-preview.html`** — the
staff preview with these deltas applied, so the two cannot drift apart.

| | student page |
|---|---|
| Actions | **read-only** — no Cancel Class modal or include, no cancel path off `onEventClick`, no Replacement Arrangement handoff. A card opens the details sheet; nothing else acts |
| The details sheet | footer is `Close` alone, never filled; no `Requested At` / `Requested By` rows, because the request belongs to the lecturer |
| Header | the cohort chip (`DFT2 (S1) G1`) beside the title; heading reads `Student My Timetable` |
| Nav | two items — `My Timetable`, `Class Replacements` |
| Block tip | names **the class's own lecturer** (a student needs to know who teaches); the staff page keeps the viewer's name |
| Summary card 2 | reads **`Class Hours`** (the staff page reads `Teaching Hours`) |
| A block's status | can be Pending or an approved Replacement as well as Cancelled / Conflict — the lane's §9d records what a card prints |

Everything else — the four breakpoints, the day strip, the agenda cards of §5, the Week summary
sheet, the tooltip migration, the accessibility work — is the same work, opt-in per page.
