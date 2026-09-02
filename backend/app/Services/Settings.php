<?php

namespace SEOChangeMonitor\Services;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;

class Settings
{
    public const FREQUENCIES = ['hourly', 'twicedaily', 'daily', 'off'];

    public const THRESHOLDS = ['critical', 'warning', 'info'];

    public static function defaults()
    {
        return [
            'frequency'         => 'daily',
            'email_enabled'     => true,
            'email_threshold'   => 'warning',
            'email_recipient'   => '',
            'retention_days'    => 0, // 0 = keep forever; open criticals are never trimmed
            'bot_tracking'      => true,
            'webhook_enabled'   => false,
            'webhook_urls'      => [],
            'webhook_threshold' => 'critical',
            'webhook_secret'    => '',

            // AI explanations: off, and invisible until a key is entered.
            'ai_enabled'        => false,
            'ai_provider'       => 'anthropic',
            'ai_api_key'        => '',
            'ai_model'          => '',
            'ai_monthly_cap'    => 5.0, // USD; 0 = no cap

            'slack_enabled'     => false,
            'slack_webhook_url' => '',
            'slack_threshold'   => 'critical',

            'report_enabled'      => false,
            'report_recipients'   => '',
            'report_brand_name'   => '',
            'report_brand_color'  => '#3b5bdb',
            'report_logo_url'     => '',
            'report_footer'       => '',
        ];
    }

    public const AI_PROVIDERS = ['anthropic', 'openai'];

    /**
     * Webhook endpoints, filtered down to well-formed URLs.
     *
     * @return string[]
     */
    public static function webhookUrls()
    {
        $urls = self::get('webhook_urls');

        if (!\is_array($urls)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(trim(...), $urls),
                static fn ($url) => $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false
            )
        );
    }

    public static function all()
    {
        $stored = Config::getOption('settings');

        return array_merge(self::defaults(), \is_array($stored) ? $stored : []);
    }

    public static function get($key)
    {
        $all = self::all();

        return $all[$key] ?? null;
    }

    public static function update(array $partial)
    {
        $settings = array_merge(self::all(), array_intersect_key($partial, self::defaults()));
        Config::updateOption('settings', $settings);

        return $settings;
    }

    public static function emailRecipient()
    {
        $recipient = self::get('email_recipient');

        return \is_string($recipient) && is_email($recipient) ? $recipient : get_option('admin_email');
    }
}
