<?php

namespace SEOChangeMonitor\HTTP\Middleware;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Deps\BitApps\WPKit\Utils\Capabilities;

class AdminCheckerMiddleware
{
    /**
     * The router treats any return value other than true as a short-circuit
     * response, so a passing check must return true.
     */
    public function handle()
    {
        if (!Capabilities::check('manage_options')) {
            return Response::error(
                __('You do not have permission to perform this action.', 'seo-change-monitor'),
                403
            );
        }

        return true;
    }
}
