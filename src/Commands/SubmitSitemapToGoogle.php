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
    protected $description = 'Submit sitemap to Google Search Console via API (Bing requires manual submission)';

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
        
        $result = GoogleSearchConsoleService::submitGoogleSitemap($sitemapUrl);
        
        if ($result['success']) {
            $this->info('✅ Successfully submitted to Google via API!');
        } else {
            $this->error('❌ ' . ($result['message'] ?? 'Unknown error'));
            if (isset($result['info'])) {
                $this->warn('ℹ️  ' . $result['info']);
            }
        }
    }
    
    /**
     * Submit to Bing only
     */
    private function submitToBing(string $sitemapUrl): void
    {
        $this->info('Submitting to Bing...');
        
        $result = GoogleSearchConsoleService::submitBingSitemap($sitemapUrl);
        
        if ($result['success']) {
            $this->info('✅ Successfully submitted to Bing!');
        } else {
            $this->error('❌ ' . ($result['message'] ?? 'Unknown error'));
            if (isset($result['info'])) {
                $this->warn('ℹ️  ' . $result['info']);
            }
        }
    }
    
    /**
     * Submit to all search engines
     */
    private function submitToAll(string $sitemapUrl): void
    {
        $this->info('Submitting to all search engines...');
        $this->line("Sitemap URL: {$sitemapUrl}");
        $this->newLine();
        
        $result = GoogleSearchConsoleService::submitToAllSearchEngines($sitemapUrl);
        
        // Track successful submissions
        $successCount = 0;
        $totalCount = 0;
        
        // Show results for each search engine
        if (isset($result['results']['google'])) {
            $totalCount++;
            $this->line('📍 Google Search Console:');
            
            if ($result['results']['google']['success']) {
                $successCount++;
                $this->info('   ✅ Successfully submitted via ' . ($result['results']['google']['method'] ?? 'API'));
            } else {
                $this->error('   ❌ Submission failed');
                $this->line('   Reason: ' . ($result['results']['google']['message'] ?? 'Unknown error'));
                
                // Provide helpful info if available
                if (isset($result['results']['google']['info'])) {
                    $this->warn('   ℹ️  ' . $result['results']['google']['info']);
                }
            }
        } else {
            $this->warn('📍 Google: Not attempted (no response)');
        }
        
        $this->newLine();
        
        if (isset($result['results']['bing'])) {
            $totalCount++;
            $this->line('📍 Bing Webmaster Tools:');
            
            if ($result['results']['bing']['success']) {
                $successCount++;
                $this->info('   ✅ Successfully submitted via ' . ($result['results']['bing']['method'] ?? 'ping'));
            } else {
                $this->error('   ❌ Submission failed');
                $this->line('   Reason: ' . ($result['results']['bing']['message'] ?? 'Unknown error'));
                
                // Provide helpful info if available
                if (isset($result['results']['bing']['info'])) {
                    $this->warn('   ℹ️  ' . $result['results']['bing']['info']);
                }
            }
        } else {
            $this->warn('📍 Bing: Not attempted (no response)');
        }
        
        $this->newLine();
        $this->line('─────────────────────────────────');
        
        if ($successCount === $totalCount && $totalCount > 0) {
            $this->info("🎉 All submissions completed successfully! ({$successCount}/{$totalCount})");
        } elseif ($successCount > 0) {
            $this->warn("⚠️  Partial submission: {$successCount} of {$totalCount} search engines succeeded");
            $this->line('Please review the errors above and try again.');
        } else {
            $this->error("❌ All submissions failed ({$successCount}/{$totalCount})");
            $this->line('Please check the error details above and ensure:');
            $this->line('  • Your internet connection is working');
            $this->line('  • API credentials are properly configured (for Google)');
            $this->line('  • The sitemap URL is accessible: ' . $sitemapUrl);
        }
    }
}