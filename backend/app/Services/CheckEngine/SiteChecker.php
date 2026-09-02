<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\Target;

/**
 * The three site-wide checks: robots.txt, XML sitemap, and the site's own
 * indexing setting. All fetches are of this site's own URLs.
 */
class SiteChecker
{
    /** Guard against enormous sitemap indexes. */
    public const MAX_CHILD_SITEMAPS = 10;

    /** @var PageFetcher */
    private $fetcher;

    /** @var RobotsTxtParser */
    private $parser;

    public function __construct()
    {
        $this->fetcher = new PageFetcher();
        $this->parser  = new RobotsTxtParser();
    }

    /**
     * @return array{fields: array, error: string|null, status: int|null}
     */
    public function check($type)
    {
        return match ($type) {
            Target::TYPE_ROBOTS => $this->checkRobots(),
            Target::TYPE_SITEMAP => $this->checkSitemap(),
            Target::TYPE_SITE_SETTINGS => $this->checkSiteSettings(),
            default => ['fields' => [], 'error' => 'Unknown site check', 'status' => null],
        };
    }

    public function checkRobots()
    {
        $result = $this->fetcher->fetch(home_url('/robots.txt'));

        if ($result->isTransportError()) {
            return ['fields' => [], 'error' => $result->error, 'status' => null];
        }

        $raw    = $result->status === 200 ? $result->body : '';
        $groups = $this->parser->parse($raw);

        return [
            'fields' => [
                'raw'          => $raw,
                'reachable'    => $result->status === 200,
                'blocks_all'   => $this->parser->blocksAllCrawlers($groups),
                'ai_bots'      => $this->parser->aiBotVerdicts($groups),
                'sitemap_urls' => $this->sitemapUrlsFrom($raw),
            ],
            'error'  => null,
            'status' => $result->status,
        ];
    }

    public function checkSitemap()
    {
        $url = $this->locateSitemap();

        if (!$url) {
            return [
                'fields' => ['url' => null, 'reachable' => false, 'valid' => false, 'url_count' => 0],
                'error'  => null,
                'status' => null,
            ];
        }

        $result = $this->fetcher->fetch($url);

        if ($result->isTransportError()) {
            return ['fields' => [], 'error' => $result->error, 'status' => null];
        }

        $reachable = $result->status === 200 && $result->body !== '';
        $parsed    = $reachable ? $this->parseSitemap($result->body, 0) : ['valid' => false, 'count' => 0];

        return [
            'fields' => [
                'url'       => $url,
                'reachable' => $reachable,
                'valid'     => $parsed['valid'],
                'url_count' => $parsed['count'],
            ],
            'error'  => null,
            'status' => $result->status,
        ];
    }

    public function checkSiteSettings()
    {
        return [
            'fields' => [
                'blog_public'         => (bool) get_option('blog_public'),
                'permalink_structure' => (string) get_option('permalink_structure'),
            ],
            'error'  => null,
            'status' => 200,
        ];
    }

    /**
     * Diffs two site-check field sets into Changes.
     *
     * @return Change[]
     */
    public function diff($type, array $before, array $after)
    {
        return match ($type) {
            Target::TYPE_ROBOTS => $this->diffRobots($before, $after),
            Target::TYPE_SITEMAP => $this->diffSitemap($before, $after),
            Target::TYPE_SITE_SETTINGS => $this->diffSiteSettings($before, $after),
            default => [],
        };
    }

    private function diffRobots(array $before, array $after)
    {
        $changes = [];

        $wasBlocking = !empty($before['blocks_all']);
        $isBlocking  = !empty($after['blocks_all']);

        if ($isBlocking && !$wasBlocking) {
            $changes[] = new Change(ChangeTypes::ROBOTS_TXT_BLOCKS_ALL, $before['raw'] ?? '', $after['raw'] ?? '');
        }

        $beforeBots = (array) ($before['ai_bots'] ?? []);
        $afterBots  = (array) ($after['ai_bots'] ?? []);
        $flipped    = [];

        foreach ($afterBots as $slug => $verdict) {
            $was = $beforeBots[$slug] ?? null;
            if ($was !== null && $was !== $verdict) {
                $flipped[] = sprintf('%s (%s → %s)', AiBots::label($slug), $was, $verdict);
            }
        }

        if ($flipped !== []) {
            $changes[] = new Change(
                ChangeTypes::AI_BOT_RULE_CHANGED,
                $beforeBots,
                $afterBots,
                ['bots' => $flipped]
            );
        }

        // Plain content drift, reported once even if the specific rules above fired.
        if (($before['raw'] ?? null) !== ($after['raw'] ?? null) && !$isBlocking && $flipped === []) {
            $changes[] = new Change(ChangeTypes::ROBOTS_TXT_CHANGED, $before['raw'] ?? '', $after['raw'] ?? '');
        }

        return $changes;
    }

