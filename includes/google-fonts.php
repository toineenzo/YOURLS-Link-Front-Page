<?php
/**
 * Curated list of popular Google Fonts.
 *
 * Bundled so the picker works offline (no Google Fonts API key needed). The
 * actual font files are still fetched from fonts.googleapis.com at render
 * time when a Google font is selected.
 *
 * Data is a flat array of ['family' => string, 'category' => string].
 */

declare(strict_types=1);

return [
    // ----- Sans-serif (most popular)
    ['family' => 'Inter',                'category' => 'sans-serif'],
    ['family' => 'Roboto',               'category' => 'sans-serif'],
    ['family' => 'Open Sans',            'category' => 'sans-serif'],
    ['family' => 'Lato',                 'category' => 'sans-serif'],
    ['family' => 'Montserrat',           'category' => 'sans-serif'],
    ['family' => 'Poppins',              'category' => 'sans-serif'],
    ['family' => 'Source Sans 3',        'category' => 'sans-serif'],
    ['family' => 'Raleway',              'category' => 'sans-serif'],
    ['family' => 'Nunito',               'category' => 'sans-serif'],
    ['family' => 'Nunito Sans',          'category' => 'sans-serif'],
    ['family' => 'Ubuntu',               'category' => 'sans-serif'],
    ['family' => 'PT Sans',              'category' => 'sans-serif'],
    ['family' => 'Oswald',               'category' => 'sans-serif'],
    ['family' => 'Roboto Condensed',     'category' => 'sans-serif'],
    ['family' => 'Mukta',                'category' => 'sans-serif'],
    ['family' => 'Work Sans',            'category' => 'sans-serif'],
    ['family' => 'Quicksand',            'category' => 'sans-serif'],
    ['family' => 'DM Sans',              'category' => 'sans-serif'],
    ['family' => 'Manrope',              'category' => 'sans-serif'],
    ['family' => 'Plus Jakarta Sans',    'category' => 'sans-serif'],
    ['family' => 'Karla',                'category' => 'sans-serif'],
    ['family' => 'Hind',                 'category' => 'sans-serif'],
    ['family' => 'Cabin',                'category' => 'sans-serif'],
    ['family' => 'Barlow',               'category' => 'sans-serif'],
    ['family' => 'Fira Sans',            'category' => 'sans-serif'],
    ['family' => 'Heebo',                'category' => 'sans-serif'],
    ['family' => 'Archivo',              'category' => 'sans-serif'],
    ['family' => 'Outfit',               'category' => 'sans-serif'],
    ['family' => 'Be Vietnam Pro',       'category' => 'sans-serif'],
    ['family' => 'Public Sans',          'category' => 'sans-serif'],
    ['family' => 'Rubik',                'category' => 'sans-serif'],
    ['family' => 'IBM Plex Sans',        'category' => 'sans-serif'],
    ['family' => 'Space Grotesk',        'category' => 'sans-serif'],
    ['family' => 'Sora',                 'category' => 'sans-serif'],
    ['family' => 'Figtree',              'category' => 'sans-serif'],
    ['family' => 'Onest',                'category' => 'sans-serif'],
    ['family' => 'Geist',                'category' => 'sans-serif'],

    // ----- Serif
    ['family' => 'Playfair Display',     'category' => 'serif'],
    ['family' => 'Merriweather',         'category' => 'serif'],
    ['family' => 'Roboto Slab',          'category' => 'serif'],
    ['family' => 'PT Serif',             'category' => 'serif'],
    ['family' => 'Lora',                 'category' => 'serif'],
    ['family' => 'Source Serif 4',       'category' => 'serif'],
    ['family' => 'EB Garamond',          'category' => 'serif'],
    ['family' => 'Crimson Text',         'category' => 'serif'],
    ['family' => 'Cormorant Garamond',   'category' => 'serif'],
    ['family' => 'Libre Baskerville',    'category' => 'serif'],
    ['family' => 'Frank Ruhl Libre',     'category' => 'serif'],
    ['family' => 'Bitter',               'category' => 'serif'],
    ['family' => 'Spectral',             'category' => 'serif'],
    ['family' => 'IBM Plex Serif',       'category' => 'serif'],
    ['family' => 'Crimson Pro',          'category' => 'serif'],
    ['family' => 'Cardo',                'category' => 'serif'],
    ['family' => 'Newsreader',           'category' => 'serif'],
    ['family' => 'Fraunces',             'category' => 'serif'],
    ['family' => 'Instrument Serif',     'category' => 'serif'],

    // ----- Display
    ['family' => 'Bebas Neue',           'category' => 'display'],
    ['family' => 'Anton',                'category' => 'display'],
    ['family' => 'Abril Fatface',        'category' => 'display'],
    ['family' => 'Pacifico',             'category' => 'display'],
    ['family' => 'Lobster',              'category' => 'display'],
    ['family' => 'Yeseva One',           'category' => 'display'],
    ['family' => 'Righteous',            'category' => 'display'],
    ['family' => 'Russo One',            'category' => 'display'],
    ['family' => 'Alfa Slab One',        'category' => 'display'],
    ['family' => 'Bowlby One',           'category' => 'display'],
    ['family' => 'Black Ops One',        'category' => 'display'],
    ['family' => 'Bungee',               'category' => 'display'],

    // ----- Monospace
    ['family' => 'JetBrains Mono',       'category' => 'monospace'],
    ['family' => 'Fira Code',            'category' => 'monospace'],
    ['family' => 'Source Code Pro',      'category' => 'monospace'],
    ['family' => 'Roboto Mono',          'category' => 'monospace'],
    ['family' => 'Inconsolata',          'category' => 'monospace'],
    ['family' => 'Space Mono',           'category' => 'monospace'],
    ['family' => 'IBM Plex Mono',        'category' => 'monospace'],
    ['family' => 'DM Mono',              'category' => 'monospace'],
    ['family' => 'Geist Mono',           'category' => 'monospace'],
    ['family' => 'Cascadia Code',        'category' => 'monospace'],

    // ----- Handwriting / Script
    ['family' => 'Caveat',               'category' => 'handwriting'],
    ['family' => 'Dancing Script',       'category' => 'handwriting'],
    ['family' => 'Indie Flower',         'category' => 'handwriting'],
    ['family' => 'Shadows Into Light',   'category' => 'handwriting'],
    ['family' => 'Kalam',                'category' => 'handwriting'],
    ['family' => 'Satisfy',              'category' => 'handwriting'],
    ['family' => 'Great Vibes',          'category' => 'handwriting'],
    ['family' => 'Sacramento',           'category' => 'handwriting'],
    ['family' => 'Permanent Marker',     'category' => 'handwriting'],
    ['family' => 'Architects Daughter',  'category' => 'handwriting'],
];
