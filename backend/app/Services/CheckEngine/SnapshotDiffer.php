<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compares two extracted field arrays for a page target and emits Changes.
 * Site-wide targets (robots/sitemap/site settings) have their own comparators
 * in SiteChecker.
 */
class SnapshotDiffer
{
    /**
     * @param array $before Fields of the older snapshot
     * @param array $after  Fields of the newer snapshot
     *
     * @return Change[]
     */
    public function diff(array $before, array $after)
    {
        $changes = [];

        $this->diffText($changes, $before, $after, 'title', ChangeTypes::TITLE_CHANGED, ChangeTypes::TITLE_REMOVED);
        $this->diffText($changes, $before, $after, 'meta_description', ChangeTypes::META_DESCRIPTION_CHANGED, ChangeTypes::META_DESCRIPTION_REMOVED);
        $this->diffRobots($changes, $before, $after);
        $this->diffCanonical($changes, $before, $after);
        $this->diffSchema($changes, $before, $after);
        $this->diffMap($changes, $before, $after, 'og', ChangeTypes::OG_CHANGED);
        $this->diffMap($changes, $before, $after, 'twitter', ChangeTypes::TWITTER_CHANGED);
        $this->diffH1($changes, $before, $after);
        $this->diffHttp($changes, $before, $after);
        $this->diffWordCount($changes, $before, $after);

        return $changes;
    }

    private function diffText(array &$changes, $before, $after, $key, $changedType, $removedType)
    {
        $old = $before[$key] ?? null;
        $new = $after[$key] ?? null;

        if ($old === $new) {
            return;
        }

        if (($old !== null && $old !== '') && ($new === null || $new === '')) {
            $changes[] = new Change($removedType, $old, $new);
        } else {
            $changes[] = new Change($changedType, $old, $new);
        }
    }

    private function diffRobots(array &$changes, $before, $after)
    {
        $old = (string) ($before['meta_robots'] ?? '');
        $new = (string) ($after['meta_robots'] ?? '');

        if ($old === $new) {
            return;
        }

        $oldTokens = $this->robotsTokens($old);
        $newTokens = $this->robotsTokens($new);

        $emitted = false;

        if (\in_array('noindex', $newTokens, true) && !\in_array('noindex', $oldTokens, true)) {
            $changes[] = new Change(ChangeTypes::NOINDEX_ADDED, $old ?: null, $new);
            $emitted   = true;
        }

        if (\in_array('noindex', $oldTokens, true) && !\in_array('noindex', $newTokens, true)) {
            $changes[] = new Change(ChangeTypes::NOINDEX_REMOVED, $old, $new ?: null);
            $emitted   = true;
        }

        if (\in_array('nofollow', $newTokens, true) && !\in_array('nofollow', $oldTokens, true)) {
            $changes[] = new Change(ChangeTypes::NOFOLLOW_ADDED, $old ?: null, $new);
            $emitted   = true;
        }

        if (!$emitted) {
            $changes[] = new Change(ChangeTypes::META_ROBOTS_CHANGED, $old ?: null, $new ?: null);
        }
    }

    private function robotsTokens($value)
    {
        return array_filter(array_map(trim(...), explode(',', strtolower($value))));
    }

    private function diffCanonical(array &$changes, $before, $after)
    {
        $old = $before['canonical'] ?? null;
        $new = $after['canonical'] ?? null;

        if ($old === $new) {
            return;
        }

        if (($old !== null && $old !== '') && ($new === null || $new === '')) {
            $changes[] = new Change(ChangeTypes::CANONICAL_REMOVED, $old, $new);

            return;
        }

        if ($new !== null && $new !== '' && $this->isOffDomain($new)) {
            $changes[] = new Change(ChangeTypes::CANONICAL_OFFSITE, $old, $new);

            return;
        }

        $changes[] = new Change(ChangeTypes::CANONICAL_CHANGED, $old, $new);
    }

