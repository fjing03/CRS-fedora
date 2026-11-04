# request-approval-ui CONSISTENCY UPDATE
**Page:** `/request-approval-ui` · **File status:** PART 1 SHIPPED · PART 2 essentially DONE (1f, 3B, 3C, 3A, 3D, 1h, 3E-3 shipped; only the §4 eyeball pass remains)
**Last updated:** 2026-11-04 — the merge lane is **applied and archived** (`07274c1`), so every blocker in the table below is lifted; **batch A** shipped the status vocabulary + the room names (1f, 3B, 3C)

> **Checkbox key**
> `[x]` = done, live, receipted · `[ ]` = not done (each one says **why** and **what unblocks it**).
>
> **Where part 1 lives:** `resources/views/livewire/request-approval.blade.php` only — plus
> `tests/request-approval.spec.ts`, `tests/status-tooltips-filter-chips.spec.ts` and
> `page-changelogs/request-approval-changelog.md`. No PHP, no `ui-common.js`, no `theme.css`,
> no payload change, no database change. **SDD waived** by the user (display-only, one page).

---

## 0. Progress at a glance

| Item | What | State | Unblocks at (merge lane `replacement-merge-one-table`) |
|---|---|---|---|
| 1a | Students column removed | ✅ done | — |
| 1b | Cohort column added before Original Class | ✅ done | — |
| 1c | Lecturer → Staff (Proposer) | ✅ done | — |
| 1d | `#` → Request ID (real `#<id>`) | ✅ done | — |
| 1e | Urgency badge = level + remaining days | ✅ done | — |
| 1f | Status wording = My Replacements (Pending/Completed) | ✅ **done 2026-11-04** | shipped — `requestStatusLabel/Short/Sentence/Tip` ride the row; the local 3-key map is deleted |
| 1g | Proposed Replacement → Requested Replacement | ✅ done | — |
| 1h | Original Class / Requested Replacement cells = My Replacements style | ✅ **done 2026-11-04** | shared `{ merged: true }` mode; slot badge kept |
| 2 | Summary cards → Approved Requests / Rejected Requests | ✅ done | — |
| 3A | Timeline out, facts strip in | ✅ **done 2026-11-04** | strip = Staff (Proposer) + Cohorts + Students + slot Conflict |
| 3B | Modal status badge wording | ✅ **done 2026-11-04** | same payload keys — the pop-up's Status row + sentence |
| 3C | Schedule = one row (Date · Time · Venue `B103 — Tutorial Room`) | ✅ **content done 2026-11-04** (the icon-led row layout is 3E-3) | `venueName` + `replacementVenueName` on the row, stripped from the API, pinned by a test |
| 3D | Three tabs, renamed, status tab far right | ✅ **done 2026-11-04** | `Original Class Schedule` · `Proposed Replacement Schedule` · `Request Status (<short>)` |
| 3E-1 | Week row on both schedule tabs | ✅ done | — |
| 3E-2 | Duration in full words | ✅ done | — |
| 3E-3 | Schedule layout matches the merged page | ✅ **done 2026-11-04** | one `Time` row per tab, merged order |
| — | Cohort(s) in the modal: compact codes joined with ` + ` (user ruling) | ✅ done | — |
| 3F | No Close button, Reject bottom-left | ✅ done | — |
| 4 | Treat My Replacements as the design reference | ⛔ pending | the merge lane's commit 2 renders the page on the **old URLs**; its **commit 3** makes `/my-replacements` canonical |

### Correction to an earlier note in this file (2026-11-04)

The first version of this table said 3C was blocked by "the payload's `venueName` — this page's
`toRow()` has no such key", implying the merge lane would add it. **That was wrong.** The merge lane
deliberately does **not** touch `ReplacementRequestMapper::toRow()` (a frozen law: the API's
agreement depends on `toRow()` staying the single source). Its `venueName` is a key in
`ReplacementHome`'s payload only.

