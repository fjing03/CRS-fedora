import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * notif-inactive-hide-sender (2026-11-05) — the user's inline brief
 * "UI/UX Update — Notifications Panel", four asks in one receipt:
 *   1. an INACTIVE row carries its own Hide action in `.nr-right`;
 *   2. every `.notif-row-title` reads Title Case;
 *   3. `.notif-row-subtitle` reads `by YOU` / `by Name (staff_id)`, and the two
 *      self-owned kinds say "Your" in the title;
 *   4. at zero unread the pill still shows `0` and Mark-all stays visible but
 *      disabled.
 *
 * This spec WRITES (it hides a row), so it runs ONLY on the disposable testing
 * DB lane — the shared guard aborts any bare/demo-targeted run at collection.
 *
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/notif-row-inactive-hide.spec.ts
 */
abortUnlessTestingTarget('tests/notif-row-inactive-hide.spec.ts', { requireAllowSql: true });

/* ONE login per run via the setup project (the Fortify limiter is 5/min). */
test.use({ storageState: '.auth/staff-5425.json' });

const BASE = process.env.CRS_BASE as string;
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';
const TAG = 'PROBE inactive-hide';
/* user 1 teaches class_session 40 (seed) — so a class-level row's actor IS user 1 */
const OWN_SESSION = 40;

function sql(query: string): void {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' });
}

/**
 * Four fixtures, all for user 1 (who owns class_session 40 in the seed):
 *  A/B  type `update`, no request, a class with no `class_exceptions` row →
 *       the read-time `undone` reason, i.e. INACTIVE (the classification
 *       production uses, not a hand-set flag). READ, because the panel opens in
 *       its unread-only view, which excludes inactive rows — the spec flips that
 *       toggle, exactly like a user would;
 *  C    a REAL cancellation (an `class_exceptions` row exists) on the viewer's
 *       own class → ACTIVE, and therefore the "Your …" title case;
 *  D    one live UNREAD row so the header can be driven to zero by Mark all.
 */
