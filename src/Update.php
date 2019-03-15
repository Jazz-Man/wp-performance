<?php

namespace JazzMan\Performance;

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
    }

    public function remove_update_crons()
    {
        if ( ! App::enabled()) {
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

    public function remove_schedule_hook()
    {
        if ( ! App::enabled()) {
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
}
