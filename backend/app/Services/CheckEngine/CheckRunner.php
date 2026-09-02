<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Snapshot;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Alerts\AlertMailer;
use SEOChangeMonitor\Services\Alerts\SlackNotifier;
use SEOChangeMonitor\Services\Alerts\WebhookNotifier;
use SEOChangeMonitor\Services\Baseline\BaselineManager;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Events\Attribution;
use SEOChangeMonitor\Services\Findings\FindingRecorder;
use SEOChangeMonitor\Services\ImpairedState;

/**
 * Owns a check run: queues the targets, processes them in chunks small enough
 * to survive any host's timeout, then finalizes.
 */
class CheckRunner
{
    /** Targets per tick, plus a wall-clock guard for slow sites. */
    public const CHUNK_SIZE = 10;

    public const CHUNK_SECONDS = 20;

    /** A queue older than this is considered abandoned and may be replaced. */
    public const STALE_QUEUE_SECONDS = 900;

    /** @var TargetChecker */
    private $checker;

    /** @var SnapshotDiffer */
    private $differ;

    /** @var SiteChecker */
    private $siteChecker;

    /** @var SeverityClassifier */
    private $classifier;

    /** @var FindingRecorder */
    private $recorder;

    /** @var Attribution */
    private $attribution;

    public function __construct()
    {
        $this->checker     = new TargetChecker();
        $this->differ      = new SnapshotDiffer();
        $this->siteChecker = new SiteChecker();
        $this->classifier  = new SeverityClassifier();
        $this->recorder    = new FindingRecorder();
        $this->attribution = new Attribution();
    }

    /**
     * Starts a run and processes the first chunk inline, scheduling the rest.
     *
     * @param string $trigger scheduled|post_change|manual|baseline|cli
     *
     * @return CheckRun|false false when another run is already in flight
     */
    public function startRun($trigger = 'manual')
    {
        if ($this->hasLiveQueue()) {
            return false;
        }

        $targets = $this->queueableTargets();

        $run = CheckRun::insert(
            [
                'trigger_type'  => $trigger,
                'status'        => CheckRun::STATUS_RUNNING,
                'targets_total' => \count($targets),
                'targets_done'  => 0,
                'started_at'    => gmdate('Y-m-d H:i:s'),
            ]
        );

        if (!$run) {
            return false;
        }

        Config::updateOption(
            'run_queue',
            [
                'run_id'      => (int) $run->id,
                'pending_ids' => array_map(static fn ($target) => (int) $target->id, $targets),
                'errors'      => 0,
                'started_at'  => time(),
            ]
        );

        $this->tick();

        return CheckRun::findOne(['id' => $run->id]);
    }

    /**
     * Processes one chunk of the queued run, then either reschedules itself or
     * finalizes. Safe to call from cron, admin_init catch-up, or the CLI.
     */
    public function tick()
    {
        $queue = Config::getOption('run_queue');
        if (!\is_array($queue) || empty($queue['run_id'])) {
            return false;
        }

        $runId   = (int) $queue['run_id'];
        $pending = isset($queue['pending_ids']) ? (array) $queue['pending_ids'] : [];
        $errors  = isset($queue['errors']) ? (int) $queue['errors'] : 0;

        $run = CheckRun::findOne(['id' => $runId]);
        if (!$run) {
            Config::deleteOption('run_queue');

            return false;
        }

        $startedAt = time();
        $processed = 0;

        while ($pending !== [] && $processed < self::CHUNK_SIZE) {
            if ($processed > 0 && (time() - $startedAt) >= self::CHUNK_SECONDS) {
                break; // leave the rest for the next tick rather than risk a timeout
            }

            $targetId = (int) array_shift($pending);
            $target   = Target::findOne(['id' => $targetId]);

            if ($target) {
                $this->checkTarget($target, $runId, $errors);
            }

            ++$processed;
        }

        $done = (int) $run->targets_total - \count($pending);
        Db::update('check_runs', ['targets_done' => $done], ['id' => $runId]);

        if ($pending === []) {
            Config::deleteOption('run_queue');

            return $this->finalizeRun($runId, $done, (int) $run->targets_total, $errors);
        }

        $queue['pending_ids'] = array_values($pending);
        $queue['errors']      = $errors;
        Config::updateOption('run_queue', $queue);

        $this->scheduleNextTick();

        return true;
    }

