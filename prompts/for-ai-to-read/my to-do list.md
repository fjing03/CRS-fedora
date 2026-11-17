# My To-Do List

## Rules & Permissions

### 1. AI Allowed — Read & Update Status Only
- AI may read tasks and mark them as Done after completion and verification.
- AI must not rewrite, add, remove or change task details.
- **Execution Status:** When PI or OpenCode is actively working on a task, display the responsible tool clearly.
  - Example: `**Status:** PI Executing`
  - Example: `**Status:** OpenCode Executing`
- Update or remove the execution status when the task is no longer being executed.

### 2. AI Requires Permission — Improve Instructions
- AI may improve task wording to make it clearer and easier to understand.
- Keep the original meaning and use simple, human-friendly, non-technical English.
- Ask for my approval before applying any changes.

### 3. AI Restricted — Delete
- AI must never delete any task, instruction or content.
- Only I can decide what should be deleted.

### 4. Communication & Approval Rules
- Use simple, everyday English. Avoid unnecessary technical terms and long explanations.
- When I need to make a decision:
  - Explain the situation in plain English.
  - List the available options with their main benefits and risks.
  - Recommend one option if appropriate.
  - Ask only one clear question at a time.
- **After completing a task, report the result to me first.** Include what was changed and whether it was successfully verified.
- **Do not start the next task until I explicitly approve.**
- Do not perform any action that requires my approval before receiving confirmation.

### 5. Investigation & Testing Tools
- Use SDD skills and/or Playwright MCP when needed.
- For complex or large tasks, use these tools where appropriate to inspect the existing system, understand the impact and verify the results.
- Do not make assumptions about the existing code or behaviour.

---

# Saturday, 7 November — Task List

## Execution Rules
- Execute tasks **in the listed order**, one step at a time.
- Complete and verify the current task before reporting its result.
- After reporting, **stop and wait for my approval** before starting the next task.
- Do not skip tasks or start future tasks early.
- Follow all approval requirements for each task.
- Update the execution status when PI or OpenCode starts working on a task.
- Mark a task as Done only after its required changes are verified.

## Tasks

- [x] **0. Previous Tasks** — Completed.

- [x] **1. Update Lab Access Type**

  - Read `prompts/for-ai-to-read/handover-lab-access-type-aware.md` carefully.
  - Follow its instructions and execute the required changes.
  - Verify the changes.
  - Report the result and wait for my approval before proceeding.

- [ ] **2. Remove Change Venue Confirmation Modal**

  **Status:** Parked — the brief and its notes moved to `prompts/extra tasks/` (2026-11-07, user decision). One question is still open there: what happens to the picked slots when the venue changes.

  - Read `prompts/extra tasks/Remove Change Venue Confirmation Modal.md` carefully.
  - Follow its instructions and execute the required changes.
  - Verify the result.
  - Report the result and wait for my approval before proceeding.
  
- [x] **3. Date & Time Format** — `docs/date-time-format-audit-2026-11-07.md`

  **Status:** all approved batches are done and verified. #30 stays deferred by your decision.

  - [x] **3a. Batch 1 — same-screen contradictions** (`10a51b0`)
    - #1 — My Replacements Quick View → Proposed Replacement Schedule → Time (12-hour, `~`).
    - #7 — Replacement Arrangement selected block and event block (`~`).
    - #13 / #14 — Approve and Reject compare cards (`~`).
    - #17 / #18 — Venue Timetable arrange question and booking subtitle (12-hour time; full day word in the sentence).

  - [x] **3b. Batch 2 — joiner drift** (`5120a98`)
    - #2, #3, #4, #8, #9, #19, #23, #24 — one `~` joiner across the live UI.
    - Also swept four unlisted spots the audit missed: the arrangement picked-slot toolbar line, the slot-picker trigger/tip/row label, and the Cancel Class success summary.

  - [x] **3c. Batch 3 — padded / month-first dates** (`f420215`)
    - #5, #6, #10, #11, #21, #27, #28 — `5 Nov 2026` (no leading zero, day first) on day headers, week ranges, the semester chip and picker labels.
    - The shared week scaffold, `DateHelper.fmt/fmtShort` and the holiday skip were unpadded together so the lead-time rule keeps working.

  - [x] **3d. Batch 4 — out-of-page surfaces** (`1dc3edb`)
    - #22 — class quick-view date/time now reads `5 Nov 2026, 2:00 PM`.
    - #29 — all three emails carry the date + 12-hour range (`Mon, 9 Nov 2026, 1:30 PM ~ 3:30 PM`).
    - #30 — deferred until `/api/v1` is unparked (not part of this batch).

