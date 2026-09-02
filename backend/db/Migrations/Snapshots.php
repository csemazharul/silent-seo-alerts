<?php

use SEOChangeMonitor\Config;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Blueprint;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Connection;
use SEOChangeMonitor\Deps\BitApps\WPDatabase\Schema;
use SEOChangeMonitor\Deps\BitApps\WPKit\Migration\Migration;

if (! defined('ABSPATH')) {
    exit;
}

final class Snapshots extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'snapshots',
            function (Blueprint $table): void {
                $table->id();
                $table->bigint('target_id')->unsigned()->index();
                $table->bigint('check_run_id')->unsigned()->nullable()->index();
                $table->longtext('fields');
                $table->char('content_hash')->length(32)->nullable();
                $table->smallint('http_status')->unsigned()->nullable();
                $table->text('redirect_target')->nullable();
                $table->varchar('fetch_error')->length(191)->nullable();
                $table->longblob('html_gz')->nullable();
                $table->tinyint('is_baseline')->length(1)->defaultValue(0)->index();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('snapshots');
    }
}