    private function diffSitemap(array $before, array $after)
    {
        $changes = [];

        $wasReachable = !empty($before['reachable']);
        $isReachable  = !empty($after['reachable']);

        if ($wasReachable && !$isReachable) {
            $changes[] = new Change(ChangeTypes::SITEMAP_UNREACHABLE, $before['url'] ?? null, $after['url'] ?? null);

            return $changes;
        }

        if ($isReachable && !empty($before['valid']) && empty($after['valid'])) {
            $changes[] = new Change(ChangeTypes::SITEMAP_INVALID, true, false);

            return $changes;
        }

        $oldCount = (int) ($before['url_count'] ?? 0);
        $newCount = (int) ($after['url_count'] ?? 0);

        if ($oldCount > 5 && $newCount < $oldCount) {
            $ratio = ($oldCount - $newCount) / $oldCount;
            if ($ratio > 0.2) {
                $changes[] = new Change(
                    ChangeTypes::SITEMAP_COUNT_DROP,
                    $oldCount,
                    $newCount,
                    ['ratio' => round($ratio, 2)]
                );
            }
        }

        return $changes;
    }

    private function diffSiteSettings(array $before, array $after)
    {
        $changes = [];

        $wasPublic = !empty($before['blog_public']);
        $isPublic  = !empty($after['blog_public']);

        if ($wasPublic && !$isPublic) {
            $changes[] = new Change(ChangeTypes::BLOG_PUBLIC_DISABLED, true, false);
        }

        return $changes;
    }

    /**
     * robots.txt first (it is the canonical pointer), then the usual locations.
     */
    private function locateSitemap()
    {
        $robots = $this->fetcher->fetch(home_url('/robots.txt'));
        if (!$robots->isTransportError() && $robots->status === 200) {
            $fromRobots = $this->sitemapUrlsFrom($robots->body);
            if ($fromRobots !== []) {
                return $fromRobots[0];
            }
        }

        foreach (['/wp-sitemap.xml', '/sitemap_index.xml', '/sitemap.xml'] as $path) {
            $candidate = $this->fetcher->fetch(home_url($path));
            if (!$candidate->isTransportError() && $candidate->status === 200 && $candidate->body !== '') {
                return home_url($path);
            }
        }

        return null;
    }

    private function sitemapUrlsFrom($raw)
    {
        preg_match_all('/^\s*sitemap:\s*(\S+)/im', (string) $raw, $matches);

        return isset($matches[1]) ? array_values(array_filter($matches[1])) : [];
    }

    /**
     * @return array{valid: bool, count: int}
     */
    private function parseSitemap($body, $depth)
    {
        $previous = libxml_use_internal_errors(true);
        $xml      = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return ['valid' => false, 'count' => 0];
        }

        $name = $xml->getName();

        if ($name === 'urlset') {
            return ['valid' => true, 'count' => \count($xml->url)];
        }

        if ($name !== 'sitemapindex' || $depth > 0) {
            return ['valid' => $name === 'sitemapindex', 'count' => 0];
        }

        $count   = 0;
        $fetched = 0;
        foreach ($xml->sitemap as $child) {
            if ($fetched >= self::MAX_CHILD_SITEMAPS) {
                break;
            }

            $childUrl = (string) $child->loc;
            if ($childUrl === '') {
                continue;
            }

            $childResult = $this->fetcher->fetch($childUrl);
            ++$fetched;

            if (!$childResult->isTransportError() && $childResult->status === 200) {
                $parsed = $this->parseSitemap($childResult->body, $depth + 1);
                $count += $parsed['count'];
            }
        }

        return ['valid' => true, 'count' => $count];
    }
}
