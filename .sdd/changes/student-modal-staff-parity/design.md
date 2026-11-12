# Design — student-modal-staff-parity

**Frozen proposal:** `proposal.md` (batch 1). This file only answers *how*.
**One shared rule:** the pop-up is built by `DetailModal.render()` — the SAME shared renderer the
staff pop-up uses — so nothing here re-invents a shell. `openClassModal({ groups })` is NOT used any
more on this page: it cannot pass a facts strip, and it derives tab keys from headings.

## Files (3)

| # | File | Kind |
|---|---|---|
| F1 | `resources/views/livewire/replacement-history.blade.php` | EDIT — the pop-up builder, the footer section, the render wiring |
| F2 | `app/Livewire/ReplacementHistory.php` | EDIT — three read-only row keys (cohorts, students, short status) |
| F3 | `page-changelogs/replacement-history-changelog.md` | EDIT — the record |

**Files addendum (review round 1):**

| # | File | Kind |
|---|---|---|
| F4 | `app/Support/CohortLabel.php` | NEW — the one cohort-label formatter (promoted from a private method) |
| F5 | `app/Livewire/ReplacementHome.php` | EDIT — its private helper becomes a one-line delegation (output byte-identical) |

Deliberately NOT touched: `public/js/ui-common.js` (the renderer + `DetailModal.selectTab` already
exist), `public/css/theme.css` (`.detail-facts`, `.btn-outline`, `.btn-action`, `.modal-footer`
`space-between` all exist), the staff blade, the partial `ui-class-detail-modal`.

## F2 — the payload (3 new keys, all derived)

In `occurrenceRow()`, beside `'lecturer'`:

```php
/* 2026-11-11 (student-modal-staff-parity): the pop-up's Class Info tab prints the
   same three facts the staff pop-up prints. Cohorts are already eager-loaded by
   render(); nothing new is queried per row. They are DISPLAY facts only — the
   cancellation reason is still never sent to a student (user ruling 2026-11-11). */
'cohorts' => $session->cohorts
    ->map(fn (Cohort $c): string => $this->spacedCohortLabel($c))
    ->values()
    ->all(),
'totalStudents' => (int) $session->cohorts->sum('student_count'),
'statusShort' => $vocabulary['short'],
```

- **The cohort label is a shared rule, not a second copy.** `spacedCohortLabel()` is today a
  `private` method of `ReplacementHome` (`:396`) — the "DFT2 (S1) G1" convention. Copying it into
  `ReplacementHistory` would be the third copy of one formatting rule, so it is **promoted** (small,
  additive): a new `app/Support/CohortLabel.php` with `CohortLabel::spaced(?Cohort $cohort): string`
  holding the exact body, `ReplacementHome` delegating to it, `ReplacementHistory` calling it. That
  makes the lane **4 files + 1 new support class** (see the Files table addendum below).
- `$vocabulary['short']` is already computed two lines above (`statusVocabulary()` returns
  `label/short/sentence/tip`); this only exports it.
- `null` student count must render as `—`, never `0` (a class with no cohort rows attached is unknown,
  not empty).

## F1 — the pop-up

### F1a. Footer becomes static (this is the bug fix)

Today the page **injects** the jump button at the front of a `space-between` footer, so the sides flip
between rows (measured). Replace it with the staff shape: a static footer, gated by `display`.

```blade
@section('modal-footer')
    <button class="btn-outline" onclick="closeModal()">Close</button>
    {{-- 2026-11-11 (user): the SAME calendar glyph + primary blue as the staff
         pop-up's #qvTimetableBtn; the side is decided by the footer's own
         space-between, never by insert order. --}}
    <button class="btn-action" id="stJumpTimetableBtn" style="display:none" onclick="jumpToTimetable()">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        <span>Go to My Timetable</span>
    </button>
@endsection
@include('partials.ui-class-detail-modal')
```

