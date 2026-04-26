<?php
/**
 * Default-options shape tests. The plugin reads these via array_merge in
 * lfp_get_general / lfp_get_appearance / lfp_get_image_grid, so a missing
 * key in the defaults silently breaks consumers — pin the shape.
 */

declare(strict_types=1);

class DefaultsTest extends PHPUnit\Framework\TestCase
{
    public function test_default_general_has_expected_top_level_keys(): void
    {
        $defaults = lfp_default_general();

        // Pick out the keys the public frontend / admin views read from.
        // If any of these disappear, frontend.php and views/admin.php break.
        $required = [
            'enabled', 'site_title', 'site_description', 'site_logo',
            'site_favicon', 'open_in_new_tab', 'login_path',
            'show_login_link', 'show_powered_by', 'powered_by_text',
            'powered_by_url', 'footer_custom_html',
            'not_found_mode', 'not_found_target_type',
            'about_enabled', 'about_image', 'about_text',
            'about_socials', 'about_personal', 'about_business',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $defaults, "Missing default: {$key}");
        }

        // Booleans must be actual booleans (admin form uses isset()).
        $this->assertIsBool($defaults['enabled']);
        $this->assertIsBool($defaults['about_enabled']);
        // Contacts are nested arrays, must already be the right shape.
        $this->assertIsArray($defaults['about_personal']);
        $this->assertArrayHasKey('enabled',     $defaults['about_personal']);
        $this->assertArrayHasKey('show_inline', $defaults['about_personal']);
    }

    public function test_default_appearance_has_color_and_size_keys(): void
    {
        $defaults = lfp_default_appearance();

        $required = [
            'background_color', 'text_color', 'muted_color',
            'card_background', 'card_hover', 'accent_color',
            'border_radius', 'page_max_width',
            'card_gap', 'card_padding_y', 'card_padding_x',
            'font_source', 'title_size', 'body_size',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $defaults, "Missing default: {$key}");
        }

        // Colors should be hex strings the sanitizer accepts.
        foreach (['background_color', 'text_color', 'card_background'] as $color_key) {
            $this->assertSame(
                $defaults[$color_key],
                lfp_sanitize_color($defaults[$color_key]),
                "Default {$color_key} doesn't survive its own sanitiser."
            );
        }
    }

    public function test_default_image_grid_starts_disabled_and_empty(): void
    {
        $defaults = lfp_default_image_grid();

        $this->assertArrayHasKey('enabled',       $defaults);
        $this->assertArrayHasKey('items',         $defaults);
        $this->assertArrayHasKey('visible_count', $defaults);

        $this->assertFalse($defaults['enabled']);
        $this->assertSame([], $defaults['items']);
        $this->assertGreaterThanOrEqual(1, (int) $defaults['visible_count']);
    }
}
