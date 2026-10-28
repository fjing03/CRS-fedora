# Design — slot-cancel-restores-session-anchor (one helper, two call sites)

## D1. The helper (single source of the anchor trigger text)

```js
function writeSlotTriggerFromAnchor() {
    const a = anchorSlotFromPinned();
    if (!a) return false;
    const el = document.getElementById('slotTriggerText');
    if (el) el.textContent = slotTriggerLabel({ week: a.week, start: a.start, end: a.end, date: a.date });
    return true;
}
```

- Placement: beside `anchorSlotFromPinned()` (blade ~:2505) — same family.
- The label goes through **`slotTriggerLabel()`** — the shared format the
  boot comment law names for the trigger ("ONE format for the two
  writers"); `anchorSlotFromPinned()` guarantees `start`/`end` are the
  30-min indices and `date` the 'DD Mon YYYY' string the label parses.
- The guests: `#slotTriggerText` null-guarded (the exact shape the boot
  tail uses today).

## D2. Call site 1 — the boot tail (REPLACES the inline builder)

The current inline block (~:4049–4057):

```js
if (!selectedOriginalSlot) {
    const a = anchorSlotFromPinned();
    if (a) { const dp = ...; triggerText.textContent = 'W' + (a.week + 1) + ' · ' + ...; }
}
```

becomes:

```js
if (!selectedOriginalSlot) writeSlotTriggerFromAnchor();
```

Byte-identical output (the helper prints the SAME label through the
shared formatter). No behaviour drift — the guard `!selectedOriginalSlot`
stays.

## D3. Call site 2 — the cancel else arm

```js
} else {
    revertingChange = true;
    selectedOriginalSlot = null;
    document.querySelectorAll('.slot-dd-item').forEach(item => item.classList.remove('selected'));
    /* SDD slot-cancel-restores-session-anchor: on a handoff state the
       lastSlotIndex-null means the PINNED anchor stands uncommitted —
       restore the trigger to that anchor, NOT the placeholder. */
    if (!writeSlotTriggerFromAnchor()) {
        document.getElementById('slotTriggerText').textContent = 'Select a slot to replace';
    }
    renderTitleSummary();
    revertingChange = false;
}
```

- The `revertingChange` pair, the class de-selection and
  `renderTitleSummary()` are ORDER-preserving as today; only the
  unconditional trigger write becomes the conditional helper call.
- `lastSlotIndex` stays untouched (never re-pointed at a non-commit).

## D4. The receipts (falsifiable set — all read-only)

Playwright probe (staff 5425):
1. `?session=68&week=14` → URL landing trigger = `W14 · Dec 24 · 11:00
   AM–12:00 PM`; select a grid slot; dropdown `data-index=1` → modal;
   **"No, Keep My Selection"** → the trigger MUST equal the landing
   label again; the strip unchanged; `window.pinnedOccurrence` unchanged.
2. Non-handoff reset: plain landing + subject pick (no anchor from a pin:
   `PAGE_DATA.pinnedSession` null → `anchorSlotFromPinned()` null) —
   after an equivalent guard-cancel the placeholder stands (the original
   semantics; the helper's false arm).
3. `slotTriggerLabel` format-writer count: exactly the helper'sONE call
   site in the boot tail (grep receipt: no third inline 'W' + builder).
- `php -l`; lint:check + phpstan 0; the suite ≥266/266 (Blade-only —
  unchanged expectation); screenshot + DOM snippets; changelog entry.

## D4b. Round-1 🟡 folds (receipt-side, no design change)
- Receipt 1 adds the state-row assert: `selectedOriginalSlot` is
  closure-scoped — its null receipt = the OBSERVABLE pair (trigger label
  restored to the anchor label AND `.slot-dd-item` carries NO
  `.selected`) + the code-walk receipt (the else arm is the sole null
  writer of the variable in this path).
- Receipt 2 concretised: the reachable false-arm state = a non-handoff
  landing (`PAGE_DATA.pinnedSession` null asserted in the probe BEFORE
  the modal), a grid pick (the guard's live block), a dropdown pick →
  cancel; the probe asserts `anchorSlotFromPinned()` would be null by
  the payload assert (pinnedSession null) — never vacuous.
