import { test, expect } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

abortUnlessTestingTarget('empty-search-copy-and-student-title');

// Read-only receipt: login/session writes only. Empty-data coverage replaces a
// browser-local array, never database rows; closing the context discards it.
const BASE = process.env.CRS_BASE!;

test('empty-search copy distinguishes hidden rows from an empty workload', async ({ browser }) => {
    for (const width of [1440, 390]) {
        const context = await browser.newContext({
            storageState: '.auth/staff-5425.json',
            viewport: { width, height: 900 },
            hasTouch: width === 390,
            isMobile: width === 390,
        });
        try {
            const page = await context.newPage();
            await page.addInitScript(() => localStorage.removeItem('mrh-filters'));
            await page.goto(BASE + '/my-replacements', { waitUntil: 'networkidle' });
            await expect(page.locator('#tableBody tr').first()).toBeAttached();

            await page.locator('.filters-btn').click();
            await page.locator('#searchInput').fill('ZZZNOMATCH');
            await page.keyboard.press('Escape');
            await expect(page.locator('#emptyState')).toBeVisible();
            await expect(page.locator('#emptyTitle')).toHaveText('No classes match your filters.');
            await expect(page.locator('#emptyText')).toHaveText('Try adjusting your search or filter criteria.');
            await expect(page.locator('#resultCount')).toContainText(/^Showing 0 of [1-9]\d* classes$/);
            await page.screenshot({ path: `/tmp/crs-wording-empty-${width}.png` });

            // Truly empty workload: client-only presentation fixture. Reuse the
            // exact original array afterwards, and verify the title resets.
            await page.evaluate(() => {
                const w = window as any;
                w.__copyReceiptRows = w.MockData.conflictedClasses;
                w.MockData.conflictedClasses = [];
                w.buildTable();
            });
            await expect(page.locator('#emptyTitle')).toHaveText('No classes currently need attention.');
            await expect(page.locator('#resultCount')).toHaveText('Showing 0 of 0 classes');
            await page.evaluate(() => {
                const w = window as any;
                w.MockData.conflictedClasses = w.__copyReceiptRows;
                delete w.__copyReceiptRows;
                w.buildTable();
            });
            await expect(page.locator('#emptyTitle')).toHaveText('No classes match your filters.');
            await page.locator('.filter-chip-remove').first().click();
            await expect(page.locator('#emptyState')).toBeHidden();
            await expect(page.locator('#tableBody tr').first()).toBeAttached();
        } finally {
            await context.close();
        }
    }
});

test('student heading agrees with the tab title and navigation', async ({ browser }) => {
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true,
    });
    try {
        const page = await context.newPage();
        await page.goto(BASE + '/login/student');
        await page.locator('#login_id').fill('24RSD0009');
        await page.locator('#password').fill('Tarumt@2026');
        await Promise.all([
            page.waitForURL('**/student-my-timetable-ui'),
            page.locator('#loginBtn').click(),
        ]);
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(BASE + '/student-class-replacements', { waitUntil: 'networkidle' });
            await expect(page.locator('h1')).toHaveText('Class Replacements');
            await expect(page).toHaveTitle(/^Class Replacements(?: —.*)?$/);
            const links = page.locator('a[href="/student-class-replacements"]');
            expect(await links.count()).toBeGreaterThan(0);
            for (const link of await links.all()) await expect(link).toHaveText('Class Replacements');
            await expect(page.locator('#upcomingBody tr').first()).toBeAttached();
            await page.screenshot({ path: `/tmp/crs-wording-student-${width}.png` });
        }
    } finally {
        await context.close();
    }
});
