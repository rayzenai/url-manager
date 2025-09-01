<?php

namespace RayzenAI\UrlManager\Filament\Resources\Urls\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use RayzenAI\UrlManager\Models\Url;

class UrlsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('URL')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(50),
                
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'redirect' => 'warning',
                        'entity' => 'success',
                        'category' => 'info',
                        default => 'gray',
                    }),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Url::STATUS_ACTIVE => 'success',
                        Url::STATUS_REDIRECT => 'warning',
                        Url::STATUS_INACTIVE => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('redirect_to')
                    ->label('Redirects To')
                    ->limit(30)
                    ->visible(fn ($record) => $record?->status === Url::STATUS_REDIRECT)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('redirect_code')
                    ->label('Code')
                    ->visible(fn ($record) => $record?->status === Url::STATUS_REDIRECT)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('visits')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('last_visited_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('last_modified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(Url::getTypes())
                    ->placeholder('All Types'),
                
                SelectFilter::make('status')
                    ->options(Url::getStatuses())
                    ->placeholder('All Statuses'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('visit')
                    ->label('Visit')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Url $record) => $record->getAbsoluteUrl())
                    ->openUrlInNewTab()
                    ->color('gray'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => Url::STATUS_ACTIVE])),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => Url::STATUS_INACTIVE])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}