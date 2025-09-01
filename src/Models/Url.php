<?php

namespace RayzenAI\UrlManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Url extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_REDIRECT = 'redirect';
    const STATUS_INACTIVE = 'inactive';

    const TYPE_ENTITY = 'entity';
    const TYPE_CATEGORY = 'category';
    const TYPE_SELLER = 'seller';
    const TYPE_MENU = 'menu';
    const TYPE_BRAND = 'brand';
    const TYPE_PAGE = 'page';
    const TYPE_BLOG = 'blog';
    const TYPE_REDIRECT = 'redirect';

    protected $fillable = [
        'slug',
        'urable_type',
        'urable_id',
        'type',
        'status',
        'redirect_to',
        'redirect_code',
        'meta',
        'visits',
        'last_visited_at',
        'last_modified_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'visits' => 'integer',
        'redirect_code' => 'integer',
        'last_visited_at' => 'datetime',
        'last_modified_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('url-manager.table_name', 'urls');
    }

    /**
     * Get the owning urable model.
     */
    public function urable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to only active URLs
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to only redirect URLs
     */
    public function scopeRedirects(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REDIRECT);
    }

    /**
     * Find URL by slug (including redirects)
     */
    public static function findBySlug(string $slug, int $depth = 0)
    {
        $maxDepth = config('url-manager.max_redirect_depth', 5);
        
        // Prevent infinite recursion
        if ($depth > $maxDepth) {
            return null;
        }

        $url = self::where('slug', $slug)->first();

        if (! $url) {
            return null;
        }

        // Handle redirects
        if ($url->status === self::STATUS_REDIRECT && $url->redirect_to) {
            return self::findBySlug($url->redirect_to, $depth + 1);
        }

        return $url;
    }

    /**
     * Get the full URL path
     */
    public function getFullPath(): string
    {
        return '/'.ltrim($this->slug, '/');
    }

    /**
     * Get the absolute URL
     */
    public function getAbsoluteUrl(): string
    {
        return url($this->getFullPath());
    }

    /**
     * Generate a unique slug for a model
     */
    public static function generateUniqueSlug($model): string
    {
        $baseSlug = Str::slug($model->name ?? $model->title ?? '');

        if (empty($baseSlug)) {
            $baseSlug = Str::lower(class_basename($model)).'-'.$model->id;
        }

        $slug = $baseSlug;
        $count = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    /**
     * Create a redirect from old slug to new slug
     */
    public static function createRedirect(string $oldSlug, string $newSlug, int $code = null): self
    {
        $code = $code ?? config('url-manager.default_redirect_code', 301);
        
        // Check if a URL with this slug already exists
        $existingUrl = self::where('slug', $oldSlug)->first();
        
        if ($existingUrl) {
            // Update existing URL to be a redirect
            $existingUrl->update([
                'redirect_to' => $newSlug,
                'redirect_code' => $code,
                'status' => self::STATUS_REDIRECT,
                'type' => self::TYPE_REDIRECT,
            ]);
            return $existingUrl;
        }
        
        // Create new redirect
        return self::create([
            'slug' => $oldSlug,
            'redirect_to' => $newSlug,
            'redirect_code' => $code,
            'status' => self::STATUS_REDIRECT,
            'type' => self::TYPE_REDIRECT,
            'urable_type' => self::class,
            'urable_id' => 0,
        ]);
    }

    /**
     * Record a visit to this URL
     */
    public function recordVisit(): void
    {
        if (!config('url-manager.track_visits', true)) {
            return;
        }
        
        $this->increment('visits');
        $this->update(['last_visited_at' => now()]);
    }

    /**
     * Touch the last_modified_at timestamp
     */
    public function touchLastModified(): void
    {
        $this->update(['last_modified_at' => now()]);
    }

    /**
     * Get SEO metadata
     */
    public function getSeoMetadata(): array
    {
        $defaultMeta = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'og_image' => null,
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
        ];

        // Merge with stored meta
        $meta = array_merge($defaultMeta, $this->meta ?? []);

        // Get metadata from the related model if available
        if ($this->urable && method_exists($this->urable, 'getSeoMetadata')) {
            $meta = array_merge($meta, $this->urable->getSeoMetadata());
        }

        return $meta;
    }

    /**
     * Get available URL types
     */
    public static function getTypes(): array
    {
        return config('url-manager.types', [
            self::TYPE_ENTITY => 'Entity',
            self::TYPE_CATEGORY => 'Category',
            self::TYPE_SELLER => 'Seller',
            self::TYPE_MENU => 'Menu',
            self::TYPE_BRAND => 'Brand',
            self::TYPE_PAGE => 'Page',
            self::TYPE_BLOG => 'Blog',
            self::TYPE_REDIRECT => 'Redirect',
        ]);
    }

    /**
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return config('url-manager.statuses', [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_REDIRECT => 'Redirect',
            self::STATUS_INACTIVE => 'Inactive',
        ]);
    }

    /**
     * Check if this URL should be indexed by search engines
     */
    public function shouldIndex(): bool
    {
        // Don't index redirects or inactive URLs
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        // Check if the model has a shouldIndex method
        if ($this->urable && method_exists($this->urable, 'shouldIndex')) {
            return $this->urable->shouldIndex();
        }

        return true;
    }

    /**
     * Get the sitemap priority for this URL
     */
    public function getSitemapPriority(): float
    {
        $priorities = config('url-manager.sitemap.priorities', []);
        
        return $priorities[$this->type] ?? config('url-manager.sitemap.default_priority', 0.5);
    }

    /**
     * Get the sitemap changefreq for this URL
     */
    public function getSitemapChangefreq(): string
    {
        if ($this->urable && method_exists($this->urable, 'getSitemapChangefreq')) {
            return $this->urable->getSitemapChangefreq();
        }

        return config('url-manager.sitemap.default_changefreq', 'weekly');
    }
}