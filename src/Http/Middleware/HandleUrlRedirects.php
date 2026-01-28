<?php

namespace RayzenAI\UrlManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RayzenAI\UrlManager\Models\Url;
use Symfony\Component\HttpFoundation\Response;

class HandleUrlRedirects
{
    /**
     * Handle an incoming request and check for URL redirects.
     * This runs BEFORE route model binding, so we can redirect old URLs
     * before they hit specific routes and cause 404s.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only handle GET requests
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Get the current path without leading slash
        $path = ltrim($request->path(), '/');

        // Look up the URL in the database
        $url = Url::where('slug', $path)
            ->where('status', Url::STATUS_REDIRECT)
            ->first();

        // If a redirect exists, perform it
        if ($url && $url->redirect_to) {
            $redirectCode = $url->redirect_code ?? 301;
            $redirectPath = '/'.ltrim($url->redirect_to, '/');

            // Preserve query string if present
            if ($request->getQueryString()) {
                $redirectPath .= '?'.$request->getQueryString();
            }

            return redirect($redirectPath, $redirectCode);
        }

        // No redirect found, continue with normal routing
        return $next($request);
    }
}
