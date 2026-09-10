<?php

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Router\Route;
use SEOChangeMonitor\HTTP\Controllers\CheckController;
use SEOChangeMonitor\HTTP\Controllers\FindingController;
use SEOChangeMonitor\HTTP\Controllers\SettingsController;
use SEOChangeMonitor\HTTP\Controllers\StatusController;
use SEOChangeMonitor\HTTP\Controllers\TargetController;

if (!defined('ABSPATH')) {
    exit;
}

Route::group(
    function (): void {
        Route::post('targets/get', [TargetController::class, 'index']);
        Route::post('targets/create', [TargetController::class, 'store']);
        Route::post('targets/update', [TargetController::class, 'update']);
        Route::post('targets/delete', [TargetController::class, 'destroy']);
        Route::post('targets/post-search', [TargetController::class, 'searchPosts']);

        Route::post('check/now', [CheckController::class, 'checkNow']);
        Route::post('check/status', [CheckController::class, 'runStatus']);

        Route::post('findings/get', [FindingController::class, 'index']);
        Route::post('findings/show', [FindingController::class, 'show']);
        Route::post('findings/resolve', [FindingController::class, 'resolve']);
        Route::post('findings/mute', [FindingController::class, 'mute']);
        Route::post('findings/reopen', [FindingController::class, 'reopen']);

        Route::post('settings/get', [SettingsController::class, 'get']);
        Route::post('settings/update', [SettingsController::class, 'update']);

        Route::post('dashboard/summary', [StatusController::class, 'dashboard']);
        Route::post('status/site', [StatusController::class, 'site']);
        Route::post('baseline/arm', [StatusController::class, 'armBaseline']);
        Route::post('baseline/disarm', [StatusController::class, 'disarmBaseline']);
    }
)->middleware('nonce', 'isAdmin');
