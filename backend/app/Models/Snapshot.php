<?php

namespace SEOChangeMonitor\Models;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Model;

class Snapshot extends Model
{
    protected $prefix = Config::VAR_PREFIX;

    protected $casts = ['id' => 'int', 'target_id' => 'int', 'check_run_id' => 'int', 'http_status' => 'int', 'is_baseline' => 'int'];

    protected $fillable = [
        'target_id', 'check_run_id', 'fields', 'content_hash', 'http_status',
        'redirect_target', 'fetch_error', 'html_gz', 'is_baseline',
    ];
}
