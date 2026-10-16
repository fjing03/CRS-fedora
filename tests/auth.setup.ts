import { test as setup } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

/**
 * auth.setup.ts — one-login-per-run storageState scaffolding (U2a T1).
 *
 * The Fortify login limiter is 5/min per login_id|login_type|ip
 * (FortifyServiceProvider), so big spec files must never log in per test.
 * This setup project UI-logs in once as staff 5770 (the demo-fixture user —
 * same precedent as type-labels.spec.ts) and saves the storage state to
 * .auth/staff-5770.json. Individual spec files opt in via
 * `test.use({ storageState: '.auth/staff-5770.json' })` — nothing global,
 * so specs with their own login flows are untouched.
 */
const PW = 'Tarumt@2026';

/** One storageState per staff identity (Fortify limiter: 1 login/run). */
async function loginAndSave(page, id, file) {
  mkdirSync(path.dirname(file), { recursive: true });
  await page.goto('/login/staff');
  await page.fill('#login_id', id);
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  // artisan serve is single-threaded — first boot / DB-cold logins can spike
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 30_000 });
  await page.context().storageState({ path: file });
}

setup('authenticate as staff 5770', async ({ page }) => {
  await loginAndSave(page, '5770', path.join('.auth', 'staff-5770.json'));
});

/* arrange-intent-session-choice manual-support state: 4363 (L-only lecturer)
   — the zero-compatible modal demo identity. */
setup('authenticate as staff 4363', async ({ page }) => {
  await loginAndSave(page, '4363', path.join('.auth', 'staff-4363.json'));
});
