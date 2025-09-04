<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
            
            ActionGroup::make($this->getViewSitemapActions())
                ->label('View Sitemaps')
                ->icon(Heroicon::OutlinedEye)
                ->button()
                ->color('gray')
                ->visible(fn () => config('url-manager.sitemap.enabled', true)),
            
            ActionGroup::make([
                    Action::make('generate-all-sitemaps')
                        ->label('Generate All Sitemaps')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->modalHeading('Generate All Sitemaps')
                        ->modalDescription('This will generate URL, image, and video sitemaps.')
                        ->modalSubmitActionLabel('Generate All')
                        ->action(function () {
                            // Get counts
                            $urlCount = \RayzenAI\UrlManager\Models\Url::active()->count();
                            $imageCount = \Illuminate\Support\Facades\DB::table('media_metadata')
                                ->where('mime_type', 'LIKE', 'image/%')
                                ->count();
                            $videoCount = \Illuminate\Support\Facades\DB::table('media_metadata')
                                ->where('mime_type', 'LIKE', 'video/%')
                                ->count();
                            
                            // Generate all sitemaps
                            Artisan::call('sitemap:generate-all');
                            
                            \Filament\Notifications\Notification::make()
                                ->title('All sitemaps generated!')
                                ->body("Generated sitemaps: {$urlCount} URLs, {$imageCount} images, {$videoCount} videos")
                                ->success()
                                ->duration(10000)
                                ->send();
                        })
                        ->color('primary'),
                        
                    Action::make('generate-url-sitemap')
                        ->label('Generate URL Sitemap')
                        ->icon(Heroicon::OutlinedLink)
                        ->requiresConfirmation()
                        ->modalHeading('Generate URL Sitemap')
                        ->modalDescription('This will regenerate the sitemap.xml file with the latest active URLs.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function () {
                            // Get the count of active URLs
                            $urlCount = \RayzenAI\UrlManager\Models\Url::active()->count();
                            
                            // Generate the sitemap
                            Artisan::call('sitemap:generate');
                            
                            \Filament\Notifications\Notification::make()
                                ->title('URL sitemap generated!')
                                ->body("Generated sitemap with {$urlCount} URLs")
                                ->success()
                                ->send();
                        })
                        ->color('success'),
                        
                    Action::make('generate-image-sitemap')
                        ->label('Generate Image Sitemap')
                        ->icon(Heroicon::OutlinedPhoto)
                        ->requiresConfirmation()
                        ->modalHeading('Generate Image Sitemap')
                        ->modalDescription('This will generate an image sitemap from all images in the media metadata.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function () {
                            // Get the count of images
                            $imageCount = \Illuminate\Support\Facades\DB::table('media_metadata')
                                ->where('mime_type', 'LIKE', 'image/%')
                                ->count();
                            
                            if ($imageCount === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('No images found')
                                    ->body('No images found in media metadata to generate sitemap.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            // Generate the image sitemap
                            Artisan::call('sitemap:generate-images');
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Image sitemap generated!')
                                ->body("Generated image sitemap with {$imageCount} images")
                                ->success()
                                ->send();
                        })
                        ->color('info'),
                        
                    Action::make('generate-video-sitemap')
                        ->label('Generate Video Sitemap')
                        ->icon(Heroicon::OutlinedVideoCamera)
                        ->requiresConfirmation()
                        ->modalHeading('Generate Video Sitemap')
                        ->modalDescription('This will generate a video sitemap from all videos in the media metadata.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function () {
                            // Get the count of videos
                            $videoCount = \Illuminate\Support\Facades\DB::table('media_metadata')
                                ->where('mime_type', 'LIKE', 'video/%')
                                ->count();
                            
                            if ($videoCount === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('No videos found')
                                    ->body('No videos found in media metadata to generate sitemap.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            // Generate the video sitemap
                            Artisan::call('sitemap:generate-videos');
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Video sitemap generated!')
                                ->body("Generated video sitemap with {$videoCount} videos")
                                ->success()
                                ->send();
                        })
                        ->color('warning'),
                ])
                ->label('Generate Sitemaps')
                ->icon(Heroicon::OutlinedArrowPath)
                ->button()
                ->color('success')
                ->visible(fn () => config('url-manager.sitemap.enabled', true)),
            
            Action::make('google-search-console-settings')
                ->label('Search Console Settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->url('/admin/google-search-console-settings')
                ->color('gray'),
            
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
                        $successCount = 0;
                        $totalCount = 0;
                        $messages = [];
                        
                        if (isset($result['results']['google'])) {
                            $totalCount++;
                            if ($result['results']['google']['success']) {
                                $successCount++;
                                $messages[] = "✅ Google: Successfully submitted";
                            } else {
                                $errorMsg = $result['results']['google']['message'] ?? 'Unknown error';
                                $messages[] = "❌ Google: {$errorMsg}";
                                
                                // Add additional info if available
                                if (isset($result['results']['google']['info'])) {
                                    $messages[] = "ℹ️ " . $result['results']['google']['info'];
                                }
                            }
                        }
                        
                        if (isset($result['results']['bing'])) {
                            $totalCount++;
                            if ($result['results']['bing']['success']) {
                                $successCount++;
                                $messages[] = "✅ Bing: Successfully submitted";
                            } else {
                                $errorMsg = $result['results']['bing']['message'] ?? 'Unknown error';
                                $messages[] = "❌ Bing: {$errorMsg}";
                            }
                        }
                        
                        // Determine the notification type and title
                        if ($successCount === 0) {
                            $title = 'All submissions failed';
                            $type = 'danger';
                        } elseif ($successCount === $totalCount) {
                            $title = 'All submissions successful';
                            $type = 'success';
                        } else {
                            $title = "Partial submission ({$successCount}/{$totalCount} succeeded)";
                            $type = 'warning';
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title($title)
                            ->body(implode("\n", $messages))
                            ->{$type}()
                            ->duration(15000) // Show for longer so user can read the details
                            ->send();
                    }
                })
                ->color('info')
                ->visible(fn () => config('url-manager.sitemap.enabled', true)),
            
            CreateAction::make(),
        ];
    }
    
    protected function getViewSitemapActions(): array
    {
        $actions = [];
        
        // Master Index
        if (file_exists(public_path('sitemap-index.xml'))) {
            $actions[] = Action::make('view-master-index')
                ->label('Master Sitemap Index')
                ->icon(Heroicon::OutlinedListBullet)
                ->url(url('/sitemap-index.xml'))
                ->openUrlInNewTab();
        }
        
        // URL Sitemap
        if (file_exists(public_path('sitemap.xml'))) {
            $actions[] = Action::make('view-url-sitemap')
                ->label('URL Sitemap')
                ->icon(Heroicon::OutlinedLink)
                ->url(url('/sitemap.xml'))
                ->openUrlInNewTab();
        }
        
        // Image Sitemap Index
        if (file_exists(public_path('sitemap-images.xml'))) {
            $actions[] = Action::make('view-image-sitemap')
                ->label('Image Sitemap Index')
                ->icon(Heroicon::OutlinedPhoto)
                ->url(url('/sitemap-images.xml'))
                ->openUrlInNewTab();
            
            // Add individual image sitemap files
            $i = 0;
            while (file_exists(public_path("sitemap-images-{$i}.xml"))) {
                $fileContent = file_get_contents(public_path("sitemap-images-{$i}.xml"));
                $imageCount = substr_count($fileContent, '<image:image>');
                $actions[] = Action::make("view-image-sitemap-{$i}")
                    ->label("→ Image Sitemap Part " . ($i + 1) . " ({$imageCount} images)")
                    ->url(url("/sitemap-images-{$i}.xml"))
                    ->openUrlInNewTab();
                $i++;
            }
        }
        
        // Video Sitemap
        if (file_exists(public_path('sitemap-videos.xml'))) {
            $actions[] = Action::make('view-video-sitemap')
                ->label('Video Sitemap')
                ->icon(Heroicon::OutlinedVideoCamera)
                ->url(url('/sitemap-videos.xml'))
                ->openUrlInNewTab();
        }
        
        return $actions;
    }
}