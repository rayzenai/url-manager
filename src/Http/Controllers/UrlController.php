<?php

namespace RayzenAI\UrlManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RayzenAI\UrlManager\Models\Url;

class UrlController extends Controller
{
    /**
     * Handle incoming URL requests
     */
    public function handle(Request $request, ?string $slug = null)
    {
        // Get the full path from the request
        $path = $slug ?? $request->path();

        // Remove leading slash if present
        $path = ltrim($path, '/');

        // Try to find by exact slug match
        $url = Url::where('slug', $path)->first();

        if (! $url) {
            abort(404);
        }

        // Handle different URL statuses
        switch ($url->status) {
            case Url::STATUS_REDIRECT:
                return $this->handleRedirect($url);

            case Url::STATUS_INACTIVE:
                abort(404);

            case Url::STATUS_ACTIVE:
            default:
                return $this->handleActive($url);
        }
    }

    /**
     * Handle active URLs
     */
    protected function handleActive(Url $url)
    {
        // Get the related model
        $model = $url->urable;

        if (! $model) {
            abort(404);
        }

        // Record visit using the centralized service
        \RayzenAI\UrlManager\Services\VisitTracker::trackVisit($url);

        // Return view based on type
        $viewName = $this->getViewName($url->type);
        
        if (view()->exists($viewName)) {
            return view($viewName, [
                'url' => $url,
                'model' => $model,
                $url->type => $model, // e.g., 'entity' => $model
            ]);
        }

        // Fallback to generic view
        if (view()->exists('url-manager::show')) {
            return view('url-manager::show', [
                'url' => $url,
                'model' => $model,
            ]);
        }

        abort(404, 'View not found for URL type: ' . $url->type);
    }

    /**
     * Handle redirects
     */
    protected function handleRedirect(Url $url)
    {
        if (! $url->redirect_to) {
            abort(404);
        }

        // Normalize the redirect target by removing leading slash
        // This ensures consistency even if data was entered manually
        $normalizedTarget = ltrim($url->redirect_to, '/');

        // Find the target URL
        $targetUrl = Url::where('slug', $normalizedTarget)
            ->where('status', Url::STATUS_ACTIVE)
            ->first();

        if (! $targetUrl) {
            // If no URL record exists, try direct redirect
            // Add leading slash for proper URL formation
            return redirect('/' . $normalizedTarget, $url->redirect_code);
        }

        return redirect($targetUrl->getAbsoluteUrl(), $url->redirect_code);
    }

    /**
     * Get view name for URL type
     */
    protected function getViewName(string $type): string
    {
        // First check for custom views in the application
        $customView = 'url.' . $type;
        if (view()->exists($customView)) {
            return $customView;
        }

        // Then check for package views
        $packageView = 'url-manager::' . $type;
        if (view()->exists($packageView)) {
            return $packageView;
        }

        // Default fallback
        return 'url-manager::show';
    }

    /**
     * Generate sitemap
     */
    public function sitemap()
    {
        if (!config('url-manager.sitemap.enabled', true)) {
            abort(404);
        }

        $urls = Url::active()
            ->with('urable')
            ->get();

        $content = view('url-manager::sitemap', compact('urls'))->render();

        return response($content)
            ->header('Content-Type', 'application/xml');
    }
}