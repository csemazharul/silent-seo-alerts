<?php

namespace SEOChangeMonitor\Models;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Model;

class Target extends Model
{
    public const TYPE_PAGE = 'page';

    public const TYPE_ROBOTS = 'robots';

    public const TYPE_SITEMAP = 'sitemap';

    public const TYPE_SITE_SETTINGS = 'site_settings';

    public const SYSTEM_TYPES = [self::TYPE_ROBOTS, self::TYPE_SITEMAP, self::TYPE_SITE_SETTINGS];

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = ['id' => 'int', 'post_id' => 'int', 'is_active' => 'int'];

    protected $fillable = ['type', 'post_id', 'url', 'label', 'is_active', 'last_checked_at', 'last_result'];
}
