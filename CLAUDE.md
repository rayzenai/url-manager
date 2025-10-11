# URL Manager Package - Developer Documentation

> **📋 IMPORTANT: This document MUST be updated whenever any feature changes are made to the package.**
>
> This document is designed for AI assistants (like Claude) and developers working on the URL Manager package. It provides a comprehensive overview of the architecture, key files, and how to modify different features.
>
> **Update Protocol:**
> - ✅ Update this file AFTER implementing any feature
> - ✅ Document new files, methods, and configurations
> - ✅ Update version history at the bottom
> - ✅ Keep code examples current
> - ✅ Document breaking changes prominently

---

## Table of Contents

1. [Package Overview](#package-overview)
2. [Quick File Finder](#quick-file-finder) - Find files instantly
3. [Architecture Overview](#architecture-overview)
   - [Complete Package Structure](#complete-package-structure)
   - [Key Models](#key-models)
4. [Key Files and Their Purpose](#key-files-and-their-purpose)
   - [Configuration Files](#configuration-files)
   - [Filament Admin Panel](#filament-admin-panel)
   - [Controllers and Routing](#controllers-and-routing)
   - [Visit Tracking System](#visit-tracking-system)
   - [Trait System](#trait-system)
   - [Commands](#commands)
   - [Google Search Console Integration](#google-search-console-integration)
   - [Package Service Provider](#package-service-provider)
   - [API Routes](#api-routes)
   - [Facades](#facades)
   - [Views and Templates](#views-and-templates)
   - [Testing and Development](#testing-and-development)
5. [Common Modification Scenarios](#common-modification-scenarios)
   - [Adding a New URL Type](#adding-a-new-url-type)
   - [Customizing Visit Analytics](#customizing-visit-analytics)
   - [Adding Middleware to URL Routes](#adding-middleware-to-url-routes)
   - [Custom Sitemap Generation](#custom-sitemap-generation)
6. [Database Schema](#database-schema)
7. [Events and Hooks](#events-and-hooks)
8. [Performance Considerations](#performance-considerations)
9. [Security Considerations](#security-considerations)
10. [Debugging Tips](#debugging-tips)
11. [Dependencies](#dependencies)
12. [Quick Command Reference](#quick-command-reference)
13. [Support and Resources](#support-and-resources)
14. [Version History](#version-history)

---

## Package Overview

**RayzenAI URL Manager** is a comprehensive Laravel package for managing URLs, redirects, visit tracking, and sitemaps with Filament admin panel integration.

**Key Features:**
- Dynamic URL management for polymorphic models
- 301/302 redirects with loop prevention
- Automatic sitemap generation (XML, Image, Video)
- Comprehensive visit tracking with analytics
- Google Search Console integration
- Filament admin panel resources
- SEO metadata management

---

## Quick File Finder

Need to find a specific file quickly? Use this table:

| Feature | File Path | Section in Docs |
|---------|-----------|-----------------|
| **Core Models** |
| URL records | `src/Models/Url.php` | [Key Models](#key-models) |
| Visit analytics | `src/Models/UrlVisit.php` | [Key Models](#key-models) |
| GSC credentials | `src/Models/GoogleSearchConsoleSetting.php` | [Key Models](#key-models) |
| **Controllers** |
| Main URL routing | `src/Http/Controllers/UrlController.php` | [Controllers and Routing](#controllers-and-routing) |
| API visit tracking | `src/Http/Controllers/TrackingController.php` | [API Routes](#api-routes) |
| **Services** |
| Visit tracking logic | `src/Services/VisitTracker.php` | [Visit Tracking System](#visit-tracking-system) |
| Facade service | `src/Services/UrlManagerService.php` | [Facades](#facades) |
| Google Search Console | `src/Services/GoogleSearchConsoleService.php` | [Google Search Console](#google-search-console-integration) |
| **Filament Resources** |
| URL management | `src/Filament/Resources/UrlResource.php` | [Filament Admin Panel](#filament-admin-panel) |
| Visit analytics | `src/Filament/Resources/UrlVisitResource.php` | [Filament Admin Panel](#filament-admin-panel) |
| GSC settings page | `src/Filament/Pages/GoogleSearchConsoleSettings.php` | [Google Search Console](#google-search-console-integration) |
| **Configuration & Bootstrap** |
| Main config | `config/url-manager.php` | [Configuration Files](#configuration-files) |
| Service provider | `src/UrlManagerServiceProvider.php` | [Package Service Provider](#package-service-provider) |
| Filament plugin | `src/UrlManagerPlugin.php` | [Filament Admin Panel](#filament-admin-panel) |
| **Routes** |
| Web routes | `routes/web.php` | [Controllers and Routing](#controllers-and-routing) |
| API routes | `routes/api.php` | [API Routes](#api-routes) |
| **Traits & Jobs** |
| HasUrl trait | `src/Traits/HasUrl.php` | [Trait System](#trait-system) |
| Async visit recording | `src/Jobs/RecordUrlVisit.php` | [Visit Tracking System](#visit-tracking-system) |
| Visit tracking middleware | `src/Http/Middleware/TrackUrlVisits.php` | [Visit Tracking System](#visit-tracking-system) |
| **Testing** |
| Referrer test page | `test.html` | [Testing and Development](#testing-and-development) |
| **Migrations** |
| URLs table | `database/migrations/2025_01_01_000000_create_urls_table.php` | [Database Schema](#database-schema) |
| Visits table | `database/migrations/2025_09_01_000002_create_url_visits_table.php` | [Database Schema](#database-schema) |
| GSC settings | `database/migrations/2025_01_01_000001_create_google_search_console_settings_table.php` | [Database Schema](#database-schema) |

---

## Architecture Overview

### Complete Package Structure

```
url-manager/
├── config/
│   └── url-manager.php                  # Main configuration
├── database/
│   └── migrations/                      # Package migrations
│       ├── 2025_01_01_000000_create_urls_table.php
│       ├── 2025_01_01_000001_create_google_search_console_settings_table.php
│       └── 2025_09_01_000002_create_url_visits_table.php
├── resources/
│   └── views/                           # Blade templates
│       ├── filament/                    # Filament-specific views
│       ├── show.blade.php               # Default entity view
│       └── sitemap.blade.php            # Sitemap template
├── routes/
│   ├── api.php                          # API routes (/api/url-manager/*)
│   └── web.php                          # Web routes (fallback, test page)
├── src/
│   ├── Commands/                        # Artisan commands
│   │   ├── CheckUrlManagerCommand.php       # php artisan url-manager:check
│   │   ├── GenerateSitemap.php              # php artisan sitemap:generate
│   │   ├── GenerateAllSitemaps.php          # php artisan sitemap:generate-all
│   │   ├── GenerateImageSitemap.php         # php artisan sitemap:generate-images
│   │   ├── GenerateVideoSitemap.php         # php artisan sitemap:generate-videos
│   │   ├── GenerateUrlsForModels.php        # php artisan urls:generate
│   │   ├── MakeModelCommand.php             # php artisan url-manager:make-model
│   │   ├── PopulateUrlVisitCountryCodes.php # php artisan url-manager:populate-country-codes
│   │   └── SubmitSitemapToGoogle.php        # php artisan sitemap:submit
│   ├── Facades/
│   │   └── UrlManager.php               # Facade for UrlManagerService
│   ├── Filament/
│   │   ├── Pages/                       # Custom admin pages
│   │   │   └── GoogleSearchConsoleSettings.php
│   │   ├── Resources/
│   │   │   ├── UrlResource.php          # Main URL management
│   │   │   ├── UrlVisitResource.php     # Visit analytics
│   │   │   └── */Pages/                 # List/Create/Edit pages
│   │   └── Widgets/                     # Dashboard widgets
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── UrlController.php        # Main URL routing
│   │   │   └── TrackingController.php   # API visit tracking
│   │   └── Middleware/
│   │       └── TrackUrlVisits.php       # Visit tracking middleware
│   ├── Jobs/
│   │   └── RecordUrlVisit.php           # Async visit recording
│   ├── Models/
│   │   ├── Url.php                      # URL records
│   │   ├── UrlVisit.php                 # Visit analytics
│   │   └── GoogleSearchConsoleSetting.php
│   ├── Services/
│   │   ├── UrlManagerService.php        # Core service (Facade)
│   │   ├── VisitTracker.php             # Visit tracking logic
│   │   └── GoogleSearchConsoleService.php
│   ├── Traits/
│   │   └── HasUrl.php                   # Model trait
│   ├── UrlManagerPlugin.php             # Filament plugin
│   └── UrlManagerServiceProvider.php    # Package service provider
├── test.html                            # Referrer test page
├── README.md                            # User documentation
└── CLAUDE.md                            # This file
```

### Key Models

1. **`Url`** (`src/Models/Url.php`)
   - Stores URL records for all models
   - Polymorphic relationship via `urable_type` and `urable_id`
   - Handles redirects and status management
   - Tracks basic visit counts

2. **`UrlVisit`** (`src/Models/UrlVisit.php`)
   - Detailed visitor analytics
   - IP address, country, browser, device tracking
   - Referrer capture and user association

3. **`GoogleSearchConsoleSetting`** (`src/Models/GoogleSearchConsoleSetting.php`)
   - Encrypted credential storage
   - Service account configuration

---

## Key Files and Their Purpose

### Configuration Files

#### `config/url-manager.php`
**Purpose:** Main configuration for the entire package

**Key Sections:**
```php
'types' => []           // Define URL types (entity, blog, etc.)
'track_visits' => true  // Enable/disable visit tracking
'visit_queue' => 'low'  // Queue for async visit recording
'sitemap' => [          // Sitemap generation settings
    'enabled' => true,
    'max_urls_per_file' => 10000,
    'priorities' => [],  // Priority per URL type
]
'filament' => [         // Admin panel configuration
    'navigation_group' => 'System',
    'navigation_sort' => 100,
]
```

**When to modify:**
- Adding new URL types for your application
- Changing sitemap generation settings
- Customizing Filament navigation
- Adjusting visit tracking behavior

---

### Filament Admin Panel

#### `src/Filament/Resources/UrlResource.php`
**Purpose:** Main admin interface for URL management

**Key Features:**
- Create/edit URLs manually
- View URL statistics (visits, last visited)
- Create redirects
- Generate and submit sitemaps
- Bulk actions (activate, deactivate, delete)

**Key Methods:**
```php
public static function table(Table $table)       // Table columns and filters
public static function form(Form $form)          // Create/edit form
public static function getRelations()            // Related resources (visits)
public static function getHeaderActions()        // Page actions (submit sitemap)
```

**When to modify:**
- Add custom columns to URL listing
- Add new filters or search fields
- Create custom bulk actions
- Add validation rules for URL creation

**Related Files:**
- `src/Filament/Resources/UrlResource/Pages/ListUrls.php`
- `src/Filament/Resources/UrlResource/Pages/CreateUrl.php`
- `src/Filament/Resources/UrlResource/Pages/EditUrl.php`

---

#### `src/Filament/Resources/UrlVisitResource.php`
**Purpose:** Analytics dashboard for visit tracking

**Key Features:**
- View all URL visits with details
- Filter by country, device, date range
- Real-time updates (polls every 30s)
- Country flags and device badges
- Referrer tracking display

**Key Columns:**
```php
- url.slug           // Which URL was visited
- ip_address         // Visitor IP (toggleable, hidden by default)
- country_code       // Country with flag emoji
- user.name          // Authenticated user or "Anonymous"
- browser            // Browser/app type with version
- device             // Desktop/Mobile/Tablet badge
- referer            // Referrer URL (toggleable, hidden by default)
- created_at         // Visit timestamp with "X ago" format
```

**When to modify:**
- Add custom analytics columns
- Create new visit filters
- Add custom aggregation widgets
- Export visit data

**Related Files:**
- `src/Filament/Resources/UrlVisitResource/Pages/ListUrlVisits.php`
- `src/Filament/Resources/UrlVisitResource/Widgets/UrlVisitStats.php`

---

#### `src/UrlManagerPlugin.php`
**Purpose:** Register the package with Filament panel

**Registration in Application:**
```php
// app/Providers/Filament/AdminPanelProvider.php
use RayzenAI\UrlManager\UrlManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            UrlManagerPlugin::make()
        ]);
}
```

**When to modify:**
- Change default navigation group
- Add custom pages to the plugin
- Register additional widgets

---

### Controllers and Routing

#### `src/Http/Controllers/UrlController.php`
**Purpose:** Handle incoming requests for managed URLs

**Key Methods:**
```php
handle()              // Main router - determines URL action
handleActive()        // Render view for active URLs
handleRedirect()      // Process 301/302 redirects
sitemap()            // Serve sitemap.xml
```

**How it works:**
1. User visits `/some-url`
2. Laravel fallback route passes to `UrlController::handle()`
3. Looks up `Url` record by slug
4. Checks status (active, redirect, inactive)
5. Renders appropriate view or redirects
6. Automatically tracks visit via `VisitTracker`

**When to modify:**
- Add custom URL handling logic
- Change view resolution strategy
- Add middleware to URL handling
- Customize redirect behavior

**Related Routes:**
```php
// routes/web.php (in package)
Route::get('/sitemap.xml', [UrlController::class, 'sitemap']);
Route::get('/_url-manager/test', function() { /* test page */ }); // Non-production only
Route::fallback([UrlController::class, 'handle']);
```

---

### Visit Tracking System

#### `src/Services/VisitTracker.php`
**Purpose:** Central service for tracking URL visits

**Key Features:**
- Captures referrer from multiple sources
- Dispatches async job for recording
- Fires `url-manager.url.visited` event

**Referrer Capture Strategy:**
```php
$referer = $request->input('ref')              // JavaScript fallback (query param)
    ?? $request->input('referer')              // Alternative query param
    ?? $request->header('referer')             // Standard HTTP header
    ?? $request->header('http_referer')        // Alternative header
    ?? $request->server('HTTP_REFERER');       // Server variable
```

**Why multiple sources?**
Browsers often block the HTTP `Referer` header for privacy/security reasons. The JavaScript fallback in the test page captures `document.referrer` and passes it as `?ref=` parameter to ensure tracking works reliably.

**When to modify:**
- Add custom metadata to visit records
- Change visit tracking logic
- Add custom event listeners
- Implement rate limiting

**Related Files:**
- `src/Jobs/RecordUrlVisit.php` - Async job that creates visit records
- `src/Http/Middleware/TrackUrlVisits.php` - Middleware for Livewire/API routes

---

#### `src/Models/UrlVisit.php`
**Purpose:** Store and manage visitor analytics

**Key Features:**
- Country detection via MaxMind GeoIP
- Mobile app detection (Flutter, React Native, etc.)
- Browser and device parsing
- Automatic view count incrementing

**Important Methods:**
```php
createFromRequest()              // Factory method to create visit from request
getCountryFlagAttribute()        // Emoji flag from country code
getStatistics()                  // Aggregated analytics for a URL
```

**Tracked Information:**
```php
protected $fillable = [
    'url_id',           // Which URL was visited
    'ip_address',       // Visitor IP
    'country_code',     // Detected country (e.g., 'US', 'NP')
    'browser',          // Browser name (e.g., 'Chrome', 'Safari')
    'browser_version',  // Browser version
    'device',           // 'desktop', 'mobile', 'tablet'
    'user_id',          // Authenticated user (nullable)
    'referer',          // Where visitor came from
    'meta',             // Additional metadata (JSON)
];
```

**When to modify:**
- Add custom visitor attributes
- Implement bot detection
- Add privacy controls (IP anonymization)
- Create custom analytics methods

---

### Trait System

#### `src/Traits/HasUrl.php`
**Purpose:** Add URL management capabilities to any model

**What it provides:**
```php
// Relationships
$model->url         // Get associated URL record
$model->visits      // Get all visit records

// Methods
$model->webUrl()    // Get full URL (https://site.com/path)
$model->adminUrl()  // Get admin edit URL

// Automatic URL management
- Creates URL on model creation
- Updates URL on model update
- Deletes URL on model deletion
```

**Required Model Methods:**
```php
// REQUIRED - Define URL structure
public function webUrlPath(): string
{
    return 'products/' . $this->slug;
}

// OPTIONAL - Override default active field name
public function activeUrlField(): string
{
    return 'is_active'; // Default
}

// OPTIONAL - Enable view count tracking
public function getViewCountColumn(): ?string
{
    return 'view_count'; // Or null to disable
}

// OPTIONAL - Provide SEO metadata
public function getSeoMetadata(): array
{
    return [
        'title' => $this->seo_title,
        'description' => $this->seo_description,
    ];
}
```

**When to modify:**
- Add custom URL generation logic
- Implement multi-language URL support
- Add custom SEO fields
- Create URL validation rules

---

### Commands

#### `php artisan urls:generate`
**Purpose:** Bulk generate URLs for models using HasUrl trait

**File:** `src/Commands/GenerateUrlsCommand.php`

**When to use:**
- After adding HasUrl trait to existing models
- Regenerating URLs after data migration
- Creating URLs for seeded data

**When to modify:**
- Add progress bars for large datasets
- Implement custom conflict resolution
- Add filtering options

---

#### `php artisan sitemap:generate`
**Purpose:** Generate XML sitemap with all active URLs

**File:** `src/Commands/GenerateSitemapCommand.php`

**Features:**
- Automatic splitting for >10,000 URLs
- Sitemap index generation
- Priority and change frequency configuration
- Last modified timestamps

**When to modify:**
- Add custom sitemap fields
- Implement dynamic priorities
- Add URL filtering logic
- Support additional sitemap types

---

#### `php artisan url-manager:check`
**Purpose:** Verify HasUrl trait configuration on models

**File:** `src/Commands/CheckUrlConfigurationCommand.php`

**Checks:**
- ✅ `webUrlPath()` implementation
- ✅ Active field existence in database
- ✅ View count column configuration
- ✅ URL record generation
- ⚠️ SEO method warnings

**When to modify:**
- Add additional validation checks
- Create auto-fix functionality
- Generate configuration templates

---

### Google Search Console Integration

#### `src/Services/GoogleSearchConsoleService.php`
**Purpose:** Submit sitemaps to Google Search Console API

**Key Features:**
- Service account authentication
- Automatic sitemap submission
- Connection testing
- Error handling and logging

**Configuration Flow:**
1. User uploads Service Account JSON in Filament
2. Credentials stored encrypted in database
3. Service extracts account email and decrypts JSON
4. Authenticates with Google Cloud API
5. Submits sitemap to Search Console property

**When to modify:**
- Add Bing Webmaster Tools integration
- Implement retry logic
- Add bulk sitemap submission
- Create submission scheduling

**Related Files:**
- `src/Filament/Pages/GoogleSearchConsoleSettings.php` - Admin settings page
- `src/Models/GoogleSearchConsoleSetting.php` - Credential storage

---

### Package Service Provider

#### `src/UrlManagerServiceProvider.php`
**Purpose:** Bootstrap the package - register routes, commands, migrations, and services

**Key Responsibilities:**
```php
public function configurePackage(Package $package)
{
    $package
        ->name('url-manager')
        ->hasConfigFile()           // Publishes config/url-manager.php
        ->hasViews()                // Registers resources/views
        ->hasRoute('web')           // Loads routes/web.php
        ->hasMigrations([...])      // Registers migrations
        ->hasCommands([...]);       // Registers Artisan commands
}
```

**Service Registration:**
```php
// Singleton service (accessible via Facade)
$this->app->singleton('url-manager', function ($app) {
    return new UrlManagerService();
});
```

**Middleware Registration:**
```php
// Automatically registers middleware alias
$router->aliasMiddleware('track-url-visits', TrackUrlVisits::class);

// Optionally auto-applies to web routes
if (config('url-manager.middleware.auto_apply', false)) {
    $router->pushMiddlewareToGroup('web', TrackUrlVisits::class);
}
```

**API Routes Registration:**
```php
// Registers routes with 'api/url-manager' prefix
Route::group(['prefix' => 'api/url-manager', 'middleware' => 'api'], function () {
    $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
});
```

**When to modify:**
- Add new commands to registration
- Change middleware auto-apply behavior
- Add custom service bindings
- Register additional routes

---

### API Routes

#### `routes/api.php`
**Purpose:** Provide API endpoints for visit tracking (e.g., from cached pages)

**Available Routes:**
```php
POST /api/url-manager/track-visit
```

**Use Case:**
When serving cached HTML (Varnish, Cloudflare, etc.), client-side JavaScript can call this endpoint to track visits:

```javascript
// Client-side tracking for cached pages
fetch('/api/url-manager/track-visit', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        slug: 'products/my-product',
        referrer: document.referrer
    })
});
```

#### `src/Http/Controllers/TrackingController.php`
**Purpose:** Handle API-based visit tracking

**Key Method:**
```php
public function track(Request $request)
{
    // Validates slug
    // Finds URL record
    // Calls VisitTracker::trackVisit()
    // Returns success response
}
```

**When to modify:**
- Add rate limiting to prevent abuse
- Add custom validation rules
- Track additional metadata from API calls
- Implement authentication requirements

---

### Facades

#### `src/Facades/UrlManager.php`
**Purpose:** Provide convenient static access to UrlManagerService

**Registration:**
The facade is bound to the `url-manager` service registered in the service provider.

**Usage:**
```php
use RayzenAI\UrlManager\Facades\UrlManager;

// All methods from UrlManagerService are available
UrlManager::generateUrl($model);
UrlManager::trackVisit($url, $userId, $metadata);
UrlManager::findBySlug('products/my-product');
```

**Available Methods:**
See "UrlManager Facade" section below for complete method list.

**When to modify:**
- Add new convenience methods to underlying service
- Add method aliases for backwards compatibility
- Document new facade methods

---

### Views and Templates

#### `resources/views/show.blade.php`
**Purpose:** Default view for displaying URL-managed entities

**Usage:**
Rendered when `UrlController::handleActive()` can't find a type-specific view.

**Variables Available:**
```blade
@extends('layouts.app')

@section('content')
    {{ $url }}      {{-- Url model instance --}}
    {{ $model }}    {{-- The actual entity (Product, Blog, etc.) --}}
@endsection
```

**Customization:**
Create type-specific views in your application:
```php
// These views take precedence:
resources/views/url/{type}.blade.php        // e.g., url/product.blade.php
resources/views/url-manager/{type}.blade.php
```

#### `resources/views/sitemap.blade.php`
**Purpose:** XML sitemap template

**Variables:**
```blade
@foreach($urls as $url)
    <url>
        <loc>{{ $url->getAbsoluteUrl() }}</loc>
        <lastmod>{{ $url->updated_at->toAtomString() }}</lastmod>
        <priority>{{ $priority }}</priority>
    </url>
@endforeach
```

**When to modify:**
- Add custom XML elements
- Include additional metadata
- Support multilingual sitemaps
- Add image/video sitemap elements

---

### Testing and Development

#### Test Page (`test.html`)
**Purpose:** Interactive test page for verifying referrer tracking

**Location:** `/vendor/rayzenai/url-manager/test.html`
**Route:** `/_url-manager/test` (non-production only)

**Features:**
- Visual interface to add entity URLs
- JavaScript-based referrer capture
- Real-time testing instructions
- Auto-detection of khojde.test domain

**How it works:**
```javascript
// When user clicks a link:
function visitLink(event, link) {
    event.preventDefault();
    const url = new URL(link.href);
    const currentPage = window.location.href;

    // Add current page as query parameter
    url.searchParams.set('ref', currentPage);

    // Navigate to modified URL
    window.location.href = url.toString();
}
```

**Why this works:**
Even if browsers block the HTTP `Referer` header, `document.referrer` is still available in JavaScript. By passing it as a query parameter (`?ref=`), we ensure the referrer is captured.

**When to modify:**
- Add debugging information
- Create automated test scenarios
- Add analytics verification tools

**Related Files:**
- `routes/web.php:12-17` - Test page route registration
- `src/Services/VisitTracker.php:23-28` - Referrer capture logic

---

## Common Modification Scenarios

### Adding a New URL Type

**1. Update Configuration**
```php
// config/url-manager.php
'types' => [
    'product' => 'Product',
    'article' => 'Article',
    'video' => 'Video',        // Add new type
],

'sitemap' => [
    'priorities' => [
        'product' => 0.8,
        'article' => 0.7,
        'video' => 0.9,        // Add priority
    ],
],
```

**2. Add Model Trait**
```php
// app/Models/Video.php
use RayzenAI\UrlManager\Traits\HasUrl;

class Video extends Model
{
    use HasUrl;

    public function webUrlPath(): string
    {
        return 'videos/' . $this->slug;
    }
}
```

**3. Generate URLs**
```bash
php artisan urls:generate "App\Models\Video"
```

---

### Customizing Visit Analytics

**Add Custom Visit Metadata:**
```php
// When tracking a visit
use RayzenAI\UrlManager\Services\VisitTracker;

VisitTracker::trackVisit($url, auth()->id(), [
    'campaign' => request('utm_campaign'),
    'source' => request('utm_source'),
    'custom_field' => 'value',
]);
```

**Add Filament Column:**
```php
// src/Filament/Resources/UrlVisitResource.php
TextColumn::make('meta.campaign')
    ->label('Campaign')
    ->getStateUsing(fn($record) => $record->meta['campaign'] ?? '-')
    ->toggleable(),
```

---

### Adding Middleware to URL Routes

**Apply custom middleware to all managed URLs:**
```php
// src/Http/Controllers/UrlController.php
public function __construct()
{
    $this->middleware(['web', 'cache-headers', 'custom-middleware']);
}
```

---

### Custom Sitemap Generation

**Add custom logic to sitemap:**
```php
// src/Commands/GenerateSitemapCommand.php
protected function generateSitemapItems(Collection $urls)
{
    return $urls->map(function ($url) {
        // Custom logic here
        $item = [
            'loc' => $url->getAbsoluteUrl(),
            'lastmod' => $url->updated_at,
            'priority' => $this->calculateDynamicPriority($url),
        ];

        return $item;
    });
}
```

---

## Database Schema

### `urls` Table
```sql
id                  bigint unsigned
urable_type         varchar         -- Polymorphic model type
urable_id           bigint unsigned -- Polymorphic model ID
slug                varchar unique  -- URL path
type                varchar         -- URL type (product, article, etc.)
status              varchar         -- active, redirect, inactive
redirect_to         varchar         -- Target for redirects
redirect_code       integer         -- 301 or 302
meta                jsonb           -- Additional metadata
visits              bigint default 0 -- Basic visit counter
last_visited_at     timestamp
created_at          timestamp
updated_at          timestamp

-- Indexes
index(urable_type, urable_id)
index(slug)
index(type)
index(status)
```

### `url_visits` Table
```sql
id              bigint unsigned
url_id          bigint unsigned -- Foreign key to urls
ip_address      varchar(45)     -- IPv4/IPv6
country_code    char(2)         -- ISO country code
browser         varchar(50)
browser_version varchar(20)
device          varchar(20)     -- desktop, mobile, tablet
user_id         bigint unsigned -- Authenticated user (nullable)
referer         varchar         -- Where visitor came from
meta            jsonb           -- Additional metadata
created_at      timestamp

-- Indexes
index(url_id)
index(user_id)
index(country_code)
index(created_at)
index(url_id, created_at)
```

---

## Events and Hooks

### Available Events

```php
// Fired when a URL is visited
Event::listen('url-manager.url.visited', function ($url, $model) {
    Log::info("Visited: {$url->slug}");
});

// Fired when a visit is recorded (with full details)
Event::listen('url-manager.visit.recorded', function ($data) {
    // $data contains: url, visit, model, user_id, metadata
});
```

### Model Events

```php
// HasUrl trait fires standard Eloquent events
Model::created(function ($model) {
    // URL automatically created via trait
});

Model::updated(function ($model) {
    // URL automatically updated via trait
});
```

---

## Performance Considerations

### Visit Tracking Performance

**Async Processing:**
- Visits are recorded via queued jobs (`RecordUrlVisit`)
- Uses configurable queue (`'visit_queue' => 'low'`)
- Response time not affected by tracking

**Optimization Tips:**
```php
// config/url-manager.php
'visit_queue' => 'low',  // Use low-priority queue

// For high-traffic sites
'track_visits' => env('TRACK_VISITS', true), // Disable in load testing
```

### Sitemap Generation

**Large Site Handling:**
- Automatic splitting at 10,000 URLs per file
- Chunked database queries
- Configurable file size limits

```php
// config/url-manager.php
'sitemap' => [
    'max_urls_per_file' => 10000,
    'max_images_per_file' => 5000,
]
```

---

## Security Considerations

### Encrypted Credentials

Google Search Console credentials are encrypted using Laravel's encryption:

```php
// src/Models/GoogleSearchConsoleSetting.php
protected $casts = [
    'service_account_json' => 'encrypted',
];
```

### Test Route Protection

Test page is only accessible in non-production environments:

```php
// routes/web.php
if (! app()->environment('production')) {
    Route::get('/_url-manager/test', function() { ... });
}
```

### SQL Injection Prevention

All database queries use parameter binding:

```php
// Always use parameter binding
Url::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->first();
```

---

## Debugging Tips

### Enable Visit Tracking Logs

Temporarily add logging to track referrer capture:

```php
// src/Services/VisitTracker.php
\Log::info('VisitTracker', [
    'url' => $url->slug,
    'referer' => $referer,
    'all_headers' => $request->headers->all(),
]);
```

### Check Queue Processing

```bash
# Process one job
php artisan queue:work --once

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Inspect Visit Data

```bash
php artisan tinker
```
```php
// Get recent visits with referrers
\RayzenAI\UrlManager\Models\UrlVisit::latest()
    ->take(10)
    ->get(['id', 'url_id', 'referer', 'created_at']);

// Check specific URL visits
$url = \RayzenAI\UrlManager\Models\Url::where('slug', 'my-slug')->first();
$url->visits()->latest()->get();
```

---

## Dependencies

### Required Packages
- `filament/filament: ^4.0` - Admin panel
- `stevebauman/location: ^7.0` - Country detection
- `jenssegers/agent: ^2.6` - Browser/device detection
- `google/apiclient: ^2.12` - Search Console API

### Optional Packages
- `kirantimsina/file-manager` - Media SEO functionality
- `spatie/laravel-sitemap` - Enhanced sitemap generation

---

## Quick Command Reference

### All Available Artisan Commands

```bash
# URL Management
php artisan urls:generate                        # Generate URLs for models using HasUrl trait
php artisan urls:generate "App\Models\Product"  # Generate URLs for specific model only

# URL Configuration Check
php artisan url-manager:check                    # Verify HasUrl trait configuration
php artisan url-manager:check "App\Models\Product"  # Check specific model

# Model Generation
php artisan url-manager:make-model Product       # Create model with HasUrl trait
php artisan url-manager:make-model Product --migration  # With migration
php artisan url-manager:make-model Product --all # With migration, factory, seeder

# Sitemap Generation
php artisan sitemap:generate                     # Generate main sitemap
php artisan sitemap:generate-all                 # Generate all sitemaps (URL, Image, Video)
php artisan sitemap:generate-images              # Generate image sitemap only
php artisan sitemap:generate-videos              # Generate video sitemap only

# Search Engine Submission
php artisan sitemap:submit                       # Submit sitemap to Google Search Console

# Analytics & Data Management
php artisan url-manager:populate-country-codes   # Populate country codes for existing visits
```

### Command File Locations

| Command | File | Purpose |
|---------|------|---------|
| `urls:generate` | `GenerateUrlsForModels.php` | Bulk URL generation |
| `url-manager:check` | `CheckUrlManagerCommand.php` | Configuration validation |
| `url-manager:make-model` | `MakeModelCommand.php` | Model scaffolding |
| `sitemap:generate` | `GenerateSitemap.php` | Main sitemap |
| `sitemap:generate-all` | `GenerateAllSitemaps.php` | All sitemaps |
| `sitemap:generate-images` | `GenerateImageSitemap.php` | Image sitemap |
| `sitemap:generate-videos` | `GenerateVideoSitemap.php` | Video sitemap |
| `sitemap:submit` | `SubmitSitemapToGoogle.php` | Search Console submission |
| `url-manager:populate-country-codes` | `PopulateUrlVisitCountryCodes.php` | Country data migration |

---

## Support and Resources

- **GitHub Issues:** Report bugs and request features
- **README.md:** User-facing documentation
- **CLAUDE.md:** This file - developer/AI documentation
- **Test Page:** `/_url-manager/test` - Interactive testing tool

---

## Version History

### Recent Changes

**Referrer Tracking Enhancement (Latest)**
- Added JavaScript-based referrer capture fallback
- Multi-source referrer detection (query params, headers, server vars)
- Test page for verifying referrer tracking
- `Referrer-Policy: unsafe-url` header support

**Google Search Console Integration**
- Service account authentication
- Encrypted credential storage in database
- Test connection functionality
- Automatic sitemap submission

**Visit Analytics**
- Country detection with flag emojis
- Mobile app detection (Flutter, React Native, etc.)
- Browser and device tracking
- Real-time Filament dashboard
