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
$all_links  = lfp_get_all_yourls_links();

$asset_v = '?v=' . LFP_VERSION;

$bootstrap = [
    'items'    => $items,
    'allLinks' => array_map(static fn($row): array => [
        'keyword' => (string) ($row->keyword ?? ''),
        'url'     => (string) ($row->url ?? ''),
        'title'   => (string) ($row->title ?? ''),
    ], $all_links),
];

?>
<link rel="stylesheet" href="<?php echo yourls_esc_attr(lfp_plugin_url('assets/admin.css' . $asset_v)); ?>">

<h2>Link Front Page</h2>
<p>Show selected shortlinks as a Linktree-style list on your YOURLS homepage. Drag &amp; drop to reorder, group links into category boxes, and customize each entry with an image, title and description.</p>

<?php if ($notice !== ''): ?>
    <div class="lfp-notice"><?php echo yourls_esc_html($notice); ?></div>
<?php endif; ?>

<form id="lfp-form" method="post" enctype="multipart/form-data" action="<?php echo yourls_esc_attr(yourls_admin_url('plugins.php?page=lfp')); ?>">
    <?php yourls_nonce_field(LFP_NONCE_ACTION); ?>
    <input type="hidden" name="lfp_action" value="save">
    <input type="hidden" name="items_json" id="lfp-items-json" value="">

    <div class="lfp-tabs" role="tablist">
        <button type="button" class="lfp-tab is-active" data-tab="links" role="tab">Links</button>
        <button type="button" class="lfp-tab" data-tab="general" role="tab">General</button>
        <button type="button" class="lfp-tab" data-tab="appearance" role="tab">Appearance</button>
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

        <div class="lfp-row">
            <label class="lfp-checkbox">
                <input type="checkbox" name="show_footer" value="1" <?php echo !empty($general['show_footer']) ? 'checked' : ''; ?>>
                <span>Show footer with login link &amp; YOURLS attribution</span>
            </label>
        </div>
    </section>

    <!-- ========================= APPEARANCE TAB ========================== -->
    <section class="lfp-pane" data-pane="appearance">
        <div class="lfp-grid lfp-grid-3">
            <div class="lfp-field">
                <label for="lfp-bg">Background color</label>
                <input type="color" id="lfp-bg" name="background_color" value="<?php echo yourls_esc_attr($appearance['background_color']); ?>">
            </div>
            <div class="lfp-field">
                <label for="lfp-fg">Text color</label>
                <input type="color" id="lfp-fg" name="text_color" value="<?php echo yourls_esc_attr($appearance['text_color']); ?>">
            </div>
            <div class="lfp-field">
                <label for="lfp-muted">Muted text</label>
                <input type="color" id="lfp-muted" name="muted_color" value="<?php echo yourls_esc_attr($appearance['muted_color']); ?>">
            </div>
            <div class="lfp-field">
                <label for="lfp-card">Card background</label>
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

        <div class="lfp-grid">
            <div class="lfp-field">
                <label for="lfp-radius">Border radius (px)</label>
                <input type="number" id="lfp-radius" name="border_radius" min="0" max="64" value="<?php echo yourls_esc_attr($appearance['border_radius']); ?>">
            </div>
            <div class="lfp-field">
                <label for="lfp-font">Font family</label>
                <input type="text" id="lfp-font" name="font_family" value="<?php echo yourls_esc_attr($appearance['font_family']); ?>">
            </div>
        </div>

        <div class="lfp-field">
            <label>Background image (optional)</label>
            <div class="lfp-image-input">
                <input type="url" name="background_image" value="<?php echo yourls_esc_attr($appearance['background_image']); ?>" placeholder="https://example.com/bg.jpg" data-lfp-image-url>
                <input type="file" name="background_image" accept="image/*" data-lfp-image-file>
            </div>
        </div>

        <div class="lfp-field">
            <label for="lfp-customcss">Custom CSS</label>
            <textarea id="lfp-customcss" name="custom_css" rows="6" class="lfp-mono" spellcheck="false"><?php echo yourls_esc_html($appearance['custom_css']); ?></textarea>
            <small>Inserted at the bottom of the inline style block on the public page.</small>
        </div>
    </section>

    <div class="lfp-actions">
        <button type="submit" class="lfp-btn lfp-btn-primary">Save settings</button>
        <button type="button" class="lfp-btn lfp-btn-danger" id="lfp-reset">Reset to defaults</button>
    </div>
</form>

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
