<?php

namespace RayzenAI\UrlManager;

use Illuminate\Routing\Router;
use RayzenAI\UrlManager\Commands\GenerateSitemap;
use RayzenAI\UrlManager\Commands\GenerateUrlsForModels;
use RayzenAI\UrlManager\Commands\SubmitSitemapToGoogle;
use RayzenAI\UrlManager\Http\Middleware\TrackUrlVisits;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class UrlManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('url-manager')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigrations([
                '2025_01_01_000000_create_urls_table',
                '2025_01_01_000001_create_google_search_console_settings_table',
                '2025_09_01_000002_create_url_visits_table',
            ])
            ->hasCommands([
                GenerateSitemap::class,
                GenerateUrlsForModels::class,
                SubmitSitemapToGoogle::class,
            ]);
    }
    
    public function packageBooted()
    {
        $this->registerMiddleware();
    }
    
    protected function registerMiddleware()
    {
        if (!config('url-manager.middleware.enabled', true)) {
            return;
        }
        
        $router = $this->app->make(Router::class);
        $alias = config('url-manager.middleware.alias', 'track-url-visits');
        
        // Register middleware alias
        $router->aliasMiddleware($alias, TrackUrlVisits::class);
        
        // Auto-apply to web routes if configured
        if (config('url-manager.middleware.auto_apply', false)) {
            $router->pushMiddlewareToGroup('web', TrackUrlVisits::class);
        }
    }
}