<?php

namespace RayzenAI\UrlManager\Filament\Resources\UrlVisitResource\Pages;

use Filament\Resources\Pages\ListRecords;
use RayzenAI\UrlManager\Filament\Resources\UrlVisitResource;

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
