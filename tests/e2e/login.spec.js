import { test } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

test.describe('Login pages', () => {
  test('Student Login', async ({ page }) => {
    await checkPage(page, '/login/student', ['form[action="/login"]', '#login_id'], 'Student Login');
  });

  test('Staff Login', async ({ page }) => {
    await checkPage(page, '/login/staff', ['form[action="/login"]', '#login_id'], 'Staff Login');
  });
});
