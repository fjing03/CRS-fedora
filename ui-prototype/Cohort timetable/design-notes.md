# Cohort Timetable responsive prototype

> **The full design contract now lives in the prototype's own header comment** —
> 17 numbered sections: purpose/scope, assets and safety, layout bands, chrome,
> the SelectDd clone, cards, notices, summary, pop-ups, a11y, touch & hover, print,
> data/scenarios, round history, evidence/limits, the queued system-wide rollout and
> the port checklist. Read that first; this file keeps the longer reasoning and the
> measured receipts.

## Scope

New standalone prototype: `cohort-timetable-responsive-preview.html` in this folder.
The brief, current-system screenshots, all existing prototypes, live CRS source and
shared assets are unchanged. Nothing was committed or pushed.

Uses the repo's existing `public/css/theme.css`, logo and `public/js/mock-data.js`
read-only. No Laravel server, account, API, database or storage is used. The sample
clock is **Monday, 16 Nov 2026**; the sample viewer is Programme Leader 5425.

## Issues found in the screenshots

Both supplied images were inspected: **CURRENT Mobile View** and **CURRENT iPad
Mini View**.

- Every day becomes a tall separate panel. Finding Friday means scrolling past
  Monday to Thursday; empty Saturday/Sunday panels make the page longer still.
- The iPad Mini repeats the phone stack instead of using its width to compare days.
- Course code, room and time spread across one row with little hierarchy. On the
  phone the time is small, while large day labels take up space above each row.
- The toolbar wraps into several lines, with small arrows and selectors beside
  larger Today/Help controls. Touch targets are inconsistent.
- The legend and ownership explanation sit after the entire week. A reader may
  not understand a thick/thin border until reaching the bottom.
- The mobile capture shows the navigation bar over the middle of the timetable.
  A still image cannot prove the cause; the new preview explicitly pins navigation
  to the top and reserves space for it.

No desktop screenshot was supplied. Desktop structure was checked against the
live cohort Blade, shared theme and grid builder **as source files**, without
logging into the live app. Desktop pixel parity has not been claimed or measured.

## Design decisions

| Screen/input | Layout |
|---|---|
| 320–743px | One selected day's content-height class cards |
| 744–1023px | Two-day vertical timeline |
| 1024–1279px | Three-day vertical timeline |
| At least 1280px, touch-capable | Four-day vertical timeline |
| At least 1280px, mouse-only | Original day-row, horizontal-time weekly table |

**Why 744 rather than 768:** an iPad Mini in portrait is 744 CSS pixels wide.
Treating it as a phone would repeat the limitation shown in the supplied image.
**Why four days on a wide touch screen:** an iPad Pro should not revert to small,
mouse-sized controls merely because it crosses the desktop width threshold.

The desktop keeps the familiar toolbar order (faculty, cohort, week, Today,
Print), status colours, weekly table, ownership legend and five summary cards.
Its internal timetable scroll is retained rather than squeezing existing blocks.
The brief's percentage figures are design goals, not measurable scores achieved
by this prototype. The small prototype-only footer makes its mock scope clear.

Other decisions:

- Faculty and cohort remain native selectors for familiar keyboard and OS touch
  pickers. Taught cohorts carry ✓ and a blue option style. Full selected names are
  available in focus/hover tips; the modal also names the class's cohort.
- Faculty/cohort selection scrolls away. Only week controls and the day strip
  remain sticky below the top bar, leaving more space for classes.
- Cohort changes preserve the selected week/day. A faculty change auto-picks the
  first taught cohort, or presents a clear selection state when there is none.
- At 320–374px, Today wraps rather than clipping the week label or shrinking touch
  controls. All fourteen week labels are measured by the browser tests.
- Cards reuse the reference prototypes' hierarchy: time + venue first, course code
  + type below, status/date note last. Full names and lecturers stay in details
  and accessible labels. Both overlapping cards get an overlap explanation.
- Thick border = your class; thin border = another lecturer's, independently of
  status colour. Only your own classes expose mock cancellation/arrangement.
- A holiday adds its notice **above** the cards; it never replaces them. A whole
  empty week has one panel and does not falsely claim all classes were cancelled.
- Summary counts the **whole selected week**, not the visible day window. Its
  actual cohort-page cards are Total Classes, Teaching Hours, My Teaching Classes,
  My Teaching Hours and Conflicts. Calculation follows live `computeSummary`:
  cancelled classes leave totals, pending is the small +N, holiday/clash records
  remain counted and also appear under Conflicts. This existing calculation is
  explained rather than silently changed during a responsive prototype task.
