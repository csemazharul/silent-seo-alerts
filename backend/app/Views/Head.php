<?php

namespace SEOChangeMonitor\Views;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Helpers\DateTimeHelper;

class Head
{
    /** Bundled under assets/fonts - nothing here is fetched from a third party. */
    public const FONT_PATH = '/fonts/outfit.css';

    public function addHeadScripts($currentScreen)
    {
        if (!str_contains($currentScreen, Config::SLUG)) {
            return;
        }

        $version  = Config::VERSION;
        $slug     = Config::SLUG;
        $codeName = Config::get('BUILD_CODE_NAME');

        wp_enqueue_style($slug . '-font', Config::get('ASSET_URI') . self::FONT_PATH, [], $version);

        if (Config::getEnv('DEV')) {
            wp_enqueue_script($slug . '-vite-client-helper-MODULE', Config::getEnv('DEV_URL') . '/src/config/devHotModule.js', [], null);
            wp_enqueue_script($slug . '-vite-client-MODULE', Config::getEnv('DEV_URL') . '/@vite/client', [], null);
            wp_enqueue_script($slug . '-index-MODULE', Config::getEnv('DEV_URL') . '/src/main.tsx', [], null);
        } else {
            wp_enqueue_script($slug . '-index-MODULE', Config::get('ASSET_URI') . "/main-{$codeName}.js", [], $version);
            wp_enqueue_style($slug . '-styles', Config::get('ASSET_URI') . "/main-{$slug}-ba-assets-{$codeName}.css", null, $version, 'screen');
        }

        wp_localize_script(Config::SLUG . '-index-MODULE', Config::VAR_PREFIX, self::createConfigVariable());

        if (!wp_script_is('media-upload')) {
            wp_enqueue_media();
        }
    }

    public static function createConfigVariable()
    {
        $frontendVars = apply_filters(
            Config::withPrefix('localized_script'),
            [
                'nonce'          => wp_create_nonce(Config::withPrefix('nonce')),
                'restNonce'      => wp_create_nonce('wp_rest'),
                'rootURL'        => Config::get('ROOT_URI'),
                'siteURL'        => Config::get('SITE_URL'),
                'siteBaseURL'    => is_multisite() ? network_site_url() : site_url(),
                'assetsURL'      => Config::get('ASSET_URI'),
                'pluginAdminURL' => get_admin_url(null, 'admin.php?page=' . Config::SLUG . '#'),
                'redirectUri'    => Config::get('REDIRECT_URI'),
                'ajaxURL'        => admin_url('admin-ajax.php'),
                'apiURL'         => Config::get('API_URL'),
                'routePrefix'    => Config::VAR_PREFIX,
                // Same filter the settings endpoint uses, so an add-on's
                // secrets never reach the page through this route either.
                'settings'       => apply_filters(
                    Config::withPrefix('settings_for_client'),
                    (array) Config::getOption('settings')
                ),
                'dateFormat'     => Config::getOption('date_format', false, true),
                'timeFormat'     => Config::getOption('time_format', false, true),
                'timeZone'       => DateTimeHelper::wp_timezone_string(),
                'pluginSlug'     => Config::SLUG,
                'uploadBaseUrl'  => Config::get('UPLOAD_BASE_URL'),
                'version'        => Config::VERSION,
                'lang'           => get_locale(),

                // The app hides pro-only screens unless the add-on is active,
                // so this is the single source of truth for that.
                'isSeoChangeMonitorProExist' => defined('SEO_CHANGE_MONITOR_PRO_VERSION') ? '1' : '0',
                'proPluginVersion'           => defined('SEO_CHANGE_MONITOR_PRO_VERSION')
                    ? SEO_CHANGE_MONITOR_PRO_VERSION
                    : '',
            ]
        );

        if (get_locale() !== 'en_US' && file_exists(Config::get('ROOT_DIR') . '/languages/frontend-extracted-strings.php')) {
            $frontendVars['translations'] = include Config::get('ROOT_DIR') . '/languages/frontend-extracted-strings.php';
        }

        return $frontendVars;
    }
}
