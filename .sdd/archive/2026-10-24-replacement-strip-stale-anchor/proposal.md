# Proposal — `replacement-strip-stale-anchor` (Bug 1 of the 5425 investigation, APPROVED)

**The approved fix (only Bug 1; Bug 2 = held by the user):** after a POST-BOOT
subject change, the Conflict Schedule strip must anchor to the LIVE auto-pinned
pair — not the boot-frozen virtual anchor (PAGE_DATA.pinnedSession + the boot
urlParams facts).

**The change (one blade, one gate):** in `renderTitleSummary()`, when
`window.__bootDone` and a live `window.pinnedOccurrence` exist and the current
subject's conflict list contains that pin's occurrence, the anchor = that real
pick-shaped slot object (reuses `extractSlotsForSubject` — no new data path);
otherwise the existing `anchorSlotFromPinned()` boot anchor stays untouched.
Pre-boot arrivals keep byte-identical behavior (no gate violation; the T18/T6
auto-pin law respected).

**Out of scope:** Bug 2 (the venue label re-validation) = HELD by the user's
word; the submit path; the picker defaults.
