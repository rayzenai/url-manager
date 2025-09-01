<?php

namespace RayzenAI\UrlManager\Services;

use RayzenAI\UrlManager\Models\Url;

class UrlManagerService
{
    /**
     * Check for URL redirects based on type and slug
     * 
     * @param string $type The type of URL (e.g., 'product', 'occasion', 'blog')
     * @param string $slug The slug to check
     * @return array|null Returns redirect data if found, null otherwise
     */
    public function checkForRedirect(string $type, string $slug): ?array
    {
        // Build path variations based on type
        $paths = $this->buildPathVariations($type, $slug);
        
        // Query for redirect
        $urlRecord = Url::where(function ($query) use ($paths) {
            foreach ($paths as $path) {
                $query->orWhere('slug', $path);
            }
        })
        ->where('status', Url::STATUS_REDIRECT)
        ->first();
        
        if ($urlRecord && $urlRecord->redirect_to) {
            return [
                'redirect_to' => $urlRecord->redirect_to,
                'code' => $urlRecord->redirect_code ?? 301,
            ];
        }
        
        return null;
    }
    
    /**
     * Build path variations for a given type and slug
     * 
     * @param string $type
     * @param string $slug
     * @return array
     */
    protected function buildPathVariations(string $type, string $slug): array
    {
        $paths = [];
        
        // Handle different types
        switch ($type) {
            case 'product':
                // Check both singular and plural forms
                $paths[] = 'product/' . $slug;
                $paths[] = '/product/' . $slug;
                $paths[] = 'products/' . $slug;
                $paths[] = '/products/' . $slug;
                break;
                
            case 'occasion':
                $paths[] = 'occasion/' . $slug;
                $paths[] = '/occasion/' . $slug;
                $paths[] = 'occasions/' . $slug;
                $paths[] = '/occasions/' . $slug;
                break;
                
            case 'blog':
                $paths[] = 'blog/' . $slug;
                $paths[] = '/blog/' . $slug;
                $paths[] = 'blogs/' . $slug;
                $paths[] = '/blogs/' . $slug;
                break;
                
            default:
                // Generic path construction
                $paths[] = $type . '/' . $slug;
                $paths[] = '/' . $type . '/' . $slug;
                // Try plural form
                $plural = rtrim($type, 's') . 's';
                if ($plural !== $type) {
                    $paths[] = $plural . '/' . $slug;
                    $paths[] = '/' . $plural . '/' . $slug;
                }
                break;
        }
        
        return $paths;
    }
    
    /**
     * Create a redirect response array suitable for API responses
     * 
     * @param string $redirectTo
     * @param int $code
     * @param string|null $message
     * @return array
     */
    public function createRedirectResponse(string $redirectTo, int $code = 301, ?string $message = null): array
    {
        return [
            'meta' => [
                'code' => $code,
                'status' => 'redirect',
            ],
            'data' => [
                'redirect_to' => $redirectTo,
            ],
            'message' => $message ?? 'This resource has been moved',
        ];
    }
}