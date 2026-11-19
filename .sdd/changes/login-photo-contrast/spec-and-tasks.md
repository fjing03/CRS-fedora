# Make login guidance readable over both background photos

Status: applied and verified. The owner explicitly waived independent review after the reviewer timed out without a verdict.

- Change the shared staff/student login shell only. Keep the two photos, existing composition, compact layout, and 62% see-through footer scrim. Stabilize the card at 92% dark / 94% light using the existing surface token, and use the existing solid surface-variant token for the input fill so focus/hover do not bring the photo through it.
- Use the existing muted foreground at full opacity for the normal format hint, ID example and resting password eye. Use the existing on-primary-container token for the role link in both themes; use an underline on hover rather than reducing opacity. In the light login footer use the existing neutral foreground, without changing any authenticated footer. No new palette colours, copy, sizing, button or auth behavior. Keep inline ID correction styling unchanged. Server failure copy/alert and role-switch target size are separate findings.
- Verify rendered contrast against both painted photo crops on desktop, narrow phone and short landscape in both themes; target 4.5:1 for text and 3:1 for the eye glyph. Keep titles and quote readable. Check focus, hover and touch reveal, existing short-layout/quote and ID feedback receipts without posting a form.

## Baseline and browser-only preview

Minimum rendered samples, combined across both photos and layouts: light hint 1.67→4.98, ID example 1.39→4.58, ID label 2.68→5.00 and light footer brand 2.07→5.48. Dark role-switch link 1.52→5.44, dark eye 1.90→5.37 and light eye 1.43→4.58. The candidate numbers are from browser-only CSS probes, not application changes. Evidence: `/tmp/crs-login-color/`.

## Verification

Rechecked 20 served Chromium frames across both photos, both themes and five viewport/state cases (including a browser-only server alert, not a login attempt). The measured minima for the implemented targets matched the preview; light footer owner/note/link samples cleared 5.50:1, 5.72:1 and 5.82:1 respectively. Checked actual desktop hover/focus and touch reveal, and inspected narrow and landscape captures. All 31 Chromium regressions for layout, inline feedback and quote passed, as did Pint and PHPStan. Server alert contrast and role-switch target size remain separate. Captures and ratios: `/tmp/crs-login-color/`.
