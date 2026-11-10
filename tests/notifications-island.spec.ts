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
  // notif-inactive-hide-sender (2026-11-05, spec R5): the pill is ALWAYS there —
  // a caught-up mailbox reads `0` instead of losing the header's anatomy.
  await expect(pill).toBeVisible();
  expect(Number(await pill.textContent())).toBe(badge);
  expect(consoleErrors, 'no JS page errors').toEqual([]);
});

test('ONE-FEED: the same notification list renders on every page', async ({ page }) => {
  // SDD notif-unified-feed (2026-10-29 — supersedes the AD-2 page-category
  // tabs): the panel shows the recipient's whole mailbox identically on
  // every page. Relative receipt: the row set (href/identity + title) must
  // be EQUAL across the approval / my-timetable / request-history pages —
  // the 2026-11-01 USER CLEANOUT (both demo DBs pristine-blank since
  // 22:25) makes an EMPTY-case receipt the normal one: all three pages
  // agree on the caught-up arm until real submits refill the canvas.
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
  if (feedA.length === 0) {
    // the blank-canvas arm: no rows anywhere + the caught-up line on all pages
    expect(feedB.length, 'no rows on my-timetable either').toBe(0);
    expect(feedC.length, 'no rows on my-request-history either').toBe(0);
    await expect(page.locator('#notifEmpty')).toContainText("You're all caught up");
    return;
  }
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
    .filter({ hasText: 'New Replacement Request (ID #' }).first();
  // the 2026-11-01 USER CLEANOUT: the canvas may legally be BLANK — the
  // empty-case receipt asserts the caught-up arm + the composed controls,
  // and the composition invariants re-assert themselves once real submits
  // (the Feature suite's 14/14 covers them server-side meanwhile)
  const composedCount = await row.count();
  if (composedCount === 0) {
    await expect(page.locator('#notifEmpty')).toContainText("You're all caught up");
    await expect(page.locator('#notifTypeFilter')).toBeVisible();
    expect(consoleErrors, 'no JS page errors').toEqual([]);
    return;
  }
  await expect(row).toBeVisible();

  // 1) the title-with-ID (the ID = the request id per the composer law)
  const title = await row.locator('.notif-row-title').textContent();
  expect(title).toMatch(/^New Replacement Request \(ID #\d+\)$/);
  // 2) the requester subtitle (the full name + the staff id)
  const subtitle = await row.locator('.notif-row-subtitle').textContent();
  expect(subtitle).toMatch(/^.+ \(\d{4}\)$/);
  // 3) the desc: week · day · time (24 h, en dash) · venue
  expect(await row.locator('.notif-row-desc').textContent())
    .toMatch(/^Week \d+ · .+ · \d{2}:\d{2}–\d{2}:\d{2} · \S+$/);
  // 4) the multiline tip — 2026-11-06 ("Organise Notification Row Hover
  //    Tooltips"): ONE vocabulary, the user's formats. Order:
  //    Staff (Proposer) / Requested At / Subject & Cohort /
  //    Original Slot Schedule (+reason in parentheses) /
  //    Requested Slot Schedule / Request ID.
  const tip = await row.getAttribute('data-tip');
  expect(tip).toContain('Staff (Proposer): ');
  expect(tip).toMatch(/\(\d{4,}\)$/m);
  expect(tip).toMatch(/Requested At: \d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} (AM|PM) \(/);
  expect(tip).toMatch(/Subject & Cohort: [A-Z]{3,5}\d{3,4} \([A-Z][a-z]+\) — .+( · \S+)?$/m);
  /* `Mon, 9 Nov 2026` + `11:00 AM ~ 1:00 PM` (tilde, one space each side) + `(reason)` */
  expect(tip).toMatch(/Original Slot Schedule: Week \d+ · [A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} · \d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM)( @ \S+)?( \(.+\))?$/m);
  expect(tip).toMatch(/Requested Slot Schedule: Week \d+ · [A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} · \d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM)( @ \S+)?$/m);
  expect(tip).toMatch(/Request ID: #\d+$/m);
  expect(tip).not.toContain('Conflict Reason:', 'the reason now rides the Original line');
  expect(tip).not.toContain('Proposed Venue: ');

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
    await expect(page.locator('#notifList .notif-row-title').first()).toContainText('New Replacement Request');
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

/* notif-manage-hide (2026-11-02, the user's approved design) — the READ-ONLY
   layout half of the lane. The WRITE flow (hide/undo/shelf) lives in
   tests/notifications-hide-manage.spec.ts on the disposable testing DB: the
   demo mailbox is read-only for agents, so nothing here clicks Hide. */
test('manage-hide layout: icon-only Mark all, gated Manage, full-row type select', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await page.goto('/request-approval-ui?bust=island-10');
  await page.click('button.notif-btn');
  await expect(page.locator('#notifPanel')).toHaveClass(/open/);

  // 1) the type select spans the whole filter row (its own line under the toggles)
  const geom = await page.evaluate(() => {
    const sel = document.querySelector('#notifTypeFilter') as HTMLElement;
    const row = document.querySelector('.notif-filters') as HTMLElement;
    const cs = getComputedStyle(row);
    const content = row.getBoundingClientRect().width
      - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    return {
      selW: Math.round(sel.getBoundingClientRect().width),
      contentW: Math.round(content),
      selH: Math.round(sel.getBoundingClientRect().height),
      title: sel.getAttribute('title'),
      tip: sel.getAttribute('data-tip'),
    };
  });
  expect(Math.abs(geom.selW - geom.contentW)).toBeLessThanOrEqual(1);
  expect(geom.selH).toBe(36);                     // the shipped compact skin
  expect(geom.title).toBeNull();                  // one tooltip system only
  expect(geom.tip).toContain('Filter the list by notification type');

  // 2) the head never overflows, at the panel's natural width
  const head = await page.evaluate(() => {
    const h = document.querySelector('.notif-head') as HTMLElement;
    return { overflow: h.scrollWidth > h.clientWidth + 1 };
  });
  expect(head.overflow).toBe(false);

  // 3) Mark all is an ICON-ONLY 30px square when shown (the old text-button
  //    sizing squeezed the glyph to ~10px — the rewrite's whole point)
  const markAll = page.locator('#notifMarkAll');
  if (await markAll.isVisible()) {
    const box = await markAll.evaluate((el) => {
      const svg = el.querySelector('svg')!.getBoundingClientRect();
      const me = el.getBoundingClientRect();
      return { w: Math.round(me.width), h: Math.round(me.height), svgW: Math.round(svg.width) };
    });
    expect(box).toEqual({ w: 30, h: 30, svgW: 16 });
  }

  // 4) the Manage affordance appears exactly when something is hideable.
  //    The VIEW filters can hide hideable rows (unread-only drops the read
  //    ones), so the count is taken with the filter OFF:
  //    hideable ⇔ read_at !== null || inactive_reason !== null ⇔ the rendered
  //    --read / --inactive classes. Both counts must then agree.
  await page.locator('.notif-filters .toggle-wrapper').first().click();
  await page.waitForTimeout(800);
  const hideableCount = await page.evaluate(() =>
    document.querySelectorAll('#notifList .notif-row--read, #notifList .notif-row--inactive').length);
  expect(await page.locator('#notifManage').count()).toBe(hideableCount > 0 ? 1 : 0);

  // 5) the tooltips ride the SHARED system, multiline where the copy is
  if (await page.locator('#notifManage').count()) {
    await page.locator('#notifManage').hover();
    await page.waitForTimeout(400);
    const tip = page.locator('.data-tip-tooltip');
    await expect(tip).toContainText('Put away notifications you have already read.');
    await expect(tip).toContainText('Nothing is deleted — you can bring them back.');
    await page.mouse.move(2, 2);
  }

  expect(consoleErrors, 'no JS page errors').toEqual([]);
});
