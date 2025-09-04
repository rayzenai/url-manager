<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class GenerateAllSitemaps extends Command
{
    protected $signature = 'sitemap:generate-all';

    protected $description = 'Generate all sitemaps (URLs, images, videos) and create a master sitemap index';

    public function handle()
    {
        if (!config('url-manager.sitemap.enabled', true)) {
            $this->error('Sitemap generation is disabled in configuration.');
            return 1;
        }

        $this->info('Generating all sitemaps...');
        
        $sitemaps = [];
        
        // Generate URL sitemap
        $this->info('Generating URL sitemap...');
        Artisan::call('sitemap:generate');
        $urlCount = \RayzenAI\UrlManager\Models\Url::active()->count();
        $this->info("✓ Generated URL sitemap with {$urlCount} URLs");
        $sitemaps[] = 'sitemap.xml';
        
        // Generate image sitemap if there are images
        $imageCount = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'image/%')
            ->count();
            
        if ($imageCount > 0) {
            $this->info("Generating image sitemap for {$imageCount} images...");
            Artisan::call('sitemap:generate-images');
            $this->info("✓ Generated image sitemap with {$imageCount} images");
            $sitemaps[] = 'sitemap-images.xml';
        } else {
            $this->info('No images found, skipping image sitemap.');
        }
        
        // Generate video sitemap if there are videos
        $videoCount = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'video/%')
            ->count();
            
        if ($videoCount > 0) {
            $this->info("Generating video sitemap for {$videoCount} videos...");
            Artisan::call('sitemap:generate-videos');
            $this->info("✓ Generated video sitemap with {$videoCount} videos");
            $sitemaps[] = 'sitemap-videos.xml';
        } else {
            $this->info('No videos found, skipping video sitemap.');
        }
        
        // Create master sitemap index
        if (count($sitemaps) > 1) {
            $this->generateMasterSitemapIndex($sitemaps);
        }
        
        $this->info('');
        $this->info('All sitemaps generated successfully!');
        $this->info('');
        $this->info('Summary:');
        $this->info("- URLs: {$urlCount}");
        $this->info("- Images: {$imageCount}");
        $this->info("- Videos: {$videoCount}");
        $this->info('');
        $this->info('Generated files:');
        foreach ($sitemaps as $sitemap) {
            $this->info("- " . public_path($sitemap));
        }
        
        return 0;
    }
    
    protected function generateMasterSitemapIndex(array $sitemaps): void
    {
        // Get the configured frontend URL for sitemap generation
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . $siteUrl . '/' . $sitemap . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . now()->toW3cString() . '</lastmod>' . PHP_EOL;
            $xml .= '  </sitemap>' . PHP_EOL;
        }
        
        $xml .= '</sitemapindex>' . PHP_EOL;
        
        $path = public_path('sitemap-index.xml');
        file_put_contents($path, $xml);
        
        $this->info("Master sitemap index saved to: {$path}");
    }
}