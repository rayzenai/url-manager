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
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getColumns(): int
    {
        return 3;
    }

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

        // Top URL this week
        $topUrl = UrlVisit::where('created_at', '>=', $lastWeek)
            ->select('url_id', DB::raw('count(*) as count'))
            ->with('url:id,slug')
            ->groupBy('url_id')
            ->orderBy('count', 'desc')
            ->first();
        
        // Top referrer
        $topReferrer = UrlVisit::where('created_at', '>=', $lastWeek)
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->select('referer', DB::raw('count(*) as count'))
            ->groupBy('referer')
            ->orderBy('count', 'desc')
            ->first();
        
        // User engagement
        $authenticatedVisits = UrlVisit::where('created_at', '>=', $lastWeek)
            ->whereNotNull('user_id')
            ->count();
        $anonymousVisits = $weekVisits - $authenticatedVisits;
        $authPercent = $weekVisits > 0 ? round(($authenticatedVisits / $weekVisits) * 100) : 0;
        
        // Peak hour today
        $peakHour = UrlVisit::whereDate('created_at', today())
            ->select(DB::raw('EXTRACT(HOUR FROM created_at)::integer as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->first();
        
        $peakHourFormatted = $peakHour ? 
            Carbon::today()->setHour((int)$peakHour->hour)->format('g A') : 'N/A';
        
        // Bounce rate (single page visits) - visitors who only viewed one URL
        $visitorPageViews = UrlVisit::where('created_at', '>=', $lastWeek)
            ->select('ip_address', DB::raw('count(DISTINCT url_id) as page_count'))
            ->groupBy('ip_address')
            ->get();
        
        $singlePageVisitors = $visitorPageViews->where('page_count', 1)->count();
        $totalVisitors = $visitorPageViews->count();
        $bounceRate = $totalVisitors > 0 ? 
            round(($singlePageVisitors / $totalVisitors) * 100) : 0;

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
            
            Stat::make('Top Page', $topUrl && $topUrl->url ? 
                    (strlen($topUrl->url->slug) > 30 ? 
                        substr($topUrl->url->slug, 0, 30) . '...' : 
                        $topUrl->url->slug) : 'N/A')
                ->description(($topUrl->count ?? 0) . ' visits this week')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),
            
            Stat::make('Top Referrer', $topReferrer ? 
                    parse_url($topReferrer->referer, PHP_URL_HOST) ?? 'Direct' : 'Direct')
                ->description(($topReferrer->count ?? 0) . ' referrals')
                ->descriptionIcon('heroicon-m-arrow-top-right-on-square')
                ->color('warning'),
            
            Stat::make('User Engagement', $authPercent . '% Logged In')
                ->description($authenticatedVisits . ' authenticated visits')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($authPercent > 30 ? 'success' : 'danger'),
            
            Stat::make('Peak Hour Today', $peakHourFormatted)
                ->description(($peakHour->count ?? 0) . ' visits at peak')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
            
            Stat::make('Bounce Rate', $bounceRate . '%')
                ->description($singlePageVisitors . ' single-page visits')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color($bounceRate < 40 ? 'success' : ($bounceRate < 70 ? 'warning' : 'danger')),
        ];
    }

    protected function getHourlyChart(): array
    {
        // Get hourly visits for today
        $hourlyVisits = UrlVisit::whereDate('created_at', today())
            ->select(DB::raw('EXTRACT(HOUR FROM created_at)::integer as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->get()
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