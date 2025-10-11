<?php

use Illuminate\Support\Facades\Route;
use RayzenAI\UrlManager\Http\Controllers\UrlController;

// Sitemap route
Route::get('/sitemap.xml', [UrlController::class, 'sitemap'])
    ->name('url-manager.sitemap');

// Test page for referrer tracking (only in non-production environments)
if (! app()->environment('production')) {
    Route::get('/_url-manager/test', function () {
        $content = file_get_contents(base_path('vendor/rayzenai/url-manager/test.html'));
        return response($content)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Referrer-Policy', 'unsafe-url');
    })->name('url-manager.test');
}

// Catch-all route for URL management (should be registered last)
Route::fallback([UrlController::class, 'handle'])
    ->name('url-manager.handle');