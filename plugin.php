<?php
/*
Plugin Name: Link Front Page
Plugin URI: https://github.com/toineenzo/YOURLS-Link-Front-Page
Description: Show selected shortlinks as a Linktree-style link list on the YOURLS homepage. Group links into category boxes, reorder by drag and drop, and customize each entry with an image, title and description.
Version: 2.0.0
Author: Toine Rademacher (toineenzo)
Author URI: https://toine.click
*/

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

const LFP_VERSION = '2.0.0';
const LFP_DIR = __DIR__;
const LFP_OPT_ITEMS = 'lfp_items';
const LFP_OPT_GENERAL = 'lfp_general';
const LFP_OPT_APPEARANCE = 'lfp_appearance';
const LFP_OPT_INSTAGRAM = 'lfp_instagram';
const LFP_NONCE_ACTION = 'lfp_save_settings';

/* ---------------------------------------------------------------------------
 * Path & URL helpers
 * ------------------------------------------------------------------------ */

function lfp_plugin_url(string $path = ''): string
{
    $dirname = basename(__DIR__);
    return YOURLS_SITE . '/user/plugins/' . $dirname . '/' . ltrim($path, '/');
}

function lfp_uploads_dir(): string
{
    $dir = LFP_DIR . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function lfp_uploads_url(): string
{
    return lfp_plugin_url('uploads/');
}

/* ---------------------------------------------------------------------------
 * Settings storage
 * ------------------------------------------------------------------------ */

function lfp_default_general(): array
{
    return [
        'enabled'           => true,
        'site_title'        => '',
        'site_description'  => '',
        'site_logo'         => '',
        'login_path'        => 'login',

        // Footer (split out in 1.1: was a single show_footer toggle)
        'show_login_link'   => true,
        'show_powered_by'   => true,
        'powered_by_text'   => '',
        'powered_by_url'    => '',

        // About-me section
        'about_enabled'     => false,
        'about_image'       => '',
        'about_text'        => '',
        'about_socials'     => [],
    ];
}

function lfp_default_appearance(): array
{
    return [
        // Colors
        'background_color'    => '#0f172a',
        'background_image'    => '',
        'text_color'          => '#f1f5f9',
        'muted_color'         => '#94a3b8',
        'card_background'     => '#1e293b',
        'card_hover'          => '#334155',
        'accent_color'        => '#3b82f6',

        // Sizing & spacing
        'border_radius'       => '16',
        'page_max_width'      => '640',
        'page_padding_top'    => '56',
        'page_padding_bottom' => '80',
        'page_padding_x'      => '20',
        'card_gap'            => '14',
        'card_padding_y'      => '14',
        'card_padding_x'      => '18',
        'icon_size'           => '44',
        'about_photo_size'    => '120',

        // Typography
        'font_source'         => 'system',  // 'system' | 'google' | 'custom'
        'font_family'         => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'font_google'         => '',
        'font_google_weights' => '400;600;700',
        'font_custom_url'     => '',
        'font_custom_format'  => '',
        'title_size'          => '1.75rem',
        'subtitle_size'       => '1rem',
        'category_title_size' => '1.15rem',
        'link_title_size'     => '1rem',
        'body_size'           => '0.95rem',

        'custom_css'          => '',
    ];
}

function lfp_default_instagram(): array
{
    return [
        'enabled' => false,
        'items'   => [],
    ];
}

function lfp_get_general(): array
{
    $stored = yourls_get_option(LFP_OPT_GENERAL, []);
    if (!is_array($stored)) {
        $stored = [];
    }

    // Migrate v1.0 footer toggle into the new split footer fields.
    if (array_key_exists('show_footer', $stored)) {
        $legacy = (bool) $stored['show_footer'];
        $stored += [
            'show_login_link' => $legacy,
            'show_powered_by' => $legacy,
        ];
        unset($stored['show_footer']);
    }

    return array_merge(lfp_default_general(), $stored);
}

function lfp_get_appearance(): array
{
    $stored = yourls_get_option(LFP_OPT_APPEARANCE, []);
    if (!is_array($stored)) {
        $stored = [];
    }
    return array_merge(lfp_default_appearance(), $stored);
}

function lfp_get_items(): array
{
    $stored = yourls_get_option(LFP_OPT_ITEMS, []);
    return is_array($stored) ? $stored : [];
}

function lfp_get_instagram(): array
{
    $stored = yourls_get_option(LFP_OPT_INSTAGRAM, []);
    if (!is_array($stored)) {
        $stored = [];
    }
    $merged = array_merge(lfp_default_instagram(), $stored);
    if (!is_array($merged['items'] ?? null)) {
        $merged['items'] = [];
    }
    return $merged;
}

function lfp_get_google_fonts(): array
{
    static $fonts = null;
    if ($fonts === null) {
        $loaded = require LFP_DIR . '/includes/google-fonts.php';
        $fonts  = is_array($loaded) ? $loaded : [];
    }
    return $fonts;
}

/* ---------------------------------------------------------------------------
 * Routing: intercept "/" and "/<login_path>" before YOURLS routes them.
 *
 * We hook in three places so the plugin works regardless of which entry
 * point YOURLS uses (root index.php served directly by the web server,
 * yourls-loader.php via .htaccess rewrite, or third-party themes such as
 * Sleeky frontend that also bootstrap YOURLS):
 *
 *   - plugins_loaded   - fires from includes/load-yourls.php on every entry
 *                        point. Catches direct index.php hits.
 *   - pre_load_template- fires inside yourls-loader.php right after the
 *                        request is resolved, before keyword matching.
 *                        This is the reliable interception point when
 *                        Apache rewrites everything to yourls-loader.php.
 *   - loader_failed    - final fallback before YOURLS does its 302 to
 *                        YOURLS_SITE, which would otherwise cause an
 *                        infinite redirect loop on the homepage.
 * ------------------------------------------------------------------------ */

yourls_add_action('plugins_loaded', 'lfp_handle_routing');
yourls_add_action('pre_load_template', 'lfp_handle_pre_load_template');
yourls_add_action('loader_failed', 'lfp_handle_loader_failed');

function lfp_handle_routing(): void
{
    if (yourls_is_admin()) {
        return;
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === 'yourls-api.php') {
        return;
    }

    $general = lfp_get_general();
    if (empty($general['enabled'])) {
        return;
    }

    $request = lfp_resolve_request();

    $login_path = trim((string) ($general['login_path'] ?? 'login'), '/');
    if ($login_path !== '' && $request === $login_path) {
        yourls_redirect(yourls_admin_url(), 302);
        exit;
    }

    if (!lfp_is_root_request($request)) {
        return;
    }

    if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) {
        return;
    }

    lfp_render_frontend();
    exit;
}

