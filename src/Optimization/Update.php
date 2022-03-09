<?php

namespace JazzMan\Performance\Optimization;

use JazzMan\AutoloadInterface\AutoloadInterface;

/**
 * Class Update.
 */
class Update implements AutoloadInterface {
    public function load() {
        // Remove admin news dashboard widget
        add_action('admin_init', [__CLASS__, 'removeDashboards']);

        // Prevent users from even trying to update plugins and themes
        add_filter('map_meta_cap', [__CLASS__, 'preventAutoUpdates'], 10, 2);

        // Remove bulk action for updating themes/plugins.
        foreach (['plugins', 'themes', 'plugins-network', 'themes-network'] as $bulkAction) {
            add_filter(sprintf('bulk_actions-%s', $bulkAction), [__CLASS__, 'removeBulkActions']);
        }

        // Admin UI items.
        // Remove menu items for updates from a standard WP install.
        add_action('admin_menu', static function (): void {
            // Bail if disabled, or on a multisite.
            if (! app_is_enabled_wp_performance()) {
                return;
            }

            if (is_multisite()) {
                return;
            }

            // Remove our items.
            remove_submenu_page('index.php', 'update-core.php');
        }, 9999);

        // Remove menu items for updates from a multisite instance.

        add_action('network_admin_menu', [__CLASS__, 'removeMultisiteMenuItems'], 9999);

        add_filter('install_plugins_tabs', [__CLASS__, 'disablePluginAddTabs']);

        // Theme update API for different calls.

        // Hijack the themes api setup to bypass the API call.
        add_filter('themes_api_args', static function (object $args, string $action) {
            // Bail if disabled.
            if ( ! app_is_enabled_wp_performance()) {
                return $args;
            }

            // Return false on feature list to avoid the API call.
            return ! empty($action) && 'feature_list' === $action ? false : $args;
        }, 10, 2);

        add_filter('themes_api', '__return_false');

        // Time based transient checks.
        $siteTransients = ['themes', 'plugins', 'core'];

        foreach ($siteTransients as $siteTransient) {
            add_filter(sprintf('pre_site_transient_update_%s', $siteTransient), [__CLASS__, 'lastCheckedCore']);
        }

        add_filter('site_transient_update_plugins', [__CLASS__, 'removePluginUpdates']);

        // Removes update check wp-cron
        remove_action('init', 'wp_schedule_update_checks');

        // Disable overall core updates.
        add_filter('auto_update_core', '__return_false');
        add_filter('wp_auto_update_core', '__return_false');

        // Disable automatic plugin and theme updates (used by WP to force push security fixes).
        add_filter('auto_update_plugin', '__return_false');
        add_filter('auto_update_theme', '__return_false');

        // Tell WordPress we are on a version control system to add additional blocks.
        add_filter('automatic_updates_is_vcs_checkout', '__return_true');

        // Disable translation updates.
        add_filter('auto_update_translation', '__return_false');

        // Disable minor core updates.
        add_filter('allow_minor_auto_core_updates', '__return_false');

        // Disable major core updates.
        add_filter('allow_major_auto_core_updates', '__return_false');

        // Disable dev core updates.
        add_filter('allow_dev_auto_core_updates', '__return_false');

        // Disable automatic updater updates.
        add_filter('automatic_updater_disabled', '__return_true');

        // Run various hooks if the plugin should be enabled
        if (app_is_enabled_wp_performance()) {
            // Disable WordPress from fetching available languages
            add_filter('pre_site_transient_available_translations', [__CLASS__, 'availableTranslations']);

            // Hijack the themes api setup to bypass the API call.
            add_filter('themes_api', '__return_true');

            // Disable debug emails (used by core for rollback alerts in automatic update deployment).
            add_filter('automatic_updates_send_debug_email', '__return_false');

            // Disable update emails (for when we push the new WordPress versions manually) as well
            // as the notification there is a new version emails.
            add_filter('auto_core_update_send_email', '__return_false');
            add_filter('send_core_update_notification_email', '__return_false');
            add_filter('automatic_updates_send_debug_email ', '__return_false', 1);

            // Get rid of the version number in the footer.
            add_filter('update_footer', '__return_empty_string', 11);

            // Filter out the pre core option.
            add_filter('pre_option_update_core', '__return_null');

            // Remove some actions.
            remove_action('admin_init', 'wp_plugin_update_rows');
            remove_action('admin_init', 'wp_theme_update_rows');
            remove_action('admin_notices', 'maintenance_nag');

            // Add back the upload tab.
            add_action('install_themes_upload', 'install_themes_upload', 10, 0);

            // Stop wp-cron from looking out for new plugin versions
            add_action('admin_init', [__CLASS__, 'removeUpdateCrons']);
            add_action('admin_init', [__CLASS__, 'removeScheduleHook']);

            // Return an empty array of items requiring update for both themes and plugins.
            add_filter('site_transient_update_themes', '__return_empty_array');
        }
    }

