<?php

/**
 * Removes everything the plugin created. WordPress loads this file - and only
 * this file - when the user deletes the plugin, so nothing here may assume the
 * autoloader, the bootstrap or any plugin class is available. The prefixes are
 * therefore spelled out rather than read from Config.
 *
 * @package SEOChangeMonitor
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/** Matches SEOChangeMonitor\Config::VAR_PREFIX. */
const SEO_CHANGE_MONITOR_UNINSTALL_PREFIX = 'SEO_CHANGE_MONITOR_';

/** Every table backend/db/Migrations creates, without the wpdb prefix. */
const SEO_CHANGE_MONITOR_UNINSTALL_TABLES = [
    'targets',
    'snapshots',
    'findings',
    'check_runs',
    'events',
    'bot_visits',
];

/** Every hook CronProvider schedules, including ones only older versions used. */
const SEO_CHANGE_MONITOR_UNINSTALL_HOOKS = [
    'scheduled_check',
    'run_tick',
    'post_change_check',
    'daily_maintenance',
    'weekly_report',
];

/**
 * Drops the plugin's tables, options, transients and scheduled events for the
 * blog that is currently switched to.
 */
function seo_change_monitor_uninstall_site()
{
    global $wpdb;

    $prefix = SEO_CHANGE_MONITOR_UNINSTALL_PREFIX;

    foreach (SEO_CHANGE_MONITOR_UNINSTALL_HOOKS as $hook) {
        wp_unschedule_hook($prefix . $hook);
    }

    foreach (SEO_CHANGE_MONITOR_UNINSTALL_TABLES as $table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table names cannot be bound, and these are hard-coded above.
        $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . $prefix . $table . '`');
    }

    $like = $wpdb->esc_like($prefix) . '%';

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deleting rows by prefix has no core API; the prefix is bound.
    $wpdb->query(
        $wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", $like)
    );

    // Bot-throttle transients are stored under the same prefix behind the two
    // names WordPress gives every transient.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- same reason.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . $like,
            '_transient_timeout_' . $like
        )
    );

    wp_cache_flush();
}

/**
 * Runs the cleanup for every site the plugin could have touched.
 *
 * The loop lives in a function so its counters stay local: WordPress includes
 * this file at file scope, where they would otherwise become globals.
 */
function seo_change_monitor_uninstall_everywhere()
{
    if (!is_multisite()) {
        seo_change_monitor_uninstall_site();

        return;
    }

    // get_sites() is capped, so page through it - a large network would
    // otherwise keep its tables on every site past the first hundred.
    $offset = 0;

    do {
        $siteIds = get_sites(['fields' => 'ids', 'number' => 100, 'offset' => $offset]);

        foreach ($siteIds as $siteId) {
            switch_to_blog($siteId);
            seo_change_monitor_uninstall_site();
            restore_current_blog();
        }

        $offset += 100;
    } while (count($siteIds) === 100);
}

seo_change_monitor_uninstall_everywhere();