- [x] **4. Business Logic Update**

  - Read `Business Logic Update — Use Working Days.md` carefully.
  - Follow its instructions and execute the required changes.
  - Start only after all main tasks are completed and I approve proceeding.
  - Do not treat this optional task as mandatory.

  **Note (2026-11-07):** this session also covers the agreed urgency clean-up, in this order —
  (1) the working-days countdown on My Replacements, (2) its two countdown rules (approved rows show `—`,
  pending rows show a countdown in the Requested Replacement column), (3) removing the Urgency column
  from Approve Requests. Each step is reported before the next one starts.


- [ ] **5. Archive Completed Instructions**

  **Approval Required:** Wait for my approval before archiving.

  **Status:** Partly done — two of the three files were archived on 2026-11-07 on my word; `handover-lab-access-type-aware.md` is still in its original place.

  Archive these files:
  - `prompts/UI Update & Bug Fix — Pending Slots, Conflicts & Legend Styling.md` — **already archived** in `prompts/completed prompt (Archieve)/`
  - `prompts/for-ai-to-read/handover-lab-access-type-aware.md` — **still in place** (needs my approval)
  - other `prompts/*`, for what have done.

  **Rules:**
  - Ask for my approval before moving any of these files.
  - Do not archive any file before approval.
  - Archive means move the files to the designated archive location, not delete them.
  - If I have not approved, leave all three files in their original locations.

- [x] **6. Review and Sync Project Handover Files**

  **Status:** Synced 2026-11-14 after the own-approved suppression and protected-manual-DB review (earlier sync 2026-11-12).

  Review the following:
  - All relevant files in `prompts/for-ai-to-read/`.
  - `prompts/for-ai-to-read/my to-do list.md` (this task list).

  **Requirements:**
  - Update only files with outdated or missing information.
  - Ensure important changes, current project details and task statuses are accurate.
  - Keep the task list consistent with the latest completed work and pending tasks.
  - Preserve all existing rules, permissions and approval requirements.
  - Avoid duplicate information and unnecessary edits.
  - Do not modify unrelated files.

  **Goal:** Keep the handover folder and this task list accurate and up to date with the latest project changes.


## Important Restrictions
- Preserve the task order.
- Report each completed task before proceeding.
- Wait for my approval before starting the next task.
- Do not archive files without my approval.
- Never delete task instructions or project files.
- Do not make unrelated changes.

# Sunday , 8 November — Task List
- **[] 1 **
- Read `Login Access Control & 403 Error Page.md` carefully.
  - Follow its instructions and execute the required changes.
  - Verify the changes.
  - Report the result and wait for my approval before proceeding.
  
- **[] Optional **
  - Read `Week Selector Auto-Selection When Switching Subjects.md` carefully.
  - Follow its instructions and execute the required changes.
  - Verify the changes.
  - Report the result and wait for my approval before proceeding.

# Thursday , 12 November — Small tasks

- **[x] 1 — Say why a room-only red cell is red (hover)**
  - Found while checking the ruling "a cancelled class keeps its room blocked" in the browser.
  - The red cell at that time/room shows only the two-word label `Venue Conflict`. Hovering it shows nothing, but the page legend says "HOVER the slot to see WHY".
  - Cause: the hover text is built from your own classes and your cohorts' classes only. A room held by a class that is neither has no text to show.
  - Ask: give those cells a plain hover line naming the class that holds the room (e.g. `Room held by BMIT2013(P) · Wed 9:00–11:00 · cancelled`).
  - Do not change the booking rules. The cell stays blocked; only the words change.
  - Wait for my approval before starting.

- **[] 2 — Open rule: should a cancelled class give its room back?**
  - Written down only; no rule says either way today, so this is a decision to make, not a bug to fix.
  - Today's behaviour (user decision 2026-11-12): **the room stays blocked** for that week. The teacher's time and the cohort's time are freed; the room is not.
  - The awkward part: a class that disappears through an **approved replacement** does give its room back, while one that disappears through **Cancel Class** does not. Same absence, two answers.
  - Why it matters: the room sits empty in the timetable while nobody may book it. A marker may ask why.
  - Options if you ever revisit it: (a) keep it blocked — no double bookings, but wasted rooms; (b) free it — matches the approved-replacement path, but someone else may take the room before the lecturer re-arranges.
  - Do not change anything until I say so.

