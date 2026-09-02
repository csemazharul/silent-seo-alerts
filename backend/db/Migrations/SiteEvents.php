<?php

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Blueprint;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Schema;
use SEOChangeMonitor\Deps\BitApps\WPKit\Migration\Migration;

if (! defined('ABSPATH')) {
    exit;
}

final class SiteEvents extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'events',
            function (Blueprint $table): void {
                $table->id();
                $table->varchar('event_type')->length(64)->index();
                $table->varchar('subject')->length(191)->nullable();
                $table->longtext('details')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('events');
    }
}
