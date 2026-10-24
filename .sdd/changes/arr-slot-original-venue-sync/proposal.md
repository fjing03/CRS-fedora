# Proposal — `arr-slot-original-venue-sync` (Bug 2 of the 5425 investigation, APPROVED — user law 2026-10-24)

**The approved law (from `prompts/opencode-prompt-arr-slot-original-venue-sync.md`):**
every NON-AUTO `slotTrigger` pick re-selects the picked conflict schedule's
ORIGINAL room (`slot.venue` — read at runtime, never hardcoded): BOTH the
dropdown's underlying selected value AND the visible `.venue-dd-label`.
Venue OPTIONS keep the existing `buildVenueFilter` semantics untouched.
Original room NOT offered → the standing fallback law (move to the first
filtered venue ONLY when the current venue would render dead — else keep
current). Smallest safe change; no eligibility-rule edits; no ui-common edit.

**Scope:** `commitSlotSelection()` in
`resources/views/livewire/replacement-arrangement.blade.php` only. The
session-change block keeps the session re-derive (id, `buildVenueFilter()`,
D1 pin, URL replaceState) and its eligibility fallback — now gated to
`opts.auto` so the PRE-BOOT arrival + post-boot auto-pin laws stay
byte-identical (see design.md D1). The new original-venue sync runs under
`window.__bootDone && !(opts && opts.auto)` on EVERY manual pick —
closing GAP A (same-session picks skipped the venue family entirely) and
GAP B (session-change picks kept an eligible-but-inherited venue).

**Out of scope:** Bug 1 (the strip anchor — already fixed by `548c84d` +
`4711bf8`); `slotTriggerLabel()`; new UI chrome/toasts for the
orig-not-offered keep case; the venue eligibility rule map.
