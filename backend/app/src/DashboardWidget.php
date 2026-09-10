<?php

namespace SEOChangeMonitor\src;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\ImpairedState;

/**
 * The plugin's only presence outside its own screens: one dashboard widget,
 * no admin notices anywhere.
 */
class DashboardWidget
{
    public function __construct()
    {
        Hooks::addAction('wp_dashboard_setup', [$this, 'register']);
    }

    public function register()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'seo_change_monitor_summary',
            __('Silent SEO Alerts', 'silent-seo-alerts'),
            [$this, 'render']
        );
    }

    public function render()
    {
        $counts = [];
        foreach ([Finding::SEVERITY_CRITICAL, Finding::SEVERITY_WARNING, Finding::SEVERITY_INFO] as $severity) {
            $counts[$severity] = (int) Finding::where('status', Finding::STATUS_OPEN)
                ->where('severity', $severity)
                ->count();
        }

        $lastRun  = CheckRun::whereNotNull('finished_at')->orderBy('id')->desc()->first();
        $impaired = ImpairedState::details();
        $logUrl   = admin_url('admin.php?page=' . Config::SLUG . '#/log');

        if ($impaired) {
            printf(
                '<p style="padding:8px 12px;border-left:4px solid #bd8600;background:#fcf9e8;"><strong>%s</strong><br>%s</p>',
                esc_html__('Monitoring impaired', 'silent-seo-alerts'),
                esc_html($impaired['reason'])
            );
        }

        echo '<ul style="margin:0;">';
        printf(
            '<li><strong style="color:%s;">%d</strong> %s</li>',
            $counts[Finding::SEVERITY_CRITICAL] > 0 ? '#b32d2e' : 'inherit',
            (int) $counts[Finding::SEVERITY_CRITICAL],
            esc_html__('critical changes open', 'silent-seo-alerts')
        );
        printf(
            '<li><strong>%d</strong> %s</li>',
            (int) $counts[Finding::SEVERITY_WARNING],
            esc_html__('warnings open', 'silent-seo-alerts')
        );
        printf(
            '<li><strong>%d</strong> %s</li>',
            (int) $counts[Finding::SEVERITY_INFO],
            esc_html__('informational changes open', 'silent-seo-alerts')
        );
        echo '</ul>';

        printf(
            '<p style="color:#646970;margin-bottom:6px;">%s</p>',
            $lastRun
                ? esc_html(
                    sprintf(
                        /* translators: %s: date and time */
                        __('Last checked %s', 'silent-seo-alerts'),
                        $lastRun->finished_at
                    )
                )
                : esc_html__('No check has run yet.', 'silent-seo-alerts')
        );

        printf(
            '<a href="%s" class="button button-secondary">%s</a>',
            esc_url($logUrl),
            esc_html__('Open the flight log', 'silent-seo-alerts')
        );
    }
}
