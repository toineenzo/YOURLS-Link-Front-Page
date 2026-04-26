<?php
/**
 * Themed 404 page. Rendered by lfp_handle_loader_failed() when the user has
 * picked the "page" mode. Reuses the same CSS variables / typography / font
 * loading as the linktree so the not-found page feels like part of the same
 * site rather than a generic browser 404.
 *
 * @var array  $general    From lfp_get_general()
 * @var string $request    The keyword the visitor tried to hit
 */

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

$appearance = lfp_get_appearance();

$nf_title  = trim((string) ($general['not_found_title']   ?? ''));
$nf_msg    = trim((string) ($general['not_found_message'] ?? ''));
$btn_label = trim((string) ($general['not_found_button_label'] ?? ''));
$btn_url   = lfp_resolve_not_found_target($general);

if ($nf_title  === '') $nf_title  = 'Link not found';
if ($nf_msg    === '') $nf_msg    = 'The short link you tried to visit does not exist or has been removed.';
if ($btn_label === '') $btn_label = $btn_url !== '' ? 'Continue' : 'Back to homepage';
if ($btn_url   === '') $btn_url   = YOURLS_SITE;

/* Typography font assets — duplicates frontend.php so the 404 page picks up
   the same Google / custom font. */
$font_source = (string) ($appearance['font_source'] ?? 'system');
$font_assets = '';
$font_stack  = (string) $appearance['font_family'];

if ($font_source === 'google' && trim((string) $appearance['font_google']) !== '') {
    $family   = (string) $appearance['font_google'];
    $weights  = trim((string) ($appearance['font_google_weights'] ?? '400;600;700')) ?: '400';
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
    '--lfp-radius'          => $appearance['border_radius'],
    '--lfp-page-max'        => $appearance['page_max_width'],
    '--lfp-pad-top'         => $appearance['page_padding_top'],
    '--lfp-pad-bot'         => $appearance['page_padding_bottom'],
    '--lfp-pad-x'           => $appearance['page_padding_x'],
    '--lfp-card-gap'        => $appearance['card_gap'],
    '--lfp-card-py'         => $appearance['card_padding_y'],
    '--lfp-card-px'         => $appearance['card_padding_x'],
    '--lfp-icon'            => $appearance['icon_size'],
    '--lfp-photo'           => $appearance['about_photo_size'],
    '--lfp-photo-radius'    => !empty($appearance['about_photo_round']) ? '999px' : (string) $appearance['border_radius'],
    '--lfp-logo'            => $appearance['logo_size'] ?? '96px',
    '--lfp-logo-radius'     => !empty($appearance['logo_round']) ? '999px' : (string) $appearance['border_radius'],
    '--lfp-font'            => $font_stack,
    '--lfp-title-size'      => $appearance['title_size'],
    '--lfp-subtitle-size'   => $appearance['subtitle_size'],
    '--lfp-cat-title-size'  => $appearance['category_title_size'],
    '--lfp-link-title-size' => $appearance['link_title_size'],
    '--lfp-body-size'       => $appearance['body_size'],
];

$style_lines = [];
foreach ($css_vars as $name => $value) {
    if ($value === '') continue;
    $style_lines[] = $name . ': ' . $value . ';';
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?php echo yourls_esc_attr($appearance['background_color']); ?>">
    <meta name="robots" content="noindex">
    <title>404 &mdash; <?php echo yourls_esc_html(trim(strip_tags(lfp_render_inline($nf_title)))); ?></title>
    <?php echo $font_assets; ?>
    <link rel="stylesheet" href="<?php echo yourls_esc_attr(lfp_plugin_url('assets/frontend.css?v=' . LFP_VERSION)); ?>">
    <style>
        :root { <?php echo implode(' ', $style_lines); ?> }
        <?php if ($appearance['custom_css'] !== ''): ?>
        <?php echo $appearance['custom_css']; ?>
        <?php endif; ?>
    </style>
</head>
<body>
<main class="lfp-page lfp-404-page">
    <section class="lfp-404">
        <div class="lfp-404-code" aria-hidden="true">404</div>
        <h1 class="lfp-404-title"><?php echo lfp_render_inline($nf_title); ?></h1>
        <div class="lfp-404-message"><?php echo lfp_render_text($nf_msg); ?></div>
        <?php if ($request !== ''): ?>
            <p class="lfp-404-keyword"><code>/<?php echo yourls_esc_html($request); ?></code></p>
        <?php endif; ?>
        <a class="lfp-404-btn" href="<?php echo yourls_esc_url($btn_url); ?>">
            <?php echo lfp_render_inline($btn_label); ?>
            <span aria-hidden="true">&rarr;</span>
        </a>
    </section>
</main>
</body>
</html>
