<?php

namespace SEOChangeMonitor\src\CLI;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Providers\CronProvider;
use SEOChangeMonitor\Services\Baseline\BaselineManager;
use SEOChangeMonitor\Services\CheckEngine\CheckRunner;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Findings\ExplanationRegistry;
use SEOChangeMonitor\Services\ImpairedState;
use SEOChangeMonitor\Services\Maintenance\RetentionPruner;
use SEOChangeMonitor\Services\Settings;
use WP_CLI;

/**
 * Manage SEO change monitoring.
 */
class Commands
{
    public static function register()
    {
        WP_CLI::add_command('seo-monitor', self::class);
    }

    /**
     * Runs a check now.
     *
     * ## OPTIONS
     *
     * [--wait]
     * : Process the whole queue in this process instead of leaving it to cron.
     *
     * ## EXAMPLES
     *
     *     wp seo-monitor check --wait
     *
     * @when after_wp_load
     */
    public function check($args, $assocArgs)
    {
        $runner = new CheckRunner();

        $run = isset($assocArgs['wait'])
            ? $runner->runSync('cli')
            : $runner->startRun('cli');

        if (!$run) {
            WP_CLI::error('A check is already running. Try again in a moment.');
        }

        WP_CLI::success(
            sprintf(
                'Run #%d %s: %d critical, %d warning, %d info (%d/%d targets).',
                $run->id,
                $run->status,
                $run->critical_count,
                $run->warning_count,
                $run->info_count,
                $run->targets_done,
                $run->targets_total
            )
        );
    }

    /**
     * Shows monitoring status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * @when after_wp_load
     */
    public function status($args, $assocArgs)
    {
        $lastRun  = CheckRun::whereNotNull('finished_at')->orderBy('id')->desc()->first();
        $next     = CronProvider::nextScheduled();
        $impaired = ImpairedState::details();

        $row = [
            'monitored_pages'  => (int) Target::where('is_active', 1)->where('type', Target::TYPE_PAGE)->count(),
            'open_critical'    => (int) Finding::where('status', Finding::STATUS_OPEN)->where('severity', Finding::SEVERITY_CRITICAL)->count(),
            'open_warning'     => (int) Finding::where('status', Finding::STATUS_OPEN)->where('severity', Finding::SEVERITY_WARNING)->count(),
            'open_info'        => (int) Finding::where('status', Finding::STATUS_OPEN)->where('severity', Finding::SEVERITY_INFO)->count(),
            'frequency'        => (string) Settings::get('frequency'),
            'last_run'         => $lastRun ? $lastRun->finished_at : 'never',
            'last_run_status'  => $lastRun ? $lastRun->status : 'n/a',
            'next_run'         => $next ? gmdate('Y-m-d H:i:s', $next) : 'not scheduled',
            'baseline_armed'   => BaselineManager::isArmed() ? 'yes' : 'no',
            'monitoring'       => $impaired ? 'impaired: ' . $impaired['reason'] : 'ok',
        ];

        WP_CLI\Utils\format_items(
            $assocArgs['format'],
            [(object) $row],
            array_keys($row)
        );
    }

    /**
     * Lists recorded findings.
     *
     * ## OPTIONS
     *
     * [--severity=<severity>]
     * : Filter by severity (critical, warning, info).
     *
     * [--status=<status>]
     * : Filter by status (open, resolved, auto_resolved, muted).
     *
     * [--limit=<limit>]
     * : How many to show.
     * ---
     * default: 20
     * ---
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     *   - csv
     * ---
     *
     * @when after_wp_load
     */
    public function log($args, $assocArgs)
    {
        $query = Finding::orderBy('id')->desc();

        if (!empty($assocArgs['severity'])) {
            $query = $query->where('severity', $assocArgs['severity']);
        }

        if (!empty($assocArgs['status'])) {
            $query = $query->where('status', $assocArgs['status']);
        }

        $findings = Db::rows($query->take((int) $assocArgs['limit'])->get());

        if ($findings === []) {
            WP_CLI::log('No findings recorded.');

            return;
        }

        $targets = [];
        $rows    = [];
        foreach ($findings as $finding) {
            if ($finding->target_id && !isset($targets[$finding->target_id])) {
                $target                          = Target::findOne(['id' => $finding->target_id]);
                $targets[$finding->target_id]    = $target ? $target->label : null;
            }

            $after       = json_decode((string) $finding->after_value, true) ?: [];
            $context     = isset($after['context']) && \is_array($after['context']) ? $after['context'] : [];
            $explanation = ExplanationRegistry::get($finding->change_type, $context);

            $rows[] = (object) [
                'id'          => (int) $finding->id,
                'severity'    => $finding->severity,
                'status'      => $finding->status,
                'change'      => $finding->change_type,
                'page'        => $finding->target_id ? $targets[$finding->target_id] : 'site-wide',
                'detected'    => $finding->created_at,
                'explanation' => $explanation['what'],
            ];
        }

        WP_CLI\Utils\format_items(
            $assocArgs['format'],
            $rows,
            ['id', 'severity', 'status', 'change', 'page', 'detected', 'explanation']
        );
    }

    /**
     * Snapshots every monitored page as a known-good baseline before you run updates.
     *
     * @when after_wp_load
     */
    public function arm($args, $assocArgs)
    {
        $count = BaselineManager::arm();

        WP_CLI::success(
            sprintf('Baseline armed for %d targets. The next check compares against it.', $count)
        );
    }

    /**
     * Deletes history older than the configured retention.
     *
     * @when after_wp_load
     */
    public function prune($args, $assocArgs)
    {
        $result = (new RetentionPruner())->prune();

        WP_CLI::success(
            sprintf(
                'Pruned %d snapshots, %d findings, %d events, %d runs.',
                $result['snapshots'],
                $result['findings'],
                $result['events'],
                $result['runs']
            )
        );
    }
}
