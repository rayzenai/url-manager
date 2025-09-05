<?php

namespace RayzenAI\UrlManager\Filament\Resources;

use RayzenAI\UrlManager\Filament\Resources\UrlVisitResource\Pages;
use RayzenAI\UrlManager\Filament\Resources\UrlVisitResource\Widgets;
use RayzenAI\UrlManager\Models\UrlVisit;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use BackedEnum;
use UnitEnum;

class UrlVisitResource extends Resource
{
    protected static ?string $model = UrlVisit::class;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $modelLabel = 'URL Visit';
    
    protected static ?string $pluralModelLabel = 'URL Visits';
    
    public static function getNavigationGroup(): ?string
    {
        return config('url-manager.filament.navigation_group', 'System');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url.slug')
                    ->label('URL')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->url->slug),
                    
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                    
                TextColumn::make('country_code')
                    ->label('Country')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->country_flag ? $record->country_flag . ' ' . $record->country_name : '-'
                    )
                    ->tooltip(fn ($record) => $record->country_name)
                    ->toggleable(),
                    
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->default('Anonymous'),
                    
                TextColumn::make('browser')
                    ->label('Browser')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $state . ($record->browser_version ? ' ' . $record->browser_version : '')
                    )
                    ->icon(fn ($state) => match (true) {
                        str_contains(strtolower($state), 'android') => 'heroicon-o-device-phone-mobile',
                        str_contains(strtolower($state), 'ios') => 'heroicon-o-device-phone-mobile',
                        str_contains(strtolower($state), 'app') => 'heroicon-o-device-phone-mobile',
                        str_contains(strtolower($state), 'chrome') => 'heroicon-o-globe-alt',
                        str_contains(strtolower($state), 'safari') => 'heroicon-o-globe-alt',
                        str_contains(strtolower($state), 'firefox') => 'heroicon-o-globe-alt',
                        str_contains(strtolower($state), 'edge') => 'heroicon-o-globe-alt',
                        default => 'heroicon-o-computer-desktop',
                    }),
                    
                TextColumn::make('platform')
                    ->label('OS')
                    ->sortable(),
                    
                TextColumn::make('device')
                    ->label('Device')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'desktop' => 'success',
                        'mobile' => 'warning',
                        'tablet' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'desktop' => 'heroicon-o-computer-desktop',
                        'mobile' => 'heroicon-o-device-phone-mobile',
                        'tablet' => 'heroicon-o-device-tablet',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                    
                TextColumn::make('referer')
                    ->label('Referrer')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->referer)
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('created_at')
                    ->label('Visit Time')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(fn () => 
                        UrlVisit::whereNotNull('country_code')
                            ->distinct()
                            ->pluck('country_code')
                            ->mapWithKeys(fn ($code) => [
                                $code => (new UrlVisit(['country_code' => $code]))->country_flag . ' ' . (new UrlVisit(['country_code' => $code]))->country_name
                            ])
                            ->toArray()
                    )
                    ->searchable(),
                    
                SelectFilter::make('device')
                    ->options([
                        'desktop' => 'Desktop',
                        'mobile' => 'Mobile',
                        'tablet' => 'Tablet',
                    ]),
                    
                Filter::make('authenticated')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('user_id'))
                    ->label('Authenticated Users'),
                    
                Filter::make('anonymous')
                    ->query(fn (Builder $query): Builder => $query->whereNull('user_id'))
                    ->label('Anonymous Visitors'),
                    
                Filter::make('today')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereDate('created_at', Carbon::today())
                    ),
                    
                Filter::make('last_7_days')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('created_at', '>=', Carbon::now()->subDays(7))
                    ),
                    
                Filter::make('last_30_days')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('created_at', '>=', Carbon::now()->subDays(30))
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUrlVisits::route('/'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function getWidgets(): array
    {
        return [
            Widgets\UrlVisitStats::class,
        ];
    }
}