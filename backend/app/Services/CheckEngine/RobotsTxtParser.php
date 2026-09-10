<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal robots.txt reader: enough to answer "is this crawler allowed?".
 */
class RobotsTxtParser
{
    /**
     * @return array user-agent (lowercased) => ['allow' => string[], 'disallow' => string[]]
     */
    public function parse($raw)
    {
        $groups  = [];
        $current = [];

        // Consecutive User-agent lines share one group; the first User-agent
        // *after* a rule starts a new group.
        $sawRule = false;

        foreach (preg_split('/\R/', (string) $raw) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field           = strtolower($field);

            if ($field === 'user-agent') {
                if ($sawRule) {
                    $current = [];
                    $sawRule = false;
                }

                $agent = strtolower($value);
                $groups[$agent] ??= ['allow' => [], 'disallow' => []];

                $current[] = $agent;

                continue;
            }

            if (($field === 'disallow' || $field === 'allow') && $current !== []) {
                foreach ($current as $agent) {
                    $groups[$agent][$field][] = $value;
                }

                $sawRule = true;

                continue;
            }

            // Any other directive (Sitemap, Crawl-delay…) ends the group.
            $current = [];
            $sawRule = false;
        }

        return $groups;
    }

    /**
     * Does this file tell every crawler to stay away from the whole site?
     */
    public function blocksAllCrawlers(array $groups)
    {
        if (!isset($groups['*'])) {
            return false;
        }

        foreach ($groups['*']['disallow'] as $path) {
            if ($path === '/') {
                // An explicit Allow can carve the site back open.
                return !\in_array('/', $groups['*']['allow'], true);
            }
        }

        return false;
    }

    /**
     * Verdict per known AI bot: blocked | allowed | unspecified.
     */
    public function aiBotVerdicts(array $groups)
    {
        $verdicts = [];

        foreach (array_keys(AiBots::KNOWN) as $slug) {
            $agent = strtolower(AiBots::userAgent($slug));

            if (isset($groups[$agent])) {
                $verdicts[$slug] = $this->isBlocked($groups[$agent]) ? 'blocked' : 'allowed';

                continue;
            }

            if (isset($groups['*']) && $this->isBlocked($groups['*'])) {
                $verdicts[$slug] = 'blocked';

                continue;
            }

            $verdicts[$slug] = 'unspecified';
        }

        return $verdicts;
    }

    private function isBlocked(array $group)
    {
        foreach ($group['disallow'] as $path) {
            if ($path === '/') {
                return !\in_array('/', $group['allow'], true);
            }
        }

        return false;
    }
}