/**
 * Fires from yourls-loader.php after the request is resolved. If we were not
 * already caught by plugins_loaded (e.g. the request was actually empty and
 * the host serves yourls-loader.php for "/"), render the linktree here.
 */
function lfp_handle_pre_load_template(string $request = ''): void
{
    $general = lfp_get_general();
    if (empty($general['enabled'])) {
        return;
    }

    $resolved = trim((string) strtok((string) $request, '?'), '/');
    if (!lfp_is_root_request($resolved)) {
        return;
    }

    lfp_render_frontend();
    exit;
}

/**
 * Final safety net: if YOURLS is about to redirect a missing keyword and the
 * request is actually the empty root, render the linktree instead of looping.
 */
function lfp_handle_loader_failed(string $request = ''): void
{
    $general = lfp_get_general();
    if (empty($general['enabled'])) {
        return;
    }

    $resolved = trim((string) strtok((string) $request, '?'), '/');
    if (!lfp_is_root_request($resolved)) {
        return;
    }

    lfp_render_frontend();
    exit;
}

/**
 * Resolve the current request path relative to YOURLS_SITE. Tries the YOURLS
 * helper first, then falls back to a direct REQUEST_URI parse so we still
 * work when yourls_get_request() has been cached with a stale value.
 */
function lfp_resolve_request(): string
{
    $request = '';
    if (function_exists('yourls_get_request')) {
        $request = (string) yourls_get_request();
    }
    if ($request === '') {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $base = parse_url(YOURLS_SITE, PHP_URL_PATH) ?: '';
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $request = $uri;
    }
    $request = (string) strtok($request, '?');
    return trim($request, '/');
}

