import { test, expect, type Page } from '@playwright/test';

/**
 * replacement-merge-one-table (T19, 2026-11-04) — the bucket-B rewrite of the
 * old badges receipts. The page is now ONE table of CLASS OCCURRENCES: the row's
 * state is its newest request, the status badge is NOT a link (M6), and the
 * Action cell is one icon (rotate / ban / eye).
 *
 * Read-only: every receipt is a GET plus a DOM read. The fixture is whatever the
 * target DB has, so the shared extractor reports facts and each receipt asserts
 * the LAW that must hold for that fact — a handled row's laws are only asserted
 * when a handled row exists (annotated, never silently skipped as a pass).
 */

const HOME = '/my-replacements';
const STATUS_LABELS = [
  'Need to Arrange',
  'Pending — Waiting for Approval',
  'Approved',
  'Rejected',
  'Cancelled by YOU',
];

type Row = {
  code: string;
  original: string;
  days: string | null;
  status: string;
  statusKey: string;
  badgeIsAnchor: boolean;
  iconClass: string | null;
  iconOnclick: string | null;
  reason: string;
};

async function rows(page: Page): Promise<Row[]> {
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#tableBody tr');
  await page.waitForTimeout(400);

  return page.evaluate(() => {
    const statusKeyOf = (badge: Element | null): string => {
      if (!badge) return 'none';
      const cls = Array.from(badge.classList).find((c) => c.startsWith('status-'));
      if (!cls || cls === 'status-none') return 'none';
      return cls.replace('status-', '');
    };

    return Array.from(document.querySelectorAll('#tableBody tr')).map((tr) => {
      const badge = tr.querySelector('.col-status .badge');
      const urgency = tr.querySelector('.col-original .urgency-high, .col-original .urgency-mid, .col-original .urgency-low');
      const icon = tr.querySelector('.col-action .btn-icon');

      return {
        code: (tr.querySelector('.col-code')?.textContent || '').trim(),
        original: (tr.querySelector('.col-original')?.textContent || '').replace(/\s+/g, ' ').trim(),
        days: urgency ? (urgency.textContent || '').trim() : null,
        status: (badge?.textContent || '').trim(),
        statusKey: statusKeyOf(badge),
        badgeIsAnchor: badge?.tagName === 'A',
        iconClass: icon ? Array.from(icon.classList).find((c) => c.startsWith('btn-icon--')) ?? null : null,
        iconOnclick: icon ? icon.getAttribute('onclick') : null,
        reason: (tr.querySelector('.col-reason')?.textContent || '').trim(),
      };
    });
  });
}

const isHandled = (r: Row): boolean => ['pending', 'approved', 'completed'].includes(r.statusKey);

test.use({ storageState: '.auth/staff-5425.json' });

test('receipt 1 — every row carries ONE M5 badge, never an anchor, and one icon', async ({ page }) => {
  const all = await rows(page);
  expect(all.length, 'the merged page lists the viewer\'s conflicted occurrences').toBeGreaterThan(0);

  for (const row of all) {
    const valid = STATUS_LABELS.includes(row.status) || /^Completed( — \d+ days? ago)?$/.test(row.status);
    expect(valid, 'M5 label: ' + row.status).toBeTruthy();
    expect(row.badgeIsAnchor, 'M6: the badge is not a door').toBe(false);
    expect(row.iconClass, 'M6: the Action cell is one icon').not.toBeNull();
  }
});

test('receipt 2 — the ban icon opens the confirm overlay; the eye opens the pop-up', async ({ page }) => {
  const all = await rows(page);
  const pending = all.find((r) => r.statusKey === 'pending');
  const handled = all.find((r) => r.statusKey === 'approved' || r.statusKey === 'completed');

  if (pending) {
    const row = page.locator('#tableBody tr:has(.col-status .badge:has-text("Pending"))').first();
    await expect(row.locator('.col-action .btn-icon--danger'), 'a pending row offers cancel').toHaveCount(1);
    await row.locator('.col-action .btn-icon--danger').click();
    await expect(page.locator('#cancelConfirmOverlay')).toBeVisible();
    await page.locator('#cancelConfirmOverlay .btn-outline').first().click(); // No, Keep It
    await expect(page.locator('#cancelConfirmOverlay')).toBeHidden();
  } else {
    test.info().annotations.push({ type: 'fixture', description: 'no pending row in this DB — the ban half is not exercised' });
  }

  if (handled) {
    const row = page.locator('#tableBody tr:has(.col-status .badge:has-text("Approved")), #tableBody tr:has(.col-status .badge:has-text("Completed"))').first();
    await expect(row.locator('.col-action .btn-icon--ghost'), 'an approved/completed row offers the eye').toHaveCount(1);
    await row.locator('.col-action .btn-icon--ghost').click();
    await expect(page.locator('#quickViewModal')).toBeVisible();
  } else {
    test.info().annotations.push({ type: 'fixture', description: 'no approved/completed row in this DB — the eye half is not exercised' });
  }
});

