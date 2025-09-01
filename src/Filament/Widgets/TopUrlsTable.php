<?php

namespace RayzenAI\UrlManager\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use RayzenAI\UrlManager\Models\Url;

class TopUrlsTable extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Most Visited URLs';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Url::query()
                    ->where('status', Url::STATUS_ACTIVE)
                    ->orderBy('visits', 'desc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('slug')
                    ->label('URL')
                    ->searchable()
                    ->limit(50)
                    ->url(fn (Url $record): string => $record->getAbsoluteUrl())
                    ->openUrlInNewTab(),
                
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'entity' => 'success',
                        'category' => 'info',
                        'redirect' => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('visits')
                    ->numeric()
                    ->sortable()
                    ->color('success'),
                
                TextColumn::make('last_visited_at')
                    ->dateTime()
                    ->sortable()
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}