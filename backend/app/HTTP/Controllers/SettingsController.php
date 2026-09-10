<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Request\Request;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Services\Settings;

class SettingsController
{
    public function get()
    {
        return Response::success($this->withoutSecrets(Settings::all()));
    }

    public function update(Request $request)
    {
        $partial = [];

        if ($request->has('frequency')) {
            $frequency = (string) $request->get('frequency');
            if (!\in_array($frequency, Settings::FREQUENCIES, true)) {
                return Response::error(__('Invalid check frequency.', 'silent-seo-alerts'));
            }

            $partial['frequency'] = $frequency;
        }

        if ($request->has('email_threshold')) {
            $threshold = (string) $request->get('email_threshold');
            if (!\in_array($threshold, Settings::THRESHOLDS, true)) {
                return Response::error(__('Invalid email threshold.', 'silent-seo-alerts'));
            }

            $partial['email_threshold'] = $threshold;
        }

        if ($request->has('email_enabled')) {
            $partial['email_enabled'] = (bool) $request->get('email_enabled');
        }

        if ($request->has('email_recipient')) {
            $recipient = sanitize_email((string) $request->get('email_recipient'));
            if ($recipient !== '' && !is_email($recipient)) {
                return Response::error(__('Invalid email address.', 'silent-seo-alerts'));
            }

            $partial['email_recipient'] = $recipient;
        }

        if ($request->has('retention_days')) {
            $days = (int) $request->get('retention_days');
            if ($days < 0 || $days > 3650) {
                return Response::error(__('Retention must be between 0 (keep forever) and 3650 days.', 'silent-seo-alerts'));
            }

            $partial['retention_days'] = $days;
        }

        if ($request->has('bot_tracking')) {
            $partial['bot_tracking'] = (bool) $request->get('bot_tracking');
        }

        /*
         * Pro's own keys - webhooks, Slack, AI and the client report - are
         * validated by whatever registers this filter. Returning a WP_Error
         * lets an add-on reject a value with its own message.
         */
        $partial = apply_filters(Config::withPrefix('settings_update_partial'), $partial, $request);

        if (is_wp_error($partial)) {
            return Response::error($partial->get_error_message());
        }

        $settings = Settings::update($partial);

        do_action(Config::withPrefix('settings_updated'), $settings);

        return Response::success($this->withoutSecrets($settings));
    }

    /**
     * Secrets never travel back to the browser. Add-ons strip their own through
     * this filter - pro replaces its API key with a "one is set" flag.
     */
    private function withoutSecrets(array $settings)
    {
        return apply_filters(Config::withPrefix('settings_for_client'), $settings);
    }
}
