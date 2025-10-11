# RayzenAI URL Manager

A comprehensive Laravel package for managing URLs, redirects, and sitemaps with Filament admin panel integration.

## Features

- 🔗 **Dynamic URL Management** - Manage all your application URLs from a central location
- 🔄 **301/302 Redirects** - Create and manage URL redirects with configurable status codes
- 🗺️ **Automatic Sitemap Generation** - Generate XML sitemaps with support for large sites
- 📊 **Visit Tracking** - Track URL visits with country detection, device info, and mobile app support
- 🎨 **Filament Integration** - Full-featured admin panel for URL management
- 🏷️ **SEO Metadata** - Manage meta tags and Open Graph data
- 🚀 **Performance Optimized** - Efficient database queries with proper indexing
- 🔒 **Redirect Loop Protection** - Automatic detection and prevention of circular redirects

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Filament 4.0+
- Stevebauman/Location 7.0+ with MaxMind database (for visitor country detection)
- kirantimsina/file-manager (optional, for media SEO functionality)

## Installation

### Step 1: Install via Composer

```bash
composer require rayzenai/url-manager
```

### Optional: Install File Manager for Enhanced SEO

For complete media SEO functionality, install the companion file-manager package:

```bash
composer require kirantimsina/file-manager
```

This package provides:
- Media metadata tracking with SEO titles
- Image optimization and compression
- Enhanced file upload components for Filament

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=url-manager-config
```

### Step 3: Configure Location Service (Required for visitor tracking)

The URL Manager uses the Stevebauman/Location package to detect visitor countries from IP addresses. You need to set up MaxMind's GeoIP database:

#### Option A: Use Local Database (Recommended)

1. Download the free GeoLite2 City database from [MaxMind](https://dev.maxmind.com/geoip/geoip2/geolite2/)
2. Create a free account and download `GeoLite2-City.mmdb`
3. Place the file in your Laravel project: `database/maxmind/GeoLite2-City.mmdb`
4. Publish and configure the Location package:

```bash
php artisan vendor:publish --provider="Stevebauman\Location\LocationServiceProvider"
```

5. Update `config/location.php`:

```php
'driver' => Stevebauman\Location\Drivers\MaxMind::class,

'maxmind' => [
    'local' => [
        'type' => 'city', // or 'country' for smaller file
        'path' => database_path('maxmind/GeoLite2-City.mmdb'),
    ],
],
```

#### Option B: Use Web Service

Configure MaxMind web service in your `.env`:

```env
MAXMIND_USER_ID=your_user_id
MAXMIND_LICENSE_KEY=your_license_key
```

### Step 4: Run Migrations

```bash
php artisan vendor:publish --tag=url-manager-migrations
php artisan migrate
```

This will create the following tables:
- `urls` - For managing URLs and redirects
- `url_visits` - For tracking visitor analytics
- `google_search_console_settings` - For storing Google Search Console credentials securely

### Step 5: Register with Filament

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
        'is_active', // Or 'active' - configurable via activeUrlField() method
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
     * Define the active field name (optional)
     * Override this if your model uses 'active' instead of 'is_active'
     */
    public function activeUrlField(): string
    {
        return 'active'; // Default is 'is_active'
    }

    /**
     * Enable automatic view count tracking
     * Optional - implement this to track views on your model
     */
    public function getViewCountColumn(): ?string
    {
        return 'view_count'; // Return null if no view counting needed
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

### Checking Your Configuration

Before you start using URL Manager, check that all your models are properly configured:

```bash
# Check all models using HasUrl trait
php artisan url-manager:check

# Check a specific model
php artisan url-manager:check "App\Models\Product"
```

This command will verify:
- ✅ `webUrlPath()` method is implemented
- ✅ `is_active` (or custom active field) exists in database
- ✅ `getViewCountColumn()` is configured correctly
- ✅ SEO methods (`ogTags()`, `getSeoMetadata()`) are present
- ✅ URL records have been generated
- ⚠️  Warnings for optional but recommended features

### Creating New Models with HasUrl

Generate a new model with HasUrl trait and all required methods pre-configured:

```bash
# Create a basic model
php artisan url-manager:make-model Product

# Create model with migration
php artisan url-manager:make-model Product --migration

