<?php

namespace RayzenAI\UrlManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;

class UrlVisit extends Model
{
    /**
     * Disable updated_at timestamp
     */
    const UPDATED_AT = null;
    
    protected $fillable = [
        'url_id',
        'ip_address',
        'country_code',
        'browser',
        'browser_version',
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

        // Resolve country code from IP address
        try {
            if ($ipAddress && $ipAddress !== 'UNKNOWN' && filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                $location = Location::get($ipAddress);
                if ($location && $location->countryCode) {
                    $data['country_code'] = $location->countryCode;
                }
            }
        } catch (\Exception $e) {
            // Silently fail if location resolution fails
        }

        // Check for mobile app source parameter first (for API calls)
        $source = request()->input('source');
        $isMobileApp = in_array($source, ['android', 'ios']);
        
        // Parse user agent for browser/device info
        if ($isMobileApp) {
            // API call from mobile app
            $data['device'] = 'mobile';
            $data['browser'] = ucfirst($source) . ' App';
            $data['browser_version'] = '';
            
            // Store source in metadata
            $data['meta'] = array_merge($data['meta'] ?? [], ['source' => $source]);
        } else {
            // Regular web/browser detection
            if ($agent->isDesktop()) {
                $data['device'] = 'desktop';
            } elseif ($agent->isTablet()) {
                $data['device'] = 'tablet';
            } elseif ($agent->isMobile()) {
                $data['device'] = 'mobile';
            } else {
                // Check User-Agent for Flutter/Dart or other mobile app frameworks
                if ($userAgent && (
                    stripos($userAgent, 'flutter') !== false ||
                    stripos($userAgent, 'dart') !== false ||
                    stripos($userAgent, 'okhttp') !== false || // Common Android HTTP client
                    stripos($userAgent, 'alamofire') !== false || // Common iOS HTTP client
                    stripos($userAgent, 'react-native') !== false ||
                    stripos($userAgent, 'php-native') !== false ||
                    stripos($userAgent, 'expo') !== false
                )) {
                    $data['device'] = 'mobile';
                    $data['browser'] = 'Mobile App';
                } else {
                    $data['device'] = 'unknown';
                }
            }
            
            // Only set browser info if not already set
            if (!isset($data['browser'])) {
                $data['browser'] = substr($agent->browser() ?: 'Unknown', 0, 50);
                $data['browser_version'] = substr($agent->version($agent->browser()) ?: '', 0, 20);
            }
        }

        return self::create($data);
    }

    /**
     * Get country flag emoji from country code
     */
    public function getCountryFlagAttribute(): ?string
    {
        if (!$this->country_code) {
            return null;
        }
        
        // Convert country code to flag emoji using regional indicator symbols
        $flag = '';
        $code = strtoupper($this->country_code);
        for ($i = 0; $i < strlen($code); $i++) {
            $flag .= mb_chr(ord($code[$i]) + 127397, 'UTF-8');
        }
        
        return $flag;
    }

    /**
     * Get country name from country code
     */
    public function getCountryNameAttribute(): ?string
    {
        if (!$this->country_code) {
            return null;
        }
        
        // Common country codes - expand as needed
        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'NP' => 'Nepal',
            'IN' => 'India',
            'CN' => 'China',
            'JP' => 'Japan',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'BR' => 'Brazil',
            'RU' => 'Russia',
            'KR' => 'South Korea',
            'MX' => 'Mexico',
            'ID' => 'Indonesia',
            'NL' => 'Netherlands',
            'SA' => 'Saudi Arabia',
            'TR' => 'Turkey',
            'CH' => 'Switzerland',
            'SE' => 'Sweden',
            'PL' => 'Poland',
            'BE' => 'Belgium',
            'AR' => 'Argentina',
            'NO' => 'Norway',
            'AT' => 'Austria',
            'AE' => 'United Arab Emirates',
            'DK' => 'Denmark',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'TH' => 'Thailand',
            'EG' => 'Egypt',
            'PH' => 'Philippines',
            'FI' => 'Finland',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'VN' => 'Vietnam',
            'CZ' => 'Czech Republic',
            'RO' => 'Romania',
            'PT' => 'Portugal',
            'NZ' => 'New Zealand',
            'GR' => 'Greece',
            'UA' => 'Ukraine',
            'HU' => 'Hungary',
            'ZA' => 'South Africa',
            'LK' => 'Sri Lanka',
        ];
        
        return $countries[strtoupper($this->country_code)] ?? strtoupper($this->country_code);
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
            'top_countries' => $visits->whereNotNull('country_code')
                ->groupBy('country_code')
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