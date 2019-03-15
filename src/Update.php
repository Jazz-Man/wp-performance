<?php

namespace JazzMan\Performance;

use stdClass;

/**
 * Class Update.
 */
class Update implements AutoloadInterface
{
    public function load()
    {
        // Stop wp-cron from looking out for new plugin versions
        add_action('admin_init', [$this, 'remove_update_crons']);
        add_action('admin_init', [$this, 'remove_schedule_hook']);

        // Prevent users from even trying to update plugins and themes
        add_filter('map_meta_cap', [$this, 'prevent_auto_updates'], 10, 2);

        // Remove bulk action for updating themes/plugins.
        add_filter('bulk_actions-plugins', [$this, 'remove_bulk_actions']);
        add_filter('bulk_actions-themes', [$this, 'remove_bulk_actions']);
        add_filter('bulk_actions-plugins-network', [$this, 'remove_bulk_actions']);
        add_filter('bulk_actions-themes-network', [$this, 'remove_bulk_actions']);

        // Admin UI items.
        add_action('admin_menu', [$this, 'admin_menu_items'], 9999);
        add_action('network_admin_menu', [$this, 'ms_admin_menu_items'], 9999);
        add_filter('install_plugins_tabs', [$this, 'plugin_add_tabs']);

        // Theme update API for different calls.
        add_filter('themes_api_args', [$this, 'bypass_theme_api'], 10, 2);
        add_filter('themes_api', '__return_false');

        // Time based transient checks.
        add_filter('pre_site_transient_update_themes', [$this, 'last_checked_themes']);
        add_filter('pre_site_transient_update_plugins', [$this, 'last_checked_plugins']);
        add_filter('pre_site_transient_update_core', [$this, 'last_checked_core']);
        add_filter('site_transient_update_themes', [$this, 'remove_update_array']);
        add_filter('site_transient_update_plugins', [$this, 'remove_plugin_updates']);

        // Removes update check wp-cron
        remove_action('init', 'wp_schedule_update_checks');

        // Disable overall core updates.
        add_filter('auto_update_core', '__return_false');
        add_filter('wp_auto_update_core', '__return_false');
    }

    /**
     * Remove all the various places WP does the update checks. As you can see there are a lot of them.
     */
    public function remove_update_crons()
    {
        if (!App::enabled()) {
            return;
        }

        // Disable Theme Updates.
        remove_action('load-update-core.php', 'wp_update_themes');
        remove_action('load-themes.php', 'wp_update_themes');
        remove_action('load-update.php', 'wp_update_themes');
        remove_action('wp_update_themes', 'wp_update_themes');
        remove_action('admin_init', '_maybe_update_themes');

        // Disable Plugin Updates.
        remove_action('load-update-core.php', 'wp_update_plugins');
        remove_action('load-plugins.php', 'wp_update_plugins');
        remove_action('load-update.php', 'wp_update_plugins');
        remove_action('wp_update_plugins', 'wp_update_plugins');
        remove_action('admin_init', '_maybe_update_plugins');

        // Disable Core updates
        add_action('init', function () {
            remove_action('init', 'wp_version_check');
        }, 2);

        // Don't look for WordPress updates. Seriously!
        remove_action('wp_version_check', 'wp_version_check');
        remove_action('admin_init', '_maybe_update_core');

        // Not even maybe.
        remove_action('wp_maybe_auto_update', 'wp_maybe_auto_update');
        remove_action('admin_init', 'wp_maybe_auto_update');
        remove_action('admin_init', 'wp_auto_update_core');
    }

    /**
     * Remove all the various schedule hooks for themes, plugins, etc.
     */
    public function remove_schedule_hook()
    {
        if (!App::enabled()) {
            return;
        }

        wp_clear_scheduled_hook('wp_update_themes');
        wp_clear_scheduled_hook('wp_update_plugins');
        wp_clear_scheduled_hook('wp_version_check');
        wp_clear_scheduled_hook('wp_maybe_auto_update');
    }

    /**
     * Filter a user's meta capabilities to prevent auto-updates from being attempted.
     *
     * @param array  $caps returns the user's actual capabilities
     * @param string $cap  capability name
     *
     * @return array the user's filtered capabilities
     */
    public function prevent_auto_updates($caps, $cap)
    {
        // Check for being enabled and look for specific cap requirements.
        if (App::enabled() && \in_array($cap, [
                'install_plugins',
                'install_themes',
                'update_plugins',
                'update_themes',
                'update_core',
            ])) {
            $caps[] = 'do_not_allow';
        }

        // Send back the data array.
        return $caps;
    }

