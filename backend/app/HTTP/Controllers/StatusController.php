<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Models\BotVisit;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Models\SiteEvent;
use SEOChangeMonitor\Models\Snapshot;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Providers\CronProvider;
use SEOChangeMonitor\Services\Baseline\BaselineManager;
use SEOChangeMonitor\Services\CheckEngine\AiBots;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\ImpairedState;
use SEOChangeMonitor\Services\Settings;

class StatusController
{
    public function dashboard()
    {
        $lastRun = CheckRun::whereNotNull('finished_at')->orderBy('id')->desc()->first();
        $next    = CronProvider::nextScheduled();

        return Response::success(
            [
                'open_counts' => [
                    'critical' => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_CRITICAL)->count(),
                    'warning'  => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_WARNING)->count(),
                    'info'     => (int) Finding::where('status', Finding::STATUS_OPEN)
                        ->where('severity', Finding::SEVERITY_INFO)->count(),
                ],
                'active_targets' => (int) Target::where('is_active', 1)
                    ->where('type', Target::TYPE_PAGE)->count(),
                'last_run'       => $lastRun ?: null,
                'next_run_at'    => $next ? gmdate('Y-m-d H:i:s', $next) : null,
                'frequency'      => Settings::get('frequency'),
                'impaired'       => ImpairedState::details(),
                'baseline'       => BaselineManager::details(),
                'recent_events'  => $this->recentEvents(),
            ]
        );
    }

    /**
     * Latest recorded state of the three site-wide checks, plus AI bot activity.
     */
    public function site()
    {
        $panels = [];

        foreach (Target::SYSTEM_TYPES as $type) {
            $target = Target::findOne(['type' => $type]);
            if (!$target) {
                $panels[$type] = null;

                continue;
            }

            $snapshot = Snapshot::where('target_id', $target->id)
                ->orderBy('id')->desc()
                ->first();

            $panels[$type] = [
                'checked_at' => $target->last_checked_at,
                'fields'     => $snapshot ? (json_decode((string) $snapshot->fields, true) ?: []) : [],
                'error'      => $snapshot ? $snapshot->fetch_error : null,
            ];
        }

        return Response::success(
            [
                'robots'        => $panels[Target::TYPE_ROBOTS],
                'sitemap'       => $panels[Target::TYPE_SITEMAP],
                'settings'      => $panels[Target::TYPE_SITE_SETTINGS],
                'bots'          => $this->botActivity(),
                'bot_tracking'  => (bool) Settings::get('bot_tracking'),
                'impaired'      => ImpairedState::details(),
            ]
        );
    }

    public function armBaseline()
    {
        $count = BaselineManager::arm();

        return Response::success(
            [
                'targets'  => $count,
                'baseline' => BaselineManager::details(),
            ]
        );
    }

    public function disarmBaseline()
    {
        BaselineManager::disarm();

        return Response::success(['baseline' => null]);
    }

    /**
     * Known AI crawlers with the date each was last seen, when tracking is on.
     */
    private function botActivity()
    {
        $visits = Db::rows(BotVisit::get());

        $seen = [];
        foreach ($visits as $visit) {
            $seen[$visit->bot_slug] = $visit;
        }

        $rows = [];
        foreach (AiBots::KNOWN as $slug => [$label, $userAgent]) {
            $visit  = $seen[$slug] ?? null;
            $rows[] = [
                'slug'       => $slug,
                'label'      => $label,
                'user_agent' => $userAgent,
                'last_seen'  => $visit ? $visit->last_seen_at : null,
                'hits'       => $visit ? (int) $visit->hits : 0,
            ];
        }

        return $rows;
    }

    private function recentEvents()
    {
        $events = Db::rows(SiteEvent::orderBy('id')->desc()->take(10)->get());

        return array_map(
            static function ($event) {
                $details = json_decode((string) $event->details, true) ?: [];

                return [
                    'id'      => (int) $event->id,
                    'type'    => $event->event_type,
                    'subject' => $details['name'] ?? $event->subject,
                    'at'      => $event->created_at,
                ];
            },
            $events
        );
    }
}