function lfp_is_root_request(string $request): bool
{
    return $request === '' || $request === 'index.php';
}

function lfp_render_frontend(): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-LFP-Rendered: 1');
    }
    require LFP_DIR . '/views/frontend.php';
}

/* ---------------------------------------------------------------------------
 * Admin: settings page
 * ------------------------------------------------------------------------ */

yourls_register_plugin_page('lfp', 'Link Front Page', 'lfp_admin_page_callback');

function lfp_admin_page_callback(): void
{
    $notice = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $notice = lfp_handle_admin_post();
    } elseif (isset($_GET['saved'])) {
        $notice = 'Settings saved.';
    }

    require LFP_DIR . '/views/admin.php';
}

function lfp_handle_admin_post(): string
{
    $nonce = (string) ($_POST['nonce'] ?? '');
    if (!yourls_verify_nonce(LFP_NONCE_ACTION, $nonce)) {
        return 'Invalid security token. Please reload the page and try again.';
    }

    $action = (string) ($_POST['lfp_action'] ?? 'save');

    if ($action === 'save') {
        lfp_save_settings();
    }
    if ($action === 'reset') {
        lfp_reset_settings();
    }

    return 'Unknown action.';
}

function lfp_save_settings(): never
{
    $uploaded = lfp_process_uploaded_files();

    $socials_json = (string) ($_POST['about_socials_json'] ?? '[]');
    $socials_raw  = json_decode($socials_json, true);
    if (!is_array($socials_raw)) {
        $socials_raw = [];
    }

    $general = [
        'enabled'           => isset($_POST['enabled']),
        'site_title'        => trim((string) ($_POST['site_title'] ?? '')),
        'site_description'  => trim((string) ($_POST['site_description'] ?? '')),
        'site_logo'         => $uploaded['site_logo'] ?? trim((string) ($_POST['site_logo'] ?? '')),
        'login_path'        => trim((string) ($_POST['login_path'] ?? 'login'), '/'),

        'show_login_link'   => isset($_POST['show_login_link']),
        'show_powered_by'   => isset($_POST['show_powered_by']),
        'powered_by_text'   => trim((string) ($_POST['powered_by_text'] ?? '')),
        'powered_by_url'    => trim((string) ($_POST['powered_by_url'] ?? '')),

        'about_enabled'     => isset($_POST['about_enabled']),
        'about_image'       => $uploaded['about_image'] ?? trim((string) ($_POST['about_image'] ?? '')),
        'about_text'        => trim((string) ($_POST['about_text'] ?? '')),
        'about_socials'     => lfp_sanitize_socials($socials_raw, $uploaded),
    ];
    yourls_update_option(LFP_OPT_GENERAL, $general);

    $font_source_raw = (string) ($_POST['font_source'] ?? 'system');
    $font_source = in_array($font_source_raw, ['system', 'google', 'custom'], true) ? $font_source_raw : 'system';

    $custom_font = lfp_process_uploaded_font();
    $existing_appearance = lfp_get_appearance();
    $custom_url    = $custom_font['url']    ?? trim((string) ($_POST['font_custom_url']    ?? $existing_appearance['font_custom_url']));
    $custom_format = $custom_font['format'] ?? trim((string) ($_POST['font_custom_format'] ?? $existing_appearance['font_custom_format']));

    $appearance = [
        'background_color'    => lfp_sanitize_color((string) ($_POST['background_color'] ?? '#0f172a')),
        'background_image'    => $uploaded['background_image'] ?? trim((string) ($_POST['background_image'] ?? '')),
        'text_color'          => lfp_sanitize_color((string) ($_POST['text_color'] ?? '#f1f5f9')),
        'muted_color'         => lfp_sanitize_color((string) ($_POST['muted_color'] ?? '#94a3b8')),
        'card_background'     => lfp_sanitize_color((string) ($_POST['card_background'] ?? '#1e293b')),
        'card_hover'          => lfp_sanitize_color((string) ($_POST['card_hover'] ?? '#334155')),
        'accent_color'        => lfp_sanitize_color((string) ($_POST['accent_color'] ?? '#3b82f6')),

        'border_radius'       => (string) max(0, min(64,   (int) ($_POST['border_radius']       ?? 16))),
        'page_max_width'      => (string) max(280, min(1600,(int) ($_POST['page_max_width']      ?? 640))),
        'page_padding_top'    => (string) max(0, min(400,  (int) ($_POST['page_padding_top']    ?? 56))),
        'page_padding_bottom' => (string) max(0, min(400,  (int) ($_POST['page_padding_bottom'] ?? 80))),
        'page_padding_x'      => (string) max(0, min(400,  (int) ($_POST['page_padding_x']      ?? 20))),
        'card_gap'            => (string) max(0, min(80,   (int) ($_POST['card_gap']            ?? 14))),
        'card_padding_y'      => (string) max(0, min(80,   (int) ($_POST['card_padding_y']      ?? 14))),
        'card_padding_x'      => (string) max(0, min(80,   (int) ($_POST['card_padding_x']      ?? 18))),
        'icon_size'           => (string) max(0, min(160,  (int) ($_POST['icon_size']           ?? 44))),
        'about_photo_size'    => (string) max(40, min(400, (int) ($_POST['about_photo_size']    ?? 120))),

        'font_source'         => $font_source,
        'font_family'         => trim((string) ($_POST['font_family'] ?? '')),
        'font_google'         => trim((string) ($_POST['font_google'] ?? '')),
        'font_google_weights' => trim((string) ($_POST['font_google_weights'] ?? '400;600;700')),
        'font_custom_url'     => $custom_url,
        'font_custom_format'  => $custom_format,
        'title_size'          => lfp_sanitize_size((string) ($_POST['title_size']          ?? '1.75rem'),  '1.75rem'),
        'subtitle_size'       => lfp_sanitize_size((string) ($_POST['subtitle_size']       ?? '1rem'),     '1rem'),
        'category_title_size' => lfp_sanitize_size((string) ($_POST['category_title_size'] ?? '1.15rem'),  '1.15rem'),
        'link_title_size'     => lfp_sanitize_size((string) ($_POST['link_title_size']     ?? '1rem'),     '1rem'),
        'body_size'           => lfp_sanitize_size((string) ($_POST['body_size']           ?? '0.95rem'),  '0.95rem'),

        'custom_css'          => (string) ($_POST['custom_css'] ?? ''),
    ];
    yourls_update_option(LFP_OPT_APPEARANCE, $appearance);

    // Instagram grid
    $insta_json = (string) ($_POST['instagram_json'] ?? '[]');
    $insta_raw  = json_decode($insta_json, true);
    if (!is_array($insta_raw)) {
        $insta_raw = [];
    }
    $instagram = [
        'enabled' => isset($_POST['instagram_enabled']),
        'items'   => lfp_sanitize_instagram($insta_raw, $uploaded),
    ];
    yourls_update_option(LFP_OPT_INSTAGRAM, $instagram);

    $items_json = (string) ($_POST['items_json'] ?? '[]');
    $items = json_decode($items_json, true);
    if (!is_array($items)) {
        $items = [];
    }
    $items = lfp_sanitize_items($items, $uploaded);
    yourls_update_option(LFP_OPT_ITEMS, $items);

    yourls_redirect(yourls_admin_url('plugins.php?page=lfp&saved=1'), 302);
    exit;
}

