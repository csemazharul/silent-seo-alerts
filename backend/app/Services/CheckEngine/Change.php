<?php

namespace SEOChangeMonitor\Services\CheckEngine;

if (!\defined('ABSPATH')) {
    exit;
}

class Change
{
    /** @var string One of the ChangeTypes constants. */
    public $type;

    /** @var mixed */
    public $before;

    /** @var mixed */
    public $after;

    /** @var array Extra context for explanations (e.g. drop ratio, affected schema types). */
    public $context = [];

    public function __construct($type, $before = null, $after = null, array $context = [])
    {
        $this->type    = $type;
        $this->before  = $before;
        $this->after   = $after;
        $this->context = $context;
    }
}
