<?php

namespace SEOChangeMonitor\Models;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Model;

class Finding extends Model
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_AUTO_RESOLVED = 'auto_resolved';

    public const STATUS_MUTED = 'muted';

    protected $prefix = Config::VAR_PREFIX;

    protected $casts = ['id' => 'int', 'target_id' => 'int', 'check_run_id' => 'int', 'is_expected' => 'int', 'resolved_by' => 'int'];

    protected $fillable = [
        'target_id', 'check_run_id', 'change_type', 'severity', 'status',
        'before_value', 'after_value', 'attributed_events', 'is_expected',
        'note', 'resolved_at', 'resolved_by', 'meta',
    ];
}
