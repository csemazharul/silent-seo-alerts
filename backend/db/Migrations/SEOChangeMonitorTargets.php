<?php

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Blueprint;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Schema;
use SEOChangeMonitor\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOChangeMonitorTargets extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'targets',
            function (Blueprint $table): void {
                $table->id();
                $table->varchar('type')->length(16)->defaultValue('page')->index();
                $table->bigint('post_id')->unsigned()->nullable()->index();
                $table->text('url');
                $table->varchar('label')->length(255)->nullable();
                $table->tinyint('is_active')->length(1)->defaultValue(1)->index();
                $table->datetime('last_checked_at')->nullable();
                $table->varchar('last_result')->length(20)->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('targets');
    }
}
