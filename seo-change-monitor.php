<?php
/**
 * Plugin Name:       SEO Change Monitor
 * Description:       Alerts you when a page goes noindex or its title, meta, canonical or schema changes — and explains what it means in plain English.
 * Version:           1.0.0
 * Author:            MI
 * Text Domain:       seo-change-monitor
 * Requires PHP:      8.2
 * Requires at least: 5.0
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'backend/bootstrap.php';
