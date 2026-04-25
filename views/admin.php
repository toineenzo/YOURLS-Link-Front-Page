<?php
/**
 * Settings UI rendered inside the YOURLS admin via yourls_register_plugin_page().
 *
 * @var string $notice
 */

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

$general    = lfp_get_general();
$appearance = lfp_get_appearance();
$items      = lfp_get_items();
$instagram  = lfp_get_instagram();
$google_fonts = lfp_get_google_fonts();
$all_links  = lfp_get_all_yourls_links();

$asset_v = '?v=' . LFP_VERSION;

$platforms = lfp_get_social_platforms();
$platform_icons = [];
foreach ($platforms as $key => $p) {
    $platform_icons[$key] = ['name' => $p['name'], 'color' => $p['color'], 'svg' => $p['svg']];
}

$bootstrap = [
    'items'      => $items,
    'socials'    => $general['about_socials'] ?? [],
    'platforms'  => $platform_icons,
    'instagram'  => $instagram['items'] ?? [],
    'googleFonts'=> $google_fonts,
    'allLinks'   => array_map(static fn($row): array => [
        'keyword' => (string) ($row->keyword ?? ''),
        'url'     => (string) ($row->url ?? ''),
        'title'   => (string) ($row->title ?? ''),
    ], $all_links),
];

?>
<link rel="stylesheet" href="<?php echo yourls_esc_attr(lfp_plugin_url('assets/admin.css' . $asset_v)); ?>">

<div class="lfp-pagehead">
    <div>
        <h2>Link Front Page</h2>
        <p>Show selected shortlinks as a Linktree-style list on your YOURLS homepage. Drag &amp; drop to reorder, group links into category boxes, and customize each entry with an image, title and description.</p>
    </div>
    <a class="lfp-btn lfp-btn-ghost" href="<?php echo yourls_esc_attr(YOURLS_SITE . '/'); ?>" target="_blank" rel="noopener">
        View linktree
        <span aria-hidden="true">&nearr;</span>
    </a>
</div>

<?php if ($notice !== ''): ?>
    <div class="lfp-notice"><?php echo yourls_esc_html($notice); ?></div>
<?php endif; ?>

