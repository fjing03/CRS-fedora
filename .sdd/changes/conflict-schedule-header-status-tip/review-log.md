# Review log — conflict-schedule-header-status-tip

## Batch 1 (proposal+design+tasks) Round 1 — 2026-10-28
### 🔴 Fixed (before apply)
- The cancelled arm lacked the status guard — every holiday-conflict event
  would have lied "Class Cancelled". The design snippet now checks
  `hit.event.status === 'cancelled'`; the holiday arm keeps the frozen
  literal behind it.
### 🟡 Addressed (receipt-side)
- The quad match verified safe on both scales (pinnedSessionPayload's
  start + the events' start = the SAME 0-based slotIndex; the anchor
  bridge proven); the additive key's single consumer audited; the
  handled-occurrence exclusion (pending/approved requests) noted as the
  honest no-tip residual.
### Applied receipts (live)
- 40/W14: `· Christmas Day holiday — no class` on `.cs-head` (the hover
  layer receipted); the occurrence span carries NO tip (the move).
- 40/W7: `⚠️ Class Cancelled — Annual Leave` — the user's own example
  fixture.
- 40/W5: `⚠️ Class Cancelled — Medical/Sick Leave` (the ReasonLabel
  override receipt).
- 40/W6 (normal): NO tip on the head.
- 23/W8: a HANDLED occurrence (rr #1 approved — excluded from the live
  conflict payload by the honest law) → no tip + the subject not offered
  — the "content matches the actual status" law behaving correctly; the
  original fixture row retired.