function seed(): void {
  sweep();
  for (const week of [8, 9]) {
    sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, class_session_id, week, read_at, created_at, updated_at)
         VALUES (1, 'lecturer', 'update', '${TAG} Scheduled class cancelled', 'fixture body',
                 '/my-timetable-ui', ${OWN_SESSION}, ${week}, now(), now() - interval '2 day', now());`);
  }
  sql(`INSERT INTO class_exceptions (class_session_id, week_number, reason, created_at, updated_at)
       VALUES (${OWN_SESSION}, 7, 'medical_leave', now(), now());`);
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, class_session_id, week, read_at, created_at, updated_at)
       VALUES (1, 'lecturer', 'cancelled', '${TAG} Scheduled class cancelled', 'fixture body',
               '/my-timetable-ui', ${OWN_SESSION}, 7, now(), now() - interval '4 day', now());`);
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, read_at, created_at, updated_at)
       VALUES (1, 'lecturer', 'update', '${TAG} Live update row', 'fixture body',
               '/my-request-history-ui', NULL, now(), now());`);
  /* and one ACTIVE, already-READ row — the user's follow-up: Hide belongs on
     every read notification, not only the inactive ones */
  sql(`INSERT INTO notifications (user_id, role_view, type, title, body, link, read_at, created_at, updated_at)
       VALUES (1, 'lecturer', 'update', '${TAG} Read active row', 'fixture body',
               '/my-request-history-ui', now(), now() - interval '1 day', now());`);
}

function sweep(): void {
  sql(`DELETE FROM notifications WHERE title LIKE '${TAG}%';`);
  sql(`DELETE FROM class_exceptions WHERE class_session_id = ${OWN_SESSION} AND week_number IN (7, 8, 9);`);
}

async function openPanel(page: Page): Promise<void> {
  await page.click('button.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);
}

test.beforeEach(async () => seed());
test.afterEach(async () => sweep());

test.setTimeout(120_000);

test('an inactive row hides itself from the feed and still comes back', async ({ page }) => {
  await page.goto(`${BASE}/my-replacements?_=inactive-a`);
  await openPanel(page);

  // the panel opens "unread only" AND "active only" (2026-11-05, the user's
  // default) — the inactive rows live in the full mailbox, so both come off
  await expect(page.locator('#notifUnreadOnly')).toBeChecked();
  await expect(page.locator('#notifActiveOnly')).toBeChecked();
  const toggles = page.locator('.notif-filters .toggle-wrapper');
  await toggles.nth(0).click();
  await toggles.nth(1).click();
  await expect(page.locator('#notifUnreadOnly')).not.toBeChecked();
  await expect(page.locator('#notifActiveOnly')).not.toBeChecked();
  const inactive = page.locator('#notifList .notif-row--inactive');
  await expect(inactive).toHaveCount(2, { timeout: 20_000 });
  // R1: the action exists, inside .nr-right, under the timestamp
  await expect(inactive.first().locator('.nr-right .notif-hide')).toBeVisible();
  await expect(inactive.first().locator('.nr-right .notif-hide')).toHaveText('Hide');

  // R2/R4: Title Case everywhere; the self-owned cancellation says "Your"
  const titles = await inactive.locator('.notif-row-title').allTextContents();
  /* the fixture's own "PROBE inactive-hide" prefix rides the stored title, so the
     expectation carries it — the transformation under test is the rest */
  expect(titles).toEqual(['PROBE Inactive-hide Scheduled Class Cancelled', 'PROBE Inactive-hide Scheduled Class Cancelled']);

  // R4: the SELF-owned LIVE cancellation says "Your". It is READ in the fixture,
  // so the follow-up rule applies too — it carries the action.
  const yours = page.locator('#notifList .notif-row:not(.notif-row--inactive)', { hasText: 'Scheduled Class Cancelled' }).first();
  await expect(yours.locator('.notif-row-title')).toHaveText('Your Scheduled Class Cancelled');
  await expect(yours.locator('.notif-row-subtitle')).toHaveText('by YOU');
  await expect(yours.locator('.nr-right .notif-hide')).toBeVisible();

  // R3: the sender line is present and in the required shape
  for (const sub of await inactive.locator('.notif-row-subtitle').allTextContents()) {
    expect(sub).toMatch(/^by (YOU|.+ \(\d+\))$/);
  }
  await expect(inactive.first().locator('.notif-row-subtitle')).toHaveText('by YOU');

  // an UNREAD active row does not get the action; a READ one does (the follow-up)
  await expect(page.locator('#notifList a.notif-row:not(.notif-row--read) .notif-hide')).toHaveCount(0);
  const readRow = page.locator('#notifList a.notif-row--read').first();
  await expect(readRow.locator('.nr-right .notif-hide')).toBeVisible();

  // the hide writes, the row leaves the feed, the shelf count rises
  /* the find-back link only renders once something IS hidden, so 0 is read as
     "no link yet", not as a missing element */
  const before = (await page.locator('.notif-foot-link').count()) === 0
    ? 0
    : Number((await page.locator('.notif-foot-link').textContent())?.match(/\d+/)?.[0] ?? '0');
  await inactive.first().locator('.notif-hide').click();
  await expect(page.locator('#notifList .notif-row--inactive')).toHaveCount(1, { timeout: 20_000 });
  await expect(page.locator('.notif-foot-link')).toHaveText(new RegExp(`Hidden \\(${before + 1}\\)`));

  // the READ active row's Hide works and must not navigate (the anchor's default
  // action is cancelled by the action itself)
  const urlBefore = page.url();
  const readBefore = await page.locator('#notifList a.notif-row--read').count();
  expect(readBefore).toBeGreaterThan(0);
  await page.locator('#notifList a.notif-row--read .notif-hide').first().click();
  await expect(page.locator('#notifList a.notif-row--read')).toHaveCount(readBefore - 1, { timeout: 20_000 });
  expect(page.url()).toBe(urlBefore, 'hiding from a read row never navigates');
  await expect(page.locator('.notif-foot-link')).toHaveText(new RegExp(`Hidden \\(${before + 2}\\)`));

  // the shelf holds both, and Unhide still restores one
  await page.locator('.notif-foot-link').click();
  await expect(page.locator('.notif-row--hidden')).toHaveCount(before + 2);
  await page.locator('.notif-row--hidden .notif-unhide').first().click();
  await expect(page.locator('.notif-row--hidden')).toHaveCount(before + 1, { timeout: 20_000 });
});

test('zero unread keeps the pill at 0 and Mark-all visible but disabled', async ({ page }) => {
  await page.goto(`${BASE}/my-replacements?_=inactive-b`);
  await openPanel(page);

  // the lone live row is unread → Mark all is enabled
  await expect(page.locator('#notifUnreadPill')).toHaveText(/\d+/);
  await expect(page.locator('#notifMarkAll')).toBeEnabled();
  await page.click('#notifMarkAll');

  // R5: nothing is hidden — the pill reads 0, the icon stays, disabled
  await expect(page.locator('#notifUnreadPill')).toHaveText('0', { timeout: 20_000 });
  await expect(page.locator('#notifMarkAll')).toBeVisible();
  await expect(page.locator('#notifMarkAll')).toBeDisabled();
  // ...and the disabled state says so with the cursor, not just the opacity
  expect(await page.locator('#notifMarkAll').evaluate((el) => getComputedStyle(el).cursor))
    .toBe('not-allowed');
});