- Modals have a pinned header/footer and scrolling body. Close stays outlined.
  Focus is trapped, background is inert, Escape closes and focus returns.
- Print uses local browser printing with the full selected week, legend and
  summary; no existing print prototype or live print flow was changed.
- All colours use existing theme tokens. No dependency was added.

## Round 2 (user, same day)

1. **No hover effect on a touch screen.** Every hover style on this page now sits
   inside `@media (hover: hover) and (pointer: fine)`. theme.css's own hover rules
   are neutralised for touch in `@media (hover: none)`, which restores each
   control's shipped resting state (week arrows, print, week select, Today, theme
   toggle, bell, nav/nav drawer, modal buttons, table cells, day column, holiday
   badge, event blocks, summary card and its ⓘ). Gate is the *primary* input, so a
   touch laptop driven by a mouse keeps its hover. The hover tip engine no longer
   listens to pointer events on touch (focus still shows every tip), and the
   theme's `::after` block tooltip is switched off this page because these blocks
   carry `data-tip`, not `data-name`/`data-tip2` (it printed a bare ` · `).
2. **The cohort chip beside the title is removed** (`#previewCohort`) — the cohort
   now reads once, in its own selector, plus the selector's tooltip and the week
   summary subtitle. No JS left referencing the deleted node.
3. **✓ + bold now mark taught faculties as well as taught cohorts**, and both
   survive in the *closed* control: the ✓ rides the option text, and the select
   itself takes `.is-taught` (bold, primary colour, primary-container fill) for the
   current selection. A faculty is "yours" when at least one of its cohorts is,
   which is the live page's rule. The guide explains the marker.
4. **Today has its own glyph.** The bullseye (`i-target`, circles) said "locate";
   it is replaced by a calendar page with the current day filled (`i-today`), used
   by the word+icon button on tablet/desktop and the icon-only button on phones.
   Its `aria-label`/tip name the real date (`Go to today, Monday 16 November 2026`).

Checked after the change: inline JavaScript parses, the style block's braces
balance, and no `#previewCohort` reference remains. **No browser run was made for
round 2 (the user asked for none)**, so the earlier receipt still describes
round 1 only.

## Round 3 (user, same day)

**The closed-day notice is now a title plus one optional detail row.**

- The date line is **gone** from the notice — the day heading above it already
  names the date, so the panel stops repeating it.
- A public holiday's name moved out of the title into **its own row**, in the
  existing `.empty-text` style, directly under the title:

  ```html
  <h3 class="empty-title">Public Holiday</h3>
  <p class="empty-text">Deepavali Holiday (In Lieu)</p>
  ```

- **A Sunday prints no detail row.** It has no holiday name to show, so the row is
  skipped entirely (never an empty `<p>`) and the existing `No classes on Sunday`
  title still carries the message. Nothing is added to Sunday that was not there.
- The holiday notice stays **additive**: a holiday day keeps its class cards below
  it, and the next-class hint still rides inside the panel.
- The panel is still phone-agenda only; the tablet window and the desktop grid
  keep their red column + `Public Holiday` badge, unchanged.

Checked after the change: inline JavaScript parses, the style block's braces
balance, and the notice/empty-state guard cannot render two panels at once.
**No browser run was made (the user asked for none).**

## Standing rule — no hover on touch screens (every page)

**User ruling, 2026-11-16. It is not a prototype quirk: every CRS page follows it.**
Recorded in `CodingMAIN.md` §10.0 rule 9, `my-coding-rules.md` rule 9, and the
prototype's own header comment.

The recipe, in order:

1. Wrap **every** hover style in `@media (hover: hover) and (pointer: fine)`.
   Gate on the primary input, never on width, so a mouse-driven touch laptop
   keeps its hover.
2. Add a `@media (hover: none)` block that restores each component's **resting**
   state from `theme.css`: week arrows, print, week select, Today, theme toggle,
   bell, nav bar, nav drawer, modal buttons (close / outline / action / danger),
   table cells, day columns, day badges, event blocks and summary cards (whose
   flip must stay flipped after a tap).
3. Gate **hover-only tooltip listeners** the same way. `focus`/`focusin` still
   shows the identical tip on every device — that is the keyboard and
   screen-reader path, and it must not be removed.
4. **Focus styles are not hover.** Leave focus-visible rings and focus tips alone.
5. `theme.css` stays read-only for the port: the neutralisers live in the page's
   own styles until they are promoted deliberately.

Reference implementation: the `(hover: none)` block in
`cohort-timetable-responsive-preview.html`. Porting checklist for the seven live
pages: my/student/cohort/venue timetables, My Replacements, Request Approval and
Class Replacements (plus the two auth pages).

