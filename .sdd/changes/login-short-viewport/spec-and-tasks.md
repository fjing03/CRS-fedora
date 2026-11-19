# Compact login layout for short windows

Status: applied and verified. The owner explicitly waived independent review after the reviewer timed out without a verdict.

## Scope and acceptance

- Change the shared staff/student login shell once. At heights of 600 CSS px or less, put a smaller logo beside the existing title and reduce vertical padding and gaps, not the input and button hit boxes. Keep the title, theme toggle, form, role switch and footer available; preserve native vertical scrolling, especially when an error or keyboard takes space. No copy, validation, colour or authentication changes.
- In the compact view, both fields and the full Log in button should fit at the top of the page at 320×430, 390×430 and 844×390, including the wrapped inline ID correction. At smaller heights and with the server alert, ensure the button and footer remain reachable without overlap rather than forcing them into a fixed frame. Maintain the existing 48px coarse targets; the role-switch link is a separate issue.
- Ask supported browsers to resize the layout viewport for an on-screen keyboard (`interactive-widget=resizes-content`); unsupported browsers keep their normal viewport and scrolling behavior. Distinguish Chromium viewport resize tests from physical keyboard/Safari verification.
- Above the compact threshold, preserve the existing form layout. Keep the quote on roomy desktops only: at least 769px wide and 840px tall, so a 700px window no longer inserts decoration above the form. Keep quote content, rotation and assets unchanged. Check the two quote gates and normal 1440×900 on both roles.
- Verify current baseline, both themes, genuine taps, the short sizes above, 320×568 and 390×844, resize while the ID field is focused, form/error reachability and normal-size isolation. Run the existing ID feedback and quote checks on the protected testing service with no sign-in submissions or database writes. Update the login changelog and stage only this lane.

Baseline: on both roles Password starts around y=433 and Log in y=503 at 390×430; at 844×390 they start near y=425 and y=495. At 1024×700 the quote adds 109px above the form vs 1024×699. Browser-only preview confirms the smaller side-by-side header buys more room than shrinking a stacked header alone.

## Verification

31 protected-target Chromium checks passed, covering both forms and themes, short windows, authentic taps, wider layouts, quote gate and contrast, and the previous inline feedback behavior. With the ID correction visible at scroll top, the button ends at y=381 (staff) / y=395 (student) at 320×430 and 390×430, and y=329 for both at 844×390. Both fields and the full button fit; primary controls kept the coarse 48px floor. A browser-only server alert at 320×300 left the button and footer reachable by scrolling. The resize proxy retained focus and input; physical keyboards and Safari were not tested. Pint and PHPStan passed. Evidence: `/tmp/crs-login-short/`. No login requests or database changes.
