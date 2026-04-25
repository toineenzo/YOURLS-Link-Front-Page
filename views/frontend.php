<?php
/**
 * Public-facing linktree page. Rendered when a visitor hits the YOURLS root.
 *
 * @var array $general    Resolved via lfp_get_general()
 * @var array $appearance Resolved via lfp_get_appearance()
 * @var array $items      Resolved via lfp_get_items()
 */

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

$general    = lfp_get_general();
$appearance = lfp_get_appearance();
$items      = lfp_get_items();
$instagram  = lfp_get_instagram();

$site_title = $general['site_title'] !== '' ? $general['site_title'] : (defined('YOURLS_SITE') ? parse_url(YOURLS_SITE, PHP_URL_HOST) : 'Links');
$site_desc  = (string) $general['site_description'];
$site_logo  = (string) $general['site_logo'];

/* ------- Resolve font stack based on the chosen source */
$font_source = (string) ($appearance['font_source'] ?? 'system');
$font_assets = '';
$font_stack  = (string) $appearance['font_family'];

if ($font_source === 'google' && trim((string) $appearance['font_google']) !== '') {
    $family   = (string) $appearance['font_google'];
    $weights  = (string) ($appearance['font_google_weights'] ?? '400;600;700');
    $weights  = trim($weights) === '' ? '400' : $weights;
    $href     = 'https://fonts.googleapis.com/css2?family=' . str_replace('%2B', '+', urlencode($family)) . ':wght@' . urlencode($weights) . '&display=swap';
    $font_assets .= '<link rel="preconnect" href="https://fonts.googleapis.com">'
                  . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                  . '<link href="' . yourls_esc_attr($href) . '" rel="stylesheet">';
    $font_stack = '"' . str_replace('"', '', $family) . '", ' . $font_stack;
} elseif ($font_source === 'custom' && trim((string) $appearance['font_custom_url']) !== '') {
    $custom_url    = yourls_esc_url((string) $appearance['font_custom_url']);
    $custom_format = preg_replace('/[^a-z0-9]/i', '', (string) $appearance['font_custom_format']) ?: 'woff2';
    $font_assets .= '<style>@font-face{font-family:"LFPCustom";src:url("' . $custom_url . '") format("' . $custom_format . '");font-display:swap;}</style>';
    $font_stack   = '"LFPCustom", ' . $font_stack;
}

$css_vars = [
    '--lfp-bg'              => $appearance['background_color'],
    '--lfp-fg'              => $appearance['text_color'],
    '--lfp-muted'           => $appearance['muted_color'],
    '--lfp-card'            => $appearance['card_background'],
    '--lfp-card-hover'      => $appearance['card_hover'],
    '--lfp-accent'          => $appearance['accent_color'],
    '--lfp-radius'          => $appearance['border_radius'] . 'px',
    '--lfp-page-max'        => $appearance['page_max_width'] . 'px',
    '--lfp-pad-top'         => $appearance['page_padding_top'] . 'px',
    '--lfp-pad-bot'         => $appearance['page_padding_bottom'] . 'px',
    '--lfp-pad-x'           => $appearance['page_padding_x'] . 'px',
    '--lfp-card-gap'        => $appearance['card_gap'] . 'px',
    '--lfp-card-py'         => $appearance['card_padding_y'] . 'px',
    '--lfp-card-px'         => $appearance['card_padding_x'] . 'px',
    '--lfp-icon'            => $appearance['icon_size'] . 'px',
    '--lfp-photo'           => $appearance['about_photo_size'] . 'px',
    '--lfp-font'            => $font_stack,
    '--lfp-title-size'      => $appearance['title_size'],
    '--lfp-subtitle-size'   => $appearance['subtitle_size'],
    '--lfp-cat-title-size'  => $appearance['category_title_size'],
    '--lfp-link-title-size' => $appearance['link_title_size'],
    '--lfp-body-size'       => $appearance['body_size'],
];

$style_lines = [];
foreach ($css_vars as $name => $value) {
    if ($value === '') {
        continue;
    }
    $style_lines[] = $name . ': ' . $value . ';';
}

$body_style = '';
if ($appearance['background_image'] !== '') {
    $body_style = "background-image:url('" . yourls_esc_url($appearance['background_image']) . "');background-size:cover;background-position:center;background-attachment:fixed;";
}

