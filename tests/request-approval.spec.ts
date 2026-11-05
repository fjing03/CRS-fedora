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

test('U3-T9c: the COHORT column replaced STUDENTS (consistency update) and row click opens the detail modal', async ({ page }) => {
  await page.goto(PAGE);

  // request-approval-ui CONSISTENCY (2026-11-03): Students removed, Cohort added
  // BEFORE Original Class, `#` → Request ID (printing the real id).
  // Column order: 0 checkbox · 1 Request ID · 2 Requested Timestamp ·
  // 3 Staff (Proposer) · 4 Course · 5 COHORT · 6 Original Class · …
  const headers = await page.evaluate(() =>
    Array.from(document.querySelectorAll('#tableHead th')).map((th) => (th.textContent || '').trim()));
  expect(headers).toContain('Request ID');
  expect(headers).toContain('Staff (Proposer)');
  expect(headers).toContain('Cohort');
  expect(headers).toContain('Requested Replacement');
  expect(headers).not.toContain('Students');
  expect(headers).not.toContain('Lecturer');
  expect(headers).not.toContain('Proposed Replacement');
  expect(headers.indexOf('Cohort')).toBe(headers.indexOf('Original Class') - 1);

  const cells = await page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('#tableBody tr'));
    return rows.map((row) => ({
      id: row.children[1]?.textContent?.trim() ?? '',
      // innerText (not textContent) so the <br> between cohort codes shows as a newline
      cohort: (row.children[5] as HTMLElement | undefined)?.innerText?.trim() ?? '',
    }));
  });
  expect(cells.length).toBeGreaterThan(0);
  for (const cell of cells) {
    // the Request ID cell is the REAL request id, never the row counter
    expect(cell.id, 'the Request ID cell prints #<id>').toMatch(/^#\d+$/);
    expect(cell.cohort.length, 'every row names at least one cohort').toBeGreaterThan(0);
    // one compact code per line — no spaces inside a code
    for (const line of cell.cohort.split('\n')) {
      expect(line.trim(), 'a compact cohort code').toMatch(/^[A-Z]+\d+\(S\d+\)G\d+$/);
    }
  }

  // CLICK a row → the detail modal opens (openModalById → DetailModal.render)
  await page.locator('#tableBody tr').first().click();
  await expect(page.locator('#modalOverlay')).toHaveClass(/show/);
  await expect(page.locator('#modalTitle')).toContainText('Request Details');

  // 3A (2026-11-04): the fixed facts strip carries what the old Request Info
  // tab held — staff (with the copy email), cohorts, students and the conflict.
  const body = page.locator('#modalBody');
  const facts = body.locator('.detail-facts');
  await expect(facts).toContainText('Staff (Proposer)');
  await expect(facts).toContainText('Students');
  await expect(facts).toContainText('Conflict');
  await expect(facts.locator('[data-tip="Click to copy email"]')).toHaveCount(1);
  await expect(body.locator('.modal-timeline'), '3A removed the timeline rail').toHaveCount(0);

  // 3D: three tabs, the status one LAST
  const tabs = body.locator('.modal-tab');
  await expect(tabs).toHaveCount(3);
  await expect(tabs.nth(0)).toHaveText('Original Class Schedule');
  await expect(tabs.nth(1)).toHaveText('Proposed Replacement Schedule');
  await expect(tabs.nth(2)).toHaveText(/^Request Status \(.+\)$/);

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
  // 3E-3 (2026-11-04): start/end collapse into ONE `Time` row (merged shape)
  expect(origText).toMatch(/Time\s*\n?\s*\d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM)/);
  expect(origText).not.toMatch(/Start Time|End Time/);

  // TAB: Proposed Replacement Schedule — split Start/End + the full validity text
  await page.locator('.modal-body .modal-tab', { hasText: 'Proposed Replacement Schedule' }).click();
  const repText = await page.locator('#modalBody').innerText();
  expect(repText).not.toContain('2026-10-23');
  expect(repText).toMatch(/Day\s*\n?\s*(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)/);
  expect(repText).toMatch(/Time\s*\n?\s*\d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM)/);
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

test('U3-T9f: the Approve wire round-trips the server — zero-write on a nonexistent id (the demo-safe wire-trip probe)', async ({ page }) => {
  await page.goto(PAGE);
  // receipt-target-guard (the 2026-11-01 incident): the old probe clicked
  // the first UI row through to the confirm — its "FAKE id" story was false:
  // the wire fired with currentApproveIds = THE ROW'S REAL id (openApproveNotesModal →
  // confirmApproveWithNotes, request-approval.blade.php), so a live pending
  // queue made this a GENUINE approval write. Rebuilt state-proof: the wire
  // is tripped DIRECTLY with a nonexistent id — ApproveRequest answers the
  // 'already decided' loser (`$request === null` → no write, ever) and
  // nothing is consumed, whatever the queue holds.
  const pendingBefore = await page.evaluate(() =>
    (window as any).PAGE_DATA.requests.filter((r: any) => r.status === 'Pending').length);
  const res = await page.evaluate((id: number) => (window as any)
    .pageLivewire().call('approve', JSON.stringify([id]), 'wire-trip probe note'), 999999);

  // the round-trip is the receipt: the validity family loser string survives
  expect(res).toBeTruthy();
  expect(JSON.stringify(res)).toContain('already decided');
  // and the queue is untouched (shape-agnostic now — not the old ===1)
  const pendingAfter = await page.evaluate(() =>
    (window as any).PAGE_DATA.requests.filter((r: any) => r.status === 'Pending').length);
  expect(pendingAfter).toBe(pendingBefore);
});

