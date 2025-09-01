<?php

namespace RayzenAI\UrlManager;

use RayzenAI\UrlManager\Commands\GenerateSitemap;
use RayzenAI\UrlManager\Commands\GenerateUrlsForModels;
use RayzenAI\UrlManager\Commands\SubmitSitemapToGoogle;
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
            ->hasMigration('2025_01_01_000000_create_urls_table')
            ->hasCommands([
                GenerateSitemap::class,
                GenerateUrlsForModels::class,
                SubmitSitemapToGoogle::class,
            ]);
    }
}