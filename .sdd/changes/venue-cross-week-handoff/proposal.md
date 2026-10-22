# Proposal — venue-cross-week-handoff

## Why (with the live repro)

The venue→arrangement handoff pins a pair that does not exist whenever the
booked slot and the class occurrence live in DIFFERENT weeks. Real repro
(staff 5770, verified 2026-10-20):

1. `venue-timetable-ui?venue=B111`, Week 7; click the free Tue 03 Nov
   13:00 cell; the intent modal lists the conflicted occurrences; pick
   **`(L) W8, Mon, Nov 9 . 11:00 ~ 13:00`** (AMCS2093).
2. The handoff URL reads `…&week=7` — the venue page's VIEWED week —
   because `bookVenueWithSession` hardcodes `currentWeek + 1`
   (`venue-timetable.blade.php:580`) and drops the occurrence week the
   picker row actually carries (`item.dataset.week` at :484).
3. `pinnedSessionPayload` (server) pins the pair `<97, 7>` — a pair that
   does not exist (97's only fact = the W8 Deepavali Monday). The strip
   invents "Week 7 · Mon, Nov 02 … @ B110" and the boot pins `{97, 7}` —
   the URL, the server payload and the strip anchor all describe a fact
   that is not real.
4. The param `week` does double duty (slot week vs occurrence week); the
   design assumed they are the same. Cross-week handoffs break it — and
   the user explicitly ruled cross-week booking VALID (2026-10-16).

### The honest threat model (reviewer round 1)

A submission TODAY happens to file the TRUE pair `{97, 8}` because the
boot's T18 auto-pin coincidentally rewrites `{97, 7}` → `{97, 8}` (97 is
the module's earliest conflict) — a rescue owned by another change
(`session-arrival-pin-guard`). The real harms: (a) the strip lies
(the phantom Week 7 line); (b) the URL + the server payload store a
nonexistent pair; (c) the rescue does not generalise — a cross-week pick
of a NON-earliest occurrence (e.g. an AMCS2093 `(P) W14` row from the
same W7 slot) submits a DIFFERENT week/occurrence than the one picked
(strip honest W14, pin `<= the earliest, 8>`). Threading the picked week
kills (a) + (b) and makes (c)'s honest end-to-end state reachable; the
arrival gate (the parallel change) stops the clobber that stands between.

## Root cause (one sentence)

The URL's `week` param means "the occurrence being replaced" downstream
(`pinnedSessionPayload`), but the venue page writes "the week I'm
viewing" — which only coincides when the booked slot and the occurrence
share a week.

## In scope (producer-only)

- `venue-timetable.blade.php`: two edits — `confirmIntentSession` carries
  `pickedRow.dataset.week`; `bookVenueWithSession` gains `occurrenceWeek`
  and writes `&week=occurrenceWeek ? occurrenceWeek : currentWeek + 1`
  (rows without a week keep today's byte-identical fallback).
- A Playwright regression row (cross-week pick → `&week=8` → honest
  strip + pin) + the same-week byte-parity control row.
- Dated changelog entries — BOTH the arrangement and the venue page
  changelogs (the producer's handoff semantics + signature change; the
  standing changelog rule).

## Explicitly NOT in scope

- No producer-side validation of cross-week pairings (user ruling keeps
  them valid).
- No `&occWeek=` second param (see explore-brief rejections).
- No sessionStorage handoff.
- **The consumer edit at `:2590` — REMOVED after reviewer round 1 🔴**:
  the site is unreachable for hydrated-pin flows (`armIntentFallbackPrefill`'s
  `:2541` `if (urlParams.session) return;` short-circuit mutually excludes
  a hydrated pin); a same-session "pin-keep" guard there would be dead
  code with a comment describing an impossible event. The venue path's
  real re-pin risk (the T18 clobber at `:2966`) belongs to
  `session-arrival-pin-guard`.
- Server PHP: zero edits — `pinnedSessionPayload` already reads `week`
  and threads it verbatim.

## Impact scope

| File | Change |
|---|---|
| `resources/views/livewire/venue-timetable.blade.php` | 2 edits: `confirmIntentSession` passes the row week; `bookVenueWithSession` takes `occurrenceWeek` and writes it into `&week=` |
| `tests/venue-cross-week-handoff.spec.ts` (NEW) | cross-week pick regression row + same-week byte-parity control |
| `page-changelogs/replacement-arrangement-changelog.md` | dated fix entry (param semantics) |
| `page-changelogs/venue-timetable-ui-changelog.md` | dated fix entry (the handoff URL + the signature change) |

## Success criteria

- The user's exact repro: handoff URL `&session=97&…&week=8`; strip reads
  `Week 8 · Mon, Nov 9 2026 · 11:00 AM–1:00 PM @ B110` (AMCS2093's true
  occurrence). (`window.pinnedOccurrence` = `{97, 8}` also holds — a
  non-discriminating assert today since the T18 coincidence produces the
  same value; the URL + the strip are the discriminating receipts.)
- Same-week handoffs produce BYTE-IDENTICAL URLs (occurrence week ==
  viewed week + 1 exactly equals today's value — design D4 matrix).
- The other real producers of `?session&week` handoffs (replacement-home
  `:533`'s occurrence `week_number`; the arrangement's own `:3217`) are
  untouched and already honest.
