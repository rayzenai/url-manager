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

### Submitting Sitemaps to Search Engines

Since Google deprecated the ping endpoint in June 2023 and Bing has also discontinued their ping service, API credentials are now required for automated sitemap submission.

#### Setting up Google Search Console API

**Prerequisites**:
- A verified property in [Google Search Console](https://search.google.com/search-console)
- A Google Cloud Project with billing enabled (API has free tier)

##### Using Service Account Authentication

1. **Create a Google Cloud Project**:
   - Go to [Google Cloud Console](https://console.cloud.google.com)
   - Create a new project or select an existing one
   - Enable these APIs:
     - "Google Search Console API" 
     - "Search Console API" (if available)
   - Note: The "Indexing API" is separate and not needed for sitemap submission

2. **Create a Service Account**:
   - Navigate to "IAM & Admin" > "Service Accounts"
   - Click "Create Service Account"
   - Give it a name like "sitemap-submitter"
   - Click "Create and Continue"
   - Skip the optional role assignment (click "Continue")
   - Click "Done"
   - Find your new service account in the list and click on it
   - Go to the "Keys" tab
   - Click "Add Key" > "Create New Key"
   - Select "JSON" format
   - Click "Create" to download the JSON credentials file
   - **Keep this file secure** - it contains credentials for API access

3. **Add Service Account to Search Console**:
   - Go to [Google Search Console](https://search.google.com/search-console)
   - Select your property
   - Go to Settings > Users and permissions
   - Click "Add user"
   - Enter the service account email (found in the JSON file, looks like `service-account@project.iam.gserviceaccount.com`)
   - Select "Owner" permission level
   - Click "Add"

4. **Configure in Admin Panel**:
   - Navigate to the Google Search Console settings page in your Filament admin panel
   - Toggle "Enable Google Search Console Integration" to ON
   - Enter your site URL or domain property:
     - **For URL-prefix property**: `https://www.yoursite.com` (must match exactly)
     - **For Domain property**: `sc-domain:yoursite.com` (recommended - covers all subdomains and protocols)
   - **Add the JSON credentials file**:
     - Save the JSON file to `storage/app/google-credentials/`
     - Enter the path relative to storage directory (e.g., `app/google-credentials/service-account.json`)
     - Or enter the full absolute path if stored elsewhere
   - The service account email will be automatically extracted and displayed
   - Click "Save Settings"
   - Use "Test Connection" to verify the setup is working
   
   **Alternative Manual Setup** (if you prefer to configure via .env):
   - Save the JSON file to your server (e.g., `storage/app/google-credentials/service-account.json`)
   - Add to your `.env` file:
     ```
     GOOGLE_SEARCH_CONSOLE_ENABLED=true
     GOOGLE_SEARCH_CONSOLE_SITE_URL=sc-domain:yoursite.com
     # Or for URL-prefix: GOOGLE_SEARCH_CONSOLE_SITE_URL=https://www.yoursite.com
     GOOGLE_APPLICATION_CREDENTIALS=/full/path/to/your/service-account.json
     GOOGLE_SERVICE_ACCOUNT_EMAIL=service-account@project.iam.gserviceaccount.com
     ```
   - The file should NOT be in a publicly accessible directory

#### Submitting Sitemaps

Once configured, you can submit sitemaps in multiple ways:

1. **Via Admin Panel**: 
   - Go to URLs management page in Filament
   - Click "Submit to Search Engines" button

2. **Via Command Line**:
   ```bash
   php artisan sitemap:submit
   ```

3. **Programmatically**:
   ```php
   use RayzenAI\UrlManager\Services\GoogleSearchConsoleService;
   
   // Submit to Google only
   $result = GoogleSearchConsoleService::submitGoogleSitemap();
   
   // Submit to all search engines (Google + Bing note)
   $result = GoogleSearchConsoleService::submitToAllSearchEngines();
   ```

#### Troubleshooting Google Search Console

**Service Account Issues**:
- **"API not configured" error**: Ensure you've enabled the Google Search Console API in your Google Cloud Project
- **"Site not verified" error**: Make sure the service account email is added as a user in Search Console with "Owner" permissions
- **"Invalid credentials" error**: Check that the JSON file path is correct and the file is readable by the web server
- **403 Forbidden errors**: 
  - The service account may not have proper permissions. Verify it's added to Search Console with "Owner" role
  - Check if you're using the correct property format. If you have a domain property in Search Console, use `sc-domain:yoursite.com` format
  - Verify the exact property format in Search Console matches what you've configured
- **File path issues**: Enter paths relative to the storage directory (e.g., `app/google-credentials/file.json`) in the admin panel

**General Issues**:
- **"Invalid site URL" error**: The site URL must match exactly with how it's registered in Search Console (including www/non-www, https/http)
- **No sitemaps found**: The site may not have any sitemaps submitted yet. Use "Submit Sitemap Now" button to submit
- **Rate limiting**: Google Search Console API has quotas. Check your Google Cloud Console for usage limits
- **Connection test passes but submission fails**: Check that sitemap.xml exists and is accessible at the expected URL

#### Note on Bing

Bing has also discontinued their ping endpoint. Sitemaps must be manually submitted through [Bing Webmaster Tools](https://www.bing.com/webmasters).

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