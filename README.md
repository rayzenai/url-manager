# RayzenAI URL Manager

A comprehensive Laravel package for managing URLs, redirects, and sitemaps with Filament admin panel integration.

## Features

- 🔗 **Dynamic URL Management** - Manage all your application URLs from a central location
- 🔄 **301/302 Redirects** - Create and manage URL redirects with configurable status codes
- 🗺️ **Automatic Sitemap Generation** - Generate XML sitemaps with support for large sites
- 📊 **Visit Tracking** - Track URL visits and analytics
- 🎨 **Filament Integration** - Full-featured admin panel for URL management
- 🏷️ **SEO Metadata** - Manage meta tags and Open Graph data
- 🚀 **Performance Optimized** - Efficient database queries with proper indexing
- 🔒 **Redirect Loop Protection** - Automatic detection and prevention of circular redirects

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Filament 4.0+

## Installation

### Step 1: Install via Composer

```bash
composer require rayzenai/url-manager
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=url-manager-config
```

### Step 3: Run Migrations

```bash
php artisan vendor:publish --tag=url-manager-migrations
php artisan migrate
```

### Step 4: Register with Filament

Add the plugin to your Filament panel configuration (typically in `app/Providers/Filament/AdminPanelProvider.php`):

```php
use RayzenAI\UrlManager\UrlManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other configuration
        ->plugin(UrlManagerPlugin::make());
}
```

### Step 5: Configure Your Models

Add the `HasUrl` trait to any model that needs URL management:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RayzenAI\UrlManager\Traits\HasUrl;

class Product extends Model
{
    use HasUrl;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active', // Required for URL visibility control
        // ... other fields
    ];

    /**
     * Define the URL path for this model
     * Required by HasUrl trait
     */
    public function webUrlPath(): string
    {
        return 'products/' . $this->slug;
    }

    /**
     * Define Open Graph tags for SEO
     * Optional but recommended
     */
    public function ogTags(): array
    {
        return [
            'title' => $this->name,
            'description' => $this->description,
            'image' => $this->image_url,
            'type' => 'product',
        ];
    }

    /**
     * Define sitemap change frequency
     * Optional - defaults to 'weekly'
     */
    public function getSitemapChangefreq(): string
    {
        return 'daily';
    }
}
```

### Step 6: Register Routes (Optional)

If you want to use the package's URL handling, add this to your `routes/web.php`:

```php
// Add at the END of your routes file (must be last)
Route::fallback([\RayzenAI\UrlManager\Http\Controllers\UrlController::class, 'handle']);
```

## Usage

### Creating URLs for Existing Models

Generate URLs for all models that use the HasUrl trait:

```bash
php artisan urls:generate
```

Or for a specific model:

```bash
php artisan urls:generate "App\Models\Product"
```

### Creating Redirects

#### Via Filament Admin

1. Navigate to the URLs section in your Filament admin panel
2. Click "Create Redirect"
3. Enter the source and destination URLs
4. Choose redirect type (301 or 302)

#### Programmatically

```php
use RayzenAI\UrlManager\Models\Url;

// Create a permanent redirect
Url::createRedirect('old-page', 'new-page', 301);

// Create a temporary redirect
Url::createRedirect('summer-sale', 'products/sale', 302);
```

### Generating Sitemaps

Generate a sitemap with all active URLs:

```bash
php artisan sitemap:generate
```

For large sites (>10,000 URLs), the package automatically creates multiple sitemap files with an index.

### Accessing URLs in Your Application

```php
// Get the full URL for a model
$product = Product::find(1);
echo $product->webUrl(); // https://yoursite.com/products/my-product

// Get the admin URL
echo $product->adminUrl(); // /admin/products/1/edit

