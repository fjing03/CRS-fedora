# Design — notif-click-highlight

## D1 The param law

- Kind `r` (request anchor): `notif-hl=r<{request_id}>` — the approval and
  history rows.
- Kind `s` (session anchor; CARRIED but unused in STAGE 1 — reserved): the
  helper currently resolves only kind `r`; a `s` param is ignored gracefully
  (the timetable surfaces' transition rides the user's week ruling).
- One anchor per navigation: the LAST `notif-hl` param read wins (the URL is
  authored once per click).

## D2 The click-time append (ui-common.js — the D4 listener block)

The capture-phase row listener keeps its durable POST + scroll-restore and
adds, BESIDE the mark (order: mark → append → navigate):

```js
const row = e.target.closest ? e.target.closest('.notif-row[data-id]') : null;
if (!row || !row.getAttribute('data-id')) return;      // the inactive guard FIRST (unchanged, keep-as-it)
durableMarkNotifRead(row.getAttribute('data-id'));     // the D4 law (unchanged)
...
/* the ANCHOR = the notification's own target id (data-notif-target), NOT
   this row's id; STAGE-1 emits it ONLY for /request-approval-ui and
   /my-request-history-ui rows; a null → plain navigation (degrade) */
const target = row.getAttribute('data-notif-target');
const link = row.getAttribute('href') || '';
if (target && link) {
    const norm = link.startsWith('/') ? link : '/' + link;
    row.setAttribute('href', norm + (norm.includes('?') ? '&' : '?') +
        'notif-hl=' + encodeURIComponent(target));
}
```

- The href mutation is in-DOM only (a click-time append; no server change);
  a click on the SAME row later re-appends on the fresh render.
- `data-id` = the ROW ID of the notification (`$n->id`); but the requested
  anchor id must be the target record id — the notification row must EXTEND
  the anchor to the payload id: the row partial gains
  `data-notif-target="r<request_id|s<class_session_id>>"` (nullable — the
  cancel-class rows carry `s`, the request rows `r`; the inactive arm is
  unchanged), and the appender reads `data-notif-target`. The mapping is server-side per
  the per-role link law (the notification's OWN columns — no guessing).
  PRECEDENCE (r wins): a row carrying both ids renders the r form. STAGE-1
  emits the attribute ONLY on rows whose link is /request-approval-ui or
  /my-request-history-ui — the student-approval and cancel rows emit
  nothing (no address-bar litter on stage-2 surfaces).

## D3 The arrival helper (ui-common.js)

function applyNotifHighlight() {
    const params = new URLSearchParams(location.search);
    const raw = params.get('notif-hl');
    if (!raw) return;
    /* the URL strip runs FIRST — pagination/filter misses strip too (no
       re-flash on refresh) */
    params.delete('notif-hl');
    const qs = params.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    /* kind precedence: r wins; kind-s is STAGE-2 (deferred on the user's
       ARRIVAL-GRID-NO-PIN week ruling) — a bare-s param = graceful no-op */
    if (!raw.startsWith('r')) return;
    const sel = 'tr[data-id="' + raw.slice(1) + '"]';
    const tryFind = (n) => {
        const row = document.querySelector(sel);
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            /* the flash starts AFTER the scroll settles (a smooth scroll can
               outlive the 1.15 s pulse — the earliest flow hops THEN pulses) */
            setTimeout(() => flashRowPulse(row), 500);
        } else if (n > 0) {
            setTimeout(() => tryFind(n - 1), 150);   /* the payload renders ~+50 ms */
        }
        /* else: graceful skip (the pagination/filter miss receipt) */
    };
    tryFind(5);
}
document.addEventListener('DOMContentLoaded', function () {
    initNotifPanel();
    setTimeout(applyNotifHighlight, 150);
});

## D4 The flash = the EXISTING .bookable-flash pulse (the user's refinement)

```css
/* theme.css :2305-2310 (ALREADY IN PLACE — zero CSS change) */
@keyframes bookable-pulse {
    0%, 100% { box-shadow: 0 0 0 0 transparent; }
    45% { box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-success) 45%, transparent); }
}
tr.bookable-flash > td { animation: bookable-pulse 1.15s ease-in-out 1; }
```

The user's ruling: the highlight must be EXACTLY the
`#earliestBtn` jump-flash. So the lane adds NO css class; it exercises the
existing pulse via a promoted generic helper in ui-common:

```js
/* the ONE pulse mechanic (promoted from flashEarliestBookableDay's in-loop
   remove/reflow-add/timeout — reusability, no behavioural delta) */
function flashRowPulse(el) {
    el.classList.remove('bookable-flash');
    void el.offsetWidth;          /* restart the animation on repeat visits */
    el.classList.add('bookable-flash');
    setTimeout(() => el.classList.remove('bookable-flash'), 1300);
}
```

