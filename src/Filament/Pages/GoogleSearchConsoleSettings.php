<?php

namespace RayzenAI\UrlManager\Filament\Pages;

use Filament\Actions\Action;
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
use RayzenAI\UrlManager\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleSettings extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $slug = 'google-search-console-settings';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Google Search Console';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 100;
    protected string $view = 'url-manager::filament.pages.google-search-console-settings';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $config = config('url-manager.google_search_console');
        
        // Convert absolute path back to relative for display
        $credentialsPath = $config['credentials_path'] ?? '';
        if ($credentialsPath && str_starts_with($credentialsPath, storage_path())) {
            // Remove the storage path prefix to show relative path
            $credentialsPath = str_replace(storage_path() . '/', '', $credentialsPath);
        }
        
        $this->form->fill([
            'enabled' => $config['enabled'] ?? false,
            'site_url' => $config['site_url'] ?? url('/'),
            'credentials_path' => $credentialsPath,
            'service_account_email' => $config['service_account_email'] ?? '',
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
                            ->label('Site URL / Domain Property')
                            ->placeholder('https://example.com or sc-domain:example.com')
                            ->helperText('Enter your site URL or domain property (e.g., "https://www.example.com" or "sc-domain:example.com")')
                            ->required()
                            ->default(url('/')),
                    ]),
                    
                Section::make('Service Account Configuration')
                    ->description('Configure your Google Service Account credentials.')
                    ->schema([
                        Forms\Components\TextInput::make('credentials_path')
                            ->label('Credentials File Path')
                            ->placeholder('app/google-credentials/service-account.json')
                            ->helperText('Path relative to storage directory (e.g., app/google-credentials/service-account.json)')
                            ->required()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Convert relative path to absolute for checking
                                $fullPath = storage_path($state);
                                if ($state && File::exists($fullPath)) {
                                    // Try to extract service account email from JSON
                                    $json = json_decode(File::get($fullPath), true);
                                    if (isset($json['client_email'])) {
                                        $set('service_account_email', $json['client_email']);
                                    }
                                }
                            })
                            ->live(),
                            
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
                            Action::make('test_connection')
                                ->label('Test Connection')
                                ->icon('heroicon-o-signal')
                                ->action(function () {
                                    $this->testConnection();
                                })
                                ->visible(fn (Get $get) => 
                                    $get('enabled') && 
                                    $get('credentials_path')
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
                                    $get('credentials_path')
                                ),
                                
                            Action::make('view_sitemaps')
                                ->label('View Submitted Sitemaps')
                                ->icon('heroicon-o-list-bullet')
                                ->action(function () {
                                    $this->viewSitemaps();
                                })
                                ->visible(fn (Get $get) => 
                                    $get('enabled') && 
                                    $get('credentials_path')
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
        
        // Convert relative path to absolute if needed
        $credentialsPath = $data['credentials_path'] ?? '';
        if ($credentialsPath && !str_starts_with($credentialsPath, '/')) {
            $credentialsPath = storage_path($credentialsPath);
        }
        
        // Update .env file with the new values
        $envUpdates = [
            'GOOGLE_SEARCH_CONSOLE_ENABLED' => $data['enabled'] ? 'true' : 'false',
            'GOOGLE_SEARCH_CONSOLE_SITE_URL' => $data['site_url'],
            'GOOGLE_APPLICATION_CREDENTIALS' => $credentialsPath,
            'GOOGLE_SERVICE_ACCOUNT_EMAIL' => $data['service_account_email'] ?? '',
        ];
        
        $this->updateEnvFile($envUpdates);
        
        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
    
    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        $envContent = File::get($envPath);
        
        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}=\"{$value}\"";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }
        
        File::put($envPath, $envContent);
        
        // Clear config cache
        if (function_exists('artisan')) {
            artisan('config:clear');
        }
    }
    
    protected function testConnection(): void
    {
        try {
            // Get current form data
            $data = $this->form->getState();
            
            // Convert relative path to absolute if needed
            $credentialsPath = $data['credentials_path'] ?? '';
            if ($credentialsPath && !str_starts_with($credentialsPath, '/')) {
                $credentialsPath = storage_path($credentialsPath);
            }
            
            // Temporarily set config values for testing
            config([
                'url-manager.google_search_console.enabled' => true,
                'url-manager.google_search_console.credentials_path' => $credentialsPath,
                'url-manager.google_search_console.site_url' => $data['site_url'],
            ]);
            
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
            
            // Convert relative path to absolute if needed
            $credentialsPath = $data['credentials_path'] ?? '';
            if ($credentialsPath && !str_starts_with($credentialsPath, '/')) {
                $credentialsPath = storage_path($credentialsPath);
            }
            
            // Temporarily set config values for submission
            config([
                'url-manager.google_search_console.enabled' => true,
                'url-manager.google_search_console.credentials_path' => $credentialsPath,
                'url-manager.google_search_console.site_url' => $data['site_url'],
            ]);
            
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
            
            // Convert relative path to absolute if needed
            $credentialsPath = $data['credentials_path'] ?? '';
            if ($credentialsPath && !str_starts_with($credentialsPath, '/')) {
                $credentialsPath = storage_path($credentialsPath);
            }
            
            // Temporarily set config values
            config([
                'url-manager.google_search_console.enabled' => true,
                'url-manager.google_search_console.credentials_path' => $credentialsPath,
                'url-manager.google_search_console.site_url' => $data['site_url'],
            ]);
            
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
}