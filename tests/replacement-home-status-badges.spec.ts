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
  // 2026-11-06 (user brief): a request its OWNER cancelled is displayed as
  // "Need to Arrange" — 'Cancelled by YOU' is no longer a state of this page.
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

/* "handled" = the row has a live request (the sort-grouping law). NOT the same
   thing as "the countdown is hidden" — since 2026-11-07 only approved/completed
   hide it (a pending row shows the countdown; see receipt 3). */
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
    /* 2026-11-04 (user): the eye is gone — a landed row carries ONE button, the
       jump. The details modal is still reachable by clicking the row itself. */
    await expect(row.locator('.col-action button'), 'a landed row offers exactly one action — the jump').toHaveCount(1);
    await expect(row.locator('.col-action button')).toHaveAttribute('aria-label', 'Go to My Timetable');
    await row.locator('.col-dash, .col-original').first().click();   // the row body, not the button
    await expect(page.locator('#quickViewModal')).toBeVisible();
  } else {
    test.info().annotations.push({ type: 'fixture', description: 'no approved/completed row in this DB — the eye half is not exercised' });
  }
});

test('receipt 3 — the countdown shows while the class date still matters', async ({ page }) => {
  /* 2026-11-07 (user law — working days + countdown placement): the class-date
     countdown is hidden only on a LANDED row (approved / completed). Pending,
     rejected, cancelled and unrequested rows keep it — pending is exactly where
     "how soon" matters. The words are now working-day words. */
  const all = await rows(page);
  const landed = (r: Row): boolean => ['approved', 'completed'].includes(r.statusKey);

  for (const row of all.filter(landed)) {
    expect(row.days, 'a landed row shows no class-date countdown (em dash)').toBe(null);
  }

  const live = all.filter((r) => !landed(r));
  expect(live.length, 'at least one row still carries a live class date').toBeGreaterThan(0);
  for (const row of live) {
    expect(row.days, 'a live row keeps its countdown').toMatch(/Working Days? Left|Working Days? Ago|Today/);
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

/** 2026-11-06 (user brief "Cancelled Pending Requests") — the display law.
 *  A manually cancelled request is still a cancelled RECORD, but this page shows
 *  the occurrence as if nothing had been proposed. Read-only: it asserts the
 *  shape on whatever rows the DB has (the write receipt, with a known request id,
 *  lives in request-cancel-pending.spec.ts).
 */
test('receipt 9 — a cancelled request never reads "Cancelled by YOU" on this page', async ({ page }) => {
  const all = await rows(page);
  const cancelledByYou = all.filter((r) => r.status === 'Cancelled by YOU');
  expect(cancelledByYou, 'the page-wide law: cancelled requests display as Need to Arrange').toEqual([]);

  // the outlined no-request badge carries the no-request tip, not the cancel one
  const outlined = page.locator('#tableBody .col-status .badge.badge-outline.status-none');
  await expect(outlined.first()).toBeVisible();
  await expect(outlined.first()).toHaveAttribute('data-tip', 'No request has been made for this class yet — it still needs arranging.');
});

/** 2026-11-06 (follow-up, user's word "remove that option") — the status filter
 *  no longer offers **Cancelled**: no row on this page carries that state any
 *  more. A filter SAVED before the change still names it, so the restore path
 *  maps that value onto Need to Arrange instead of leaving the select blank.
 */
test('receipt 10 — the status filter drops Cancelled, and a stale saved filter degrades to Need to Arrange', async ({ page }) => {
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });

  const values = await page.$$eval('#statusFilter option', (els) =>
    els.map((e) => (e as HTMLOptionElement).value));
  expect(values, 'the filter offers every state this page can display, and only those').toEqual([
    'all', 'none', 'pending', 'approved', 'rejected', 'completed',
  ]);

  // a filter saved BEFORE the removal still says "cancelled"
  await page.evaluate(() => localStorage.setItem('mrh-filters', JSON.stringify({
    status: 'cancelled', search: '', week: 'all', excludeCompleted: true,
  })));
  await page.goto(`${HOME}?stale=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#tableBody tr');

  await expect(page.locator('#statusFilter'), 'a value the select no longer has must never blank it').toHaveValue('none');

  const badges = await page.$$eval('#tableBody .col-status .badge', (els) =>
    els.map((e) => (e.textContent || '').trim()));
  expect(badges.length, 'the mapped filter still shows the rows those requests became').toBeGreaterThan(0);
  expect(badges.every((t) => t === 'Need to Arrange'), 'only the no-request rows: ' + badges.join(' | ')).toBe(true);
});
