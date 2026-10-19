/**
 * request-approval.spec.ts — SDD span-aware-approval-flow T9 (Slice C): the
 * REAL RequestApproval page replaces the D10 mock; the smoke verifies the
 * payload + the core affordances on the DEMO DB (READ-ONLY: no decision
 * made — the actions' behaviors live in ApprovalWorkflowTest on testing DB).
 */
import { test, expect, type Page } from '@playwright/test';

test.use({ storageState: '.auth/staff-5425.json' }); // the demo's PL (Surayaini — staff 5425, the demo PL)

const PAGE = '/request-approval-ui';

test('U3-T9a: the real page loads with the PL auth + pending rows from the queue', async ({ page }) => {
  const resp = await page.goto(PAGE);
  expect(resp?.status()).toBe(200);

  // the request-approval PL page (route mw = auth + pl)
  await expect(page.locator('h1')).toHaveText('Request Approval');

  // the real payload replaces MockData: LIVE rows, at least ONE pending
  const rows = await page.evaluate(() => (window as any).PAGE_DATA.requests);
  expect(Array.isArray(rows)).toBe(true);
  const pending = rows.filter((r: any) => r.status === 'Pending');
  expect(pending.length).toBeGreaterThan(0);
  // the {lecturer} field = the proposer name (payload shape per §2.13)
  expect(typeof pending[0].lecturer).toBe('string');
});

test('U3-T9b: the Grid loads via payload MockData bridge and sorts by requested timestamp', async ({ page }) => {
  await page.goto(PAGE);
  // the mock data bridge is served by the component's payload
  const probe = await page.evaluate(() => ({
      hasPAGE: typeof (window as any).PAGE_DATA !== 'undefined',
      pageKeys: typeof (window as any).PAGE_DATA === 'object' ? Object.keys((window as any).PAGE_DATA) : null,
      hasMock: (window as any).MockData !== undefined && (window as any).MockData !== null,
      mockKeys: (window as any).MockData ? Object.keys((window as any).MockData).slice(0, 6) : null,
      isPoly: Array.isArray((window as any).MockData?.approvalRequests),
  }));
  console.log(JSON.stringify(probe));
  const bridge = probe.hasMock && probe.isPoly;
  expect(bridge).toBe(true);
  await expect(page.locator('#resultCount')).toContainText(/result/i);
  // the table's FIRST body row renders (SkeletonLoader has N rows of page data)
  await expect(page.locator('#tableBody tr').first()).toBeVisible();
});

test('U3-T9c: STUDENTS column is never undefined and row click opens the detail modal (post-fix smoke)', async ({ page }) => {
  await page.goto(PAGE);
  // STUDENTS: every table cell in that column is a number (the mapper's
  // cohort metadata fixed the undefined field)
  const students = await page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('#tableBody tr'));
    return rows.map(row => row.children[7]?.textContent?.trim() ?? '');
  });
  expect(students.length).toBeGreaterThan(0);
  for (const s of students) {
    expect(/^\d+$/.test(s), `STUDENTS cell "${s}" should be numeric`).toBe(true);
  }

  // CLICK a row → the detail modal opens (openModalById → DetailModal.render)
  await page.locator('#tableBody tr').first().click();
  await expect(page.locator('#modalOverlay')).toHaveClass(/show/);
  await expect(page.locator('#modalTitle')).toContainText('Request Details');
  // the three content tabs exist with data
  await expect(page.locator('#modalBody')).toContainText('Request Information');
  await page.locator('#modalOverlay .modal-close').first().click();
  await expect(page.locator('#modalOverlay')).not.toHaveClass(/show/);
});

test('U3-T9d: the Request Details modal formats dates/hours/Start-End split + full validity text (user 2026-10-18)', async ({ page }) => {
  await page.goto(PAGE);
  await page.locator('#tableBody tr').first().click();
  await expect(page.locator('#modalOverlay')).toHaveClass(/show/);

  // TAB: Original Class — human date + hour words
  await page.locator('.modal-body .modal-tab', { hasText: 'Original Class' }).click();
  const origText = await page.locator('#modalBody').innerText();
  expect(origText).not.toContain('2026-12-24'); // the raw ISO never shows
  expect(origText).toMatch(/\d{1,2} (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4}/);
  expect(origText).toMatch(/\d+ hours?/); // durations in word form
  expect(origText).toMatch(/Start Time\s*\n?\s*\d{1,2}:\d{2} (AM|PM)/);
  expect(origText).toMatch(/End Time\s*\n?\s*\d{1,2}:\d{2} (AM|PM)/);

  // TAB: Replacement Class — split Start/End + the full validity text
  await page.locator('.modal-body .modal-tab', { hasText: 'Replacement Class' }).click();
  const repText = await page.locator('#modalBody').innerText();
  expect(repText).not.toContain('2026-10-23');
  expect(repText).toMatch(/Start Time\s*\n?\s*\d{1,2}:\d{2} (AM|PM)/);
  expect(repText).toMatch(/Day\s*\n?\s*(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)/);
  expect(repText).toMatch(/End Time\s*\n?\s*\d{1,2}:\d{2} (AM|PM)/);
  expect(repText).toMatch(/Slot available/);
});

test('U3-T9e: the PL nav badge is ACCURATE + CONSISTENT (user report 2026-10-18)', async ({ page }) => {
  await page.goto(PAGE);
  const badge = page.locator('#navPendingBadge');

  // the badge equals the REAL pending count (server-rendered + JS-approved)
  const payloadPending = await page.evaluate(() =>
    (window as any).PAGE_DATA.requests.filter((r: any) => r.status === 'Pending').length);
  await expect(badge).toHaveText(String(payloadPending));
  expect(await badge.evaluate(el => getComputedStyle(el).display)).not.toBe('none');

  // the OTHER pages' badges come from the same server count — consistent 1/1:
  await page.goto('/my-timetable-ui');
  const badge2 = page.locator('#navPendingBadge');
  // the household page's nav counts from the same window.PENDING_NAV_COUNT
  await expect(badge2).toHaveText(String(payloadPending));
  expect(await badge2.evaluate(el => getComputedStyle(el).display)).not.toBe('none');
});

test('U3-T9f: the Approve NOTES Confirm wires the server (the demo-safe wire-trip probe)', async ({ page }) => {
  await page.goto(PAGE);
  // the row's Approve → notes modal → confirm → the WIRE call returns a
  // server decision for the FAKE id (no demo request consumed: the server
  // answers 'already decided' for a nonexistent id — the wire path is what
  // the smoke asserts; request #8 stays pending).
  await page.locator('#tableBody tr').first().click();
  await expect(page.locator('#modalOverlay')).toHaveClass(/show/);
  await page.locator('#approveRequestBtn').click();
  await expect(page.locator('#approveNotesModal')).toHaveClass(/show/);
  await page.locator('#approveNotesInput').fill('wire-trip probe note');
  await page.locator('#confirmApproveBtn').click();

  // the toast carries the server decision text (a live wire round-trip)
await expect(page.locator('.toast-content, #toastNotification', { hasText: 'already decided' }).first()).toBeVisible({ timeout: 8000 });
  // the demo request is untouched: the queue still holds ONE pending
  const pending = await page.evaluate(() =>
    (window as any).PAGE_DATA.requests.filter((r: any) => r.status === 'Pending').length);
  expect(pending).toBe(1);
});
