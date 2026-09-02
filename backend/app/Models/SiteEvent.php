<?php

namespace SEOChangeMonitor\Models;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Model;

class SiteEvent extends Model
{
    protected $table = 'events';

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = ['id' => 'int'];

    protected $fillable = ['event_type', 'subject', 'details'];
}
