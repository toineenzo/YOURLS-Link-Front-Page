import { test, expect } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=lfp';

test.describe('Plugin admin page loads cleanly', () => {
  test('settings page renders all four tabs', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);

    await expect(page.locator('#lfp-form')).toBeVisible();
    await expect(page.locator('button[data-tab="links"]')).toBeVisible();
    await expect(page.locator('button[data-tab="image_grid"]')).toBeVisible();
    await expect(page.locator('button[data-tab="general"]')).toBeVisible();
    await expect(page.locator('button[data-tab="appearance"]')).toBeVisible();

    expect(errors.serverErrors).toEqual([]);
  });

  test('switching tabs does not trigger JS errors', async ({ page, errors }) => {
    await page.goto(ADMIN_PATH);

    for (const tab of ['general', 'appearance', 'image_grid', 'links']) {
      await page.locator(`button[data-tab="${tab}"]`).click();
      await expect(page.locator(`section[data-pane="${tab}"]`)).toHaveClass(
        /is-active/
      );
    }

    expect(errors.pageErrors).toEqual([]);
    expect(errors.consoleErrors).toEqual([]);
  });
});
