<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Models\Finding;

/**
 * Single source of truth for every change type the plugin can detect.
 * Keys double as explanation-template keys.
 */
class ChangeTypes
{
    // Page-level
    public const TITLE_CHANGED            = 'title_changed';

    public const TITLE_REMOVED            = 'title_removed';

    public const META_DESCRIPTION_CHANGED = 'meta_description_changed';

    public const META_DESCRIPTION_REMOVED = 'meta_description_removed';

    public const META_ROBOTS_CHANGED      = 'meta_robots_changed';

    public const NOINDEX_ADDED            = 'noindex_added';

    public const NOINDEX_REMOVED          = 'noindex_removed';

    public const NOFOLLOW_ADDED           = 'nofollow_added';

    public const CANONICAL_CHANGED        = 'canonical_changed';

    public const CANONICAL_REMOVED        = 'canonical_removed';

    public const CANONICAL_OFFSITE        = 'canonical_offsite';

    public const SCHEMA_TYPE_REMOVED      = 'schema_type_removed';

    public const SCHEMA_TYPE_ADDED        = 'schema_type_added';

    public const SCHEMA_INVALID_JSON      = 'schema_invalid_json';

    public const OG_CHANGED               = 'og_changed';

    public const TWITTER_CHANGED          = 'twitter_changed';

    public const H1_CHANGED               = 'h1_changed';

    public const H1_COUNT_CHANGED         = 'h1_count_changed';

    public const H1_REMOVED               = 'h1_removed';

    public const HTTP_STATUS_ERROR        = 'http_status_error';

    public const REDIRECT_ADDED           = 'redirect_added';

    public const REDIRECT_OFFSITE         = 'redirect_offsite';

    public const WORD_COUNT_DROP_MAJOR    = 'word_count_drop_major';

    public const WORD_COUNT_DROP          = 'word_count_drop';

    // Site-level
    public const ROBOTS_TXT_CHANGED       = 'robots_txt_changed';

    public const ROBOTS_TXT_BLOCKS_ALL    = 'robots_txt_blocks_all';

    public const AI_BOT_RULE_CHANGED      = 'ai_bot_rule_changed';

    public const SITEMAP_UNREACHABLE      = 'sitemap_unreachable';

    public const SITEMAP_INVALID          = 'sitemap_invalid';

    public const SITEMAP_COUNT_DROP       = 'sitemap_count_drop';

    public const BLOG_PUBLIC_DISABLED     = 'blog_public_disabled';

    public const AI_BOT_GONE_SILENT       = 'ai_bot_gone_silent';

    // Global
    public const MONITORING_IMPAIRED      = 'monitoring_impaired';

    /**
     * Base severity per change type.
     */
    public const SEVERITY = [
        self::TITLE_CHANGED            => Finding::SEVERITY_WARNING,
        self::TITLE_REMOVED            => Finding::SEVERITY_CRITICAL,
        self::META_DESCRIPTION_CHANGED => Finding::SEVERITY_WARNING,
        self::META_DESCRIPTION_REMOVED => Finding::SEVERITY_CRITICAL,
        self::META_ROBOTS_CHANGED      => Finding::SEVERITY_WARNING,
        self::NOINDEX_ADDED            => Finding::SEVERITY_CRITICAL,
        self::NOINDEX_REMOVED          => Finding::SEVERITY_INFO,
        self::NOFOLLOW_ADDED           => Finding::SEVERITY_WARNING,
        self::CANONICAL_CHANGED        => Finding::SEVERITY_WARNING,
        self::CANONICAL_REMOVED        => Finding::SEVERITY_CRITICAL,
        self::CANONICAL_OFFSITE        => Finding::SEVERITY_CRITICAL,
        self::SCHEMA_TYPE_REMOVED      => Finding::SEVERITY_CRITICAL,
        self::SCHEMA_TYPE_ADDED        => Finding::SEVERITY_INFO,
        self::SCHEMA_INVALID_JSON      => Finding::SEVERITY_WARNING,
        self::OG_CHANGED               => Finding::SEVERITY_WARNING,
        self::TWITTER_CHANGED          => Finding::SEVERITY_INFO,
        self::H1_CHANGED               => Finding::SEVERITY_WARNING,
        self::H1_COUNT_CHANGED         => Finding::SEVERITY_WARNING,
        self::H1_REMOVED               => Finding::SEVERITY_WARNING,
        self::HTTP_STATUS_ERROR        => Finding::SEVERITY_CRITICAL,
        self::REDIRECT_ADDED           => Finding::SEVERITY_WARNING,
        self::REDIRECT_OFFSITE         => Finding::SEVERITY_CRITICAL,
        self::WORD_COUNT_DROP_MAJOR    => Finding::SEVERITY_CRITICAL,
        self::WORD_COUNT_DROP          => Finding::SEVERITY_WARNING,
        self::ROBOTS_TXT_CHANGED       => Finding::SEVERITY_WARNING,
        self::ROBOTS_TXT_BLOCKS_ALL    => Finding::SEVERITY_CRITICAL,
        self::AI_BOT_RULE_CHANGED      => Finding::SEVERITY_WARNING,
        self::SITEMAP_UNREACHABLE      => Finding::SEVERITY_CRITICAL,
        self::SITEMAP_INVALID          => Finding::SEVERITY_CRITICAL,
        self::SITEMAP_COUNT_DROP       => Finding::SEVERITY_WARNING,
        self::BLOG_PUBLIC_DISABLED     => Finding::SEVERITY_CRITICAL,
        self::AI_BOT_GONE_SILENT       => Finding::SEVERITY_WARNING,
        self::MONITORING_IMPAIRED      => Finding::SEVERITY_WARNING,
    ];

    /**
     * Types that may be downgraded to Info when the site owner edited the
     * page themselves. Indexability-affecting types are deliberately absent:
     * they stay at base severity no matter who caused them.
     */
    public const EXPECTED_DOWNGRADABLE = [
        self::TITLE_CHANGED,
        self::META_DESCRIPTION_CHANGED,
        self::META_DESCRIPTION_REMOVED,
        self::META_ROBOTS_CHANGED,
        self::OG_CHANGED,
        self::TWITTER_CHANGED,
        self::H1_CHANGED,
        self::H1_COUNT_CHANGED,
        self::H1_REMOVED,
        self::WORD_COUNT_DROP,
        self::WORD_COUNT_DROP_MAJOR,
        self::SCHEMA_TYPE_ADDED,
    ];

    public static function baseSeverity($type)
    {
        return self::SEVERITY[$type] ?? Finding::SEVERITY_INFO;
    }
}
