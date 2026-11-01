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

/* SDD notif-new-request-ux (T8): the F-2 test NAME reworded per D5 — the
   badge = the ACTIONABLE unread (the Batch-2 D5 requires the D5-consistent
   wording so a future re-verify reads it right). */
test('bell + pill carry the actionable-unread total (F-2, notif-new-request-ux D5), no mock rows', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await page.goto('/my-timetable-ui?_=island-1');
  await expect(page.locator('.notif-btn')).toBeVisible();

  // the bells = the whole mailbox's ACTIONABLE unread (the D15-inactive rows
  // excluded — the notif-new-request-ux D5; the badge == the panel pill)
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

/* SDD notif-new-request-ux (T8), re-anchored 2026-11-01 — the demo DB was
   rebuilt+swapped per the user's recovery ruling (backups README, commits
   67606dd+d32ebd7) AND it is LIVE (requests get decided while the receipts
   run). So the receipt is STRUCTURAL, not id-pinned: it picks whatever
   unread pending-submitted row the mailbox currently holds and asserts the
   composer's invariants against THAT row's own values. READ-ONLY. */
test('composer receipts on an unread submitted row (structural)', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await page.goto('/request-approval-ui?bust=island-8');
  await page.click('button.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);
  const row = page.locator('#notifList a.notif-row')
    .filter({ hasText: 'New replacement request (ID #' }).first();
  await expect(row).toBeVisible();

  // 1) the title-with-ID (the ID = the request id per the composer law)
  const title = await row.locator('.notif-row-title').textContent();
  expect(title).toMatch(/^New replacement request \(ID #\d+\)$/);
  // 2) the requester subtitle (the full name + the staff id)
  const subtitle = await row.locator('.notif-row-subtitle').textContent();
  expect(subtitle).toMatch(/^.+ \(\d{4}\)$/);
  // 3) the desc: week · day · time (24 h, en dash) · venue
  expect(await row.locator('.notif-row-desc').textContent())
    .toMatch(/^Week \d+ · .+ · \d{2}:\d{2}–\d{2}:\d{2} · \S+$/);
  // 4) the multiline tip: the 5 fact lines + the dynamic Requested-at line 2
  const tip = await row.getAttribute('data-tip');
  expect(tip).toContain('Staff: ');
  expect(tip).toMatch(/Requested at: \d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} (AM|PM) \(/);
  expect(tip).toMatch(/Proposed Schedule: Week \d+ · \d{1,2} [A-Z][a-z]{2} \d{4} · \d{2}:\d{2}–\d{2}:\d{2}/);
  expect(tip).toMatch(/Proposed Venue: \S+/);
  expect(tip).toMatch(/Subject: [A-Z]{3,5}\d{3,4} \((?:P|L|T)\) — /);

  expect(consoleErrors, 'no JS page errors').toEqual([]);
});

/* The 2026-11-01 UI request (SDD-waived): the Active-only toggle + the type
   dropdown in the filter bar. READ-ONLY receipt on the demo mailbox: the
   controls render, the wire:model filters re-render, and the BADGE/PILL stay
   the filter-INDEPENDENT actionable-unread total (the D5/F-2 law). */
test('filter bar: Active-only toggle + the type dropdown, badge unmoved', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await page.goto('/request-approval-ui?bust=island-9');
  const badge = await badgeNumber(page);
  await page.click('button.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);

  // the controls are in the bar — the toggle input is opacity-0 by design
  // (the house .toggle-wrapper law), so visibility asserts ride the WRAPPER
  const activeToggle = page.locator('.notif-filters .toggle-wrapper').nth(1);
  await expect(activeToggle).toBeVisible();
  await expect(activeToggle).toContainText('Active only');
  await expect(page.locator('#notifTypeFilter')).toBeVisible();

  // type dropdown → only the stored type survives; here: submitted
  await page.selectOption('#notifTypeFilter', 'submitted');
  await page.waitForTimeout(700);
  const submittedRows = await page.locator('#notifList .notif-row').count();
  if (submittedRows > 0) {
    await expect(page.locator('#notifList .notif-row-title').first()).toContainText('New replacement request');
  }

  // a type with no rows → the caught-up arm
  await page.selectOption('#notifTypeFilter', 'rejected');
  await page.waitForTimeout(700);
  await expect(page.locator('#notifEmpty')).toBeVisible();

  // Active-only ON with a supporting type: the demo's one request is PENDING
  // (active) → the row comes back while the filter combo is set
  await page.selectOption('#notifTypeFilter', 'submitted');
  await page.locator('.notif-filters .toggle-wrapper').nth(1).click(); // the Active-only toggle
  await page.waitForTimeout(700);
  if (submittedRows > 0) {
    await expect(page.locator('#notifList .notif-row').first()).toBeVisible();
  }

  // the badge + pill NEVER moved — the D5/F-2 law (the filters are view-only)
  expect(await badgeNumber(page)).toBe(badge);
  if (badge > 0) {
    expect(Number(await page.locator('#notifUnreadPill').textContent())).toBe(badge);
  }
  expect(consoleErrors, 'no JS page errors').toEqual([]);
});
