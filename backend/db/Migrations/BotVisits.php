<?php

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Blueprint;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Schema;
use SEOChangeMonitor\Deps\BitApps\WPKit\Migration\Migration;

if (! defined('ABSPATH')) {
    exit;
}

final class BotVisits extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'bot_visits',
            function (Blueprint $table): void {
                $table->id();
                $table->varchar('bot_slug')->length(64)->index();
                $table->datetime('first_seen_at')->nullable();
                $table->datetime('last_seen_at')->nullable();
                $table->bigint('hits')->unsigned()->defaultValue(0);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('bot_visits');
    }
}
