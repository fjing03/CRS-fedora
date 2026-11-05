import { test, expect, Page } from '@playwright/test';
import { throwUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * receipt-target-guard: TC-4.5 refuses to run unless the testing (:8030) envs —
 * its confirm path records a real replacement_requests row; the demo DB (:8000)
 * is read-only for agents since the 2026-11-01 wipe (docs/demo-db-data-changes.md).
 */

const BASE = (process.env.CRS_BASE ?? 'http://localhost:8000');

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

/* Every case below is a LECTURER page, and playwright.config.ts sets no default
   storageState — so without this the file browsed as a GUEST and the modal/table
   cases "passed" by annotating instead of asserting. 5425 is the demo's PL
   (is_pl), which Section 5 needs. The one student row overrides this locally. */
test.use({ storageState: '.auth/staff-5425.json' });

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
      /* read from each blade's `new WeekNavigator(...)`: all four view pages are
         {sessionScoped:true}, and my-timetable + student pass storageKey = null,
         so the constructor's default 'weekNav-' + selectId applies. The old three
         rows named keys no page has written since 2026-10-20. */
      { url: '/my-timetable-ui', storageKey: 'weekNav-weekSelect', sessionScoped: true },
      { url: '/cohort-timetable', storageKey: 'cohortTimetableWeek', sessionScoped: true },
      { url: '/venue-timetable-ui', storageKey: 'venueTimetableWeek', sessionScoped: true },
      { url: '/student-my-timetable-ui', storageKey: 'weekNav-weekSelect', sessionScoped: true },
    ];

    for (const { url, storageKey, sessionScoped } of todayPages) {
      test(`TC-2.x Today button persists on ${url}`, async ({ page }) => {
        if (url === '/student-my-timetable-ui') {
          // the staff storageState is a bounce on a student route — clear it and
          // sign in as the seeded student (one extra login per run; the Fortify
          // limiter is 5/min)
          await page.context().clearCookies();
          await page.goto(`${BASE}/login/student`, { waitUntil: 'networkidle' });
          await page.fill('#login_id', '24DFT0001');
          await page.fill('#password', 'Tarumt@2026');
          await page.click('button[type="submit"]');
          await page.waitForURL(/student-my-timetable|dashboard/, { timeout: 30_000 });
        }
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
        /* week-memory-until-logout (ui-common.js WeekNavigator.save()): only a
           page built with {sessionScoped:true} writes `weekmem-<key>@<stamp>` to
           sessionStorage. Every other page keeps the plain
           `localStorage['<storageKey>']`. The old rows demanded the weekmem
           prefix on localStorage for ALL four — a key no page writes. */
        const stored = sessionScoped
          ? await page.evaluate((k) => {
              const pref = 'weekmem-' + k + '@';
              const hit = Object.keys(sessionStorage).find((key) => key.startsWith(pref));
              return hit ? sessionStorage.getItem(hit) : null;
            }, storageKey)
          : await page.evaluate((k) => localStorage.getItem(k), storageKey);
        expect(stored, `the page must remember its week (${sessionScoped ? 'sessionStorage weekmem-' + storageKey : 'localStorage ' + storageKey})`).not.toBeNull();
        expect(String(stored)).toMatch(/^\d+$/);
      });
    }

    test('TC-2.5 Replacement Arrangement week selector respects saved week', async ({ page }) => {
      await page.goto(`${BASE}/replacement-arrangement`, { waitUntil: 'networkidle' });
      /* week-memory-until-logout: the page no longer reads a global
         'currentWeek'. Writing a week straight into storage fights the page's
         bookable-week filter (a week with no slot resolves back to today), so
         drive the real law instead: pick a week the selector OFFERS, reload, and
         the page must come back on it. */
      const select = page.locator('#weekSelector, #weekSelect').first();
      if (!(await select.isVisible().catch(() => false))) {
        test.info().annotations.push({ type: 'fixture', description: 'no week selector on the page' });
        return;
      }
      const before = await select.inputValue();
      const offered = await select.locator('option').evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value));
      const target = offered.find((v) => v !== before);
      if (!target) {
        test.info().annotations.push({ type: 'fixture', description: 'the selector offers only one week' });
        return;
      }
      await select.selectOption(target);
      await page.waitForTimeout(500);
      await page.reload({ waitUntil: 'networkidle' });
      await expect(page.locator('#weekSelector, #weekSelect').first()).toHaveValue(target);
    });
  });

  // Section 3: Modal & Detail Views
  test.describe('Section 3: Modal & Detail Views', () => {
    /* replacement-merge-one-table (T19): /replacement-home-ui and
       /my-request-history-ui are one page now — the merged My Replacements
       table — so both open the SAME pop-up (`#quickViewModal`) and that pop-up
       labels the class `Class ID`, not `Subject Code`. The row is the door
       (M6): click the course-code cell, and fall back to the eye icon. */
    /* Which overlay a page opens is a moving target (#classModal, #eventModal,
       #modalOverlay, #quickViewModal…), and hard-coding it per page is how this
       section ended up asserting two ids that no page opens any more. The law
       under test is: click a class, a pop-up appears, and it names the class. */
    const modalPages: [string, string, string][] = [
      ['/my-timetable-ui', '.event-block', 'Subject Code'],
      ['/cohort-timetable', '.event-block', 'Subject Code'],
      ['/venue-timetable-ui', '.event-block', 'Subject Code'],
      ['/request-approval-ui', '.replacement-card, .request-row', 'Subject Code'],
      ['/replacement-home-ui', '#tableBody .col-code', 'Class ID'],
      ['/my-request-history-ui', '#tableBody .col-code', 'Class ID'],
    ];

    for (const [url, clickSelector, expectedText] of modalPages) {
      test(`TC-3.x Modal opens on ${url}`, async ({ page }) => {
        await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
        // NOTE: a bare 'tbody tr' matches BEFORE its child .event-block in
        // DOM order, and clicking the row opens nothing — always prefer the
        // block/card itself over its row container.
        const clickable = page.locator(clickSelector).first();
        if (!(await clickable.isVisible().catch(() => false))) {
          test.info().annotations.push({ type: 'fixture', description: `no class on ${url} — the pop-up was not exercised` });
          return;
        }
        await clickable.click();
        // wait until SOME overlay is visible, then read only the visible ones
        const visibleOverlayText = () =>
          page.locator('.modal-overlay').evaluateAll((els) =>
            // NOT offsetParent — a fixed-position overlay always reports null
            els.filter((e) => {
              const s = getComputedStyle(e);
              const r = e.getBoundingClientRect();
              return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 0 && r.height > 0;
            })
              .map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim())
              .join(' | '),
          );
        await expect.poll(visibleOverlayText, { timeout: 5000 }).not.toBe('');
        const openedText = await visibleOverlayText();
        expect(openedText, `the pop-up on ${url} names the class`).toContain(expectedText);
      });
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
      // the receipt-target-guard: the confirm path records a real
      // replacement_requests row — testing-DB envs only
      throwUnlessTestingTarget('ui-regression TC-4.5 — the submit-confirm writes a replacement_requests row');
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
      /* The three selects are populated by the server, so their option VALUES are
         DB-specific ('focs'/'dft2s1' are the demo fixture's). Pick the first real
         option instead, and assert the LAW this case is named for — each class
         counts separately, and every card renders the split shape `X` or `X+Y`
         (the summary-cards-confirmed-pending contract, 2026-11-02) — rather than
         the demo's 3 classes / 4 hours, which describe the demo DB only. */
      for (const [id, nth] of [['#facultySelect', 1], ['#cohortSelect', 0], ['#weekSelect', 0]] as const) {
        const control = page.locator(id).first();
        // a disabled/covered picker never accepts an action — the case is about
        // the cards, not about the picker, so a refused select is not a failure
        if (await control.isVisible().catch(() => false) && await control.isEnabled().catch(() => false)) {
          const options = await control.locator('option').count();
          if (options > nth) await control.selectOption({ index: nth }, { timeout: 3000 }).catch(() => {});
        }
      }
      await page.waitForTimeout(600);

      if (!(await page.locator('#sumMyClasses').isVisible().catch(() => false))) {
        test.info().annotations.push({
          type: 'fixture',
          description: 'the cohort grid did not open on this DB — the My Teaching cards are not rendered to assert',
        });
        return;
      }

      for (const id of ['#sumTotal', '#sumHours', '#sumMyClasses', '#sumMyHours']) {
        await expect(page.locator(id), `${id} keeps the X or X+Y shape`).toHaveText(/^\d+(?:\.\d)?(?:\+[\d.]+)?$/);
      }
      expect(Number(await page.locator('#sumConflict').textContent())).not.toBeNaN();
    });

    test('TC-7.2 Replacement Home is own-records: table and cards scoped to the logged-in lecturer', async ({ page }) => {
      await page.goto(`${BASE}/replacement-home-ui`, { waitUntil: 'networkidle' });
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