function lfp_reset_settings(): never
{
    yourls_update_option(LFP_OPT_GENERAL, lfp_default_general());
    yourls_update_option(LFP_OPT_APPEARANCE, lfp_default_appearance());
    yourls_update_option(LFP_OPT_ITEMS, []);
    yourls_update_option(LFP_OPT_INSTAGRAM, lfp_default_instagram());
    yourls_redirect(yourls_admin_url('plugins.php?page=lfp&saved=1'), 302);
    exit;
}

function lfp_sanitize_color(string $color): string
{
    $color = trim($color);
    if ($color === '') {
        return '';
    }
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1) {
        return $color;
    }
    if (preg_match('/^(rgb|rgba|hsl|hsla)\([^)]+\)$/i', $color) === 1) {
        return $color;
    }
    if (preg_match('/^[a-zA-Z]+$/', $color) === 1) {
        return $color;
    }
    return '';
}

function lfp_sanitize_id(string $id): string
{
    $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    return is_string($clean) ? $clean : '';
}

/**
 * Validate a CSS length value: accepts plain numbers (interpreted as px),
 * px / % / em / rem / vh / vw / vmin / vmax / ch / ex, and the calc() /
 * clamp() / min() / max() functional forms. Falls back to $default on
 * anything else so we never inject hostile CSS.
 */
