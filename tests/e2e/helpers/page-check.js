import { expect } from '@playwright/test';

/**
 * Shared smoke-check: loads a route, asserts no errors, verifies key element + title.
 * @param {import('@playwright/test').Page} page
 * @param {string} path - route path (e.g. '/my-timetable-ui')
 * @param {string[]} keySelectors - CSS selectors that must be visible (at least one)
 * @param {string} expectedTitle - expected page title substring
 */
export async function checkPage(page, path, keySelectors, expectedTitle) {
  const consoleErrors = [];
  const pageErrors = [];
  const failedRequests = [];
  const badResponses = [];

  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });

  page.on('pageerror', err => {
    pageErrors.push(err.message);
  });

  page.on('requestfailed', req => {
    failedRequests.push(`${req.failure().errorText} ${req.url()}`);
  });

  page.on('response', res => {
    if (res.status() >= 400) {
      badResponses.push(`${res.status()} ${res.url()}`);
    }
  });

  await page.goto(path, { waitUntil: 'networkidle' });

  // Title assertion
  await expect(page).toHaveTitle(new RegExp(expectedTitle));

  // Key element assertion — at least one selector must be visible
  let found = false;
  for (const sel of keySelectors) {
    try {
      await page.locator(sel).first().waitFor({ state: 'visible', timeout: 10_000 });
      found = true;
      break;
    } catch {
      // try next selector
    }
  }
  expect(found, `None of the key selectors [${keySelectors}] were visible on ${path}`).toBeTruthy();

  // Error assertions
  expect(consoleErrors, `Console errors on ${path}: ${consoleErrors.join('; ')}`).toHaveLength(0);
  expect(pageErrors, `JS exceptions on ${path}: ${pageErrors.join('; ')}`).toHaveLength(0);
  expect(failedRequests, `Failed requests on ${path}: ${failedRequests.join('; ')}`).toHaveLength(0);
  expect(badResponses, `Bad responses on ${path}: ${badResponses.join('; ')}`).toHaveLength(0);
}
