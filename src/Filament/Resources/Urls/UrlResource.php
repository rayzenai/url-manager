<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use RayzenAI\UrlManager\Filament\Resources\Urls\Pages\CreateUrl;
use RayzenAI\UrlManager\Filament\Resources\Urls\Pages\EditUrl;
use RayzenAI\UrlManager\Filament\Resources\Urls\Pages\ListUrls;
use RayzenAI\UrlManager\Filament\Resources\Urls\Schemas\UrlForm;
use RayzenAI\UrlManager\Filament\Resources\Urls\Tables\UrlsTable;
use RayzenAI\UrlManager\Models\Url;

class UrlResource extends Resource
{
    protected static ?string $model = Url::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static string|\UnitEnum|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 100;
    
    public static function getNavigationLabel(): string
    {
        return 'URLs';
    }
    
    public static function getPluralLabel(): string
    {
        return 'URLs';
    }
    
    public static function getLabel(): string
    {
        return 'URL';
    }

    public static function form(Schema $schema): Schema
    {
        return UrlForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UrlsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUrls::route('/'),
            'create' => CreateUrl::route('/create'),
            'edit' => EditUrl::route('/{record}/edit'),
        ];
    }
}