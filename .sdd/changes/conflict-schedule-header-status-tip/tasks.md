# Tasks — conflict-schedule-header-status-tip

## T1. Server key (≤0.25h)
- [ ] `conflictEventsPayload`: the additive `cancelledReason` via
      `ReasonLabel::from` (null for non-exception events); `php -l`.

## T2. Client move + status resolve (≤0.5h)
- [ ] `renderTitleSummary`: the statusTip resolver (cancelled → holiday →
      none); the attr MOVES: the `.cs-head` div gains the conditional
      data-tip; the occurrence span loses it; `escHtml` on the value.
- [ ] `php -l` + the stale-serve ritual (2 commands) + :8001.

## T3. Receipts (≤0.5h)
- [ ] Playwright: the three anchors (23/W8 cancel, 40/W14 holiday,
      40/W7 none) + the textContent identity + hover screenshots.

## T4. Gates + bookkeeping (≤0.5h)
- [ ] lint:check + phpstan 0 + suite ≥266/266 (background).
- [ ] Changelog entry; ONE commit `fix(ui): ...`; lane → applied.
