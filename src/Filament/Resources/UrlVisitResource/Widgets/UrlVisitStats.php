<?php

namespace RayzenAI\UrlManager\Filament\Resources\UrlVisitResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RayzenAI\UrlManager\Models\UrlVisit;
use RayzenAI\UrlManager\Models\Url;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UrlVisitStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastWeek = $now->copy()->subWeek();
        $lastMonth = $now->copy()->subMonth();

        // Today's stats
        $todayVisits = UrlVisit::whereDate('created_at', $today)->count();
        $todayUniqueVisitors = UrlVisit::whereDate('created_at', $today)
            ->distinct('ip_address')
            ->count('ip_address');
        
        // Yesterday's stats for comparison
        $yesterdayVisits = UrlVisit::whereDate('created_at', $yesterday)->count();
        
        // This week's stats
        $weekVisits = UrlVisit::where('created_at', '>=', $lastWeek)->count();
        $weekUniqueVisitors = UrlVisit::where('created_at', '>=', $lastWeek)
            ->distinct('ip_address')
            ->count('ip_address');
        
        // This month's stats
        $monthVisits = UrlVisit::where('created_at', '>=', $lastMonth)->count();
        
        // Device breakdown
        $deviceStats = UrlVisit::where('created_at', '>=', $lastWeek)
            ->select('device', DB::raw('count(*) as count'))
            ->groupBy('device')
            ->pluck('count', 'device')
            ->toArray();
        
        $desktopPercent = isset($deviceStats['desktop']) ? 
            round(($deviceStats['desktop'] / max($weekVisits, 1)) * 100) : 0;
        $mobilePercent = isset($deviceStats['mobile']) ? 
            round(($deviceStats['mobile'] / max($weekVisits, 1)) * 100) : 0;

        // Calculate trend
        $trend = $yesterdayVisits > 0 ? 
            round((($todayVisits - $yesterdayVisits) / $yesterdayVisits) * 100) : 0;
        
        // Top browsers
        $topBrowser = UrlVisit::where('created_at', '>=', $lastWeek)
            ->select('browser', DB::raw('count(*) as count'))
            ->groupBy('browser')
            ->orderBy('count', 'desc')
            ->first();

        return [
            Stat::make('Today\'s Visits', number_format($todayVisits))
                ->description($todayUniqueVisitors . ' unique visitors')
                ->descriptionIcon('heroicon-m-users')
                ->chart($this->getHourlyChart())
                ->color($todayVisits > $yesterdayVisits ? 'success' : 'warning'),
            
            Stat::make('This Week', $this->formatNumber($weekVisits))
                ->description($weekUniqueVisitors . ' unique visitors')
                ->descriptionIcon('heroicon-m-calendar')
                ->chart($this->getDailyChart())
                ->color('info'),
            
            Stat::make('Device Split', $desktopPercent . '% Desktop')
                ->description($mobilePercent . '% Mobile')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('primary'),
            
            Stat::make('Top Browser', $topBrowser->browser ?? 'N/A')
                ->description(($topBrowser->count ?? 0) . ' visits this week')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('secondary'),
        ];
    }

    protected function getHourlyChart(): array
    {
        // Get hourly visits for today
        $hourlyVisits = UrlVisit::whereDate('created_at', today())
            ->select(DB::raw('EXTRACT(HOUR FROM created_at) as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
        
        $chart = [];
        for ($i = 0; $i <= 23; $i++) {
            $chart[] = $hourlyVisits[$i] ?? 0;
        }
        
        return $chart;
    }
    
    protected function getDailyChart(): array
    {
        // Get daily visits for last 7 days
        $dailyVisits = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = UrlVisit::whereDate('created_at', $date)->count();
            $dailyVisits[] = $count;
        }
        
        return $dailyVisits;
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