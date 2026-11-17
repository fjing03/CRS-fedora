# Task 11 — `cards-and-badges-responsive` (compact lane)

> **Source of truth is the brief**, not this file: `prompts/pi-prompt-cards-and-badges-responsive.md`.
> This artifact records only what the brief leaves open, what has already shipped, and the task
> list. It is deliberately one file — proposal, decisions, acceptance criteria and tasks — instead
> of the four-batch ladder, on the user's instruction of 2026-11-17 to cut the SDD cost.

## 1. What this lane is

Three list surfaces — My Replacements, Request Approval, Class Replacements (student) — get the
card family, the badge system and the touch floor their prototypes already draw. Not a redesign:
every requirement below is quoted from the brief or the drawing.

## 2. Already shipped, before this lane opened (2026-11-17)

The survey found the cards did not match their drawings, and the user had two of them fixed on the
spot. The lane owns what is left; it must **not** re-do these.

| Commit | What |
|---|---|
| `1e92ba2` | My Replacements' phone card: head band, `ORIGINAL` band, `REQUESTED` band (or *Not requested*), foot. Ported from the drawing's `wrapHomeCard`. |
| `57a3adf` | Request Approval's phone card: id pill · proposer · status, one labelled `REQUESTED` band with the slot-validity mark, foot with course + age (`wrapRequestCard`). Class Replacements' card: `ORIGINAL`/`REQUESTED` side by side with week pills, 2 × 2 facts, single-meridiem times (`patchSlotRows`). |

Consequence for this lane: **the card shapes are done at 390 and ≥600 for My Replacements and the
student page**. What the brief asks beyond them is badges, the touch floor, the count pill, and
Request Approval's ≥600 flattening.

## 3. Decisions

### T1 — Select controls take the 48px floor; their `<option>` values are skipped *(user, 2026-11-17)*

The three pages carry `#statusFilter`, the two week dropdowns and the `Rows:` page-size select.
Their `<option>` items are **native browser UI** — an OS popup that CSS cannot size, and one
`theme.css` only ever colours (`.filter-select option`, line 1264). So the floor applies to the
`<select>` element, never to its options. This sits beside the brief's own two exemptions (the
chip's 26px "x"; badges never inherit the floor).

### T2 — Badge geometry lives in `theme.css` once, and the sketch's `.detail-facts` rules do not travel

The three prototypes carry a `<style id="badge-polish">` copy each. The brief calls them a sketch and
says the finished rules belong in `theme.css` once — and that the `.detail-facts` rules inside them
are stale. So: take the badge rules (one pill language: `inline-flex`, centred, `border-box`, `min-height: 22px`,
**`max-width: 100%`** (the line that keeps a long cohort code inside its pill), `padding: 3px 10px`,
`border-radius: var(--radius-pill)` — the token, not a literal `999px`, since `.badge` already uses it —
12px/600, `line-height 1.2`), the
`.badge-outline` border-box fix and `.slot-badge { vertical-align: -3px }`; **drop** everything about
`.detail-facts`, `.df-item`, `.df-label` — task 10 owns that strip.

Live today: `.badge { display: inline-block; padding: 4px 10px; vertical-align: middle }` with no
`min-height` and no `line-height`, which is why a filled and an outlined badge stand at different
heights and why the survey's I3 found them off-centre.

### T3 — No colour, background or border-colour may move

The brief is explicit. This lane is geometry, spacing and type only. The regression check is that
`theme.css`'s badge palette block is byte-identical after the change.

### T4 — The count pill is SPLIT, because a frozen ruling already owns the staff pages

**Withdrawn as first written** (round 1, blocker 1): I claimed "one shared string, so all three or
none", and that was false on both counts.

- `filters-button` (frozen, applied, archived 2026-11-15) ruled the pill exists on the **two staff
  pages** with the wording `Showing X of Y classes` / `Showing X of Y results`, and that **the
  student page has no count and gets no pill**. `updateResultCount` is parameterised by `label`
  (`ui-common.js:2246-2249`), and `replacement-history.blade.php` has no `#resultCount` at all.