/**
 * Render a single link card.
 */
$render_link = static function (array $link): string {
    $resolved = lfp_resolve_link($link);
    if (!$resolved['exists']) {
        return '';
    }

    $title       = yourls_esc_html($resolved['title']);
    $description = yourls_esc_html($resolved['description']);
    $image       = $resolved['image'];
    $url         = yourls_esc_url($resolved['short_url']);

    $img_html = '';
    if ($image !== '') {
        $img_html = '<div class="lfp-link-image"><img src="' . yourls_esc_url($image) . '" alt="" loading="lazy"></div>';
    }

    $desc_html = $description !== '' ? '<p class="lfp-link-desc">' . $description . '</p>' : '';

    return <<<HTML
<a class="lfp-link" href="{$url}" rel="noopener">
    {$img_html}
    <div class="lfp-link-text">
        <span class="lfp-link-title">{$title}</span>
        {$desc_html}
    </div>
    <span class="lfp-link-arrow" aria-hidden="true">&rarr;</span>
</a>
HTML;
};

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?php echo yourls_esc_attr($appearance['background_color']); ?>">
    <title><?php echo yourls_esc_html($site_title); ?></title>
    <?php if ($site_desc !== ''): ?>
    <meta name="description" content="<?php echo yourls_esc_attr($site_desc); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?php echo yourls_esc_attr($site_title); ?>">
    <?php if ($site_desc !== ''): ?>
    <meta property="og:description" content="<?php echo yourls_esc_attr($site_desc); ?>">
    <?php endif; ?>
    <?php if ($site_logo !== ''): ?>
    <meta property="og:image" content="<?php echo yourls_esc_attr($site_logo); ?>">
    <link rel="icon" href="<?php echo yourls_esc_attr($site_logo); ?>">
    <?php endif; ?>
    <?php echo $font_assets; ?>
    <link rel="stylesheet" href="<?php echo yourls_esc_attr(lfp_plugin_url('assets/frontend.css?v=' . LFP_VERSION)); ?>">
    <style>
        :root { <?php echo implode(' ', $style_lines); ?> }
        <?php if ($appearance['custom_css'] !== ''): ?>
        <?php echo $appearance['custom_css']; ?>
        <?php endif; ?>
    </style>
