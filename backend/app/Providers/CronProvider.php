<?php

namespace SEOChangeMonitor\Providers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Services\CheckEngine\CheckRunner;
use SEOChangeMonitor\Services\Maintenance\BotSilenceChecker;
use SEOChangeMonitor\Services\Reports\WeeklyReport;
use SEOChangeMonitor\Services\Maintenance\RetentionPruner;
use SEOChangeMonitor\Services\Settings;

/**
 * Owns every scheduled event the plugin uses.
 */
class CronProvider
{
    public const HOOK_SCHEDULED   = 'scheduled_check';

    public const HOOK_TICK        = 'run_tick';

    public const HOOK_POST_CHANGE = 'post_change_check';

    public const HOOK_MAINTENANCE = 'daily_maintenance';

    public const HOOK_WEEKLY_REPORT = 'weekly_report';

    public function __construct()
    {
        Hooks::addAction(Config::withPrefix(self::HOOK_SCHEDULED), [$this, 'runScheduled']);
        Hooks::addAction(Config::withPrefix(self::HOOK_TICK), [$this, 'runTick']);
        Hooks::addAction(Config::withPrefix(self::HOOK_POST_CHANGE), [$this, 'runPostChange']);
        Hooks::addAction(Config::withPrefix(self::HOOK_MAINTENANCE), [$this, 'runMaintenance']);
        Hooks::addAction(Config::withPrefix(self::HOOK_WEEKLY_REPORT), [$this, 'runWeeklyReport']);

        // Activation/deactivation scheduling is wired in SetupProvider, which is
        // constructed at plugin load, because this class only exists from `init` onward.
        Hooks::addAction(Config::withPrefix('settings_updated'), [self::class, 'onSettingsUpdated']);

        // WP-Cron is unreliable on low-traffic sites; catch up on admin hits.
        Hooks::addAction('admin_init', [$this, 'catchUpStalledRun']);
    }

    public function runScheduled()
    {
        (new CheckRunner())->startRun('scheduled');
    }

    public function runTick()
    {
        (new CheckRunner())->tick();
    }

    public function runPostChange()
    {
        (new CheckRunner())->startRun('post_change');
    }

    public function runMaintenance()
    {
        (new BotSilenceChecker())->check();
        (new RetentionPruner())->prune();
    }

    public function runWeeklyReport()
    {
        if (!Settings::get('report_enabled')) {
            return;
        }

        (new WeeklyReport())->send();
    }

    /**
     * If a queued run has gone quiet (cron never fired), nudge it along on the
     * next admin page load rather than leaving it stuck.
     */
    public function catchUpStalledRun()
    {
        $queue = Config::getOption('run_queue');

        if (!\is_array($queue) || empty($queue['started_at'])) {
            return;
        }

        $hook = Config::withPrefix(self::HOOK_TICK);
        if ((time() - (int) $queue['started_at']) > 300 && !wp_next_scheduled($hook)) {
            (new CheckRunner())->tick();
        }
    }

    public static function scheduleAll()
    {
        self::scheduleRecurringCheck(Settings::get('frequency'));

        $maintenance = Config::withPrefix(self::HOOK_MAINTENANCE);
        if (!wp_next_scheduled($maintenance)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', $maintenance);
        }

        $report = Config::withPrefix(self::HOOK_WEEKLY_REPORT);
        if (!wp_next_scheduled($report)) {
            wp_schedule_event(self::nextMondayMorning(), 'weekly', $report);
        }
    }

    /** Reports land Monday morning, when someone is around to read them. */
    private static function nextMondayMorning()
    {
        $next = strtotime('next monday 08:00', current_time('timestamp'));

        return $next ? $next - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS) : time() + WEEK_IN_SECONDS;
    }

    public static function unscheduleAll()
    {
        $hooks = [self::HOOK_SCHEDULED, self::HOOK_TICK, self::HOOK_POST_CHANGE, self::HOOK_MAINTENANCE, self::HOOK_WEEKLY_REPORT];
        foreach ($hooks as $hook) {
            wp_unschedule_hook(Config::withPrefix($hook));
        }
    }

    public static function onSettingsUpdated($settings)
    {
        $frequency = \is_array($settings) && isset($settings['frequency'])
            ? $settings['frequency']
            : Settings::get('frequency');

        self::scheduleRecurringCheck($frequency);
    }

    public static function scheduleRecurringCheck($frequency)
    {
        $hook = Config::withPrefix(self::HOOK_SCHEDULED);
        wp_unschedule_hook($hook);

        if ($frequency === 'off' || !\in_array($frequency, ['hourly', 'twicedaily', 'daily'], true)) {
            return;
        }

        wp_schedule_event(time() + 300, $frequency, $hook);
    }

    /**
     * Next scheduled run as a UTC timestamp, for the dashboard.
     *
     * @return int|false
     */
    public static function nextScheduled()
    {
        return wp_next_scheduled(Config::withPrefix(self::HOOK_SCHEDULED));
    }
}
