<?php

namespace RayzenAI\UrlManager\Filament\Resources\UrlVisitResource\Pages;

use RayzenAI\UrlManager\Filament\Resources\UrlVisitResource;
use Filament\Resources\Pages\ListRecords;

class ListUrlVisits extends ListRecords
{
    protected static string $resource = UrlVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            UrlVisitResource\Widgets\UrlVisitStats::class,
        ];
    }
}