function lfp_sanitize_size(string $value, string $default = ''): string
{
    $value = trim($value);
    if ($value === '') {
        return $default;
    }
    if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
        return $value . 'px';
    }
    if (preg_match('/^-?\d+(\.\d+)?(px|%|em|rem|vh|vw|vmin|vmax|ch|ex|pt|pc|cm|mm|in)$/i', $value) === 1) {
        return $value;
    }
    if (preg_match('/^(clamp|calc|min|max)\([^;{}<>]+\)$/i', $value) === 1) {
        return $value;
    }
    return $default;
}

function lfp_sanitize_instagram(array $items, array $uploaded): array
{
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = lfp_sanitize_id((string) ($item['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $source = ($item['source'] ?? 'url') === 'keyword' ? 'keyword' : 'url';
        $clean = [
            'id'        => $id,
            'source'    => $source,
            'url'       => '',
            'keyword'   => '',
            'image'     => $uploaded['ig_' . $id] ?? trim((string) ($item['image'] ?? '')),
            'title'     => trim((string) ($item['title'] ?? '')),
            'show_mode' => 'always',
        ];
        $mode = (string) ($item['show_mode'] ?? 'always');
        if (in_array($mode, ['always', 'hover', 'never'], true)) {
            $clean['show_mode'] = $mode;
        }
        if ($source === 'keyword') {
            $kw = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['keyword'] ?? ''));
            $clean['keyword'] = is_string($kw) ? $kw : '';
            if ($clean['keyword'] === '') {
                continue;
            }
        } else {
            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $clean['url'] = $url;
        }
        if ($clean['image'] === '') {
            continue; // grid items always need an image
        }
        $result[] = $clean;
    }
    return $result;
}

function lfp_resolve_instagram(array $entry): ?array
{
    $source = ($entry['source'] ?? 'url') === 'keyword' ? 'keyword' : 'url';
    $url = '';
    if ($source === 'keyword') {
        $keyword = (string) ($entry['keyword'] ?? '');
        if ($keyword === '' || !yourls_keyword_is_taken($keyword)) {
            return null;
        }
        $url = yourls_link($keyword);
    } else {
        $url = trim((string) ($entry['url'] ?? ''));
        if ($url === '') {
            return null;
        }
    }
    $image = trim((string) ($entry['image'] ?? ''));
    if ($image === '') {
        return null;
    }
    return [
        'id'        => (string) ($entry['id'] ?? ''),
        'url'       => $url,
        'image'     => $image,
        'title'     => trim((string) ($entry['title'] ?? '')),
        'show_mode' => in_array($entry['show_mode'] ?? 'always', ['always', 'hover', 'never'], true)
            ? (string) $entry['show_mode']
            : 'always',
    ];
}

/**
 * Process a custom font upload (input name="font_custom_file"). Accepts
 * woff2 / woff / ttf / otf, stores under uploads/fonts/, returns
 * ['url' => ..., 'format' => ...] or null when no upload happened.
 */
