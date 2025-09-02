<?php

namespace RayzenAI\UrlManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RayzenAI\UrlManager\Services\VisitTracker;

class TrackUrlVisits
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Track visit after response is generated
        VisitTracker::trackVisitByPath($request);
        
        return $response;
    }
}