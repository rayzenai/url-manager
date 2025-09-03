<?php

use Illuminate\Support\Facades\Route;
use RayzenAI\UrlManager\Http\Controllers\TrackingController;

// API routes are automatically prefixed with 'api/url-manager' by the service provider
// Visit tracking endpoint for cached responses
Route::post('/track-visit', [TrackingController::class, 'track'])
    ->name('url-manager.track-visit');