<form id="lfp-form" method="post" enctype="multipart/form-data" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=lfp')); ?>">
    <?php yourls_nonce_field(LFP_NONCE_ACTION); ?>
    <input type="hidden" name="lfp_action" value="save">
    <input type="hidden" name="items_json" id="lfp-items-json" value="">

    <div class="lfp-tabs" role="tablist">
        <button type="button" class="lfp-tab is-active" data-tab="links" role="tab">
            <svg class="lfp-tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
            </svg>
            <span class="lfp-tab-label">Links</span>
        </button>
        <button type="button" class="lfp-tab" data-tab="general" role="tab">
            <svg class="lfp-tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <span class="lfp-tab-label">General</span>
        </button>
        <button type="button" class="lfp-tab" data-tab="instagram" role="tab">
            <svg class="lfp-tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
            </svg>
            <span class="lfp-tab-label">Image grid</span>
        </button>
        <button type="button" class="lfp-tab" data-tab="appearance" role="tab">
            <svg class="lfp-tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="13.5" cy="6.5" r="0.6" fill="currentColor" stroke="none"/>
                <circle cx="17.5" cy="10.5" r="0.6" fill="currentColor" stroke="none"/>
                <circle cx="8.5" cy="7.5" r="0.6" fill="currentColor" stroke="none"/>
                <circle cx="6.5" cy="12.5" r="0.6" fill="currentColor" stroke="none"/>
                <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-5.52-4.48-10-10-10z"/>
            </svg>
            <span class="lfp-tab-label">Appearance</span>
        </button>
    </div>

    <!-- ============================ LINKS TAB ============================ -->
    <section class="lfp-pane is-active" data-pane="links">
        <div class="lfp-toolbar">
            <button type="button" class="lfp-btn" id="lfp-add-link">+ Add link</button>
            <button type="button" class="lfp-btn" id="lfp-add-category">+ Add category</button>
            <span class="lfp-toolbar-hint">Drag the <span class="lfp-handle-demo">&#x2630;</span> handle to reorder. Drop links onto a category to nest them.</span>
        </div>

        <div id="lfp-tree" class="lfp-tree" data-level="0" aria-live="polite"></div>

        <details class="lfp-empty-template" hidden>
            <summary>No items yet</summary>
        </details>
    </section>

    <!-- =========================== GENERAL TAB =========================== -->
    <section class="lfp-pane" data-pane="general">
        <div class="lfp-row">
            <label class="lfp-checkbox">
                <input type="checkbox" name="enabled" value="1" <?php echo $general['enabled'] ? 'checked' : ''; ?>>
                <span>Enable Link Front Page (replaces the YOURLS homepage)</span>
            </label>
        </div>

        <div class="lfp-grid">
            <div class="lfp-field">
                <label for="lfp-site-title">Site title</label>
                <input type="text" id="lfp-site-title" name="site_title" value="<?php echo yourls_esc_attr($general['site_title']); ?>" placeholder="My Links">
                <small>Shown at the top of the page and in the browser tab.</small>
            </div>
            <div class="lfp-field">
                <label for="lfp-login-path">Login path</label>
                <div class="lfp-prefix-input">
                    <span><?php echo yourls_esc_html(trim(YOURLS_SITE, '/')); ?>/</span>
                    <input type="text" id="lfp-login-path" name="login_path" value="<?php echo yourls_esc_attr($general['login_path']); ?>" placeholder="login">
                </div>
                <small>Visiting this path redirects to the YOURLS admin.</small>
            </div>
        </div>

        <div class="lfp-field">
            <label for="lfp-site-description">Tagline / description</label>
            <textarea id="lfp-site-description" name="site_description" rows="2" placeholder="A short subtitle below the page title."><?php echo yourls_esc_html($general['site_description']); ?></textarea>
        </div>

        <div class="lfp-field">
            <label>Logo / avatar</label>
            <div class="lfp-image-input">
                <input type="url" name="site_logo" value="<?php echo yourls_esc_attr($general['site_logo']); ?>" placeholder="https://example.com/avatar.png" data-lfp-image-url>
                <input type="file" name="site_logo" accept="image/*" data-lfp-image-file>
                <?php if ($general['site_logo'] !== ''): ?>
                    <img class="lfp-thumb" src="<?php echo yourls_esc_url($general['site_logo']); ?>" alt="">
                <?php endif; ?>
            </div>
            <small>URL or upload an image. Uploaded files are saved under <code>user/plugins/&lt;plugin&gt;/uploads/</code>.</small>
        </div>

        <fieldset class="lfp-fieldset">
            <legend>About me</legend>

            <div class="lfp-row">
                <label class="lfp-checkbox">
                    <input type="checkbox" name="about_enabled" value="1" <?php echo !empty($general['about_enabled']) ? 'checked' : ''; ?>>
                    <span>Show an "About me" section above the link list</span>
                </label>
            </div>

            <div class="lfp-grid">
                <div class="lfp-field">
                    <label>Profile photo</label>
                    <div class="lfp-image-input">
                        <input type="url" name="about_image" value="<?php echo yourls_esc_attr($general['about_image']); ?>" placeholder="https://example.com/me.jpg" data-lfp-image-url>
                        <input type="file" name="about_image" accept="image/*" data-lfp-image-file>
                        <?php if ($general['about_image'] !== ''): ?>
                            <img class="lfp-thumb" src="<?php echo yourls_esc_url($general['about_image']); ?>" alt="">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="lfp-field">
                    <label for="lfp-about-text">About text</label>
                    <textarea id="lfp-about-text" name="about_text" rows="4" placeholder="A short bio."><?php echo yourls_esc_html($general['about_text']); ?></textarea>
                </div>
            </div>

            <div class="lfp-field">
                <label>Social media buttons</label>
                <small>Icon-only buttons rendered next to your profile photo. Each button can either point to a free-form URL or be tied to one of your existing YOURLS shortlinks.</small>
                <div class="lfp-toolbar lfp-toolbar-tight">
                    <button type="button" class="lfp-btn" id="lfp-add-social">+ Add social button</button>
                </div>
                <div id="lfp-socials" class="lfp-socials" aria-live="polite"></div>
                <input type="hidden" name="about_socials_json" id="lfp-socials-json" value="">
            </div>
        </fieldset>

        <fieldset class="lfp-fieldset">
            <legend>Footer</legend>

            <div class="lfp-row">
                <label class="lfp-checkbox">
                    <input type="checkbox" name="show_login_link" value="1" <?php echo !empty($general['show_login_link']) ? 'checked' : ''; ?>>
                    <span>Show login link (links to <code><?php echo yourls_esc_html(trim((string) $general['login_path'], '/')); ?></code>)</span>
                </label>
            </div>

            <div class="lfp-row">
                <label class="lfp-checkbox">
                    <input type="checkbox" name="show_powered_by" value="1" <?php echo !empty($general['show_powered_by']) ? 'checked' : ''; ?>>
                    <span>Show "Powered by" attribution</span>
                </label>
            </div>

            <div class="lfp-grid">
                <div class="lfp-field">
                    <label for="lfp-pby-text">Attribution text</label>
                    <input type="text" id="lfp-pby-text" name="powered_by_text" value="<?php echo yourls_esc_attr($general['powered_by_text']); ?>" placeholder="YOURLS">
                    <small>Shown after "Powered by". Leave empty to default to <em>YOURLS</em>.</small>
                </div>
                <div class="lfp-field">
                    <label for="lfp-pby-url">Attribution URL</label>
                    <input type="url" id="lfp-pby-url" name="powered_by_url" value="<?php echo yourls_esc_attr($general['powered_by_url']); ?>" placeholder="https://yourls.org">
                    <small>Where the attribution links to. Leave empty for <em>https://yourls.org</em>.</small>
                </div>
            </div>

            <div class="lfp-field">
                <label for="lfp-footer-html">Custom footer HTML</label>
                <textarea id="lfp-footer-html" name="footer_custom_html" rows="4" class="lfp-mono" spellcheck="false" placeholder="&lt;p&gt;&copy; 2026 Toine Rademacher&lt;/p&gt;"><?php echo yourls_esc_html($general['footer_custom_html']); ?></textarea>
                <small>Appended to the footer on the public page. <strong>HTML is rendered as-is</strong>; no sanitization, so don't paste anything you don't trust.</small>
            </div>
        </fieldset>
    </section>

    <!-- ========================= APPEARANCE TAB ========================== -->
    <section class="lfp-pane" data-pane="appearance">

        <fieldset class="lfp-fieldset">
            <legend>Colors</legend>
            <div class="lfp-grid lfp-grid-3">
                <div class="lfp-field">
                    <label for="lfp-bg">Background</label>
                    <input type="color" id="lfp-bg" name="background_color" value="<?php echo yourls_esc_attr($appearance['background_color']); ?>">
                </div>
                <div class="lfp-field">
                    <label for="lfp-fg">Text</label>
                    <input type="color" id="lfp-fg" name="text_color" value="<?php echo yourls_esc_attr($appearance['text_color']); ?>">
                </div>
                <div class="lfp-field">
                    <label for="lfp-muted">Muted text</label>
                    <input type="color" id="lfp-muted" name="muted_color" value="<?php echo yourls_esc_attr($appearance['muted_color']); ?>">
                </div>
                <div class="lfp-field">
                    <label for="lfp-card">Card</label>
                    <input type="color" id="lfp-card" name="card_background" value="<?php echo yourls_esc_attr($appearance['card_background']); ?>">
                </div>
                <div class="lfp-field">
                    <label for="lfp-cardhover">Card hover</label>
                    <input type="color" id="lfp-cardhover" name="card_hover" value="<?php echo yourls_esc_attr($appearance['card_hover']); ?>">
                </div>
                <div class="lfp-field">
                    <label for="lfp-accent">Accent</label>
                    <input type="color" id="lfp-accent" name="accent_color" value="<?php echo yourls_esc_attr($appearance['accent_color']); ?>">
                </div>
            </div>
            <div class="lfp-field">
                <label>Background image (optional)</label>
                <div class="lfp-image-input">
                    <input type="url" name="background_image" value="<?php echo yourls_esc_attr($appearance['background_image']); ?>" placeholder="https://example.com/bg.jpg" data-lfp-image-url>
                    <input type="file" name="background_image" accept="image/*" data-lfp-image-file>
                    <?php if (!empty($appearance['background_image'])): ?>
                        <img class="lfp-thumb" src="<?php echo yourls_esc_url($appearance['background_image']); ?>" alt="">
                    <?php endif; ?>
                </div>
            </div>

            <div class="lfp-grid lfp-grid-2">
                <div class="lfp-field">
                    <label for="lfp-bg-size">Size</label>
                    <select name="background_size" id="lfp-bg-size">
                        <option value="cover"      <?php echo $appearance['background_size']==='cover'?'selected':''; ?>>Cover (fill, may crop)</option>
                        <option value="contain"    <?php echo $appearance['background_size']==='contain'?'selected':''; ?>>Contain (fit inside)</option>
                        <option value="auto"       <?php echo $appearance['background_size']==='auto'?'selected':''; ?>>Auto (original size)</option>
                        <option value="100% 100%"  <?php echo $appearance['background_size']==='100% 100%'?'selected':''; ?>>Stretch (100% &times; 100%)</option>
                    </select>
                </div>
                <div class="lfp-field">
                    <label for="lfp-bg-repeat">Repeat</label>
                    <select name="background_repeat" id="lfp-bg-repeat">
                        <option value="no-repeat" <?php echo $appearance['background_repeat']==='no-repeat'?'selected':''; ?>>No repeat</option>
                        <option value="repeat"    <?php echo $appearance['background_repeat']==='repeat'?'selected':''; ?>>Repeat both</option>
                        <option value="repeat-x"  <?php echo $appearance['background_repeat']==='repeat-x'?'selected':''; ?>>Repeat horizontally</option>
                        <option value="repeat-y"  <?php echo $appearance['background_repeat']==='repeat-y'?'selected':''; ?>>Repeat vertically</option>
                        <option value="space"     <?php echo $appearance['background_repeat']==='space'?'selected':''; ?>>Space (no clipping)</option>
                        <option value="round"     <?php echo $appearance['background_repeat']==='round'?'selected':''; ?>>Round (rescale to fit)</option>
                    </select>
                </div>
                <div class="lfp-field">
                    <label for="lfp-bg-pos">Position</label>
                    <select name="background_position" id="lfp-bg-pos">
                        <option value="center"       <?php echo $appearance['background_position']==='center'?'selected':''; ?>>Center</option>
                        <option value="top"          <?php echo $appearance['background_position']==='top'?'selected':''; ?>>Top</option>
                        <option value="bottom"       <?php echo $appearance['background_position']==='bottom'?'selected':''; ?>>Bottom</option>
                        <option value="left"         <?php echo $appearance['background_position']==='left'?'selected':''; ?>>Left</option>
                        <option value="right"        <?php echo $appearance['background_position']==='right'?'selected':''; ?>>Right</option>
                        <option value="top left"     <?php echo $appearance['background_position']==='top left'?'selected':''; ?>>Top-left</option>
                        <option value="top right"    <?php echo $appearance['background_position']==='top right'?'selected':''; ?>>Top-right</option>
                        <option value="bottom left"  <?php echo $appearance['background_position']==='bottom left'?'selected':''; ?>>Bottom-left</option>
                        <option value="bottom right" <?php echo $appearance['background_position']==='bottom right'?'selected':''; ?>>Bottom-right</option>
                    </select>
                </div>
                <div class="lfp-field">
                    <label for="lfp-bg-attach">Attachment</label>
                    <select name="background_attachment" id="lfp-bg-attach">
                        <option value="fixed"  <?php echo $appearance['background_attachment']==='fixed'?'selected':''; ?>>Fixed (parallax)</option>
                        <option value="scroll" <?php echo $appearance['background_attachment']==='scroll'?'selected':''; ?>>Scroll with page</option>
                        <option value="local"  <?php echo $appearance['background_attachment']==='local'?'selected':''; ?>>Local (scroll inside element)</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="lfp-fieldset">
            <legend>Spacing &amp; sizing</legend>
            <div class="lfp-grid lfp-grid-3">
                <div class="lfp-field">
                    <label for="lfp-radius">Border radius</label>
                    <input type="text" id="lfp-radius" name="border_radius" value="<?php echo yourls_esc_attr($appearance['border_radius']); ?>" placeholder="16px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-pmw">Page max width</label>
                    <input type="text" id="lfp-pmw" name="page_max_width" value="<?php echo yourls_esc_attr($appearance['page_max_width']); ?>" placeholder="640px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-pad-x">Page side padding</label>
                    <input type="text" id="lfp-pad-x" name="page_padding_x" value="<?php echo yourls_esc_attr($appearance['page_padding_x']); ?>" placeholder="20px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-pad-top">Page top padding</label>
                    <input type="text" id="lfp-pad-top" name="page_padding_top" value="<?php echo yourls_esc_attr($appearance['page_padding_top']); ?>" placeholder="56px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-pad-bot">Page bottom padding</label>
                    <input type="text" id="lfp-pad-bot" name="page_padding_bottom" value="<?php echo yourls_esc_attr($appearance['page_padding_bottom']); ?>" placeholder="80px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-cardgap">Gap between cards</label>
                    <input type="text" id="lfp-cardgap" name="card_gap" value="<?php echo yourls_esc_attr($appearance['card_gap']); ?>" placeholder="14px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-cardpy">Card padding Y</label>
                    <input type="text" id="lfp-cardpy" name="card_padding_y" value="<?php echo yourls_esc_attr($appearance['card_padding_y']); ?>" placeholder="14px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-cardpx">Card padding X</label>
                    <input type="text" id="lfp-cardpx" name="card_padding_x" value="<?php echo yourls_esc_attr($appearance['card_padding_x']); ?>" placeholder="18px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-iconsz">Link icon size</label>
                    <input type="text" id="lfp-iconsz" name="icon_size" value="<?php echo yourls_esc_attr($appearance['icon_size']); ?>" placeholder="44px">
                </div>
                <div class="lfp-field">
                    <label for="lfp-photosz">About photo size</label>
                    <input type="text" id="lfp-photosz" name="about_photo_size" value="<?php echo yourls_esc_attr($appearance['about_photo_size']); ?>" placeholder="120px">
                </div>
            </div>
            <small class="lfp-hint">Same units as the typography fields: <code>px</code>, <code>%</code>, <code>em</code>, <code>rem</code>, <code>vh</code>, <code>vw</code>, plus <code>clamp()</code>, <code>calc()</code>, <code>min()</code>, <code>max()</code>. Bare numbers become <code>px</code>.</small>
        </fieldset>

        <fieldset class="lfp-fieldset">
            <legend>Typography</legend>

            <div class="lfp-grid">
                <div class="lfp-field">
                    <label>Font source</label>
                    <select name="font_source" id="lfp-font-source">
                        <option value="system" <?php echo $appearance['font_source'] === 'system' ? 'selected' : ''; ?>>System default (CSS font-family stack)</option>
                        <option value="google" <?php echo $appearance['font_source'] === 'google' ? 'selected' : ''; ?>>Google Fonts</option>
                        <option value="custom" <?php echo $appearance['font_source'] === 'custom' ? 'selected' : ''; ?>>Custom upload</option>
                    </select>
                </div>
                <div class="lfp-field">
                    <label>Weights to load (Google)</label>
                    <input type="text" name="font_google_weights" value="<?php echo yourls_esc_attr($appearance['font_google_weights']); ?>" placeholder="400;600;700">
                    <small>Semicolon-separated list. Used only when font source is Google Fonts.</small>
                </div>
            </div>

            <div class="lfp-field" data-lfp-fontblock="system">
                <label for="lfp-font">Font family stack</label>
                <input type="text" id="lfp-font" name="font_family" value="<?php echo yourls_esc_attr($appearance['font_family']); ?>" placeholder="system-ui, -apple-system, sans-serif">
                <small>Standard CSS <code>font-family</code> value.</small>
            </div>

            <div class="lfp-field" data-lfp-fontblock="google">
                <label for="lfp-font-google">Google Font</label>
                <div class="lfp-font-picker">
                    <input type="text" id="lfp-font-search" placeholder="Search Google Fonts…" autocomplete="off">
                    <select id="lfp-font-google" name="font_google" size="6">
                        <?php foreach ($google_fonts as $f): ?>
                            <option value="<?php echo yourls_esc_attr($f['family']); ?>"
                                    data-category="<?php echo yourls_esc_attr($f['category']); ?>"
                                    <?php echo $appearance['font_google'] === $f['family'] ? 'selected' : ''; ?>>
                                <?php echo yourls_esc_html($f['family']); ?>
                                <?php echo ' (' . yourls_esc_html($f['category']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="lfp-font-preview" id="lfp-font-preview" aria-live="polite">
                        <div class="lfp-font-preview-row" style="font-size:1.6rem;font-weight:700">The quick brown fox</div>
                        <div class="lfp-font-preview-row" style="font-size:1rem">jumps over the lazy dog. 0123456789</div>
                        <div class="lfp-font-preview-row" style="font-size:0.85rem;opacity:0.7">ABCDEFGHIJKLMNOPQRSTUVWXYZ &mdash; abcdefghijklmnopqrstuvwxyz</div>
                    </div>
                </div>
                <small>Loaded from <code>fonts.googleapis.com</code> when the page is shown. Live preview updates the moment you select a font.</small>
            </div>

            <div class="lfp-field" data-lfp-fontblock="custom">
                <label>Custom font file</label>
                <div class="lfp-image-input">
                    <input type="file" name="font_custom_file" accept=".woff2,.woff,.ttf,.otf">
                    <?php if (!empty($appearance['font_custom_url'])): ?>
                        <small>Currently using: <code><?php echo yourls_esc_html(basename(parse_url($appearance['font_custom_url'], PHP_URL_PATH) ?: $appearance['font_custom_url'])); ?></code> (<?php echo yourls_esc_html($appearance['font_custom_format']); ?>)</small>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="font_custom_url"    value="<?php echo yourls_esc_attr($appearance['font_custom_url']); ?>">
                <input type="hidden" name="font_custom_format" value="<?php echo yourls_esc_attr($appearance['font_custom_format']); ?>">
                <small>Accepts <code>.woff2</code>, <code>.woff</code>, <code>.ttf</code>, <code>.otf</code> (max 5&nbsp;MB).</small>
            </div>

            <div class="lfp-grid lfp-grid-3">
                <div class="lfp-field">
                    <label for="lfp-tsize">Site title size</label>
                    <input type="text" id="lfp-tsize" name="title_size" value="<?php echo yourls_esc_attr($appearance['title_size']); ?>" placeholder="1.75rem">
                </div>
                <div class="lfp-field">
                    <label for="lfp-ssize">Subtitle size</label>
                    <input type="text" id="lfp-ssize" name="subtitle_size" value="<?php echo yourls_esc_attr($appearance['subtitle_size']); ?>" placeholder="1rem">
                </div>
                <div class="lfp-field">
                    <label for="lfp-csize">Category title size</label>
                    <input type="text" id="lfp-csize" name="category_title_size" value="<?php echo yourls_esc_attr($appearance['category_title_size']); ?>" placeholder="1.15rem">
                </div>
                <div class="lfp-field">
                    <label for="lfp-lsize">Link title size</label>
                    <input type="text" id="lfp-lsize" name="link_title_size" value="<?php echo yourls_esc_attr($appearance['link_title_size']); ?>" placeholder="1rem">
                </div>
                <div class="lfp-field">
                    <label for="lfp-bsize">Body / description</label>
                    <input type="text" id="lfp-bsize" name="body_size" value="<?php echo yourls_esc_attr($appearance['body_size']); ?>" placeholder="0.95rem">
                </div>
            </div>
            <small class="lfp-hint">All size fields accept <code>px</code>, <code>%</code>, <code>em</code>, <code>rem</code>, <code>vh</code>, <code>vw</code>, and CSS functions like <code>clamp(1rem, 4vw, 2rem)</code> or <code>calc(1rem + 2vw)</code>.</small>
        </fieldset>

        <fieldset class="lfp-fieldset">
            <legend>Custom CSS</legend>
            <div class="lfp-field">
                <textarea id="lfp-customcss" name="custom_css" rows="6" class="lfp-mono" spellcheck="false"><?php echo yourls_esc_html($appearance['custom_css']); ?></textarea>
                <small>Inserted at the bottom of the inline style block on the public page.</small>
            </div>
        </fieldset>
    </section>

    <!-- ========================= IMAGE GRID TAB ========================== -->
    <section class="lfp-pane" data-pane="instagram">
        <p>A 3-column gallery of clickable images — perfect for mirroring your Instagram, Pinterest, TikTok, or any social feed where you say "link in bio". Each tile points to the URL or YOURLS shortlink that the matching post promotes.</p>

        <div class="lfp-row">
            <label class="lfp-checkbox">
                <input type="checkbox" name="instagram_enabled" value="1" <?php echo !empty($instagram['enabled']) ? 'checked' : ''; ?>>
                <span>Show the image grid above the link list</span>
            </label>
        </div>

        <div class="lfp-toolbar lfp-toolbar-tight">
            <button type="button" class="lfp-btn" id="lfp-ig-bulk">&#x2B73; Bulk upload images</button>
            <input type="file" id="lfp-ig-bulk-input" accept="image/*" multiple hidden>
            <span class="lfp-toolbar-hint">Pick multiple files at once. Each image becomes a tile; fill in URL / title later by clicking the tile.</span>
        </div>

        <div id="lfp-ig-grid" class="lfp-ig-grid"></div>
        <input type="hidden" name="instagram_json" id="lfp-ig-json" value="">
    </section>

    <div class="lfp-actions">
        <button type="submit" class="lfp-btn lfp-btn-primary">Save settings</button>
        <button type="button" class="lfp-btn lfp-btn-danger" id="lfp-reset">Reset to defaults</button>
    </div>
</form>

<footer class="lfp-pluginfoot">
    <span class="lfp-pluginfoot-name">Link Front Page <strong>v<?php echo yourls_esc_html(LFP_VERSION); ?></strong></span>
    <span class="lfp-pluginfoot-sep" aria-hidden="true">&middot;</span>
    <span class="lfp-pluginfoot-credit">Made by <a href="https://toine.click" target="_blank" rel="noopener">Toine Rademacher (toineenzo)</a></span>
</footer>

<!-- ====================== ITEM TEMPLATES & PICKER ===================== -->

<template id="lfp-tpl-link">
    <article class="lfp-item lfp-item--link" data-type="link" draggable="true">
        <div class="lfp-item-head">
            <span class="lfp-handle" title="Drag to reorder">&#x2630;</span>
            <span class="lfp-item-thumb" data-lfp-thumb></span>
            <div class="lfp-item-meta">
                <span class="lfp-item-keyword" data-lfp-keyword></span>
                <span class="lfp-item-fallback" data-lfp-fallback></span>
            </div>
            <button type="button" class="lfp-icon-btn" data-lfp-toggle title="Edit">&#9881;</button>
            <button type="button" class="lfp-icon-btn lfp-icon-danger" data-lfp-remove title="Remove">&times;</button>
        </div>
        <div class="lfp-item-body" hidden>
            <div class="lfp-grid">
                <div class="lfp-field">
                    <label>Custom title <small>(optional &mdash; falls back to YOURLS link title)</small></label>
                    <input type="text" data-lfp-title>
                </div>
                <div class="lfp-field">
                    <label>Image</label>
                    <div class="lfp-image-input">
                        <input type="url" placeholder="https://..." data-lfp-image-url>
                        <input type="file" accept="image/*" data-lfp-image-file>
                    </div>
                </div>
            </div>
            <div class="lfp-field">
                <label>Description</label>
                <textarea rows="2" data-lfp-description></textarea>
            </div>
        </div>
    </article>
</template>

<template id="lfp-tpl-category">
    <article class="lfp-item lfp-item--category" data-type="category" draggable="true">
        <div class="lfp-item-head">
            <span class="lfp-handle" title="Drag to reorder">&#x2630;</span>
            <span class="lfp-item-thumb" data-lfp-thumb></span>
            <div class="lfp-item-meta">
                <strong class="lfp-item-cat-title" data-lfp-display-title>Category</strong>
                <span class="lfp-item-cat-count" data-lfp-count></span>
            </div>
            <button type="button" class="lfp-icon-btn" data-lfp-toggle title="Edit">&#9881;</button>
            <button type="button" class="lfp-icon-btn lfp-icon-danger" data-lfp-remove title="Remove">&times;</button>
        </div>
        <div class="lfp-item-body" hidden>
            <div class="lfp-grid">
                <div class="lfp-field">
                    <label>Title</label>
                    <input type="text" data-lfp-title placeholder="My Category">
                </div>
                <div class="lfp-field">
                    <label>Image</label>
                    <div class="lfp-image-input">
                        <input type="url" placeholder="https://..." data-lfp-image-url>
                        <input type="file" accept="image/*" data-lfp-image-file>
                    </div>
                </div>
            </div>
            <div class="lfp-field">
                <label>Description</label>
                <textarea rows="2" data-lfp-description></textarea>
            </div>
        </div>
        <div class="lfp-tree lfp-tree-children" data-level="1" data-lfp-children></div>
    </article>
</template>

<template id="lfp-tpl-social">
    <div class="lfp-social-row" draggable="true">
        <span class="lfp-handle" title="Drag to reorder">&#x2630;</span>
        <span class="lfp-social-icon" data-lfp-social-icon></span>
        <select class="lfp-social-platform" data-lfp-social-platform>
            <?php foreach (lfp_get_social_platforms() as $key => $platform): ?>
                <option value="<?php echo yourls_esc_attr($key); ?>"><?php echo yourls_esc_html($platform['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="lfp-social-source" data-lfp-social-source>
            <option value="url">URL</option>
            <option value="keyword">YOURLS keyword</option>
        </select>
        <input type="url" class="lfp-social-url" data-lfp-social-url placeholder="https://...">
        <span class="lfp-social-keyword-wrap" data-lfp-social-keyword-wrap hidden>
            <code class="lfp-social-keyword" data-lfp-social-keyword>—</code>
            <button type="button" class="lfp-btn lfp-btn-tight" data-lfp-social-pick>Pick…</button>
        </span>
        <button type="button" class="lfp-icon-btn lfp-icon-danger" data-lfp-social-remove title="Remove">&times;</button>
    </div>
</template>

<template id="lfp-tpl-ig-tile">
    <div class="lfp-ig-tile" draggable="true">
        <span class="lfp-handle" title="Drag to reorder">&#x2630;</span>
        <button type="button" class="lfp-ig-edit" data-lfp-ig-edit aria-label="Edit tile">
            <span class="lfp-ig-img" data-lfp-ig-img></span>
            <span class="lfp-ig-overlay" data-lfp-ig-overlay>
                <span class="lfp-ig-title" data-lfp-ig-title></span>
            </span>
        </button>
        <button type="button" class="lfp-icon-btn lfp-icon-danger lfp-ig-remove" data-lfp-ig-remove title="Remove">&times;</button>
    </div>
</template>

<template id="lfp-tpl-ig-add">
    <button type="button" class="lfp-ig-add" data-lfp-ig-add aria-label="Add tile">
        <span class="lfp-ig-add-icon">&#43;</span>
        <span class="lfp-ig-add-label">Add tile</span>
    </button>
</template>

<dialog id="lfp-ig-dialog">
    <form method="dialog" id="lfp-ig-form">
        <header class="lfp-picker-head">
            <h3 id="lfp-ig-dialog-title">Add image tile</h3>
            <button type="submit" class="lfp-icon-btn" value="cancel" aria-label="Close">&times;</button>
        </header>

        <div class="lfp-ig-form">
            <div class="lfp-ig-image-block">
                <div class="lfp-ig-preview" id="lfp-ig-preview" aria-hidden="true"></div>
                <div class="lfp-ig-image-fields">
                    <label class="lfp-ig-label">Image <span class="lfp-ig-required">*</span></label>
                    <input type="url" id="lfp-ig-image-url" placeholder="https://example.com/photo.jpg">
                    <input type="file" id="lfp-ig-image-file" accept="image/*">
                    <small>Paste a URL or upload a file — this is the picture shown in the grid.</small>
                </div>
            </div>

            <div class="lfp-field">
                <label for="lfp-ig-source">Link source</label>
                <select id="lfp-ig-source">
                    <option value="url">URL</option>
                    <option value="keyword">YOURLS keyword</option>
                </select>
            </div>

            <div class="lfp-field" data-lfp-ig-block="url">
                <label for="lfp-ig-url">URL</label>
                <input type="url" id="lfp-ig-url" placeholder="https://example.com/blog-post">
            </div>

            <div class="lfp-field" data-lfp-ig-block="keyword" hidden>
                <label>YOURLS shortlink</label>
                <div class="lfp-keyword-pick">
                    <code id="lfp-ig-keyword-display">—</code>
                    <button type="button" class="lfp-btn" id="lfp-ig-pick">Pick a shortlink…</button>
                </div>
                <small>Resolves through <code>yourls_link()</code> at click time so YOURLS click stats still increment.</small>
            </div>

            <div class="lfp-field">
                <label for="lfp-ig-title-input">Optional title (overlay)</label>
                <input type="text" id="lfp-ig-title-input" placeholder="e.g. New blog post">
            </div>

            <div class="lfp-field">
                <label for="lfp-ig-show-mode">Show title</label>
                <select id="lfp-ig-show-mode">
                    <option value="always">Always visible</option>
                    <option value="hover">Only on hover</option>
                    <option value="never">Never (hide title)</option>
                </select>
            </div>
        </div>

        <footer class="lfp-ig-foot">
            <button type="button" class="lfp-btn" id="lfp-ig-cancel">Cancel</button>
            <button type="button" class="lfp-btn lfp-btn-primary" id="lfp-ig-save">Save tile</button>
        </footer>
    </form>
</dialog>

<dialog id="lfp-picker">
    <form method="dialog">
        <header class="lfp-picker-head">
            <h3>Add a YOURLS shortlink</h3>
            <button type="submit" class="lfp-icon-btn" value="cancel" aria-label="Close">&times;</button>
        </header>
        <div class="lfp-picker-search">
            <input type="search" id="lfp-picker-q" placeholder="Search by keyword, title or URL…" autocomplete="off">
        </div>
        <ul id="lfp-picker-list" class="lfp-picker-list"></ul>
        <footer class="lfp-picker-foot">
            <small>Showing first 200 matches. Refine your search to see more.</small>
        </footer>
    </form>
</dialog>

<script id="lfp-bootstrap" type="application/json"><?php echo json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo yourls_esc_attr(lfp_plugin_url('assets/admin.js' . $asset_v)); ?>"></script>