// Check if a model's URL is active
if ($product->url && $product->url->status === 'active') {
    // URL is active
}
```

### Visit Tracking

Visits are automatically tracked when using the package's URL controller. Access visit data:

```php
$url = $product->url;
echo $url->visits; // Total visits
echo $url->last_visited_at; // Last visit timestamp
```

## Configuration

The configuration file `config/url-manager.php` allows you to customize:

```php
return [
    // Database table name
    'table_name' => 'urls',
    
    // URL types available in your application
    'types' => [
        'product' => 'Product',
        'category' => 'Category',
        'page' => 'Page',
        // Add your custom types
    ],
    
    // Maximum redirect chain depth (prevents infinite loops)
    'max_redirect_depth' => 5,
    
    // Visit tracking
    'track_visits' => true,
    'visit_queue' => 'low', // Queue for visit tracking jobs
    
    // Sitemap configuration
    'sitemap' => [
        'enabled' => true,
        'path' => public_path('sitemap.xml'),
        'max_urls_per_file' => 10000,
        'default_changefreq' => 'weekly',
        'default_priority' => 0.5,
        'priorities' => [
            'product' => 0.8,
            'category' => 0.9,
            'page' => 0.6,
        ],
    ],
    
    // Filament admin panel
    'filament' => [
        'enabled' => true,
        'navigation_group' => 'System',
        'navigation_icon' => 'heroicon-o-link',
        'navigation_sort' => 100,
    ],
];
```

## Advanced Features

### Custom URL Types

Register custom URL types in your configuration:

```php
'types' => [
    'product' => 'Product',
    'article' => 'Article',
    'custom_type' => 'Custom Type',
],
```

### SEO Metadata

Models can provide SEO metadata through the `getSeoMetadata()` method:

```php
public function getSeoMetadata(): array
{
    return [
        'title' => $this->seo_title ?? $this->name,
        'description' => $this->seo_description ?? $this->description,
        'keywords' => $this->seo_keywords,
        'og_image' => $this->featured_image,
        'og_type' => 'article',
        'twitter_card' => 'summary_large_image',
    ];
}
```

### Event Handling

Listen for URL events in your application:

```php
// In a service provider or event listener
Event::listen('url-manager.url.visited', function ($url, $model) {
    // Log visit, send analytics, etc.
    Log::info("URL visited: {$url->slug}");
});
```

### Multiple Sitemap Support

For sites with more than 10,000 URLs, the package automatically generates multiple sitemap files:

```
sitemap.xml         (index file)
sitemap-0.xml       (first 10,000 URLs)
sitemap-1.xml       (next 10,000 URLs)
...
```

## Filament Admin Panel

The package includes a complete Filament resource with:

- **URL Listing** - Search, filter, and sort URLs
- **Create/Edit Forms** - Manage URL details and metadata
- **Redirect Creation** - Quick action to create 301/302 redirects
- **Sitemap Generation** - Generate and view sitemaps
- **Bulk Actions** - Activate/deactivate multiple URLs
- **Visit Statistics** - View visit counts and last visited times

### Dashboard Widgets

The package provides two dashboard widgets:

1. **URL Stats Overview** - Displays total URLs, redirects, and visit statistics
2. **Top URLs Table** - Shows the 10 most visited URLs with their metrics

## Best Practices

1. **Always include `is_active` field** in models using HasUrl trait
2. **Implement `webUrlPath()` method** to define URL structure
3. **Use meaningful slugs** for SEO optimization
4. **Set up redirects** when changing URL structures
5. **Generate sitemaps regularly** (via cron job)
6. **Monitor redirect chains** to avoid deep nesting
7. **Use appropriate HTTP status codes** (301 for permanent, 302 for temporary)

## Testing

Run the package tests:

```bash
composer test
```

## Troubleshooting

### URLs not generating for models

Ensure your model:
- Uses the `HasUrl` trait
- Has an `is_active` field
- Implements the `webUrlPath()` method

### Sitemap not accessible

Check that:
- Sitemap generation is enabled in config
- The public directory is writable
- Routes are properly registered

### Redirects not working

Verify:
- The fallback route is registered last in `routes/web.php`
- No other routes are conflicting
- Redirect depth limit hasn't been exceeded

## Contributing

Contributions are welcome! Please submit pull requests with tests.

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/rayzenai/url-manager/issues).

## Credits

Created by [Kiran Timsina](https://github.com/kirantimsina) at RayzenAI.