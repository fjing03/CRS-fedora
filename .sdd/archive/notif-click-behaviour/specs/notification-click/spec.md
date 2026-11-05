# Spec — notification click behaviour

## FR 1 — status on the row
**1.1** Every clickable inbox row carries the LIVE status of the record it is about.
**1.2** Request rows resolve through the same resolver the pages use, so a derived *Completed* is reported.
**1.3** A request-less cancellation row reports `cancelled` (its subject is the cancelled class).
**1.4** A row whose record cannot be resolved reports nothing, and the click keeps its stored link.

## FR 2 — cancelled
**2.1** The row's destination is `/my-replacements`.
**2.2** The anchor is `o<class_session_id>` plus `notif-w=<week>`.
**2.3** On arrival the page switches to that row's page, scrolls it into view and flashes it once.
**2.4** A row that is not in the payload produces no flash, no scroll, no error.

## FR 3 — approved
**3.1** The row's destination is `/my-replacements`.
**3.2** The anchor is `r<request_id>`.
**3.3** On arrival the request's row flashes once (`tr[data-request-id]`).

## FR 4 — completed
**4.1** The click does not navigate.
**4.2** The row is hidden through the existing hide gate (`hidden_at` stamped; nothing deleted).
**4.3** The row leaves the panel on the next render; the Hidden shelf restores it.
**4.4** If the hide cannot be issued, the click falls back to navigation (never swallowed).

## FR 5 — everything else unchanged
**5.1** `submitted` rows still land on `/request-approval-ui` with `r<request>`.
**5.2** `rejected` rows still land on their stored page with `r<request>`.
**5.3** Student rows still land on `/student-my-timetable-ui`.
**5.4** Inactive rows stay anchor-less and unclickable.

## FR 6 — the flash is the existing one
**6.1** The pulse is `flashRowPulse` → `.bookable-flash` → `bookable-pulse` (1.15 s, one iteration).
**6.2** If the landing page rebuilds between find and flash, the pulse lands on the element carrying the
selector at flash time (never on a detached node).

## FR 7 — no replay
**7.1** The arrival params are removed from the address bar before any matching.
**7.2** Refreshing after any of the above repeats nothing.

## Acceptance scenarios

| # | Given | When | Then |
|---|---|---|---|
| S1 | a cancellation notice | clicked | My Replacements opens, that class's row flashes once |
| S2 | an approved notice | clicked | My Replacements opens, that request's row flashes once |
| S3 | a completed notice | clicked | the row disappears from the panel; the page does not change |
| S4 | an approved notice for a class not on this lecturer's page | clicked | navigates; nothing flashes; no error |
| S5 | any of the above | the page is refreshed | nothing repeats |
| S6 | a submitted notice | clicked | the approval queue opens on the request, as today |
