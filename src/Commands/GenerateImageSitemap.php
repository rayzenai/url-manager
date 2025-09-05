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
        $maxPerFile = config('url-manager.sitemap.images.max_images_per_file', 5000);
        
        
        // Build the query based on configuration
        $query = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'image/%')
            ->whereNotNull('seo_title'); // Only include images with SEO titles
        
        $totalImages = $query->count();
        
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
       
        $query = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'image/%')
            ->whereNotNull('seo_title'); // Only include images with SEO titles
        
        return $query->orderBy('created_at', 'desc')
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
                        // Remove control characters that are invalid in XML
                        $title = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $title);
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
            // Get configured image size for sitemap
            $imageSize = $this->getBestImageSize();
            
            // Use FileManager to get the full URL (handles S3, local storage, etc.)
            if (class_exists(\Kirantimsina\FileManager\Facades\FileManager::class)) {
                // If a size is determined, use it; otherwise use original
                if ($imageSize) {
                    return \Kirantimsina\FileManager\Facades\FileManager::getMediaPath($image->file_name, $imageSize);
                } else {
                    return \Kirantimsina\FileManager\Facades\FileManager::getMediaPath($image->file_name);
                }
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
    
    /**
     * Intelligently determine the best image size for sitemaps
     * Prefers sizes between 600-1000px, falling back to the closest available
     */
    protected function getBestImageSize(): ?string
    {
        // First check if there's a manually configured size
        $configuredSize = config('url-manager.sitemap.images.image_size');
        
        // If explicitly set to null or false, use original
        if ($configuredSize === null || $configuredSize === false) {
            return null;
        }
        
        // If a specific size is configured and not 'auto', use it
        if ($configuredSize && $configuredSize !== 'auto') {
            return $configuredSize;
        }
        
        // Auto-detect best size from file-manager config
        $availableSizes = config('file-manager.image_sizes', []);
        
        if (empty($availableSizes)) {
            // No sizes configured, use original
            return null;
        }
        
        // Find sizes within our preferred range (600-1000px)
        $preferredMin = 600;
        $preferredMax = 1000;
        $candidateSizes = [];
        
        foreach ($availableSizes as $name => $height) {
            $heightInt = intval($height);
            if ($heightInt >= $preferredMin && $heightInt <= $preferredMax) {
                $candidateSizes[$name] = $heightInt;
            }
        }
        
        // If we found sizes in the preferred range, use the largest one
        if (!empty($candidateSizes)) {
            // Sort by height (ascending) and get the largest
            asort($candidateSizes);
            $bestSize = array_key_last($candidateSizes);
            
            $this->info("Auto-selected image size '{$bestSize}' ({$candidateSizes[$bestSize]}px) for sitemaps");
            return $bestSize;
        }
        
        // No sizes in preferred range, find the closest one
        $closestSize = null;
        $closestDistance = PHP_INT_MAX;
        $targetHeight = 800; // Middle of our preferred range
        
        foreach ($availableSizes as $name => $height) {
            $heightInt = intval($height);
            $distance = abs($heightInt - $targetHeight);
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestSize = $name;
            }
        }
        
        if ($closestSize) {
            $heightInt = intval($availableSizes[$closestSize]);
            $this->info("Auto-selected closest image size '{$closestSize}' ({$heightInt}px) for sitemaps");
            return $closestSize;
        }
        
        // Fallback to original if no suitable size found
        return null;
    }
    
    protected function getImageTitle($image): ?string
    {
        // First check if we have a pre-populated seo_title field
        if (!empty($image->seo_title)) {
            return $image->seo_title;
        }
        
        // If seo_title is not available, fall back to polymorphic relationship lookup
        // This maintains backward compatibility for systems without the seo_title field
        if (!empty($image->mediable_type) && !empty($image->mediable_id)) {
            try {
                // Get the parent model
                $parentModel = $image->mediable_type::find($image->mediable_id);
                
                if ($parentModel) {
                    // Check if we have a custom title mapping for this model type
                    $titleMappings = config('url-manager.sitemap.images.title_mappings', []);
                    $modelType = str_replace('\\\\', '\\', $image->mediable_type); // Normalize the model type
                    
                    if (isset($titleMappings[$modelType])) {
                        $mapping = $titleMappings[$modelType];
                        
                        // Check if it's a template string with placeholders
                        if (str_contains($mapping, '{')) {
                            // Replace placeholders with actual field values
                            return preg_replace_callback('/\{(\w+)\}/', function($matches) use ($parentModel) {
                                $field = $matches[1];
                                return $parentModel->$field ?? '';
                            }, $mapping);
                        }
                        
                        // It's a pipe-separated list of fallback fields
                        $fields = explode('|', $mapping);
                        foreach ($fields as $field) {
                            // Check if field has a character limit (e.g., "message:60")
                            if (str_contains($field, ':')) {
                                [$fieldName, $limit] = explode(':', $field);
                                if (isset($parentModel->$fieldName) && !empty($parentModel->$fieldName)) {
                                    return substr($parentModel->$fieldName, 0, (int)$limit);
                                }
                            } elseif (isset($parentModel->$field) && !empty($parentModel->$field)) {
                                return $parentModel->$field;
                            } elseif (!str_contains($field, '->') && !isset($parentModel->$field)) {
                                // If it's not a field and doesn't contain ->, it might be a default value
                                if (!empty(trim($field)) && !property_exists($parentModel, $field)) {
                                    return $field; // Use as literal default value
                                }
                            }
                        }
                    }
                    
                    // Fallback to generic field checking if no custom mapping
                    $commonFields = ['meta_title', 'title', 'name', 'product_name', 'heading', 'full_name'];
                    foreach ($commonFields as $field) {
                        if (isset($parentModel->$field) && !empty($parentModel->$field)) {
                            return $parentModel->$field;
                        }
                    }
                }
            } catch (\Exception $e) {
                // If we can't load the parent model, fall back to other methods
            }
        }
        
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
        
        // Use file name without extension as last fallback
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