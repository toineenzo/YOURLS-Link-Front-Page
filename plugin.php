<?php
/*
Plugin Name: Link Front Page
Plugin URI: https://github.com/toineenzo/YOURLS-Link-Front-Page
Description: Show selected shortlinks as a Linktree-style link list on the YOURLS homepage. Group links into category boxes, reorder by drag and drop, and customize each entry with an image, title and description.
Version: 2.3.0
Author: Toine Rademacher (toineenzo)
Author URI: https://toine.click
*/

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

const LFP_VERSION = '2.3.0';
const LFP_DIR = __DIR__;
const LFP_OPT_ITEMS = 'lfp_items';
const LFP_OPT_GENERAL = 'lfp_general';
const LFP_OPT_APPEARANCE = 'lfp_appearance';
const LFP_OPT_IMAGE_GRID = 'lfp_image_grid';
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
        'show_login_link'    => true,
        'show_powered_by'    => true,
        'powered_by_text'    => '',
        'powered_by_url'     => '',
        'footer_custom_html' => '',

        // About-me section
        'about_enabled'     => false,
        'about_image'       => '',
        'about_text'        => '',
        'about_socials'     => [],

        // Optional contact card data (Personal + Business). Each tab keeps
        // the same shape so the VCF generator can swap between them.
        'about_personal' => [
            'enabled' => false,
            'name'    => '',
            'phone'   => '',
            'email'   => '',
            'website' => '',
            'address' => '',
        ],
        'about_business' => [
            'enabled' => false,
            'name'    => '',
            'phone'   => '',
            'email'   => '',
            'website' => '',
            'address' => '',
        ],
    ];
}

