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
 * 2. Model MUST implement webUrlPath() method that returns the desired URL path
 * 3. Model MAY implement ogTags() method for Open Graph meta tags
 * 4. Model MAY implement getSeoMetadata() for additional SEO metadata
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
 *     public function ogTags(): array
 *     {
 *         return [
 *             'title' => $this->name,
 *             'description' => $this->description,
 *             'image' => $this->image_url,
 *         ];
 *     }
 * }
 * ```
 */
trait HasUrl
{
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
            if ($model->url) {
                // Update URL status based on model's active status
                if ($model->wasChanged('is_active')) {
                    $model->updateUrlStatus();
                }
                
                // Update slug if changed
                if (method_exists($model, 'getSlugAttribute') && $model->wasChanged('slug')) {
                    $model->updateUrlSlug();
                }
                
                // Touch last modified
                $model->url->touchLastModified();
            } elseif ($model->shouldHaveUrl()) {
                // Create URL if it doesn't exist but should
                $model->createUrl();
            }
        });

        // Delete URL when model is deleted
        static::deleted(function ($model) {
            if ($model->url) {
                $model->url->delete();
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
        // Check if model is active (if has is_active field)
        if (property_exists($this, 'fillable') && in_array('is_active', $this->fillable)) {
            return $this->is_active ?? false;
        }

        return true;
    }

    /**
     * Check if model is active for URL purposes
     */
    public function isActiveForUrl(): bool
    {
        return $this->is_active ?? true;
    }

    /**
     * Create URL for this model
     */
    protected function createUrl(): void
    {
        if (!method_exists($this, 'webUrlPath')) {
            return;
        }

        $path = $this->webUrlPath();
        $type = $this->getUrlType();

        Url::create([
            'slug' => $path,
            'urable_type' => get_class($this),
            'urable_id' => $this->id,
            'type' => $type,
            'status' => $this->isActiveForUrl() ? Url::STATUS_ACTIVE : Url::STATUS_INACTIVE,
            'last_modified_at' => now(),
        ]);
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
            // Create redirect from old to new
            Url::createRedirect($oldPath, $newPath);
            
            // Update current URL
            $this->url->update([
                'slug' => $newPath,
                'last_modified_at' => now(),
            ]);
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
     * Get Open Graph tags for this model
     */
    public function ogTags(): array
    {
        return [
            'title' => $this->name ?? $this->title ?? null,
            'description' => $this->description ?? null,
            'type' => 'website',
        ];
    }

    /**
     * Get SEO metadata for this model
     */
    public function getSeoMetadata(): array
    {
        $ogTags = $this->ogTags();
        
        return [
            'title' => $ogTags['title'] ?? null,
            'description' => $ogTags['description'] ?? null,
            'og_type' => $ogTags['type'] ?? 'website',
            'og_image' => $ogTags['image'] ?? null,
        ];
    }
}