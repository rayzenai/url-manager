<?php

namespace RayzenAI\UrlManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RayzenAI\UrlManager\Services\VisitTracker;
use RayzenAI\UrlManager\Models\Url;
use RayzenAI\UrlManager\Jobs\RecordUrlVisit;

class TrackingController extends Controller
{
    /**
     * Track a visit to a URL without returning the actual content
     * This endpoint is specifically for frontend caching scenarios
     */
    public function track(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        if (!config('url-manager.track_visits', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Visit tracking is disabled',
            ]);
        }

        $path = $request->input('path');
        
        // Convert API-style paths to URL Manager slugs if needed
        $slug = VisitTracker::convertApiPathToSlug($path);
        
        if (!$slug) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid path',
            ], 400);
        }
        
        // Find URL record by slug
        $url = Url::where('slug', $slug)
            ->where('status', Url::STATUS_ACTIVE)
            ->first();
            
        if (!$url) {
            // Silently fail - the URL might not be tracked
            return response()->json([
                'success' => true,
                'message' => 'Visit recorded',
                'tracked' => false,
            ]);
        }
        
        // Try to authenticate using bearer token if present
        $userId = null;
        if ($request->hasHeader('Authorization')) {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));
            try {
                // Use Sanctum to authenticate the token
                $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
                if ($user) {
                    $userId = $user->id;
                }
            } catch (\Exception $e) {
                // Token authentication failed, continue as guest
            }
        }
        
        // Fall back to session auth if no bearer token
        if (!$userId) {
            $userId = auth()->id();
        }
        
        // Dispatch job to record visit asynchronously
        RecordUrlVisit::dispatch(
            $url,
            $userId,
            [
                'tracked_via' => 'api_endpoint',
                'original_path' => $path,
                'user_agent' => $request->header('X-Original-User-Agent') ?: $request->header('User-Agent'),
                'ip' => $request->header('X-Forwarded-For') ?: $request->ip(),
                'referer' => $request->header('X-Original-Referer') ?: $request->header('Referer'),
            ]
        );
        
        // Fire event for custom handling
        event('url-manager.url.visited', [$url, $url->urable]);

        return response()->json([
            'success' => true,
            'message' => 'Visit recorded',
            'tracked' => true,
        ]);
    }
}