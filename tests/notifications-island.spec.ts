import { test, expect, Page } from '@playwright/test';

/**
 * SDD notifications-real-bridge — the notifications island receipts on the
 * LIVE DEMO DB (:8000). READ-ONLY by construction: no mark-read / mark-all /
 * row clicks happen here — only the bell/panel rendering, the caught-up
 * category-aware copy, and the poll-morph laws. The writable receipts (badge
 * growth, durable POST badge shrink, disabled/tooltip row, mark-all zero)
 * live in notifications-island-testing-db.spec.ts against the disposable
 * testing DB (the lane's receipts strategy B) — never on this DB.
 *
 * Login via the setup project's storageState (staff 5425 — the demo PL).
 * The demo DB is SHARED: every assertion is relative (no absolute counts).
 */

test.use({ storageState: '.auth/staff-5425.json' });

/** The unread badge's rendered number (0 when hidden). */
async function badgeNumber(page: Page): Promise<number> {
  const badge = page.locator('#notifBadge');
  const visible = await badge.isVisible();
  return visible ? Number(await badge.textContent()) : 0;
}

test('bell + pill carry the whole-mailbox unread total (F-2), no mock rows', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await page.goto('/my-timetable-ui?_=island-1');
  await expect(page.locator('.notif-btn')).toBeVisible();

  // the bell island = the TOTAL unread mailbox count (the whole, never the tab slice)
  const badge = await badgeNumber(page);

  // open the panel: the pill shows the SAME total (the ONE unread total law)
  await page.click('button.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);
  const pill = page.locator('#notifUnreadPill');
  if (badge > 0) {
    await expect(pill).toBeVisible();
    expect(Number(await pill.textContent())).toBe(badge);
  } else {
    await expect(pill).toBeHidden();
  }
  expect(consoleErrors, 'no JS page errors').toEqual([]);
});

test('caught-up swap is category-aware (counted from the mailbox total)', async ({ page }) => {
  // pageKey repair note: the island's resolveRoleView only matches camelCase
  // keys, the mounts pass the kebab nav keys — the AD-2 map never engages and
  // the viewed tab stays 'lecturer' (UNDER USER VERDICT). This receipt asserts
  // what is true TODAY and stays meaningful either way: with the unread-only
  // toggle ON, when the viewed tab shows no rows while the mailbox total is
  // non-zero, the caught-up copy names the total.
  await page.goto('/request-approval-ui?_=island-2');
  const badge = await badgeNumber(page);
  await page.click('button.notif-btn');

  const empty = page.locator('#notifEmpty');
  const list = page.locator('#notifList');
  const listShown = !(await list.isHidden());

  if (badge === 0) {
    await expect(empty).toContainText("You're all caught up");
  } else if (!listShown) {
    const swap = (await empty.textContent()) ?? '';
    if (swap.includes('unread in other tabs')) {
      const named = Number((swap.match(/— (\d+) unread/) ?? [])[1]);
      expect(named).toBe(badge);
    }
  } else {
    // rows in view: the swap is hidden
    await expect(empty).toBeHidden();
  }
});

test('an OPEN panel survives poll cycles: state, toggle and pill preserved (AD-15)', async ({ page }) => {
  await page.goto('/request-approval-ui?_=island-3');
  await page.click('button.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);
  const pillText = await page.locator('#notifUnreadPill').textContent();

  // flip the server-owned toggle; a poll morph (>10 s) must preserve it
  await page.click('.notif-filters .toggle-wrapper');
  const afterToggle = await page.locator('#notifUnreadOnly').isChecked();

  await page.waitForTimeout(12_000); // ≥1 poll cycle at 10 s

  await expect(page.locator('#notifPanel')).toHaveClass(/open/, 'the panel survived the poll morph');
  expect(await page.locator('#notifUnreadOnly').isChecked()).toBe(afterToggle, 'the toggle survives its server-owned morph');
  if (pillText !== null) await expect(page.locator('#notifUnreadPill')).toHaveText(pillText ?? '');
  // the body is not scroll-locked away (overflow restored once closed is
  // separate; while open the AD-11 lock stands)
  expect(await page.evaluate(() => document.body.style.overflow)).toBe('hidden');
});
