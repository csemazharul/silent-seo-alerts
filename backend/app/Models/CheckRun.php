<?php

namespace SEOChangeMonitor\Models;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Model;

class CheckRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_IMPAIRED = 'impaired';

    public const STATUS_FAILED = 'failed';

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = [
        'id' => 'int', 'targets_total' => 'int', 'targets_done' => 'int',
        'critical_count' => 'int', 'warning_count' => 'int', 'info_count' => 'int',
        'email_sent' => 'int',
    ];

    protected $fillable = [
        'trigger_type', 'status', 'targets_total', 'targets_done',
        'critical_count', 'warning_count', 'info_count', 'email_sent',
        'started_at', 'finished_at',
    ];
}
