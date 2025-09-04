<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RayzenAI\UrlManager\Models\Url;

class GenerateVideoSitemap extends Command
{
    protected $signature = 'sitemap:generate-videos {--limit=50000 : Maximum videos per sitemap}';

    protected $description = 'Generate XML video sitemap from media metadata';

    public function handle()
    {
        if (!config('url-manager.sitemap.enabled', true)) {
            $this->error('Sitemap generation is disabled in configuration.');
            return 1;
        }

        $this->info('Generating video sitemap...');
        
        $limit = (int) $this->option('limit');
        $maxPerFile = config('url-manager.sitemap.max_videos_per_file', 5000);
        
        // Get all videos from media_metadata
        $totalVideos = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'video/%')
            ->count();
        
        if ($totalVideos === 0) {
            $this->warn('No videos found in media metadata.');
            return 0;
        }
        
        $this->info("Found {$totalVideos} videos");
        
        // Determine if we need multiple sitemap files
        if ($totalVideos > $maxPerFile) {
            $this->generateMultipleVideoSitemaps($totalVideos, $maxPerFile, $limit);
        } else {
            $this->generateSingleVideoSitemap($limit);
        }
        
        $this->info('Video sitemap generated successfully!');
        
        return 0;
    }
    
    protected function generateSingleVideoSitemap(int $limit)
    {
        $videos = $this->getVideoData($limit);
        
        $xml = $this->generateVideoXml($videos);
        
        $path = public_path('sitemap-videos.xml');
        file_put_contents($path, $xml);
        
        $this->info("Video sitemap saved to: {$path}");
    }
    
    protected function generateMultipleVideoSitemaps(int $totalVideos, int $maxPerFile, int $limit)
    {
        $numberOfFiles = ceil(min($totalVideos, $limit) / $maxPerFile);
        
        // Generate sitemap index
        $sitemapIndex = $this->generateVideoSitemapIndex($numberOfFiles);
        $indexPath = public_path('sitemap-videos.xml');
        file_put_contents($indexPath, $sitemapIndex);
        
        $this->info("Video sitemap index saved to: {$indexPath}");
        
        // Generate individual sitemap files
        for ($i = 0; $i < $numberOfFiles; $i++) {
            $offset = $i * $maxPerFile;
            $videos = $this->getVideoData(min($maxPerFile, $limit - $offset), $offset);
            
            $xml = $this->generateVideoXml($videos);
            
            $filePath = public_path("sitemap-videos-{$i}.xml");
            file_put_contents($filePath, $xml);
            
            $this->info("Video sitemap part {$i} saved to: {$filePath}");
        }
    }
    
    protected function getVideoData(int $limit, int $offset = 0)
    {
        return DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'video/%')
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }
    
    protected function generateVideoXml($videos): string
    {
        // Get the configured frontend URL for sitemap generation
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1" ';
        $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $xml .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $xml .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd ';
        $xml .= 'http://www.google.com/schemas/sitemap-video/1.1 ';
        $xml .= 'http://www.google.com/schemas/sitemap-video/1.1/sitemap-video.xsd">' . PHP_EOL;
        
        // Group videos by their parent page
        $groupedVideos = [];
        foreach ($videos as $video) {
            // Try to find the URL for this media's parent
            $parentUrl = $this->getParentUrl($video);
            if (!$parentUrl) {
                // Default to homepage if no parent found
                $parentUrl = '/';
            }
            
            if (!isset($groupedVideos[$parentUrl])) {
                $groupedVideos[$parentUrl] = [];
            }
            $groupedVideos[$parentUrl][] = $video;
        }
        
        // Generate URL entries with their videos
        foreach ($groupedVideos as $urlPath => $urlVideos) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($siteUrl . $urlPath) . '</loc>' . PHP_EOL;
            
            foreach ($urlVideos as $video) {
                $videoUrl = $this->getVideoUrl($video);
                $thumbnailUrl = $this->getThumbnailUrl($video);
                
                if ($videoUrl && $thumbnailUrl) {
                    $xml .= '    <video:video>' . PHP_EOL;
                    
                    // Required fields
                    $xml .= '      <video:thumbnail_loc>' . htmlspecialchars($thumbnailUrl) . '</video:thumbnail_loc>' . PHP_EOL;
                    $xml .= '      <video:title>' . htmlspecialchars($video->title ?: 'Video') . '</video:title>' . PHP_EOL;
                    $xml .= '      <video:description>' . htmlspecialchars($video->alt_text ?: $video->title ?: 'Video content') . '</video:description>' . PHP_EOL;
                    $xml .= '      <video:content_loc>' . htmlspecialchars($videoUrl) . '</video:content_loc>' . PHP_EOL;
                    
                    // Optional fields
                    if (!empty($video->duration)) {
                        $xml .= '      <video:duration>' . (int)$video->duration . '</video:duration>' . PHP_EOL;
                    }
                    
                    if (!empty($video->created_at)) {
                        $xml .= '      <video:publication_date>' . $video->created_at . '</video:publication_date>' . PHP_EOL;
                    }
                    
                    // Family friendly by default
                    $xml .= '      <video:family_friendly>yes</video:family_friendly>' . PHP_EOL;
                    
                    $xml .= '    </video:video>' . PHP_EOL;
                }
            }
            
            $xml .= '  </url>' . PHP_EOL;
        }
        
        $xml .= '</urlset>' . PHP_EOL;
        
        return $xml;
    }
    
    protected function getParentUrl($video): ?string
    {
        // Check if this video is associated with a URL-managed entity
        if ($video->mediable_type && $video->mediable_id) {
            // Try to find a URL for this mediable
            $url = Url::where('urable_type', $video->mediable_type)
                ->where('urable_id', $video->mediable_id)
                ->where('status', 'active')
                ->first();
                
            if ($url) {
                return $url->getFullPath();
            }
        }
        
        return null;
    }
    
    protected function getVideoUrl($video): ?string
    {
        // Get the configured frontend URL
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        // Check if we have the file path
        if (!empty($video->file_path)) {
            // If it's already a full URL, return it
            if (filter_var($video->file_path, FILTER_VALIDATE_URL)) {
                return $video->file_path;
            }
            
            // If it starts with storage/, prepend the site URL
            if (str_starts_with($video->file_path, 'storage/')) {
                return $siteUrl . '/' . $video->file_path;
            }
            
            // If it starts with /, append to site URL
            if (str_starts_with($video->file_path, '/')) {
                return $siteUrl . $video->file_path;
            }
            
            // Otherwise, assume it needs /storage/ prefix
            return $siteUrl . '/storage/' . $video->file_path;
        }
        
        return null;
    }
    
    protected function getThumbnailUrl($video): ?string
    {
        // Get the configured frontend URL
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        // Check if we have a thumbnail stored
        if (!empty($video->thumbnail_path)) {
            if (filter_var($video->thumbnail_path, FILTER_VALIDATE_URL)) {
                return $video->thumbnail_path;
            }
            
            if (str_starts_with($video->thumbnail_path, 'storage/')) {
                return $siteUrl . '/' . $video->thumbnail_path;
            }
            
            if (str_starts_with($video->thumbnail_path, '/')) {
                return $siteUrl . $video->thumbnail_path;
            }
            
            return $siteUrl . '/storage/' . $video->thumbnail_path;
        }
        
        // Use a default video thumbnail placeholder
        return $siteUrl . '/images/video-placeholder.jpg';
    }
    
    protected function generateVideoSitemapIndex(int $numberOfFiles): string
    {
        // Get the configured frontend URL for sitemap generation
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        for ($i = 0; $i < $numberOfFiles; $i++) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . $siteUrl . "/sitemap-videos-{$i}.xml" . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . now()->toW3cString() . '</lastmod>' . PHP_EOL;
            $xml .= '  </sitemap>' . PHP_EOL;
        }
        
        $xml .= '</sitemapindex>' . PHP_EOL;
        
        return $xml;
    }
}