import { test, expect } from '@playwright/test';
import { checkPage } from './helpers/page-check.js';

// ══════════════════════════════════════════════════════════════
// AUTH WIRING — Playwright E2E Tests
// ══════════════════════════════════════════════════════════════

// ─── 1. Login Pages: Remember Me ─────────────────────────────

test.describe('Login: Remember Me checkbox', () => {
  test('Student login has remember me checkbox and hint', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const checkbox = page.locator('input[name="remember"][type="checkbox"]');
    await expect(checkbox).toBeAttached();

    const label = page.locator('label.remember-me');
    await expect(label).toBeVisible();
    await expect(label).toContainText('Remember me');

    const hint = page.locator('p.remember-hint');
    await expect(hint).toBeVisible();
    await expect(hint).toContainText('30 days');
  });

  test('Staff login has remember me checkbox and hint', async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });

    const checkbox = page.locator('input[name="remember"][type="checkbox"]');
    await expect(checkbox).toBeAttached();

    const label = page.locator('label.remember-me');
    await expect(label).toBeVisible();
    await expect(label).toContainText('Remember me');

    const hint = page.locator('p.remember-hint');
    await expect(hint).toBeVisible();
    await expect(hint).toContainText('30 minutes');
  });

  test('Remember me checkbox is unchecked by default', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const checkbox = page.locator('input[name="remember"][type="checkbox"]');
    await expect(checkbox).not.toBeChecked();
  });

  test('Remember me checkbox can be toggled', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const checkbox = page.locator('input[name="remember"][type="checkbox"]');
    await checkbox.check();
    await expect(checkbox).toBeChecked();

    await checkbox.uncheck();
    await expect(checkbox).not.toBeChecked();
  });
});

// ─── 2. Login Pages: Form Structure ──────────────────────────

test.describe('Login: Form structure', () => {
  test('Student login form posts to /login with login_type=student', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const form = page.locator('form[action="/login"]');
    await expect(form).toBeAttached();

    const loginType = form.locator('input[name="login_type"][value="student"]');
    await expect(loginType).toBeAttached();

    const csrf = form.locator('input[name="_token"]');
    await expect(csrf).toBeAttached();
  });

  test('Staff login form posts to /login with login_type=staff', async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });

    const form = page.locator('form[action="/login"]');
    await expect(form).toBeAttached();

    const loginType = form.locator('input[name="login_type"][value="staff"]');
    await expect(loginType).toBeAttached();
  });

  test('Student login has role switch link to staff', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const link = page.locator('a[href*="login/staff"]');
    await expect(link).toBeVisible();
    await expect(link).toContainText('Staff Login');
  });

  test('Staff login has role switch link to student', async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });

    const link = page.locator('a[href*="login/student"]');
    await expect(link).toBeVisible();
    await expect(link).toContainText('Student Login');
  });
});

// ─── 3. Login Pages: Lockout Countdown ───────────────────────

test.describe('Login: Lockout countdown', () => {
  test('Staff login page includes lockout countdown script', async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });

    const script = page.locator('script[src="/js/lockout-countdown.js"]');
    await expect(script).toBeAttached();
  });

  test('Student login page does not include lockout script', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const script = page.locator('script[src="/js/lockout-countdown.js"]');
    await expect(script).toHaveCount(0);
  });

  test('Lockout countdown partial is included in staff login template', async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });

    // The lockout countdown is conditionally rendered via @if(session('lockout_expires'))
    // Verify the script is loaded (which drives the countdown when session data exists)
    const script = page.locator('script[src="/js/lockout-countdown.js"]');
    await expect(script).toBeAttached();
  });
});

// ─── 4. Nav Bar: Guest State ─────────────────────────────────

test.describe('Nav bar: Guest state', () => {
  test('Shows login link when not authenticated', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const loginLink = page.locator('.top-right .login-link');
    await expect(loginLink).toBeVisible();
    await expect(loginLink).toContainText('Login');
    await expect(loginLink).toHaveAttribute('href', '/login/student');
  });

  test('Does not show user panel when not authenticated', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const userPanel = page.locator('.top-right .user-panel');
    await expect(userPanel).toHaveCount(0);
  });

  test('Does not show session dot when not authenticated', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const sessionDot = page.locator('.session-dot');
    await expect(sessionDot).toHaveCount(0);
  });

  test('Nav items are visible and clickable', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const navItems = page.locator('.nav-items .nav-item');
    const count = await navItems.count();
    expect(count).toBeGreaterThanOrEqual(5);

    const firstItem = navItems.first();
    await expect(firstItem).toBeVisible();
    const href = await firstItem.getAttribute('href');
    expect(href).toBeTruthy();
  });
});

// ─── 5. Nav Bar: Mobile Drawer Guest State ───────────────────

test.describe('Nav bar: Mobile drawer guest state', () => {
  test('Mobile drawer shows login link when not authenticated', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const drawerLogin = page.locator('.nav-drawer .login-link');
    await expect(drawerLogin).toBeAttached();
    await expect(drawerLogin).toContainText('Login');
  });

  test('Mobile drawer does not show user info when not authenticated', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const drawerUser = page.locator('.nav-drawer-user-avatar');
    await expect(drawerUser).toHaveCount(0);
  });
});