    /**
     * Remove the ability to update plugins/themes from single
     * site and multisite bulk actions.
     *
     * @param array $actions all the bulk actions
     *
     * @return array $actions  The remaining actions
     */
    public function remove_bulk_actions($actions)
    {
        if (App::enabled()) {
            return $actions;
        }

        // Set an array of items to be removed with optional filter.
        if (false === $remove = apply_filters('core_blocker_bulk_items',
                ['update-selected', 'update', 'upgrade'])) {
            return $actions;
        }

        // Loop the item array and unset each.
        foreach ($remove as $key) {
            unset($actions[$key]);
        }

        // Return the remaining.
        return $actions;
    }

    /**
     * Remove menu items for updates from a standard WP install.
     */
    public function admin_menu_items()
    {
        // Bail if disabled, or on a multisite.
        if (!App::enabled() || is_multisite()) {
            return;
        }

        // Remove our items.
        remove_submenu_page('index.php', 'update-core.php');
    }

    /**
     * Remove menu items for updates from a multisite instance.
     */
    public function ms_admin_menu_items()
    {
        // Bail if disabled or not on our network admin.
        if (!App::enabled() || !is_network_admin()) {
            return;
        }

        // Remove the items.
        remove_submenu_page('index.php', 'upgrade.php');
    }

    /**
     * Remove the tabs on the plugin page to add new items
     * since they require the WP connection and will fail.
     *
     * @param array $nonmenu_tabs all the tabs displayed
     *
     * @return array $nonmenu_tabs  the remaining tabs
     */
    public function plugin_add_tabs($nonmenu_tabs)
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return $nonmenu_tabs;
        }

        // Set an array of tabs to be removed with optional filter.
        if (false === $remove = apply_filters('core_blocker_bulk_items',
                ['featured', 'popular', 'recommended', 'favorites', 'beta'])) {
            return $nonmenu_tabs;
        }

        // Loop the item array and unset each.
        foreach ($remove as $key) {
            unset($nonmenu_tabs[$key]);
        }

        // Return the tabs.
        return $nonmenu_tabs;
    }

    /**
     * Hijack the themes api setup to bypass the API call.
     *
     * @param object $args   arguments used to query for installer pages from the Themes API
     * @param string $action Requested action. Likely values are 'theme_information',
     *                       'feature_list', or 'query_themes'.
     *
     * @return bool|object true or false depending on the type of query
     */
    public function bypass_theme_api($args, $action)
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return $args;
        }

        // Return false on feature list to avoid the API call.
        return !empty($action) && 'feature_list' === $action ? false : $args;
    }

    /**
     * Always send back that the latest version of our theme is the one we're running.
     *
     * @return object|false the modified output with our information
     */
    public function last_checked_themes()
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return false;
        }

        // Call the global WP version.
        global $wp_version;

        // Set a blank data array.
        $data = [];

        /** @var \WP_Theme[] $themes */
        $themes = wp_get_themes();

        // Build my theme data array.
        foreach ($themes as $theme) {
            $data[$theme->get_stylesheet()] = $theme->get('Version');
        }

        // Return our object.
        return (object) [
            'last_checked' => time(),
            'updates' => [],
            'version_checked' => $wp_version,
            'checked' => $data,
        ];
    }

    /**
     * Always send back that the latest version of our plugins are the one we're running.
     *
     * @return object|false the modified output with our information
     */
    public function last_checked_plugins()
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return false;
        }

        // Call the global WP version.
        global $wp_version;

        // Set a blank data array.
        $data = [];

        // Add our plugin file if we don't have it.
        if (!\function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        // Build my plugin data array.
        foreach (get_plugins() as $file => $pl) {
            $data[$file] = $pl['Version'];
        }

        // Return our object.
        return (object) [
            'last_checked' => time(),
            'updates' => [],
            'version_checked' => $wp_version,
            'checked' => $data,
        ];
    }

    /**
     * Always send back that the latest version of WordPress is the one we're running.
     *
     * @return object|false the modified output with our information
     */
    public function last_checked_core()
    {
        // Bail if disabled.
        if (!App::enabled()) {
            return false;
        }

        // Call the global WP version.
        global $wp_version;

        // Return our object.
        return (object) [
            'last_checked' => time(),
            'updates' => [],
            'version_checked' => $wp_version,
        ];
    }

    /**
     * Return an empty array of items requiring update for both themes and plugins.
     *
     * @param array $items all the items being passed for update
     *
     * @return array an empty array, or the original items if not enabled
     */
    public function remove_update_array($items)
    {
        return !App::enabled() ? $items : [];
    }

    /**
     * Returns list of plugins which tells that there's no updates.
     *
     * @param array|stdClass $current Empty array
     *
     * @return array Lookalike data which is stored in site transient 'update_plugins'
     */
    public function remove_plugin_updates($current)
    {
        if (!$current) {
            $current = new stdClass();
            $current->last_checked = time();
            $current->translations = [];

            $plugins = get_plugins();
            foreach ($plugins as $file => $p) {
                $current->checked[$file] = (string) $p['Version'];
            }
            $current->response = [];
        }

        return $current;
    }
}
