<?php

namespace SEOChangeMonitor\Views;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;

class PluginPageActions
{
    public function getActionLinks()
    {
        return [
            [
                'url'   => admin_url('admin.php?page=' . Config::SLUG . '#/settings'),
                'title' => __('Settings', 'silent-seo-alerts'),
            ],
            [
                'url'   => 'https://wordpress.org/support/plugin/silent-seo-alerts/',
                'title' => __('Support', 'silent-seo-alerts'),
            ],
        ];
    }

    public function renderActionLinks($links)
    {
        $actionLinks = [];
        foreach ($this->getActionLinks() as $link) {
            $actionLinks[] = '<a href="' . esc_url($link['url']) . '">' . esc_html($link['title']) . '</a>';
        }

        return array_merge($actionLinks, $links);
    }
}