    /**
     * Runs a whole check to completion in this process. Used by the CLI and the
     * "Check now" button, where the user is waiting for the result.
     */
    public function runSync($trigger = 'manual')
    {
        $run = $this->startRun($trigger);
        if (!$run) {
            return false;
        }

        $guard = 0;
        while (Config::getOption('run_queue') && $guard < 500) {
            $this->tick();
            ++$guard;
        }

        return CheckRun::findOne(['id' => $run->id]);
    }

    /**
     * Fetch, snapshot, diff, classify, and record for one target.
     */
    public function checkTarget($target, $runId, &$transportErrors)
    {
        $comparison = $this->comparisonSnapshot($target);
        $snapshot   = $this->checker->snapshot($target, $runId);

        if (!$snapshot) {
            return;
        }

        if ($snapshot->fetch_error) {
            ++$transportErrors;

            return; // never diff or resolve against a failed fetch
        }

        $currentFields = json_decode((string) $snapshot->fields, true) ?: [];

        if ($comparison && $comparison->content_hash !== $snapshot->content_hash) {
            $previousFields = json_decode((string) $comparison->fields, true) ?: [];

            $changes = \in_array($target->type, Target::SYSTEM_TYPES, true)
                ? $this->siteChecker->diff($target->type, $previousFields, $currentFields)
                : $this->differ->diff($previousFields, $currentFields);

            $context = $this->attribution->context($target, $runId);

            foreach ($changes as $change) {
                $severity = $this->classifier->classify($change, $context);
                $this->recorder->record($runId, $target->id, $change, $severity, $context);
            }

            if ($changes !== []) {
                Db::update('targets', ['last_result' => 'changed'], ['id' => $target->id]);
            }
        }

        $this->recorder->autoResolve($target->id, $currentFields);
    }

    /**
     * While a baseline is armed we compare against it, so a burst of updates is
     * measured against the known-good state rather than the previous run.
     */
    private function comparisonSnapshot($target)
    {
        if (BaselineManager::isArmed()) {
            $baseline = Snapshot::where('target_id', $target->id)
                ->where('is_baseline', 1)
                ->orderBy('id')->desc()
                ->first();

            if ($baseline) {
                return $baseline;
            }
        }

        return Snapshot::where('target_id', $target->id)
            ->where('is_baseline', 0)
            ->orderBy('id')->desc()
            ->first();
    }

    private function finalizeRun($runId, $done, $total, $transportErrors)
    {
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        $rows   = Finding::where('check_run_id', $runId)->get() ?: [];
        foreach ((array) $rows as $row) {
            if (isset($counts[$row->severity])) {
                ++$counts[$row->severity];
            }
        }

        $isImpaired = $total > 0 && $transportErrors >= $total;

        if ($isImpaired) {
            ImpairedState::mark(
                __('The site could not fetch its own pages (loopback requests are failing).', 'seo-change-monitor')
            );
        } else {
            ImpairedState::clear();
        }

        Db::update(
            'check_runs',
            [
                'status'         => $isImpaired ? CheckRun::STATUS_IMPAIRED : CheckRun::STATUS_COMPLETE,
                'targets_done'   => $done,
                'critical_count' => $counts['critical'],
                'warning_count'  => $counts['warning'],
                'info_count'     => $counts['info'],
                'finished_at'    => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $runId]
        );

        BaselineManager::disarmAfterComparison($runId);

        (new AlertMailer())->sendRunDigest($runId);
        (new WebhookNotifier())->sendRunDigest($runId);
        (new SlackNotifier())->sendRunDigest($runId);

        do_action(Config::withPrefix('run_finished'), $runId);

        return CheckRun::findOne(['id' => $runId]);
    }

    private function queueableTargets()
    {
        $targets = Db::rows(Target::where('is_active', 1)->orderBy('id')->asc()->get());

        // System targets first: a site-wide problem explains page-level ones.
        usort(
            $targets,
            static fn ($a, $b) => ($a->type === Target::TYPE_PAGE ? 1 : 0) <=> ($b->type === Target::TYPE_PAGE ? 1 : 0)
        );

        return $targets;
    }

    private function hasLiveQueue()
    {
        $queue = Config::getOption('run_queue');

        if (!\is_array($queue) || empty($queue['started_at'])) {
            return false;
        }

        if ((time() - (int) $queue['started_at']) > self::STALE_QUEUE_SECONDS) {
            Config::deleteOption('run_queue'); // abandoned run, reclaim it

            return false;
        }

        return true;
    }

    private function scheduleNextTick()
    {
        $hook = Config::withPrefix('run_tick');

        if (!wp_next_scheduled($hook)) {
            wp_schedule_single_event(time() + 10, $hook);
        }

        // Low-traffic sites may not fire cron on their own.
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            spawn_cron();
        }
    }
}