function lfp_process_uploaded_font(): ?array
{
    if (empty($_FILES['font_custom_file']) || !is_array($_FILES['font_custom_file'])) {
        return null;
    }
    $file = $_FILES['font_custom_file'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return null;
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return null;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return null;
    }

    $name = (string) ($file['name'] ?? '');
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $valid = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];
    if (!isset($valid[$ext])) {
        return null;
    }

    $fonts_dir = lfp_uploads_dir() . '/fonts';
    if (!is_dir($fonts_dir)) {
        @mkdir($fonts_dir, 0755, true);
        @file_put_contents($fonts_dir . '/index.html', '');
    }

    try {
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    } catch (\Throwable) {
        $filename = uniqid('lfp_', true) . '.' . $ext;
    }
    $dest = $fonts_dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }
    @chmod($dest, 0644);

    return [
        'url'    => lfp_uploads_url() . 'fonts/' . $filename,
        'format' => $valid[$ext],
    ];
}

function lfp_sanitize_items(array $items, array $uploaded): array
{
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = ($item['type'] ?? '') === 'category' ? 'category' : 'link';
        $id   = lfp_sanitize_id((string) ($item['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $base = [
            'id'          => $id,
            'type'        => $type,
            'title'       => trim((string) ($item['title'] ?? '')),
            'description' => trim((string) ($item['description'] ?? '')),
            'image'       => $uploaded['item_' . $id] ?? trim((string) ($item['image'] ?? '')),
        ];

        if ($type === 'link') {
            $keyword = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['keyword'] ?? ''));
            if (!is_string($keyword) || $keyword === '') {
                continue;
            }
            $base['keyword'] = $keyword;
        } else {
            $children = [];
            if (isset($item['children']) && is_array($item['children'])) {
                $children = lfp_sanitize_items($item['children'], $uploaded);
                $children = array_values(array_filter(
                    $children,
                    static fn(array $c): bool => ($c['type'] ?? '') === 'link',
                ));
            }
            $base['children'] = $children;
        }

        $result[] = $base;
    }
    return $result;
}

