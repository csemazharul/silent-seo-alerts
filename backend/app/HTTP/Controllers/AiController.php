<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Request\Request;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Models\Finding;
use SEOChangeMonitor\Services\Ai\AiException;
use SEOChangeMonitor\Services\Ai\AiExplainer;
use SEOChangeMonitor\Services\Settings;

class AiController
{
    /**
     * On-demand only: nothing here ever runs from cron.
     */
    public function explain(Request $request)
    {
        $finding = Finding::findOne(['id' => (int) $request->get('id')]);
        if (!$finding) {
            return Response::error(__('Finding not found.', 'seo-change-monitor'));
        }

        try {
            $result = (new AiExplainer())->explain($finding, (bool) $request->get('refresh'));
        } catch (AiException $e) {
            // The template explanation is already on screen; say why the extra
            // interpretation is missing and leave monitoring untouched.
            return Response::error($e->getMessage());
        }

        return Response::success($result + ['usage' => AiExplainer::usage()]);
    }

    /** Monthly call count and estimated spend, for the settings screen. */
    public function usage()
    {
        return Response::success(
            [
                'usage'     => AiExplainer::usage(),
                'cap'       => (float) Settings::get('ai_monthly_cap'),
                'available' => (new AiExplainer())->isConfigured(),
            ]
        );
    }
}
