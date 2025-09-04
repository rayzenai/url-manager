<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RayzenAI\UrlManager\Models\Url;

class GenerateImageSitemap extends Command
{
    protected $signature = 'sitemap:generate-images {--limit=50000 : Maximum images per sitemap}';

    protected $description = 'Generate XML image sitemap from media metadata';

    public function handle()
    {
        if (!config('url-manager.sitemap.enabled', true)) {
            $this->error('Sitemap generation is disabled in configuration.');
            return 1;
        }

        $this->info('Generating image sitemap...');
        
        $limit = (int) $this->option('limit');
        $maxPerFile = config('url-manager.sitemap.max_images_per_file', 5000);
        
        // Get all images from media_metadata
        $totalImages = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'image/%')
            ->count();
        
        if ($totalImages === 0) {
            $this->warn('No images found in media metadata.');
            return 0;
        }
        
        $this->info("Found {$totalImages} images");
        
        // Determine if we need multiple sitemap files
        if ($totalImages > $maxPerFile) {
            $this->generateMultipleImageSitemaps($totalImages, $maxPerFile, $limit);
        } else {
            $this->generateSingleImageSitemap($limit);
        }
        
        $this->info('Image sitemap generated successfully!');
        
        return 0;
    }
    
    protected function generateSingleImageSitemap(int $limit)
    {
        $images = $this->getImageData($limit);
        
        $xml = $this->generateImageXml($images);
        
        $path = public_path('sitemap-images.xml');
        file_put_contents($path, $xml);
        
        $this->info("Image sitemap saved to: {$path}");
    }
    
    protected function generateMultipleImageSitemaps(int $totalImages, int $maxPerFile, int $limit)
    {
        $numberOfFiles = ceil(min($totalImages, $limit) / $maxPerFile);
        
        // Generate sitemap index
        $sitemapIndex = $this->generateImageSitemapIndex($numberOfFiles);
        $indexPath = public_path('sitemap-images.xml');
        file_put_contents($indexPath, $sitemapIndex);
        
        $this->info("Image sitemap index saved to: {$indexPath}");
        
        // Generate individual sitemap files
        for ($i = 0; $i < $numberOfFiles; $i++) {
            $offset = $i * $maxPerFile;
            $images = $this->getImageData(min($maxPerFile, $limit - $offset), $offset);
            
            $xml = $this->generateImageXml($images);
            
            $filePath = public_path("sitemap-images-{$i}.xml");
            file_put_contents($filePath, $xml);
            
            $this->info("Image sitemap part {$i} saved to: {$filePath}");
        }
    }
    
    protected function getImageData(int $limit, int $offset = 0)
    {
        return DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'image/%')
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }
    
    protected function generateImageXml($images): string
    {
        // Get the configured frontend URL for sitemap generation
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $xml .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $xml .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd ';
        $xml .= 'http://www.google.com/schemas/sitemap-image/1.1 ';
        $xml .= 'http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd">' . PHP_EOL;
        
        // Group images by their parent page
        $groupedImages = [];
        foreach ($images as $image) {
            // Try to find the URL for this media's parent
            $parentUrl = $this->getParentUrl($image);
            if (!$parentUrl) {
                // Default to homepage if no parent found
                $parentUrl = '/';
            }
            
            if (!isset($groupedImages[$parentUrl])) {
                $groupedImages[$parentUrl] = [];
            }
            $groupedImages[$parentUrl][] = $image;
        }
        
        // Generate URL entries with their images
        foreach ($groupedImages as $urlPath => $urlImages) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($siteUrl . $urlPath) . '</loc>' . PHP_EOL;
            
            foreach ($urlImages as $image) {
                $imageUrl = $this->getImageUrl($image);
                if ($imageUrl) {
                    $xml .= '    <image:image>' . PHP_EOL;
                    $xml .= '      <image:loc>' . htmlspecialchars($imageUrl) . '</image:loc>' . PHP_EOL;
                    
                    // Try to extract title from metadata or use file name
                    $title = $this->getImageTitle($image);
                    if ($title) {
                        $xml .= '      <image:title>' . htmlspecialchars($title) . '</image:title>' . PHP_EOL;
                    }
                    
                    $xml .= '    </image:image>' . PHP_EOL;
                }
            }
            
            $xml .= '  </url>' . PHP_EOL;
        }
        
        $xml .= '</urlset>' . PHP_EOL;
        
        return $xml;
    }
    
    protected function getParentUrl($image): ?string
    {
        // Check if this image is associated with a URL-managed entity
        if ($image->mediable_type && $image->mediable_id) {
            // Try to find a URL for this mediable
            $url = Url::where('urable_type', $image->mediable_type)
                ->where('urable_id', $image->mediable_id)
                ->where('status', 'active')
                ->first();
                
            if ($url) {
                return $url->getFullPath();
            }
        }
        
        return null;
    }
    
    protected function getImageUrl($image): ?string
    {
        // Use the FileManager facade to get the correct media URL
        if (!empty($image->file_name)) {
            // Use FileManager to get the full URL (handles S3, local storage, etc.)
            if (class_exists(\Kirantimsina\FileManager\Facades\FileManager::class)) {
                return \Kirantimsina\FileManager\Facades\FileManager::getMediaPath($image->file_name);
            }
            
            // Fallback to manual URL construction if FileManager not available
            $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
            $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
            
            // If it's already a full URL, return it
            if (filter_var($image->file_name, FILTER_VALIDATE_URL)) {
                return $image->file_name;
            }
            
            // If it starts with storage/, prepend the site URL
            if (str_starts_with($image->file_name, 'storage/')) {
                return $siteUrl . '/' . $image->file_name;
            }
            
            // If it starts with /, append to site URL
            if (str_starts_with($image->file_name, '/')) {
                return $siteUrl . $image->file_name;
            }
            
            // Otherwise, assume it needs /storage/ prefix
            return $siteUrl . '/storage/' . $image->file_name;
        }
        
        return null;
    }
    
    protected function getImageTitle($image): ?string
    {
        // Try to extract title from metadata JSON
        if (!empty($image->metadata)) {
            $metadata = is_string($image->metadata) ? json_decode($image->metadata, true) : $image->metadata;
            if (isset($metadata['title'])) {
                return $metadata['title'];
            }
            if (isset($metadata['alt'])) {
                return $metadata['alt'];
            }
        }
        
        // Use file name without extension as fallback
        if (!empty($image->file_name)) {
            $fileName = pathinfo($image->file_name, PATHINFO_FILENAME);
            // Clean up the file name to make it more readable
            return str_replace(['-', '_'], ' ', $fileName);
        }
        
        return null;
    }
    
    protected function generateImageSitemapIndex(int $numberOfFiles): string
    {
        // Get the configured frontend URL for sitemap generation
        $settings = \RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
        
        for ($i = 0; $i < $numberOfFiles; $i++) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . $siteUrl . "/sitemap-images-{$i}.xml" . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . now()->toW3cString() . '</lastmod>' . PHP_EOL;
            $xml .= '  </sitemap>' . PHP_EOL;
        }
        
        $xml .= '</sitemapindex>' . PHP_EOL;
        
        return $xml;
    }
}