<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Pages;

use Filament\Resources\Pages\CreateRecord;
use RayzenAI\UrlManager\Filament\Resources\Urls\UrlResource;

class CreateUrl extends CreateRecord
{
    protected static string $resource = UrlResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['last_modified_at'] = now();
        
        if (!isset($data['visits'])) {
            $data['visits'] = 0;
        }
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}