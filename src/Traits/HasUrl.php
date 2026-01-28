<?php

namespace RayzenAI\UrlManager\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use RayzenAI\UrlManager\Models\Url;

/**
 * HasUrl Trait
 *
 * Provides URL management functionality for Eloquent models.
 *
 * Requirements for models using this trait:
 * 1. Model MUST have an 'is_active' boolean field to control URL visibility
 *    (override activeUrlField() if using a different field name)
 * 2. Model MUST have a 'slug' field for URL generation
 *    (override slugField() if using a different field name)
 * 3. Model MUST implement webUrlPath() method that returns the desired URL path
 * 4. Model MAY override ogTags() method for Open Graph meta tags
 * 5. Model MAY override getSeoMetadata() for additional SEO metadata
 * 6. Model MAY implement getViewCountColumn() to track view counts
 * 7. Model MAY implement recordVisit($userId, $metadata) for custom visit tracking
 * 8. Model MAY override ogImageField() to specify the OG image field (default: 'og_image')
 * 9. Model MAY override ogImageFallbackField() to specify a fallback image field (default: 'image')
 *
 * OG Tags returned by ogTags():
 * - title: The OG title (from meta_title, name, or title field)
 * - description: The OG description (from meta_description or description field)
 * - type: The OG type (default: 'website')
 * - image: The OG image URL (from og_image field, falls back to image field)
 * - image_width: The OG image width (default: 1200)
 * - image_height: The OG image height (default: 630)
 * - image_alt: The OG image alt text (defaults to title)
 * - url: The canonical URL
 * - site_name: The site name from config
 *
 * Example implementation:
 * ```php
 * class Product extends Model
 * {
 *     use HasUrl;
 *
 *     public function webUrlPath(): string
 *     {
 *         return 'products/' . $this->slug;
 *     }
 *
 *     // Optional: Override if using different field for OG image
 *     public function ogImageFallbackField(): ?string
 *     {
 *         return 'featured_image'; // Falls back to this if og_image is empty
 *     }
 *
 *     // Optional: Override for custom OG tags
 *     public function ogTags(): array
 *     {
 *         return [
 *             'title' => $this->name,
 *             'description' => $this->description,
 *             'type' => 'product',
 *             'image' => $this->getOgImageUrl(),
 *             'image_width' => 1200,
 *             'image_height' => 630,
 *             'image_alt' => $this->name,
 *             'url' => $this->webUrl(),
 *             'site_name' => config('app.name'),
 *         ];
 *     }
 *
 *     public function getViewCountColumn(): ?string
 *     {
 *         return 'view_count'; // Return null if no view counting needed
 *     }
 * }
 * ```
 */
trait HasUrl
{
    /**
     * Get the field name that determines if the model is active for URL purposes
     * Override this in your model if using a different field name
     */
    public function activeUrlField(): string
    {
        return 'is_active';
    }

    /**
     * Get the field name that contains the slug for URL generation
     * Override this in your model if using a different field name
     */
    public function slugField(): string
    {
        return 'slug';
    }

    /**
     * Get the web URL path for this model
     * Override this in your model to customize the URL path
     * Defaults to the model's slug if not overridden
     */
    public function webUrlPath(): string
    {
        $modelName = str(class_basename($this))->plural()->kebab()->toString();
        $slug = (string) $this->id;

        // Use slug if it exists
        if (property_exists($this, 'slug') || isset($this->slug)) {
            $slug =  $this->slug;
        }
        
        // Fallback to a generic path using the model name and ID/slug
        return "{$modelName}/{$slug}";
    }

    /**
     * Boot the HasUrl trait
     */
    protected static function bootHasUrl(): void
    {
        // Create URL when model is created
        static::created(function ($model) {
            if ($model->shouldHaveUrl()) {
                $model->createUrl();
            }
        });

        // Update URL when model is updated
        static::updated(function ($model) {
            $activeField = $model->activeUrlField();
            $slugField = $model->slugField();

            // Check if URL relationship is loaded to avoid lazy loading issues
            if ($model->relationLoaded('url') && $model->url) {
                // Update URL status based on model's active status
                if ($model->wasChanged($activeField)) {
                    $model->updateUrlStatus();
                }

                // Update slug if changed
                if ($model->wasChanged($slugField)) {
                    $model->updateUrlSlug();
                }

                // Touch last modified
                $model->url->touchLastModified();
            } elseif ($model->shouldHaveUrl()) {
                // Load the URL relationship if we need to check it
                $model->load('url');
                if (!$model->url) {
                    // Create URL if it doesn't exist but should
                    $model->createUrl();
                }
            }
        });

        // Delete URL when model is deleted
        static::deleted(function ($model) {
            // Check if URL relationship is loaded to avoid lazy loading issues
            if ($model->relationLoaded('url') && $model->url) {
                $model->url->delete();
            } else {
                // If relationship not loaded, query directly to avoid lazy loading
                Url::where('urable_type', get_class($model))
                    ->where('urable_id', $model->id)
                    ->delete();
            }
        });
    }

    /**
     * Get the URL relationship
     */
    public function url(): MorphOne
    {
        return $this->morphOne(Url::class, 'urable');
    }

    /**
     * Get the full web URL for this model
     */
    public function webUrl(): string
    {
        if ($this->url) {
            return $this->url->getAbsoluteUrl();
        }

        // Fallback to generating URL from path
        return url($this->webUrlPath());
    }

    /**
     * Get the menu URL for this model (if applicable)
     */
    public function menuUrl(): ?string
    {
        // Override in model if menu URLs are different
        return $this->webUrl();
    }

    /**
     * Get the admin panel URL for this model
     */
    public function adminUrl(): string
    {
        $resourceName = str(class_basename($this))->plural()->lower();
        return "/admin/{$resourceName}/{$this->id}/edit";
    }

