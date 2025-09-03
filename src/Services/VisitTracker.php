<?php

namespace RayzenAI\UrlManager\Services;

use Illuminate\Http\Request;
use RayzenAI\UrlManager\Jobs\RecordUrlVisit;
use RayzenAI\UrlManager\Models\Url;

class VisitTracker
{
    /**
     * Track a visit for the given URL
     */
    public static function trackVisit(Url $url, ?Request $request = null): void
    {
        if (!config('url-manager.track_visits', true)) {
            return;
        }
        
        $request = $request ?? request();
        
        // Dispatch job to record visit asynchronously
        RecordUrlVisit::dispatch(
            $url,
            auth()->id(),
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ]
        );
        
        // Fire event for custom handling
        event('url-manager.url.visited', [$url, $url->urable]);
    }
    
    /**
     * Track a visit by finding the URL from request path
     */
    public static function trackVisitByPath(Request $request): void
    {
        if (!config('url-manager.track_visits', true)) {
            return;
        }
        
        $path = ltrim($request->path(), '/');
        
        // Convert API path to URL Manager slug format if needed
        $slug = self::convertApiPathToSlug($path);
        
        if (!$slug) {
            return;
        }
        
        // Find URL record by slug
        $url = Url::where('slug', $slug)
            ->where('status', Url::STATUS_ACTIVE)
            ->first();
            
        if ($url) {
            self::trackVisit($url, $request);
        }
    }
    
    /**
     * Convert API path to URL Manager slug format using configured conversions
     */
    public static function convertApiPathToSlug(string $path): ?string
    {
        $conversions = config('url-manager.api_path_conversions', []);
        
        foreach ($conversions as $pattern => $replacement) {
            if (preg_match($pattern, $path)) {
                return preg_replace($pattern, $replacement, $path);
            }
        }
        
        // If no conversion found, return the original path
        return $path;
    }
}