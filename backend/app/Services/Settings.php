<?php

namespace SEOChangeMonitor\Services;

if (!defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;

class Settings
{
    public const FREQUENCIES = ['hourly', 'twicedaily', 'daily', 'off'];

    public const THRESHOLDS = ['critical', 'warning', 'info'];

    /**
     * Every key the plugin stores. Add-ons register their own through the
     * filter, which is also what makes them persistable: update() only saves
     * keys that appear here.
     */
    public static function defaults()
    {
        return apply_filters(Config::withPrefix('settings_defaults'), [
            'frequency'         => 'daily',
            'email_enabled'     => true,
            'email_threshold'   => 'warning',
            'email_recipient'   => '',
            'retention_days'    => 0, // 0 = keep forever; open criticals are never trimmed
            'bot_tracking'      => true,
        ]);
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
