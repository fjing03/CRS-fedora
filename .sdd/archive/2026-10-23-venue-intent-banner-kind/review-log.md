# review-log — venue-intent-banner-kind

- The premise carried from venue-c2-toast-noise (56d769b): the manual branch
  already speaks (the banner + the pulse + the subject pre-select); a
  floating third toast = rejected redundancy. The user chose option 2.
- The fix (a6ef02c): ONE clause in the intentNeedsManual banner (an <em>,
  no CSS): "Pre-selected AMCS2093 (L) — its sessions fit <venue>; pick
  another free slot — the pre-selection stays." The no-match banner + the
  user-click toast untouched.
- Gates: the file 9/9 at the time (the NEW C2b row), pint ✓, phpstan-1G 0,
  phpunit 230/230; the changelog dated.
- **VERIFY (2026-10-23, tree = 1903bea = origin): the file 10/10** fresh —
  the C2b kind row green. Tasks 5/5. PASS.