    private function diffSchema(array &$changes, $before, $after)
    {
        $old = (array) ($before['schema_types'] ?? []);
        $new = (array) ($after['schema_types'] ?? []);

        $invalid = '(invalid JSON-LD)';
        if (\in_array($invalid, $new, true) && !\in_array($invalid, $old, true)) {
            $changes[] = new Change(ChangeTypes::SCHEMA_INVALID_JSON, $old, $new);
        }

        $removed = array_values(array_diff($old, $new, [$invalid]));
        $added   = array_values(array_diff($new, $old, [$invalid]));

        if ($removed !== []) {
            $changes[] = new Change(ChangeTypes::SCHEMA_TYPE_REMOVED, $old, $new, ['removed' => $removed]);
        }

        if ($added !== []) {
            $changes[] = new Change(ChangeTypes::SCHEMA_TYPE_ADDED, $old, $new, ['added' => $added]);
        }
    }

    private function diffMap(array &$changes, $before, $after, $key, $type)
    {
        $old = (array) ($before[$key] ?? []);
        $new = (array) ($after[$key] ?? []);

        if ($old == $new) {
            return;
        }

        $changed = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $tag) {
            $ov = $old[$tag] ?? null;
            $nv = $new[$tag] ?? null;
            if ($ov !== $nv) {
                $changed[] = $tag;
            }
        }

        $changes[] = new Change($type, $old, $new, ['tags' => $changed]);
    }

    private function diffH1(array &$changes, $before, $after)
    {
        $oldCount = (int) ($before['h1_count'] ?? 0);
        $newCount = (int) ($after['h1_count'] ?? 0);
        $oldFirst = $before['h1_first'] ?? null;
        $newFirst = $after['h1_first'] ?? null;

        if ($oldCount > 0 && $newCount === 0) {
            $changes[] = new Change(ChangeTypes::H1_REMOVED, $oldFirst, null, ['before_count' => $oldCount]);

            return;
        }

        if ($oldCount !== $newCount) {
            $changes[] = new Change(ChangeTypes::H1_COUNT_CHANGED, $oldCount, $newCount);
        }

        if ($oldFirst !== $newFirst && $newCount > 0 && $oldCount > 0) {
            $changes[] = new Change(ChangeTypes::H1_CHANGED, $oldFirst, $newFirst);
        }
    }

    private function diffHttp(array &$changes, $before, $after)
    {
        $oldStatus = (int) ($before['http_status'] ?? 0);
        $newStatus = (int) ($after['http_status'] ?? 0);

        if ($newStatus >= 400 && $oldStatus < 400) {
            $changes[] = new Change(ChangeTypes::HTTP_STATUS_ERROR, $oldStatus, $newStatus);
        }

        $oldRedirect = $before['redirect_target'] ?? null;
        $newRedirect = $after['redirect_target'] ?? null;

        if ($newRedirect && $newRedirect !== $oldRedirect) {
            $type      = $this->isOffDomain($newRedirect) ? ChangeTypes::REDIRECT_OFFSITE : ChangeTypes::REDIRECT_ADDED;
            $changes[] = new Change($type, $oldRedirect, $newRedirect);
        }
    }

    private function diffWordCount(array &$changes, $before, $after)
    {
        $old = (int) ($before['word_count'] ?? 0);
        $new = (int) ($after['word_count'] ?? 0);

        if ($old < 50 || $new >= $old) {
            return; // tiny pages and growth are not alertable
        }

        $ratio = ($old - $new) / $old;

        if ($ratio > 0.4) {
            $changes[] = new Change(ChangeTypes::WORD_COUNT_DROP_MAJOR, $old, $new, ['ratio' => round($ratio, 2)]);
        } elseif ($ratio > 0.2) {
            $changes[] = new Change(ChangeTypes::WORD_COUNT_DROP, $old, $new, ['ratio' => round($ratio, 2)]);
        }
    }

    private function isOffDomain($url)
    {
        $homeHost = wp_parse_url(home_url(), PHP_URL_HOST);
        $urlHost  = wp_parse_url($url, PHP_URL_HOST);

        // Relative URLs stay on-domain.
        return $urlHost !== null && strcasecmp((string) $urlHost, (string) $homeHost) !== 0;
    }
}
