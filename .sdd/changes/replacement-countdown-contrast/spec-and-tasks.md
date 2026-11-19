# Dark-mode replacement countdown contrast

Status: applied and verified; owner explicitly waived independent review for this batch after the reviewer timed out without a verdict.

- Make the red Overdue and green Working Days Left countdown text readable in dark mode on My Replacements and Class Replacements only. Use existing on-error-container/on-success-container palette tokens; require at least 4.5:1 on the rendered surfaces as a readability yardstick.
- Scope to the two page wrappers, including tables, cards and details. Leave light mode, amber, Request Approval, backgrounds, wording and countdown calculations unchanged.
- Verify current served examples at desktop and phone sizes, and check details without submitting anything. Account for row hover and card/detail backgrounds. No DB changes.

Current served baseline: red 2.65:1 and green 2.98:1 on both lists at 1440 and 390px; hovered desktop rows drop to 2.25:1 and 2.53:1. Light desktop text measures 5.62:1 red and 5.00:1 green. Logs: `/tmp/crs-countdown-contrast/`.

After: red 6.93:1 and green 7.44:1 on both served lists, phone cards and detail modals. Hovered rows measure 5.89:1 red and 6.32:1 green. Light desktop colors and measured ratios are unchanged. Desktop click and real phone tap opened the detail views; no data was submitted.