# Create model with migration, factory, and seeder
php artisan url-manager:make-model Product --all
```

The generated model includes:
- HasUrl trait already configured
- `webUrlPath()` method with sensible defaults
- `getViewCountColumn()` for automatic view tracking
- `ogTags()` and `getSeoMetadata()` for SEO
- Proper fillable fields and casts

### Using the UrlManager Facade

For common URL operations, use the `UrlManager` facade:

```php
use RayzenAI\UrlManager\Facades\UrlManager;

// Generate URL for a model
$product = Product::find(1);
UrlManager::generateUrl($product);

// Track a visit manually
UrlManager::trackVisit($product, auth()->id());

// Create a redirect
UrlManager::createRedirect('old-url', 'new-url', 301);

// Find URL by slug
$url = UrlManager::findBySlug('products/my-product');

// Get visit count
$visits = UrlManager::getVisitCount($product);

// Delete URL
UrlManager::deleteUrl($product);
```

### Creating URLs for Existing Models

Generate URLs for all models that use the HasUrl trait:

```bash
php artisan urls:generate
```

Or for a specific model:

```bash
php artisan urls:generate "App\Models\Product"
```

#### Handling Large Datasets

For large datasets with thousands of records, you may need to increase PHP memory and execution limits:

```bash
# Increase PHP memory limit and execution time
php -d memory_limit=2G -d max_execution_time=0 artisan urls:generate

# Or generate for specific models one at a time
php artisan urls:generate "App\Models\Product"
php artisan urls:generate "App\Models\Category"
php artisan urls:generate "App\Models\Blog"
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
   - **Add your Service Account credentials**:
     - Open your downloaded JSON file in a text editor
     - Copy the entire JSON content
     - Paste it into the "Service Account JSON" field
     - The service account email will be automatically extracted
   - Click "Save Settings"
   - Use "Test Connection" to verify the setup is working
   
   **Why Database Storage?**
   - Credentials are encrypted and stored securely in your database
   - Survives deployments (no need to re-upload files)
   - No file system dependencies
   - Easier to manage in production environments

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
- **Invalid JSON error**: Ensure you're copying the complete JSON content from the credentials file, including all brackets

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

The package automatically tracks URL visits, but to track view counts on your models, you need to implement the `getViewCountColumn()` method:

```php
class Product extends Model
{
    use HasUrl;

    protected $fillable = [
        'name',
        'slug',
        'view_count', // Add your view count column
        // ...
    ];

    /**
     * Enable automatic view count tracking
     * When visitors access this model's URL, the view_count column will be incremented
     */
    public function getViewCountColumn(): ?string
    {
        return 'view_count'; // Return null if you don't want view counting
    }
}
```

**Important**: Make sure your database migration includes the view count column:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->unsignedBigInteger('view_count')->default(0); // Add this
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

The package provides two ways to track visits:

#### Method 1: Using Middleware (Recommended for Livewire & API Routes)

Register the middleware in your application:

**For Laravel 11** - Add to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'track-url-visits' => \RayzenAI\UrlManager\Http\Middleware\TrackUrlVisits::class,
    ]);
})
```

**For Laravel 10 and below** - Add to `app/Http/Kernel.php`:
```php
protected $middlewareAliases = [
    // ...
    'track-url-visits' => \RayzenAI\UrlManager\Http\Middleware\TrackUrlVisits::class,
];
```

Then apply the middleware to your routes:

```php
// For Livewire components
Route::get('/property/{slug}', PropertyDetails::class)
    ->middleware('track-url-visits')
    ->name('property');

// For API routes
Route::middleware(['track-url-visits'])->group(function () {
    Route::get('/api/products/{slug}', [ProductController::class, 'show']);
    Route::get('/api/categories/{slug}', [CategoryController::class, 'show']);
});

