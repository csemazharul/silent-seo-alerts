<?php

namespace SEOChangeMonitor\Services\Baseline;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\CheckEngine\TargetChecker;
use SEOChangeMonitor\Services\Db;

/**
 * "Arm before you update": snapshots every monitored page as a known-good
 * baseline, so the next check compares against that rather than the last run.
 */
class BaselineManager
{
    /** An armed baseline expires on its own so it cannot silently skew later runs. */
    public const EXPIRY_SECONDS = 86400;

    public static function isArmed()
    {
        $armed = Config::getOption('armed_baseline');

        if (!\is_array($armed) || empty($armed['armed_at'])) {
            return false;
        }

        if ((time() - (int) $armed['armed_at']) > self::EXPIRY_SECONDS) {
            Config::deleteOption('armed_baseline');

            return false;
        }

        return true;
    }

    public static function details()
    {
        $armed = Config::getOption('armed_baseline');

        return self::isArmed() && \is_array($armed) ? $armed : null;
    }

    /**
     * @return int number of pages baselined
     */
    public static function arm()
    {
        $checker = new TargetChecker();
        $targets = Db::rows(Target::where('is_active', 1)->get());

        $count = 0;
        foreach ($targets as $target) {
            if ($checker->snapshot($target, null, true)) {
                ++$count;
            }
        }

        Config::updateOption('armed_baseline', ['armed_at' => time(), 'targets' => $count]);

        return $count;
    }

    public static function disarm()
    {
        Config::deleteOption('armed_baseline');
    }

    /**
     * Called at the end of every run: once a comparison against the baseline
     * has happened, the baseline has done its job.
     */
    public static function disarmAfterComparison($runId)
    {
        if (self::isArmed()) {
            self::disarm();
        }
    }
}
