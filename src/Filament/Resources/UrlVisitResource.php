<?php

namespace RayzenAI\UrlManager\Filament\Resources;

use RayzenAI\UrlManager\Filament\Resources\UrlVisitResource\Pages;
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
    
    protected static string | UnitEnum | null $navigationGroup = 'URL Management';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $modelLabel = 'URL Visit';
    
    protected static ?string $pluralModelLabel = 'URL Visits';

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
                    ),
                    
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
}