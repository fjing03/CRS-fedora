# Design — student-nav-label-parity

Frozen baseline: proposal.md (R1 PASS). Baseline context: explore-brief.md.

## 1. Shared whitelist label (`partials/ui-nav-bar.blade.php:13`)

Student item 2: `'label'=>'Request History'` → `'label'=>'Replacement History'`. Key (`replacement-history`) and href unchanged → `activeNav` highlighting (lines 39, 102 compare keys) intact. Lecturer literal at line 19 untouched.

Result (both student pages, bar + drawer):

```php
$isStudent ? [
    ['key'=>'my-timetable','label'=>'My Timetable','href'=>'/student-my-timetable-ui'],
    ['key'=>'replacement-history','label'=>'Replacement History','href'=>'/replacement-history-ui'],
]
```

## 2. Override deletions

- `ui-design-templates/replacement-history-UI-design-template.blade.php` (LIVE-served, routes/web.php:91-94 fallback): delete the `'navItems' => [...]` entry from the `@extends('layouts.ui-template', [...])` array. Keep `'homeUrl' => '/student-my-timetable-ui'`, `'activeNav' => 'replacement-history'`, `'pageKey' => 'replacementHistory'`.
- `ui-design-templates/student-my-timetable-UI-design-template.blade.php` (fallback-only): same deletion; keep `homeUrl`/`activeNav`/`pageKey`.

After both: the only `$navItems` references are the layout pass-through (layouts/ui-template.blade.php:35) and the partial consumer (ui-nav-bar.blade.php:23).

## 3. Playwright parity assertion (`tests/nav-identity.spec.ts` test 2)

Insert between the href assertions (L60-68) and the direct-visit check (L70-76):

```ts
// Nav label parity (student-nav-label-parity): both student pages show the
// same two labels from the shared whitelist.
const expectedLabels = ['My Timetable', 'Replacement History'];
for (const path of ['/student-my-timetable-ui', '/replacement-history-ui']) {
  await page.goto(path);
  await page.waitForSelector('.nav-items a');
  const barLabels = await page.locator('.nav-items a').allTextContents();
  expect(barLabels).toEqual(expectedLabels);
}
await page.goto('/student-my-timetable-ui');
await page.setViewportSize({ width: 375, height: 667 });
await page.click('#navHamburger');
const drawerLabels = await page.locator('#navDrawer .nav-drawer-item').allTextContents();
expect(drawerLabels).toEqual(expectedLabels);
```

(Exact selectors pinned: `.nav-items a` bar, `#navDrawer .nav-drawer-item` drawer. Rate-limit note: the test logs in once as `25RSD0001`; the two page `goto`s are cookie-session navigations, not logins.)

## 4. Wave 3b guard (proposal R1 💡 — recorded for the archive)

When `ReplacementHistory` is eventually wired (Wave 3b), the legacy template must **keep inheriting the whitelist — do not re-introduce `navItems`**. Recorded in review-log and the cohort/auth changelog entries point here.

## 5. Changelogs (per frozen proposal §2.5)

- `auth-wiring-changelog.md`: primary entry — whitelist label rename + both override deletions + single-source note.
- `replacement-history-changelog.md`: page now inherits the shared nav (labels + highlighting unchanged mechanically).
- `student-my-timetable-ui-changelog.md`: one-line postscript correcting the earlier "Request History" claim → "Replacement History".

## 6. Gates (mirror proposal SC4)

`composer run lint:check` · `composer run types:check` (or `vendor/bin/phpstan analyse --memory-limit=1G` directly — the composer script does not wire the flag) · phpunit 129/129 · nav-identity 3/3 (extended) · timetable-wiring 5/5 · venue-db 4/4. Records-intact: no DB writes. Guarded server restart (standalone `pkill -f "[a]rtisan serve" || true` + view-cache clear; never `pkill -9 php`) — blade changes.

## 7. Commit plan (no push without explicit authorization)

1. `feat(nav): student nav label parity — 'My Timetable' + 'Replacement History' on both student pages` (partial + 2 templates + spec + 3 changelogs).
2. `docs(sdd)` archive after verify.
