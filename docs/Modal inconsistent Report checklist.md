# Modal inconsistent Report Checklist

> Status column added 2026-11-10 by the agent, after re-checking every line against the tree
> (not from memory). Nothing in the original findings was reworded; each item just gained a
> verdict + the evidence line.
>
> **Legend** — ✅ fixed · ◐ partly fixed · ⛔ open · ⚠️ WITHDRAWN (my report was wrong)
>
> **Commits behind the fixes** — `36779f3` (#1–#4) · `a65f4a0` (footer size) ·
> `47626bb` (room once) · `3a66d9f` (subject once) · `ce176fc` (B3 + no IDs in subtitles) ·
> `1c9e4d9` (B1 + B7) · `b9ea391` (B4)

**Score: 4 fixed · 1 mostly fixed · 6 open · 2 withdrawn.**

---

## B. Still inconsistent (13 findings)

### B1. The two "Close" buttons are the same word but not the same look — ✅ FIXED (`1c9e4d9`)
Class/Slot use btn-close-modal with a filled background rgb(27, 40, 56); Replacement Details uses
btn-outline, transparent. Same job, two skins.
→ `.btn-close-modal` now renders `background: transparent; border: 1px solid var(--color-outline);
color: var(--color-on-surface)` — pixel-identical to `.btn-outline`, 34 px. The class name stayed
because it is the close hook the markup and the frozen specs select.

### B2. Request Details has no Close button at all — ⛔ OPEN
Only the ✕ in the header. (That was a deliberate rule on 2026-11-03, but next to the other two it now
reads as an omission.)
→ Verified today: the footer still holds only `✕ Reject` + `✓ Approve`; line 313 carries the
`CONSISTENCY 3F (2026-11-03)` comment that removed it on purpose. Needs a user ruling, not a fix.

### B3. Two names for the same primary action — ✅ FIXED (`ce176fc`)
Replace Now (blue, class modals) vs Arrange Replacement (blue, Replacement Details).
→ `Replace Now</button>` now appears **0 times** in `resources/views/livewire/*.blade.php`; all
three timetable pages read **Arrange Replacement**, same icon, same blue `rgb(0, 77, 152)`, 34 px.
`venue-status-parity.spec.ts` follows the new label.

### B4. Two separator styles in the subtitle — ✅ FIXED (`b9ea391`, plus `ce176fc` for the `#28 ·`)
AMSE1003 — Software Engineering (Lecture) (em dash), AMSE1003 · Software Engineering (Lecture)
(middle dot, Replacement Details), and Request Details uses both: #28 · AMCS2093 — Operating
Systems (Lecture).
→ All four subtitles now use the em dash; measured: `AMSE1003 — Software Engineering (Lecture)`,
`MPU-3232 — Entrepreneurship (Lecture)`, `AMSE2003 — Software Testing and Quality Assurance
(Tutorial)`, `AMSE1003 — Software Engineering (Lecture)`. No middle dot left in a subtitle.

### B5. Three names for an identifier — ◐ PARTLY FIXED (`ce176fc` + student pop-up 2026-11-11)
Session ID (#99), Request ID (#38), Class ID (second tab of Replacement Details).
→ `Session ID` (ui-common.js:1231), `Request ID` (replacement-home:868, request-approval:972),
`Class ID` (replacement-home:851) are unchanged. **Note:** the work labelled "B5" in commit `ce176fc`
is this file's **B6**, not this line.
→ 2026-11-11 (SDD `student-modal-staff-parity`): the student pop-up now uses the staff names for the
ones it prints — `Class ID` on the Original tab and `Request ID` on the Requested tab. The remaining
`Session ID` (Class/Slot Details) is a different surface and still open.

### B6. The ID sits in different places — ◐ MOSTLY FIXED (`ce176fc`)
Request Details puts #28 · in the subtitle and a Request ID row; Replacement Details puts #38 in a
row only; Class Details has Session ID as a row and nothing in the subtitle.
→ No subtitle carries an ID any more (Request Details' `#28 ·` is gone), so the ID is always a ROW.
**Residue:** Request Details' `Request ID` row sits in the *Request Status* tab, while Class Details
and Replacement Details show theirs in the first tab.

### B7. ✓ ✓ Slot available — ✅ FIXED (`1c9e4d9`)
The badge prints one tick and the label prints another.
→ The badge is now the only glyph in the row: `(green ✓) Slot available`. Same for the red case — no
second ⚠ before "This make-up slot is NO LONGER FREE".

### B8. Facts live in two shapes — ⛔ OPEN
Request/Replacement Details show a bordered strip above the tabs (Cohorts · Students · Conflict ·
Public Holiday — …, plus Staff (Proposer) (5770) 📋 with a copy icon); Class/Slot Details show the
identical facts as normal labelled rows inside Class Information (Cohort, Total Students).
→ Still true: `factsHtml` is still passed by `replacement-home.blade.php` and
`request-approval.blade.php`.

### B9. Tab labels mix a heading with a state — ◐ PARTLY FIXED (student pop-up 2026-11-11)
Class/Slot: Class Information · Schedule · Status. Request/Replacement: … · Request Status
(Pending), … · Request Status (Rejected).
→ `label: 'Request Status (' + r.requestStatusShort + ')'` (request-approval:1028) and
`label: 'Request Status (' + statusShortOf(c) + ')'` (replacement-home:894) are unchanged.
→ 2026-11-11 (SDD `student-modal-staff-parity`): the student pop-up joined them on purpose — its label
is `Request Status (Approved)` now, with the short word travelling in the row payload
(`statusShort`). The open question is whether the app WANTS the state in the label at all.

### B10. A holiday is stated three times in Slot Details — ⛔ OPEN
Status = Public Holiday, Status Description = Class falls on a public holiday — no class runs,
Remarks = Public Holiday (Deepavali Holiday (In Lieu)). The same fact appears a fourth time in
Replacement Details' facts strip.
→ Still true: the class modal keeps `{ label: 'Remarks', value: event.remarks || '—' }`
(ui-common.js:1241) beside the Status and Status Description rows.

### B11. Student Class Details is a different shape from the staff one — ⛔ OPEN
It shows only Session ID · Lecturer, while staff shows Session ID · Lecturer · Cohort · Total
Students. (Possibly correct: a student knows their own cohort. But it is the only modal with two
rows.)
→ Still true: `student-my-timetable.blade.php` calls `openClassModal` with no `extraFields`, so it
renders Session ID + Lecturer only.

### B12. Approve button colour changes between the page and its own confirm modal — ⚠️ WITHDRAWN
In Request Details, Approve is transparent with a green border; in the confirm modal it is filled
green rgb(46, 125, 90).
→ **My measurement was wrong.** The `rgb(46, 125, 90)` was the button **hovered** (the click that
opened the confirm modal left the pointer on it). Re-measured with the mouse parked away:
`Cancel bg=rgba(0,0,0,0) h=34`, `✓ Approve bg=rgba(0,0,0,0) h=34` — identical to the page's. No
change needed.

### B13. The Approve/Reject modals have no title and no subtitle — ⚠️ PARTLY WITHDRAWN
At the same 616 px width — so a 616 px box with nothing but a question and two buttons.
→ **It does have a title:** `<h2 id="approveNotesTitle">Approve Request</h2>` at 616 px. My probe
looked for `.modal-title` and this markup uses a bare `<h2>`, so it reported `title: null`.
**Residue that is real:** no subtitle, and the title element does not use the shared
`.modal-title` / `.modal-subtitle` markup the four detail modals use.

---

## Notes for the next round

- The two withdrawn items (B12, B13) came from one bad probe: it read `.modal-title` (absent here)
  and measured straight after a click, so it caught a hover state. Lesson: park the mouse and read
  the real element before reporting a colour or a heading.
- Of the six open items, **B2** is the only one that is a deliberate earlier ruling, so it needs a
  decision rather than a fix; **B5 / B9 / B10 / B11** are wording/grouping decisions; **B8** is the
  structural one and the most expensive.
