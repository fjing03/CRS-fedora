# Explore Brief: arrange-intent-session-choice

**Status:** exploration complete 2026-10-15 (grilled session; no code touched). Ready for `/sdd-propose arrange-intent-session-choice`.
**Origin:** live user-reported dead-end 2026-10-15 — staff 5770 booked a B009 (lab, `allowed_session_types = P`-only) slot from the venue page; their only conflict subject is `L`-type; the consumer's honest fallback fired *"is conflicted"* — truthful in effect, imprecise in cause (venue-type ineligibility). The user proposed (and iterated to) the solution below.
**Sequencing:** lands BEFORE `full-block-reservation` (Fix 3) — recommended two days of discussion (separate small change wins on freeze speed, review scope, and blast radius); also resolves U2a's deferred "is conflicted" copy item early, keeping Fix 3 single-purpose.

## The problem (root cause, not symptom)

The producer handoff (`bookVenue` → `/replacement-arrangement?venue=&date=&time=&from=venue-timetable`) carries NO class context, so the consumer cannot auto-select, and the picked subject may be venue-ineligible (FR 4.7: labs host P only, tutorials T only, lecture halls L only). The dead-end appears *after* the user invested clicks — fail-early is possible, the codebase already carries everything needed.

## Rejected approaches and WHY

