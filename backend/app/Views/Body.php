<?php

namespace SEOChangeMonitor\Views;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;

class Body
{
    public function render()
    {
        echo '<div id="seo-change-monitor-root"></div>';
    }
}