# Saturday, 14 November — Task List

## Notes for today
- Execute **in the listed order**, one task at a time. Report each result and wait for my approval before starting the next.
- **Testing rule today:** for tasks **1–5**, verify only — no need to run the full test suites.
- Tasks 2–5 reuse files other lanes already claim; check the gate on each before starting.
- Do not commit or push unless I say so. Only stage files the current task touches.

## Tasks

- [x] **1. `nav-right-align`** — the shared top nav bar

  **Status:** Done and verified 2026-11-14. Gate cleared (the other chat does not touch `theme.css`).
  First saved commit `0851531` — the 194 `theme.css` lines left over from the day before, proven inert
  because every added selector targets a class the committed pages do not carry yet. Then the two nav
  rules were added on top, uncommitted.

  - Two small changes in `public/css/theme.css`:
    1. Push the moon / bell / menu-button group to the right side of the bar — one rule that works at every width.
    2. Remove the hard-coded `right: 12px` from the phone menu button, so it sits in line with the moon and bell instead of being pinned separately.
  - Why: measured in the real app, the menu button sits **574px** from the right edge at 900px; at 390px the moon and bell stay on the left while the button is pinned far right.
  - The bar is shared, so **every page** changes at once.
  - Verify: My Timetable, Cohort, Venue, My Replacements, Request Approval and one student page at 390 / 900 / 1360px; the menu button still opens the drawer; the drawer still shows the user block and Logout.

- [x] **2. `my-timetable-ui-cleanup`** — records only, no code

  **Status:** Done and verified 2026-11-14. 27/27 ticked (was 0/27), `sdd.yaml` moved
  `active` → `applied`, missing `apply-log.md` created. Every tick was checked against
  the code and re-measured in the browser on all six pages (96px cards, no inline guide,
  no semester bar, tinted select where intended, zero page errors) before it was marked.
  No code touched. Recorded gap: this lane never had frozen gates or an approval line;
  that was written down as-is, not backdated.

  - Bring the lane's records up to date so they match what shipped: summary cards, guide icon → modal, semester bar and week subtitle removed, week-select tint.
  - No code, no test suite. This exists so the next lanes stop reading facts that are no longer true.

- [x] **3. `summary-card-detail-copy`**

  **Status:** Done and verified 2026-11-14. 55/55 tasks. Card backs centred with
  `line-height: 1.45`, 4px bullet gap and the info button moved top-right; one
  column below 390px; the popup is a formula strip + term lines + optional
  footnote, from a new optional `detail` key. Verified on 48 page+width combos
  (max 3 lines, 0 spill) and all 30 popups incl. dark theme; no JS change needed.
  Committed in `b9673aa`. Recorded open item: the pre-existing "No red blocks"
  contradiction is unchanged (needs a product decision).

  - Extends the summary cards' detail copy. Uses `theme.css` and `partials/ui-summary-bar.blade.php`.
  - Builds directly on what task 2 just documented, and hands `theme.css` back afterwards.
  - Verify: cards still keep the three-line rule on their backs; no page errors.

