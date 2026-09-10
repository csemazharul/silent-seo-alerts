<?php

namespace SEOChangeMonitor\src;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Views\Body;

final class Menu
{
    public static function getSideBarMenu(Body $body)
    {
        $menu = [
            'Home' => [
                'type'       => 'menu',
                'title'      => Config::TITLE,
                'name'       => Config::TITLE,
                'capability' => 'manage_options',
                'slug'       => Config::SLUG,
                'callback'   => [$body, 'render'],
                'icon'       => 'dashicons-visibility',
                'position'   => '20',
            ],
        ];

        // Submenu entries point at the same admin page with a hash route, so
        // the React app navigates without a page load.
        $routes = [
            ''               => __('Dashboard', 'silent-seo-alerts'),
            '#/pages'        => __('Monitored Pages', 'silent-seo-alerts'),
            '#/log'          => __('Flight Log', 'silent-seo-alerts'),
            '#/site'         => __('Site-wide', 'silent-seo-alerts'),
            '#/integrations' => __('Integrations', 'silent-seo-alerts'),
            '#/settings'     => __('Settings', 'silent-seo-alerts'),
        ];

        foreach ($routes as $route => $label) {
            $menu['submenu' . $route] = [
                'type'       => 'submenu',
                'parent'     => Config::SLUG,
                'title'      => $label,
                'name'       => $label,
                'capability' => 'manage_options',
                'slug'       => Config::SLUG . $route,
            ];
        }

        return $menu;
    }
}
