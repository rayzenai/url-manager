<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use RayzenAI\UrlManager\Models\Url;

class UrlForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('URL Management')
                ->tabs([
                    Tab::make('Basic Information')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('slug')
                                        ->label('URL Slug')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->helperText('The URL path (e.g., "products/my-product")'),
                                    
                                    Select::make('type')
                                        ->label('URL Type')
                                        ->options(Url::getTypes())
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(fn ($state, callable $set) => 
                                            $state === 'redirect' ? $set('status', Url::STATUS_REDIRECT) : null
                                        ),
                                ]),
                            
                            Grid::make(2)
                                ->schema([
                                    Select::make('status')
                                        ->label('Status')
                                        ->options(Url::getStatuses())
                                        ->required()
                                        ->default(Url::STATUS_ACTIVE)
                                        ->reactive(),
                                    
                                    TextInput::make('urable_type')
                                        ->label('Model Type')
                                        ->helperText('Full class name of the related model')
                                        ->visible(fn ($get) => $get('type') !== 'redirect'),
                                ]),
                            
                            TextInput::make('urable_id')
                                ->label('Model ID')
                                ->numeric()
                                ->visible(fn ($get) => $get('type') !== 'redirect'),
                        ]),
                    
                    Tab::make('Redirect Settings')
                        ->visible(fn ($get) => $get('status') === Url::STATUS_REDIRECT || $get('type') === 'redirect')
                        ->schema([
                            TextInput::make('redirect_to')
                                ->label('Redirect To')
                                ->required(fn ($get) => $get('status') === Url::STATUS_REDIRECT)
                                ->helperText('The target URL slug or full URL'),
                            
                            Select::make('redirect_code')
                                ->label('Redirect Code')
                                ->options([
                                    301 => '301 - Permanent Redirect',
                                    302 => '302 - Temporary Redirect',
                                    307 => '307 - Temporary Redirect (Method Preserved)',
                                    308 => '308 - Permanent Redirect (Method Preserved)',
                                ])
                                ->default(301)
                                ->required(fn ($get) => $get('status') === Url::STATUS_REDIRECT),
                        ]),
                    
                    Tab::make('Statistics')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('visits')
                                        ->label('Total Visits')
                                        ->numeric()
                                        ->disabled()
                                        ->default(0),
                                    
                                    TextInput::make('last_visited_at')
                                        ->label('Last Visited')
                                        ->disabled(),
                                ]),
                            
                            TextInput::make('last_modified_at')
                                ->label('Last Modified')
                                ->disabled(),
                        ]),
                ]),
        ]);
    }
}