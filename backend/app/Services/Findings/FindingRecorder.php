<?php

namespace SEOChangeMonitor\Services\Findings;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\CheckEngine\Change;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\CheckEngine\ChangeTypes;
use SEOChangeMonitor\Services\CheckEngine\DiffContext;

class FindingRecorder
{
    /**
     * Records a finding, refreshing an existing open finding of the same
     * (target, change_type) instead of duplicating it.
     *
     * @param null|int $runId
     * @param null|int $targetId
     *
     * @return array [Finding|false, bool $isNew]
     */
    public function record($runId, $targetId, Change $change, $severity, DiffContext $context)
    {
        $existing = Finding::where('target_id', $targetId)
            ->where('change_type', $change->type)
            ->where('status', Finding::STATUS_OPEN)
            ->first();

        $payload = [
            'check_run_id' => $runId,
            'severity'     => $severity,
            'after_value'  => wp_json_encode(['value' => $change->after, 'context' => $change->context]),
            'is_expected'  => $context->expected ? 1 : 0,
            // Refreshed with the values so the finding always describes one
            // coherent occurrence rather than new values beside stale causes.
            'attributed_events' => wp_json_encode($context->attributedEvents),
        ];

        if ($existing) {
            Db::update('findings', $payload, ['id' => $existing->id]);

            return [Finding::findOne(['id' => $existing->id]), false];
        }

        $payload['target_id']    = $targetId;
        $payload['change_type']  = $change->type;
        $payload['status']       = Finding::STATUS_OPEN;
        $payload['before_value'] = wp_json_encode(['value' => $change->before]);

        return [Finding::insert($payload), true];
    }

    /**
     * Auto-resolves open critical findings for a target whose bad condition no
     * longer holds in the latest snapshot state. Judged against state, not the
     * diff: an ongoing problem produces no new diff on later runs, and must
     * stay open until the condition actually clears.
     *
     * @param null|int $targetId
     * @param array    $currentFields the latest snapshot's extracted fields
     *
     * @return Finding[] the findings that were just auto-resolved
     */
    public function autoResolve($targetId, array $currentFields)
    {
        $open = Finding::where('target_id', $targetId)
            ->where('status', Finding::STATUS_OPEN)
            ->where('severity', Finding::SEVERITY_CRITICAL)
            ->get();

        if (!$open) {
            return [];
        }

        $resolved = [];
        foreach ($open as $finding) {
            if ($this->stillActive($finding, $currentFields)) {
                continue;
            }

            Db::update(
                'findings',
                [
                    'status'      => Finding::STATUS_AUTO_RESOLVED,
                    'resolved_at' => gmdate('Y-m-d H:i:s'),
                ],
                ['id' => $finding->id]
            );

            $resolved[] = Finding::findOne(['id' => $finding->id]);
        }

        return $resolved;
    }

    /**
     * Does the bad condition behind a critical finding still hold?
     * Unknown types stay active (never silently resolve what we can't verify).
     */
    private function stillActive($finding, array $fields)
    {
        $robots    = isset($fields['meta_robots']) ? (string) $fields['meta_robots'] : '';
        $canonical = isset($fields['canonical']) ? (string) $fields['canonical'] : '';

        switch ($finding->change_type) {
            case ChangeTypes::TITLE_REMOVED:
                return empty($fields['title']);

            case ChangeTypes::META_DESCRIPTION_REMOVED:
                return empty($fields['meta_description']);

            case ChangeTypes::NOINDEX_ADDED:
                return strpos($robots, 'noindex') !== false;

            case ChangeTypes::CANONICAL_REMOVED:
                return $canonical === '';

            case ChangeTypes::CANONICAL_OFFSITE:
                return $canonical !== '' && $this->isOffDomain($canonical);

            case ChangeTypes::SCHEMA_TYPE_REMOVED:
                $after   = json_decode((string) $finding->after_value, true);
                $removed = isset($after['context']['removed']) ? (array) $after['context']['removed'] : [];
                $current = isset($fields['schema_types']) ? (array) $fields['schema_types'] : [];

                return array_diff($removed, $current) !== [];

            case ChangeTypes::HTTP_STATUS_ERROR:
                return isset($fields['http_status']) && (int) $fields['http_status'] >= 400;

            case ChangeTypes::REDIRECT_OFFSITE:
                return !empty($fields['redirect_target']) && $this->isOffDomain($fields['redirect_target']);

            case ChangeTypes::WORD_COUNT_DROP_MAJOR:
                $before = json_decode((string) $finding->before_value, true);
                $old    = isset($before['value']) ? (int) $before['value'] : 0;
                $now    = isset($fields['word_count']) ? (int) $fields['word_count'] : 0;

                // Cleared once the page recovers to within 20% of its old length.
                return $old > 0 && $now < $old * 0.8;

            // Site-wide conditions (fields come from SiteChecker).
            case ChangeTypes::ROBOTS_TXT_BLOCKS_ALL:
                return !empty($fields['blocks_all']);

            case ChangeTypes::SITEMAP_UNREACHABLE:
                return empty($fields['reachable']);

            case ChangeTypes::SITEMAP_INVALID:
                return !empty($fields['reachable']) && empty($fields['valid']);

            case ChangeTypes::BLOG_PUBLIC_DISABLED:
                return isset($fields['blog_public']) && !$fields['blog_public'];

            default:
                return true;
        }
    }

    private function isOffDomain($url)
    {
        $homeHost = wp_parse_url(home_url(), PHP_URL_HOST);
        $urlHost  = wp_parse_url($url, PHP_URL_HOST);

        return $urlHost !== null && strcasecmp((string) $urlHost, (string) $homeHost) !== 0;
    }
}