test('receipt 3 — Days Left is an em dash on a handled row and a countdown otherwise', async ({ page }) => {
  const all = await rows(page);

  for (const row of all.filter(isHandled)) {
    expect(row.days, 'D7: a handled row shows no countdown (em dash or no urgency span)').toBe(null);
  }

  const actionable = all.filter((r) => !isHandled(r));
  expect(actionable.length, 'at least one row still needs action').toBeGreaterThan(0);
  for (const row of actionable) {
    expect(row.days, 'D7: an unarranged row keeps its real countdown').toMatch(/days? left|Overdue|today/i);
  }
});

test('receipt 4 — handled rows sort AFTER the rows that need action', async ({ page }) => {
  const flags = (await rows(page)).map(isHandled);
  if (!flags.includes(true)) {
    test.info().annotations.push({ type: 'fixture', description: 'no handled row — the grouping law is asserted on the needs-action prefix only' });
    expect(flags.every((f) => f === false), 'without handled rows every row is needs-action').toBe(true);
    return;
  }

  const firstHandled = flags.indexOf(true);
  expect(flags.slice(firstHandled).every(Boolean), 'D8: once the handled group starts, it never returns to needs-action').toBe(true);
});

test('receipt 5 — the phone card opens the same pop-up', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#cardView .replacement-card');

  const card = page.locator('#cardView .replacement-card').first();
  /* the card builds its own badge (HtmlBuilder.replacementHomeCard), so the
     composed text needs the page's reasonText hook to reach it too. */
  const headerBadges = (await card.locator('.rc-header .badge').allTextContents()).map((t) => t.trim());
  expect(
    headerBadges.some((t) => /^(Public Holiday|Class Cancelled) — /.test(t)),
    'the card badge carries the composed text, never the raw reason: ' + headerBadges.join(' | '),
  ).toBe(true);

  await card.click();
  await expect(page.locator('#quickViewModal')).toBeVisible();
  await expect(page.locator('#quickViewModal .modal-tab').first()).toHaveText(/Original Class Schedule/);
});

test('receipt 6 — a handled row hides the arrange button and the tab names the request status', async ({ page }) => {
  const handled = (await rows(page)).find(isHandled);
  if (!handled) {
    test.info().annotations.push({ type: 'fixture', description: 'no handled row in this DB — receipt 6 needs one' });
    test.skip(true, 'no handled row in this DB: the hidden-arrange receipt cannot be exercised');
    return;
  }

  const row = page.locator('#tableBody tr', { has: page.locator('.col-status .badge:has-text("Pending"), .col-status .badge:has-text("Approved"), .col-status .badge:has-text("Completed")') }).first();
  await row.locator('.col-code').click();

  const tabs = page.locator('#quickViewModal .modal-tab');
  await expect(tabs.first()).toHaveText(/Original Class Schedule/);
  await expect(tabs).toContainText([/Request Status \(/]);
  await expect(page.locator('#qvArrangeBtn'), 'M7: a handled row offers no arrange button').toBeHidden();
});

test('receipt 7 — the rotate icon arranges that class and week', async ({ page }) => {
  const all = await rows(page);
  const actionable = all.find((r) => !isHandled(r));
  expect(actionable, 'a needs-action row exists').toBeTruthy();

  const rotate = page.locator('#tableBody .col-action .btn-icon--primary').first();
  await expect(rotate).toHaveCount(1);
  const onclick = await rotate.getAttribute('onclick');
  expect(onclick, 'S6.6: it hands off (sessionId, week) FROM this page').toMatch(/goToReplacementFor\(\d+,\s*\d+,\s*'replacement-home'\)/);
  await expect(rotate).toHaveAttribute('data-tip', 'Arrange a replacement for this class');
});

test('receipt 8 — the Conflict Reason badge says which kind of conflict, not just its colour', async ({ page }) => {
  const all = await rows(page);
  expect(all.length, 'the merged page lists conflicted occurrences').toBeGreaterThan(0);

  for (const row of all) {
    expect(row.reason, 'every row carries a reason badge').not.toBe('');
    const named =
      row.reason.startsWith('Public Holiday — ') || row.reason.startsWith('Class Cancelled — ');
    expect(named, 'explore-brief row 4: the badge names the kind — ' + row.reason).toBeTruthy();
  }

  const holiday = all.find((r) => r.reason.startsWith('Public Holiday — '));
  if (holiday) {
    expect(holiday.reason, 'a holiday badge names the day (or the prototype literal fallback)')
      .toMatch(/^Public Holiday — \S/);
  } else {
    test.info().annotations.push({ type: 'fixture', description: 'no public-holiday row in this DB — the day-name half is not exercised' });
  }

  const cancelled = all.find((r) => r.reason.startsWith('Class Cancelled — '));
  if (!cancelled) {
    test.info().annotations.push({ type: 'fixture', description: 'no cancelled-class row in this DB — the grey half is not exercised' });
  }

  // the pop-up's facts strip prints the SAME badge, never a second vocabulary
  const row = page.locator('#tableBody tr').first();
  const shown = ((await row.locator('.col-reason').textContent()) || '').trim();
  await row.locator('.col-code').click();
  await expect(page.locator('#quickViewModal')).toBeVisible();
  await expect(page.locator('#quickViewModal .detail-facts')).toContainText(shown);
});
