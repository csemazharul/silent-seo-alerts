<?php

namespace SEOChangeMonitor\Services\Events;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Models\SiteEvent;
use SEOChangeMonitor\Services\CheckEngine\DiffContext;
use SEOChangeMonitor\Services\Db;

/**
 * Ties detected changes to the site events that happened just before them.
 * Deliberately phrased as correlation, never causation.
 */
class Attribution
{
    /** Never look further back than this, however long ago the last run was. */
    private const MAX_LOOKBACK_HOURS = 48;

    private const MAX_EVENTS = 20;

    /** @var array|null cached per run */
    private $events;

    /**
     * Events recorded since the previous completed run started.
     *
     * @param int $currentRunId
     *
     * @return array raw SiteEvent models
     */
    public function eventsForRun($currentRunId)
    {
        if ($this->events !== null) {
            return $this->events;
        }

        $previous = CheckRun::where('id', '<', $currentRunId)
            ->whereNotNull('finished_at')
            ->orderBy('id')->desc()
            ->first();

        $cutoff = gmdate('Y-m-d H:i:s', time() - self::MAX_LOOKBACK_HOURS * HOUR_IN_SECONDS);
        if ($previous && $previous->started_at && $previous->started_at > $cutoff) {
            $cutoff = $previous->started_at;
        }

        $events = SiteEvent::where('created_at', '>=', $cutoff)
            ->orderBy('id')->desc()
            ->take(self::MAX_EVENTS)
            ->get();

        $this->events = Db::rows($events);

        return $this->events;
    }

    /**
     * @param object $target
     * @param int    $runId
     */
    public function context($target, $runId)
    {
        $events   = $this->eventsForRun($runId);
        $expected = false;
        $attached = [];

        foreach ($events as $event) {
            if ($event->event_type === EventRecorder::TYPE_POST_SAVED) {
                if ($target->post_id && (int) $event->subject === (int) $target->post_id) {
                    $expected = true;
                }

                continue; // the user's own edit is context, not a suspect
            }

            $details    = json_decode((string) $event->details, true) ?: [];
            $attached[] = [
                'event_id' => (int) $event->id,
                'type'     => $event->event_type,
                'subject'  => $details['name'] ?? $event->subject,
                'at'       => $event->created_at,
            ];
        }

        return new DiffContext($expected, $attached);
    }

    /** Forget the cached window (used between runs in a long-lived process). */
    public function reset()
    {
        $this->events = null;
    }
}
