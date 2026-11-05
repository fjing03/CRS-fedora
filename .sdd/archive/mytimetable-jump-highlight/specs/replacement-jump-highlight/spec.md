# Spec — replacement-records jump & single flash

Capability: **replacement-jump-highlight**. Derived from the frozen `proposal.md` + `design.md`.

## FR 1 — the timetable block carries its replacement record id

**R1.1** For an **approved** replacement request, the event rendered at the replacement's landing week
carries `requestId` = the replacement request's primary key.
**R1.2** The value reaches the DOM as `data-rid` on the block element, and **only** on blocks whose
event carries a `requestId`.
**R1.3** No other event gains `data-rid`; `data-sid` keeps its current meaning on every block, including
replacement blocks.
**R1.4** The key is additive: cohort, venue and student timetable payloads are unchanged in every
existing key.

## FR 2 — one navigation helper

**R2.1** `goToReplacementInTimetable(requestId, week)` navigates to
`/my-timetable-ui?repl-hl=<requestId>&repl-w=<week>`.
**R2.2** A missing `requestId` aborts without navigating.
**R2.3** A missing `week` still navigates (the arrival arm then leaves the week alone).
**R2.4** Both entry points call this helper; no second URL is built anywhere.

## FR 3 — arrival arm

**R3.1** On arrival with `repl-hl`, the params are removed from the address bar **before** any matching
work, so a refresh or a back-navigation cannot replay the flash.
**R3.2** The arm acts only on `/my-timetable-ui` and only when `window.selectWeek` is a function;
everywhere else it strips the params and stops.
**R3.3** When `repl-w` is a valid week (1…14, 1-based) the page switches to it through
`window.selectWeek`.
**R3.4** The target is `#tableBody .event-block[data-rid="<requestId>"]`, retried up to 20 times at
250 ms.
**R3.5** **No fallback selector.** A miss ends silently: no flash, no scroll, no error.
**R3.6** On a hit: the block is scrolled to centre, then flashed once, 500 ms later.
**R3.7** The flash is `flashRowPulse()` — `.bookable-flash`, the existing 1.15 s single-iteration
`bookable-pulse`. No new keyframes, classes, durations or colours.

## FR 4 — Replacement Details modal button

**R4.1** A button labelled `Go to My Timetable` with a calendar icon sits at the **bottom-right** of the
modal footer.
**R4.2** It is visible when the displayed record's status is `approved` or `completed`.
**R4.3** It is hidden for `pending`, `rejected`, `cancelled` and "no request".
**R4.4** Clicking closes the modal, then performs FR 2 with that record's `requestId` and
`replacementWeek`.
**R4.5** The existing footer buttons (`Close`, `Cancel Request`, `Arrange Replacement`), their labels,
their order and their visibility gates are unchanged.

## FR 5 — My Replacements ACTION column button

**R5.1** A new **icon-only** button appears in the ACTION column, with no visible text.
**R5.2** Its hover tooltip and accessible name both read `Go to My Timetable`.
**R5.3** It is rendered only for `approved` / `completed` rows, after the existing eye button; the eye
button keeps its position, behaviour and tooltip.
**R5.4** Clicking it must not open the row's quick view (`stopPropagation`).
**R5.5** Pending and "need to arrange" rows keep exactly the buttons they have today.
**R5.6** It uses the existing `.btn-icon` + `.btn-icon--ghost` language — no new button class, no new
colour.

## FR 6 — layout integrity

**R6.1** At 1920, 1600, 1440, 1366, 1280, 1100, 900 and 820 px, no cell content paints outside its
column (`scrollWidth === clientWidth` per measured cell).
**R6.2** At 1280 px the ACTION column holds both buttons without clipping.
**R6.3** At ≤ 768 px the card view renders both buttons inside the card; the page has no horizontal
overflow at 390 px.

## FR 7 — regressions

**R7.1** Notification arms (`notif-hl=r…`, `notif-hl=s…`), the Today button, the Earliest-bookable jump
and the arrangement page's own flash behave exactly as before.
**R7.2** `php artisan test` green; `composer run lint:check` clean; PHPStan level 7 with 0 errors;
`node --check public/js/ui-common.js` passes.

## Acceptance scenarios

| # | Given | When | Then |
|---|---|---|---|
| S1 | an approved request, replacement in week W | the row's modal is opened | `Go to My Timetable` is visible bottom-right |
| S2 | the same, button clicked | — | lands on `/my-timetable-ui`, week selector = W, `[data-rid="<id>"]` has `.bookable-flash` once within 5 s, the URL no longer carries `repl-hl` |
| S3 | a second approved request in the same week | S2 runs | the neighbour block never gains `.bookable-flash` |
| S4 | a pending request | the modal is opened | no `Go to My Timetable`; `Cancel Request` visible as today |
| S5 | a rejected request | the modal is opened | no `Go to My Timetable`; only `Close` |
| S6 | a row with no request | the ACTION column renders | only the rotate (arrange) button, as today |
| S7 | an approved row | ACTION icon clicked | identical outcome to S2, no quick view opened |
| S8 | the page is refreshed after S2 | — | nothing flashes |