## Round 4 — the Today icon

Round 2's calendar-with-a-dot was rejected as **too common and too close to
"Earliest bookable"** (whose shipped glyph in `partials/ui-today-btn` is a
calendar with a chevron). Round 4 replaces it with a **"you are here" pin with a
filled dot** — the one silhouette no other CRS control uses, which reads as
*where the reader is now* and survives at the real 17 px. The button's `aria-label`
and tip still name the date (`Go to today, Monday 16 November 2026`).

Rejected on the way, and why: a bullseye (generic "locate"), a calendar + dot
(Earliest bookable's family), and an open-arc "recentre" arrow (reads as refresh
at 17 px). Checked as a static raster at 192 px and at the shipped 17 px.

## Round 5 — the faculty/cohort controls are now a 1:1 clone

**What I got wrong in round 2/3:** I re-styled the native `<select>` (a bold
`is-taught` trigger, a `✓` typed into the option text, visible *Faculty* /
*Cohort* labels, and friendly respaced names like `DFT1 (S1)`). The system does
none of that. Corrected against the live blade
(`resources/views/livewire/cohort-timetable.blade.php`) and
`CURRENT fac cohort dd design.png`:

| Live behaviour | Now in the prototype |
|---|---|
| Bare `.semester-bar` chips, **no visible field labels** | labels removed; `aria-label` + `title` carry the names |
| Trigger text is the **code**: `FOCS`, `DFT1(S1)G1`, `RSD2(S1)G2` | derived from the registry ids (no invented data) |
| Full name rides `title` / the row's `data-tip` | option `title` = `DFT1(S1)G1 — Diploma in Information Technology` |
| `mousedown` opens the page's **SelectDd panel**, native popup suppressed | cloned: rebuild rows from the live options on every open |
| Taught rows: **bold blue + CSS `✓`**, nothing on the trigger | `.select-dd-item-taught` with the blade's `::before { content: '✓ ' }` |
| Sticky hint line: `✓ bold = cohorts you teach` / `✓ bold = you teach here` | same wording, only when a taught row exists |
| `1.5px` primary ring on the open trigger | `.semester-bar select.select-dd-open` (box-shadow only) |
| Position under the trigger, clamp to viewport, flip up, close on outside click / Escape / page scroll / resize, 300 ms open guard | copied point for point |
| Rows are non-focusable divs; the native `<select>` keeps keyboard + OS picker | same (keyboard users keep the native control) |

The only deliberate differences: the row hover sits behind hover-capable media
(standing rule above) and rows keep a 48px floor under `any-pointer: coarse`.
The toolbar is now **one card** with the live stack — selects row, full-width
week row, `Today` + `Print` row — not a sticky bar, and Print stays visible on
phones, because that is what the live page does.

## Round 5b — richer sample data

The shared registry flags few statuses, and mostly on other cohorts' weeks, so
the default view could not show them together. `SAMPLE` in the page now fills
**DSF1(S1) · Week 9 (16–22 Nov 2026)** with 18 classes across Mon–Fri:

