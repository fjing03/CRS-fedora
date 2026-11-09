# Review log — notif-click-highlight

## proposal+design+tasks Round 1 — 2026-10-30 (combined batch)
### 🔴 Fixed (before freeze)
- The flash refinement PROPAGATED: .notif-hl-row + the 6 s timer removed
  EVERYWHERE (D3/D6/T2/proposal) — D3 now flashes via flashRowPulse(row)
  (the existing tr.bookable-flash pulse, ~1.3 s); T2 becomes "zero css
  change"; the receipts read bookable-flash/~1.3 s.
- D2's snippet rewritten to read data-notif-target (the NULLABLE degrade →
  plain navigation, no append); the inactive guard stays FIRST (mark →
  null-degrade → append → navigate).
### 🟡 Addressed (same pass)
- Unconditional URL strip (before the found-check) — pagination/filter
  misses strip too; the pagination miss receipted as an accepted skip.
- :8000 receipts pinned to the NO-real-click mechanism (DOM/structural
  snapshot only — the durable POST has no env guard); real click receipts
  live on :8030 only.
- Both-ids precedence recorded (r wins; kind-s ignored in STAGE-1) AND the
  partial emits data-notif-target ONLY for the STAGE-1 surfaces (the
  approval/history links); student-approval + cancel rows emit nothing
  until STAGE-2 (no address-bar litter).
- Flash-vs-scroll timing: flashRowPulse starts after a ~500 ms
  scroll-settle delay (the smooth-scroll can outlive a 1.15 s pulse).
- Promote ledger: flashRowPulse recorded as a same-file promo (2 call
  sites) in design D4 — the reuse law satisfied without new CSS.
- Path citations fixed to resources/views/livewire/….
### 🔴 Outstanding
- (none — implementation-ready; the STAGE-2 timetable surfaces stay parked
  on the user's ARRIVAL-GRID-NO-PIN week ruling)
# Review log — notif-click-highlight (the STAGE-2 amendment round)

## proposal+design+tasks Round 2 — 2026-10-30 (the A + cancel-row rulings)
### 🔴 Fixed (declaration-level, before re-freeze)
- The arrival find/query matched NOTHING on my-timetable: the default
  builder path renders .event-block (NOT .cell-content — that's the
  cellRender surfaces only). The D7 find = `#tableBody .event-block[data-sid]`
  and the theme arm for the s-target flash = `.event-block.bookable-flash`
  (lane-owned css arm; "zero css change" corrected to this extent).
- The stamp is id-blind-proof: `event.sessionId ?? event.id` (the house pair).
- The arrival week switch = the PAGE wrapper `window.selectWeek(idx)` (it
  re-syncs currentWeek + saveWeek); notif-w validated into [0, weeks-1]
  BEFORE the move; absent/out-of-range → the graceful skip (no move, no
  flash); the URL strip covers notif-w TOO.
- data-notif-week emitted ONLY on kind-s rows (r-rows never append notif-w).
- The s-arm emission GATED by the link string: the partial emits
  data-notif-target ONLY for /my-timetable-ui, /request-approval-ui,
  /my-request-history-ui rows — LEGACY persisted rows (the old
  /cohort-timetable + /venue-timetable-ui strings) emit NOTHING → plain
  navigation, no address-bar litter (the lifecycle law preserved).
- ONE feat commit covers STAGE-1 + STAGE-2 (T5 activated).
### 🟡 Addressed
- The approve/decided student-approval rows carry BOTH ids — the r-wins
  precedence recorded in D2.
- The pendo of .cell-content remains the arrangement's cellRender only —
  no false positives from the my-timetable default path.
### 🔴 Outstanding
- (none — A-lane ready; the theme arm ownership split with lane B is
  recorded in D7's flash note)

## Applied receipt — 2026-10-30 (the user's apply of the frozen lane)
- T1: the row partial's data-notif-target / data-notif-week (the surface gate:
  /request-approval-ui + /my-request-history-ui r-rows, /my-timetable-ui
  s-rows; the LEGACY stored links emit nothing; the inactive arm stays
  anchor-less — the keep-as-it law); the capture listener's append beside the
  durable POST (kept the mark FIRST, the inactive guard FIRST).
- T2: the flashRowPulse promo (flashEarliestBookableDay delegates — the same
  mechanics); applyNotifHighlight (the unconditional strip incl. notif-w;
  the kind-r find with the 14×250 ms async budget; the kind-s branch gated
  to /my-timetable-ui + window.selectWeek + the notif-w validation); the
  DOMContentLoaded wiring; the builder stamp data-sid = sessionId ?? id; the
  theme arm .event-block.bookable-flash (the same keyframes).
- The blade quirk receipted: DIRECTLY adjacent @endif@if directives are not
  matched by Blade's \B regex (the second directive remains literal) —
  SPACED directives are the fix; caught by the suite, unchanged output.
- T3: the :8030 receipts — the clicked r-row lands + the queue row/tr[data-id]
  FLASHES (mutation-observer receipt FLASH TR data-id=5) + the URL params
  strip; the s-row lands on /my-timetable-ui with the date selector showing
  the event's WEEK and the cell (event-block[data-sid]) flashing; the
  LEGACY row navigates PLAIN (no params); the inactive row has no
  data-id/target; zero console/5xx.
- The :8000 structural snapshot (no real clicks — the durable POST has no
  env guard).
- Gates: pint + phpstan 0 + the full suite 277/277 + the :8000 island spec
  7/7 (--grep-invert "TC70"); the ritual; node --check.
- ONE feat commit; the STAGE-2 timetable surfaces = ACTIVATED per the user's
  ruling A (the cancel rows → /my-timetable-ui; the A carve-out recorded);
  the summary-card + the today button live in notif-jump-flash-family.
- NO push.