- The brief grants `X of Y match your filters` to the **student page only** (§8).

So:

- **T4a (this lane)** — the student surface gains the pill, `X of Y match your filters`. It is
  **new work with a new count source** (the student list has no counter today), which is why it gets
  its own task and its own file list rather than riding T11c.
- **T4b (NOT this lane, needs the user's word)** — re-wording the two staff pills. The house format
  was set on 2026-10-30 (`bb46a19`) and the honest-zero rule comes with it; changing it is a ruling,
  not a drift fix. **Left untouched; raised as a question at the end of the lane.**

### T5 — Request Approval's pending shape cannot be seen with live data, and this lane says so

The queue holds **no pending requests** today (`request-approval.spec.ts` fails on exactly that: it
demands at least one). The pending card is therefore verified against the drawing only. This lane
does **not** create a pending request — that is a demo-DB write and needs the user's word. Recorded
as an open check, not as a pass.

### T7 — The width-gated 40px block loses `.badge`, and `.page-btn` moves to the capability query

Round 1, blocker 2: `theme.css:5065-5076` still carries the **pre-2026-11-15** law —
`@media (max-width: 1024px) { … .page-btn, .badge { min-height: 40px; min-width: 40px } }` — gated on
**width**, which `my-coding-rules.md` #9 superseded in favour of `@media (any-pointer: coarse)`.
While it stands, every badge is 40px at ≤1024 on *any* device, so AC3 ("badges stay compact") and
AC4 ("filled and outlined the same height") cannot pass, and the survey measured the symptom live at
390 (I3).

Decision: **remove `.badge` from that block** (the brief is explicit that badges are not buttons and
never inherit a floor), and **move `.page-btn` out of it into the capability query** rather than
deleting its target — a pagination button keeps a real 48px target on touch and returns to its
compact size on a mouse. Visible consequence, recorded on purpose: on a **narrow mouse window**
(≤1024, mouse only) badges drop from 40px to their natural ~22px and pagination shrinks to its
compact size. That is the standing capability law finally applying, not a regression.

### T6 — Request Approval ≥600 flattens its band to one line

The other two pages put their two bands side by side at ≥600 (shipped). Request Approval has a single
band, so at ≥600 its 2 × 2 becomes one line with the countdown keeping the right edge — the drawing's
own rule, ported with the card.

## 4. Acceptance criteria

- **AC1** Every control named in the brief's §Touch sizing measures **≥ 48 × 48** under
  `@media (any-pointer: coarse)`: the card, Approve / Reject / View, Filters, every `filter-chip`,
  Clear all, `page-btn`, the summary info button, the search input, the select controls and the date
  field. **Options inside selects are exempt (T1).**
- **AC2** On a mouse-only device every measured size is **unchanged** — Filters 34px, chip 22px, row
  35px at 1600px, exactly as the brief's regression check says.
- **AC3** The chip's "x" stays a ~26px glyph inside its 48px chip (`theme.css:5495-5497` already
  ships the 26px remove); badges keep their compact ~22px on touch **and** at ≤1024 on a mouse —
  which requires T7, since the old width-gated block inflates them to 40px there.
- **AC4** Filled and outlined badges are the **same height** and their text is centred, measured on
  all three surfaces (My Replacements, Request Approval, Class Replacements) at 390, 768 and 1440.
  Method: `getBoundingClientRect().height` of one `.badge` and one `.badge-outline` per page, equal
  within 0.5px, and `align-items: center` on both.
- **AC5** `.slot-badge` sits on the text baseline: **−3px everywhere**. The drawing disagrees with
  itself (its polish rule says −3px, its card rule says −2px, and `57a3adf` ported the −2px), so this
  lane settles on one number, −3px, and updates the card's rule to match — otherwise a
  higher-specificity card selector would silently win and the AC could not be verified.
