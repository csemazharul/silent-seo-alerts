<?php

namespace SEOChangeMonitor\HTTP\Middleware;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Request\Request;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;

class NonceCheckerMiddleware
{
    /**
     * The router treats any return value other than true as a short-circuit
     * response, so a passing check must return true.
     */
    public function handle(Request $request)
    {
        if (!wp_verify_nonce($request->get('_ajax_nonce'), Config::withPrefix('nonce'))) {
            return Response::error(__('Nonce verification failed.', 'silent-seo-alerts'), 403);
        }

        return true;
    }
}
