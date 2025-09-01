<?php

namespace RayzenAI\UrlManager;

use Filament\Contracts\Plugin;
use Filament\Panel;
use RayzenAI\UrlManager\Filament\Pages\GoogleSearchConsoleSettings;
use RayzenAI\UrlManager\Filament\Resources\Urls\UrlResource;
use RayzenAI\UrlManager\Filament\Widgets\TopUrlsTable;
use RayzenAI\UrlManager\Filament\Widgets\UrlStatsOverview;

class UrlManagerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'url-manager';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            UrlResource::class,
        ]);
        
        $panel->pages([
            GoogleSearchConsoleSettings::class,
        ]);
        
        if (config('url-manager.filament.widgets', true)) {
            $panel->widgets([
                UrlStatsOverview::class,
                TopUrlsTable::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static;
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}