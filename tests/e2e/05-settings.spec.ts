import { test, expect } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=lfp';

test.describe.configure({ mode: 'serial' });

test.describe('General + Appearance settings', () => {
  const siteTitle = `LFP Test Site ${Date.now().toString(36)}`;
  const siteDescription = 'Automated test description.';
  const backgroundColor = '#112233';

  test('General tab: change site title + description and persist', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="general"]').click();

    // Make sure the plugin homepage is enabled so we can verify the rendered
    // output afterwards.
    const enable = page.locator('input[name="enabled"]');
    if (!(await enable.isChecked())) {
      await enable.check();
    }

    await page.locator('#lfp-site-title').fill(siteTitle);
    await page.locator('#lfp-site-description').fill(siteDescription);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // Reload the admin and confirm the values stuck.
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="general"]').click();
    await expect(page.locator('#lfp-site-title')).toHaveValue(siteTitle);
    await expect(page.locator('#lfp-site-description')).toHaveValue(siteDescription);

    // Public page should use the title we just set. Parsedown wraps the title
    // in markup, so we match by visible text rather than exact innerHTML.
    await page.goto('/');
    await expect(page.locator('body')).toContainText(siteTitle);

    expect(errors.serverErrors).toEqual([]);
  });

  test('Appearance tab: change background color and persist', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="appearance"]').click();

    await page.locator('#lfp-bg').fill(backgroundColor);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // Reload the admin and verify the colour stuck.
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="appearance"]').click();
    await expect(page.locator('#lfp-bg')).toHaveValue(backgroundColor);

    // Public page should expose the colour via the --lfp-bg CSS variable on
    // either the <html> or <body> element.
    await page.goto('/');
    const cssBg = await page.evaluate(() => {
      const root = document.documentElement;
      const body = document.body;
      return (
        getComputedStyle(root).getPropertyValue('--lfp-bg').trim() ||
        getComputedStyle(body).getPropertyValue('--lfp-bg').trim()
      );
    });
    expect(cssBg.toLowerCase()).toContain(backgroundColor.toLowerCase());

    expect(errors.serverErrors).toEqual([]);
  });
});
