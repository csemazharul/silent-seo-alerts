<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\Snapshot;
use SEOChangeMonitor\Models\Target;
use SEOChangeMonitor\Services\Db;

/**
 * Fetches one target, extracts its fields, and stores the snapshot.
 * Raw compressed HTML is kept only on the newest snapshot per target
 * (baselines keep theirs) so storage stays bounded.
 */
class TargetChecker
{
    /** @var PageFetcher */
    private $fetcher;

    /** @var SnapshotExtractor */
    private $extractor;

    /** @var SiteChecker */
    private $siteChecker;

    public function __construct()
    {
        $this->fetcher     = new PageFetcher();
        $this->extractor   = new SnapshotExtractor();
        $this->siteChecker = new SiteChecker();
    }

    /**
     * @param Target   $target
     * @param null|int $runId
     * @param bool     $isBaseline
     *
     * @return Snapshot
     */
    public function snapshot($target, $runId = null, $isBaseline = false)
    {
        $isSystemTarget = \in_array($target->type, Target::SYSTEM_TYPES, true);

        if ($isSystemTarget) {
            $checked  = $this->siteChecker->check($target->type);
            $fields   = $checked['fields'];
            $status   = $checked['status'];
            $error    = $checked['error'];
            $redirect = null;
            $body     = '';
        } else {
            $result   = $this->fetcher->fetch($target->url);
            $fields   = $this->extractor->extract($result);
            $status   = $result->status;
            $error    = $result->error;
            $redirect = $result->redirectTarget;
            $body     = $result->body;
        }

        $snapshot = Snapshot::insert(
            [
                'target_id'       => $target->id,
                'check_run_id'    => $runId,
                'fields'          => wp_json_encode($fields),
                'content_hash'    => md5(wp_json_encode($fields)),
                'http_status'     => $status,
                'redirect_target' => $redirect,
                'fetch_error'     => $error !== null ? substr($error, 0, 191) : null,
                'html_gz'         => $body !== '' ? gzcompress($body, 6) : null,
                'is_baseline'     => $isBaseline ? 1 : 0,
            ]
        );

        if ($snapshot && !$isBaseline) {
            // Drop raw HTML from every older non-baseline snapshot of this target.
            Db::query(
                'UPDATE `' . Db::table('snapshots') . '` SET html_gz = NULL'
                . ' WHERE target_id = %d AND id != %d AND is_baseline = 0',
                [$target->id, $snapshot->id]
            );
        }

        Db::update(
            'targets',
            [
                'last_checked_at' => gmdate('Y-m-d H:i:s'),
                'last_result'     => $error !== null || (int) $status >= 400 ? 'error' : 'ok',
            ],
            ['id' => $target->id]
        );

        return $snapshot;
    }
}
