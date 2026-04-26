import { test, expect, createYourlsShortlink } from '../utils/fixtures';

/**
 * Sanity checks that YOURLS itself keeps working with the plugin active.
 * The bug fixed in v2.3.1 (TypeError on every shortlink visit) is exactly the
 * kind of regression these tests are here to catch.
 */

test.describe('YOURLS still works with Link Front Page active', () => {
  test('admin link table loads without errors', async ({ page, errors }) => {
    const response = await page.goto('/admin/index.php');
    expect(response?.status()).toBeLessThan(400);
    await expect(page.locator('#new_url_form')).toBeVisible();
    expect(errors.serverErrors).toEqual([]);
  });

  test('plugin appears as Active on plugins page', async ({ page }) => {
    await page.goto('/admin/plugins.php');
    await expect(
      page.locator('tr.plugin.active', { hasText: 'Link Front Page' })
    ).toBeVisible();
  });

  test('creating a YOURLS shortlink works and the shortlink redirects', async ({
    page,
    errors,
  }) => {
    const keyword = `base${Date.now().toString(36)}`;
    await createYourlsShortlink(page, {
      url: 'https://example.com/yourls-base',
      keyword,
      title: 'YOURLS base test',
    });

    // Use the request API rather than a full navigation so we can inspect
    // the very first response for the keyword (a 3xx redirect to the long
    // URL) without Playwright trying to fetch external example.com.
    const resp = await page.request.get(`/${keyword}`, { maxRedirects: 0 });
    expect(
      resp.status(),
      `GET /${keyword} returned ${resp.status()}: ${(await resp.text()).slice(0, 200)}`
    ).toBeLessThan(400);
    expect(errors.serverErrors).toEqual([]);
  });
});
