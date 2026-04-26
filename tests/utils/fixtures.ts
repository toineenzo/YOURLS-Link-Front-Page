import { test as base, expect, Page, Response } from '@playwright/test';

export type ErrorLog = {
  consoleErrors: string[];
  pageErrors: string[];
  serverErrors: string[];
};

// Patterns that almost always indicate a real, plugin-induced PHP failure.
// Deprecation / strict-warning noise from YOURLS or PHP itself is intentionally
// excluded so upstream churn doesn't break this suite.
const PHP_ERROR_PATTERNS: RegExp[] = [
  /<b>\s*Fatal error\s*<\/b>/i,
  /<b>\s*Parse error\s*<\/b>/i,
  /Uncaught\s+(?:Error|TypeError|ValueError|ArgumentCountError|ArgumentError)/,
  /Stack trace:/i,
];

// Narrower patterns: only flag a Warning / Notice when it points back at this
// plugin's directory, since those are something the plugin author can fix.
const PLUGIN_ONLY_ERROR_PATTERNS: RegExp[] = [
  /<b>\s*Warning\s*<\/b>:[^<]*Link-Front-Page/i,
  /<b>\s*Notice\s*<\/b>:[^<]*Link-Front-Page/i,
];

// Console messages YOURLS itself emits that we don't want to flag — typically
// favicon 404s on the admin pages or transient resource warnings out of our
// control. Add to this list if a noisy benign message shows up.
const CONSOLE_IGNORE: RegExp[] = [
  /favicon\.ico/i,
  /Failed to load resource: the server responded with a status of 404/i,
];

async function snapshotPhpErrors(page: Page, sink: string[]) {
  try {
    const html = await page.content();
    const all = [...PHP_ERROR_PATTERNS, ...PLUGIN_ONLY_ERROR_PATTERNS];
    for (const pattern of all) {
      const m = html.match(pattern);
      if (m) {
        const idx = html.indexOf(m[0]);
        const excerpt = html
          .substring(Math.max(0, idx - 80), Math.min(html.length, idx + 240))
          .replace(/\s+/g, ' ')
          .trim();
        sink.push(`PHP error matched ${pattern} on ${page.url()}: …${excerpt}…`);
      }
    }
  } catch {
    /* page may have navigated away */
  }
}

export const test = base.extend<{ errors: ErrorLog }>({
  errors: async ({ page }, use) => {
    const errors: ErrorLog = {
      consoleErrors: [],
      pageErrors: [],
      serverErrors: [],
    };

    page.on('console', (msg) => {
      if (msg.type() !== 'error') return;
      const text = msg.text();
      if (CONSOLE_IGNORE.some((re) => re.test(text))) return;
      errors.consoleErrors.push(text);
    });

    page.on('pageerror', (err) => {
      errors.pageErrors.push(`${err.name}: ${err.message}`);
    });

    page.on('response', (response: Response) => {
      const status = response.status();
      const url = response.url();
      if (status >= 500 && !/\.(?:png|jpg|jpeg|gif|svg|ico)$/i.test(url)) {
        errors.serverErrors.push(`HTTP ${status} ${url}`);
      }
    });

    page.on('framenavigated', async (frame) => {
      if (frame !== page.mainFrame()) return;
      await snapshotPhpErrors(page, errors.serverErrors);
    });

    await use(errors);

    // Re-scan the final page state so errors triggered by the last action are
    // still caught even without a navigation.
    await snapshotPhpErrors(page, errors.serverErrors);

    expect.soft(errors.serverErrors, 'YOURLS / PHP errors').toEqual([]);
    expect.soft(errors.pageErrors, 'Uncaught JavaScript errors').toEqual([]);
    expect.soft(errors.consoleErrors, 'Browser console errors').toEqual([]);
  },
});

export { expect };

/**
 * Pre-create a YOURLS shortlink via the standard admin form at
 * /admin/index.php. The form is JS-driven (AJAX add) so we wait for the new
 * row to appear in the link table. The `title` parameter is honoured only
 * when the running YOURLS build exposes a title input — 1.10.2 doesn't, the
 * server scrapes the destination's <title> instead.
 */
export async function createYourlsShortlink(
  page: Page,
  opts: { url: string; keyword: string; title?: string }
) {
  await page.goto('/admin/index.php');
  await page.locator('#add-url').waitFor({ state: 'visible' });

  await page.locator('#add-url').fill(opts.url);
  await page.locator('#add-keyword').fill(opts.keyword);
  if (opts.title !== undefined) {
    const titleField = page.locator('#add-title');
    if (await titleField.count()) {
      await titleField.fill(opts.title);
    }
  }

  await page.locator('#add-button').click();

  // YOURLS' add_link() AJAX scrapes the destination URL for a <title> on the
  // server before responding, which takes ~10 s on a fresh DNS lookup. The
  // record itself is committed to the database almost immediately though — so
  // rather than waiting on the in-place row insert, give the scrape a head
  // start and then reload, where the rendered table reflects the saved row.
  await page.waitForTimeout(2000);
  await page.goto('/admin/index.php');
  await page
    .locator(`#main_table tr:has-text("${opts.keyword}")`)
    .first()
    .waitFor({ state: 'visible', timeout: 30_000 });
}
