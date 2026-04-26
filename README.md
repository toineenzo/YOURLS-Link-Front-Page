# Link Front Page

> A personal landing page for your YOURLS install — Linktree-style links, an image grid, an About-me block, and a full design panel. All from one settings screen, no theme files to edit.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Latest release](https://img.shields.io/github/v/release/toineenzo/YOURLS-Link-Front-Page?display_name=tag)](https://github.com/toineenzo/YOURLS-Link-Front-Page/releases)
[![Listed in Awesome YOURLS!](https://img.shields.io/badge/Awesome-YOURLS-C5A3BE)](https://github.com/YOURLS/awesome)

🌐 **Live demo**: <https://toine.click>

<p align="center">
  <!-- TODO: drop the homepage hero screenshot in docs/screenshots/hero.png -->
  <img src="docs/screenshots/hero.png" alt="Link Front Page — public landing page" width="720">
</p>

---

## What you can do with it

### 🔗 A Linktree-style link list

Add as many links as you want, group them into category boxes, drag them around to reorder. Each entry has its own image, title, description, and a click-through that either points at a YOURLS shortlink (so click stats keep working) or any URL you type in.

<p align="center">
  <!-- TODO: docs/screenshots/links-tab.png -->
  <img src="docs/screenshots/links-tab.png" alt="Links tab in the admin" width="720">
</p>

### 🖼️ An image grid

Three-column gallery for "link in bio" content — Instagram-style. Bulk-upload a folder of images, give each tile a title overlay (always shown / on hover / hidden), and have it click through to a URL or a YOURLS shortlink.

<p align="center">
  <!-- TODO: docs/screenshots/image-grid.png -->
  <img src="docs/screenshots/image-grid.png" alt="Image grid on the public page" width="720">
</p>

### 👋 An About-me block

Profile photo, short bio, and a row of icon-only buttons for 37 social platforms (X, Instagram, GitHub, Mastodon, Bluesky, Threads, Patreon, Ko-fi, …). You can also surface a Personal and a Business contact card with one-click vCard download for visitors.

<p align="center">
  <!-- TODO: docs/screenshots/about-me.png -->
  <img src="docs/screenshots/about-me.png" alt="About-me block + social icons" width="720">
</p>

### 🎨 A full design panel

Pick colours, fonts, background images and spacings until it looks right. Typography supports a system stack, 85+ Google Fonts (with live preview), or your own font upload. Spacings accept any CSS unit (`px`, `em`, `rem`, `clamp()`, `calc()`, …). There's a Custom CSS escape hatch for anything the panel doesn't cover.

<p align="center">
  <!-- TODO: docs/screenshots/appearance-tab.png -->
  <img src="docs/screenshots/appearance-tab.png" alt="Appearance tab" width="720">
</p>

### ➕ Quick-add from the link table

Every row of YOURLS' admin link table grows a small **+** button next to *Stats / Share / Edit / Delete*. One click adds that shortlink to the bottom of your homepage list, with the destination's favicon as the default image.

<p align="center">
  <!-- TODO: docs/screenshots/quick-add.png -->
  <img src="docs/screenshots/quick-add.png" alt="Quick-add button on the link table" width="720">
</p>

### 🚦 A friendlier 404 page

Decide what happens when someone visits a shortlink that doesn't exist: keep YOURLS' default redirect, send them to your landing page, redirect to any URL or shortlink, or render a styled error page with your own message + button.

---

## Installation

1. Download `YOURLS-Link-Front-Page-vX.Y.Z.zip` from the [Releases page](https://github.com/toineenzo/YOURLS-Link-Front-Page/releases) and unzip into your `user/plugins/` folder. The final path should be `user/plugins/Link-Front-Page/`.
2. Open the YOURLS admin → *Manage Plugins* → click **Activate** on *Link Front Page*.
3. A new sub-page appears under *Manage Plugins → Link Front Page*. Open it and start adding things.

…or via git:

```bash
cd /path/to/yourls/user/plugins
git clone https://github.com/toineenzo/YOURLS-Link-Front-Page.git Link-Front-Page
```

> **Heads-up**: if your YOURLS root is missing `index.php` (e.g. you replaced it with a custom homepage), Apache returns 404 for `/`. Restore the standard YOURLS `index.php` and the plugin will pick it up.

---

## A short tour

The settings page has four tabs:

| Tab | What you do here |
| --- | --- |
| **Links** | Build the link list and category boxes shown on `/`. |
| **Image grid** | Manage the image gallery (bulk-upload, per-tile dialog, "Show more"). |
| **General** | Site title, About-me, social buttons, contact cards, footer, 404 behaviour. |
| **Appearance** | Colours, background image, spacing, typography, custom CSS. |

Hit **Save settings** and visit `/` to see your changes live.

---

## Compatibility

- **YOURLS** 1.9 or newer (verified against 1.10).
- **PHP** 8.1 or newer (8.4 recommended).
- Plays nicely with the [Sleeky](https://github.com/Flynntes/Sleeky) admin theme — the CI suite tests both setups on every release.

---

## Help & feedback

Found a bug or have an idea? [Open an issue](https://github.com/toineenzo/YOURLS-Link-Front-Page/issues). Please mention your YOURLS and PHP version.

---

## License

[MIT](LICENSE). Bundles [Parsedown](https://github.com/erusev/parsedown) (MIT) for Markdown and [simple-icons](https://simpleicons.org) (CC0) SVGs.
