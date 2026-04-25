# YOURLS — Link Front Page

> Turn your YOURLS homepage into a Linktree-style landing page that showcases the shortlinks you have already created.

This plugin replaces the (rather plain) default YOURLS homepage with a curated, mobile-friendly link list. Group your shortlinks into category boxes, drag them into the order you like, and give each entry a custom image, title and description. If you don't bother to set a custom title, the plugin falls back to the title that YOURLS already stores for that shortlink.

The admin login is still one click away — just visit `https://your-site/login` (configurable) and you land on the regular YOURLS admin.

## ✨ Features

- **Linktree-style homepage** at `https://your-site/`, replacing the default YOURLS info page.
- **Configurable login path** (defaults to `/login`) so you can still reach the admin.
- **About-me section** (optional) with a profile photo, bio text and a row of icon-only social media buttons. Each button can either point to a free-form URL or be tied to one of your existing YOURLS shortlinks (so click-tracking still works).
    - 30+ supported brands out of the box: X (Twitter), Instagram, Facebook, TikTok, YouTube, LinkedIn, GitHub, GitLab, Reddit, Discord, Telegram, WhatsApp, Snapchat, Pinterest, Twitch, Spotify, SoundCloud, Mastodon, Bluesky, Threads, Patreon, Ko-fi, Buy Me a Coffee, PayPal, Signal, Dribbble, Behance, Medium, Substack, Dev.to, Stack Overflow, Product Hunt, Steam — plus generic Website / Email / RSS / Phone icons.
    - Brand SVGs are bundled inline (CC0, simple-icons.org). No CDN, no tracking.
- **Visual settings panel** under *Manage Plugins → Link Front Page*:
    - Searchable picker over all your existing YOURLS shortlinks.
    - **Drag &amp; drop** to reorder items, drop links onto a category box to nest them, drop them back to the top level to un-nest.
    - **Category boxes** with their own title, description and optional banner image.
    - **Per-link customization**: image (URL or upload), custom title, description.
    - Smart **title fallback** — if you don't set a custom title, the YOURLS-saved link title is used.
- **Configurable footer** with two independent toggles: show login link, and show "Powered by …" attribution (custom name + URL, defaults to `Powered by YOURLS` linking to yourls.org).
- **Appearance tab** with live colors (background, card, accent, text), border radius, font family, optional background image and a custom CSS escape hatch.
- **Image uploads** stored under `user/plugins/yourls-link-front-page/uploads/` (jpeg, png, gif, webp, svg, max 5 MB).
- **No external dependencies** — vanilla JS, native HTML5 drag &amp; drop, native `<dialog>` picker, inline SVG icons.
- **PHP 8.4 compatible** with `declare(strict_types=1)`, `match` expressions, typed signatures, and modern null-coalescing patterns.
- Uses native YOURLS APIs throughout (`yourls_get_option`, `yourls_update_option`, `yourls_keyword_is_taken`, `yourls_get_keyword_longurl`, `yourls_get_keyword_title`, `yourls_link`, `yourls_redirect`, `yourls_register_plugin_page`, `yourls_nonce_field`, `yourls_verify_nonce`, …).

## 📦 Installation

1. Download or clone this repository into your YOURLS install:

    ```bash
    cd /path/to/yourls/user/plugins
    git clone https://github.com/toineenzo/YOURLS-Link-Front-Page.git yourls-link-front-page
    ```

2. Open the YOURLS admin at `/admin/plugins.php` and click **Activate** on *Link Front Page*.

3. The plugin adds a sub-page in the admin sidebar under *Manage Plugins → Link Front Page*. Open it and start adding links and categories.

That's it — visit your YOURLS root URL and you should see your new link front page.

## 🚀 Usage

### Adding links

1. Go to *Manage Plugins → Link Front Page*.
2. Click **+ Add link** and search the picker for the shortlink you want to feature.
3. Click the gear icon on the new card to expand the editor and (optionally) set:
    - a custom title (overrides the YOURLS link title);
    - a description, shown beneath the title;
    - an image, either by URL or by uploading a file (≤ 5 MB).

