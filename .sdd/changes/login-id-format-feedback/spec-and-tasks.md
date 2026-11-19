# Inline ID format feedback on both login pages

Status: applied and verified. The owner explicitly waived independent review after the reviewer timed out without a verdict.

## Acceptance

- The shared login shell shows a role-specific correction in the existing ID hint area after a non-empty malformed ID loses focus. On first load, while first typing, or for an empty/whitespace-only ID, keep the normal format hint. Once shown, the correction stays while the ID is malformed and clears when corrected or emptied. Keep the visible message tied to the field and expose its invalid state.
- Staff: **Enter 4 digits, with or without an uppercase P.** Student: **Enter 2 digits, 3 letters, then 4 digits (for example, 25RSD0001).** The correction wraps and is readable in both themes using existing palette tokens. Do not change the normal hints or switch staff to a numeric-only keyboard: `P5425` must be as easy to enter as before.
- Reuse the same regex and trim rule for the button and inline message. Preserve acceptance of `5425`, `P5425`, and existing student ID formats. Button enablement, password behavior, form submission, and server authentication/errors remain unchanged.
- Verify malformed → blur → correction, show/clear as edited, empty reset, valid formats, and button behavior on both pages at desktop and phone sizes, including a 430px keyboard-height proxy. Check the longer student message wraps at 320px and measure error contrast on the photo-backed surface. No demo DB writes or real login attempts for this task.

Baseline: both `542` and `25RS0001` disable Log in with a password present, but no inline correction appears on blur. Browser evidence: `/tmp/crs-login-critique/`.

## Verification

Eight Chromium checks passed across both roles at 1440×900 dark, 390×430 dark, 320×568 light and 320×430 light on the protected testing server. Desktop used Tab; phones used a genuine tap into Password. Both messages displayed after blur, stayed while malformed, and cleared on correction and whitespace. Valid formats retained the existing submit gate. The student correction wraps to two lines at 320px without overflow. Contrast measured 6.93:1 dark and 9.29:1 light. Pint and PHPStan passed. No form was submitted. Captures and JSON: `/tmp/crs-login-id-feedback/`.