| Rejected | Why rejected |
|---|---|
| Copy-only fix ("is conflicted" → room-type reason) | Treats the symptom; user still hits the dead-end AFTER clicks (too late). Kept as a *sub-item* (consumer copy set), not the solution. |
| Consumer prefill only (no producer modal; the pre-brief Q1–Q6 shape) | Works, but zero-compatible is discovered post-arrival, and the user wanted an explicit choice point. Superseded by (B). |
| Modal grain = module only (pre-brief Q1 option B) | Mixed-type modules (AMCS2093 = L+P+T) would default to their first session (L) and recreate the dead-end. Occurrence grain (A) required. |
| Modal dropdowns show incompatible items greyed/disabled | User decision: **show only compatible items** (both dropdowns) — incompatible options simply don't render; honest empty message instead. |
| `[Close]` button label | User decision: **`Cancel`** (matches frozen vocabulary: cancel request/class; `Close` reads as dismissing a viewer; `Back` reads as browser-back). |
| (A) modal-only with no consumer fallback | User decision: **(B)** — modal + consumer fallback, because hand-typed/deep URL arrivals (e.g. testing, bookmarks) also deserve the saving; costs a second small prefill path, accepted. |
| Sorting candidates by "closeness to booked slot time" | Unpredictable; venue is fixed by the intent so closeness has no matrix meaning. Dropdown display order wins (frozen UI's native order). |
| `&cohort=` param dropped (derivable client-side) | User decision: keep it — the frozen template's original `code`+`cohort` URL contract restored in full; one string, consumer already parses both. |

## Final solution (grilled, user-confirmed 2026-10-15)

### (1) Producer: "Select a Session" modal on the venue page

- Trigger: the existing tooltip's **Arrange** button (U2a affordance; tooltip itself only exists on bookable cells, so too-soon/no-fit cells never reach the modal).
- Modal content per user spec: title **"Select a Session"**; **subject dropdown** (only modules owning ≥1 venue-compatible session occurrence); **session dropdown** (only the chosen module's compatible occurrences); buttons **[Cancel] [Arrange]**.
- Auto-preselection (rule Q2): first compatible module in **subject-dropdown display order**; within it, first compatible occurrence in **payload session order**. Exactly-1-compatible case = one confirm click.
- **[Arrange] navigates only with a compatible session selected** → compatible-only dropdowns make the dead-end impossible by construction.
- Zero-compatible case (no module/occurrence fits the venue type): no dropdowns; honest message *"None of your classes can run in {venue} — {room-type hosts {type} sessions only}."* + **[Cancel]**.
- **[Cancel]/Escape closes without navigating**; nothing is remembered/persisted by the modal.
- B005's no-Diploma rule participates in the filter (session cohorts including a `D*` programme → not offered for B005). B006 Cisco priority stays priority-not-ban (P sessions eligible).
- Design constraints (user-mandated): modal styling/structure reuses shared infrastructure — theme.css tokens + existing modal classes; markup as a **new Blade partial** under `resources/views/partials/` (following the `ui-class-detail-modal` idiom); no copy-paste.
- §10.0 rule 9 (mobile): ≤768px modal becomes the shared bottom-sheet pattern.

### (2) Producer payload: the venue page must know *your* classes

- New: `VenueTimetable` component gains a lecturer-courses payload (code, per-session type/cohort/duration — the shape `ReplacementArrangement::coursesPayload()` already builds).
- **OOP promotion:** extract the payload builder into a shared service/helper consumed by both components (2nd duplication today, promoted by explicit user requirement "modal design use oop/shared file"; record in design "Promoted to shared").
- Zero DB/schema change.

### (3) Handoff URL contract (user-frozen)

```
/replacement-arrangement?venue={code}&date={DD Mon YYYY}&time={HH:MM}&from=venue-timetable&code={module}&session={id}&cohort={label}
```
- `code` + `cohort` = the **frozen template's original params, now genuinely filled** (consumer parses both today).
- `session` = new numeric session-occurrence id. **Consumer already parses it** (`:3090` `params.get('session')`; binds via the wanted-session resolution; its own history rewrite writes the same param family). Verify at propose: `?session=` binds cleanly under `applyUrlParams` + `consumeBookingIntent` with zero-or-one-line consumer bridge.
- Synergy note (record, not scope): with a session in the URL the consumer's default block span derives from that session's duration — the U2a day-end advisory rule stops mattering for modal-driven flows; Fix 3 later generalizes spans 1–8.

### (4) Consumer: prefill fallback for intent arrivals that bypassed the modal (the B half)

- Fires **only** on full-intent arrivals (`from=venue-timetable` + venue + date + time) with **no subject picked yet** (subject-empty boundary; manual picks are never overridden — the banner then names the user's exact pick instead).
- Matching rule = the modal's: session-occurrence grain vs the intent venue's allowed types; first compatible module in dropdown order, first compatible occurrence in payload order; auto-select then runs through the existing `consumeBookingIntent` machinery (amber pre-selection, pulse, toast — zero new selection code).
- **Bare `?venue=` deep-links do NOT prefill** (no booked slot exists; grid-locked behavior unchanged) — intent-only, user-confirmed.
- Consumer honest-copy set (replaces "is conflicted" entirely; three non-overlapping causes, frozen wording from grilling):
  1. **Venue-type ineligibility (subject picked, incompatible):** *"{subject}({type}) has no {required-type} sessions that can run in {venue} ({room-type})."* — delivered whether or not prefill was possible; manual picks never auto-switched.
  2. **Occupancy (block no longer fits post-arrival):** *"Your booked slot ({day}, {date} · {time}) is no longer available on the {venue} grid (a block already holds it) — pick another available slot manually."*
  3. **Too-soon:** unchanged frozen wording — *"is within 3 working days — please pick a later slot manually."*
  4. **Prefill success note:** *"auto-picked {module}({type}) — its sessions fit {venue}; change if needed."* (honest attribution of the automation).
- "is conflicted — please select an available slot manually." retires entirely from `renderBookingIntent` (its occupancy branch becomes wording 2 above).

## Key cross-module data flows

- `VenueTimetable` (producer) — renders modal with `venues` + lecturer-courses payload (shared builder) → filters sessions by `venueEligible`-equivalent (venue room type vs session type; capacity irrelevant for eligibility display? **open check**: modal filters by type (+B005 cohort rule) only — capacity/cap filter intentionally NOT re-applied here, it lives in the engine) → `bookVenue(code, date, time, session, cohort)` builds the 7-param URL.
- `ReplacementArrangement` (consumer) — `resolveBookingIntent` (unchanged) → `applyUrlParams` binds `code`/`session` natively → subject-empty fallback prefill (new, small) runs when pre-modal arrival had no session → `evaluateBookingFit` + `consumeBookingIntent` (unchanged engines) → reworded `renderBookingIntent` honest copy set.
- sessionStorage family (`bookingIntentCancelled/Dismissed`, intent keys) — byte-unchanged.

## Scope inventory (expected)

- **Producer:** `app/Livewire/VenueTimetable.php` (+shared payload service), `resources/views/livewire/venue-timetable.blade.php` (bookVenue + modal wiring), `resources/views/partials/ui-intent-session-modal.blade.php` (new).
- **Consumer:** `resources/views/livewire/replacement-arrangement.blade.php` (prefill fallback + copy set only).
- **Tests:** venue-page modal spec (dropdown filtering incl. B005 rule, preselection, zero-compatible, cancel, handoff URL); consumer fallback spec (intent-without-modal prefill; manual-pick-no-override; three honest-copy branches); existing specs must stay green.
- **Docs:** both pages' changelogs + change review-log.

## Known open questions (for propose)

1. Consumer `code`/`cohort` param parsing — verify display path & that prefill doesn't double-fire when both modal-context (session) and fallback logics exist (session presence short-circuits fallback).
2. `?session=` binding under `applyUrlParams`/`consumeBookingIntent` — confirm zero-bridge; translate the one-line bridge if a gap exists.
3. Modal semantics on multi-P sessions within one module (two `AMCS2093(P)` occurrences): occurrence labels should show day/time (e.g. *"AMCS2093 (P) · Thu 14:00"*) so a duplicate-module pick is distinguishable — wording at propose.
4. Confirm the shared payload service placement (suggest `App\Services\` or an existing concern pattern; PSR/CodingMAIN §10.4).
5. Does the modal appear identically when the clicked slot's week contains PH/Sunday rules? (Tooltip only exists on bookable cells — should be moot; verify guard reuse.)

## Proposal-round-1 declarative reconciliation (2026-10-15, reviewer-directed)

- **Handoff URL is 8-param, not 7:** the grilling-derived 7-param draft gains the REQUIRED `&week={replaced-occurrence week, 1-based}` — `pinnedSessionPayload` returns null without it (verified :125–150). Semantics pinned: the pin names `(replaced occurrence session id, replaced occurrence week)` = the clicked venue cell; consumer's existing slot-picker overwrite rule stands (no new behavior). This addendum supersedes the 7-param URL in the final-solution section above.
- **Producer modal adds the duration-fit filter** to compatibility (span from the intent slot's hour must end by the grid's day end — the default `BOOK_SPAN=4` guard generalized to session-accurate spans); zero-compatible message covers a dedicated duration cause; the consumer copy set gains a day-end branch. (Closes open-question 3's adjacent hazard: duration × day-end false "conflict" cause.)
- **Consumer fallback occurrence-binding is a real bridge** (`window.pinnedOccurrence` client-side before subject bind — :2697–2701 binds the FIRST session without a pin; mixed-type modules would re-create the dead-end). `?session=` itself binds natively (open-question 2 half resolved: zero bridge for the modal path; explicit bridge for the fallback path).
- **Occurrence label format** (open-question 3) — user decision at freeze; user-decided `{module} {name} ({type}) · W{n}, {day abbr}, {DD Mon YYYY} · {HH:MM}–{HH:MM}`.
- **Attribution-note host** — the existing pre-selection toast, extended text (no new host).
- **Matrix vocabulary normalization** — design obligation: server-side canonicalization in the shared payload service (`cisco_lab` maps 'CiscoLab' vs 'Lab' across the two components today).
