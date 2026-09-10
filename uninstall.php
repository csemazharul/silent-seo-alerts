<?php

/**
 * Removes everything the plugin created. WordPress loads this file - and only
 * this file - when the user deletes the plugin, so nothing here may assume the
 * autoloader, the bootstrap or any plugin class is available. The storage
 * prefix is therefore spelled out rather than read from Config.
 *
 * @package SilentSeoAlerts
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Drops the plugin's tables, options, transients and scheduled events for the
 * blog that is currently switched to.
 */
function silent_seo_alerts_uninstall_site()
{
    global $wpdb;

    // Matches SEOChangeMonitor\Config::VAR_PREFIX. The plugin was renamed after
    // this prefix was set; the stored data still carries the original one.
    $prefix = 'SEO_CHANGE_MONITOR_';

    // Every hook CronProvider schedules, including ones only older versions used.
    $hooks = ['scheduled_check', 'run_tick', 'post_change_check', 'daily_maintenance', 'weekly_report'];

    // Every table backend/db/Migrations creates, without the wpdb prefix.
    $tables = ['targets', 'snapshots', 'findings', 'check_runs', 'events', 'bot_visits'];

    foreach ($hooks as $hook) {
        wp_unschedule_hook($prefix . $hook);
    }

    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names cannot be bound, and these are hard-coded above.
        $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . $prefix . $table . '`');
    }

    /*
     * Options, plus the bot-throttle transients, which WordPress stores under
     * two further names. Deleting rows by prefix has no core API, so this is a
     * direct query; every pattern is bound.
     */
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM `{$wpdb->options}`
              WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like($prefix) . '%',
            $wpdb->esc_like('_transient_' . $prefix) . '%',
            $wpdb->esc_like('_transient_timeout_' . $prefix) . '%'
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
function silent_seo_alerts_uninstall_everywhere()
{
    if (!is_multisite()) {
        silent_seo_alerts_uninstall_site();

        return;
    }

    // get_sites() is capped, so page through it - a large network would
    // otherwise keep its tables on every site past the first page.
    $perPage = 100;
    $offset  = 0;

    do {
        $siteIds = get_sites(['fields' => 'ids', 'number' => $perPage, 'offset' => $offset]);

        foreach ($siteIds as $siteId) {
            switch_to_blog($siteId);
            silent_seo_alerts_uninstall_site();
            restore_current_blog();
        }

        $offset += $perPage;
    } while (count($siteIds) === $perPage);
}

silent_seo_alerts_uninstall_everywhere();
