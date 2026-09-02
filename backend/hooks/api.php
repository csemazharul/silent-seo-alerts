<?php

use SEOChangeMonitor\Deps\BitApps\WPKit\Http\Router\Route;
use SEOChangeMonitor\HTTP\Controllers\RestStatusController;

if (!defined('ABSPATH')) {
    exit;
}

// Admin-only: the WPKit API router registers routes with a permissive
// permission_callback, so the isAdmin middleware is what actually guards this.
Route::group(
    function (): void {
        Route::get('status', [RestStatusController::class, 'status']);
    }
)->middleware('isAdmin');