// Works with any route type - controllers, closures, Livewire, Inertia, etc.
Route::middleware(['auth', 'track-url-visits'])->group(function () {
    Route::get('/dashboard', Dashboard::class);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

The middleware automatically:
- Matches the request path against URLs in the database
- Records visits asynchronously via queued jobs
- Captures IP address, user agent, referrer, and authenticated user ID
- **Increments the model's view_count** if `getViewCountColumn()` is implemented
- Works transparently without modifying your controllers or components

#### Method 2: Using Fallback Route

If you use the package's fallback route controller:

```php
// Add at the END of your routes/web.php
Route::fallback([\RayzenAI\UrlManager\Http\Controllers\UrlController::class, 'handle']);
```

Visits are automatically tracked for any URL managed by the package.

#### Accessing Visit Data

```php
// URL-level visit tracking (always available)
$url = $product->url;
echo $url->visits; // Total visits on the URL record
echo $url->last_visited_at; // Last visit timestamp

// Model-level view tracking (if getViewCountColumn() is implemented)
echo $product->view_count; // Total views on the model itself
```

**Note**: The difference between URL visits and model view counts:
- **URL visits**: Tracked in the `urls` table and `url_visits` table (always enabled)
- **Model view counts**: Tracked on your model's `view_count` column (requires `getViewCountColumn()` implementation)

### Visitor Analytics Features

The URL Manager provides comprehensive visitor tracking with the following features:

#### Country Detection
- Automatically detects visitor's country from IP address using MaxMind GeoIP database
- Displays country flags (🇺🇸 🇬🇧 🇳🇵 🇮🇳) in the admin panel
- Filter visits by country in Filament resource

#### Mobile App Detection
The package intelligently detects mobile app traffic through:
- **API Source Parameters**: Recognizes `source=android` or `source=ios` parameters
- **User-Agent Analysis**: Detects Flutter, React Native, Expo, and other mobile frameworks
- **HTTP Client Detection**: Identifies OkHttp (Android) and Alamofire (iOS) clients

#### Populate Existing Data
If you have existing visitor data without country codes, run:

```bash
php artisan url-manager:populate-country-codes
```

This command will:
- Process all URL visits without country codes
- Resolve countries from IP addresses
- Update records with detected country codes

#### Visitor Information Tracked
- **IP Address** with country flag
- **Country Code** with flag emoji display
- **Browser/App** type and version
- **Device Type** (Desktop, Mobile, Tablet)
- **Referrer URL**
- **User** (if authenticated)
- **Visit Timestamp**
- **Metadata** (additional custom data)

### Testing Referrer Tracking

The package includes a built-in test page to verify that referrer tracking is working correctly. This is especially useful when setting up the package or debugging referrer capture issues.

**Access the test page** (only available in non-production environments):
```
https://yourdomain.com/_url-manager/test
```

The test page provides:
- A visual interface to add and visit entity URLs
- Automatic referrer capture via JavaScript (works even when browsers block HTTP Referer headers)
- Real-time verification of referrer tracking
- Instructions for checking captured referrers in Filament admin panel

**How it works:**
1. Visit the test page at `/_url-manager/test`
2. Add your entity slugs (e.g., `entities/my-product`, `blog/my-post`)
3. Click on the links to visit those pages
4. Check Filament admin panel → URL Visits → Toggle "Referrer" column
5. You should see the test page URL as the referrer

The test page uses a multi-layer approach to ensure referrers are captured:
- JavaScript-based capture (passes referrer as `?ref=` query parameter)
- HTTP Referer header support
- Multiple fallback sources (headers and server variables)

**Note:** The test route is automatically disabled in production environments for security.

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
        
        // Image sitemap configuration
        'images' => [
            'enabled' => true,
            'max_images_per_file' => 5000,
            'image_size' => 'large', // Use optimized size: 'thumb', 'medium', 'large', 'full', or null for original
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

### UrlManager Facade

The `UrlManager` facade provides a convenient API for common URL operations:

```php
use RayzenAI\UrlManager\Facades\UrlManager;

// Generate or update URL for a model
$url = UrlManager::generateUrl($product);

// Manually track a visit (normally handled automatically)
UrlManager::trackVisit($product, auth()->id(), ['source' => 'mobile']);

// Create redirects programmatically
UrlManager::createRedirect('/old-path', '/new-path', 301);

// Find URLs by slug
$url = UrlManager::findBySlug('products/my-product');

// Get all redirects
$redirects = UrlManager::getRedirects();

// Get visit count for a model
$totalVisits = UrlManager::getVisitCount($product);

// Delete URL for a model
UrlManager::deleteUrl($product);
```

**When to use the facade:**
- Creating redirects programmatically
- Manual visit tracking (in addition to automatic tracking)
- Quick URL lookups by slug
- Debugging or admin tools

**When NOT to use it:**
- URL creation is automatic via HasUrl trait events
- Visit tracking is automatic via middleware/fallback route
- Use the facade only when you need programmatic control

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

### Media SEO with File Manager

If you have the `kirantimsina/file-manager` package installed, you can enhance your SEO by managing media metadata:

#### Populate SEO Titles for Images

Generate SEO-friendly titles for all your media files:

```bash
# Generate SEO titles for all media
php artisan file-manager:populate-seo-titles

# Generate for specific model only
php artisan file-manager:populate-seo-titles --model=Product

# Dry run to see what would be generated
php artisan file-manager:populate-seo-titles --dry-run

# Overwrite existing SEO titles
php artisan file-manager:populate-seo-titles --overwrite
```

The command automatically generates SEO-friendly titles based on:
- Parent model's name/title
- Media field context (e.g., "Featured Image", "Gallery")
- Clean filename processing
- Removes special characters from beginning/end for cleaner SEO

#### Image Sitemap Generation

Generate a dedicated image sitemap for better image SEO:

```bash
# Generate image sitemap with optimized SEO titles
php artisan sitemap:generate-images

# Include specific models only
php artisan sitemap:generate-images --model=Product --model=Blog

# Set custom maximum images per sitemap file
php artisan sitemap:generate-images --max-urls=5000
```

**Features:**
- Uses pre-populated SEO titles from media_metadata table for optimal performance
- Only includes images with meaningful SEO titles (excludes internal/system images)
- Automatically creates index files for large image collections
- Generates Google Image sitemap format with proper XML namespace
- Includes image location, title, and caption metadata
- Uses optimized image sizes instead of originals for better performance

**Performance Optimization:**
- Direct database queries avoid expensive polymorphic lookups
- Chunked processing for handling millions of images
- Only processes images from SEO-enabled models (configured in file-manager)

#### Image Size Configuration

Configure the image size used in sitemaps in `config/url-manager.php`:

```php
'sitemap' => [
    'images' => [
        'enabled' => true,
        'max_images_per_file' => 5000,
        
        // Configure which size to use for sitemap images
        // Options: 'icon', 'thumb', 'medium', 'large', 'full', etc.
        // Set to null to use original images
        'image_size' => 'large', // Default: 720px height
    ],
],
```

The available sizes are defined in your `config/file-manager.php`:

```php
'image_sizes' => [
    'icon' => 64,       // 64px height
    'thumb' => 240,     // 240px height  
    'medium' => 480,    // 480px height
    'large' => 720,     // 720px height (recommended for sitemaps)
    'full' => 1080,     // 1080px height
],
```

#### SEO Title Configuration

Control which models receive SEO titles in `config/file-manager.php`:

```php
'seo' => [
    'enabled_models' => [
        'App\Models\Product',
        'App\Models\Category',
        'App\Models\Blog',
        // Models that should have SEO titles
    ],
    'excluded_models' => [
        'App\Models\User',
        'App\Models\Order',
        // Models that should NOT have SEO titles
    ],
],
```

#### Media Metadata in Sitemaps

When using file-manager, media files are automatically included in your sitemaps with proper SEO titles and metadata for better search engine indexing. The integration:
- Respects model configuration (enabled/excluded models)
- Uses cached SEO titles for fast generation
- Supports large-scale image collections with automatic file splitting

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

1. **Always include an active field** (`is_active` or `active`) in models using HasUrl trait
2. **Implement `webUrlPath()` method** to define URL structure
3. **Override `activeUrlField()` method** if using a field name other than `is_active`
4. **Use meaningful slugs** for SEO optimization
5. **Set up redirects** when changing URL structures
6. **Generate sitemaps regularly** (via cron job)
7. **Monitor redirect chains** to avoid deep nesting
8. **Use appropriate HTTP status codes** (301 for permanent, 302 for temporary)

## Testing

Run the package tests:

```bash
composer test
```

## Troubleshooting

### URLs not generating for models

Ensure your model:
- Uses the `HasUrl` trait
- Has an active field (`is_active` or `active` - configurable via `activeUrlField()` method)
- Implements the `webUrlPath()` method

### Common URL Generation Issues

1. **"URL with slug already exists for different model" Warning**
   - This occurs when multiple models have the same slug
   - Solution: Ensure unique slugs within each model type
   - The command skips duplicates to maintain data integrity

2. **Not all URLs are generated**
   - Check for duplicate slugs in your data
   - Verify models have unique slug values
   - For large datasets, increase PHP memory limit
   - Run the command multiple times if needed

3. **Models using 'active' instead of 'is_active'**
   - Override the `activeUrlField()` method in your model:
   ```php
   public function activeUrlField(): string
   {
       return 'active'; // Your model's active field name
   }
   ```

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