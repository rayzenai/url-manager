<?php

namespace RayzenAI\UrlManager\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService
{
    /**
     * Submit sitemap to Google using ping service
     * This is the simplest method and doesn't require API credentials
     */
    public static function pingGoogleSitemap(?string $sitemapUrl = null): array
    {
        try {
            // Use provided URL or default to the application's sitemap
            $sitemapUrl = $sitemapUrl ?: url('/sitemap.xml');
            
            // Google's sitemap ping endpoint
            $pingUrl = 'https://www.google.com/ping';
            
            // Make the request to Google
            $response = Http::get($pingUrl, [
                'sitemap' => $sitemapUrl
            ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Sitemap successfully submitted to Google',
                    'sitemap_url' => $sitemapUrl,
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to submit sitemap to Google',
                'error' => $response->body(),
            ];
            
        } catch (Exception $e) {
            Log::error('Google sitemap submission failed', [
                'error' => $e->getMessage(),
                'sitemap_url' => $sitemapUrl,
            ]);
            
            return [
                'success' => false,
                'message' => 'Error submitting sitemap: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Submit sitemap to Bing using ping service
     * Bonus: Also submit to Bing for better coverage
     */
    public static function pingBingSitemap(?string $sitemapUrl = null): array
    {
        try {
            // Use provided URL or default to the application's sitemap
            $sitemapUrl = $sitemapUrl ?: url('/sitemap.xml');
            
            // Bing's sitemap ping endpoint
            $pingUrl = 'https://www.bing.com/ping';
            
            // Make the request to Bing
            $response = Http::get($pingUrl, [
                'sitemap' => $sitemapUrl
            ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Sitemap successfully submitted to Bing',
                    'sitemap_url' => $sitemapUrl,
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to submit sitemap to Bing',
                'error' => $response->body(),
            ];
            
        } catch (Exception $e) {
            Log::error('Bing sitemap submission failed', [
                'error' => $e->getMessage(),
                'sitemap_url' => $sitemapUrl,
            ]);
            
            return [
                'success' => false,
                'message' => 'Error submitting sitemap: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Submit sitemap to multiple search engines
     */
    public static function submitToAllSearchEngines(?string $sitemapUrl = null): array
    {
        $sitemapUrl = $sitemapUrl ?: url('/sitemap.xml');
        $results = [];
        
        // Submit to Google
        $googleResult = self::pingGoogleSitemap($sitemapUrl);
        $results['google'] = $googleResult;
        
        // Submit to Bing
        $bingResult = self::pingBingSitemap($sitemapUrl);
        $results['bing'] = $bingResult;
        
        // Check if all submissions were successful
        $allSuccessful = collect($results)->every(fn($result) => $result['success']);
        
        return [
            'success' => $allSuccessful,
            'results' => $results,
            'sitemap_url' => $sitemapUrl,
        ];
    }
    
    /**
     * Advanced method using Google Search Console API
     * Requires API credentials and more setup
     */
    public static function submitUsingApi(string $siteUrl, string $sitemapUrl, array $credentials): array
    {
        // This would require Google API Client library
        // For now, we'll use the simpler ping method above
        // This is a placeholder for future implementation
        
        return [
            'success' => false,
            'message' => 'API submission not yet implemented. Use ping method instead.',
        ];
    }
}