    /**
     * Check if model should have a URL
     */
    protected function shouldHaveUrl(): bool
    {
        $activeField = $this->activeUrlField();
        
        // Check if model has the active field
        if (property_exists($this, 'fillable') && in_array($activeField, $this->fillable)) {
            return $this->{$activeField} ?? false;
        }

        return true;
    }

    /**
     * Check if model is active for URL purposes
     */
    public function isActiveForUrl(): bool
    {
        $activeField = $this->activeUrlField();
        return $this->{$activeField} ?? true;
    }

    /**
     * Create URL for this model
     */
    protected function createUrl(): void
    {
        $path = $this->webUrlPath();
        $type = $this->getUrlType();

        // Use updateOrCreate to handle race conditions and duplicates
        Url::updateOrCreate(
            [
                'urable_type' => get_class($this),
                'urable_id' => $this->id,
            ],
            [
                'slug' => $path,
                'type' => $type,
                'status' => $this->isActiveForUrl() ? Url::STATUS_ACTIVE : Url::STATUS_INACTIVE,
                'last_modified_at' => now(),
            ]
        );
    }

    /**
     * Update URL status based on model's active status
     */
    protected function updateUrlStatus(): void
    {
        if (!$this->url) {
            return;
        }

        $this->url->update([
            'status' => $this->isActiveForUrl() ? Url::STATUS_ACTIVE : Url::STATUS_INACTIVE,
            'last_modified_at' => now(),
        ]);
    }

    /**
     * Update URL slug when model slug changes
     */
    protected function updateUrlSlug(): void
    {
        if (!$this->url || !method_exists($this, 'webUrlPath')) {
            return;
        }

        $newPath = $this->webUrlPath();
        $oldPath = $this->url->slug;

        if ($newPath !== $oldPath) {
            // Check for circular redirect chains before creating redirect
            $chain = Url::detectRedirectChain($oldPath, $newPath);

            if ($chain) {
                throw new \RuntimeException(
                    'Cannot update slug: This would create a circular redirect chain: ' .
                    implode(' → ', $chain)
                );
            }

            // IMPORTANT: Update current URL FIRST, then create redirect
            // This way createRedirect won't find the active URL and convert it to a redirect
            $this->url->update([
                'slug' => $newPath,
                'last_modified_at' => now(),
            ]);

            // Now create a NEW redirect entry from old path to new path
            Url::createRedirect($oldPath, $newPath);
        }
    }

    /**
     * Get the URL type for this model
     */
    protected function getUrlType(): string
    {
        $className = class_basename($this);
        
        $typeMap = [
            'Entity' => Url::TYPE_ENTITY,
            'Category' => Url::TYPE_CATEGORY,
            'Seller' => Url::TYPE_SELLER,
            'Brand' => Url::TYPE_BRAND,
            'Blog' => Url::TYPE_BLOG,
            'Page' => Url::TYPE_PAGE,
        ];

        return $typeMap[$className] ?? Url::TYPE_PAGE;
    }

    /**
     * Get the OG image field name for this model
     * Override this in your model if using a different field name
     */
    public function ogImageField(): string
    {
        return 'og_image';
    }

    /**
     * Get the fallback image field name for OG image
     * Override this in your model to specify a fallback image field
     */
    public function ogImageFallbackField(): ?string
    {
        return 'image';
    }

    /**
     * Get the OG image URL for this model
     * Returns og_image if set, otherwise falls back to the fallback field
     */
    public function getOgImageUrl(): ?string
    {
        $ogImageField = $this->ogImageField();
        $fallbackField = $this->ogImageFallbackField();

        // Try og_image field first
        if (!empty($this->{$ogImageField})) {
            return $this->{$ogImageField};
        }

        // Try fallback field
        if ($fallbackField && !empty($this->{$fallbackField})) {
            return $this->{$fallbackField};
        }

        return null;
    }

    /**
     * Get Open Graph tags for this model
     *
     * Returns an array with the following keys:
     * - title: The OG title (required)
     * - description: The OG description (required)
     * - type: The OG type (default: 'website')
     * - image: The OG image URL (optional)
     * - image_width: The OG image width (default: 1200)
     * - image_height: The OG image height (default: 630)
     * - image_alt: The OG image alt text (optional)
     * - url: The canonical URL (optional)
     * - site_name: The site name (optional)
     *
     * Override this in your model to customize the OG tags
     */
    public function ogTags(): array
    {
        $title = $this->meta_title ?? $this->name ?? $this->title ?? null;
        $description = $this->meta_description ?? $this->description ?? null;
        $image = $this->getOgImageUrl();

        return [
            'title' => $title,
            'description' => $description,
            'type' => 'website',
            'image' => $image,
            'image_width' => 1200,
            'image_height' => 630,
            'image_alt' => $title,
            'url' => $this->webUrl(),
            'site_name' => config('app.name'),
        ];
    }

    /**
     * Get SEO metadata for this model
     * Combines OG tags with additional SEO metadata
     */
    public function getSeoMetadata(): array
    {
        $ogTags = $this->ogTags();

        return [
            'title' => $ogTags['title'] ?? null,
            'description' => $ogTags['description'] ?? null,
            'og_type' => $ogTags['type'] ?? 'website',
            'og_image' => $ogTags['image'] ?? null,
            'og_image_width' => $ogTags['image_width'] ?? 1200,
            'og_image_height' => $ogTags['image_height'] ?? 630,
            'og_image_alt' => $ogTags['image_alt'] ?? null,
            'og_url' => $ogTags['url'] ?? null,
            'og_site_name' => $ogTags['site_name'] ?? null,
            'canonical_url' => $ogTags['url'] ?? null,
        ];
    }
}