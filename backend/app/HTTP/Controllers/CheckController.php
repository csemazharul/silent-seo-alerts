<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Models\CheckRun;
use SEOChangeMonitor\Services\CheckEngine\CheckRunner;

class CheckController
{
    public function checkNow()
    {
        $runner = new CheckRunner();
        $run    = $runner->runSync('manual');

        if (!$run) {
            return Response::error(__('Could not start the check.', 'seo-change-monitor'));
        }

        return Response::success($run);
    }

    public function runStatus()
    {
        $run = CheckRun::orderBy('id')->desc()->first();

        return Response::success($run ?: null);
    }
}
