<?php

namespace RayzenAI\UrlManager\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RayzenAI\UrlManager\Models\Url;

class UrlStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUrls = Url::count();
        $activeUrls = Url::where('status', Url::STATUS_ACTIVE)->count();
        $redirects = Url::where('status', Url::STATUS_REDIRECT)->count();
        $totalVisits = Url::sum('visits');
        $todayVisits = Url::whereDate('last_visited_at', today())->count();
        $inactiveUrls = Url::where('status', Url::STATUS_INACTIVE)->count();

        return [
            Stat::make('Total URLs', number_format($totalUrls))
                ->description($activeUrls . ' active, ' . $inactiveUrls . ' inactive')
                ->descriptionIcon('heroicon-m-link')
                ->color('primary'),
            
            Stat::make('Redirects', number_format($redirects))
                ->description('301 & 302 redirects')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('warning'),
            
            Stat::make('Total Visits', $this->formatNumber($totalVisits))
                ->description($todayVisits . ' today')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success'),
        ];
    }

    protected function formatNumber($number): string
    {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        } else {
            return number_format($number);
        }
    }
}