<?php

namespace SEOChangeMonitor\Providers;

if (!\defined('ABSPATH')) {
    exit;
}

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPKit\Hooks\Hooks;
use SEOChangeMonitor\Models\BotVisit;
use SEOChangeMonitor\Services\CheckEngine\AiBots;
use SEOChangeMonitor\Services\Db;
use SEOChangeMonitor\Services\Settings;

/**
 * Notes when a known AI crawler visits, so the plugin can later point out that
 * one has gone quiet.
 *
 * Only sees requests that reach PHP: full-page caching or a CDN can hide
 * visits entirely, which the UI says plainly rather than implying absence.
 */
class BotTrackerProvider
{
    /** One write per bot per hour is plenty for a last-seen date. */
    private const THROTTLE_SECONDS = 3600;

    public function __construct()
    {
        Hooks::addAction('wp', [$this, 'maybeRecordVisit']);
    }

    public function maybeRecordVisit()
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (!Settings::get('bot_tracking')) {
            return;
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $slug = AiBots::matchUserAgent($userAgent);
        if (!$slug) {
            return;
        }

        $throttleKey = Config::withPrefix('bot_seen_' . $slug);
        if (get_transient($throttleKey)) {
            return;
        }

        set_transient($throttleKey, 1, self::THROTTLE_SECONDS);
        $this->upsert($slug);
    }

    private function upsert($slug)
    {
        $now      = gmdate('Y-m-d H:i:s');
        $existing = BotVisit::findOne(['bot_slug' => $slug]);

        if ($existing) {
            Db::query(
                'UPDATE `' . Db::table('bot_visits') . '` SET last_seen_at = %s, hits = hits + 1 WHERE id = %d',
                [$now, $existing->id]
            );

            return;
        }

        BotVisit::insert(
            [
                'bot_slug'      => $slug,
                'first_seen_at' => $now,
                'last_seen_at'  => $now,
                'hits'          => 1,
            ]
        );
    }
}
