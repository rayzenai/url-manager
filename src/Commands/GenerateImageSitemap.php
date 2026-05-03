<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Kirantimsina\FileManager\Facades\FileManager;
use RayzenAI\UrlManager\Models\GoogleSearchConsoleSetting;
use RayzenAI\UrlManager\Models\Url;

class GenerateImageSitemap extends Command
{
    protected $signature = 'sitemap:generate-images {--limit=50000 : Maximum images per sitemap}';

    protected $description = 'Generate XML image sitemap from media metadata';

    protected ?string $cachedImageSize = null;

    protected bool $imageSizeComputed = false;

    /** @var array<string, string|null> Batch-loaded URL paths keyed by "type:id" */
    protected array $urlCache = [];

    public function handle()
    {
        if (! config('url-manager.sitemap.enabled', true)) {
            $this->error('Sitemap generation is disabled in configuration.');

            return 1;
        }

        $this->info('Generating image sitemap...');

        $limit = (int) $this->option('limit');
        $maxPerFile = config('url-manager.sitemap.images.max_images_per_file', 5000);

        $query = $this->baseQuery();

        $totalImages = $query->count();

        if ($totalImages === 0) {
            $this->warn('No images found in media metadata.');

            return 0;
        }

        $this->info("Found {$totalImages} images");

        if ($totalImages > $maxPerFile) {
            $this->generateMultipleImageSitemaps($totalImages, $maxPerFile, $limit);
        } else {
            $this->generateSingleImageSitemap($limit);
        }

        $this->info('Image sitemap generated successfully!');

        return 0;
    }

    /**
     * Base query: images with SEO titles, excluding configured model types.
     */
    protected function baseQuery()
    {
        $query = DB::table('media_metadata')
            ->where('mime_type', 'LIKE', 'image/%')
            ->whereNotNull('seo_title');

        $excluded = config('url-manager.sitemap.images.excluded_models', []);

        if ($excluded !== []) {
            $query->whereNotIn('mediable_type', $excluded);
        }

        return $query;
    }

    protected function generateSingleImageSitemap(int $limit): void
    {
        $images = $this->getImageData($limit);
        $this->preloadUrls($images);

        $xml = $this->generateImageXml($images);

        $path = public_path('sitemap-images.xml');
        file_put_contents($path, $xml);

        $this->info("Image sitemap saved to: {$path}");
    }

    protected function generateMultipleImageSitemaps(int $totalImages, int $maxPerFile, int $limit): void
    {
        $numberOfFiles = (int) ceil(min($totalImages, $limit) / $maxPerFile);

        $sitemapIndex = $this->generateImageSitemapIndex($numberOfFiles);
        $indexPath = public_path('sitemap-images.xml');
        file_put_contents($indexPath, $sitemapIndex);

        $this->info("Image sitemap index saved to: {$indexPath}");

        for ($i = 0; $i < $numberOfFiles; $i++) {
            $offset = $i * $maxPerFile;
            $images = $this->getImageData(min($maxPerFile, $limit - $offset), $offset);
            $this->preloadUrls($images);

            $xml = $this->generateImageXml($images);

            $filePath = public_path("sitemap-images-{$i}.xml");
            file_put_contents($filePath, $xml);

            $this->info("Image sitemap part {$i} saved to: {$filePath}");
        }
    }

