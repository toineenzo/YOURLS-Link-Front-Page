import { readFile } from 'node:fs/promises';
import { test, expect } from '../utils/fixtures';

const ADMIN_PATH = '/admin/plugins.php?page=lfp';

test.describe.configure({ mode: 'serial' });

test.describe('About-me, contact cards, vCard downloads, footer', () => {
  const stamp = Date.now().toString(36);

  const aboutImageUrl = 'https://example.com/me.png';
  const aboutText = `Hi, I'm an automated test ${stamp}.`;

  const personal = {
    name: `Pers Tester ${stamp}`,
    phone: '+31 6 11 22 33 44',
    email: `personal-${stamp}@example.com`,
    website: 'https://example.com/personal',
    address: 'Personal Street 1\n1234 AB Testtown',
  };

  const business = {
    name: `Biz Tester ${stamp}`,
    email: `business-${stamp}@example.com`,
    website: 'https://example.com/business',
  };

  const poweredByText = `Custom Brand ${stamp}`;
  const poweredByUrl = 'https://example.com/brand';
  const footerCustomHtml = `<p data-test-marker="${stamp}">Custom footer note ${stamp}</p>`;

  test('About-me: enable + photo + text rendered on the public page', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="general"]').click();

    const enabled = page.locator('input[name="about_enabled"]');
    if (!(await enabled.isChecked())) {
      await enabled.check();
    }

    // Profile photo input is the first .lfp-image-input within the About-me
    // fieldset; scope to that to avoid hitting site_logo / site_favicon.
    const aboutFieldset = page.locator('fieldset:has(legend:has-text("About me"))');
    await aboutFieldset
      .locator('input[name="about_image"][type="url"]')
      .fill(aboutImageUrl);
    await page.locator('#lfp-about-text').fill(aboutText);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    await page.goto('/');
    const aboutSection = page.locator('section.lfp-about');
    await expect(aboutSection).toBeVisible();
    await expect(aboutSection.locator('.lfp-about-text')).toContainText(
      aboutText
    );
    expect(
      await aboutSection.locator('.lfp-about-photo').getAttribute('src')
    ).toBe(aboutImageUrl);

    expect(errors.serverErrors).toEqual([]);
  });

  test('Personal contact: vCard download + inline rendering', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="general"]').click();

    // Personal tab is active by default — no need to click the contact-tab,
    // but be defensive in case test ordering changes.
    await page.locator('button[data-contact-tab="personal"]').click();

    await page.locator('input[name="about_personal_enabled"]').check();
    await page.locator('input[name="about_personal_show_inline"]').check();
    await page.locator('input[name="about_personal_name"]').fill(personal.name);
    await page.locator('input[name="about_personal_phone"]').fill(personal.phone);
    await page.locator('input[name="about_personal_email"]').fill(personal.email);
    await page
      .locator('input[name="about_personal_website"]')
      .fill(personal.website);
    await page
      .locator('textarea[name="about_personal_address"]')
      .fill(personal.address);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    // ---------- Public-page assertions ----------
    await page.goto('/');

    // Inline contact card.
    const inlineDl = page
      .locator('dl.lfp-contact-info')
      .filter({ hasText: personal.name });
    await expect(inlineDl).toBeVisible();
    await expect(inlineDl).toContainText(personal.phone);
    await expect(inlineDl).toContainText(personal.email);
    // The address textarea preserves line breaks; the HTML uses <br>, so the
    // first line should still be findable as plain text.
    await expect(inlineDl).toContainText('Personal Street 1');

    // Personal vCard button must point at the public endpoint.
    const personalBtn = page.locator(
      'a.lfp-contact-btn[href*="contact.vcf?type=personal"]'
    );
    await expect(personalBtn).toBeVisible();
    expect(await personalBtn.getAttribute('download')).not.toBeNull();

    // Click the button and verify a download starts with .vcf payload.
    const downloadPromise = page.waitForEvent('download', { timeout: 10_000 });
    await personalBtn.click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.vcf$/i);
    const path = await download.path();
    const content = await readFile(path, 'utf-8');
    expect(content).toContain('BEGIN:VCARD');
    expect(content).toContain('END:VCARD');
    expect(content).toContain(personal.name);
    expect(content).toContain(personal.email);

    // Belt-and-braces: the same endpoint should also respond with a vCard
    // content-type for any other client that fetches it directly.
    const apiResp = await page.request.get('/contact.vcf?type=personal');
    expect(apiResp.status()).toBe(200);
    expect(apiResp.headers()['content-type']).toMatch(/text\/vcard/i);
    const apiText = await apiResp.text();
    expect(apiText).toContain('BEGIN:VCARD');
    expect(apiText).toContain(personal.name);

    expect(errors.serverErrors).toEqual([]);
  });

  test('Business contact: vCard download present, inline omitted', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="general"]').click();
    await page.locator('button[data-contact-tab="business"]').click();

    await page.locator('input[name="about_business_enabled"]').check();
    // Make sure show_inline stays OFF for business so we can verify the
    // public page lists ONLY the personal inline card.
    const businessInline = page.locator(
      'input[name="about_business_show_inline"]'
    );
    if (await businessInline.isChecked()) {
      await businessInline.uncheck();
    }
    await page.locator('input[name="about_business_name"]').fill(business.name);
    await page.locator('input[name="about_business_email"]').fill(business.email);
    await page
      .locator('input[name="about_business_website"]')
      .fill(business.website);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    await page.goto('/');

    // Both vCard buttons should now be visible.
    const personalBtn = page.locator(
      'a.lfp-contact-btn[href*="contact.vcf?type=personal"]'
    );
    const businessBtn = page.locator(
      'a.lfp-contact-btn[href*="contact.vcf?type=business"]'
    );
    await expect(personalBtn).toBeVisible();
    await expect(businessBtn).toBeVisible();

    // Business inline card should be absent (we never enabled show_inline
    // for business). The Personal inline card from the previous test should
    // still be the only inline contact block.
    const inlineDls = page.locator('dl.lfp-contact-info');
    await expect(inlineDls).toHaveCount(1);
    await expect(inlineDls.first()).not.toContainText(business.name);

    // Click the business download button and check the payload.
    const downloadPromise = page.waitForEvent('download', { timeout: 10_000 });
    await businessBtn.click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.vcf$/i);
    const content = await readFile(await download.path(), 'utf-8');
    expect(content).toContain('BEGIN:VCARD');
    expect(content).toContain(business.name);
    // Business cards include ORG: as the company line per RFC 6350.
    expect(content).toMatch(/ORG[:;]/);

    // Direct fetch should also work.
    const apiResp = await page.request.get('/contact.vcf?type=business');
    expect(apiResp.status()).toBe(200);
    expect(apiResp.headers()['content-type']).toMatch(/text\/vcard/i);

    expect(errors.serverErrors).toEqual([]);
  });

  test('Footer: login link off, custom Powered-by, custom footer HTML', async ({
    page,
    errors,
  }) => {
    await page.goto(ADMIN_PATH);
    await page.locator('button[data-tab="general"]').click();

    // Hide the login link, keep the Powered-by attribution but customise it.
    const loginLink = page.locator('input[name="show_login_link"]');
    if (await loginLink.isChecked()) {
      await loginLink.uncheck();
    }
    const poweredBy = page.locator('input[name="show_powered_by"]');
    if (!(await poweredBy.isChecked())) {
      await poweredBy.check();
    }
    await page.locator('#lfp-pby-text').fill(poweredByText);
    await page.locator('#lfp-pby-url').fill(poweredByUrl);
    await page.locator('#lfp-footer-html').fill(footerCustomHtml);

    await page.locator('button[type=submit].lfp-btn-primary').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('saved=1');

    await page.goto('/');

    const footer = page.locator('footer.lfp-footer');
    await expect(footer).toBeVisible();

    // Login link must be gone, Powered-by must use our custom text + URL.
    await expect(footer.locator('a[rel="nofollow"]')).toHaveCount(0);
    const poweredAnchor = footer.locator('.lfp-footer-line a').first();
    await expect(poweredAnchor).toContainText(poweredByText);
    expect(await poweredAnchor.getAttribute('href')).toBe(poweredByUrl);

    // Custom footer HTML rendered raw.
    await expect(
      footer.locator(`.lfp-footer-custom [data-test-marker="${stamp}"]`)
    ).toBeVisible();
    await expect(footer.locator('.lfp-footer-custom')).toContainText(
      `Custom footer note ${stamp}`
    );

    expect(errors.serverErrors).toEqual([]);
  });
});
