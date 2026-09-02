<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * The AI crawlers worth telling a site owner about.
 *
 * slug => [label, user-agent token used in robots.txt and request headers]
 */
class AiBots
{
    public const KNOWN = [
        'gptbot'              => ['OpenAI GPTBot', 'GPTBot'],
        'oai-searchbot'       => ['OpenAI SearchBot', 'OAI-SearchBot'],
        'chatgpt-user'        => ['ChatGPT (user browsing)', 'ChatGPT-User'],
        'claudebot'           => ['ClaudeBot', 'ClaudeBot'],
        'claude-web'          => ['Claude-Web', 'Claude-Web'],
        'anthropic-ai'        => ['anthropic-ai', 'anthropic-ai'],
        'perplexitybot'       => ['PerplexityBot', 'PerplexityBot'],
        'perplexity-user'     => ['Perplexity (user browsing)', 'Perplexity-User'],
        'google-extended'     => ['Google Gemini (Google-Extended)', 'Google-Extended'],
        'ccbot'               => ['Common Crawl (CCBot)', 'CCBot'],
        'bytespider'          => ['ByteDance Bytespider', 'Bytespider'],
        'amazonbot'           => ['Amazonbot', 'Amazonbot'],
        'applebot-extended'   => ['Applebot-Extended', 'Applebot-Extended'],
        'meta-externalagent'  => ['Meta AI crawler', 'Meta-ExternalAgent'],
    ];

    public static function label($slug)
    {
        return isset(self::KNOWN[$slug]) ? self::KNOWN[$slug][0] : $slug;
    }

    public static function userAgent($slug)
    {
        return isset(self::KNOWN[$slug]) ? self::KNOWN[$slug][1] : $slug;
    }

    /**
     * Which known AI bot, if any, sent this request.
     *
     * @return string|null slug
     */
    public static function matchUserAgent($userAgent)
    {
        if (!\is_string($userAgent) || $userAgent === '') {
            return null;
        }

        foreach (self::KNOWN as $slug => [$label, $token]) {
            if (stripos($userAgent, $token) !== false) {
                return $slug;
            }
        }

        return null;
    }
}
