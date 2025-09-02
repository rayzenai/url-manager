<?php

namespace RayzenAI\UrlManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RayzenAI\UrlManager\Jobs\RecordUrlVisit;
use RayzenAI\UrlManager\Models\Url;

class TrackUrlVisits
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Track visit after response is generated
        if (config('url-manager.track_visits', true)) {
            $this->trackVisit($request);
        }
        
        return $response;
    }
    
    /**
     * Track the visit for the current URL
     */
    protected function trackVisit(Request $request): void
    {
        $path = ltrim($request->path(), '/');
        
        // Find URL record by slug
        $url = Url::where('slug', $path)
            ->where('status', Url::STATUS_ACTIVE)
            ->first();
            
        if ($url) {
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
    }
}