- [x] **4. `modal-responsive-width`**

  **Status:** Done and verified 2026-11-14. `theme.css` gate cleared by your approval; lane applied
  (`f15ef96`), `status: applied`, 9 of 11 tasks ticked. Pop-ups now size to their content
  above a phone, the sheet is phones-only, and the phone footer/close button/pinned header and footer
  are fixed. Measured in a real browser at 390 / 600 / 601 / 700 / 768 / 1024 / 1360 — no pop-up grew,
  class details went 680 → 420 above 768. `T3b` stays deferred by your decision (the arrangement page's
  own confirm-dialog block lives in a file carrying another session's work); `T10` (archive) waits for your word.
  **Follow-ups you asked for, same day, all done:** the page-local 680 pins removed (same lane
  commit `f15ef96`), the grey facts strip no longer widens the pop-up (`308034d`), Confirm Cancellation and
  "Slot Too Short" raised to the shared 680 ceiling (`308034d`, `1756745`), the session list no longer
  clipped and both pop-up arrows aligned (`85ce7a3`, `6f197de`). Records: `review-log.md` + `apply-log.md`
  of each lane; new specs `tests/modal-responsive-width.spec.ts` (9/9) and `ModalConsistencyTest` (16/16).

  - Make the shared modals fit small screens without changing the desktop look.
  - Verify: modals at 320 / 390 / 768 / 1360px; no content cut off; close button still reachable.

- [x] **5. `arrangement-venue-picker-collapse`**

  **Status:** Done and verified 2026-11-14. Applied on your "yes. proceed.", 40/40 tasks,
  `status: applied`. The phone "Choose venue" sheet now folds by block: tap the bar to fold its
  rooms away, first block open, a search opens every matching block, and folding resets when the
  sheet reopens or you type. Sticky while scrolling, same pattern as the venue page.
  **Verified:** the sheet's own spec **7/7** on the blessed `:8030` invocation (run four times today),
  a real Tab reaching the bar with a visible focus ring and Space/Enter toggling it, both themes,
  0 page errors at 1440 and 390, and the desktop four-column picker untouched.
  **Committed:** `bd4a8e8` for the code — which also had to carry the 2026-11-12 searchable sheet it
  lives inside (that function existed in no commit), so its dialog markup, its spec and the
  arrangement changelog block went in with it; your instruction, recorded in the lane. Notes in
  `ef1e537`/`e42146e`/`e55255b`.
  **Two honest gaps:** `arrangement-mobile.spec.ts` gives 36 passed / 1 failed / 2 skipped, and that
  failure also fails with this lane's parts reverted, so it is pre-existing (the spec needs a freshly
  seeded database, which the testing one deliberately is not). And the pattern is now the **2nd**
  copy — a third page must move it into `theme.css`. The testing database was snapshotted before every
  run and its counts were identical afterwards.

  - Collapse the venue picker's category headers (second copy of that pattern; the third must move it to `theme.css`).
  - Does not use `theme.css`, so it can jump ahead of tasks 3–4 if that gate opens sooner.
  - Verify: picker opens, collapses and picks as before; no page errors.

- [x] **6. `summary-cards-button`** — the cards became one Summary button on small screens

  **Status:** DONE 2026-11-14 — the lane ran explore → propose → design → specs → tasks → apply →
  verify → **archive** (`.sdd/archive/2026-11-14-summary-cards-button/`, 7 reviewer rounds). Shipped as
  **`0514aaf`** + **`49c7290`**; nothing pushed.

  - **Seven** pages opt in, not eight: Replacement Arrangement renders no statistics cards at all (its
    commented-out block was deleted the same day), so it gets no button — its "Selection Summary" is a
    different panel.
  - At ≤768px each page's own `#summarySection` is **moved** (never copied) into one shared sheet emitted
    by `partials/ui-summary-bar`, opened by a bare chart icon beside the help "?"; ≥769px is untouched.
  - Explore corrected the premise: the cards never filled the first screen — they sit 970–3871px down,
    *after* the content — so the gain is reach + a shorter page, not a fold. It also found the plain
    variant (`student-class-replacements`) was the odd one out (three 114px columns at 390, no ⓘ,
    hover-only backs, a "Hover a card" hint on touch); fixed inside the sheet only.
  - Evidence: new spec **13/13**, `venue-timetable` TC36/37/58 **6/6**, `cancelled-slot-reuse` 15/15,
    zero page errors on all seven pages in both themes; four unrelated failures are pre-existing and
    attributed in the archived `sdd.yaml`.
  - Task 8's "Week summary sheet" should still extend this sheet rather than build a second one.

  **Moved (2026-11-15):** tasks **7–11** were not started today and now live in `# Sunday, 15 November — Task List`
  below. Their numbers are kept as they were, because the task text refers to them ("task 8", "tasks 6–10").

# Sunday, 15 November — Task List

## Notes for today
- **Start with task 12** (`table-column-trims`). It is the only one of the six whose **design is
  already frozen**, so it is the cheapest to finish (three small blade edits, one commit). The
  rest still need design, or specs, or both. Its number stays 12; this line is the running order.
- Carried over from **Saturday, 14 November**: tasks **7–11**. They keep their old numbers so the
  cross-references inside them ("task 8", "tasks 6–10") still work.
- Do not commit or push unless I say so. Only stage files the current task touches.

## Tasks