- **normal** (own and others') — 11 blocks, including an 18:00 evening slot;
- **pending** — 3 requests (Tue 11:00, Wed 17:30, Thu 10:00), each with its
  “Pending since …” note and, for the owner's, Requested At/By in the pop-up;
- **replacement** — 2 approved landings (Mon 13:00, Thu 15:00) with the
  “(Replaced for …)” note and an Original tab;
- **cancelled** — 1 plain (Fri 08:00) and 1 already carrying **(Proposed ⏳)**
  (Tue 14:00), which correctly suppresses the duplicate `✕ Cancelled` word;
- **conflict** — 1 (Wed 09:00, B106 double-booked) with its reason in the tip;
- own classes (thick border + mock actions) vs other lecturers' (hairline).

Saturday and Sunday stay empty so the empty-day/off-day notices still have a
subject; every other cohort and week keeps reading the shared registry
unchanged, and `?scenario=overlap|empty-day|empty-week|proposed` still layer on
top for the reviewer.

## Round 6 — the Today icon is back to the system's own

The round‑4 pin is **reverted on the user's word**. The button now uses the
exactly shipped glyph from `resources/views/partials/ui-today-btn.blade.php`:
three concentric circles on the 24px grid, `16 × 16`, `stroke-width: 2.5`. This
matches the live screenshot and keeps the clone honest — no redesign.

What is still recorded for context: the shipped **Earliest bookable** variant
uses a *calendar with a chevron*, so the bullseye and that button never collide.
Both abandoned attempts (a calendar with a dot, an open-arc "recentre" arrow)
are gone from the file.

The tip/`aria-label` still name the real date (`Go to today, Monday 16 November
2026`) — that is a wording detail, not a restyle; say the word and it goes back
to the live `Go to current week`.

## Round 7 — print is desktop only, and two system-wide wording items are queued

### 1. Print hidden outside the desktop view (applied here)

`Print` is now **desktop only**: it is hidden in the agenda (phone) and window
(tablet, including a wide touch screen) modes and appears only in the mouse
full-week desktop toolbar. It is a deliberate deviation from the live page,
which shows Print at every width; recorded in the file's §4 and the round history.

### 2. Public Holiday notice — the shape is confirmed (already live here)

The user's screenshot of the live mobile page shows exactly the round‑3 shape:
`Public Holiday` as the title, the holiday name on its own `.empty-text` row, no
date line, then the divider and the next-class hint. **No code change was needed**
for the prototype; this is recorded as the target for the rollout below.

### 3. QUEUED — system-wide, all four timetable pages, small screens only

**Not applied. Recorded only, per the user's instruction.** Also mirrored as
§16 of the prototype's header contract.

- Pages: My Timetable · Student My Timetable · Cohort Timetable · Venue Timetable.
- Surface: the smaller/mobile view only (the page's own mobile layout), not desktop.
- **A. Public holiday notice** → title `Public Holiday` + `<p class="empty-text">`
  with the holiday name, **no date line**, divider + next-class hint under it,
  and the day's class cards kept below the notice (additive).
- **B. Empty day / Sunday title** → simply `<h3 class="empty-title">No Classes</h3>`,
  replacing today's `No classes on Sunday, 22 Nov 2026` form on those surfaces.
  No detail row was specified; the day heading / day strip already name the day
  and date. **Not confirmed, so ask before building it.**
- Both need a real SDD lane when they go ahead: four blades, one shared empty-state
  shape, page changelogs, and the existing specs that pin the old strings
  (`ui-regression`, the timetable specs and the student parity test all read
  these titles).

## Preview

From the repository root:

```bash
python3 -m http.server 8091 --bind 127.0.0.1
```

Open:

`http://127.0.0.1:8091/ui-prototype/Cohort%20timetable/cohort-timetable-responsive-preview.html`

The HTML can also be opened directly while retaining its relative asset paths.

Useful sample URLs:

- `?week=7&day=0` — Deepavali notice plus Monday classes.
- `?scenario=overlap` — a pending fixture crosses two neighbouring classes.
- `?scenario=empty-day` — no classes on the selected day.
- `?scenario=empty-week` — one truthful empty-week panel.
- `?scenario=proposed` — Proposed note without a duplicate Cancelled word.
- `?cohort=dsf2s1&theme=dark` — another cohort and dark theme.

Weeks in URL parameters are zero-based. Scenarios are local review fixtures only;
normal browsing derives its events from the existing shared mock dataset.
Cancellation ends with an explicit “No class or request was changed” message.
Navigation/bell/arrangement destinations are safe mock notices, not real links.

## Browser checks

Run against the static server only:

```bash
node 'ui-prototype/Cohort timetable/check-responsive.cjs'
```

**Result: PASS — 72 width/theme/input combinations and 10 interaction groups.**

- Widths: 320, 360, 390, 430, 600, 743, 744, 767, 768, 820, 834, 1024, 1180,
  1279, 1280, 1366, 1440 and 1920; light/dark; mouse/touch.
- Zero page-level horizontal overflow, correct layout bands, seven day buttons,
  correct five summary labels and all fourteen week labels fitting their fields.
- At least 48×48 for visible enabled buttons/selects on touch, including wide iPads.
- Selection, faculty-clearing state, Today's exact day/week, state across resize,
  keyboard day-button focus, ownership-limited actions and mock confirmation.
- Summary flip/explanation/back, modal/drawer focus traps, Escape/focus restore.
- Holiday cards, all overlap participants, Proposed wording, empty day, and an
  empty week/overlap fixture across agenda, tablet and desktop modes.
- Print-media full week with all nine default-cohort events and screen restoration.
- Shared `window.MockData` unchanged by interaction tests; no page errors and no
  non-static or writing network requests. Test guard refuses ports 8000/8001/8030.

Receipt: `/tmp/cohort-preview-receipt.json`. Screenshots were inspected for phone,
iPad Mini, tablet, wide touch and mouse desktop, plus the summary dialog. Browser
checks use Chromium emulation, not physical iPad/Safari certification. Native
picker presentation and actual printer output still depend on the browser/OS.
