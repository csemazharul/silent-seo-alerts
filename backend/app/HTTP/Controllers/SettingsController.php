<?php

namespace SEOChangeMonitor\HTTP\Controllers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Request\Request;
use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Response;
use SEOChangeMonitor\Services\Alerts\SlackNotifier;
use SEOChangeMonitor\Services\Alerts\WebhookNotifier;
use SEOChangeMonitor\Services\Reports\WeeklyReport;
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
                return Response::error(__('Invalid check frequency.', 'seo-change-monitor'));
            }

            $partial['frequency'] = $frequency;
        }

        if ($request->has('email_threshold')) {
            $threshold = (string) $request->get('email_threshold');
            if (!\in_array($threshold, Settings::THRESHOLDS, true)) {
                return Response::error(__('Invalid email threshold.', 'seo-change-monitor'));
            }

            $partial['email_threshold'] = $threshold;
        }

        if ($request->has('email_enabled')) {
            $partial['email_enabled'] = (bool) $request->get('email_enabled');
        }

        if ($request->has('email_recipient')) {
            $recipient = sanitize_email((string) $request->get('email_recipient'));
            if ($recipient !== '' && !is_email($recipient)) {
                return Response::error(__('Invalid email address.', 'seo-change-monitor'));
            }

            $partial['email_recipient'] = $recipient;
        }

        if ($request->has('retention_days')) {
            $days = (int) $request->get('retention_days');
            if ($days < 0 || $days > 3650) {
                return Response::error(__('Retention must be between 0 (keep forever) and 3650 days.', 'seo-change-monitor'));
            }

            $partial['retention_days'] = $days;
        }

        if ($request->has('bot_tracking')) {
            $partial['bot_tracking'] = (bool) $request->get('bot_tracking');
        }

        if ($request->has('webhook_enabled')) {
            $partial['webhook_enabled'] = (bool) $request->get('webhook_enabled');
        }

        if ($request->has('webhook_threshold')) {
            $threshold = (string) $request->get('webhook_threshold');
            if (!\in_array($threshold, Settings::THRESHOLDS, true)) {
                return Response::error(__('Invalid webhook threshold.', 'seo-change-monitor'));
            }

            $partial['webhook_threshold'] = $threshold;
        }

        if ($request->has('webhook_secret')) {
            $partial['webhook_secret'] = sanitize_text_field((string) $request->get('webhook_secret'));
        }

        if ($request->has('webhook_urls')) {
            $urls  = $request->get('webhook_urls');
            $urls  = \is_array($urls) ? $urls : preg_split('/\R/', (string) $urls);
            $clean = [];

            foreach ((array) $urls as $url) {
                $url = esc_url_raw(trim((string) $url));
                if ($url === '') {
                    continue;
                }

                if (!in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    return Response::error(
                        __('Webhook URLs must start with http:// or https://.', 'seo-change-monitor')
                    );
                }

                $clean[] = $url;
            }

            if (\count($clean) > 10) {
                return Response::error(__('Please use no more than 10 webhook URLs.', 'seo-change-monitor'));
            }

            $partial['webhook_urls'] = $clean;
        }

        foreach (['ai_enabled', 'slack_enabled', 'report_enabled'] as $flag) {
            if ($request->has($flag)) {
                $partial[$flag] = (bool) $request->get($flag);
            }
        }

        if ($request->has('ai_provider')) {
            $provider = (string) $request->get('ai_provider');
            if (!\in_array($provider, Settings::AI_PROVIDERS, true)) {
                return Response::error(__('Unknown AI provider.', 'seo-change-monitor'));
            }

            $partial['ai_provider'] = $provider;
        }

        // The key is never sent back to the browser, so the form always submits
        // it blank. Blank therefore means "leave it as it is"; clearing is an
        // explicit action.
        if ($request->get('ai_api_key_clear')) {
            $partial['ai_api_key'] = '';
        } elseif ($request->has('ai_api_key')) {
            $submitted = sanitize_text_field((string) $request->get('ai_api_key'));
            if ($submitted !== '') {
                $partial['ai_api_key'] = $submitted;
            }
        }

        if ($request->has('ai_model')) {
            $partial['ai_model'] = sanitize_text_field((string) $request->get('ai_model'));
        }

        if ($request->has('ai_monthly_cap')) {
            $cap = (float) $request->get('ai_monthly_cap');
            if ($cap < 0 || $cap > 1000) {
                return Response::error(__('The monthly cap must be between 0 and 1000.', 'seo-change-monitor'));
            }

            $partial['ai_monthly_cap'] = round($cap, 2);
        }

        if ($request->has('slack_webhook_url')) {
            $url = esc_url_raw(trim((string) $request->get('slack_webhook_url')));
            if ($url !== '' && strpos($url, 'https://hooks.slack.com/') !== 0) {
                return Response::error(__('That does not look like a Slack incoming webhook URL.', 'seo-change-monitor'));
            }

            $partial['slack_webhook_url'] = $url;
        }

        if ($request->has('slack_threshold')) {
            $threshold = (string) $request->get('slack_threshold');
            if (!\in_array($threshold, Settings::THRESHOLDS, true)) {
                return Response::error(__('Invalid Slack threshold.', 'seo-change-monitor'));
            }

            $partial['slack_threshold'] = $threshold;
        }

        if ($request->has('report_recipients')) {
            $partial['report_recipients'] = sanitize_text_field((string) $request->get('report_recipients'));
        }

        foreach (['report_brand_name', 'report_footer'] as $field) {
            if ($request->has($field)) {
                $partial[$field] = sanitize_text_field((string) $request->get($field));
            }
        }

        if ($request->has('report_brand_color')) {
            $color = sanitize_hex_color((string) $request->get('report_brand_color'));
            if (!$color) {
                return Response::error(__('Brand colour must be a hex value like #3b5bdb.', 'seo-change-monitor'));
            }

            $partial['report_brand_color'] = $color;
        }

        if ($request->has('report_logo_url')) {
            $partial['report_logo_url'] = esc_url_raw(trim((string) $request->get('report_logo_url')));
        }

        $settings = Settings::update($partial);

        do_action('SEO_CHANGE_MONITOR_settings_updated', $settings);

        return Response::success($this->withoutSecrets($settings));
    }

    /**
     * The API key never travels back to the browser, only whether one is set.
     */
    private function withoutSecrets(array $settings)
    {
        $settings['ai_api_key_set'] = trim((string) $settings['ai_api_key']) !== '';
        $settings['ai_api_key']     = '';

        return $settings;
    }

    public function testSlack(Request $request)
    {
        $url = esc_url_raw(trim((string) $request->get('url')));

        if ($url === '' || strpos($url, 'https://hooks.slack.com/') !== 0) {
            return Response::error(__('Enter a Slack incoming webhook URL (it starts with https://hooks.slack.com/).', 'seo-change-monitor'));
        }

        if (!(new SlackNotifier())->sendTest($url)) {
            return Response::error(__('Slack did not accept the message. Check the webhook URL is still valid.', 'seo-change-monitor'));
        }

        return Response::success(['delivered' => true]);
    }

    /** Renders the weekly report without sending it. */
    public function previewReport()
    {
        $report = new WeeklyReport();

        return Response::success(
            [
                'html'       => $report->render($report->build()),
                'recipients' => WeeklyReport::recipients(),
            ]
        );
    }

    public function sendReport()
    {
        if (WeeklyReport::recipients() === []) {
            return Response::error(__('Add at least one valid recipient email first.', 'seo-change-monitor'));
        }

        if (!(new WeeklyReport())->send()) {
            return Response::error(__('WordPress could not send the report. Check your site\'s email setup.', 'seo-change-monitor'));
        }

        return Response::success(['sent' => true]);
    }

    /**
     * Posts a sample payload so the user can confirm their endpoint accepts it.
     */
    public function testWebhook(Request $request)
    {
        $url = esc_url_raw(trim((string) $request->get('url')));

        if ($url === '' || !in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return Response::error(__('Enter a valid http:// or https:// URL first.', 'seo-change-monitor'));
        }

        $delivered = (new WebhookNotifier())->sendTest($url);

        if (!$delivered) {
            return Response::error(
                __('The endpoint did not accept the test delivery. Check the URL and that it returns a 2xx response.', 'seo-change-monitor')
            );
        }

        return Response::success(['delivered' => true]);
    }
}