- **AC6** No badge colour, background or border-colour changes — the palette block is untouched.
- **AC7a** The **student** surface shows `X of Y match your filters`, where X is the visible row
  count and Y the total after filters.
- **AC7b** The two staff pills are **unchanged** — `Showing X of Y classes` / `… results` — because a
  frozen ruling owns them (T4b).
- **AC8** At ≥600 Request Approval's band is one line with the countdown at the right edge; the other
  two pages keep their side-by-side bands.
- **AC9** The card stays the click target: `role="button"`, `data-request-id` intact, and it opens
  the details pop-up by **click and by keyboard** (Enter and Space), all three surfaces.
- **AC9b** Request Approval's bulk-select (`toggleRowSelect` / `toggleSelectAll`) still works after
  the markup change.
- **AC10** The enlarged targets are **real boxes**, not overlapping padding: two neighbouring chips,
  and two neighbouring action buttons, must each receive their own tap (assert via
  `elementFromPoint` at the shared edge).
- **AC11** **No type size moves.** The two lower type tiers keep their approved sizes; a target size
  is not a type size.
- **AC12** **Printing is unaffected**: the four timetable papers still export one A4 landscape page
  with the same content (a badge rule that leaked into print would show here).
- **AC13** Badge consumers survive the shared `.badge` rewrite: `.badge-sm`, `.badge-capacity` and
  every fixed-width or `nowrap` parent are checked and named in the verify note; the stale comment
  quoting "the .badge base (4px 10px)" (`theme.css:5897`) is updated.

## 5. Tasks

| # | Work | Files |
|---|---|---|
| T11a | Port the badge geometry into `theme.css` once (T2, T3) | `public/css/theme.css` |
| T11b | The 48px touch block, **in each page's own `<style>`** as the brief requires ("the three blades need the same block in their own `<style>`"), scoped to that page's controls — not an unscoped `button, select, input` block in `theme.css`, which would reach the nav bar, the notification bell and every other page (round 1, blocker 3). Includes the bulk-select checkbox the sketch grows. | the three blades |
| T11c | **T4a only** — the student surface's new `X of Y match your filters` pill, with the count source it needs (AC7a). The staff pills are **not** touched (T4b) | `replacement-history.blade.php`, `ui-common.js` |
| T11d | Request Approval's ≥600 flattening (T6, AC8) | `request-approval.blade.php` |
| T11e | Verify: 48px measured under coarse pointer at 390/768; **unchanged at 1600px** with a mouse (the brief's regression numbers live at 1600 — Filters 34px, chip 22px, row 35px, not 1440); badge heights on three pages; the student count; keyboard open; `elementFromPoint` on neighbouring targets; card heights against the brief's §7 targets at 320/402/768/desktop; print unaffected | — |
| T11f | Changelog + apply log; the survey report's I2/I3 rows closed | `page-changelogs/`, `docs/` |

**Effort:** about half a session. **Risk:** low but shared — `theme.css` is read by every page, so
T11a/T11b are scoped by selector (`any-pointer: coarse`, `.badge*`, `.slot-badge`) and verified
against the pages that already pass today.

### Open question this lane raises but does not answer

The two **staff** count pills keep `Showing X of Y classes` / `… results` (T4b). The drawing says
`9 of 9 match your filters`. If the user wants the drawing's wording on the staff pages too, that is
a one-string change **after** they say so — it reverses a frozen 2026-11-15 ruling, so it is not
mine to make. Also unaddressed from the brief: the 320px question (Request Approval's urgency
sentence wraps below 360px — confirm acceptable or propose better); carried to the next pass.

## 6. Not in this lane

- The card `ORIGINAL`/`REQUESTED` shapes — shipped (`1e92ba2`, `57a3adf`).
- The details pop-up's strip and tabs — task 10.
- Removing table columns — task 12, which depends on task 10.
- Any colour change, any type-size change, any new icon.