- [x] **7. `filters-button`** — the filters behind one labelled button

  **Status: DONE 2026-11-15 — applied, verified (PASS, 3 rounds) and archived to
  `.sdd/archive/2026-11-15-filters-button/`.** That folder holds all four frozen batches
  (`proposal.md`, `design.md`, `specs/filters-panel/spec.md`, `tasks.md` 23/23), the review log,
  the apply log and the verify report. The code shipped in one commit (`19a51d0`, plus the docs
  sync after it); the lane's own working folder is now empty of it, as an archived lane should be.

  - Landed so far: the measured case (on a phone the filters take up to **half** the first screen —
    My Replacements 265px bar + 120px chips ending 519px down; the student page only a quarter),
    the per-page control inventory (three different switch ids, two chip-removal mechanisms, three
    Reset meanings that are all "page defaults"), the option comparison, and the recommended shape:
    **one shared frame partial + a JS module that moves each page's own toolbar into it, with the
    labels as real markup in the Blade** (not built by JS).
  - **All four decisions were answered (user, 2026-11-14) and are written into the frozen proposal:**
    (1) the student page gets **no Reset** — the panel head is title + close; (1b) the count pill
    **follows the prototype**, so the old rule hiding it below 1500px is retired and it shows at every
    width; (2) the week option reads **"All Weeks"**, deliberately reversing the 2026-11-10 "SLOT"
    ruling; (3) the **second week dropdown is IN on all three pages** — so this is no longer a
    layout-only lane, it carries one new filter per page (predicate, chip, reset, remembered state).
  - **Four test files drive controls that moved behind the panel**, so they now open it first through
    `tests/helpers/filters-panel-helper.ts` (Playwright cannot click a control inside a hidden
    overlay): `status-tooltips-filter-chips`, `week-filter-preserved`, `request-completed-status` and
    `summary-cards-button`, plus the two search tests in the legacy `e2e/full-suite.spec.js`. Their
    pinned strings moved with the lane (`All Weeks`, `Completed hidden`, no `Active Filters:`), and
    `StudentReplacementHistoryParityTest.php` was amended **precisely** — `:143` allLabel, `:146` the
    now-false "no `Requested Slot Week:`" assertion, `:149` the predicate, the docblock — while
    `:144` (the Original chip text) and `:156` (the toggle test) stay green, because they survive.
  - **Built on 2026-11-15:** one shared partial holds the row (button · chips · count) and the panel
    frame; `FiltersPanel` in `ui-common.js` **moves** each page's own toolbar into it (ids never
    renamed, so the pages' own repaint and listeners keep working; the host and the panel body are
    `wire:ignore` because both staff pages re-draw on Approve/Reject). Labels above every control;
    Reset in the panel head on the two staff pages only; both week fields on all three pages, the
    requested week derived from the replacement date (the student page's from its 1-based
    `r.req.week`) — no backend change. `All Weeks`, `Completed hidden`, no `Active Filters:` prefix,
    and the count pill shows at every width (the old 1500px hide rule is gone).
  - **After you saw it live (same day, user review) four corrections landed:** the button, chips and
    count now sit on **one row** at ≥769 with the chips keeping their tinted frame (the prototype's
    look); a phone keeps the button + count on the first line and the chips below (all three cannot
    fit — the count alone is ~130px); Request Approval's **sort-hint line is deleted**; and on the
    student page the panel's × takes the right edge on its own, the **Hide COMPLETED** label sits
    directly above its switch, and opening the panel focuses the first field/select rather than the
    switch (whose tip used to pop on open).
  - Evidence: `tests/filters-button.spec.ts` **21 passed / 2 skipped** on five runs (both skips are
    data gaps that re-enable themselves), the four rewired specs 11/6/6/12, three stay-green specs
    green. Measured at 390×844: first card **519→226** (My Replacements), **541→226** (Request
    Approval, demo DB), **375→259** (Class Replacements); phone row 84px (114px on Request Approval,
    three chips wrap) against 120–126px before.

