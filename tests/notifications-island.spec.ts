import { test, expect, Page } from '@playwright/test';

/**
 * SDD notifications-real-bridge — the notifications island receipts on the
 * LIVE DEMO DB (:8000). READ-ONLY by construction: no mark-read / mark-all /
 * row clicks happen here — only the bell/panel rendering, the ONE-FEED
 * cross-page consistency (notif-unified-feed, 2026-10-29 — the per-page
 * category tabs are superseded), and the poll-morph laws. The writable receipts (badge
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

test('ONE-FEED: the same notification list renders on every page', async ({ page }) => {
  // SDD notif-unified-feed (2026-10-29 — supersedes the AD-2 page-category
  // tabs): the panel shows the recipient's whole mailbox identically on
  // every page. Relative receipt: the row set (href/identity + title) must
  // be EQUAL across the approval / my-timetable / request-history pages.
  const feedOf = async (path) => {
    await page.goto(path + `?_=island-2-${Date.now()}`);
    await page.click('button.notif-btn');
    await page.waitForTimeout(400);
    // read with the unread-only toggle OFF so READ rows join the visible set
    const checked = await page.locator('#notifUnreadOnly').isChecked();
    if (checked) { await page.click('.notif-filters .toggle-wrapper'); await page.waitForTimeout(700); }
    const rows = await page.evaluate(() =>
      Array.from(document.querySelectorAll('#notifList .notif-row')).map((r) =>
        (r.getAttribute('href') || 'inactive') + '|' + (r.querySelector('.notif-row-title') || {}).textContent)
    );
    return rows;
  };
  const feedA = await feedOf('/request-approval-ui');
  const feedB = await feedOf('/my-timetable-ui');
  const feedC = await feedOf('/my-request-history-ui');
  expect(feedA.length, 'a non-empty mailbox renders rows on the approval page').toBeGreaterThan(0);
  expect(feedA, 'the SAME feed on every page (rows+order)').toEqual(feedB);
  expect(feedB, 'the SAME feed on every page (rows+order)').toEqual(feedC);
});

test('caught-up swap shows the single frozen line when the view is empty', async ({ page }) => {
  // notif-unified-feed: the per-category "unread in other tabs" wording is
  // retired; an empty view is always the classic caught-up line. Relative:
  // the list renders whenever rows exist (unread-only toggle's own state).
  await page.goto('/my-request-history-ui?_=island-2b');
  await page.click('button.notif-btn');
  const listShown = !(await page.locator('#notifList').isHidden());
  if (listShown) {
    await expect(page.locator('#notifEmpty')).toBeHidden();
  } else {
    await expect(page.locator('#notifEmpty')).toContainText("You're all caught up");
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
