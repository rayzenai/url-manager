<?php

namespace RayzenAI\UrlManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RayzenAI\UrlManager\Models\Url;
use RayzenAI\UrlManager\Models\UrlVisit;
use Carbon\Carbon;

class UrlAnalyticsController extends Controller
{
    /**
     * Display analytics dashboard
     */
    public function index(Request $request)
    {
        $dateRange = $request->get('range', '7days');
        $urlId = $request->get('url_id');
        
        list($startDate, $endDate) = $this->getDateRange($dateRange);
        
        // Get overall statistics
        $stats = $this->getOverallStats($startDate, $endDate, $urlId);
        
        // Get top URLs
        $topUrls = $this->getTopUrls($startDate, $endDate, 10);
        
        // Get hourly distribution
        $hourlyDistribution = $this->getHourlyDistribution($startDate, $endDate, $urlId);
        
        // Get recent visits
        $recentVisits = $this->getRecentVisits($urlId, 50);
        
        return view('url-manager::analytics.index', compact(
            'stats',
            'topUrls',
            'hourlyDistribution',
            'recentVisits',
            'dateRange',
            'urlId',
            'startDate',
            'endDate'
        ));
    }
    
    /**
     * Show analytics for a specific URL
     */
    public function show(Request $request, Url $url)
    {
        $dateRange = $request->get('range', '7days');
        list($startDate, $endDate) = $this->getDateRange($dateRange);
        
        // Get URL-specific statistics
        $stats = UrlVisit::getStatistics($url->id, $startDate, $endDate);
        
        // Get daily visits chart data
        $dailyVisits = $this->getDailyVisits($url->id, $startDate, $endDate);
        
        // Get recent visits for this URL
        $recentVisits = UrlVisit::where('url_id', $url->id)
            ->with('user')
            ->latest()
            ->take(100)
            ->get();
        
        // Get the model associated with this URL
        $model = $url->urable;
        
        return view('url-manager::analytics.show', compact(
            'url',
            'model',
            'stats',
            'dailyVisits',
            'recentVisits',
            'dateRange',
            'startDate',
            'endDate'
        ));
    }
    
    /**
     * Export analytics data
     */
    public function export(Request $request)
    {
        $dateRange = $request->get('range', '30days');
        $urlId = $request->get('url_id');
        $format = $request->get('format', 'csv');
        
        list($startDate, $endDate) = $this->getDateRange($dateRange);
        
        $query = UrlVisit::with(['url', 'user'])
            ->dateRange($startDate, $endDate);
        
        if ($urlId) {
            $query->where('url_id', $urlId);
        }
        
        $visits = $query->get();
        
        if ($format === 'csv') {
            return $this->exportCsv($visits);
        }
        
        return $this->exportJson($visits);
    }
    
    /**
     * Get date range based on preset
     */
    protected function getDateRange(string $preset): array
    {
        $endDate = Carbon::now()->endOfDay();
        
        switch ($preset) {
            case 'today':
                $startDate = Carbon::today();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case '7days':
                $startDate = Carbon::now()->subDays(7)->startOfDay();
                break;
            case '30days':
                $startDate = Carbon::now()->subDays(30)->startOfDay();
                break;
            case '90days':
                $startDate = Carbon::now()->subDays(90)->startOfDay();
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                break;
            default:
                $startDate = Carbon::now()->subDays(7)->startOfDay();
        }
        
        return [$startDate, $endDate];
    }
    
    /**
     * Get overall statistics
     */
    protected function getOverallStats($startDate, $endDate, $urlId = null)
    {
        $query = UrlVisit::dateRange($startDate, $endDate);
        
        if ($urlId) {
            $query->where('url_id', $urlId);
        }
        
        $visits = $query->get();
        
        return [
            'total_visits' => $visits->count(),
            'unique_visitors' => $visits->unique('ip_address')->count(),
            'authenticated_visits' => $visits->whereNotNull('user_id')->count(),
            'anonymous_visits' => $visits->whereNull('user_id')->count(),
            'desktop_visits' => $visits->where('device', 'desktop')->count(),
            'mobile_visits' => $visits->where('device', 'mobile')->count(),
            'tablet_visits' => $visits->where('device', 'tablet')->count(),
        ];
    }
    
    /**
     * Get top URLs by visits
     */
    protected function getTopUrls($startDate, $endDate, $limit = 10)
    {
        return UrlVisit::with('url')
            ->dateRange($startDate, $endDate)
            ->selectRaw('url_id, COUNT(*) as visit_count, COUNT(DISTINCT ip_address) as unique_visitors')
            ->groupBy('url_id')
            ->orderByDesc('visit_count')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get hourly distribution of visits
     */
    protected function getHourlyDistribution($startDate, $endDate, $urlId = null)
    {
        $query = UrlVisit::dateRange($startDate, $endDate)
            ->selectRaw('EXTRACT(HOUR FROM created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour');
        
        if ($urlId) {
            $query->where('url_id', $urlId);
        }
        
        $data = $query->get()->pluck('count', 'hour');
        
        // Fill in missing hours with 0
        $distribution = [];
        for ($i = 0; $i < 24; $i++) {
            $distribution[$i] = $data->get($i, 0);
        }
        
        return $distribution;
    }
    
    /**
     * Get daily visits for a URL
     */
    protected function getDailyVisits($urlId, $startDate, $endDate)
    {
        return UrlVisit::where('url_id', $urlId)
            ->dateRange($startDate, $endDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as visits, COUNT(DISTINCT ip_address) as unique_visitors')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
    
    /**
     * Get recent visits
     */
    protected function getRecentVisits($urlId = null, $limit = 50)
    {
        $query = UrlVisit::with(['url', 'user'])
            ->latest()
            ->limit($limit);
        
        if ($urlId) {
            $query->where('url_id', $urlId);
        }
        
        return $query->get();
    }
    
    /**
     * Export visits as CSV
     */
    protected function exportCsv($visits)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="url-visits-' . date('Y-m-d') . '.csv"',
        ];
        
        $callback = function() use ($visits) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Date',
                'URL',
                'IP Address',
                'Country',
                'User',
                'Browser',
                'Device',
                'Referer',
            ]);
            
            // Data rows
            foreach ($visits as $visit) {
                fputcsv($file, [
                    $visit->created_at->format('Y-m-d H:i:s'),
                    $visit->url->slug ?? '',
                    $visit->ip_address,
                    $visit->country_code ?? 'Unknown',
                    $visit->user->name ?? 'Anonymous',
                    $visit->browser . ' ' . $visit->browser_version,
                    $visit->device,
                    $visit->referer,
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Export visits as JSON
     */
    protected function exportJson($visits)
    {
        return response()->json([
            'data' => $visits->map(function ($visit) {
                return [
                    'date' => $visit->created_at->toIso8601String(),
                    'url' => $visit->url->slug ?? null,
                    'ip_address' => $visit->ip_address,
                    'country_code' => $visit->country_code,
                    'user' => $visit->user ? [
                        'id' => $visit->user->id,
                        'name' => $visit->user->name,
                    ] : null,
                    'browser' => $visit->browser,
                    'browser_version' => $visit->browser_version,
                    'device' => $visit->device,
                    'referer' => $visit->referer,
                    'meta' => $visit->meta,
                ];
            }),
            'count' => $visits->count(),
            'exported_at' => now()->toIso8601String(),
        ]);
    }
}