function lfp_sanitize_socials(array $socials, array $uploaded): array
{
    $platforms = lfp_get_social_platforms();
    $result = [];
    foreach ($socials as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = lfp_sanitize_id((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $platform = (string) ($entry['platform'] ?? '');
        if (!isset($platforms[$platform])) {
            continue;
        }
        $source = ($entry['source'] ?? 'url') === 'keyword' ? 'keyword' : 'url';
        $clean = [
            'id'       => $id,
            'platform' => $platform,
            'source'   => $source,
            'url'      => '',
            'keyword'  => '',
            'label'    => trim((string) ($entry['label'] ?? '')),
        ];
        if ($source === 'keyword') {
            $kw = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($entry['keyword'] ?? ''));
            $clean['keyword'] = is_string($kw) ? $kw : '';
            if ($clean['keyword'] === '') {
                continue;
            }
        } else {
            $url = trim((string) ($entry['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $clean['url'] = $url;
        }
        $result[] = $clean;
    }
    return $result;
}

/* ---------------------------------------------------------------------------
 * File upload handling
 * ------------------------------------------------------------------------ */

function lfp_process_uploaded_files(): array
{
    $result = [];
    if (empty($_FILES) || !is_array($_FILES)) {
        return $result;
    }

    $uploads_dir = lfp_uploads_dir();
    $uploads_url = lfp_uploads_url();

    $allowed = [
        'image/jpeg'    => 'jpg',
        'image/pjpeg'   => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $max_size = 5 * 1024 * 1024;

    foreach ($_FILES as $key => $file) {
        if (!is_array($file)) {
            continue;
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $max_size) {
            continue;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            continue;
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmp);
            if (is_string($detected)) {
                $mime = $detected;
            }
        }
        if ($mime === '') {
            $mime = (string) ($file['type'] ?? '');
        }

        if (!isset($allowed[$mime])) {
            continue;
        }

        $ext = $allowed[$mime];
        try {
            $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        } catch (\Throwable) {
            $filename = uniqid('lfp_', true) . '.' . $ext;
        }
        $dest = $uploads_dir . '/' . $filename;

        if (move_uploaded_file($tmp, $dest)) {
            @chmod($dest, 0644);
            $result[(string) $key] = $uploads_url . $filename;
        }
    }
    return $result;
}

/* ---------------------------------------------------------------------------
 * Data helpers used by the views
 * ------------------------------------------------------------------------ */

function lfp_get_link_data(string $keyword): ?array
{
    if (!yourls_keyword_is_taken($keyword)) {
        return null;
    }
    $url   = yourls_get_keyword_longurl($keyword);
    $title = yourls_get_keyword_title($keyword);
    if (!is_string($url) || $url === '') {
        return null;
    }
    return [
        'keyword'   => $keyword,
        'long_url'  => $url,
        'short_url' => yourls_link($keyword),
        'title'     => is_string($title) ? $title : '',
    ];
}

function lfp_get_all_yourls_links(): array
{
    global $ydb;
    $table = defined('YOURLS_DB_TABLE_URL') ? YOURLS_DB_TABLE_URL : 'yourls_url';

    try {
        $rows = $ydb->fetchObjects("SELECT keyword, url, title FROM `$table` ORDER BY timestamp DESC LIMIT 5000");
    } catch (\Throwable) {
        return [];
    }

    if (!is_array($rows)) {
        return [];
    }
    return $rows;
}

function lfp_resolve_link(array $item): array
{
    $keyword = (string) ($item['keyword'] ?? '');
    $data    = $keyword !== '' ? lfp_get_link_data($keyword) : null;

    $custom_title = trim((string) ($item['title'] ?? ''));
    $title        = $custom_title !== '' ? $custom_title : ($data['title'] ?? '');
    if ($title === '' && $data !== null) {
        $title = $data['long_url'];
    }
    if ($title === '') {
        $title = $keyword;
    }

    return [
        'id'          => (string) ($item['id'] ?? ''),
        'keyword'     => $keyword,
        'title'       => $title,
        'description' => (string) ($item['description'] ?? ''),
        'image'       => (string) ($item['image'] ?? ''),
        'short_url'   => $data['short_url'] ?? (YOURLS_SITE . '/' . $keyword),
        'long_url'    => $data['long_url']  ?? '',
        'exists'      => $data !== null,
    ];
}

/* ---------------------------------------------------------------------------
 * Social platforms
 * ------------------------------------------------------------------------ */

function lfp_get_social_platforms(): array
{
    static $platforms = null;
    if ($platforms === null) {
        $loaded = require LFP_DIR . '/includes/social-platforms.php';
        $platforms = is_array($loaded) ? $loaded : [];
    }
    return $platforms;
}

function lfp_render_social_icon(string $platform_key): string
{
    $platforms = lfp_get_social_platforms();
    if (!isset($platforms[$platform_key])) {
        return '';
    }
    $svg = $platforms[$platform_key]['svg'];
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $svg . '</svg>';
}

function lfp_resolve_social(array $entry): ?array
{
    $platforms = lfp_get_social_platforms();
    $platform  = (string) ($entry['platform'] ?? '');
    if (!isset($platforms[$platform])) {
        return null;
    }
    $source = ($entry['source'] ?? 'url') === 'keyword' ? 'keyword' : 'url';
    $url    = '';

    if ($source === 'keyword') {
        $keyword = (string) ($entry['keyword'] ?? '');
        if ($keyword === '' || !yourls_keyword_is_taken($keyword)) {
            return null;
        }
        $url = yourls_link($keyword);
    } else {
        $url = trim((string) ($entry['url'] ?? ''));
        if ($url === '') {
            return null;
        }
    }

    $label = trim((string) ($entry['label'] ?? ''));
    if ($label === '') {
        $label = $platforms[$platform]['name'];
    }

    return [
        'platform' => $platform,
        'name'     => $platforms[$platform]['name'],
        'color'    => $platforms[$platform]['color'],
        'url'      => $url,
        'label'    => $label,
    ];
}

