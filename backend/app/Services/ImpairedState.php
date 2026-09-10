<?php

namespace SEOChangeMonitor\Services;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\CheckEngine\ChangeTypes;

/**
 * Tracks whether the plugin can fetch this site's own pages at all.
 *
 * While impaired the plugin must never report an all-clear: no auto-resolve
 * runs, and every surface says so plainly.
 */
class ImpairedState
{
    public static function isImpaired()
    {
        return (bool) Config::getOption('impaired');
    }

    public static function details()
    {
        $stored = Config::getOption('impaired');

        return \is_array($stored) ? $stored : null;
    }

    public static function mark($reason)
    {
        if (!self::isImpaired()) {
            Config::updateOption(
                'impaired',
                ['since' => gmdate('Y-m-d H:i:s'), 'reason' => (string) $reason]
            );
        }

        $existing = Finding::where('change_type', ChangeTypes::MONITORING_IMPAIRED)
            ->where('status', Finding::STATUS_OPEN)
            ->first();

        if (!$existing) {
            Finding::insert(
                [
                    'target_id'    => null,
                    'change_type'  => ChangeTypes::MONITORING_IMPAIRED,
                    'severity'     => Finding::SEVERITY_WARNING,
                    'status'       => Finding::STATUS_OPEN,
                    'before_value' => wp_json_encode(['value' => null]),
                    'after_value'  => wp_json_encode(['value' => (string) $reason, 'context' => []]),
                ]
            );
        }
    }

    public static function clear()
    {
        if (!self::isImpaired()) {
            return;
        }

        Config::deleteOption('impaired');

        $open = Finding::where('change_type', ChangeTypes::MONITORING_IMPAIRED)
            ->where('status', Finding::STATUS_OPEN)
            ->get();

        foreach ((array) $open as $finding) {
            Db::update(
                'findings',
                ['status' => Finding::STATUS_AUTO_RESOLVED, 'resolved_at' => gmdate('Y-m-d H:i:s')],
                ['id' => $finding->id]
            );
        }
    }
}
