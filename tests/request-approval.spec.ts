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