    protected function getImageData(int $limit, int $offset = 0): Collection
    {
        return $this->baseQuery()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Batch-load all URL records for the images in one query instead of N+1.
     */
    protected function preloadUrls(Collection $images): void
    {
        $this->urlCache = [];

        // Group by mediable_type to build efficient OR queries
        $groups = [];
        foreach ($images as $image) {
            if ($image->mediable_type && $image->mediable_id) {
                $groups[$image->mediable_type][] = $image->mediable_id;
            }
        }

        if ($groups === []) {
            return;
        }

        $query = Url::where('status', 'active');
        $query->where(function ($q) use ($groups) {
            foreach ($groups as $type => $ids) {
                $q->orWhere(function ($sub) use ($type, $ids) {
                    $sub->where('urable_type', $type)
                        ->whereIn('urable_id', array_unique($ids));
                });
            }
        });

        foreach ($query->get(['urable_type', 'urable_id', 'slug']) as $url) {
            $key = $url->urable_type . ':' . $url->urable_id;
            $this->urlCache[$key] = $url->getFullPath();
        }
    }

    protected function generateImageXml(Collection $images): string
    {
        $settings = GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
        $xml .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
        $xml .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd ';
        $xml .= 'http://www.google.com/schemas/sitemap-image/1.1 ';
        $xml .= 'http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd">' . PHP_EOL;

        // Group images by their parent page URL
        $groupedImages = [];
        foreach ($images as $image) {
            $parentUrl = $this->getParentUrl($image) ?? '/';

            if (! isset($groupedImages[$parentUrl])) {
                $groupedImages[$parentUrl] = [];
            }
            $groupedImages[$parentUrl][] = $image;
        }

        foreach ($groupedImages as $urlPath => $urlImages) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($siteUrl . $urlPath) . '</loc>' . PHP_EOL;

            foreach ($urlImages as $image) {
                $imageUrl = $this->getImageUrl($image);
                if ($imageUrl) {
                    $xml .= '    <image:image>' . PHP_EOL;
                    $xml .= '      <image:loc>' . htmlspecialchars($imageUrl) . '</image:loc>' . PHP_EOL;

                    $title = $this->getImageTitle($image);
                    if ($title) {
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
        if ($image->mediable_type && $image->mediable_id) {
            $key = $image->mediable_type . ':' . $image->mediable_id;

            return $this->urlCache[$key] ?? null;
        }

        return null;
    }

    protected function getImageUrl($image): ?string
    {
        if (empty($image->file_name)) {
            return null;
        }

        $imageSize = $this->getCachedImageSize();

        // Use FileManager to get the full URL (handles S3, local storage, etc.)
        if (class_exists(FileManager::class)) {
            return $imageSize
                ? FileManager::getMediaPath($image->file_name, $imageSize)
                : FileManager::getMediaPath($image->file_name);
        }

        // Fallback to manual URL construction
        $settings = GoogleSearchConsoleSetting::getSettings();
        $siteUrl = rtrim($settings->frontend_url ?: url('/'), '/');

        if (filter_var($image->file_name, FILTER_VALIDATE_URL)) {
            return $image->file_name;
        }

        if (str_starts_with($image->file_name, 'storage/')) {
            return $siteUrl . '/' . $image->file_name;
        }

        if (str_starts_with($image->file_name, '/')) {
            return $siteUrl . $image->file_name;
        }

        return $siteUrl . '/storage/' . $image->file_name;
    }

    protected function getCachedImageSize(): ?string
    {
        if (! $this->imageSizeComputed) {
            $this->cachedImageSize = $this->getBestImageSize();
            $this->imageSizeComputed = true;
        }

        return $this->cachedImageSize;
    }

    /**
     * Determine the best image size for sitemaps (600-1000px preferred).
     */
    protected function getBestImageSize(): ?string
    {
        $configuredSize = config('url-manager.sitemap.images.image_size');

        if ($configuredSize === null || $configuredSize === false) {
            return null;
        }

        if ($configuredSize && $configuredSize !== 'auto') {
            return $configuredSize;
        }

        $availableSizes = config('file-manager.image_sizes', []);

        if (empty($availableSizes)) {
            return null;
        }

        $preferredMin = 600;
        $preferredMax = 1000;
        $candidateSizes = [];

        foreach ($availableSizes as $name => $height) {
            $heightInt = intval($height);
            if ($heightInt >= $preferredMin && $heightInt <= $preferredMax) {
                $candidateSizes[$name] = $heightInt;
            }
        }

        if (! empty($candidateSizes)) {
            asort($candidateSizes);
            $bestSize = array_key_last($candidateSizes);
            $this->info("Auto-selected image size '{$bestSize}' ({$candidateSizes[$bestSize]}px) for sitemaps");

            return $bestSize;
        }

        // No sizes in preferred range — find closest to 800px
        $closestSize = null;
        $closestDistance = PHP_INT_MAX;

        foreach ($availableSizes as $name => $height) {
            $distance = abs(intval($height) - 800);
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestSize = $name;
            }
        }

        if ($closestSize) {
            $this->info("Auto-selected closest image size '{$closestSize}' (" . intval($availableSizes[$closestSize]) . 'px) for sitemaps');

            return $closestSize;
        }

        return null;
    }

    protected function getImageTitle($image): ?string
    {
        if (! empty($image->seo_title)) {
            return $image->seo_title;
        }

        // Try metadata JSON
        if (! empty($image->metadata)) {
            $metadata = is_string($image->metadata) ? json_decode($image->metadata, true) : $image->metadata;
            if (isset($metadata['title'])) {
                return $metadata['title'];
            }
            if (isset($metadata['alt'])) {
                return $metadata['alt'];
            }
        }

        // Use file name without extension as last fallback
        if (! empty($image->file_name)) {
            $fileName = pathinfo($image->file_name, PATHINFO_FILENAME);

            return str_replace(['-', '_'], ' ', $fileName);
        }

        return null;
    }

    protected function generateImageSitemapIndex(int $numberOfFiles): string
    {
        $settings = GoogleSearchConsoleSetting::getSettings();
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
