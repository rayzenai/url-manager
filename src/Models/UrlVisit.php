<?php

namespace RayzenAI\UrlManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Agent\Agent;

class UrlVisit extends Model
{
    /**
     * Disable updated_at timestamp
     */
    const UPDATED_AT = null;
    
    protected $fillable = [
        'url_id',
        'ip_address',
        'browser',
        'browser_version',
        'platform',
        'device',
        'user_id',
        'referer',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the URL this visit belongs to
     */
    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }

    /**
     * Get the user who made this visit
     */
    public function user(): BelongsTo
    {
        $userModel = config('url-manager.user_model', 'App\Models\User');
        return $this->belongsTo($userModel);
    }

    /**
     * Create a visit record from request data
     */
    public static function createFromRequest(Url $url, ?int $userId = null, array $metadata = []): self
    {
        // Get user agent from metadata if available (for queued jobs), otherwise from request
        $userAgent = $metadata['user_agent'] ?? request()->userAgent();
        $ipAddress = $metadata['ip'] ?? request()->ip();
        $referer = $metadata['referer'] ?? request()->header('referer');
        
        // Remove these from metadata to avoid duplication
        unset($metadata['user_agent'], $metadata['ip'], $metadata['referer']);
        
        $agent = new Agent();
        if ($userAgent) {
            $agent->setUserAgent($userAgent);
        }

        $data = [
            'url_id' => $url->id,
            'ip_address' => $ipAddress,
            'user_id' => $userId,
            'referer' => $referer,
            'meta' => $metadata,
        ];

        // Parse user agent for browser/device info
        if ($agent->isDesktop()) {
            $data['device'] = 'desktop';
        } elseif ($agent->isTablet()) {
            $data['device'] = 'tablet';
        } elseif ($agent->isMobile()) {
            $data['device'] = 'mobile';
        } else {
            $data['device'] = 'unknown';
        }

        $data['browser'] = substr($agent->browser() ?: 'Unknown', 0, 50);
        $data['browser_version'] = substr($agent->version($agent->browser()) ?: '', 0, 20);
        $data['platform'] = substr($agent->platform() ?: 'Unknown', 0, 50);

        return self::create($data);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for authenticated visits only
     */
    public function scopeAuthenticated($query)
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope for anonymous visits only
     */
    public function scopeAnonymous($query)
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope for specific device type
     */
    public function scopeDevice($query, string $device)
    {
        return $query->where('device', $device);
    }

    /**
     * Scope for specific browser
     */
    public function scopeBrowser($query, string $browser)
    {
        return $query->where('browser', $browser);
    }

    /**
     * Get unique visitors count for a URL
     */
    public static function uniqueVisitors(int $urlId, $startDate = null, $endDate = null)
    {
        $query = self::where('url_id', $urlId);

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        return $query->distinct('ip_address')->count('ip_address');
    }

    /**
     * Get visit statistics for a URL
     */
    public static function getStatistics(int $urlId, $startDate = null, $endDate = null): array
    {
        $query = self::where('url_id', $urlId);

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $visits = $query->get();

        return [
            'total_visits' => $visits->count(),
            'unique_visitors' => $visits->unique('ip_address')->count(),
            'authenticated_visits' => $visits->whereNotNull('user_id')->count(),
            'anonymous_visits' => $visits->whereNull('user_id')->count(),
            'devices' => [
                'desktop' => $visits->where('device', 'desktop')->count(),
                'mobile' => $visits->where('device', 'mobile')->count(),
                'tablet' => $visits->where('device', 'tablet')->count(),
            ],
            'top_browsers' => $visits->groupBy('browser')
                ->map(function ($group) {
                    return $group->count();
                })
                ->sortDesc()
                ->take(5),
            'top_platforms' => $visits->groupBy('platform')
                ->map(function ($group) {
                    return $group->count();
                })
                ->sortDesc()
                ->take(5),
            'top_referers' => $visits->whereNotNull('referer')
                ->groupBy('referer')
                ->map(function ($group) {
                    return $group->count();
                })
                ->sortDesc()
                ->take(10),
        ];
    }
}