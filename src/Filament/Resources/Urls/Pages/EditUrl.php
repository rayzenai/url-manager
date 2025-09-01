<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use RayzenAI\UrlManager\Filament\Resources\Urls\UrlResource;

class EditUrl extends EditRecord
{
    protected static string $resource = UrlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['last_modified_at'] = now();
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}