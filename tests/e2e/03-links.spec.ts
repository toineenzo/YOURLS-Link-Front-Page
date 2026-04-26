import { test, expect, createYourlsShortlink } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=lfp';

// 1×1 transparent PNG used as the file payload for image-upload assertions.
const PIXEL_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgAAIAAAUAAarVyFEAAAAASUVORK5CYII=',
  'base64'
);

test.describe.configure({ mode: 'serial' });

test.describe('Adding link items + categories via the plugin', () => {
  let yourlsKeyword: string;
  const stamp = Date.now().toString(36);
  const customUrlMarker = `Custom-${stamp}`;
  const customUrlDescription = `Description for custom link ${stamp}.`;
  const yourlsCustomTitle = `Picked-${stamp}`;
  const categoryTitle = `Category-${stamp}`;
  const categoryDescription = `Grouping for ${stamp}.`;
  const categoryImageUrl = 'https://example.com/cat-banner.png';

  test.beforeAll(async ({ browser }) => {
    yourlsKeyword = `pluginlink${stamp}`;
    const ctx = await browser.newContext({
      baseURL: process.env.YOURLS_BASE_URL ?? 'http://127.0.0.1:8080',
      storageState: '.auth/admin.json',
    });
    const page = await ctx.newPage();
    await createYourlsShortlink(page, {
      url: 'https://example.com/from-yourls',
      keyword: yourlsKeyword,
      title: 'From YOURLS',
    });
    await ctx.close();
  });

  test('custom URL link with title, description and favicon-button image', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="links"]').click();

    await page.locator('#lfp-add-url').click();

    const card = page.locator('#lfp-tree article.lfp-item--link').last();

    // Custom destination + title + description.
    await card
      .locator('[data-lfp-link-url]')
      .fill('https://github.com/toineenzo/YOURLS-Link-Front-Page');
    await card.locator('[data-lfp-title]').fill(customUrlMarker);
    await card.locator('[data-lfp-description]').fill(customUrlDescription);

    // Favicon button auto-fills the image URL with Google's s2/favicons proxy
    // for the destination domain.
    await card.locator('[data-lfp-image-favicon]').click();
    const imageUrl = await card
      .locator('[data-lfp-image-url]')
      .inputValue();
    expect(imageUrl).toContain('s2/favicons');
    expect(imageUrl).toContain('domain=github.com');

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // Public-page rendering: the link card must show the title, description
    // and an <img> sourced from the favicon URL we set via the helper button.
    await page.goto('/');
    const linkCard = page.locator('a.lfp-link', { hasText: customUrlMarker });
    await expect(linkCard).toBeVisible();
    await expect(linkCard.locator('.lfp-link-title')).toContainText(customUrlMarker);
    await expect(linkCard.locator('.lfp-link-desc')).toContainText(
      customUrlDescription
    );
    const renderedImg = linkCard.locator('.lfp-link-image img');
    await expect(renderedImg).toBeVisible();
    expect(await renderedImg.getAttribute('src')).toContain('s2/favicons');

    expect(errors.serverErrors).toEqual([]);
  });

  test('YOURLS shortlink picked from the picker, with uploaded image', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="links"]').click();

    await page.locator('#lfp-add-link').click();

    const picker = page.locator('#lfp-picker');
    await expect(picker).toBeVisible();
    await page.locator('#lfp-picker-q').fill(yourlsKeyword);
    await page
      .locator(`#lfp-picker-list li[data-keyword="${yourlsKeyword}"]`)
      .click();
    await expect(picker).toBeHidden();

    const card = page
      .locator('#lfp-tree article.lfp-item--link')
      .filter({ hasText: yourlsKeyword })
      .last();
    await expect(card).toBeVisible();

    // Override the YOURLS-derived title and upload a PNG to act as the image.
    await card.locator('[data-lfp-title]').fill(yourlsCustomTitle);

    await card.locator('[data-lfp-image-file]').setInputFiles({
      name: 'tile.png',
      mimeType: 'image/png',
      buffer: PIXEL_PNG,
    });

    // The file handler reads the upload as a data URL and writes it back into
    // the URL input. Confirm the dataUrl made it through.
    await expect
      .poll(async () => card.locator('[data-lfp-image-url]').inputValue(), {
        timeout: 5000,
      })
      .toMatch(/^data:image\/png;base64,/);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // After save the backend converts the data URL into an /uploads/ file and
    // re-renders. Check the public page shows our chosen title + an <img>.
    await page.goto('/');
    const linkCard = page.locator('a.lfp-link', { hasText: yourlsCustomTitle });
    await expect(linkCard).toBeVisible();
    await expect(linkCard.locator('.lfp-link-title')).toContainText(
      yourlsCustomTitle
    );
    const renderedImg = linkCard.locator('.lfp-link-image img');
    await expect(renderedImg).toBeVisible();
    const src = await renderedImg.getAttribute('src');
    expect(src).toBeTruthy();
    // Either the saved /uploads/ path or the inline data URL fallback are
    // acceptable — both prove the upload pipeline didn't drop the image.
    expect(src!).toMatch(/uploads\/|^data:image\//);

    expect(errors.serverErrors).toEqual([]);
  });

  test('category with title, description and image', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="links"]').click();

    await page.locator('#lfp-add-category').click();

    const card = page.locator('#lfp-tree article.lfp-item--category').last();
    // Categories are added with their body collapsed — open it.
    if (await card.locator('.lfp-item-body[hidden]').count()) {
      await card.locator('[data-lfp-toggle]').click();
    }

    await card.locator('[data-lfp-title]').fill(categoryTitle);
    await card.locator('[data-lfp-description]').fill(categoryDescription);
    await card.locator('[data-lfp-image-url]').fill(categoryImageUrl);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // Public page renders categories as <article class="lfp-category">.
    await page.goto('/');
    const cat = page.locator('article.lfp-category', { hasText: categoryTitle });
    await expect(cat).toBeVisible();
    await expect(cat.locator('.lfp-category-title')).toContainText(categoryTitle);
    await expect(cat.locator('.lfp-category-desc')).toContainText(
      categoryDescription
    );
    expect(
      await cat.locator('.lfp-category-image img').getAttribute('src')
    ).toBe(categoryImageUrl);

    expect(errors.serverErrors).toEqual([]);
  });
});
