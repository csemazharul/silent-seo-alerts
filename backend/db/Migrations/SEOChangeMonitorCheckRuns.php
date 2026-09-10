<?php

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Blueprint;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Schema;
use SEOChangeMonitor\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOChangeMonitorCheckRuns extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'check_runs',
            function (Blueprint $table): void {
                $table->id();
                $table->varchar('trigger_type')->length(20)->defaultValue('manual');
                $table->varchar('status')->length(16)->defaultValue('running')->index();
                $table->int('targets_total')->unsigned()->defaultValue(0);
                $table->int('targets_done')->unsigned()->defaultValue(0);
                $table->int('critical_count')->unsigned()->defaultValue(0);
                $table->int('warning_count')->unsigned()->defaultValue(0);
                $table->int('info_count')->unsigned()->defaultValue(0);
                $table->tinyint('email_sent')->length(1)->defaultValue(0);
                $table->datetime('started_at')->nullable();
                $table->datetime('finished_at')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('check_runs');
    }
}