### Adding categories

1. Click **+ Add category**.
2. Expand the category, give it a title, description and optional image.
3. Drag links onto the category to nest them. Drag them back out to un-nest.

### Reordering

Grab the &#x2630; handle on the left of any card and drag. Drop *above* or *below* an existing card to position your item; drop into the empty space of a category's children list to append.

> 💡 Categories can only live at the top level — YOURLS link lists are not designed for deep nesting.

### Customizing the look

The **Appearance** tab gives you HTML5 color pickers for the background, text, muted text, card background, hover background and accent. There's also a custom-CSS textarea for advanced tweaks; it gets injected at the bottom of the public page's inline `<style>` block.

### The login path

By default the plugin reserves `/login` as a redirect to `/admin/`. You can change this in the **General** tab — for example to `/admin-please` or `/me`. If you want to keep `/login` available as a real shortlink, just set the login path to a different value.

## 🗂 File structure

```
yourls-link-front-page/
├── plugin.php             # Plugin entrypoint, hooks, settings I/O, upload handling
├── views/
│   ├── frontend.php       # Public Linktree-style page rendered at /
│   └── admin.php          # Settings UI rendered inside YOURLS admin
├── assets/
│   ├── frontend.css       # Public styles (uses CSS custom properties)
│   ├── admin.css          # Admin styles
│   └── admin.js           # Admin logic (drag/drop, picker, serialization)
├── uploads/               # User-uploaded images (auto-created)
└── README.md
```

## ⚙️ Stored options

The plugin uses three YOURLS options (see `yourls_get_option`):

| Option           | Shape  | Notes                                                                    |
| ---------------- | ------ | ------------------------------------------------------------------------ |
| `lfp_general`    | array  | Enabled flag, site title/description, logo, login path, footer toggle.   |
| `lfp_appearance` | array  | Color tokens, border radius, font family, background image, custom CSS.  |
| `lfp_items`      | array  | Ordered list of links and categories (categories carry a `children` list). |

Items are stored as a tree:

```php
[
    [
        'id' => 'cat_xxx',
        'type' => 'category',
        'title' => 'Socials',
        'description' => 'Catch me online',
        'image' => 'https://…/banner.jpg',
        'children' => [
            ['id' => 'link_a', 'type' => 'link', 'keyword' => 'tw', 'title' => 'Twitter', …],
            ['id' => 'link_b', 'type' => 'link', 'keyword' => 'gh', 'title' => '',          …],
        ],
    ],
    ['id' => 'link_c', 'type' => 'link', 'keyword' => 'blog', 'title' => '', …],
]
```

When rendering a link, the title is resolved as:
1. The custom `title` set in the plugin, if any.
2. Otherwise the YOURLS link title returned by `yourls_get_keyword_title($keyword)`.
3. Otherwise the long URL.

## 🧪 Requirements

- **YOURLS** ≥ 1.9 (uses the modern `yourls_*` API surface).
- **PHP** ≥ 8.4 (the plugin is written against PHP 8.4 syntax — `declare(strict_types=1)`, `match`, first-class callable syntax, `str_contains`).
- A browser with native HTML5 `<dialog>` and drag/drop support (Chrome, Edge, Firefox, Safari ≥ 15.4).

## 🛡️ Security notes

- All form submissions are protected by a YOURLS nonce (`yourls_nonce_field` / `yourls_verify_nonce`).
- Output in both the admin and frontend uses `yourls_esc_html`, `yourls_esc_attr` and `yourls_esc_url`.
- Uploads are restricted by MIME type and file size, and stored under random, hex-named filenames generated with `random_bytes`.
- The login path is intercepted via `yourls_redirect` — no rewriting of `.htaccess` or `web.config` is required.

## 🤝 Contributing

Bug reports, feature requests and pull requests are welcome on [GitHub](https://github.com/toineenzo/YOURLS-Link-Front-Page/issues). Please describe your YOURLS version and PHP version when filing a bug.

## 📄 License

[MIT](LICENSE) — do whatever you want, just don't blame me.
