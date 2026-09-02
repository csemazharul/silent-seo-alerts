<?php

namespace SEOChangeMonitor\src;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\ImpairedState;
use SEOChangeMonitor\Services\Settings;

class SiteHealth
{
    private const INTERVALS = ['hourly' => HOUR_IN_SECONDS, 'twicedaily' => 43200, 'daily' => DAY_IN_SECONDS];

    public function __construct()
    {
        Hooks::addFilter('site_status_tests', [$this, 'registerTests']);
    }

    public function registerTests($tests)
    {
        $tests['direct']['seo_change_monitor_operational'] = [
            'label' => __('SEO change monitoring is working', 'seo-change-monitor'),
            'test'  => [$this, 'testOperational'],
        ];

        $tests['direct']['seo_change_monitor_criticals'] = [
            'label' => __('SEO critical findings', 'seo-change-monitor'),
            'test'  => [$this, 'testCriticals'],
        ];

        return $tests;
    }

    public function testOperational()
    {
        $result = [
            'label'       => __('SEO Change Monitor is watching your pages', 'seo-change-monitor'),
            'status'      => 'good',
            'badge'       => ['label' => __('SEO', 'seo-change-monitor'), 'color' => 'blue'],
            'description' => '<p>' . esc_html__('Checks are running on schedule and the site can fetch its own pages.', 'seo-change-monitor') . '</p>',
            'actions'     => '',
            'test'        => 'seo_change_monitor_operational',
        ];

        if (ImpairedState::isImpaired()) {
            $details = ImpairedState::details();

            $result['status']      = 'critical';
            $result['label']       = __('SEO change monitoring is impaired', 'seo-change-monitor');
            $result['description'] = '<p>' . esc_html(
                sprintf(
                    /* translators: 1: reason, 2: date */
                    __('%1$s (since %2$s). Until this is fixed, changes to your pages may go unnoticed.', 'seo-change-monitor'),
                    $details['reason'],
                    $details['since']
                )
            ) . '</p>';

            return $result;
        }

        $overdue = $this->overdueRun();
        if ($overdue) {
            $result['status']      = 'recommended';
            $result['label']       = __('SEO checks are overdue', 'seo-change-monitor');
            $result['description'] = '<p>' . esc_html__(
                'The last scheduled check ran longer ago than expected. WP-Cron may not be firing on this site; a real system cron job is more reliable.',
                'seo-change-monitor'
            ) . '</p>';
        }

        return $result;
    }

    public function testCriticals()
    {
        $count = (int) Finding::where('status', Finding::STATUS_OPEN)
            ->where('severity', Finding::SEVERITY_CRITICAL)
            ->count();

        $result = [
            'label'       => __('No critical SEO changes are outstanding', 'seo-change-monitor'),
            'status'      => 'good',
            'badge'       => ['label' => __('SEO', 'seo-change-monitor'), 'color' => 'blue'],
            'description' => '<p>' . esc_html__('Nothing critical has changed on your monitored pages.', 'seo-change-monitor') . '</p>',
            'actions'     => '',
            'test'        => 'seo_change_monitor_criticals',
        ];

        if ($count > 0) {
            $result['status'] = 'critical';
            $result['label']  = sprintf(
                /* translators: %d: number of findings */
                _n(
                    '%d critical SEO change needs your attention',
                    '%d critical SEO changes need your attention',
                    $count,
                    'seo-change-monitor'
                ),
                $count
            );
            $result['description'] = '<p>' . esc_html__(
                'Something that affects whether your pages can be found in search has changed.',
                'seo-change-monitor'
            ) . '</p>';
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                esc_url(admin_url('admin.php?page=' . Config::SLUG . '#/log')),
                esc_html__('Open the flight log', 'seo-change-monitor')
            );
        }

        return $result;
    }

    private function overdueRun()
    {
        $frequency = (string) Settings::get('frequency');
        if (!isset(self::INTERVALS[$frequency])) {
            return false;
        }

        $lastRun = CheckRun::whereNotNull('finished_at')->orderBy('id')->desc()->first();
        if (!$lastRun) {
            return false;
        }

        $finished = strtotime($lastRun->finished_at . ' UTC');

        return $finished && (time() - $finished) > self::INTERVALS[$frequency] * 2;
    }
}
