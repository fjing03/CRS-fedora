# Make the login role-switch link easier to tap

Status: applied and verified. The owner explicitly waived independent review for this fix after the reviewer timed out without a verdict.

- In the shared login shell, give the existing Staff Login / Student Login anchor a minimum 48×48 CSS-pixel hit box when `(any-pointer: coarse)` matches, including wide touch devices. Keep it a secondary text link under Log in; no copy, URL, colour, keyboard, auth or mouse-only layout changes.
- Use the shell's existing coarse-pointer CSS. Verify a tap within the enlarged area outside the text reaches the other role on a narrow phone, landscape and wide touch; confirm mouse-only links are unchanged, and keep the full Log in button reachable in compact view. Use the protected testing service and no sign-in attempt.

Baseline: served Chromium measures 86×16px on staff login and 67×16px on student login at narrow, landscape, and wide widths. A browser-only CSS preview enlarges coarse links to 86×48px and 67×48px; mouse-only stays 16px tall. Evidence: `/tmp/crs-role-switch/baseline.txt`.

Verification: all eight Chromium checks passed at 320×430, 844×390 and 1440×900 across both routes. The expanded upper and lower edges hit the anchor, and genuine taps above the text navigated both ways. Mouse-only inline links still worked, and Log in was fully visible before scrolling. Pint and PHPStan passed. Captures and test report: `/tmp/crs-role-switch/`. No form was submitted.
