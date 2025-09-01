<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use RayzenAI\UrlManager\Filament\Resources\Urls\UrlResource;

class ListUrls extends ListRecords
{
    protected static string $resource = UrlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create-redirect')
                ->label('Create Redirect')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->modalHeading('Create 301 Redirect')
                ->modalDescription('Create a permanent redirect from one URL to another')
                ->form([
                    \Filament\Forms\Components\TextInput::make('from')
                        ->label('From URL')
                        ->placeholder('old-page')
                        ->required()
                        ->helperText('The URL path to redirect from'),
                    \Filament\Forms\Components\TextInput::make('to')
                        ->label('To URL')
                        ->placeholder('new-page')
                        ->required()
                        ->helperText('The URL path to redirect to'),
                    \Filament\Forms\Components\Select::make('code')
                        ->label('Redirect Type')
                        ->options([
                            301 => '301 - Permanent',
                            302 => '302 - Temporary',
                        ])
                        ->default(301)
                        ->required(),
                ])
                ->action(function (array $data) {
                    \RayzenAI\UrlManager\Models\Url::createRedirect(
                        $data['from'],
                        $data['to'],
                        $data['code']
                    );
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Redirect created successfully!')
                        ->success()
                        ->send();
                })
                ->color('warning'),
            
            Action::make('view-existing-sitemap')
                ->label('View Sitemap')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn () => url('/sitemap.xml'))
                ->openUrlInNewTab()
                ->color('gray')
                ->visible(fn () => config('url-manager.sitemap.enabled', true)),
            
            Action::make('generate-sitemap')
                ->label('Generate Sitemap')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalHeading('Generate New Sitemap')
                ->modalDescription('This will regenerate the sitemap.xml file with the latest active URLs.')
                ->modalSubmitActionLabel('Generate')
                ->action(function () {
                    if (class_exists(\RayzenAI\UrlManager\Commands\GenerateSitemap::class)) {
                        Artisan::call('sitemap:generate');
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Sitemap generated successfully!')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Sitemap generation not configured')
                            ->warning()
                            ->send();
                    }
                })
                ->color('success')
                ->visible(fn () => config('url-manager.sitemap.enabled', true)),
            
            CreateAction::make(),
        ];
    }
}