So **3C is this lane's own work**. `toRow()` has exactly **two consumers** —
`RequestApproval::rowsPayload()` (`:76`/`:79`) and `ApiReadController::myRequests()` (`:156`) — so
adding the key touches **three surfaces**:

| Surface | Effect of adding it in `toRow()` |
|---|---|
| `toRow()` itself | the key exists (`replacementVenueName` — pairs with `replacementVenue`; **not** the merged payload's plain `venueName`, a different payload) |
| `RequestApproval` payload (`:76`/`:79`) | gains the key — harmless, and it is exactly what 3C needs on this page |
| `/api/v1/requests/my` (`:156` → strips four keys at `:159`) | would gain it too **unless stripped** |

→ **3C is "add in `toRow()`, strip only in `myRequests()`".** Stripping in the mapper or in the
approval payload would remove the very thing 3C is for.

**Two edits, not one:**

1. `ReplacementRequestMapper::toRow()` — carry the name through. `stringsFromSpan()` already returns
   `venueName` (`:312`); `toRow()` calls `replacementStrings()`, which deliberately returns only
   `date/time/venue` (`:152-154`), so the name never reaches a row today.
2. `ApiReadController::myRequests()` — add the new key to the `unset(...)` at `:159`.

(The alternative — looking the room name up in the browser — is rejected: this page's payload has no
venue directory, so it would hardcode or duplicate data.)

### The strip must be guarded by a test, not by memory

**Nothing pins the API's key set today.** There is no `tests/Feature/*Api*` file; `SpanReservationTest`
asserts `replacementTime`'s **value** (`:451`, `:465`, `:475`), and `tests/e2e/api-wiring.spec.js` is
testing-DB gated and reads values. So a stray `replacementVenueName` would pass **every existing test**.

Worse, it would not even fail loudly: `routes/api.php`'s whole `/api/v1` group is **commented out**
("ORPHANED API ROUTES", 2026-10), so the endpoint is unreachable today and the leak is **latent** until
Wave 3b re-enables those routes.

So the 3C change carries its own guard, and it needs **no HTTP route**:

```php
// PHPUnit — ApiReadController::myRequests() is callable directly (resolveUser()
// falls back to staff 5425 when nobody is authenticated), so the strip is
// pinned even while the routes are commented out.
$json = app(ApiReadController::class)->myRequests()->getData(true);
$this->assertArrayNotHasKey('replacementVenueName', $json['requests'][0]);   // API shape preserved
$this->assertArrayHasKey('replacementVenueName', $mapper->toRow($r, $start, 'queue')); // 3C's source
```

**Why the split:** the merge lane `replacement-merge-one-table` — **`sdd.yaml status: tasks`**
(proposal/design/specs frozen, `tasks.md` in review, 2026-11-04) — is building the **My Replacements**
page right now: its status vocabulary (`ReplacementRequestMapper`
`statusLabel/statusShort/statusSentence/statusTip`, decisions M5), its modal shell (`#quickViewModal`,
M7) and its cell design (§3.2–3.3). Seven of the eight pending items are "match that page"; doing
them before it exists would create a second copy of the same rules that then has to be deleted.
**The exception is 3C, which is ours and only needs the two edits in §0's correction.**
Each pending item below names the exact thing to consume, and *which of the merge lane's commits*
delivers it:

The merge lane's own words: **it does not apply** — PI applies its tasks. So the commits below are
PI's commits run from that frozen lane, not that session's.

| Their commit (applied by PI) | Contains | Frees |
|---|---|---|
| commit 1 | T1–T6, the mapper vocabulary | 1f, 3B |
| commit 2 | T9, the one `DetailModal.render` line + the merged view (old URLs) | 3A, then 3D · 1h · 3E-3 · the §4 pass |
| commit 3 | the canonical `/my-replacements` URL | the final §4 eyeball

---

## 1. Table updates

- [x] **1a — Remove the Students column.**
  *Requirement:* "Remove the **Students** column."
  **Done:** dropped from `columns[]` and the `<td>` in `renderRow()`.
  The student count is still visible where it belongs: the pop-up's *Original Class* tab
  (`Total Students` + the shared `cohortBreakdown()` `A + B = total`) and the Approve/Reject
  context card's meta chips (`15 students`).
  **Receipt:** `request-approval.spec.ts` U3-T9c asserts the header set no longer contains
  *Students*.

- [x] **1b — Add a Cohort column immediately before Original Class.**
  *Requirement:* "Display multiple cohorts in this format: `RSD3(S1)G2 + RSD3(S1)G3`."
  *Refined by the user 2026-11-03:* the **table stacks** them (`RSD3(S1)G2<br>RSD3(S1)G3`), the
  **modal joins** them with ` + ` — see 3E/extra below.
  **Done:** one compact code per line via the page-local `compactCohort()` (strips the spaced
  convention `RSD3 (S1) G2` → `RSD3(S1)G2`), cell class `col-cohort`, empty cell renders `—`.
  **Not sortable** (`sortable: false`): a multi-cohort row has no single value to sort by.
  **Receipt:** U3-T9c asserts every cohort cell is non-empty and every line matches
  `^[A-Z]+\d+\(S\d+\)G\d+$`; live: `DSF2(S1)G1`, and `RSD2(S1)G2` / `RSD2(S1)G1` stacked.

- [x] **1c — Rename Lecturer to Staff (Proposer).**
  **Done:** header label + its `data-tip` ("The staff member who submitted the request — sorted by
  name"). The cell is unchanged: `Name (staffId)` + clickable email + 📋 copy icon.
  **Receipt:** header assertion in U3-T9c (`Lecturer` absent, `Staff (Proposer)` present).

- [x] **1d — Rename the `#` header to Request ID.**
  *Decision taken while doing it (user approved 2026-11-03):* the cell now prints the **real
  request id** (`#26`), not the row counter — a header saying "Request ID" over `1, 2, 3…` would be
  a lie. The mobile card already printed `#<id>`, so the two views now agree.
  **Done:** header `Request ID` (not sortable, tip "The request identifier the lecturer sees (#) —
  not sortable"); cell `#<id>` with class `col-request-id`.
  **Receipt:** U3-T9c asserts `^#\d+$` per row.

- [x] **1e — Urgency badge shows the level followed by the remaining days.**
  *Requirement example:* `Normal — 67 days left`. Keep the existing category and badge styling.
  **Done:** `urgencyLabel(level, classDate)` composes the text; `urgencyShort(level)` keeps the bare
  word for the places that need it. The four wordings:

  | State | Badge text |
  |---|---|
  | normal | `Normal — 52 days left` |
  | urgent (1 day) | `Urgent — 1 day left` |
  | urgent (today) | `Urgent — today` |
  | overdue | `Overdue — 5 days past` |

  Styling untouched (`urgency-normal` / `urgency-urgent` / `urgency-overdue`); the tooltip keeps its
  fuller sentence (`… left before the Proposed Class.`), and the *Requested age* chip keeps the bare
  word in its tooltip. The same text now appears in the table, the Approve/Reject context card and
  the **mobile card** — the card gets it through a per-row closure, so `HtmlBuilder.requestCard`
  (shared) was **not** edited.
  **Receipt:** `status-tooltips-filter-chips.spec.ts` RA-2 (badge↔tip leading word, Overdue shift,
  the `Urgent — today` case, the Urgent filter keeping Overdue).

- [x] **1f — Status badge wording consistent with My Replacements (Pending, Completed).**
  **Done (2026-11-04, batch A):** `toRow()` carries the shared M5 vocabulary as
  `requestStatusLabel / requestStatusShort / requestStatusSentence / requestStatusTip`; the queue
  badge, its `data-tip`, the pop-up's Status row and the mobile card all read it, and the local
  `statusTooltip()` 3-key map + `statusDescMap` are **deleted**. The Completed/Cancelled
  "no tooltip" gap is closed. One deliberate deviation from this file's wording: `statusTip()`
  gained an `$audience` parameter, because M5's strings are written **for the lecturer** ("your
  request") while this page is the PL side of the same decision — the PL voice keeps the copy the
  page already shipped, and the lecturer's strings are byte-identical to the approved M5 text.
  **Why it had been pending:** the target vocabulary is the merge lane's **M5** table, delivered as
  `ReplacementRequestMapper::statusLabel()/statusShort()/statusSentence()/statusTip()` (§1.6 of that
  lane). A local copy here would be the second source of truth the merge exists to remove.
  **Known gap today (must not be forgotten):** this page's `statusTooltip()` map still has only
  **three** keys (Pending / Approved / Rejected), so a **Completed** or **Cancelled** badge renders
  with **no tooltip** at all. 1f fixes that too.
  **Unblocked by:** `replacement-merge-one-table` applied. **Then:** the row badge, the modal badge,
  the mobile card and the `data-tip` all read the shared helpers; the local `statusTooltip()` map
  and the local `statusDescMap` are deleted.
  **Watch:** `statusClass()` stays as-is (it already maps all five states) — do not rename classes.

- [x] **1g — Rename Proposed Replacement to Requested Replacement.**
  **Done:** the column header, its tip ("Requested new date, time, and venue") **and** the sort-hint
  line above the table (`… Original Class, Requested Replacement, Urgency, Status`). The filter
  chips and the modal tab are separate items (chips say `Status:`/`Urgency:`; the tab is 3D).

- [x] **1h — Original Class and Requested Replacement cells follow My Replacements' format/style.**
  **Done (2026-11-04, batch C):** `HtmlBuilder.classBlock()` / `replacementBlock()` take an opt-in
  `{ merged: true }` mode — duration in **words** and the **pin-icon venue line**
  (`B010 — Computer Lab B010`); the Original Class cell now shows its venue too, which it never did.
  The shared `{ merged: true }` flag (not a rewrite) is what keeps the frozen templates and every
  legacy caller byte-identical. The three pieces the merged page had page-locally are now shared in
  `ui-common.js` (`uiHoursWords`, `uiHoursFromRange`, `uiVenueLine`) rather than a third copy.
  **Kept:** the slot-validity badge (`✓`/`⚠`) — PL-only information, ahead of the venue in the
  Requested Replacement cell.

---

## 2. Summary cards

- [x] **2 — Rename the two decision cards.**
  *Requirement:* "Rename/update the summary cards to: **Rejected Requests**, **Approved Requests**."
  **Done:** labels `Approved` → **Approved Requests** and `Rejected` → **Rejected Requests** in the
  `partials.ui-summary-bar` card array. **Ids unchanged** (`#summaryApproved`, `#summaryRejected`),
  so the counts, the filters and every existing spec keep working. `Total Requests`,
  `Pending Requests` and `Total Reviewed` are unchanged (the brief named only the two).
  **Live (demo data, 2 pending requests):** `TOTAL REQUESTS 2` · `PENDING REQUESTS 2` ·
  `APPROVED REQUESTS 0` · `REJECTED REQUESTS 0` · `TOTAL REVIEWED 0`.

---

## 3. Request detail modal

- [x] **3A — Replace `modal-timeline` with the `detailFacts` section.**
  **Done (2026-11-04, batch B):** `timeline:` is dropped from `DetailModal.render()` and the
  *Request Info* tab is **absorbed by the strip** — Staff (Proposer) `Name (staffId)` + the email
  copy contract, Cohorts (compact ` + `), Students (`A + B = total`), and the slot **Conflict** badge
  while the request is pending. Subject code/name stay in the pop-up subtitle; `Requested At`,
  `Rejection Reason`, `Remark` and `Reviewed By/At` moved into the status tab, so no information was
  dropped with the tab. Removing the rail also removed the `"the timeline function got error …"`
  demo note it printed at the top of this pop-up.
  **Why it had been pending:** the strip is the merge lane's **M7** and that lane edits the single
  `DetailModal.factsHtml` line — writing it here first would have meant two copies.

- [x] **3B — Modal status badge wording.**
  **Done (2026-11-04, batch A):** the pop-up prints `Pending — Waiting for Approval` and its
  *Status Description* is the shared sentence (`requestStatusSentence`), not the deleted local map.
  **Why it had been pending:** same dependency as 1f (M5 vocabulary). One change fixed both.

- [x] **3C — Schedule information as one row per block.** *(content done; the icon-led row layout
  is 3E-3)*
  **Done (2026-11-04, batch A):** both schedule tabs print **`code — room name`**
  (`B010 — Computer Lab B010`) through the page's `venueText()`. `classRow()` gained `venueName`,
  `toRow()` gained `replacementVenueName`, and `ApiReadController::myRequests()` strips both —
  `tests/Feature/MyRequestsApiShapeTest.php` pins the API's exact 23-key row so the leak cannot
  come back.
  *Requirement:* `Date: Thursday, 2nd November 2026` · `Time: 10:00 AM ~ 12:00 PM` ·
  `Venue: B103 — Tutorial Room`, applied to both Original Class and Requested Replacement.
  **Blocker: none — this is our own work** (corrected 2026-11-04; the merge lane does not touch
  `toRow()`). Two edits:
  1. `ReplacementRequestMapper::toRow()` — carry `venueName` through (`stringsFromSpan()` already
     returns it at `:312`; `replacementStrings()` returns only `date/time/venue` at `:152-154`).
  2. `ApiReadController::myRequests()` — add the new key to the `unset(...)` at `:159`, or
     `/api/requests` grows a field and the pinned API shape breaks.
  **The layout/styling half** (one row, icon-led) is 3E-3 and does wait for the merged design.
  **Note:** `U3-T9d` pins today's row shape (*Date / Day / Start Time / End Time*); the layout change
  rewrites that spec.

- [x] **3D — Tabs: `Original Class Schedule` · `Proposed Replacement Schedule` ·
  `Request Status (<status>)` — status tab far right.**
  **Done (2026-11-04, batch B):** exactly those three, in that order; the replacement tab is
  **absent** when nothing is proposed (`r.replacementDate` null), and the old *Review* tab's
  metadata now lives inside the status tab. The pop-up grew to **616 px** (the merged page's shell)
  because the shared 420 px base cannot fit three tabs.
  **Why it had been pending:** it needed 3A (the *Request Info* tab gone) and the merged tab labels.

- [x] **3E-1 — Add a Week row to both schedule tabs.**
  **Done:** `Week 14` / `Week 7`, after *Day*, in both tabs. Derived by `weekOfIso()` from the date
  and the semester start (semester start + 7×n, clamped to the semester's week count) — **no new
  payload key**, so nothing to change on the server.

- [x] **3E-2 — Display Duration in full words.**
  **Done:** the *Original Class* tab already printed words (`durationWords()` → `1 hour`,
  `2 hours`); the *Replacement Class* tab had **no** duration row at all, so `rangeHours()` now
  derives it from the requested time range (`1 hour`, `2 hours`, `1.5 hours`). Both tabs now read the
  same way.

- [x] **Extra (user ruling 2026-11-03) — Cohort(s) in the modal.**
  The modal prints the **compact codes joined with ` + `** (`RSD3(S1)G2 + RSD3(S1)G3`), while the
  table stacks them one per line. Same codes, different layout, as the user specified.

- [x] **3E-3 — The schedule layout/styling matches the merged page.**
  **Done (2026-11-04, batch C):** `Start Time` + `End Time` collapse into ONE `Time` row
  (`9:00 AM ~ 11:00 AM`), so each tab reads Week · Day · Date · Time · Duration · Venue (+ Slot
  Validity on the replacement tab) — the merged pop-up's order — with the venue printing
  `code — name` (3C).

- [x] **3F — Modal buttons.**
  *Requirement:* remove **Close**; move **Reject** to the bottom-left; rename it to `× Reject`.
  **Done:** the footer's Close button is gone. Verified live that all three remaining ways still
  close it: the header `✕`, a click on the overlay, and **Esc** and **✕ Reject** now occupies the bottom-**left** slot with
  **✓ Approve** bottom-right. The label was already `✕ Reject`. The JS line that toggled the Close
  button's visibility was removed with it (no null dereference).
  **Receipt:** live footer buttons = `✕ Reject`, `✓ Approve`; `#closeModalBtn` no longer exists on
  this page.

---

## 4. Main UI reference

- [ ] **4 — Treat My Replacements (table + modal) as the main design reference.**
  **Why pending:** that page is being built by the merge lane (`/my-replacements`, M1–M16). Once it
  exists, re-read this whole file against it and treat part 2's items as "diff my PL page against it".
  **Borrowed already** where the pattern existed without the merge: compact cohort codes, the
  schedule rows, the email copy contract, the `data-tip` singleton, the confirm modal for
  destructive actions.

---

## 5. Decisions taken while shipping part 1 (do not re-litigate)

1. **`Request ID` prints the real `#<id>`**, not the row counter. The mobile card already did.
2. **Cohort formats:** table = stacked compact codes (one per line) · modal = the same codes joined
   with ` + `. (The brief's example `RSD3(S1)G2 + RSD3(S1)G3` is the modal form; the user chose the
   stack for the table so the column stays narrow.)
3. **Urgency wording** is exactly `Level — N days left` / `Level — today` / `Overdue — N days past`.
   The tooltip keeps the longer sentence, so the two are no longer identical strings.
4. **The Students column is gone from the table only** — the count stays in the modal and in the
   Approve/Reject context card.
5. **Table width: keep.** The Cohort column + the longer urgency text made the table ~150 px wider.
   Measured: at 1400 px it already scrolled before; at 1600 px it used to fit exactly and now needs
   ~40 px of horizontal scroll. `.grid-scroll` scrolls, so every column (including Actions) stays
   reachable. **User decision 2026-11-03: keep it as is** — do not squeeze the design.
6. **No shared-file edits:** the mobile card's longer urgency label is passed as a per-row closure,
   so `HtmlBuilder.requestCard` is untouched. `theme.css`, `ui-common.js`, the payload and the
   database were not touched at all.

---

## 6. Part 2 backlog (in a sensible order, tied to the merge lane's commits)

1. **1f + 3B** — as soon as the merge lane's **commit 1** (T1–T6, the mapper vocabulary; applied by
   PI, not by the design session) lands: adopt
   the four status helpers on this page and delete the local `statusTooltip()` 3-key map and the
   local `statusDescMap`. That also fixes Completed/Cancelled rendering **no tooltip** today.
   *No dependency on their view file at all.*
2. **3A** — at their **commit 2** (T9, the single `DetailModal.render` line): pass `factsHtml`, drop
   `timeline:`, absorb the *Request Info* tab. Their edit is additive, so a caller that omits
   `factsHtml` (this page, until then) keeps working unchanged.
3. **3D** — right after 3A: the three-tab shape.
4. **3C** — **not blocked**: do it whenever convenient (the two edits in the correction above:
   `toRow()` carries `venueName` + the API `unset()`).
5. **1h + 3E-3** — at their **commit 2**: align the two table cells and the schedule block with the
   merged design, keeping this page's PL-only slot-validity badge.
6. **4** — the eyeball pass once their **commit 2** renders the merged page (it is live on the **old
   URLs** first; **commit 3** makes `/my-replacements` canonical). Update this file's checkboxes.

---

## 7. How to verify part 1 (repeatable)

```bash
scripts/dev-serve.sh 8000                     # clear stale compiled views first
npx playwright test tests/request-approval.spec.ts tests/status-tooltips-filter-chips.spec.ts
npx playwright test tests/ui-regression.spec.ts tests/nav-identity.spec.ts
```

Expected: **9/9**, **9/9**, and **16/16**. Screenshots from the receipt run:
`/tmp/opencode/pl-table-consistency.png`, `pl-modal-original.png`, `pl-modal-replacement.png`.

Manual checklist (login `5425` / `Tarumt@2026` → `/request-approval-ui`):

- [x] Headers read `Request ID · Requested Timestamp · Staff (Proposer) · Course Code & Name ·
      Cohort · Original Class · Requested Replacement · Urgency · Status · Actions`
- [x] A row shows `#26`, a stacked cohort cell, and `Normal — 52 days left`
- [x] Cards read `Approved Requests` / `Rejected Requests` (`2 · 2 · 0 · 0 · 0`)
- [x] Modal → *Original Class*: `Week 14` + `1 hour` + `Cohort(s) DSF2(S1)G1`;
      *Replacement Class*: `Week 7` + `1 hour` + Slot Validity `✓ Slot available`
- [x] Modal footer: `✕ Reject` bottom-left, `✓ Approve` bottom-right, **no** Close button;
      header `✕` / overlay click / **Esc** all still close it (each verified)
- [x] ~1400 px: the table scrolls sideways (1350 → 1590) and the Actions buttons are reachable
      after scrolling (measured)

---

## 8. Guardrails for part 2

- **Do not change business logic.** The approve/reject wire, `ApproveRequest` / `RejectRequest`,
  permissions, the bulk-bar, the filters and the counts stay exactly as they are. This file is about
  *how the page reads*, never *what it does*.
- **Do not touch other pages.** `/my-replacements`, `/my-timetable-ui`, the venue page and the
  student pages belong to other lanes.
- **Do not edit the frozen mock** `resources/views/ui-design-templates/request-approval-UI-design-template.blade.php`.
  It still shows the old footer and columns — that is intentional (it is the D10 fallback
  reference, not the live page).
- **No hardcoded examples.** Every string shown must come from the payload (`MockData.approvalRequests`
  ← `RequestApproval::rowsPayload()` → `ReplacementRequestMapper::toRow()`).
- **Collision surface 1 — `tests/status-tooltips-filter-chips.spec.ts`, split by BLOCK, never by
  find-and-replace.**
  This lane owns the **RA side**: `RA_MAP` (`:25-31`), `T5.RA-1` (`:178`), `T5.RA-2` (`:206`),
  `T5.RA-3` (`:281`). The merge lane owns the **MRH side**: `MRH_MAP` (`:18-24`), `T5.MRH-1`
  (`:62`), `T5.MRH-2` (`:96`), `T5.MRH-3` (`:151`).
  This lane landed first (`24db2be`), so the merge lane rebases onto it and touches only the MRH
  half. **Its design line "`#modalOverlay` → `#quickViewModal`" is MRH-scoped** — this PL page keeps
  `#modalOverlay` by design, so a repo-wide sweep of that id would break `T5.RA-1`/`RA-2`/`RA-3` and
  `request-approval.spec.ts` U3-T9c/T9d.
- **Collision surface 2 — `DetailModal`** (`ui-common.js`). The merge lane's edit is the **single
  `factsHtml` key** and it is **additive**: a caller that omits `factsHtml` (this page, today) is
  unaffected, so this page's `#modalOverlay` / `#modalBody` / `#modalTitle` / `.modal-tab` contract
  stays intact.
- **Two page-local helpers are twins of the merge lane's need**: `rangeHours()` (duration in words)
  and `weekOfIso()` (the Week row). Their merged modal needs exactly the same two (their design
  §4.2). After both lanes land there will be two independent implementations — a **promote-on-3rd
  candidate for a later pass**, explicitly **not** a blocker now, and not to be "fixed" by editing
  this page while their lane is in flight.
- **Keep the ids the specs and the merge lane rely on:** `#modalOverlay`, `#modalBody`, `#modalTitle`,
  `.modal-tab`, `#rejectRequestBtn`, `#approveRequestBtn`, `#tableHead`, `#tableBody`,
  `#summaryApproved`, `#summaryRejected`.
