# explore-brief — `cohort-venue-replace-now` (2026-10-24)

## User law (verbatim)
"Cohort page & Venue page shud've Replace Now button too. for OWN Original
Conflicted Class."

Reading: BOTH pages carry the Replace Now affordance, gated to OWN
conflicted classes only. The venue page ALREADY has it (class-modal-
affordances T3, gate `isMine && event.status === 'conflict'`, viewed week).
The cohort page deliberately does NOT (its footer comment cites "the frozen
design D1/D3 — no Replace Now on cohort"). **The user's new law SUPERSEDES
that frozen decision.** This lane lands the cohort page's button and
re-verifies the venue page's receipt.

## Code receipts (verified 2026-10-24)

- **`goToReplacementFor(sessionId, week, from)`** — shared
  (`public/js/ui-common.js` :1318); `my-timetable` passes `'my-timetable'`,
  venue passes `'venue-timetable'`, replacement-home's real flow passes
  `'replacement-home'`. The handoff shape
  `?session=&week=&from=` is the D1 pair the arrangement page resolves.
- **`BackNavigator.#routes`** (:3017) has NO `'cohort-timetable'` key → a
  cohort-handoff would fall back to `/replacement-home-ui` on the way back.
  The non-breaking fix (route-map additive row) keeps all four existing
  rows byte-identical.
- **Cohort payload** (`app/Concerns/ResolvesTimetableTimeline.php`
  `baseEvent` :362–385): every event carries `id` (= class_session_id),
  `lecturer` (the lecturer's NAME), `status` ('conflict'|'normal'|…),
  venue/cohort/studentCount. So the client OWN+conflict gate needs **NO new
  payload fields** — `event.id` is the sessionId, `event.lecturer` the
  ownership key (same shape the venue page reads; it reads
  `event.mine === true || event.lectuer === MockData.currentUser?.name`).
- **The cohort page's conflict colouring already computes
  `var isMine = e.lectuer === MockData.currentUser.name`**
  (cohort-timetable.blade.php :485) — the ownership bridge
  (`MockData.currentUser` hydrate :180) exists; reuse it.
<!-- R1: the co-terminal parity note — the gate's ownership key receipt at the venue page is event.lecturer (correct spelling); the 'typo' mention below was a NOTE-WRITING error, do not path it. -->
- **The cohort modal** = `partials.ui-class-detail-modal` (`modalId: eventModal`)
  + `@section('modal-footer')` (left Close / right the runtime-appended
  Cancel Class?). The venue page's footer include is the verbatim reuse
  source (its `btnReplaceNow` button markup lives in the SAME footer block).
- **my-timetable's button** (:103) is the richer gate
  (`stampedEvent.occurrenceFact` — the D-P4 exact-pair law). The venue page
  uses the SIMPLER gate (`isMine && status === 'conflict'`, viewed week).
  The venue gate is what the user's law restates ("OWN Original Conflicted
  Class" = own + conflict + the fact weeks the grid shows) — the COHORT page
  reuses the venue gate family (the simpler one), consistent with the venue
  page (they share "grid shows all classes, not just mine").
- **The cohort page's cancellation destroy-simulation** — CancelClass
  spans only 'cancelled-class'; a subsequent Replace Now on the SAME class
  would carry a stale sessionId after a simulated cancel. The CancelClass
  modal closes on its action; the Replace Now reads a FRESH
  `currentModalEvent` — but the twin-store bridge
  (`MockData.myTimetable = { eventsByWeek: … }`) is rebuilt per
  buildTimetable(); the button and the event bind at the NEXT modal open —
  the simulated-cancelled event won't reappear in that cohort's grid build
  (buildTimetable filters cancelled... — actually the cancel machinery's
  resolveMatches mutates + writes the ledger; the grid rebuild inside the
  cancel callback re-derives). Edge case noted: Replace Now must NOT offer
  itself when the class is cancelled (CancelClass self-hides S1–S5 /
  status != conflict guard covers the visible gate; the Replace Now gate
  checks `status === 'conflict'` on the SINCE-POPPED event — safe).

## Rejected approaches and why

1. **New custom action route / Livewire component for the handoff** —
   rejected: `goToReplacementFor` is the existing shared click-through
   (three call sites already); a new route would clone the arrangement
   page's own URL law.
2. **Reusing my-timetable's `occurrenceFact` gate on cohort** — rejected
   for THIS lane: the cohort/venue pages serve all-lecturer grids; the
   D-P4 stamp exists only on my-timetable's own advisories. The venue
   gate (isMine + status) is the page-family-consistent shape; the
   "viewed week" scoping is inherent (the modal opens from a rendered
   event in the seen week).
3. **Also adding to my-timetable** — NOT in scope: the user named cohort +
   venue only; my-timetable already has its richer Replace Now.

## Final solution mapping

- Cohort page footer: add the `btn-replace-now` button (the venue markup
  verbatim module: `<button class="btn-replace-now" id="btnReplaceNow"
  style="display:none" onclick="goToReplacementFor(currentModalEvent?.id,
  currentWeek + 1, 'cohort-timetable')">Replace Now</button>`).
- `openModal` (~:525): set `btnReplaceNow.style.display` per the VENUE gate
  (`event.mine === true || event.lectuer === MockData.currentUser?.name`
  — fix the typo receipt: `event.lecturer === MockData.currentUser.name`)
  `&& event.status === 'conflict'`.
- The `week` arg: **SUPERSEDED — see the Q1 RESOLVED section below
  (the +1 for BOTH pages)**; the earlier "match the venue's raw-pass
  convention" instruction here is retired so no implementer applies it.
- `BackNavigator.#routes` (ui-common.js): ADD)
  `'cohort-timetable': '/cohort-timetable-ui'` — the way BACK from an
  arrangement session opened from the cohort page. keep the other four
  rows byte-identical.
- Venue page: NO fix — the receipt re-verifies (its button exists; the
  proposal freezes the re-verify as a task).

## Known open questions

- Q1 (design): the venue's `goToReplacementFor(currentModalEvent?.sessionId,
  currentWeek, 'venue-timetable')` — verify the venue's existing test
  receipts (class-modal-affordances lane, tests 5/5 green) to pin whether
  `currentWeek` there is the 0-based internal value — if yes, the handoff's
  `?week=` = the correct Week number only because the VenueTimetable page
  builds `currentWeek` 1-based? Resolve BEFORE tasks by reading
  `goToReplacementFor` + the arrangement page's pinnedSession resolution
  (server-side resolves `?week=` into the pinned pair — the arrangement
  expects the HUMAN week number 1–14; my-timetable passes
  `rarityReplaceOccurrenceWeek()` = the occurrenceFact.week (human).
  Venue passes `currentWeek` — venue `currentWeek` = ??? read to resolve.).

## Open question RESOLVED (receipt, 2026-10-24)

- **Q1 answer:** the venue page's `currentWeek` = the **0-based index**
  (`currentWeekIndex()` = the today-vs-semesterStart floor, 0-based,
  ui-common.js :36–43; weekNav._currentWeek mirrors it raw). The venue
  handoff therefore passes the TOOTED RAW INDEX. The arrangement page's
  `pinnedSessionPayload` validates `week` in **1..14 1-based human terms**
  ($week < 1 || $week > $weeks → null). The class-modal-affordances
  receipts that WERE green (the lane spec's `?session=68&week=14` and the
  replace-now route probe green ×1) rode the **10-based URL** hand shape
  (the my-timetable's occurrenceFact.week = human) and NOT any venue
  button click with the 0-based currentWeek. So the VENUE page has a
  LATENT OFF-BY-ONE: its own Replace Now handoff passes a 0-based index
  where the arrangement expects the 1-based human week — e.g. clicking
  Replace Now on the W2 venue row (currentWeek=1) hands off
  `?week=1` = the arrangement reads that as WEEK 1 (the wrong pair → the
  pinned-session ownership gate would seek the session at W1 — the same
  session id resolves but the WEEK pair the submit keys is WRONG).
  THE FIX: BOTH the venue page's AND the cohort page's button hand off
  `currentWeek + 1` (the human week number; the mock's 0-based → 1-based
  convention = the `+1` the BRIDGE PATCH spells everywhere else). The
  class-modal-affordances frozen T3 markup DID carry this latent city
  (design D-E2 "the viewed-week-gate verified at the modal-open gate" —
  never the handoff's arithmetic); fixing it here = the SAME lane scope
  (the user's law = cohort AND venue buttons honest).
