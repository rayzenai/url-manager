<?php

namespace RayzenAI\UrlManager\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\File;
use RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting;
use RayzenAI\UrlManager\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleSettings extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $slug = 'google-search-console-settings';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Google Search Console';
    protected static ?int $navigationSort = 100;
    
    public static function getNavigationGroup(): ?string
    {
        return config('url-manager.filament.navigation_group', 'System');
    }
    protected string $view = 'url-manager::filament.pages.google-search-console-settings';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $settings = GoogleSearchConsoleSetting::getSettings();
        
        $this->form->fill([
            'enabled' => $settings->enabled,
            'site_url' => $settings->site_url ?: 'sc-domain:' . parse_url(url('/'), PHP_URL_HOST),
            'frontend_url' => $settings->frontend_url ?: url('/'),
            'credentials_json' => '', // Don't show existing credentials for security
            'service_account_email' => $settings->service_account_email,
            'has_saved_credentials' => !empty($settings->credentials), // Track if credentials exist
        ]);
    }
    
    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Google Search Console Configuration')
                    ->description('Configure Google Search Console API integration for automatic sitemap submission.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Google Search Console Integration')
                            ->helperText('Enable API integration for sitemap submission and search analytics')
                            ->live(),
                            
                        Forms\Components\TextInput::make('site_url')
                            ->label('Google Search Console Property')
                            ->placeholder('https://example.com or sc-domain:example.com')
                            ->helperText('Enter your Google Search Console property (e.g., "https://www.example.com" or "sc-domain:example.com")')
                            ->required(),
                            
                        Forms\Components\TextInput::make('frontend_url')
                            ->label('Frontend Website URL')
                            ->placeholder('https://example.com')
                            ->helperText('Enter your actual website URL for sitemap generation (e.g., "https://www.example.com")')
                            ->url()
                            ->required(),
                    ]),
                    
                Section::make('Service Account Configuration')
                    ->description('Configure your Google Service Account credentials.')
                    ->schema([
                        Forms\Components\Textarea::make('credentials_json')
                            ->label('Service Account JSON')
                            ->placeholder(fn (Get $get) => 
                                $get('has_saved_credentials') 
                                    ? 'Credentials are already saved. Paste new JSON here to update them...'
                                    : 'Paste your entire Service Account JSON here...'
                            )
                            ->helperText(fn (Get $get) => 
                                $get('has_saved_credentials')
                                    ? '✅ Credentials are saved securely in the database. Leave empty to keep existing credentials.'
                                    : 'Paste the complete JSON content from your Service Account credentials file'
                            )
                            ->rows(10)
                            ->required(fn (Get $get) => !$get('has_saved_credentials'))
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    try {
                                        // Validate and parse JSON
                                        $json = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && isset($json['client_email'])) {
                                            $set('service_account_email', $json['client_email']);
                                        }
                                    } catch (\Exception $e) {
                                        // Invalid JSON, ignore
                                    }
                                }
                            })
                            ->live(),
                            
                        Forms\Components\Hidden::make('has_saved_credentials'),
                            
                        Forms\Components\TextInput::make('service_account_email')
                            ->label('Service Account Email')
                            ->email()
                            ->placeholder('service-account@project.iam.gserviceaccount.com')
                            ->helperText('The email address of your service account (extracted from JSON)')
                            ->disabled()
                            ->dehydrated(),
                            
                        TextEntry::make('setup_instructions')
                            ->label('Setup Instructions')
                            ->state(fn () => view('url-manager::filament.partials.service-account-instructions')),
                    ])
                    ->visible(fn (Get $get) => $get('enabled')),
                    
                Section::make('Actions')
                    ->schema([
                        Actions::make([
                            Action::make('generate_sitemap')
                                ->label('Generate Sitemap')
                                ->icon('heroicon-o-arrow-path')
                                ->color('success')
                                ->action(function () {
                                    $this->generateSitemap();
                                })
                                ->visible(fn (Get $get) => $get('enabled')),
                                
                            Action::make('generate_image_sitemap')
                                ->label('Generate Image Sitemap')
                                ->icon('heroicon-o-photo')
                                ->color('info')
                                ->action(function () {
                                    $this->generateImageSitemap();
                                })
                                ->visible(fn (Get $get) => $get('enabled')),
                                
                            Action::make('generate_video_sitemap')
                                ->label('Generate Video Sitemap')
                                ->icon('heroicon-o-video-camera')
                                ->color('warning')
                                ->action(function () {
                                    $this->generateVideoSitemap();
                                })
                                ->visible(fn (Get $get) => $get('enabled')),
                                
                            ActionGroup::make($this->getViewSitemapActions())
                                ->label('View Sitemaps')
                                ->icon('heroicon-o-eye')
                                ->button()
                                ->color('gray')
                                ->visible(fn (Get $get) => $get('enabled')),
                                
                            Action::make('test_connection')
                                ->label('Test Connection')
                                ->icon('heroicon-o-signal')
                                ->action(function () {
                                    $this->testConnection();
                                })
                                ->visible(fn (Get $get) => 
                                    $get('enabled') && 
                                    (!empty($get('service_account_email')) || $get('has_saved_credentials'))
                                ),
                                
                            Action::make('submit_sitemap')
                                ->label('Submit Sitemap Now')
                                ->icon('heroicon-o-paper-airplane')
                                ->requiresConfirmation()
                                ->action(function () {
                                    $this->submitSitemap();
                                })
                                ->visible(fn (Get $get) => 
                                    $get('enabled') && 
                                    (!empty($get('service_account_email')) || $get('has_saved_credentials'))
                                ),
                                
                            Action::make('view_sitemaps')
                                ->label('View Submitted Sitemaps')
                                ->icon('heroicon-o-list-bullet')
                                ->action(function () {
                                    $this->viewSitemaps();
                                })
                                ->visible(fn (Get $get) => 
                                    $get('enabled') && 
                                    (!empty($get('service_account_email')) || $get('has_saved_credentials'))
                                ),
                        ]),
                    ])
                    ->visible(fn (Get $get) => $get('enabled')),
            ])
            ->statePath('data');
    }
    
    public function save(): void
    {
        $data = $this->form->getState();
        
        $settings = GoogleSearchConsoleSetting::getSettings();
        
        $updateData = [
            'enabled' => $data['enabled'],
            'site_url' => $data['site_url'],
            'frontend_url' => $data['frontend_url'],
        ];
        
        // Only update credentials if new JSON was provided
        if (!empty($data['credentials_json'])) {
            try {
                $credentials = json_decode($data['credentials_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON format');
                }
                
                $updateData['credentials'] = $credentials;
                if (isset($credentials['client_email'])) {
                    $updateData['service_account_email'] = $credentials['client_email'];
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Invalid JSON')
                    ->body('The credentials JSON is not valid. Please check the format.')
                    ->danger()
                    ->send();
                return;
            }
        }
        
        $settings->updateSettings($updateData);
        
        Notification::make()
            ->title('Settings saved successfully')
            ->body('Your Google Search Console settings have been saved to the database.')
            ->success()
            ->send();
    }
    
    
    protected function testConnection(): void
    {
        try {
            // Get current form data
            $data = $this->form->getState();
            
            // Get current settings from database
            $settings = GoogleSearchConsoleSetting::getSettings();
            
            // Check if we have credentials (either from form or already saved)
            $hasCredentials = !empty($data['credentials_json']) || !empty($settings->credentials);
            
            if (!$hasCredentials) {
                Notification::make()
                    ->title('No credentials')
                    ->body('Please provide Service Account JSON credentials.')
                    ->danger()
                    ->send();
                return;
            }
            
            // Update with new credentials if provided
            if (!empty($data['credentials_json'])) {
                try {
                    $credentials = json_decode($data['credentials_json'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Invalid JSON format');
                    }
                    $settings->credentials = $credentials;
                    if (isset($credentials['client_email'])) {
                        $settings->service_account_email = $credentials['client_email'];
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Invalid JSON')
                        ->body('The credentials JSON is not valid.')
                        ->danger()
                        ->send();
                    return;
                }
            }
            
            // Update other settings
            $settings->enabled = true;
            $settings->site_url = $data['site_url'];
            $settings->save();
            
            // Test the connection
            $service = new GoogleSearchConsoleService();
            $result = $service->getSitemaps();
            
            if ($result['success']) {
                Notification::make()
                    ->title('Connection successful!')
                    ->body('Successfully connected to Google Search Console API.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Connection failed')
                    ->body($result['message'] ?? 'Could not connect to Google Search Console API.')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Connection error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function submitSitemap(): void
    {
        try {
            // Get current form data
            $data = $this->form->getState();
            
            // Use current settings for submission
            $result = GoogleSearchConsoleService::submitGoogleSitemap();
            
            if ($result['success']) {
                Notification::make()
                    ->title('Sitemap submitted successfully!')
                    ->body("Sitemap submitted via API to: {$result['sitemap_url']}")
                    ->success()
                    ->send();
            } else {
                $title = 'Sitemap submission failed';
                $messages = [$result['message'] ?? 'Could not submit sitemap.'];
                
                // Add additional info if available
                if (isset($result['info'])) {
                    $messages[] = '';
                    $messages[] = '💡 ' . $result['info'];
                }
                
                Notification::make()
                    ->title($title)
                    ->body(implode("\n", $messages))
                    ->danger()
                    ->duration(10000)
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Submission error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function viewSitemaps(): void
    {
        try {
            // Get current form data
            $data = $this->form->getState();
            
            // Use current settings
            $service = new GoogleSearchConsoleService();
            $result = $service->getSitemaps();
            
            if ($result['success'] && !empty($result['sitemaps'])) {
                $sitemapList = collect($result['sitemaps'])
                    ->map(fn($s) => "• {$s['path']} (Errors: {$s['errors']}, Warnings: {$s['warnings']})")
                    ->join("\n");
                    
                Notification::make()
                    ->title('Submitted Sitemaps')
                    ->body($sitemapList ?: 'No sitemaps found.')
                    ->success()
                    ->duration(10000)
                    ->send();
            } else {
                Notification::make()
                    ->title('No sitemaps found')
                    ->body('No sitemaps are currently submitted to Google Search Console.')
                    ->info()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error fetching sitemaps')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function generateSitemap(): void
    {
        try {
            // Get the count of active URLs
            $urlCount = \RayzenAI\UrlManager\Models\Url::active()->count();
            
            // Generate the sitemap using Artisan command
            \Illuminate\Support\Facades\Artisan::call('sitemap:generate');
            
            Notification::make()
                ->title('Sitemap generated successfully!')
                ->body("Generated sitemap with {$urlCount} URLs")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error generating sitemap')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function generateImageSitemap(): void
    {
        try {
            // Get the count of images
            $imageCount = \Illuminate\Support\Facades\DB::table('media_metadata')
                ->where('mime_type', 'LIKE', 'image/%')
                ->count();
            
            if ($imageCount === 0) {
                Notification::make()
                    ->title('No images found')
                    ->body('No images found in media metadata to generate sitemap.')
                    ->warning()
                    ->send();
                return;
            }
            
            // Generate the image sitemap using Artisan command
            \Illuminate\Support\Facades\Artisan::call('sitemap:generate-images');
            
            Notification::make()
                ->title('Image sitemap generated successfully!')
                ->body("Generated image sitemap with {$imageCount} images")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error generating image sitemap')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function generateVideoSitemap(): void
    {
        try {
            // Get the count of videos
            $videoCount = \Illuminate\Support\Facades\DB::table('media_metadata')
                ->where('mime_type', 'LIKE', 'video/%')
                ->count();
            
            if ($videoCount === 0) {
                Notification::make()
                    ->title('No videos found')
                    ->body('No videos found in media metadata to generate sitemap.')
                    ->warning()
                    ->send();
                return;
            }
            
            // Generate the video sitemap using Artisan command
            \Illuminate\Support\Facades\Artisan::call('sitemap:generate-videos');
            
            Notification::make()
                ->title('Video sitemap generated successfully!')
                ->body("Generated video sitemap with {$videoCount} videos")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error generating video sitemap')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getViewSitemapActions(): array
    {
        $actions = [];
        
        // Master Index
        if (file_exists(public_path('sitemap-index.xml'))) {
            $actions[] = Action::make('view_master_index')
                ->label('Master Sitemap Index')
                ->icon('heroicon-o-list-bullet')
                ->url(url('/sitemap-index.xml'))
                ->openUrlInNewTab();
        }
        
        // URL Sitemap
        if (file_exists(public_path('sitemap.xml'))) {
            $actions[] = Action::make('view_url_sitemap')
                ->label('URL Sitemap')
                ->icon('heroicon-o-link')
                ->url(url('/sitemap.xml'))
                ->openUrlInNewTab();
        }
        
        // Image Sitemap Index
        if (file_exists(public_path('sitemap-images.xml'))) {
            $actions[] = Action::make('view_image_sitemap')
                ->label('Image Sitemap Index')
                ->icon('heroicon-o-photo')
                ->url(url('/sitemap-images.xml'))
                ->openUrlInNewTab();
            
            // Add individual image sitemap files
            $i = 0;
            while (file_exists(public_path("sitemap-images-{$i}.xml"))) {
                $fileContent = file_get_contents(public_path("sitemap-images-{$i}.xml"));
                $imageCount = substr_count($fileContent, '<image:image>');
                $actions[] = Action::make("view_image_sitemap_{$i}")
                    ->label("→ Image Sitemap Part " . ($i + 1) . " ({$imageCount} images)")
                    ->url(url("/sitemap-images-{$i}.xml"))
                    ->openUrlInNewTab();
                $i++;
            }
        }
        
        // Video Sitemap
        if (file_exists(public_path('sitemap-videos.xml'))) {
            $actions[] = Action::make('view_video_sitemap')
                ->label('Video Sitemap')
                ->icon('heroicon-o-video-camera')
                ->url(url('/sitemap-videos.xml'))
                ->openUrlInNewTab();
        }
        
        return $actions;
    }
}