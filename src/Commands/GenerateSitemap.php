<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use RayzenAI\UrlManager\Models\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--limit=50000 : Maximum URLs per sitemap}';

    protected $description = 'Generate XML sitemap from active URLs';

    public function handle()
    {
        if (!config('url-manager.sitemap.enabled', true)) {
            $this->error('Sitemap generation is disabled in configuration.');
            return 1;
        }

        $this->info('Generating sitemap...');
        
        $limit = (int) $this->option('limit');
        $maxPerFile = config('url-manager.sitemap.max_urls_per_file', 10000);
        
        // Get all active URLs
        $totalUrls = Url::active()->count();
        
        if ($totalUrls === 0) {
            $this->warn('No active URLs found.');
            return 0;
        }
        
        $this->info("Found {$totalUrls} active URLs");
        
        // Determine if we need multiple sitemap files
        if ($totalUrls > $maxPerFile) {
            $this->generateMultipleSitemaps($totalUrls, $maxPerFile, $limit);
        } else {
            $this->generateSingleSitemap($limit);
        }
        
        $this->info('Sitemap generated successfully!');
        
        return 0;
    }
    
    protected function generateSingleSitemap(int $limit)
    {
        $urls = Url::active()
            ->with('urable')
            ->limit($limit)
            ->get();
        
        $xml = $this->generateXml($urls);
        
        $path = config('url-manager.sitemap.path', public_path('sitemap.xml'));
        file_put_contents($path, $xml);
        
        $this->info("Sitemap saved to: {$path}");
    }
    
    protected function generateMultipleSitemaps(int $totalUrls, int $maxPerFile, int $limit)
    {
        $numberOfFiles = ceil(min($totalUrls, $limit) / $maxPerFile);
        
        // Generate sitemap index
        $sitemapIndex = $this->generateSitemapIndex($numberOfFiles);
        $indexPath = config('url-manager.sitemap.path', public_path('sitemap.xml'));
        file_put_contents($indexPath, $sitemapIndex);
        
        $this->info("Sitemap index saved to: {$indexPath}");
        
        // Generate individual sitemap files
        for ($i = 0; $i < $numberOfFiles; $i++) {
            $offset = $i * $maxPerFile;
            $urls = Url::active()
                ->with('urable')
                ->offset($offset)
                ->limit(min($maxPerFile, $limit - $offset))
                ->get();
            
            $xml = $this->generateXml($urls);
            
            $filePath = public_path("sitemap-{$i}.xml");
            file_put_contents($filePath, $xml);
            
            $this->info("Sitemap part {$i} saved to: {$filePath}");
        }
    }
    
    protected function generateXml($urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $xml .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $xml .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;
        
        // Add homepage first
        $xml .= '  <url>' . PHP_EOL;
        $xml .= '    <loc>' . url('/') . '</loc>' . PHP_EOL;
        $xml .= '    <lastmod>' . now()->toW3cString() . '</lastmod>' . PHP_EOL;
        $xml .= '    <changefreq>daily</changefreq>' . PHP_EOL;
        $xml .= '    <priority>1.0</priority>' . PHP_EOL;
        $xml .= '  </url>' . PHP_EOL;
        
        foreach ($urls as $url) {
            if (!$url->shouldIndex()) {
                continue;
            }
            
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url->getAbsoluteUrl()) . '</loc>' . PHP_EOL;
            
            if ($url->last_modified_at) {
                $xml .= '    <lastmod>' . $url->last_modified_at->toW3cString() . '</lastmod>' . PHP_EOL;
            }
            
            $xml .= '    <changefreq>' . $url->getSitemapChangefreq() . '</changefreq>' . PHP_EOL;
            $xml .= '    <priority>' . $url->getSitemapPriority() . '</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }
        
        $xml .= '</urlset>' . PHP_EOL;
        
        return $xml;
    }
    
    protected function generateSitemapIndex(int $numberOfFiles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        for ($i = 0; $i < $numberOfFiles; $i++) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . url("/sitemap-{$i}.xml") . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . now()->toW3cString() . '</lastmod>' . PHP_EOL;
            $xml .= '  </sitemap>' . PHP_EOL;
        }
        
        $xml .= '</sitemapindex>' . PHP_EOL;
        
        return $xml;
    }
}