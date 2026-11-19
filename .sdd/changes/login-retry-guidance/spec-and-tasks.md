# Make a rejected login easier to recover from

Status: applied and verified. The owner explicitly waived independent review for this fix after the reviewer timed out without a verdict.

- In the shared login shell, keep the ordinary credential failure response **These credentials do not match our records.** on both routes. Only for that message, add **Check your Staff ID format and re-enter your password.** or **Check your Student ID format and re-enter your password.** No distinction between an unknown ID and a wrong password.
- For ordinary staff failure only, also show **Forgot your password? Reset it through the TARUMT intranet.** This is the recovery route already named in staff lockout copy; do not invent or guess a URL. Student has no verified recovery route in scope, so do not promise one. Leave lockout, throttle, other errors and status wording untouched; do not change validation or authentication logic, redirects, retained ID or cleared password behavior.
- Use the existing on-error-container foreground over a solid surface/error-token tint for login alerts, with a 4.5:1 text contrast target in both themes. Put guidance on its own line. Keep compact layout and document scrolling; the alert should be readable and the password and Log in reachable at short heights. Do not change the inline format correction styling.
- Verify one fictional, non-account rejection per role on the protected testing server only; do not trigger lockout or flood the endpoint. Check exact guidance, retention, cleared password, both themes, short viewport fit/reachability, and scope to the generic error. No demo DB changes.

Baseline: the critique captured the unhelpful generic failure on both routes; a later browser-only alert probe still measured 2.41:1 dark / 4.11:1 light after the separate photo guidance contrast fix (`/tmp/crs-login-color/after-ratios.txt`).

## Verification

One fictional rejection per role on the protected testing server returned to the same route with ID retained, password cleared and the expected guidance. Both themes wrapped without clipping at 390×430 or 320×430; the alert measured 6.47:1 dark and 7.32:1 light. A real tap reached Password and the submit button could be scrolled fully into view after the alert. Ten Playwright checks (including inline ID feedback), three synthetic PHP view checks (lockout, throttle and session status), Pint and PHPStan passed. No real account was used and no demo DB changes were made. Evidence: `/tmp/crs-login-retry/`.