</head>
<body style="<?php echo yourls_esc_attr($body_style); ?>">
<main class="lfp-page">
    <header class="lfp-header">
        <?php if ($site_logo !== ''): ?>
            <img class="lfp-logo" src="<?php echo yourls_esc_url($site_logo); ?>" alt="<?php echo yourls_esc_attr($site_title); ?>">
        <?php endif; ?>
        <h1 class="lfp-title"><?php echo yourls_esc_html($site_title); ?></h1>
        <?php if ($site_desc !== ''): ?>
            <p class="lfp-subtitle"><?php echo yourls_esc_html($site_desc); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!empty($general['about_enabled'])):
        $about_image   = (string) ($general['about_image']   ?? '');
        $about_text    = (string) ($general['about_text']    ?? '');
        $about_socials = is_array($general['about_socials'] ?? null) ? $general['about_socials'] : [];
        $resolved_socials = [];
        foreach ($about_socials as $entry) {
            $resolved = lfp_resolve_social($entry);
            if ($resolved !== null) {
                $resolved_socials[] = $resolved;
            }
        }
    ?>
        <section class="lfp-about">
            <?php if ($about_image !== ''): ?>
                <img class="lfp-about-photo" src="<?php echo yourls_esc_url($about_image); ?>" alt="" loading="lazy">
            <?php endif; ?>
            <?php if ($about_text !== ''): ?>
                <p class="lfp-about-text"><?php echo nl2br(yourls_esc_html($about_text), false); ?></p>
            <?php endif; ?>
            <?php if (!empty($resolved_socials)): ?>
                <ul class="lfp-socials">
                    <?php foreach ($resolved_socials as $soc): ?>
                        <li>
                            <a href="<?php echo yourls_esc_url($soc['url']); ?>"
                               class="lfp-social-btn"
                               style="--lfp-social-color: <?php echo yourls_esc_attr($soc['color']); ?>"
                               aria-label="<?php echo yourls_esc_attr($soc['label']); ?>"
                               rel="me noopener">
                                <?php echo lfp_render_social_icon($soc['platform']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($instagram['enabled']) && !empty($instagram['items'])):
        $resolved_ig = [];
        foreach ($instagram['items'] as $entry) {
            $r = lfp_resolve_instagram($entry);
            if ($r !== null) $resolved_ig[] = $r;
        }
    ?>
        <?php if (!empty($resolved_ig)): ?>
        <section class="lfp-ig" aria-label="Instagram feed">
            <?php foreach ($resolved_ig as $tile): ?>
                <a class="lfp-ig-tile lfp-ig-show-<?php echo yourls_esc_attr($tile['show_mode']); ?>"
                   href="<?php echo yourls_esc_url($tile['url']); ?>"
                   rel="noopener"
                   <?php if ($tile['title'] !== ''): ?>aria-label="<?php echo yourls_esc_attr($tile['title']); ?>"<?php endif; ?>>
                    <img src="<?php echo yourls_esc_url($tile['image']); ?>" alt="" loading="lazy">
                    <?php if ($tile['title'] !== '' && $tile['show_mode'] !== 'never'): ?>
                        <span class="lfp-ig-overlay"><?php echo yourls_esc_html($tile['title']); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>

    <section class="lfp-list">
        <?php if (empty($items)): ?>
            <div class="lfp-empty">
                <p>No links to show yet.</p>
                <p class="lfp-empty-hint">An administrator can configure links in the YOURLS admin under <em>Manage Plugins &rsaquo; Link Front Page</em>.</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php $type = $item['type'] ?? 'link'; ?>
                <?php if ($type === 'category'): ?>
                    <?php
                    $cat_title = trim((string) ($item['title'] ?? ''));
                    $cat_desc  = trim((string) ($item['description'] ?? ''));
                    $cat_image = (string) ($item['image'] ?? '');
                    $children  = is_array($item['children'] ?? null) ? $item['children'] : [];
                    ?>
                    <article class="lfp-category">
                        <?php if ($cat_image !== ''): ?>
                            <div class="lfp-category-image">
                                <img src="<?php echo yourls_esc_url($cat_image); ?>" alt="" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <?php if ($cat_title !== '' || $cat_desc !== ''): ?>
                            <header class="lfp-category-head">
                                <?php if ($cat_title !== ''): ?>
                                    <h2 class="lfp-category-title"><?php echo yourls_esc_html($cat_title); ?></h2>
                                <?php endif; ?>
                                <?php if ($cat_desc !== ''): ?>
                                    <p class="lfp-category-desc"><?php echo yourls_esc_html($cat_desc); ?></p>
                                <?php endif; ?>
                            </header>
                        <?php endif; ?>
                        <?php if (!empty($children)): ?>
                            <div class="lfp-category-links">
                                <?php foreach ($children as $child): ?>
                                    <?php echo $render_link($child); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php else: ?>
                    <?php echo $render_link($item); ?>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php
    $show_login = !empty($general['show_login_link']);
    $show_pby   = !empty($general['show_powered_by']);
    if ($show_login || $show_pby):
        $pby_text = trim((string) ($general['powered_by_text'] ?? ''));
        $pby_url  = trim((string) ($general['powered_by_url']  ?? ''));
        if ($pby_text === '') $pby_text = 'YOURLS';
        if ($pby_url  === '') $pby_url  = 'https://yourls.org';
    ?>
        <footer class="lfp-footer">
            <?php if ($show_login): ?>
                <a href="<?php echo yourls_esc_attr(YOURLS_SITE . '/' . trim((string) $general['login_path'], '/')); ?>" rel="nofollow">Login</a>
            <?php endif; ?>
            <?php if ($show_login && $show_pby): ?>
                <span aria-hidden="true">&middot;</span>
            <?php endif; ?>
            <?php if ($show_pby): ?>
                <span>Powered by <a href="<?php echo yourls_esc_url($pby_url); ?>" rel="noopener"><?php echo yourls_esc_html($pby_text); ?></a></span>
            <?php endif; ?>
        </footer>
    <?php endif; ?>
</main>
</body>
</html>
