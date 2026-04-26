import { chromium, request, FullConfig } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const BASE_URL = process.env.YOURLS_BASE_URL ?? 'http://127.0.0.1:8080';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'admin';

async function waitForYourls() {
  const ctx = await request.newContext({ baseURL: BASE_URL });
  for (let i = 0; i < 90; i++) {
    try {
      const r = await ctx.get('/admin/install.php', { maxRedirects: 0 });
      const status = r.status();
      // 200 / 302 once YOURLS is running. 503 is what YOURLS returns from the
      // installer page before the database tables exist — the install flow
      // we kick off below still works against it.
      if (status === 200 || status === 302 || status === 503) {
        await ctx.dispose();
        return;
      }
    } catch {
      /* still booting */
    }
    await new Promise((res) => setTimeout(res, 2000));
  }
  await ctx.dispose();
  throw new Error(`YOURLS at ${BASE_URL} did not become reachable`);
}

export default async function globalSetup(_config: FullConfig) {
  await waitForYourls();

  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL: BASE_URL });
  const page = await context.newPage();

  // 1. Run YOURLS installer if not already installed.
  await page.goto('/admin/install.php');
  const installButton = page
    .locator('input[type=submit][value*="Install" i], button:has-text("Install YOURLS")')
    .first();
  if (await installButton.isVisible({ timeout: 5000 }).catch(() => false)) {
    await installButton.click();
    await page.waitForLoadState('networkidle');
  }

  // 2. Log in to the admin (form-based session cookie).
  await page.goto('/admin/');
  const usernameInput = page.locator('input[name=username]');
  if (await usernameInput.isVisible({ timeout: 5000 }).catch(() => false)) {
    await usernameInput.fill(ADMIN_USER);
    await page.locator('input[name=password]').fill(ADMIN_PASS);
    // YOURLS' login form has multiple inputs; click the one with value="Login"
    // (or the form's submit button) to avoid hitting any other submit on
    // the page.
    const loginButton = page
      .locator('input[type=submit][value*="Login" i], input[type=submit][name="submit"], button[type=submit]')
      .first();
    await Promise.all([
      page.waitForLoadState('networkidle'),
      loginButton.click(),
    ]);
  }

  // Confirm we are actually logged in. The admin area links to logout.php
  // when a session is active.
  await page.goto('/admin/index.php');
  const loggedIn = await page
    .locator('a[href*="logout"]')
    .first()
    .isVisible({ timeout: 5000 })
    .catch(() => false);
  if (!loggedIn) {
    // Print enough of the post-login admin page for someone reading the CI
    // log to see what went wrong (login form still showing, error banner,
    // unexpected redirect, …).
    const url = page.url();
    const title = await page.title().catch(() => '?');
    const bodyExcerpt = (await page.content().catch(() => ''))
      .replace(/\s+/g, ' ')
      .slice(0, 6000);
    throw new Error(
      `Admin login failed — no logout link visible after sign-in.\n` +
      `URL after login: ${url}\nTitle: ${title}\nFirst 1500 chars of body:\n${bodyExcerpt}`
    );
  }

  // 3. Activate the plugin if not already active. YOURLS plugins.php exposes
  //    activate / deactivate links keyed by plugin directory name.
  await page.goto('/admin/plugins.php');
  const activateLink = page.locator(
    'a[href*="action=activate"][href*="plugin=Link-Front-Page"]'
  );
  if (await activateLink.first().isVisible({ timeout: 5000 }).catch(() => false)) {
    await activateLink.first().click();
    await page.waitForLoadState('networkidle');
  }

  // Verify activation succeeded — the deactivate link should now be present.
  const deactivateLink = page.locator(
    'a[href*="action=deactivate"][href*="plugin=Link-Front-Page"]'
  );
  if (!(await deactivateLink.first().isVisible({ timeout: 5000 }).catch(() => false))) {
    throw new Error(
      'Plugin "Link-Front-Page" did not activate — check that the plugin folder is mounted at user/plugins/Link-Front-Page'
    );
  }

  // Persist the authenticated state so individual specs do not have to repeat
  // the install / login dance.
  await mkdir('.auth', { recursive: true });
  await context.storageState({ path: '.auth/admin.json' });
  await browser.close();
}