function lfp_default_appearance(): array
{
    return [
        // Colors
        'background_color'      => '#0f172a',
        'background_image'      => '',
        'background_size'       => 'cover',     // cover | contain | auto | 100% 100%
        'background_repeat'     => 'no-repeat', // no-repeat | repeat | repeat-x | repeat-y | space | round
        'background_position'   => 'center',    // center | top | bottom | left | right | top left | …
        'background_attachment' => 'fixed',     // fixed | scroll | local
        'text_color'            => '#f1f5f9',
        'muted_color'         => '#94a3b8',
        'card_background'     => '#1e293b',
        'card_hover'          => '#334155',
        'accent_color'        => '#3b82f6',

        // Sizing & spacing — strings with CSS units. Plain numbers entered
        // by the user are coerced to "Npx" via lfp_sanitize_size().
        'border_radius'       => '16px',
        'page_max_width'      => '640px',
        'page_padding_top'    => '56px',
        'page_padding_bottom' => '80px',
        'page_padding_x'      => '20px',
        'card_gap'            => '14px',
        'card_padding_y'      => '14px',
        'card_padding_x'      => '18px',
        'icon_size'           => '44px',
        'about_photo_size'    => '120px',

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

function lfp_default_image_grid(): array
{
    return [
        'enabled'       => false,
        'visible_count' => 3,
        'items'         => [],
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

    // Migrate legacy spacing values that were stored as bare integers (pre-2.1
    // fields used <input type="number">) to "Npx" strings.
    $legacy_size_keys = [
        'border_radius', 'page_max_width', 'page_padding_top',
        'page_padding_bottom', 'page_padding_x', 'card_gap',
        'card_padding_y', 'card_padding_x', 'icon_size', 'about_photo_size',
    ];
    foreach ($legacy_size_keys as $key) {
        if (isset($stored[$key])
            && is_string($stored[$key])
            && preg_match('/^-?\d+(\.\d+)?$/', $stored[$key]) === 1
        ) {
            $stored[$key] = $stored[$key] . 'px';
        }
    }

    return array_merge(lfp_default_appearance(), $stored);
}

function lfp_get_items(): array
{
    $stored = yourls_get_option(LFP_OPT_ITEMS, []);
    return is_array($stored) ? $stored : [];
}

function lfp_get_image_grid(): array
{
    $stored = yourls_get_option(LFP_OPT_IMAGE_GRID, false);

    // Migrate from the v2.x storage key (lfp_instagram) to the renamed
    // lfp_image_grid key. Once migrated the legacy option is deleted so
    // the two never drift apart.
    if ($stored === false) {
        $legacy = yourls_get_option('lfp_instagram', false);
        if (is_array($legacy)) {
            yourls_update_option(LFP_OPT_IMAGE_GRID, $legacy);
            if (function_exists('yourls_delete_option')) {
                yourls_delete_option('lfp_instagram');
            }
            $stored = $legacy;
        }
    }

    if (!is_array($stored)) {
        $stored = [];
    }
    $merged = array_merge(lfp_default_image_grid(), $stored);
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

/**
 * Parsedown lazy loader. Lets us run untrusted-by-author markdown through
 * a real parser instead of regex hacks. The class itself escapes raw HTML
 * by default; we deliberately allow inline HTML because the admin (the
 * author) is trusted on this surface — same security posture as the
 * Custom CSS / Custom footer HTML fields.
 */
function lfp_parsedown(): \Parsedown
{
    static $instance = null;
    if ($instance === null) {
        require_once LFP_DIR . '/includes/Parsedown.php';
        $instance = new \Parsedown();
        $instance->setMarkupEscaped(false);
        $instance->setBreaksEnabled(true);
        $instance->setSafeMode(false);
    }
    return $instance;
}

/**
 * Render multi-line text that may contain Markdown and/or HTML. Used for
 * descriptions, About-me text, image overlay titles, etc.
 */
function lfp_render_text(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    return lfp_parsedown()->text($text);
}

/**
 * Render a single-line piece of text — wraps the result in NO block tag,
 * so this is safe to drop inside an existing <h1>, <span>, etc. Used for
 * site title, tagline, link / category titles.
 */
function lfp_render_inline(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    return lfp_parsedown()->line($text);
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

    // /contact.vcf?type=personal|business — serves the matching contact card
    // as a downloadable vCard 3.0.
    if ($request === 'contact.vcf') {
        lfp_serve_vcard($general);
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

function lfp_serve_vcard(array $general): void
{
    $type    = ($_GET['type'] ?? 'personal') === 'business' ? 'business' : 'personal';
    $contact = is_array($general['about_' . $type] ?? null) ? $general['about_' . $type] : [];
    $org     = $type === 'business' ? trim((string) ($contact['name'] ?? '')) : null;
    $vcard   = lfp_build_vcard($contact, $org);

    if ($vcard === null) {
        if (!headers_sent()) {
            header('HTTP/1.1 404 Not Found');
        }
        echo 'Contact not configured.';
        return;
    }

    $filename = ($contact['name'] !== '' ? preg_replace('/[^a-zA-Z0-9._-]/', '_', $contact['name']) : 'contact') . '.vcf';
    if (!headers_sent()) {
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($vcard));
    }
    echo $vcard;
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
 * Admin: row action in /admin/index.php — adds a link directly to the bottom
 * of the homepage link list with a single click.
 * ------------------------------------------------------------------------ */

yourls_add_filter('table_add_row_action_array', 'lfp_table_row_action', 10, 2);
yourls_add_action('plugins_loaded',             'lfp_handle_admin_quickadd');
yourls_add_action('html_head',                  'lfp_admin_quickadd_styles');

/**
 * Sign a keyword for the quick-add row action. We can't use yourls_*nonce()
 * here because the YOURLS_USER constant is set by yourls_maybe_require_auth()
 * which runs *after* our plugins_loaded handler, so the salt would differ
 * between create-time (table render, user is set) and verify-time (early
 * hook, user not set). Instead, we sign with the site-wide cookie key —
 * available everywhere YOURLS runs, secret to operators.
 */
function lfp_quickadd_sign(string $keyword): string
{
    $secret = defined('YOURLS_COOKIEKEY') ? YOURLS_COOKIEKEY : 'lfp-fallback';
    return substr(hash_hmac('sha256', 'lfp_quickadd|' . $keyword, (string) $secret), 0, 16);
}

function lfp_table_row_action(array $actions, string $keyword): array
{
    if (!yourls_keyword_is_taken($keyword)) {
        return $actions;
    }

    $in_list = isset(lfp_listed_keywords()[$keyword]);

    if ($in_list) {
        $anchor = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        $actions['lfp_add'] = [
            'href'   => '#',
            'id'     => 'lfp_add_' . $keyword,
            'title'  => 'Already on the Link Front Page homepage',
            'anchor' => $anchor,
        ];
        return $actions;
    }

    $sig    = lfp_quickadd_sign($keyword);
    $href   = yourls_admin_url('index.php')
            . '?lfp_quickadd=' . urlencode($keyword)
            . '&lfp_nonce='    . urlencode($sig);
    $anchor = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';

    $actions['lfp_add'] = [
        'href'   => $href,
        'id'     => 'lfp_add_' . $keyword,
        'title'  => 'Add to Link Front Page homepage',
        'anchor' => $anchor,
    ];
    return $actions;
}

/**
 * Quick-add handler. Hooks plugins_loaded so it runs before the admin page
 * starts emitting HTML, then redirects back to /admin/index.php with a
 * success/duplicate flag.
 */
function lfp_handle_admin_quickadd(): void
{
    if (!yourls_is_admin()) {
        return;
    }
    if (!isset($_GET['lfp_quickadd'])) {
        return;
    }

    // Bail anonymous visitors — only authenticated admins should mutate the
    // homepage list. yourls_is_valid_user() reads the YOURLS auth cookie and
    // returns true / false (or an error string); cast to bool keeps us safe.
    if (function_exists('yourls_is_valid_user') && yourls_is_valid_user() !== true) {
        yourls_redirect(yourls_admin_url(), 302);
        exit;
    }

    $keyword = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['lfp_quickadd']);
    $sig     = (string) ($_GET['lfp_nonce'] ?? '');

    if (!is_string($keyword) || $keyword === ''
        || !hash_equals(lfp_quickadd_sign($keyword), $sig)
        || !yourls_keyword_is_taken($keyword)
    ) {
        yourls_redirect(yourls_admin_url('index.php'), 302);
        exit;
    }

    $items   = lfp_get_items();
    $listed  = lfp_listed_keywords();
    $already = isset($listed[$keyword]);

    if (!$already) {
        try {
            $id = 'link_' . bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $id = 'link_' . substr(md5(uniqid('', true)), 0, 8);
        }
        $items[] = [
            'id'          => $id,
            'type'        => 'link',
            'keyword'     => $keyword,
            'title'       => '',
            'description' => '',
            'image'       => '',
        ];
        yourls_update_option(LFP_OPT_ITEMS, $items);
    }

    $flag = $already ? 'lfp_dup' : 'lfp_added';
    yourls_redirect(yourls_admin_url('index.php?' . $flag . '=' . urlencode($keyword)), 302);
    exit;
}

/**
 * Tiny CSS injection in admin so the quick-add icon button blends in with
 * the existing row-action buttons regardless of the active YOURLS theme,
 * plus a brief flash banner when a quick-add just happened.
 */
function lfp_admin_quickadd_styles(): void
{
    if (!yourls_is_admin()) {
        return;
    }

    $added = isset($_GET['lfp_added']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['lfp_added']) : '';
    $dup   = isset($_GET['lfp_dup'])   ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['lfp_dup'])   : '';

    echo '<style>
        /* Quick-add row action — match the standard YOURLS / Sleeky action
           buttons (Stats, Share, Edit, Delete): same colored square with a
           white icon. Works in both light and dark mode because the button
           carries its own background and foreground. */
        a.button_lfp_add {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 28px !important;
            height: 28px !important;
            margin: 0 2px !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 4px !important;
            background: #3b82f6 !important;
            color: #ffffff !important;
            text-decoration: none !important;
            vertical-align: middle !important;
            box-shadow: none !important;
            transition: background 120ms ease, transform 120ms ease;
            box-sizing: border-box !important;
            line-height: 1 !important;
        }
        a.button_lfp_add:hover {
            background: #2563eb !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }
        a.button_lfp_add:active {
            transform: translateY(0);
        }
        a.button_lfp_add svg {
            width: 14px !important;
            height: 14px !important;
            display: block !important;
            color: #ffffff !important;
            stroke: #ffffff !important;
            fill: none !important;
            background: transparent !important;
        }
        /* Already-listed state — uses a distinct title attribute we set
           server-side so we can style without injecting a custom class. */
        a.button_lfp_add[title^="Already"] {
            background: #10b981 !important;
            cursor: default !important;
        }
        a.button_lfp_add[title^="Already"]:hover {
            background: #10b981 !important;
            transform: none;
        }

        .lfp-quickadd-flash {
            position: fixed; top: 12px; left: 50%; transform: translateX(-50%);
            background: #1e293b; color: #f1f5f9; padding: 10px 18px;
            border-radius: 6px; font-size: 0.9rem; z-index: 99999;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3); border-left: 3px solid #3b82f6;
            animation: lfp-flash 4s ease forwards;
        }
        .lfp-quickadd-flash.is-dup { border-left-color: #f59e0b; }
        @keyframes lfp-flash {
            0%   { opacity: 0; transform: translate(-50%, -16px); }
            10%  { opacity: 1; transform: translate(-50%, 0); }
            85%  { opacity: 1; transform: translate(-50%, 0); }
            100% { opacity: 0; transform: translate(-50%, -16px); pointer-events: none; }
        }
    </style>';

    if ($added !== '') {
        echo '<script>document.addEventListener("DOMContentLoaded", () => {'
           . 'const f=document.createElement("div");f.className="lfp-quickadd-flash";'
           . 'f.textContent=' . json_encode('Added "' . $added . '" to the Link Front Page homepage list.') . ';'
           . 'document.body.appendChild(f);setTimeout(()=>f.remove(),4500);'
           . '});</script>';
    } elseif ($dup !== '') {
        echo '<script>document.addEventListener("DOMContentLoaded", () => {'
           . 'const f=document.createElement("div");f.className="lfp-quickadd-flash is-dup";'
           . 'f.textContent=' . json_encode('"' . $dup . '" was already on the Link Front Page homepage list.') . ';'
           . 'document.body.appendChild(f);setTimeout(()=>f.remove(),4500);'
           . '});</script>';
    }
}

/**
 * Returns a [keyword => true] map of every keyword currently on the homepage
 * link list (top-level links and links nested inside categories).
 */
function lfp_listed_keywords(): array
{
    static $set = null;
    if ($set !== null) {
        return $set;
    }
    $set = [];
    $walk = function (array $list) use (&$walk, &$set): void {
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['type'] ?? '') === 'link') {
                $kw = (string) ($item['keyword'] ?? '');
                if ($kw !== '') {
                    $set[$kw] = true;
                }
            }
            if (($item['type'] ?? '') === 'category' && is_array($item['children'] ?? null)) {
                $walk($item['children']);
            }
        }
    };
    $walk(lfp_get_items());
    return $set;
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

        'show_login_link'    => isset($_POST['show_login_link']),
        'show_powered_by'    => isset($_POST['show_powered_by']),
        'powered_by_text'    => trim((string) ($_POST['powered_by_text'] ?? '')),
        'powered_by_url'     => trim((string) ($_POST['powered_by_url'] ?? '')),
        'footer_custom_html' => (string) ($_POST['footer_custom_html'] ?? ''),

        'about_enabled'     => isset($_POST['about_enabled']),
        'about_image'       => $uploaded['about_image'] ?? trim((string) ($_POST['about_image'] ?? '')),
        'about_text'        => (string) ($_POST['about_text'] ?? ''),
        'about_socials'     => lfp_sanitize_socials($socials_raw, $uploaded),
        'about_personal'    => lfp_sanitize_contact('personal'),
        'about_business'    => lfp_sanitize_contact('business'),
    ];
    yourls_update_option(LFP_OPT_GENERAL, $general);

    $font_source_raw = (string) ($_POST['font_source'] ?? 'system');
    $font_source = in_array($font_source_raw, ['system', 'google', 'custom'], true) ? $font_source_raw : 'system';

    $custom_font = lfp_process_uploaded_font();
    $existing_appearance = lfp_get_appearance();
    $custom_url    = $custom_font['url']    ?? trim((string) ($_POST['font_custom_url']    ?? $existing_appearance['font_custom_url']));
    $custom_format = $custom_font['format'] ?? trim((string) ($_POST['font_custom_format'] ?? $existing_appearance['font_custom_format']));

    $bg_size_opts       = ['cover', 'contain', 'auto', '100% 100%'];
    $bg_repeat_opts     = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round'];
    $bg_position_opts   = ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'];
    $bg_attachment_opts = ['fixed', 'scroll', 'local'];

    $bg_size       = (string) ($_POST['background_size']       ?? 'cover');
    $bg_repeat     = (string) ($_POST['background_repeat']     ?? 'no-repeat');
    $bg_position   = (string) ($_POST['background_position']   ?? 'center');
    $bg_attachment = (string) ($_POST['background_attachment'] ?? 'fixed');

    $appearance = [
        'background_color'      => lfp_sanitize_color((string) ($_POST['background_color'] ?? '#0f172a')),
        'background_image'      => $uploaded['background_image'] ?? trim((string) ($_POST['background_image'] ?? '')),
        'background_size'       => in_array($bg_size,       $bg_size_opts,       true) ? $bg_size       : 'cover',
        'background_repeat'     => in_array($bg_repeat,     $bg_repeat_opts,     true) ? $bg_repeat     : 'no-repeat',
        'background_position'   => in_array($bg_position,   $bg_position_opts,   true) ? $bg_position   : 'center',
        'background_attachment' => in_array($bg_attachment, $bg_attachment_opts, true) ? $bg_attachment : 'fixed',
        'text_color'            => lfp_sanitize_color((string) ($_POST['text_color'] ?? '#f1f5f9')),
        'muted_color'         => lfp_sanitize_color((string) ($_POST['muted_color'] ?? '#94a3b8')),
        'card_background'     => lfp_sanitize_color((string) ($_POST['card_background'] ?? '#1e293b')),
        'card_hover'          => lfp_sanitize_color((string) ($_POST['card_hover'] ?? '#334155')),
        'accent_color'        => lfp_sanitize_color((string) ($_POST['accent_color'] ?? '#3b82f6')),

        'border_radius'       => lfp_sanitize_size((string) ($_POST['border_radius']       ?? '16px'),  '16px'),
        'page_max_width'      => lfp_sanitize_size((string) ($_POST['page_max_width']      ?? '640px'), '640px'),
        'page_padding_top'    => lfp_sanitize_size((string) ($_POST['page_padding_top']    ?? '56px'),  '56px'),
        'page_padding_bottom' => lfp_sanitize_size((string) ($_POST['page_padding_bottom'] ?? '80px'),  '80px'),
        'page_padding_x'      => lfp_sanitize_size((string) ($_POST['page_padding_x']      ?? '20px'),  '20px'),
        'card_gap'            => lfp_sanitize_size((string) ($_POST['card_gap']            ?? '14px'),  '14px'),
        'card_padding_y'      => lfp_sanitize_size((string) ($_POST['card_padding_y']      ?? '14px'),  '14px'),
        'card_padding_x'      => lfp_sanitize_size((string) ($_POST['card_padding_x']      ?? '18px'),  '18px'),
        'icon_size'           => lfp_sanitize_size((string) ($_POST['icon_size']           ?? '44px'),  '44px'),
        'about_photo_size'    => lfp_sanitize_size((string) ($_POST['about_photo_size']    ?? '120px'), '120px'),

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

    // Image grid
    $imgrid_json = (string) ($_POST['image_grid_json'] ?? '[]');
    $imgrid_raw  = json_decode($imgrid_json, true);
    if (!is_array($imgrid_raw)) {
        $imgrid_raw = [];
    }
    $visible = max(1, min(60, (int) ($_POST['image_grid_visible_count'] ?? 3)));
    $image_grid = [
        'enabled'       => isset($_POST['image_grid_enabled']),
        'visible_count' => $visible,
        'items'         => lfp_sanitize_image_grid($imgrid_raw, $uploaded),
    ];
    yourls_update_option(LFP_OPT_IMAGE_GRID, $image_grid);

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
    $scope = (string) ($_POST['reset_scope'] ?? 'all');

    switch ($scope) {
        case 'links':
            yourls_update_option(LFP_OPT_ITEMS, []);
            break;
        case 'general':
            yourls_update_option(LFP_OPT_GENERAL, lfp_default_general());
            break;
        case 'image_grid':
            $img = lfp_get_image_grid();
            $img['items'] = [];
            yourls_update_option(LFP_OPT_IMAGE_GRID, $img);
            break;
        case 'appearance':
            yourls_update_option(LFP_OPT_APPEARANCE, lfp_default_appearance());
            break;
        case 'all':
        default:
            yourls_update_option(LFP_OPT_GENERAL,   lfp_default_general());
            yourls_update_option(LFP_OPT_APPEARANCE, lfp_default_appearance());
            yourls_update_option(LFP_OPT_ITEMS,     []);
            yourls_update_option(LFP_OPT_IMAGE_GRID, lfp_default_image_grid());
            break;
    }

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
 * Pull a Personal/Business contact block out of $_POST. Field names are
 * "about_<scope>_<field>", e.g. about_personal_phone.
 */
function lfp_sanitize_contact(string $scope): array
{
    $prefix = 'about_' . $scope . '_';
    return [
        'enabled' => isset($_POST[$prefix . 'enabled']),
        'name'    => trim((string) ($_POST[$prefix . 'name']    ?? '')),
        'phone'   => trim((string) ($_POST[$prefix . 'phone']   ?? '')),
        'email'   => trim((string) ($_POST[$prefix . 'email']   ?? '')),
        'website' => trim((string) ($_POST[$prefix . 'website'] ?? '')),
        'address' => trim((string) ($_POST[$prefix . 'address'] ?? '')),
    ];
}

/**
 * Build a vCard 3.0 payload from a sanitized contact array. Returns null
 * when the contact is empty / disabled.
 */
function lfp_build_vcard(array $contact, ?string $org_label = null): ?string
{
    $name    = trim((string) ($contact['name']    ?? ''));
    $phone   = trim((string) ($contact['phone']   ?? ''));
    $email   = trim((string) ($contact['email']   ?? ''));
    $website = trim((string) ($contact['website'] ?? ''));
    $address = trim((string) ($contact['address'] ?? ''));

    if ($name === '' && $phone === '' && $email === '' && $website === '' && $address === '') {
        return null;
    }

    $escape = static fn(string $v): string => str_replace(
        ["\\", ',', ';', "\r\n", "\n"],
        ['\\\\', '\\,', '\\;', '\\n', '\\n'],
        $v,
    );

    $lines   = [];
    $lines[] = 'BEGIN:VCARD';
    $lines[] = 'VERSION:3.0';
    if ($name !== '') {
        $lines[] = 'FN:'  . $escape($name);
        // Best-effort split: last word is family name, the rest given name.
        $parts  = preg_split('/\s+/', $name) ?: [$name];
        $family = count($parts) > 1 ? array_pop($parts) : '';
        $given  = implode(' ', $parts);
        $lines[] = 'N:' . $escape($family) . ';' . $escape($given) . ';;;';
    }
    if ($org_label !== null && $org_label !== '') {
        $lines[] = 'ORG:' . $escape($org_label);
    }
    if ($phone   !== '') $lines[] = 'TEL;TYPE=' . ($org_label ? 'WORK' : 'CELL') . ',VOICE:' . $escape($phone);
    if ($email   !== '') $lines[] = 'EMAIL;TYPE=' . ($org_label ? 'WORK' : 'HOME') . ',INTERNET:' . $escape($email);
    if ($website !== '') $lines[] = 'URL:' . $escape($website);
    if ($address !== '') {
        // Single-line ADR: ;;<street>;;;;
        $lines[] = 'ADR;TYPE=' . ($org_label ? 'WORK' : 'HOME') . ':;;' . $escape($address) . ';;;;';
    }
    $lines[] = 'END:VCARD';

    return implode("\r\n", $lines) . "\r\n";
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

function lfp_sanitize_image_grid(array $items, array $uploaded): array
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

        $image = $uploaded['imgrid_' . $id] ?? trim((string) ($item['image'] ?? ''));
        // Bulk uploads and dialog uploads arrive as data: URLs in the items
        // JSON. Convert them to real files in uploads/ so the option blob
        // doesn't fill up with multi-megabyte base64 strings.
        if (str_starts_with($image, 'data:image/')) {
            $saved = lfp_save_data_url_image($image);
            if ($saved !== null) {
                $image = $saved;
            }
        }

        $clean = [
            'id'        => $id,
            'source'    => $source,
            'url'       => '',
            'keyword'   => '',
            'image'     => $image,
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
            // Tiles without a destination are kept (the user can fill the
            // URL/keyword in later); the public page filters them out via
            // lfp_resolve_image_grid() until both pieces are present.
        } else {
            $clean['url'] = trim((string) ($item['url'] ?? ''));
        }
        if ($clean['image'] === '') {
            continue; // grid items always need an image
        }
        $result[] = $clean;
    }
    return $result;
}

/**
 * Decode a data: URL produced by FileReader.readAsDataURL and persist it as
 * a real file under uploads/. Returns the public URL or null on failure.
 */
function lfp_save_data_url_image(string $data_url): ?string
{
    if (preg_match('#^data:(image/(jpeg|png|gif|webp|svg\+xml));base64,(.+)$#', $data_url, $m) !== 1) {
        return null;
    }
    $mime = $m[1];
    $bin  = base64_decode($m[3], true);
    if ($bin === false || $bin === '' || strlen($bin) > 5 * 1024 * 1024) {
        return null;
    }
    $ext_map = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $ext = $ext_map[$mime] ?? 'bin';

    try {
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    } catch (\Throwable) {
        $filename = uniqid('lfp_', true) . '.' . $ext;
    }
    $dest = lfp_uploads_dir() . '/' . $filename;
    if (file_put_contents($dest, $bin) === false) {
        return null;
    }
    @chmod($dest, 0644);
    return lfp_uploads_url() . $filename;
}

function lfp_resolve_image_grid(array $entry): ?array
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

