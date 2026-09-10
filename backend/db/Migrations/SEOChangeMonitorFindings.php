<?php

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Blueprint;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Schema;
use SEOChangeMonitor\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOChangeMonitorFindings extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'findings',
            function (Blueprint $table): void {
                $table->id();
                $table->bigint('target_id')->unsigned()->nullable()->index();
                $table->bigint('check_run_id')->unsigned()->nullable()->index();
                $table->varchar('change_type')->length(64)->index();
                $table->varchar('severity')->length(10)->index();
                $table->varchar('status')->length(16)->defaultValue('open')->index();
                $table->longtext('before_value')->nullable();
                $table->longtext('after_value')->nullable();
                $table->longtext('attributed_events')->nullable();
                $table->tinyint('is_expected')->length(1)->defaultValue(0);
                $table->text('note')->nullable();
                $table->datetime('resolved_at')->nullable();
                $table->bigint('resolved_by')->unsigned()->nullable();
                $table->longtext('meta')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('findings');
    }
}
