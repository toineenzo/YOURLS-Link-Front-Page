import { test, expect, createYourlsShortlink } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=lfp';

// 1×1 transparent PNG used as a tile upload payload. Avoids needing real
// fixture files in the repo while still exercising the plugin's upload pipeline.
const PIXEL_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgAAIAAAUAAarVyFEAAAAASUVORK5CYII=',
  'base64'
);

type Tile = {
  index: number;
  source: 'url' | 'keyword';
  url?: string;
  keyword?: string;
  title: string;
  show_mode: 'always' | 'hover' | 'never';
};

test.describe.configure({ mode: 'serial' });

test.describe('Image grid: bulk upload, mixed sources, title visibility modes', () => {
  // Six tiles — three with custom URLs, three with YOURLS shortlinks.
  // Each show_mode (always / hover / never) is exercised on both sides so
  // there is no asymmetry between url-backed and keyword-backed tiles.
  const stamp = Date.now().toString(36);
  const keywords = [`igkw1${stamp}`, `igkw2${stamp}`, `igkw3${stamp}`];

  const tiles: Tile[] = [
    { index: 0, source: 'url',     url: 'https://example.com/url-always', title: 'URL · Always',  show_mode: 'always' },
    { index: 1, source: 'url',     url: 'https://example.com/url-hover',  title: 'URL · Hover',   show_mode: 'hover' },
    { index: 2, source: 'url',     url: 'https://example.com/url-never',  title: 'URL · Never',   show_mode: 'never' },
    { index: 3, source: 'keyword', keyword: keywords[0],                  title: 'KW · Always',   show_mode: 'always' },
    { index: 4, source: 'keyword', keyword: keywords[1],                  title: 'KW · Hover',    show_mode: 'hover' },
    { index: 5, source: 'keyword', keyword: keywords[2],                  title: 'KW · Never',    show_mode: 'never' },
  ];

  test.beforeAll(async ({ browser }) => {
    // Seed the three YOURLS shortlinks the picker will need. Reuse the saved
    // admin storage state so we are authenticated.
    const ctx = await browser.newContext({
      baseURL: process.env.YOURLS_BASE_URL ?? 'http://localhost:8080',
      storageState: '.auth/admin.json',
    });
    const page = await ctx.newPage();
    for (let i = 0; i < keywords.length; i++) {
      await createYourlsShortlink(page, {
        url: `https://example.com/imgrid-target-${i + 1}`,
        keyword: keywords[i],
        title: `Imgrid target ${i + 1}`,
      });
    }
    await ctx.close();
  });

  test('bulk-upload 6 tiles, edit each one, verify on the public page', async ({
    page,
    errors,
  }) => {
    test.setTimeout(180_000);

    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="image_grid"]').click();
    await expect(page.locator('section[data-pane="image_grid"]')).toHaveClass(
      /is-active/
    );

    // Enable the grid + bump the visible count so all six tiles render on the
    // public page without the "Show more" button hiding the back half.
    const enabledToggle = page.locator('input[name="image_grid_enabled"]');
    if (!(await enabledToggle.isChecked())) {
      await enabledToggle.check();
    }
    await page.locator('#lfp-imgrid-visible').fill(String(tiles.length));

    // Bulk-upload all six pixels in one shot.
    await page.locator('#lfp-imgrid-bulk-input').setInputFiles(
      tiles.map((t) => ({
        name: `pixel-${t.index}.png`,
        mimeType: 'image/png',
        buffer: PIXEL_PNG,
      }))
    );

    // Wait until all six tiles have been rendered into the admin grid.
    await expect(page.locator('#lfp-imgrid-grid .lfp-imgrid-tile')).toHaveCount(
      tiles.length,
      { timeout: 15_000 }
    );

    // Edit each tile: set title, source, destination, show_mode.
    for (const tile of tiles) {
      const tileNode = page
        .locator('#lfp-imgrid-grid .lfp-imgrid-tile')
        .nth(tile.index);
      await tileNode.locator('[data-lfp-imgrid-edit]').click();

      const dialog = page.locator('#lfp-imgrid-dialog');
      await expect(dialog).toBeVisible();

      await dialog.locator('#lfp-imgrid-title-input').fill(tile.title);
      await dialog.locator('#lfp-imgrid-show-mode').selectOption(tile.show_mode);
      await dialog.locator('#lfp-imgrid-source').selectOption(tile.source);

      if (tile.source === 'url') {
        await dialog.locator('#lfp-imgrid-url').fill(tile.url!);
      } else {
        // Open the shared YOURLS picker, search for the seeded keyword and
        // select the matching row.
        await dialog.locator('#lfp-imgrid-pick').click();
        const picker = page.locator('#lfp-picker');
        await expect(picker).toBeVisible();
        await picker.locator('#lfp-picker-q').fill(tile.keyword!);
        await picker
          .locator(`#lfp-picker-list li[data-keyword="${tile.keyword}"]`)
          .click();
        await expect(picker).toBeHidden();
        // Confirm the dialog now shows the picked keyword.
        await expect(dialog.locator('#lfp-imgrid-keyword-display')).toHaveText(
          tile.keyword!
        );
      }

      await dialog.locator('#lfp-imgrid-save').click();
      await expect(dialog).toBeHidden();
    }

    // Persist all six tiles via the form's main Save button.
    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // ---------------------------------------------------------------------
    // Public-page assertions.
    // ---------------------------------------------------------------------

    await page.goto('/');
    const publicTiles = page.locator('section.lfp-imgrid a.lfp-imgrid-tile');
    await expect(publicTiles).toHaveCount(tiles.length);

    for (const tile of tiles) {
      const node = publicTiles.nth(tile.index);

      // Each tile must carry the show-mode class corresponding to the choice
      // we made in the editor.
      await expect(node).toHaveClass(
        new RegExp(`\\blfp-imgrid-show-${tile.show_mode}\\b`)
      );

      // Source-dependent href: custom URL passes through verbatim; keyword
      // tiles resolve through yourls_link() to /<keyword> on YOURLS_SITE.
      const href = await node.getAttribute('href');
      expect(href).not.toBeNull();
      if (tile.source === 'url') {
        expect(href).toBe(tile.url!);
      } else {
        expect(href).toContain(`/${tile.keyword}`);
      }

      // Title overlay rendering rules (frontend.php line 306):
      //   show_mode === 'never' → overlay span omitted entirely
      //   otherwise             → overlay span present with the title text
      const overlay = node.locator('.lfp-imgrid-overlay');
      if (tile.show_mode === 'never') {
        await expect(overlay).toHaveCount(0);
      } else {
        await expect(overlay).toHaveCount(1);
        await expect(overlay).toContainText(tile.title);
      }
    }

    // 'always' tiles must already paint the overlay; 'hover' tiles must start
    // transparent and only fade in once the user points at them.
    const alwaysTile = publicTiles.nth(
      tiles.find((t) => t.show_mode === 'always')!.index
    );
    const alwaysOpacity = await alwaysTile
      .locator('.lfp-imgrid-overlay')
      .evaluate((el) => parseFloat(getComputedStyle(el).opacity));
    expect(alwaysOpacity).toBeGreaterThan(0.99);

    const hoverTile = publicTiles.nth(
      tiles.find((t) => t.show_mode === 'hover')!.index
    );
    const hoverOpacityBefore = await hoverTile
      .locator('.lfp-imgrid-overlay')
      .evaluate((el) => parseFloat(getComputedStyle(el).opacity));
    expect(hoverOpacityBefore).toBeLessThan(0.01);

    await hoverTile.hover();
    // CSS transitions out at 200ms — give the browser a beat to settle.
    await expect
      .poll(
        async () =>
          hoverTile
            .locator('.lfp-imgrid-overlay')
            .evaluate((el) => parseFloat(getComputedStyle(el).opacity)),
        { timeout: 2000 }
      )
      .toBeGreaterThan(0.99);

    expect(errors.serverErrors).toEqual([]);
  });
});
