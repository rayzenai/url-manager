<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use RayzenAI\UrlManager\Filament\Resources\Urls\UrlResource;
use RayzenAI\UrlManager\Services\GoogleSearchConsoleService;

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
            
            Action::make('submit-to-google')
                ->label('Submit to Search Engines')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->requiresConfirmation()
                ->modalHeading('Submit Sitemap to Search Engines')
                ->modalDescription('This will notify Google and Bing about your updated sitemap.')
                ->modalSubmitActionLabel('Submit')
                ->action(function () {
                    // First, generate the latest sitemap
                    if (class_exists(\RayzenAI\UrlManager\Commands\GenerateSitemap::class)) {
                        Artisan::call('sitemap:generate');
                    }
                    
                    // Submit to search engines
                    $result = GoogleSearchConsoleService::submitToAllSearchEngines();
                    
                    if ($result['success']) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sitemap submitted successfully!')
                            ->body('Your sitemap has been submitted to Google and Bing.')
                            ->success()
                            ->send();
                    } else {
                        // Show details about which submissions succeeded/failed
                        $message = 'Submission results:' . PHP_EOL;
                        
                        if (isset($result['results']['google'])) {
                            $googleStatus = $result['results']['google']['success'] ? '✅' : '❌';
                            $message .= "Google: {$googleStatus}" . PHP_EOL;
                        }
                        
                        if (isset($result['results']['bing'])) {
                            $bingStatus = $result['results']['bing']['success'] ? '✅' : '❌';
                            $message .= "Bing: {$bingStatus}";
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Partial submission')
                            ->body($message)
                            ->warning()
                            ->send();
                    }
                })
                ->color('info')
                ->visible(fn () => config('url-manager.sitemap.enabled', true)),
            
            CreateAction::make(),
        ];
    }
}