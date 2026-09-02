<?php

namespace SEOChangeMonitor\Services\Alerts;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\Db;

/**
 * What each notification channel reports on.
 *
 * Email, Slack and webhooks all answer the same two questions: which findings
 * from this run clear the user's severity threshold, and which criticals cleared
 * while it ran. Only the delivery differs, so the selection lives here.
 */
class Digest
{
    private const RANK = ['info' => 0, 'warning' => 1, 'critical' => 2];

    /**
     * Severity as a comparable number.
     *
     * @param string $severity
     * @param int    $fallback used when the stored value is not a known severity
     */
    public static function rank($severity, $fallback = 0)
    {
        return isset(self::RANK[$severity]) ? self::RANK[$severity] : $fallback;
    }

    /**
     * Open findings from one run at or above a threshold, in database order.
     *
     * @param int    $runId
     * @param string $threshold        the user's chosen minimum severity
     * @param string $fallbackSeverity used when the stored threshold is invalid
     *
     * @return Finding[]
     */
    public static function openFindings($runId, $threshold, $fallbackSeverity)
    {
        $minRank = self::rank($threshold, self::rank($fallbackSeverity));

        $rows = Db::rows(
            Finding::where('check_run_id', $runId)
                ->where('status', Finding::STATUS_OPEN)
                ->get()
        );

        return array_values(
            array_filter($rows, static fn ($row) => self::rank($row->severity, -1) >= $minRank)
        );
    }

    /**
     * Most severe first, for channels that show a list to a person.
     *
     * @param Finding[] $findings
     *
     * @return Finding[]
     */
    public static function sortBySeverity(array $findings)
    {
        usort($findings, static fn ($a, $b) => self::rank($b->severity) <=> self::rank($a->severity));

        return $findings;
    }

    /**
     * Criticals that cleared while this run was in flight, for the all-clear.
     *
     * @param false|object $run a CheckRun, or false when the run row is gone
     *
     * @return Finding[]
     */
    public static function resolvedCriticals($run)
    {
        if (!$run || !$run->started_at) {
            return [];
        }

        return Db::rows(
            Finding::where('status', Finding::STATUS_AUTO_RESOLVED)
                ->where('severity', Finding::SEVERITY_CRITICAL)
                ->where('resolved_at', '>=', $run->started_at)
                ->get()
        );
    }
}