- [x] **8. `my-timetable-responsive-prompt`** — carried to 15 Nov 2026 (not started today)

  **Status: VERIFIED PASS 2026-11-15 (2 review rounds), then extended 2026-11-16** — 12
  prototype-comparison follow-ups, the two bug fixes (TODAY lands on today; a holiday keeps its
  classes), the "Proposed ⏳ replaces Cancelled" ruling, and the nine round-2 rulings; 42 receipts
  green; committed `7809c1b`. **ARCHIVED 2026-11-16** — the lane now lives at
  `.sdd/archive/2026-11-16-my-timetable-responsive-prompt/` (`status: archived`).
  Built and receipted: the phone agenda + day strip, the tablet transposed window, the Week
  summary sheet (SummarySheet gated off on these pages), both pages wired, the pin against the
  pre-change baseline, 19 responsive receipts, and the three amended files. Four defects the
  receipts found were fixed the same day (sheet painted only its range line; Enter/Space on a
  card opened nothing; Tab escaped the sheet; `_dialogAbove()` was true on every page). Six
  measured corrections the design did not predict are in the apply log. Next: `/sdd-verify`.

  **Pre-apply record (kept as written): ALL FOUR BATCHES FROZEN 2026-11-15.**
  `proposal.md` (4 review rounds), `design.md` (3), `specs/timetable-responsive/spec.md` (2) and
  `tasks.md` (2, T0a–T19 in eight batches) are frozen; `implementation_approved: false`. The earlier
  note below is the pre-freeze record and is kept as written.
  `.sdd/changes/my-timetable-responsive-prompt/`. The proposal has had **2 independent review rounds**
  (`review_rounds: 2`, `proposal_frozen: false`); the brief itself was iterated five times before it.
  `design.md`, specs and `tasks.md` are **not written yet**, and **no code** has been touched.
  The proposal records what is already shipped (guide dialog, week subtitle + progress removal, tinted
  week select, flip summary cards) so the lane does not redo them, and it names the layout target
  (`<768` one day, 768–1023 two days, 1024–1279 three days, ≥1280 unchanged except the five allowed
  exceptions). Two facts worth knowing before the next step: the shared grid builder
  `buildTimetableGrid()` has **five** live callers (the arrangement page uses its legacy `cellRender`
  path), so every shared change must be opt-in and pinned; and three brief §13 questions stay
  **open** — the print button on tablet, phone access to Cohort/Venue without the drawer, and
  whether Week summary is reachable from desktop.

  - **Scope widened 2026-11-15 (user): the lane now carries `student-my-timetable` as phase B.** The
    student timetable is the same page with different data — 0 `@media` blocks, the same six partials,
    the same `buildTimetableGrid()` — so it would have been a second lane re-exploring the same code.
    Phase A (staff) ships alone; phase B is one more blade edit on finished, flagged components. Its
    only real deltas: read-only (no Cancel Class wiring), a cohort chip, and a block whose status also
    carries Pending / approved Replacement — the one new design question, now proposal §9d.
  - **Prototype step done 2026-11-15.** Phase B's visual reference now exists:
    `ui-prototype/student-my-timetable-mobile-preview.html` — the staff preview with the four
    student deltas applied (read-only, cohort chip + two-item nav, lecturer in the block tip,
    `Class Hours`). On your word the **staff** preview changed the same day: the class card gained
    **three tiers** (time + venue primary, code grey secondary, one right-aligned note line last),
    **the subject name left the card below 1280px** (kept in the tip, the sheet title and the
    spoken label), and both previews' class pop-up now matches the decided shape (short tabs). Later
    the same day you corrected three things there: `Close` is **never** the filled primary (it
    reverses the 14 Nov "fill when alone" rule), a cancelled class's Status row reads **`Class
    Cancelled`** rather than a bare `Cancelled` (the block keeps the short word), and the row now has
    a real skin — `theme.css` never defined `.badge-cancelled`, so it used to print a transparent
    pill; it wears the grey `--color-surface-variant` / `--color-on-surface-variant` pair
    (`.badge-reason`'s, the same chip My Replacements prints), **not red**, because red is Public
    Holiday's and Conflict's. Checked at 320 / 402 / 768 / 1024 / 1280 / 1440 in both themes, no page errors. The brief
    and the lane's `proposal.md` were updated to match — including a new §15 for the student page
    and §10 for this alignment.
  - First move: write the SDD lane (proposal → design → tasks) from `ui-prototype/my-timetable-responsive-prompt.md`, then review it. No code until that is approved.
  - Its §9 "Week summary sheet" should reuse task 6's sheet and only add what the timetable pages
    need on top (the legend bar and the full semester range) — not a second sheet.
  - Why it sits before print: it restructures the timetable pages, and print has to attach to whatever structure exists.

- [x] **9. `timetable-print-v2`**

  **Status:** Done — applied and verified 2026-11-16, amended twice (the Print dialog + its switch),
  ARCHIVED 2026-11-17 (`.sdd/archive/2026-11-17-timetable-print-v2/verify-report.md`).

  - Real printing on the four timetable pages that already show the printer button.
  - Last on purpose: built after task 8, it is built once.
  - Ask before starting: if you would rather bump this up, it is the only swap worth making.

- [x] **10. `details-modal-consistency`** — the details pop-up, one shape on three pages

  **Status:** DONE 2026-11-17 — applied and committed (`051ae2d`, plus `ab5e7a2` for the
  countdown decision). Lane `.sdd/changes/details-modal-consistency/` (compact shape, one
  review round). The three pop-ups now share `Requested` · `Original` · `Class Info` · `Status`,
  a one-badge-led-fact strip (`Status` on top, `Conflict` last; the PL's last row is `Slot
  Validity`), a `Days Left` row under `Day & Date`, and one filled primary that is never `Close`.
  D1 was the user's call and is settled: an approved or completed row no longer reads `Overdue`
  (`ab5e7a2`). Verification: measured live at 402 and 1440 — four tabs on one row (356px of 356),
  strip order, `Days Left` in all four states; `replacement-home-working-days` 8/8 (2 red at HEAD,
  green now). **Open:** `request-approval.spec.ts` is 4 passed / 6 failed **before and after** this
  lane — the queue has no pending rows, so five cases have nothing to click and two assert a
  pending row exists. Not archived yet; archive on the user's word.

  - Three pop-ups, not three features: My Replacements' Replacement Details, Request Approval's
    Request Details, and the student Replacement Details. One `DetailModal` renderer in
    `ui-common.js`, one set of rules in `theme.css`, and only the content per blade.
  - **The CSS half is already live** (the prototypes read the same stylesheet): the one-primary
    footer, the one-fact-per-row strip with its 112px label column, the visible reason badge and the
    footer pinning. Only the three blades are still on the old shape — do not rebuild the CSS.
  - What the blades need: the short tab labels (`Requested · Original · Class Info · Status`), a
    `Class Info` tab on both staff pop-ups, the strip reduced to one badge-led fact per row with
    **Status on top and Conflict at the bottom** (Request Approval: `Slot Validity`), the `Days Left`
    row under `Day & Date`, and the footer's single filled primary.
  - Blocked on nobody; can run beside the filters explore because it touches different files.

  **Not this lane (2026-11-15):** the **class details pop-up** of the two timetable pages
  (`partials/ui-class-detail-modal` + `openClassModal`) is a different pop-up. It already carries
  the same tab labels (`Class Info · Schedule · Original · Status`), and since 2026-11-15 its
  `Close` is never filled — **the same rule this lane now applies** (see item 10 of
  `prompts/pi-prompt-details-modal-consistency.md`, "Decisions taken later": `Close` is never the
  filled primary, which reverses the earlier "Close fills when alone"). The two pop-ups now agree, so
  there is nothing to "fix" in either direction; the class pop-up's own lane is task 8.

- [x] **11. `cards-and-badges-responsive`** — the replacement cards, the tablet layout and the badge system

  **Status:** DONE 2026-11-17 — applied in four commits (`1e92ba2`, `57a3adf`, `33afe3a`,
  `fd55f40`, `e08b457`). Lane `.sdd/changes/cards-and-badges-responsive/` (compact shape, one
  review round). Shipped: the three card shapes (`ORIGINAL`/`REQUESTED` bands with week pills,
  the PL's id · proposer · status head), one badge pill language at 22px, the **capability-gated**
  48 × 48 touch floor (dropdown `<option>` values exempt by the user's 2026-11-17 ruling), the
  PL's one-line band at ≥600, the student's `X of Y match your filters` pill, and the badge
  consumer audit. **Recorded gaps:** the PL's pending card has never been seen with live data (no
  pending rows exist), so its shape is verified against the drawing only; and the filter chip
  stays 32px on touch because the `filters-button` lane's measured trade outranks this brief —
  the conflict is written into `theme.css` so nobody "fixes" it by accident.

  - **Cards on all three pages.** Student and My Replacements: head band, slot band(s) with the
    `ORIGINAL` / `REQUESTED` label and the week pill, a 2×2 fact grid, a foot band (conflict badge +
    lecturer or cohorts), plus the student's `X of Y match your filters` pill. My Replacements shows
    the `REQUESTED` band whenever the row has a proposal. Request Approval: `#35` as a neutral id
    pill, the proposer's name in the head, the **requested slot only**, and the course + request age
    in the foot — no cohorts, no original date.
  - **Tablet ≥600:** the student and staff cards put their two slot bands side by side and a row with
    no proposal prints `REQUESTED · Not requested`; Request Approval flattens its single band to one
    line with the countdown at the right edge. This replaces the dead middle the cards used to show
    at iPad width.
  - **Badges on all three pages** — Request Approval included, because its prototype still carries the
    polish and no other lane owns it. Geometry, spacing and type only: no colour, background or
    text-colour change, no meaning removed, no new icons
    (`prompts/extra tasks/Improve the UIUX of the badges.md`).
  - The three `<style id="badge-polish">` copies are a sketch; the finished rules belong in
    `theme.css` once. The `.detail-facts` rules inside the current copy are stale and must not be
    carried over.
  - Two traps the lane must respect: the card is the click target and `data-request-id` is how the
    cancel flash finds it; and Request Approval's bulk-select flow (`toggleRowSelect`) must keep
    working when the card markup changes.
  - **Touch sizing (2026-11-15, user):** 48 × 48px on **touch-capable devices only**, gated on
    `@media (any-pointer: coarse)` — never on width, so a touch laptop or an iPad Pro at any width
    still qualifies while a mouse-only desktop keeps today's compact sizes. Applies to the card, the
    Approve / Reject / View buttons, Filters, chips, Clear all, pagination, summary info and inputs;
    the chip's little "x" is the one approved exception (the 26px glyph inside the 48px chip).
    Badges never inherit the floor. The three prototypes already carry the block — copy it, do not
    invent a second one.

  **Not these cards (2026-11-15):** the timetable **class blocks** (My Timetable, Student My
  Timetable) are a separate component with their own three-tier rule (time + venue primary, code
  grey, note line last) and they belong to task 8 — do not fold them into this lane.

