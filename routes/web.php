<?php

use Illuminate\Support\Facades\Route;
use RayzenAI\UrlManager\Http\Controllers\UrlController;

// Sitemap route
Route::get('/sitemap.xml', [UrlController::class, 'sitemap'])
    ->name('url-manager.sitemap');

// Catch-all route for URL management (should be registered last)
Route::fallback([UrlController::class, 'handle'])
    ->name('url-manager.handle');