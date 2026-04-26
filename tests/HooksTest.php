<?php
/**
 * Wire-up coverage. Make sure the plugin actually attaches the hooks the
 * Playwright suite + the frontend rely on. If a refactor accidentally
 * removes a yourls_add_action / yourls_add_filter call, this catches it
 * before the e2e run does.
 */

declare(strict_types=1);

class HooksTest extends PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideExpectedActions
     */
    public function test_action_is_registered(string $hook, string $callback): void
    {
        $filters = yourls_get_filters($hook);
        $this->assertIsArray($filters, "No filters registered on action '{$hook}'.");

        $found = false;
        foreach ($filters as $priority_bucket) {
            if (is_array($priority_bucket) && array_key_exists($callback, $priority_bucket)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Callback '{$callback}' not bound to action '{$hook}'.");
    }

    public static function provideExpectedActions(): array
    {
        return [
            'plugin loaded routing'       => ['plugins_loaded',     'lfp_handle_routing'],
            'pre_load_template fallback'  => ['pre_load_template',  'lfp_handle_pre_load_template'],
            'loader_failed fallback'      => ['loader_failed',      'lfp_handle_loader_failed'],
            'admin row action handler'    => ['plugins_loaded',     'lfp_handle_admin_quickadd'],
        ];
    }

    /**
     * @dataProvider provideExpectedFilters
     */
    public function test_filter_is_registered(string $hook, string $callback): void
    {
        $filters = yourls_get_filters($hook);
        $found = false;
        foreach ((array) $filters as $priority_bucket) {
            if (is_array($priority_bucket) && array_key_exists($callback, $priority_bucket)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Callback '{$callback}' not bound to filter '{$hook}'.");
    }

    public static function provideExpectedFilters(): array
    {
        return [
            'admin row action injection' => ['table_add_row_action_array', 'lfp_table_row_action'],
        ];
    }
}