`flashEarliestBookableDay` delegates its loop to `flashRowPulse` (same
timings, zero behaviour delta — the promote-on-reuse law), and
`applyNotifHighlight` calls `flashRowPulse(row)` after
`row.scrollIntoView({ behavior: 'smooth', block: 'center' })`.

## D5 The wire map (D2 + D3 pinned per surface)

| Emitter row kind | data-notif-target | Landing page | Query scanned | Element found |
|---|---|---|---|---|
| pl `submitted`/`awaiting` | `r<request_id>` | /request-approval-ui | `r<id>` | `#tableBody tr[data-id]` (queue or decided table) |
| lecturer `approved`/`rejected` | `r<request_id>` | /my-request-history-ui | `r<id>` | `#tableBody tr[data-id]` |
| `update` (cancel-class) | `s<class_session_id>` | /cohort or /venue | Ignored in STAGE-1 (see the proposal's STAGE-2) → no-op |

## D6 The receipts

- :8030 — seeded request rows; click a pl row unread → lands on the
  approval queue → the queue row gains `bookable-flash` + scrolls into view →
  auto-clean after ~6 s; the URL lost `notif-hl` after the arrival;
  repeat for the history page (a rejected row) and for READ rows.
- Inactive row: NO params appended (the capture guard) — receipt.
- :8000 read-only — the demo clicks are inert (no DB writes); the param
  append receipt via the DOM snapshot only.
- No console errors; the island receipts (7/7) and the suite green.


## D7 STAGE 2 (ACTIVATED — the rulings A + the cancel-row link law, 2026-10-30)

### D7.1 The emitter link law (a decision-level change to notifications-real-bridge D1's table — SUPERSEDED by the user's ruling)

`NotificationEmitter::classCancelled`:
- the actor (owner) lecturer row: link `/cohort-timetable` → **`/my-timetable-ui`**;
- the dept-PL rows: link `/venue-timetable-ui` → **`/my-timetable-ui`**;
- the students' rows: UNTOUCHED (`/student-my-timetable-ui`).

### D7.2 The my-timetable arrival (the A-exception carve-out)

- The URL: `?notif-hl=s<session_id>&notif-w=<week>` (the week rides the
  click-time append — the row partial also emits `data-notif-week` from the
  notification's `week` column for the append).
- arrival (the /my-timetable-ui plane): parse kind-s → the page wrapper
  `window.selectWeek(idx)` (it re-syncs currentWeek + saveWeek) with
  `notif-w` validated 1-based → [0, weeks-1]; ABSENT or out-of-range → the
  graceful skip (no week move, no flash) → the selector's navigate rebuilds
  the grid → find `#tableBody .event-block[data-sid="<sid>"]` (the default
  builder renders .event-block — .cell-content is the cellRender surfaces
  ONLY) → flashRowPulse + scrollIntoView (after a ~500 ms settle) → the URL
  strip runs FIRST and covers notif-hl AND notif-w (the same unconditional
  law).
- THEMES: the s-target flash needs its css arm —
  `.event-block.bookable-flash { animation: bookable-pulse 1.15s
  ease-in-out 1; }` in theme.css (lane-A-owned; the same keyframes/token —
  no new color).
- THE CARVE-OUT: this selector move happens ONLY for a `notif-hl` URL — the
  `?week=` law keeps every other deep link pinned. Recorded in the
  changelog; the WONTFIX doc line gets the carve-out note at apply.

### D7.3 The builder bridge patch (the documented one-liner)

`buildTimetableGrid` (the default path) stamps the event cell:
`div.dataset.sid = String(event.sessionId ?? event.id)` (the house
id-blind-proof pair — the venue reserved-row arm carries a
replacement_time_slot_id in `id`). One line, per the 99.9%-rule
bridge-patch convention; the grid's click flow untouched.
THE GATE (the legacy rows): the row partial emits data-notif-target ONLY
when the row's STORED link is one of the STAGE-1/2 surfaces
(/my-timetable-ui, /request-approval-ui, /my-request-history-ui) — the
legacy persisted rows (the old /cohort-timetable + /venue-timetable-ui
strings) emit NOTHING → plain navigation, no params, no litter (the
lifecycle law: no migration, no retro-rewrite).

### D7.4 The receipts (STAGE 2)

- :8030 — as 5425: cancel a class → click the OWN one-feed row → arrives
  /my-timetable-ui with the selector ON the event's week → the cell
  `.cell-content[data-sid]` flashes (bookable-flash) → the URL strip.
- The dept-PL copy (Rahmat fixture): arrival → NO cell → the graceful skip
  (the navigation fine, no flash) — receipted.
- The A-exception regression guard: a plain `?week=` link STILL does not
  move the selector (the carve-out is notif-hl-only).

## D8 The relationship to the superseded lanes (updated)

- notif-actor-pl-dedupe is untouched (its F1 skip precedes the link change
  — the actor row now links /my-timetable-ui; the dept-PL rows for others
  too; no double-links for owner-PL staff).
