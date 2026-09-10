<?php

namespace SEOChangeMonitor\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Settings;

/**
 * Activation seeding and uninstall cleanup.
 */
class SetupProvider
{
    public function __construct()
    {
        Hooks::addAction(Config::withPrefix('activate'), [$this, 'activate']);
        Hooks::addAction(Config::withPrefix('deactivate'), [CronProvider::class, 'unscheduleAll']);
    }

    public function activate()
    {
        if (!Config::getOption('settings')) {
            Config::updateOption('settings', Settings::defaults());
        }

        $this->seedSystemTargets();
        $this->seedSuggestedPages();

        CronProvider::scheduleAll();
    }

    /**
     * The three site-wide checks are rows in the targets table so they flow
     * through the same snapshot/diff/finding pipeline as pages.
     */
    public function seedSystemTargets()
    {
        $systems = [
            [Target::TYPE_ROBOTS, home_url('/robots.txt'), __('robots.txt', 'silent-seo-alerts')],
            [Target::TYPE_SITEMAP, '', __('XML sitemap', 'silent-seo-alerts')],
            [Target::TYPE_SITE_SETTINGS, '', __('Site indexing settings', 'silent-seo-alerts')],
        ];

        foreach ($systems as [$type, $url, $label]) {
            if (!Target::findOne(['type' => $type])) {
                Target::insert(['type' => $type, 'url' => $url, 'label' => $label, 'is_active' => 1]);
            }
        }
    }

    /**
     * Seed the front page as an active target and recent pages as inactive
     * suggestions the user can switch on.
     */
    public function seedSuggestedPages()
    {
        if (Target::findOne(['type' => Target::TYPE_PAGE])) {
            return; // already seeded (re-activation)
        }

        Target::insert(
            [
                'type'      => Target::TYPE_PAGE,
                'post_id'   => (int) get_option('page_on_front') ?: null,
                'url'       => home_url('/'),
                'label'     => __('Front page', 'silent-seo-alerts'),
                'is_active' => 1,
            ]
        );

        $pages = get_posts(
            [
                'post_type'   => ['page', 'post'],
                'post_status' => 'publish',
                'numberposts' => 10,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]
        );

        foreach ($pages as $page) {
            $permalink = get_permalink($page);
            if (!$permalink || untrailingslashit($permalink) === untrailingslashit(home_url('/'))) {
                continue;
            }

            Target::insert(
                [
                    'type'      => Target::TYPE_PAGE,
                    'post_id'   => $page->ID,
                    'url'       => $permalink,
                    'label'     => $page->post_title !== '' ? $page->post_title : $permalink,
                    'is_active' => 0,
                ]
            );
        }
    }
}
