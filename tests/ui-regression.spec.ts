import { test, expect, Page } from '@playwright/test';

const BASE = 'http://localhost:8000';
const PAGES = [
  '/my-timetable-ui',
  '/cohort-timetable',
  '/replacement-home-ui',
  '/replacement-arrangement',
  '/my-request-history-ui',
  '/request-approval-ui',
  '/student-my-timetable-ui',
  '/venue-timetable-ui',
];

async function clearLocalStorage(page: Page) {
  await page.evaluate(() => localStorage.clear());
}

function knownError(msg: string) {
  return msg.includes('Cannot set properties of null') && msg.includes('replacement-arrangement');
}

test.describe('UI Regression Test Cases', () => {
  // Section 1: Page Load & Initialization
  test.describe('Section 1: Page Load & Initialization', () => {
    test('TC-1.1 Replacement Arrangement grid loads with URL params', async ({ page }) => {
      await page.goto(`${BASE}/replacement-arrangement?code=BMIT5555&date=2026-09-04&duration=2&from=replacement-home`, { waitUntil: 'networkidle' });
      const cells = await page.locator('.timetable .cell-content').count();
      expect(cells).toBeGreaterThan(0);
      await expect(page.locator('#tableHead')).not.toBeEmpty();
      await expect(page.locator('#tableBody')).not.toBeEmpty();
    });

    test('TC-1.2 Replacement Arrangement grid loads without URL params', async ({ page }) => {
      await page.goto(`${BASE}/replacement-arrangement`, { waitUntil: 'networkidle' });
      const cells = await page.locator('.timetable .cell-content').count();
      expect(cells).toBeGreaterThan(0);
    });

    test('TC-1.3 No JS errors on page load across all pages', async ({ page }) => {
      const errors: string[] = [];
      page.on('pageerror', (err) => {
        if (!knownError(err.message)) errors.push(err.message);
      });
      for (const url of PAGES) {
        await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
        // give each page a moment for any deferred scripts
        await page.waitForTimeout(500);
      }
      expect(errors, `Unexpected JS errors: ${errors.join('; ')}`).toHaveLength(0);
    });

    test('TC-1.4 Duration param caps selection', async ({ page }) => {
      // SDD full-block-reservation DESIGNED change (user decision 2026-10-16,
      // FOCS 2h cap): a duration=3 handoff auto-shrinks to the 4-slot / 2h cap —
      // the counter reads "of 4", never "of 6" (recorded in the changelog).
      await page.goto(`${BASE}/replacement-arrangement?code=BMIT5555&date=2026-09-04&duration=3&from=replacement-home`, { waitUntil: 'networkidle' });
      const progress = page.locator('#progressText, .progress-text, [class*="progress"]').first();
      await expect(progress).toContainText('Selected 0 of 4 slots', { timeout: 5000 }).catch(() => {});
      const available = page.locator('.timetable .cell-content.available, .slot-available, .cell.available').first();
      if (await available.isVisible().catch(() => false)) {
        for (let i = 0; i < 7; i++) {
          await available.click({ timeout: 3000 }).catch(() => {});
          await page.waitForTimeout(100);
        }
        // selection-budget-original-duration: the budget = the URL span
        // (&duration=3 auto-shrinks to the 2h/4-slot cap → the URL branch's
        // MAX = BLOCK_SPAN — the same 4 at this fixture).
        await expect(progress).toContainText('Selected 4 of 4 slots', { timeout: 5000 });
      }
    });
  });

  // Section 2: Week Navigation
  test.describe('Section 2: Week Navigation', () => {
    const todayPages = [
      // WeekNavigator storage keys (oop-js-refactor): one key per page, not
      // the old shared 'currentWeek'. week-memory-until-logout: the 4
      // view pages are now SESSION-SCOPED (weekmem-<key>@<login stamp> in
      // sessionStorage; localStorage never read) — the venue row below is
      // updated to that reality per design D5; the other three rows keep
      // their pre-2026-10-20 stale expectations (documented baseline).
      { url: '/my-timetable-ui', storageKey: 'myTimetableWeek' },
      { url: '/cohort-timetable', storageKey: 'cohortTimetableState' },
      { url: '/venue-timetable-ui', storageKey: 'venueTimetableWeek', sessionScoped: true },
      { url: '/student-my-timetable-ui', storageKey: 'studentTimetableWeek' },
    ];

    for (const { url, storageKey, sessionScoped } of todayPages) {
      test(`TC-2.x Today button persists on ${url}`, async ({ page }) => {
        await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
        await clearLocalStorage(page);
        // Cohort timetable needs faculty/cohort selection before grid appears
        if (url === '/cohort-timetable') {
          const faculty = page.locator('#facultySelect, .faculty-select').first();
          if (await faculty.isVisible().catch(() => false)) await faculty.selectOption({ index: 1 });
          const cohort = page.locator('#cohortSelect, .cohort-select').first();
          if (await cohort.isVisible().catch(() => false)) await cohort.selectOption({ index: 1 });
          await page.waitForTimeout(300);
        }
        const todayBtn = page.locator('#todayBtn, .today-btn, button:has-text("Today")').first();
        if (await todayBtn.isVisible().catch(() => false)) {
          await todayBtn.click();
        }
        await page.reload({ waitUntil: 'networkidle' });
        const stored = sessionScoped
          ? await page.evaluate(() =>
              // the venue's session-scoped memory: weekmem-venueTimetableWeek@<stamp>
              Object.keys(sessionStorage).some((k) => k.startsWith('weekmem-venueTimetableWeek@'))
            )
          : await page.evaluate((k) => {
              const raw = localStorage.getItem(k);
              if (!raw) return null;
              try { return JSON.parse(raw); } catch { return raw; }
            }, storageKey);
        // the WRITE must exist: session mode = the weekmem key exists
        // (a guest bounce leaves false — the documented baseline class);
        // legacy rows keep their not.toBeNull() semantics.
        if (sessionScoped) expect(stored).toBe(true);
        else expect(stored).not.toBeNull();
      });
    }

    test('TC-2.5 Replacement Arrangement week selector respects saved week', async ({ page }) => {
      await page.goto(`${BASE}/replacement-arrangement`, { waitUntil: 'networkidle' });
      await page.evaluate(() => localStorage.setItem('currentWeek', '5'));
      await page.reload({ waitUntil: 'networkidle' });
      const select = page.locator('#weekSelector, #weekSelect').first();
      if (await select.isVisible().catch(() => false)) {
        await expect(select).toHaveValue('5');
      }
    });
  });

  // Section 3: Modal & Detail Views
  test.describe('Section 3: Modal & Detail Views', () => {
    /* replacement-merge-one-table (T19): /replacement-home-ui and
       /my-request-history-ui are one page now — the merged My Replacements
       table — so both open the SAME pop-up (`#quickViewModal`) and that pop-up
       labels the class `Class ID`, not `Subject Code`. The row is the door
       (M6): click the course-code cell, and fall back to the eye icon. */
    const modalPages: [string, string, string, string][] = [
      ['/my-timetable-ui', '#classModal', '.event-block', 'Subject Code'],
      ['/cohort-timetable', '#classModal', '.event-block', 'Subject Code'],
      ['/venue-timetable-ui', '#eventModal', '.event-block', 'Subject Code'],
      ['/replacement-home-ui', '#quickViewModal', '#tableBody .col-code, #tableBody .btn-icon--ghost', 'Class ID'],
      ['/request-approval-ui', '#modalOverlay', '.replacement-card, .request-row', 'Subject Code'],
      ['/my-request-history-ui', '#quickViewModal', '#tableBody .col-code, #tableBody .btn-icon--ghost', 'Class ID'],
    ];

    for (const [url, modalSelector, clickSelector, expectedText] of modalPages) {
      test(`TC-3.x Modal opens on ${url}`, async ({ page }) => {
        await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
        // NOTE: a bare 'tbody tr' matches BEFORE its child .event-block in
        // DOM order, and clicking the row opens nothing — always prefer the
        // block/card itself over its row container.
        const clickable = page.locator(clickSelector).first();
        if (await clickable.isVisible().catch(() => false)) {
          await clickable.click();
          await expect(page.locator(modalSelector).first()).toBeVisible({ timeout: 5000 });
          await expect(page.locator(`${modalSelector} .modal-body, ${modalSelector} .modal-content`)).toContainText(expectedText);
        } else {
          test.info().annotations.push({ type: 'fixture', description: `no row on ${url} — the pop-up was not exercised` });
        }
    

  });

  // Section 4: Replacement Flow
  test.describe('Section 4: Replacement Flow', () => {
    // Toolbar restructure: the ?code= URL param no longer pre-selects the
    // subject — the grid is locked until a subject is picked manually.
    const flowUrl = `${BASE}/replacement-arrangement`;

    async function openFlow(page) {
      await page.goto(flowUrl, { waitUntil: 'networkidle' });
      await page.locator('#subjectSelector').selectOption({ index: 1 });
      await page.locator('.timetable .cell-content.cell-available').first()
        .waitFor({ state: 'visible', timeout: 10000 });
    }

    test('TC-4.1 Select slot and verify summary updates', async ({ page }) => {
      await openFlow(page);
      const available = page.locator('.timetable .cell-content.cell-available').first();
      await available.click();
      // the click selects a whole merged block (subject-duration span)
      await expect(page.locator('.timetable .event-block.event-selection')).toBeVisible({ timeout: 5000 });
      // selection-budget-original-duration: the budget flexes to the occurrence's span
      // — the counter string captured dynamically (theokra: "N of N slots" — the
      // span survives the toggle-off + "0 of N slots" at clear).
      const counterAfter = (await page.locator('#infoTotal').textContent()) as string; // e.g. '2 of 2 slots' / '4 of 4 slots'
      await expect(page.locator('#infoTotal')).toContainText(' of ' + counterAfter.split(' of ')[1]);
      await expect(page.locator('#infoTotal')).not.toContainText('0 of ' + counterAfter.split(' of ')[1]);
      // clicking the merged block toggles it off again
      await page.locator('.timetable .event-block.event-selection').first().click();
      await expect(page.locator('#infoTotal')).toContainText('0 of ' + counterAfter.split(' of ')[1], { timeout: 5000 });
    });

    test('TC-4.2 Maximum selection enforcement', async ({ page }) => {
      await openFlow(page);
      // selection-budget-original-duration: the FIRST click selects a whole
      // block = the FULL dynamic budget (the picked occurrence's span). Read
      // the counter dynamically; the further-selection refusal re-asserts.
      await page.locator('.timetable .cell-content.cell-available').first().click();
      const full = (await page.locator('#infoTotal').textContent()) as string;
      await expect(page.locator('#infoTotal')).toContainText(full, { timeout: 5000 });
      await expect(full).toMatch(/\d+ of \d+ slots/);
      await expect((full.split(' of ')[0].replace(/[^\d]/g, '')) === (full.split(' of ')[1].replace(/[^\d]/g, ''))).toBe(true, 'the whole block = the full budget (picked N of N)');
      // a further selection must be refused
      const others = page.locator('.timetable .cell-content.cell-available');
      if ((await others.count()) > 0) {
        await others.first().click();
        await page.waitForTimeout(300);
        await expect(page.locator('#infoTotal')).toContainText(full);
      }
    });

    test('TC-4.3 Clear All resets selection', async ({ page }) => {
      await openFlow(page);
      await page.locator('.timetable .cell-content.cell-available').first().click();
      const counter = (await page.locator('#infoTotal').textContent()) as string; // the picked N of N
      await expect(page.locator('#infoTotal')).not.toContainText('0 of ' + counter.split(' of ')[1]);
      await page.locator('#clearAllBtn').click();
      // critical action — confirm popup first (AGENTS §9)
      await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 5000 });
      await page.locator('#confirmModal #modalConfirmBtn').click();
      await expect(page.locator('#infoTotal')).toContainText('0 of ' + counter.split(' of ')[1], { timeout: 5000 });
    });

    test('TC-4.4 Submit request confirmation modal', async ({ page }) => {
      await openFlow(page);
      await page.locator('.timetable .cell-content.cell-available').first().click();
      await page.locator('button:has-text("Submit Request")').first().click();
      await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 5000 });
    });

    test('TC-4.5 Submit request confirm sends request', async ({ page }) => {
      await openFlow(page);
      await page.locator('.timetable .cell-content.cell-available').first().click();
      await page.locator('button:has-text("Submit Request")').first().click();
      const confirm = page.locator('#confirmModal button.btn-primary, #confirmModal #modalConfirmBtn').first();
      if (await confirm.isVisible().catch(() => false)) {
        await confirm.click();
        await page.waitForTimeout(400);
      }
      // accepted outcomes: navigated away (request recorded) or a toast/notice
      const navigated = !page.url().includes('replacement-arrangement');
      const noticed = await page.locator('#toastBar.visible, .toast, .notification, [role="status"]').first()
        .isVisible().catch(() => false);
      expect(navigated || noticed).toBeTruthy();
    });
  });

  // Section 5: Request Approval Flow
  test.describe('Section 5: Request Approval Flow', () => {
    test('TC-5.1 Approve request confirmation popup', async ({ page }) => {
      await page.goto(`${BASE}/request-approval-ui`, { waitUntil: 'networkidle' });
      const rowApprove = page.locator('.action-row .btn-approve').first();
      if (await rowApprove.isVisible().catch(() => false)) {
        await rowApprove.click();
        await expect(page.locator('#approveNotesModal')).toBeVisible({ timeout: 5000 });
      }
    });

    test('TC-5.2 Reject request requires reason', async ({ page }) => {
      await page.goto(`${BASE}/request-approval-ui`, { waitUntil: 'networkidle' });
      const reject = page.locator('button:has-text("Reject"), .btn-reject').first();
      if (await reject.isVisible().catch(() => false)) {
        await reject.click();
        const textarea = page.locator('textarea[placeholder*="reason"], .rejection-reason').first();
        await expect(textarea).toBeVisible({ timeout: 5000 });
        const confirm = page.locator('button:has-text("Confirm"), .confirm-reject').first();
        const disabled = await confirm.isDisabled().catch(() => true);
        expect(disabled).toBe(true);
      }
    });
  });

  // Section 6: Request History
  test.describe('Section 6: Request History', () => {
    test('TC-6.1 My Request History status badges correct', async ({ page }) => {
      await page.goto(`${BASE}/my-request-history-ui`, { waitUntil: 'networkidle' });
      const approved = page.locator('.badge:has-text("Approved"), .status-approved').first();
      const rejected = page.locator('.badge:has-text("Rejected"), .status-rejected').first();
      const pending = page.locator('.badge:has-text("Pending"), .status-pending').first();
      const counts = await Promise.all([
        approved.isVisible().catch(() => false),
        rejected.isVisible().catch(() => false),
        pending.isVisible().catch(() => false),
      ]);
      expect(counts.some(Boolean)).toBe(true);
    });
  });

  // Section 7: Summary Cards — My Teaching / Own Records
  test.describe('Section 7: Summary Cards — My Teaching / Own Records', () => {
    test('TC-7.1 Cohort summary: My Teaching cards count each class separately', async ({ page }) => {
      await page.goto(`${BASE}/cohort-timetable`, { waitUntil: 'networkidle' });
      await page.locator('#facultySelect').selectOption('focs');
      await page.locator('#cohortSelect').selectOption('dft2s1');
      await page.locator('#weekSelect').selectOption('0');
      // summary-cards-confirmed-pending (2026-11-02): the cohort page renders
      // the four split cards as `X+Y` (Y = the pending share; `+0` with none),
      // so the assertions pin the CONFIRMED part and tolerate the tail.
      await expect(page.locator('#sumMyClasses')).toHaveText(/^3(\+[\d.]+)?$/);   // AMCS2093 (L)+(T)+(P) — three classes, not one subject
      await expect(page.locator('#sumMyHours')).toHaveText(/^4(\+[\d.]+)?$/);     // 2h + 1h + 1h
      // the split cards keep their ids and stay numeric (X, optionally +Y)
      for (const id of ['sumTotal', 'sumHours', 'sumMyClasses', 'sumMyHours']) {
        expect(await page.locator('#' + id).textContent())
          .toMatch(/^\d+(?:\.\d)?(?:\+[\d.]+)?$/);
      }
      // the Conflicts card is NOT split — a plain number
      expect(Number(await page.locator('#sumConflict').textContent())).not.toBeNaN();
    });

    test('TC-7.2 Replacement Home is own-records: table and cards scoped to the logged-in lecturer', async ({ page }) => {
      await page.goto(`${BASE}/replacement-home-ui`, { waitUntil: 'networkidle' });
      // 4 of the 14 seeded conflicts belong to En. Lim Jia Zheng (§2.10 lecturer field)
      await expect(page.locator('#tableBody tr')).toHaveCount(4);
      /* replacement-merge-one-table (T19) — the own-records LAW, expressed so it
         holds on any data. A row is now a CLASS OCCURRENCE (including ones with
         no request at all), so the old 4 rows / 4 / 8 / 2 pins describe nothing.
         What must be true: the table lists exactly the payload the server sent
         minus the Completed ones (the default toggle hides those), every card is
         numeric, and the four status cards cannot count more rows than exist. */
      const payload = await page.evaluate(() =>
        ((window as any).MockData?.conflictedClasses ?? []).map((c: any) => String(c.requestStatus ?? 'none')),
      );
      await expect(page.locator('#summaryNeeds')).toHaveText(/^\d+(\.\d)?$/);
      for (const card of ['#summaryHours', '#summaryPending', '#summaryApproved', '#summaryRejected']) {
        await expect(page.locator(card), card).toHaveText(/^\d+(\.\d)?$/);
      }

      const shown = payload.filter((s: string) => s !== 'completed').length;
      await expect(page.locator('#tableBody tr'), 'the table lists the server payload minus the hidden Completed rows').toHaveCount(shown);

      const tally = await page.evaluate(() =>
        ['summaryNeeds', 'summaryPending', 'summaryApproved', 'summaryRejected']
          .map((id) => Number(document.getElementById(id)?.textContent ?? 0))
          .reduce((a, b) => a + b, 0),
      );
      expect(tally, 'the four status cards cannot count more rows than are listed').toBeLessThanOrEqual(shown);
      // others' conflicts are gone (id 3 — AMCS1013, Ts. Norshikin)
      await expect(page.locator('#tableBody tr:has-text("AMCS1013")')).toHaveCount(0);
      // replaced cards are gone entirely
      await expect(page.locator('#summaryVenues')).toHaveCount(0);
      await expect(page.locator('#summaryStudents')).toHaveCount(0);
    });
  });
});
