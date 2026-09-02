<?php

namespace SEOChangeMonitor\Services\Maintenance;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Settings;

/**
 * Trims history to the retention the user chose.
 *
 * Two things are never deleted: unresolved critical findings, and each target's
 * latest snapshot (which is the comparison base for the next run).
 */
class RetentionPruner
{
    /** Events stay at least this long regardless of retention, for attribution. */
    private const MIN_EVENT_DAYS = 90;

    public function prune()
    {
        $days = (int) Settings::get('retention_days');

        if ($days <= 0) {
            return ['snapshots' => 0, 'findings' => 0, 'events' => 0, 'runs' => 0];
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        return [
            'snapshots' => $this->pruneSnapshots($cutoff),
            'findings'  => $this->pruneFindings($cutoff),
            'events'    => $this->pruneEvents(max($days, self::MIN_EVENT_DAYS)),
            'runs'      => $this->pruneRuns($cutoff),
        ];
    }

    private function pruneSnapshots($cutoff)
    {
        $snapshots = Db::table('snapshots');

        // Keep baselines and the newest snapshot per target.
        return (int) Db::query(
            "DELETE s FROM `{$snapshots}` s
             JOIN (SELECT target_id, MAX(id) AS newest FROM `{$snapshots}` GROUP BY target_id) latest
               ON latest.target_id = s.target_id
             WHERE s.created_at < %s
               AND s.is_baseline = 0
               AND s.id <> latest.newest",
            [$cutoff]
        );
    }

    private function pruneFindings($cutoff)
    {
        $findings = Db::table('findings');

        return (int) Db::query(
            "DELETE FROM `{$findings}`
             WHERE created_at < %s
               AND NOT (severity = %s AND status = %s)",
            [$cutoff, Finding::SEVERITY_CRITICAL, Finding::STATUS_OPEN]
        );
    }

    private function pruneEvents($days)
    {
        $events = Db::table('events');
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        return (int) Db::query("DELETE FROM `{$events}` WHERE created_at < %s", [$cutoff]);
    }

    private function pruneRuns($cutoff)
    {
        $runs = Db::table('check_runs');

        return (int) Db::query(
            "DELETE FROM `{$runs}` WHERE created_at < %s AND status <> 'running'",
            [$cutoff]
        );
    }
}
