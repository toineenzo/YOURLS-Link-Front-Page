# YOURLS — Link Front Page

> Turn the YOURLS homepage into a personal landing page: a Linktree-style link list, an Instagram-style image grid, an About-me block with social buttons and downloadable contact cards, and full design controls — without leaving your YOURLS install.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4+-purple.svg)](#requirements)
[![YOURLS](https://img.shields.io/badge/YOURLS-1.9+-orange.svg)](https://yourls.org)
[![Latest release](https://img.shields.io/github/v/release/toineenzo/YOURLS-Link-Front-Page?display_name=tag)](https://github.com/toineenzo/YOURLS-Link-Front-Page/releases)

🌐 **Live demo**: <https://toine.click>

---

## ✨ Features

### Personal landing page
- **Linktree-style link list** with category boxes, drag-and-drop ordering and per-link image / title / description.
- **About-me section** with profile photo, bio, and a row of icon-only social buttons.
- **37 social platforms** bundled inline (CC0, [simple-icons](https://simpleicons.org)) — X, Instagram, Facebook, TikTok, YouTube, LinkedIn, GitHub, GitLab, Reddit, Discord, Telegram, WhatsApp, Snapchat, Pinterest, Twitch, Spotify, SoundCloud, Mastodon, Bluesky, Threads, Patreon, Ko-fi, Buy Me a Coffee, PayPal, Signal, Dribbble, Behance, Medium, Substack, Dev.to, Stack Overflow, Product Hunt, Steam — plus generic Website / Email / RSS / Phone icons.
- **Contact cards** (Personal + Business): name, phone, email, website, address. Each card is downloadable as a vCard 3.0 (`.vcf`) file via `/contact.vcf?type=personal|business`.
- **Image grid** — 3-column gallery for "link in bio" content. Each tile has its own image, optional overlay title and configurable show-mode (always / hover / never), points to a free URL or a YOURLS shortlink (so click-tracking still works), and supports bulk image upload.
- **Markdown + HTML** in titles, descriptions, About-me text, image overlays and the custom footer block (parsed by [Parsedown](https://github.com/erusev/parsedown)).

### Quick-add from the YOURLS link manager
Every row in `/admin/index.php` grows a small **+** button next to the standard Stats / Share / Edit / Delete actions. One click adds the shortlink to the bottom of the homepage list. The icon flips to a green ✓ once the keyword is on the list.

### Full design controls

**Colors** (HTML5 color pickers): background, text, muted, card, card-hover, accent, plus optional background image with `size` / `repeat` / `position` / `attachment` selects.

**Spacing & sizing**: page max width, page padding (top / bottom / sides), gap between cards, card padding, link icon size, about-photo size, border radius. Each field accepts any CSS length: `px`, `%`, `em`, `rem`, `vh`, `vw`, plus `clamp()`, `calc()`, `min()`, `max()`. Bare numbers become `px`.

**Typography**: pick the font source —
- **System** — free-text CSS font-family stack.
- **Google Fonts** — bundled curated list of 85+ popular families with live search and live preview; loaded from `fonts.googleapis.com` at render time; weights configurable.
- **Custom upload** — `.woff2` / `.woff` / `.ttf` / `.otf` (max 5 MB), stored under `uploads/fonts/` with a random hex filename and emitted as `@font-face`.

Five separate size fields (site title, subtitle, category title, link title, body) accept the same units as Spacing & sizing.

**Custom CSS** escape hatch.

### Robust routing

Hooks **three** YOURLS entry points so the homepage interception always fires:

| Hook | Catches |
| --- | --- |
| `plugins_loaded` | Direct `index.php` hits. |
| `pre_load_template` | `yourls-loader.php` after request resolution. |
| `loader_failed` | Final fallback before YOURLS' 302 to `YOURLS_SITE`. |

Plus a `REQUEST_URI` fallback for stale-cached `yourls_get_request()` and an `X-LFP-Rendered: 1` response header for easy DevTools verification. Configurable login path (defaults to `/login`) redirects to the YOURLS admin via `yourls_redirect()` — no `.htaccess` editing needed.

### Compatibility

- Coexists cleanly with the **Sleeky** YOURLS theme (defensive CSS resets dodge Sleeky's global `nav`, `select`, `input` overrides).
- **PHP 8.4** ready: `declare(strict_types=1)`, typed signatures, `match`, `never`, `str_contains`. Runs on PHP ≥ 8.1.
- Uses native YOURLS APIs throughout: `yourls_get_option`, `yourls_update_option`, `yourls_keyword_is_taken`, `yourls_get_keyword_longurl`, `yourls_get_keyword_title`, `yourls_link`, `yourls_redirect`, `yourls_register_plugin_page`, `yourls_nonce_field`, `yourls_verify_nonce`, `yourls_esc_html`/`_attr`/`_url`.
- **No external runtime dependencies** other than `fonts.googleapis.com` when you pick a Google font. Vanilla JS, native HTML5 drag-and-drop, native `<dialog>`, inline SVG icons, single-file Parsedown.

---

## 📦 Installation

```bash
cd /path/to/yourls/user/plugins
git clone https://github.com/toineenzo/YOURLS-Link-Front-Page.git Link-Front-Page
```

…or download `YOURLS-Link-Front-Page-vX.Y.Z.zip` from the [Releases page](https://github.com/toineenzo/YOURLS-Link-Front-Page/releases) and unzip into `user/plugins/`. The zip already contains a `Link-Front-Page/` folder, so the final path is `user/plugins/Link-Front-Page/`.

Then open the YOURLS admin at `/admin/plugins.php` and click **Activate** on *Link Front Page*. The plugin adds a sub-page under *Manage Plugins → Link Front Page*. Open it and start adding links and categories.

> **Heads-up**: if your YOURLS root is missing `index.php` (e.g. you replaced it with a custom homepage and forgot the file), Apache will return 404 for `/`. Restore the standard YOURLS `index.php` at the root and the plugin will pick it up.

---

## ⚡ Quick tour

| Tab | What it does |
| --- | --- |
| **Links** | Drag-and-drop the link list, add categories, customize each entry. |
| **General** | Site title, login path, About-me section, social-media buttons, contact cards (Personal + Business), footer toggles, custom footer HTML. |
| **Image grid** | 3-column image gallery; bulk upload, per-tile dialog, "Show more" button. |
| **Appearance** | Colors, background image, spacing, typography (system / Google Fonts / custom upload), custom CSS. |

---

## 🗂 File structure

```
Link-Front-Page/
├── plugin.php                   # Plugin entrypoint, hooks, settings I/O,
│                                  routing, vCard generator, upload handling
├── views/
│   ├── frontend.php             # Public landing page rendered at /
│   └── admin.php                # Settings UI rendered inside YOURLS admin
├── assets/
│   ├── frontend.css             # Public styles (CSS custom properties)
│   ├── admin.css                # Admin styles
│   └── admin.js                 # Admin logic (drag/drop, picker, dialogs)
├── includes/
│   ├── google-fonts.php         # Curated Google Fonts list
│   ├── social-platforms.php     # 37 social media platform definitions
│   └── Parsedown.php            # Bundled Markdown parser (CC-BY)
├── uploads/                     # User-uploaded images / fonts (auto-created)
└── README.md
```

---

## ⚙️ Stored options

The plugin uses four YOURLS options:

| Option              | Shape | Notes |
| ------------------- | ----- | ----- |
| `lfp_general`       | array | Site title, description, logo, login path, footer toggles, About-me, social buttons, Personal + Business contact cards. |
| `lfp_appearance`    | array | Colors, sizing (with units), typography (font source + size fields), background image options, custom CSS. |
| `lfp_items`         | array | Ordered tree of links and categories. |
| `lfp_image_grid`    | array | Image grid: enabled flag, default visible count, ordered list of tiles. |

Migrations from earlier 1.x / 2.x storage keys (`show_footer`, `lfp_instagram`, bare integer spacing values) run transparently on first read.

---

## 🧪 Requirements

- **YOURLS** ≥ 1.9
- **PHP** ≥ 8.1 (8.4 recommended; the plugin uses `declare(strict_types=1)`, `match`, `never`, `str_contains`)
- A modern browser for the admin UI: Chrome, Edge, Firefox, Safari ≥ 15.4 (native `<dialog>` and HTML5 drag-and-drop)

---

## 🛡 Security

- All form submissions are nonce-protected via `yourls_nonce_field` / `yourls_verify_nonce`.
- The quick-add row action signs its URL with `hash_hmac('sha256', …, YOURLS_COOKIEKEY)` and gates execution on `yourls_is_valid_user()`.
- Output uses `yourls_esc_html` / `_attr` / `_url`. Markdown / HTML fields are deliberately rendered raw (admin-trusted), same posture as the Custom CSS field.
- Uploads are MIME-type allow-listed and renamed to random hex filenames generated with `random_bytes`.
- The login path is intercepted via `yourls_redirect` — no `.htaccess` rewriting required.

---

## 🤝 Contributing

Bug reports, feature requests and pull requests are welcome on [GitHub](https://github.com/toineenzo/YOURLS-Link-Front-Page/issues). Please describe your YOURLS version and PHP version when filing a bug.

---

## 📄 License

[MIT](LICENSE) — do whatever you want, just don't blame me.

Bundled third-party code:
- **Parsedown** by Emanuil Rusev, [MIT license](https://github.com/erusev/parsedown/blob/master/LICENSE.txt).
- **Social media SVG icons** from [simple-icons](https://simpleicons.org), [CC0 1.0 Universal](https://creativecommons.org/publicdomain/zero/1.0/).
- **Google Fonts list** is metadata only; font files are served by Google.
