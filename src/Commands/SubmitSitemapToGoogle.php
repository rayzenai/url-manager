<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use RayzenAI\UrlManager\Services\GoogleSearchConsoleService;

class SubmitSitemapToGoogle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:submit 
                            {--url= : Custom sitemap URL to submit}
                            {--google : Submit only to Google}
                            {--bing : Submit only to Bing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit sitemap to search engines (Google and Bing)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sitemapUrl = $this->option('url');
        $googleOnly = $this->option('google');
        $bingOnly = $this->option('bing');
        
        $this->info('Submitting sitemap to search engines...');
        
        if ($sitemapUrl) {
            $this->line("Using custom sitemap URL: {$sitemapUrl}");
        } else {
            $sitemapUrl = url('/sitemap.xml');
            $this->line("Using default sitemap URL: {$sitemapUrl}");
        }
        
        // Submit based on options
        if ($googleOnly) {
            $this->submitToGoogle($sitemapUrl);
        } elseif ($bingOnly) {
            $this->submitToBing($sitemapUrl);
        } else {
            $this->submitToAll($sitemapUrl);
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Submit to Google only
     */
    private function submitToGoogle(string $sitemapUrl): void
    {
        $this->info('Submitting to Google...');
        
        $result = GoogleSearchConsoleService::pingGoogleSitemap($sitemapUrl);
        
        if ($result['success']) {
            $this->info('✅ Successfully submitted to Google!');
        } else {
            $this->error('❌ Failed to submit to Google: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    /**
     * Submit to Bing only
     */
    private function submitToBing(string $sitemapUrl): void
    {
        $this->info('Submitting to Bing...');
        
        $result = GoogleSearchConsoleService::pingBingSitemap($sitemapUrl);
        
        if ($result['success']) {
            $this->info('✅ Successfully submitted to Bing!');
        } else {
            $this->error('❌ Failed to submit to Bing: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    /**
     * Submit to all search engines
     */
    private function submitToAll(string $sitemapUrl): void
    {
        $this->info('Submitting to all search engines...');
        
        $result = GoogleSearchConsoleService::submitToAllSearchEngines($sitemapUrl);
        
        // Show results for each search engine
        if (isset($result['results']['google'])) {
            if ($result['results']['google']['success']) {
                $this->info('✅ Google: Successfully submitted');
            } else {
                $this->error('❌ Google: ' . ($result['results']['google']['message'] ?? 'Failed'));
            }
        }
        
        if (isset($result['results']['bing'])) {
            if ($result['results']['bing']['success']) {
                $this->info('✅ Bing: Successfully submitted');
            } else {
                $this->error('❌ Bing: ' . ($result['results']['bing']['message'] ?? 'Failed'));
            }
        }
        
        $this->newLine();
        
        if ($result['success']) {
            $this->info('🎉 All submissions completed successfully!');
        } else {
            $this->warn('⚠️ Some submissions failed. Check the details above.');
        }
    }
}