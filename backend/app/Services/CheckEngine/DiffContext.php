<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

class DiffContext
{
    /** @var bool True when the site owner edited this post shortly before the change was detected. */
    public $expected = false;

    /** @var array Denormalized site events preceding the change: [{event_id, type, subject, at}]. */
    public $attributedEvents = [];

    public function __construct($expected = false, array $attributedEvents = [])
    {
        $this->expected         = $expected;
        $this->attributedEvents = $attributedEvents;
    }
}