    /**
     * Remove WordPress news dashboard widget.
     */
    public static function removeDashboards(): void {
        remove_meta_box('dashboard_primary', 'dashboard', 'normal');
    }

    public static function removeMultisiteMenuItems(?string $menu = ''): void {
        // Bail if disabled or not on our network admin.
        if (! app_is_enabled_wp_performance()) {
            return;
        }

        if (! is_network_admin()) {
            return;
        }

        // Remove the items.
        remove_submenu_page('index.php', 'upgrade.php');
    }

    /**
     * Remove all the various places WP does the update checks. As you can see there are a lot of them.
     */
    public static function removeUpdateCrons(): void {
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
        add_action('init', static function (): void {
            remove_action('init', 'wp_version_check');
        }, 2);

        // Don't look for WordPress updates. Seriously!
        remove_action('wp_version_check', 'wp_version_check');
        remove_action('admin_init', '_maybe_update_core');

        // Not even maybe.
        remove_action('wp_maybe_auto_update', 'wp_maybe_auto_update');
        remove_action('admin_init', 'wp_maybe_auto_update');
    }

    /**
     * Remove all the various schedule hooks for themes, plugins, etc.
     */
    public function removeScheduleHook(): void {
        wp_clear_scheduled_hook('wp_update_themes');
        wp_clear_scheduled_hook('wp_update_plugins');
        wp_clear_scheduled_hook('wp_version_check');
        wp_clear_scheduled_hook('wp_maybe_auto_update');
    }