Delete: the `jump`/`jumpCall`/`insertAdjacentHTML` block in `openReplacementModal()`, and the
`.modal-footer .btn-jump-timetable` CSS rule (F1's page-styles).

The inline `onclick` needs the handler on `window` (relief note: `goToReplacementInTimetable` is
already global; only the page wrapper needs exporting, beside the existing
`window.renderUpcoming` / `window.closeModalOutside` / `window.clearAllFilters` exports):

```js
let currentReplacement = null;               // set by openReplacementModal
function jumpToTimetable() {
    if (!currentReplacement || !currentReplacement.requestId) return;
    goToReplacementInTimetable(currentReplacement.requestId,
        currentReplacement.req ? currentReplacement.req.week : 0, '/student-my-timetable-ui');
}
window.jumpToTimetable = jumpToTimetable;
```

Gate inside `openReplacementModal()`, after the render:

```js
const jumpBtn = document.getElementById('stJumpTimetableBtn');
if (jumpBtn) {
    /* 2026-11-11 (user, decision 5): the jump shows only when the timetable really
       carries the new slot — approved or already done. A pending request is not in
       the timetable yet (the staff pop-up's own rule). */
    const landed = r.status === 'approved' || r.status === 'completed';
    jumpBtn.style.display = (landed && r.requestId && r.req) ? 'inline-flex' : 'none';
}
```

### F1b. Title, subtitle, strip, tabs

```js
currentReplacement = r;
const requested = r.req;                       // null on an unarranged row
const typeLong = UiLabels.classType(r.type, 'long');

const tabs = [];
if (requested) tabs.push({ key: 'requested', label: 'Requested Slot Schedule', html: /* see F1c */ });
tabs.push({ key: 'original', label: 'Original Slot Schedule', html: /* see F1c */ });
tabs.push({ key: 'class-info', label: 'Class Info', html: /* see F1c */ });
tabs.push({ key: 'status', label: 'Request Status' + (r.statusShort ? ' (' + r.statusShort + ')' : ''), html: /* F1d */ });

DetailModal.render({
    modalId: 'classModal',
    title: 'Replacement Details',
    subtitle: r.code + ' \u2014 ' + r.name + ' (' + typeLong + ')',
    factsHtml: '<span class="df-item df-item--full"><span class="df-label">Conflict</span>'
        + conflictBadgeFull(r) + '</span>',
    tabs: tabs,
});
/* decision 4: open on Class Info when there is no requested slot */
if (!requested) DetailModal.selectTab('class-info');
```

- `conflictBadgeFull(r)` prints the badge **in full** — `Public Holiday — <holidayName>` or
  `Class Cancelled` (no reason: a student never receives one). Badge classes stay
  `badge badge-holiday` / `badge badge-reason`, i.e. the colours already in use. It needs no `data-tip`
  (the text is the detail), matching the staff pop-up ruling of the same day.
- The strip carries ONE item, `df-item--full`, exactly like the staff strip's Conflict item.

### F1c. The three content tabs

| Tab | Rows (in order) |
|---|---|
| Requested | `Request ID` `#<requestId>` (strong) · `Week` · `Day & Date` (countdown in brackets **only while pending**) · `Time & Duration` · `Venue` |
| Original | `Class ID` `#<sessionId>` (strong) · `Week` · `Day & Date` (countdown in brackets; ` — ` before an Overdue label, the staff rule) · `Time & Duration` · `Venue` |
| Class Info | `Lecturer` · `Cohorts` (joined with `, `, `—` when empty) · `Students` (`r.totalStudents` or `—`) |

Row builders are the shared ones — `DetailModal.row()` / `DetailModal.section()` +
`ModalFacts.dayDate()` / `ModalFacts.timeDuration()` / `ModalFacts.venue()` — the same calls
`replacement-home.blade.php` makes, so the wording cannot drift. The countdown uses the page's
existing `daysLeft` / `daysLeftLabel` / `urgencyBandClass` helpers (today's `slotRows()` already has
them).

- `Class ID` = `r.id.split(':')[0]` (the occurrence key's session half) — the id the staff pop-up calls
  `Class ID`.
- `Request ID` = `r.requestId`.
- Venue: `ModalFacts.venue(r.orig.venue)` — the student payload carries the code only (no room name);
  the helper degrades to the code alone.

### F1d. The Status tab (unchanged content)

Status badge (`statusCell(r)` as today) · `Status Note` (completed only) · `Request` (`requestedAt`,
when present). No rejection reason, no remark — parked in the proposal's non-goals.

## Removals (must all be gone when the lane is done)

1. `{ heading: 'Conflict Reason', … }` — the tab.
2. `{ heading: 'Course', … }` — code/name/type now live in the subtitle, lecturer in Class Info.
3. The `jump` injection block + `jumpCall`.
4. `.modal-footer .btn-jump-timetable { margin-right: 8px; }`.
5. `openClassModal({...})` on this page (replaced by `DetailModal.render`).

## Tokens / wording law

- Colours: none added — `badge-holiday` / `badge-reason`, `.btn-action` (primary) and `.btn-outline`
  (Close) already carry the tokens.
- Separator: the em dash in the subtitle (`\u2014`), never a middle dot (modal-consistency B4).
- Glyph: the calendar (staff's `#qvTimetableBtn`, 14px / stroke 2.5) — one action, one icon.
- Labels: `Class ID`, `Request ID` (checklist B5's staff names), `Class Info`, `Request Status (Short)`.

## Receipts (what the tasks stage must prove)

1. BMIT2013 as `24RSD0012`: title, subtitle, strip text, 4 tabs in order, opening tab, `Request ID`/`Class ID`
   rows, Class Info values, footer sides + colours, no `Conflict Reason` tab.
2. A **pending** row: no `Go to My Timetable`.
3. A row with **no request**: 3 tabs, opens on `Class Info`, no Requested tab, footer has Close only.
4. A cancelled row: strip reads `Class Cancelled` and carries no leave reason.
5. Phone (390px): the pop-up still fits, footer stacks per the shared mobile rule.
6. A feature test pinning the three new payload keys (they are data, not pixels).

## Risks

| Risk | Mitigation |
|---|---|
| A row with no cohort data prints `0 students` | the `—` rule above, asserted in receipt 1's feature test |
| The strip duplicating the Status text (checklist B10) | out of scope by ruling; logged in the changelog |
| Someone re-adds the injected jump later | the static footer plus a comment naming the flip defect |
| `statusShort` missing on an old payload snapshot | the tab label degrades to bare `Request Status` (the `r.statusShort ? …` guard) |
