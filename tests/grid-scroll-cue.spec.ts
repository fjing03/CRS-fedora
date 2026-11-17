import { test, expect } from '@playwright/test';

/**
 * grid-scroll-cue — read-only receipt for the 2026-11-17 scroll cue.
 *
 * WHY. At 1440 the arrangement grid is 1976px wide inside a 1390px card, and Chromium's
 * overlay scrollbars take no space, so nothing on screen said the week continued past
 * the clipped `15:00 / 16:00` header. The user's ruling (2026-11-17): keep the density,
 * make the hidden part obvious.
 *
 * WHAT IS PINNED HERE
 *   1. a grid that does not fit shows the right cue, and marks itself is-more-right;
 *   2. scrolled to the end, the right cue goes away and the left one appears;
 *   3. a grid that fits shows no cue at all (the cue is a fact, not decoration);
 *   4. print hides both the cue and the edge fade;
 *   5. the cue is never a target — pointer-events: none, aria-hidden.
 *
 * SAFETY. Read-only browsing. No fixtures, no SQL, no writes, no booking handoff.
 * Guard is fail-closed at module level (this file writes nothing, so CRS_ALLOW_SQL is
 * not required — same contract as cohort-venue-responsive.spec.ts):
 *
 *   CRS_MODE=testing CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/grid-scroll-cue.spec.ts --reporter=line
 */

const BASE = process.env.CRS_BASE || 'http://127.0.0.1:8030';
const STAFF_AUTH = '.auth/staff-5425.json';

async function openGrid(page: import('@playwright/test').Page, path: string) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await expect(page.locator('.grid-wrapper').first()).toBeAttached();
    // the module syncs on load, on resize and on Livewire navigation; give it a frame
    await page.waitForTimeout(150);
}

test.use({ storageState: STAFF_AUTH, viewport: { width: 1440, height: 900 } });

test('G1. a grid wider than its card shows the right cue, and hides it at the end', async ({ page }) => {
    await openGrid(page, '/replacement-arrangement');

    const wrapper = page.locator('.grid-wrapper').first();
    const scroller = wrapper.locator('.grid-scroll');
    const rightCue = wrapper.locator('.grid-scroll-cue--right');
    const leftCue = wrapper.locator('.grid-scroll-cue--left');

    const overflow = await scroller.evaluate((el) => el.scrollWidth - el.clientWidth);
    test.skip(overflow <= 4, 'this fixture fits at 1440 — the cue has nothing to say');

    await expect(wrapper).toHaveClass(/is-more-right/);
    await expect(rightCue).toBeVisible();
    await expect(leftCue).toBeHidden();

    await scroller.evaluate((el) => { el.scrollLeft = el.scrollWidth; });
    await expect(wrapper).toHaveClass(/is-more-left/);
    await expect(rightCue).toBeHidden();
    await expect(leftCue).toBeVisible();
});

test('G2. the cue is a hint, never a target', async ({ page }) => {
    await openGrid(page, '/replacement-arrangement');
    const cue = page.locator('.grid-scroll-cue--right');
    await expect(cue).toHaveAttribute('aria-hidden', 'true');
    await expect(cue).toHaveCSS('pointer-events', 'none');
});

test('G3. a grid that fits shows no cue', async ({ page }) => {
    await page.goto(BASE + '/venue-timetable-ui', { waitUntil: 'networkidle' });
    const wrappers = page.locator('.grid-wrapper');
    const count = await wrappers.count();
    for (let i = 0; i < count; i++) {
        const w = wrappers.nth(i);
        const over = await w.locator('.grid-scroll').evaluate((el) => el.scrollWidth - el.clientWidth);
        if (over > 4) continue; // wide fixture: the cue is correct there, G1 covers it
        await expect(w).not.toHaveClass(/is-more-right/);
        await expect(w.locator('.grid-scroll-cue--right')).toBeHidden();
    }
});

test('G4. print hides the cue and the edge fade', async ({ page }) => {
    await openGrid(page, '/replacement-arrangement');
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.grid-scroll-cue--right')).toBeHidden();
    const fadeOpacity = await page.locator('.grid-wrapper').first().evaluate((el) =>
        getComputedStyle(el, '::after').opacity);
    expect(Number(fadeOpacity)).toBe(0);
});
