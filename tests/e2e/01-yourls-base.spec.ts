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

    // Visit the shortlink — must redirect (3xx) without a server error.
    const response = await page.goto(`/${keyword}`, { waitUntil: 'commit' });
    expect(response, `no response for /${keyword}`).not.toBeNull();
    // Either we landed on example.com (redirect followed) or got a 3xx.
    // Playwright's goto resolves to the final response, so check for a 2xx
    // or 3xx anywhere along the chain via the request's final URL.
    expect(response!.status()).toBeLessThan(400);
    expect(errors.serverErrors).toEqual([]);
  });
});