    /**
     * Filter a user's meta capabilities to prevent auto-updates from being attempted.
     *
     * @param string[] $caps returns the user's actual capabilities
     * @param string   $cap  capability name
     *
     * @return string[] the user's filtered capabilities
     *
     * @psalm-return array<string>
     */
    public static function preventAutoUpdates(array $caps, string $cap): array {
        // Check for being enabled and look for specific cap requirements.
        if (app_is_enabled_wp_performance() && in_array($cap, [
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
     * @param array<string,string> $actions all the bulk actions
     *
     * @return array<string,string> The remaining actions
     */
    public static function removeBulkActions(array $actions): array {
        if (app_is_enabled_wp_performance()) {
            return $actions;
        }

        // Set an array of items to be removed with optional filter.
        /** @var string[]|false $removeActionList */
        $removeActionList = apply_filters('core_blocker_bulk_items', ['update-selected', 'update', 'upgrade']);

        if (false === $removeActionList) {
            return $actions;
        }

        // Loop the item array and unset each.
        foreach ($removeActionList as $singleRemoveActionList) {
            unset($actions[$singleRemoveActionList]);
        }

        // Return the remaining.
        return $actions;
    }

    /**
     * Remove the tabs on the plugin page to add new items
     * since they require the WP connection and will fail.
     *
     * @param string[] $tabs all the tabs displayed
     *
     * @return string[] $nonmenu_tabs  the remaining tabs
     */
    public static function disablePluginAddTabs(array $tabs): array {
        // Bail if disabled.
        if ( ! app_is_enabled_wp_performance()) {
            return $tabs;
        }

        // Set an array of tabs to be removed with optional filter.
        /** @var string[]|false $removeActionList */
        $removeActionList = apply_filters('core_blocker_bulk_items', ['featured', 'popular', 'recommended', 'favorites', 'beta']);

        if (false === $removeActionList) {
            return $tabs;
        }

        // Loop the item array and unset each.
        foreach ($removeActionList as $singleRemoveActionList) {
            unset($tabs[$singleRemoveActionList]);
        }

        // Return the tabs.
        return $tabs;
    }

    /**
     * Always send back that the latest version of WordPress/Plugins/Theme is the one we're running.
     *
     * @param bool $transient
     *
     * @return bool|object the modified output with our information
     */
    public static function lastCheckedCore(bool $transient = false) {
        // Bail if disabled.
        if ( ! app_is_enabled_wp_performance()) {
            return false;
        }

        // Call the global WP version.
        global $wp_version;

        $curentAction = current_action();

        switch ($curentAction) {
            case 'pre_site_transient_update_themes':
                // Set a blank data array.
                /** @var array<string,string> $data */
                $data = [];

                $themes = wp_get_themes();

                // Build my theme data array.
                foreach ($themes as $theme) {
                    $data[$theme->get_stylesheet()] = $theme->get('Version');
                }

                return (object) [
                    'last_checked' => time(),
                    'updates' => [],
                    'version_checked' => $wp_version,
                    'checked' => $data,
                ];

            case 'pre_site_transient_update_core':
                return (object) [
                    'last_checked' => time(),
                    'updates' => [],
                    'version_checked' => $wp_version,
                ];

            case 'pre_site_transient_update_plugins':
                // Set a blank data array.
                /** @var array<string,string> $data */
                $data = [];

                // Add our plugin file if we don't have it.
                if ( ! function_exists('get_plugins')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                // Build my plugin data array.
                foreach (get_plugins() as $file => $pl) {
                    $data[$file] = (string) $pl['Version'];
                }

                return (object) [
                    'last_checked' => time(),
                    'updates' => [],
                    'version_checked' => $wp_version,
                    'checked' => $data,
                ];
        }

        return $transient;
    }

    /**
     * Returns list of plugins which tells that there's no updates.
     *
     * @param array<string,mixed>|object $current Empty array
     *
     * @return array<string, mixed>|object
     */
    public static function removePluginUpdates($current) {
        if ( ! $current) {
            $current = [
                'last_checked' => time(),
                'translations' => [],
                'response' => [],
                'checked' => [],
            ];

            $plugins = get_plugins();

            foreach ($plugins as $file => $p) {
                $current['checked'][$file] = (string) $p['Version'];
            }

            $current = (object) $current;
        }

        return $current;
    }

    /**
     * 	Returns installed languages instead of all possibly available languages.
     *
     * @return array<string, array{language: string, iso: array{0: string}|string, version: string, updated: string, strings: array{continue: string}|string, package: string}>
     */
    public static function availableTranslations(): array {
        // Call the global WP version.
        global $wp_version;

        $coreLanguges = self::coreBlockerGetLanguages();
        $languages = get_available_languages();

        // shared settings
        $date = date_i18n('Y-m-d H:is', time()); // eg. 2016-06-26 10:08:23

        $availableLanguages = [];

        foreach ($languages as $language) {
            // Try to mimick the data that wordpress puts into 'available_translations' transient
            $settings = [
                'language' => $language,
                'iso' => [$language],
                'version' => $wp_version,
                'updated' => $date,
                'strings' => [
                    'continue' => __('Continue'),
                ],
                'package' => sprintf(
                    'https://downloads.wordpress.org/translation/core/%s/%s.zip',
                    esc_attr($wp_version),
                    esc_attr($language)
                ),
            ];

            $availableLanguages[$language] = array_merge($settings, $coreLanguges[$language]);
        }

        /* @var array<string, array{language: string, iso: array{0: string}|string, version: string, updated: string, strings: array{continue: string}|string, package: string}> $availableLanguages */

        return $availableLanguages;
    }

    /**
     * Contains a predefined list of all 4.6 version languages so that we can deduce available languages from languages folder.
     *
     * @return array<string,array<string,string>>
     */
    private static function coreBlockerGetLanguages(): array {
        return [
            'ar' => ['english_name' => 'Arabic', 'native_name' => 'العربية'],
            'ary' => ['english_name' => 'Moroccan Arabic', 'native_name' => 'العربية المغربية'],
            'az' => ['english_name' => 'Azerbaijani', 'native_name' => 'Azərbaycan dili'],
            'azb' => ['english_name' => 'South Azerbaijani', 'native_name' => 'گؤنئی آذربایجان'],
            'bg_BG' => ['english_name' => 'Bulgarian', 'native_name' => 'Български'],
            'bn_BD' => ['english_name' => 'Bengali', 'native_name' => 'বাংলা'],
            'bs_BA' => ['english_name' => 'Bosnian', 'native_name' => 'Bosanski'],
            'ca' => ['english_name' => 'Catalan', 'native_name' => 'Català'],
            'ceb' => ['english_name' => 'Cebuano', 'native_name' => 'Cebuano'],
            'cs_CZ' => ['english_name' => 'Czech', 'native_name' => 'Čeština‎'],
            'cy' => ['english_name' => 'Welsh', 'native_name' => 'Cymraeg'],
            'da_DK' => ['english_name' => 'Danish', 'native_name' => 'Dansk'],
            'de_DE_formal' => ['english_name' => 'German (Formal)', 'native_name' => 'Deutsch (Sie)'],
            'de_DE' => ['english_name' => 'German', 'native_name' => 'Deutsch'],
            'de_CH_informal' => ['english_name' => '(Switzerland, Informal)', 'native_name' => 'Deutsch (Schweiz, Du)'],
            'de_CH' => ['english_name' => 'German (Switzerland)', 'native_name' => 'Deutsch (Schweiz)'],
            'el' => ['english_name' => 'Greek', 'native_name' => 'Ελληνικά'],
            'en_CA' => ['english_name' => 'English (Canada)', 'native_name' => 'English (Canada)'],
            'en_ZA' => ['english_name' => 'English (South Africa)', 'native_name' => 'English (South Africa)'],
            'en_AU' => ['english_name' => 'English (Australia)', 'native_name' => 'English (Australia)'],
            'en_NZ' => ['english_name' => 'English (New Zealand)', 'native_name' => 'English (New Zealand)'],
            'en_GB' => ['english_name' => 'English (UK)', 'native_name' => 'English (UK)'],
            'eo' => ['english_name' => 'Esperanto', 'native_name' => 'Esperanto'],
            'es_CL' => ['english_name' => 'Spanish (Chile)', 'native_name' => 'Español de Chile'],
            'es_AR' => ['english_name' => 'Spanish (Argentina)', 'native_name' => 'Español de Argentina'],
            'es_PE' => ['english_name' => 'Spanish (Peru)', 'native_name' => 'Español de Perú'],
            'es_MX' => ['english_name' => 'Spanish (Mexico)', 'native_name' => 'Español de México'],
            'es_CO' => ['english_name' => 'Spanish (Colombia)', 'native_name' => 'Español de Colombia'],
            'es_ES' => ['english_name' => 'Spanish (Spain)', 'native_name' => 'Español'],
            'es_VE' => ['english_name' => 'Spanish (Venezuela)', 'native_name' => 'Español de Venezuela'],
            'es_GT' => ['english_name' => 'Spanish (Guatemala)', 'native_name' => 'Español de Guatemala'],
            'et' => ['english_name' => 'Estonian', 'native_name' => 'Eesti'],
            'eu' => ['english_name' => 'Basque', 'native_name' => 'Euskara'],
            'fa_IR' => ['english_name' => 'Persian', 'native_name' => 'فارسی'],
            'fi' => ['english_name' => 'Finnish', 'native_name' => 'Suomi'],
            'fr_BE' => ['english_name' => 'French (Belgium)', 'native_name' => 'Français de Belgique'],
            'fr_FR' => ['english_name' => 'French (France)', 'native_name' => 'Français'],
            'fr_CA' => ['english_name' => 'French (Canada)', 'native_name' => 'Français du Canada'],
            'gd' => ['english_name' => 'Scottish Gaelic', 'native_name' => 'Gàidhlig'],
            'gl_ES' => ['english_name' => 'Galician', 'native_name' => 'Galego'],
            'haz' => ['english_name' => 'Hazaragi', 'native_name' => 'هزاره گی'],
            'he_IL' => ['english_name' => 'Hebrew', 'native_name' => 'עִבְרִית'],
            'hi_IN' => ['english_name' => 'Hindi', 'native_name' => 'हिन्दी'],
            'hr' => ['english_name' => 'Croatian', 'native_name' => 'Hrvatski'],
            'hu_HU' => ['english_name' => 'Hungarian', 'native_name' => 'Magyar'],
            'hy' => ['english_name' => 'Armenian', 'native_name' => 'Հայերեն'],
            'id_ID' => ['english_name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia'],
            'is_IS' => ['english_name' => 'Icelandic', 'native_name' => 'Íslenska'],
            'it_IT' => ['english_name' => 'Italian', 'native_name' => 'Italiano'],
            'ja' => ['english_name' => 'Japanese', 'native_name' => '日本語'],
            'ka_GE' => ['english_name' => 'Georgian', 'native_name' => 'ქართული'],
            'ko_KR' => ['english_name' => 'Korean', 'native_name' => '한국어'],
            'lt_LT' => ['english_name' => 'Lithuanian', 'native_name' => 'Lietuvių kalba'],
            'mk_MK' => ['english_name' => 'Macedonian', 'native_name' => 'Македонски јазик'],
            'mr' => ['english_name' => 'Marathi', 'native_name' => 'मराठी'],
            'ms_MY' => ['english_name' => 'Malay', 'native_name' => 'Bahasa Melayu'],
            'my_MM' => ['english_name' => 'Myanmar (Burmese)', 'native_name' => 'ဗမာစာ'],
            'nb_NO' => ['english_name' => 'Norwegian (Bokmål)', 'native_name' => 'Norsk bokmål'],
            'nl_NL' => ['english_name' => 'Dutch', 'native_name' => 'Nederlands'],
            'nl_NL_formal' => ['english_name' => 'Dutch (Formal)', 'native_name' => 'Nederlands (Formeel)'],
            'nn_NO' => ['english_name' => 'Norwegian (Nynorsk)', 'native_name' => 'Norsk nynorsk'],
            'oci' => ['english_name' => 'Occitan', 'native_name' => 'Occitan'],
            'pl_PL' => ['english_name' => 'Polish', 'native_name' => 'Polski'],
            'ps' => ['english_name' => 'Pashto', 'native_name' => 'پښتو'],
            'pt_BR' => ['english_name' => 'Portuguese (Brazil)', 'native_name' => 'Português do Brasil'],
            'pt_PT' => ['english_name' => 'Portuguese (Portugal)', 'native_name' => 'Português'],
            'ro_RO' => ['english_name' => 'Romanian', 'native_name' => 'Română'],
            'ru_RU' => ['english_name' => 'Russian', 'native_name' => 'Русский'],
            'sk_SK' => ['english_name' => 'Slovak', 'native_name' => 'Slovenčina'],
            'sl_SI' => ['english_name' => 'Slovenian', 'native_name' => 'Slovenščina'],
            'sq' => ['english_name' => 'Albanian', 'native_name' => 'Shqip'],
            'sr_RS' => ['english_name' => 'Serbian', 'native_name' => 'Српски језик'],
            'sv_SE' => ['english_name' => 'Swedish', 'native_name' => 'Svenska'],
            'th' => ['english_name' => 'Thai', 'native_name' => 'ไทย'],
            'tl' => ['english_name' => 'Tagalog', 'native_name' => 'Tagalog'],
            'tr_TR' => ['english_name' => 'Turkish', 'native_name' => 'Türkçe'],
            'ug_CN' => ['english_name' => 'Uighur', 'native_name' => 'Uyƣurqə'],
            'uk' => ['english_name' => 'Ukrainian', 'native_name' => 'Українська'],
            'vi' => ['english_name' => 'Vietnamese', 'native_name' => 'Tiếng Việt'],
            'zh_CN' => ['english_name' => 'Chinese (China)', 'native_name' => '简体中文'],
            'zh_TW' => ['english_name' => 'Chinese (Taiwan)', 'native_name' => '繁體中文'],
        ];
    }
}