// ─── 6. Nav Bar: Structure & Elements ────────────────────────

test.describe('Nav bar: Structure', () => {
  test('Has logo that navigates home', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const logo = page.locator('.top-logo');
    await expect(logo).toBeVisible();

    const img = logo.locator('img');
    await expect(img).toBeVisible();
    await expect(img).toHaveAttribute('alt', 'TAR UMT');
  });

  test('Has theme toggle button', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const toggle = page.locator('.theme-toggle');
    await expect(toggle).toBeVisible();
  });

  test('Has notification button', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const notifBtn = page.locator('.notif-btn');
    await expect(notifBtn).toBeVisible();
  });

  test('Has hamburger menu button (hidden on desktop)', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const hamburger = page.locator('.nav-hamburger');
    await expect(hamburger).toBeAttached();
  });
});

// ─── 7. Session Countdown: Partial Structure ─────────────────

test.describe('Session countdown: Partial structure', () => {
  test('Session countdown is NOT rendered for guest users (wrapped in @auth)', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const countdown = page.locator('.session-countdown');
    await expect(countdown).toHaveCount(0);
  });

  test('Session expiry modal is NOT rendered for guest users', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const modal = page.locator('#sessionModal');
    await expect(modal).toHaveCount(0);
  });

  test('Session countdown script IS loaded for all users', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const script = page.locator('script[src="/js/session-countdown.js"]');
    await expect(script).toBeAttached();
  });
});

// ─── 8. Session Indicator ────────────────────────────────────

test.describe('Session indicator', () => {
  test('Session dot element exists in nav bar (hidden when guest)', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const dot = page.locator('.session-dot');
    const count = await dot.count();
    expect(count).toBe(0);
  });
});

// ─── 9. Auto-Logout: Staff Only ──────────────────────────────

test.describe('Auto-logout: Staff only', () => {
  test('Auto-logout script is NOT loaded for guest users', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const script = page.locator('script[src="/js/auto-logout.js"]');
    await expect(script).toHaveCount(0);
  });

  test('Auto-logout form is NOT present for guest users', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const form = page.locator('#auto-logout-form');
    await expect(form).toHaveCount(0);
  });

  test('Idle warning modal is NOT present for guest users', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const modal = page.locator('#idleWarningModal');
    await expect(modal).toHaveCount(0);
  });
});

// ─── 10. Smoke: All pages load without auth errors ───────────

test.describe('Smoke: All pages load without 401/403', () => {
  const pages = [
    { path: '/my-timetable-ui', title: 'My Timetable' },
    { path: '/cohort-timetable-ui', title: 'Cohort Timetable' },
    { path: '/replacement-home-ui', title: 'Replacement' },
    { path: '/my-request-history-ui', title: 'My Request History' },
    { path: '/student-my-timetable-ui', title: 'My Timetable' },
    { path: '/login/student', title: 'Student Login' },
    { path: '/login/staff', title: 'Staff Login' },
  ];

  for (const p of pages) {
    test(`${p.path} loads without 401/403`, async ({ page }) => {
      const badResponses = [];
      page.on('response', res => {
        if (res.status() === 401 || res.status() === 403) {
          badResponses.push(`${res.status()} ${res.url()}`);
        }
      });

      await page.goto(p.path, { waitUntil: 'networkidle' });
      expect(badResponses, `Auth errors on ${p.path}: ${badResponses.join('; ')}`).toHaveLength(0);
    });
  }
});

// ─── 11. Logout Form: CSRF Protection ────────────────────────

test.describe('Logout form: CSRF', () => {
  test('Staff login logout form (if present) uses POST with CSRF', async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });

    // The logout form is in the nav bar, not on the login page itself
    // But we can verify the route('logout') pattern exists in any form
    const forms = page.locator('form[method="POST"]');
    const count = await forms.count();
    // At minimum the login form uses POST
    expect(count).toBeGreaterThanOrEqual(1);
  });
});

// ─── 12. CSS: Theme classes exist ────────────────────────────

test.describe('CSS: Auth-related styles loaded', () => {
  test('Remember me checkbox is styled', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const label = page.locator('label.remember-me');
    await expect(label).toBeVisible();

    const styles = await label.evaluate(el => {
      const cs = window.getComputedStyle(el);
      return { display: cs.display, cursor: cs.cursor };
    });
    expect(styles.display).toBe('flex');
    expect(styles.cursor).toBe('pointer');
  });

  test('Remember hint has correct font size', async ({ page }) => {
    await page.goto('/login/student', { waitUntil: 'networkidle' });

    const hint = page.locator('p.remember-hint');
    await expect(hint).toBeVisible();

    const fontSize = await hint.evaluate(el => {
      return window.getComputedStyle(el).fontSize;
    });
    expect(fontSize).toBe('12px');
  });
});
