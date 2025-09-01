<?php

namespace RayzenAI\UrlManager\Services;

use Exception;
use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\Webmasters;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService
{
    protected ?Client $client = null;
    protected ?Webmasters $webmastersService = null;
    protected ?SearchConsole $searchConsoleService = null;
    
    /**
     * Initialize Google API client with Service Account credentials
     */
    protected function initializeClient(): void
    {
        if ($this->client !== null) {
            return;
        }
        
        $config = config('url-manager.google_search_console');
        
        if (!$config['enabled']) {
            return;
        }
        
        // Check if service account credentials are configured
        if (!$config['credentials_path'] || !file_exists($config['credentials_path'])) {
            return;
        }
        
        try {
            $this->client = new Client();
            $this->client->setApplicationName('URL Manager - Search Console');
            $this->client->setAuthConfig($config['credentials_path']);
            $this->client->setScopes([
                SearchConsole::WEBMASTERS,
                SearchConsole::WEBMASTERS_READONLY,
            ]);
            
            // Initialize services
            $this->webmastersService = new Webmasters($this->client);
            $this->searchConsoleService = new SearchConsole($this->client);
        } catch (Exception $e) {
            Log::error('Failed to initialize Google Search Console client', [
                'error' => $e->getMessage()
            ]);
        }
    }
    /**
     * Submit sitemap to Google using API if available
     */
    public static function submitGoogleSitemap(?string $sitemapUrl = null): array
    {
        $instance = new static();
        $instance->initializeClient();
        
        // Check if API is configured
        if (!$instance->webmastersService) {
            return [
                'success' => false,
                'message' => 'Google Search Console API is not configured.',
                'info' => 'Please upload Service Account credentials JSON file in your admin panel settings.',
            ];
        }
        
        return $instance->submitSitemapViaApi($sitemapUrl);
    }
    
    /**
     * Submit sitemap using Google Search Console API
     */
    protected function submitSitemapViaApi(?string $sitemapUrl = null): array
    {
        try {
            $sitemapUrl = $sitemapUrl ?: url('/sitemap.xml');
            $siteUrl = config('url-manager.google_search_console.site_url') ?: url('/');
            
            // First, verify the site is added
            $this->verifySiteInSearchConsole($siteUrl);
            
            // Submit the sitemap
            $this->webmastersService->sitemaps->submit($siteUrl, $sitemapUrl);
            
            return [
                'success' => true,
                'message' => 'Sitemap successfully submitted to Google via API',
                'sitemap_url' => $sitemapUrl,
                'method' => 'api',
            ];
        } catch (Exception $e) {
            Log::error('Google Search Console API submission failed', [
                'error' => $e->getMessage(),
                'sitemap_url' => $sitemapUrl,
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to submit sitemap via API: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Verify site is added to Google Search Console
     */
    protected function verifySiteInSearchConsole(string $siteUrl): void
    {
        try {
            // Check if site exists
            $sites = $this->webmastersService->sites->listSites();
            $siteExists = false;
            
            foreach ($sites->getSiteEntry() as $site) {
                if ($site->getSiteUrl() === $siteUrl) {
                    $siteExists = true;
                    break;
                }
            }
            
            // Add site if it doesn't exist
            if (!$siteExists) {
                $this->webmastersService->sites->add($siteUrl);
            }
        } catch (Exception $e) {
            // Site might already exist or we don't have permission
            Log::warning('Could not verify site in Search Console', [
                'site_url' => $siteUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    
    /**
     * Submit sitemap to Bing via API (placeholder for future implementation)
     * Currently Bing requires manual submission through their webmaster tools
     */
    public static function submitBingSitemap(?string $sitemapUrl = null): array
    {
        // Bing doesn't provide a public API for sitemap submission
        // Users need to manually submit through Bing Webmaster Tools
        return [
            'success' => false,
            'message' => 'Bing requires manual sitemap submission through Bing Webmaster Tools.',
            'info' => 'Visit https://www.bing.com/webmasters to submit your sitemap.',
            'sitemap_url' => $sitemapUrl ?: url('/sitemap.xml'),
        ];
    }
    
    /**
     * Submit sitemap to multiple search engines
     */
    public static function submitToAllSearchEngines(?string $sitemapUrl = null): array
    {
        $sitemapUrl = $sitemapUrl ?: url('/sitemap.xml');
        $results = [];
        
        // Submit to Google via API
        $googleResult = self::submitGoogleSitemap($sitemapUrl);
        $results['google'] = $googleResult;
        
        // Note about Bing (no API available)
        $bingResult = self::submitBingSitemap($sitemapUrl);
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
     * Get list of sitemaps for a site
     */
    public function getSitemaps(?string $siteUrl = null): array
    {
        $this->initializeClient();
        
        if (!$this->webmastersService) {
            return [
                'success' => false,
                'message' => 'Google Search Console API not configured',
                'sitemaps' => [],
            ];
        }
        
        try {
            $siteUrl = $siteUrl ?: config('url-manager.google_search_console.site_url') ?: url('/');
            $sitemaps = $this->webmastersService->sitemaps->listSitemaps($siteUrl);
            
            $sitemapList = [];
            foreach ($sitemaps->getSitemap() as $sitemap) {
                $sitemapList[] = [
                    'path' => $sitemap->getPath(),
                    'last_submitted' => $sitemap->getLastSubmitted(),
                    'last_downloaded' => $sitemap->getLastDownloaded(),
                    'errors' => $sitemap->getErrors(),
                    'warnings' => $sitemap->getWarnings(),
                    'is_pending' => $sitemap->getIsPending(),
                    'is_sitemaps_index' => $sitemap->getIsSitemapsIndex(),
                    'contents' => $sitemap->getContents(),
                ];
            }
            
            return [
                'success' => true,
                'sitemaps' => $sitemapList,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get sitemaps: ' . $e->getMessage(),
                'sitemaps' => [],
            ];
        }
    }
    
    /**
     * Delete a sitemap from Google Search Console
     */
    public function deleteSitemap(string $sitemapUrl, ?string $siteUrl = null): array
    {
        $this->initializeClient();
        
        if (!$this->webmastersService) {
            return [
                'success' => false,
                'message' => 'Google Search Console API not configured',
            ];
        }
        
        try {
            $siteUrl = $siteUrl ?: config('url-manager.google_search_console.site_url') ?: url('/');
            $this->webmastersService->sitemaps->delete($siteUrl, $sitemapUrl);
            
            return [
                'success' => true,
                'message' => 'Sitemap deleted successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete sitemap: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get search analytics data
     */
    public function getSearchAnalytics(?string $siteUrl = null, array $options = []): array
    {
        $this->initializeClient();
        
        if (!$this->searchConsoleService) {
            return [
                'success' => false,
                'message' => 'Google Search Console API not configured',
                'data' => [],
            ];
        }
        
        try {
            $siteUrl = $siteUrl ?: config('url-manager.google_search_console.site_url') ?: url('/');
            
            $request = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequest();
            $request->setStartDate($options['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
            $request->setEndDate($options['end_date'] ?? date('Y-m-d'));
            $request->setDimensions($options['dimensions'] ?? ['query', 'page']);
            $request->setRowLimit($options['row_limit'] ?? 100);
            
            $response = $this->searchConsoleService->searchanalytics->query($siteUrl, $request);
            
            $data = [];
            foreach ($response->getRows() as $row) {
                $data[] = [
                    'keys' => $row->getKeys(),
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => $row->getCtr(),
                    'position' => $row->getPosition(),
                ];
            }
            
            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get search analytics: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }
}