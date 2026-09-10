<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Providers\CronProvider;
use SEOChangeMonitor\Services\ImpairedState;
use SEOChangeMonitor\Services\Settings;

/**
 * Read-only status for external monitoring. Admin-only; exposes no page content
 * and no credentials.
 */
class RestStatusController
{
    public function status()
    {
        $lastRun  = CheckRun::whereNotNull('finished_at')->orderBy('id')->desc()->first();
        $next     = CronProvider::nextScheduled();
        $impaired = ImpairedState::details();

        return Response::success(
            [
                'monitoring'      => $impaired ? 'impaired' : 'ok',
                'impaired_since'  => $impaired ? $impaired['since'] : null,
                'impaired_reason' => $impaired ? $impaired['reason'] : null,
                'monitored_pages' => (int) Target::where('is_active', 1)
                    ->where('type', Target::TYPE_PAGE)->count(),
                'open_findings'   => [
                    'critical' => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_CRITICAL)->count(),
                    'warning'  => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_WARNING)->count(),
                    'info'     => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_INFO)->count(),
                ],
                'frequency'       => (string) Settings::get('frequency'),
                'last_run'        => $lastRun ? [
                    'id'          => (int) $lastRun->id,
                    'status'      => $lastRun->status,
                    'trigger'     => $lastRun->trigger_type,
                    'finished_at' => $lastRun->finished_at,
                ] : null,
                'next_run_at'     => $next ? gmdate('Y-m-d H:i:s', $next) : null,
            ]
        );
    }
}