- [x] **12. `table-column-trims`** — the three list tables stop printing what the pop-up already shows

  **Status:** DONE 2026-11-17 — applied (`579bc1d`, plus `de24705` for two receipts that were red for
  the wrong reason), verified **PASS** and **archived** to
  `.sdd/archive/2026-11-17-table-column-trims/`. Specs and tasks were folded into one artifact and
  reviewed once, per your cost ruling. Shipped: Request Approval's Staff cell is the name alone (the
  ID and the copy-email line now live only in the pop-up's Class Info tab), and all three Course
  cells read `AMSE2003 (L)` on one line — on Request Approval that cell *gains* the type.
  `CourseColumnTipTest` 4/4 (17 assertions); measured at 1024 the Staff column went 188 → 146px,
  Course 250 → 118px and the table 1451 → 1277px. The four DOM specs read 12 failed / 27 passed
  **identically** before and after; two of those reds were repaired, the other ten belong to other
  surfaces and are attributed in the lane's apply log and verify report.

  - **What it changes, two trims across three pages.** (1) Request Approval's Staff cell prints the
    proposer's **name only** — the `(staffId)` and the click-to-copy email leave the queue (they stay
    in the pop-up's Staff row). (2) All three tables' Course cell prints **`AMSE2003 (L)` on one line**
    — code plus type, the subject name gone (it stays in the pop-up subtitle and in search). The
    student page is included, which is why this is a lane and not a small-change exemption.
  - **Not bad news for the reader:** Request Approval's course cell actually **gains** the class type
    (it printed none before), so its changelog must say so — the rest is trimming.
  - **Constraints already verified:** `tests/Feature/CourseColumnTipTest.php` pins the *shape* of two
    of the rewritten cells with source regexes — keep those shapes, never weaken the test.
    `.cell-name` must be removed rather than emptied (it is `display: block`, so keeping it would put
    the type on a second line). No `theme.css` line and no new class.
  - **Deliberately out of scope:** column widths (My Replacements is fixed-layout with a 16% course
    column, so the trim shortens rows but frees no pixels), the cards, the confirm dialogs, the
    notification rows, the print view.
  - **Open decision left for you:** the course name's last home. A cell hover-tooltip would reverse
    the 2026-11-09 "no hover on the cell" ruling *and* flip a passing test — the design recommends
    pop-up only.
  - **Next move:** specs → tasks → apply (three blades, then the three page changelogs, one `ui:`
    commit).

## End-of-round rule (every round)

- **After each round:** bring the **next round's SDD lane** records up to date, then review and sync the files in `prompts/for-ai-to-read/`.
- For that sync:
  - Update only files that contain outdated or missing information.
  - Make sure all important changes and current project details are accurately reflected.
  - Avoid unnecessary edits or duplicate information.
  - Do not modify unrelated files.
- **Goal:** keep that folder accurate and up to date with the latest project changes.
