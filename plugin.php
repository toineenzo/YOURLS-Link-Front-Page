<?php
/*
Plugin Name: Link Front Page
Plugin URI: https://github.com/toineenzo/YOURLS-Link-Front-Page
Description: Show selected shortlinks as a Linktree-style link list on the YOURLS homepage. Group links into category boxes, reorder by drag and drop, and customize each entry with an image, title and description.
Version: 1.0.0
Author: toineenzo
Author URI: https://github.com/toineenzo
*/

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

const LFP_VERSION = '1.0.0';
const LFP_DIR = __DIR__;
const LFP_OPT_ITEMS = 'lfp_items';
const LFP_OPT_GENERAL = 'lfp_general';
const LFP_OPT_APPEARANCE = 'lfp_appearance';
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
        'enabled'          => true,
        'site_title'       => '',
        'site_description' => '',
        'site_logo'        => '',
        'login_path'       => 'login',
        'show_footer'      => true,
    ];
}

function lfp_default_appearance(): array
{
    return [
        'background_color' => '#0f172a',
        'background_image' => '',
        'text_color'       => '#f1f5f9',
        'muted_color'      => '#94a3b8',
        'card_background'  => '#1e293b',
        'card_hover'       => '#334155',
        'accent_color'     => '#3b82f6',
        'border_radius'    => '16',
        'font_family'      => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'custom_css'       => '',
    ];
}

function lfp_get_general(): array
{
    $stored = yourls_get_option(LFP_OPT_GENERAL, []);
    if (!is_array($stored)) {
        $stored = [];
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

/* ---------------------------------------------------------------------------
 * Routing: intercept "/" and "/<login_path>" before YOURLS routes them
 * ------------------------------------------------------------------------ */

yourls_add_action('plugins_loaded', 'lfp_handle_routing');

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

    $request = (string) yourls_get_request();
    $request = (string) strtok($request, '?');
    $request = trim($request, '/');

    $login_path = trim((string) ($general['login_path'] ?? 'login'), '/');
    if ($login_path !== '' && $request === $login_path) {
        yourls_redirect(yourls_admin_url(), 302);
        exit;
    }

    $is_root_request = ($request === '' || $request === 'index.php');
    if (!$is_root_request) {
        return;
    }

    // Make sure we are really on the public root, not in /admin/
    if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) {
        return;
    }

    lfp_render_frontend();
    exit;
}

function lfp_render_frontend(): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
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

    $general = [
        'enabled'          => isset($_POST['enabled']),
        'site_title'       => trim((string) ($_POST['site_title'] ?? '')),
        'site_description' => trim((string) ($_POST['site_description'] ?? '')),
        'site_logo'        => $uploaded['site_logo'] ?? trim((string) ($_POST['site_logo'] ?? '')),
        'login_path'       => trim((string) ($_POST['login_path'] ?? 'login'), '/'),
        'show_footer'      => isset($_POST['show_footer']),
    ];
    yourls_update_option(LFP_OPT_GENERAL, $general);

    $appearance = [
        'background_color' => lfp_sanitize_color((string) ($_POST['background_color'] ?? '#0f172a')),
        'background_image' => $uploaded['background_image'] ?? trim((string) ($_POST['background_image'] ?? '')),
        'text_color'       => lfp_sanitize_color((string) ($_POST['text_color'] ?? '#f1f5f9')),
        'muted_color'      => lfp_sanitize_color((string) ($_POST['muted_color'] ?? '#94a3b8')),
        'card_background'  => lfp_sanitize_color((string) ($_POST['card_background'] ?? '#1e293b')),
        'card_hover'       => lfp_sanitize_color((string) ($_POST['card_hover'] ?? '#334155')),
        'accent_color'     => lfp_sanitize_color((string) ($_POST['accent_color'] ?? '#3b82f6')),
        'border_radius'    => (string) max(0, min(64, (int) ($_POST['border_radius'] ?? 16))),
        'font_family'      => trim((string) ($_POST['font_family'] ?? '')),
        'custom_css'       => (string) ($_POST['custom_css'] ?? ''),
    ];
    yourls_update_option(LFP_OPT_APPEARANCE, $appearance);

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