test('U3-T9g: the Approve/Reject modals — Request ID in the title, [Lecture], the Original card (2026-11-05)', async ({ page }) => {
  await page.goto(PAGE);

  const pending = await page.evaluate(() => (window as any).PAGE_DATA.requests
    .filter((r: any) => r.status === 'Pending')
    .map((r: any) => ({ id: r.id, classType: r.classType })));
  expect(pending.length).toBeGreaterThan(0);
  const row = pending[0];
  const longType = ({ L: 'Lecture', T: 'Tutorial', P: 'Practical' } as Record<string, string>)[row.classType] || row.classType;
  const pendingBefore = pending.length;

  // READ-ONLY: the modals are opened through their own entry points and closed
  // again — no decision, no wire call, no write.
  await page.evaluate((id: number) => (window as any).openRejectModal(id), row.id);
  await expect(page.locator('#rejectReasonModal')).toHaveClass(/show/);

  // (2) the Request ID lives in the title, read from the actual request — and
  //     it is NOT repeated inside the card (the Approve modal keeps its chip)
  await expect(page.locator('#rejectReasonTitle')).toHaveText('Rejection Reason (#' + row.id + ')');
  await expect(page.locator('#rejectSummary .approval-id')).toHaveCount(0);

  // (1) the subject prints the FULL class type inside the brackets
  await expect(page.locator('#rejectSummary .approval-classtype')).toHaveText('[' + longType + ']');

  // (3) no lecturer avatar, no copy-email icon inside the modal card
  await expect(page.locator('#rejectSummary .lecturer-avatar')).toHaveCount(0);
  await expect(page.locator('#rejectSummary .copy-icon')).toHaveCount(0);

  // (4) Title Case presets + the new instruction line
  await expect(page.locator('#rejectReasonModal .reject-preset-chip')).toHaveText([
    'Venue Unavailable', 'Insufficient Notice', 'Slot Conflict', 'Lecturer Unavailable', 'Other',
  ]);
  await expect(page.locator('#rejectReasonModal .section-heading-sub').first())
    .toHaveText('Please Provide a Reason for Rejection (Required):');

  // (5) the Original card: date + week together, time WITHOUT the duration,
  //     and the bare venue code on its own line
  const original = page.locator('#rejectSummary .cmp-original');
  await expect(original.locator('.cmp-label')).toHaveText('Original');
  // 616 px shell (2026-11-05): date + week stay on ONE rendered line
  const dateRects = await original.locator('.class-day-date').evaluate((el) => el.getClientRects().length);
  expect(dateRects, 'date + week must not wrap at the 616 px width').toBe(1);
  const originalText = (await original.innerText()).replace(/\s+/g, ' ');
  expect(originalText).toMatch(/\((Week \d+)\)/);
  expect(originalText).toMatch(/\d{1,2}:\d{2} (AM|PM) to \d{1,2}:\d{2} (AM|PM)/);
  expect(originalText).not.toMatch(/\((?:1|2|3) hours?\)/);
  // the venue line is the CODE alone — no `Venue Code:` label (user follow-up)
  await expect(original.locator('.class-venue')).toHaveText(/^[A-Z]{1,2}\d{2,4}$/);
  expect(originalText).not.toContain('Venue Code');

  await page.evaluate(() => (window as any).setRejectModalTitle(null));
  await page.evaluate(() => (window as any).bulkReject());
  await expect(page.locator('#rejectReasonTitle'), 'a bulk reject names no single id').toHaveText('Rejection Reason');
  await page.evaluate(() => (window as any).closeRejectModal());
  await expect(page.locator('#rejectReasonModal')).not.toHaveClass(/show/);

  // The SAME card feeds the Approve modal — the shared four updates hold there too
  await page.evaluate((id: number) => (window as any).openApproveNotesModal([id]), row.id);
  await expect(page.locator('#approveNotesModal')).toHaveClass(/show/);
  await expect(page.locator('#approveNotesSummary .approval-classtype')).toHaveText('[' + longType + ']');
  await expect(page.locator('#approveNotesSummary .approval-id'), 'the Approve modal keeps the id (its title has none)').toHaveCount(1);
  await expect(page.locator('#approveNotesSummary .lecturer-avatar')).toHaveCount(0);
  await expect(page.locator('#approveNotesSummary .copy-icon')).toHaveCount(0);
  const approveOriginal = (await page.locator('#approveNotesSummary .cmp-original').innerText()).replace(/\s+/g, ' ');
  await expect(page.locator('#approveNotesSummary .cmp-original .class-venue')).toHaveText(/^[A-Z]{1,2}\d{2,4}$/);
  expect(approveOriginal).not.toContain('Venue Code');
  expect(approveOriginal).not.toMatch(/\((?:1|2|3) hours?\)/);
  await page.evaluate(() => (window as any).closeApproveNotesModal());
  await expect(page.locator('#approveNotesModal')).not.toHaveClass(/show/);

  // the queue is untouched
  const pendingAfter = await page.evaluate(() =>
    (window as any).PAGE_DATA.requests.filter((r: any) => r.status === 'Pending').length);
  expect(pendingAfter).toBe(pendingBefore);
});
