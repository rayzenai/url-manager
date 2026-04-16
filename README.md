# RayzenAI URL Manager

A comprehensive Laravel package for managing URLs, redirects, sitemaps, and structured data (JSON-LD) with Filament admin panel integration.

## Features

- **Dynamic URL Management** — Manage all application URLs from a central location
- **301/302 Redirects** — Create and manage redirects with circular-chain protection
- **Automatic Sitemap Generation** — XML sitemaps with multi-file support for large sites
- **JSON-LD Structured Data** — Schema.org builders for rich search results (Product, Event, Article, FAQ, and more)
- **Visit Tracking** — Track URL visits with country detection, device info, and referrer capture
- **Filament Integration** — Full admin panel with UrlInput component, analytics widgets, and GSC settings
- **SEO Metadata** — Open Graph tags, canonical URLs, and structured metadata per model
- **Redirect Loop Protection** — Automatic detection and prevention of circular redirects

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5+
- Stevebauman/Location 7.0+ with MaxMind database (for visitor country detection)
- kirantimsina/file-manager (optional, for media SEO / image sitemaps)

## Installation

### 1. Install via Composer

```bash
composer require rayzenai/url-manager
```

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --tag=url-manager-config
php artisan vendor:publish --tag=url-manager-migrations
php artisan migrate
```

This creates three tables: `urls`, `url_visits`, and `google_search_console_settings`.

### 3. Register with Filament

Add the plugin to your panel provider:

```php
use RayzenAI\UrlManager\UrlManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(UrlManagerPlugin::make());
}
```

### 4. Configure Your Models

Add the `HasUrl` trait to any model that needs URL management:

```php
use Illuminate\Database\Eloquent\Model;
use RayzenAI\UrlManager\Traits\HasUrl;

class Product extends Model
{
    use HasUrl;

    public function webUrlPath(): string
    {
        return 'products/' . $this->slug;
    }

    // Optional: override if your active field isn't 'is_active'
    public function activeUrlField(): string
    {
        return 'is_active';
    }
}
```

### 5. Register Redirect Middleware

Add the middleware early in your stack (before route model binding) so old URLs redirect instead of 404-ing:

```php
use RayzenAI\UrlManager\Http\Middleware\HandleUrlRedirects;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            HandleUrlRedirects::class,
        ]);
    });
```

### 6. Generate URLs for Existing Models

```bash
# All models with HasUrl
php artisan urls:generate

# Specific model
php artisan urls:generate "App\Models\Product"
```

---

## JSON-LD Structured Data

The package includes a `Schema` builder and `JsonLd` collector for generating schema.org structured data as `<script type="application/ld+json">` tags.

### Schema Builder

Static factory methods for common schema.org types:

```php
use RayzenAI\UrlManager\Support\Schema;

// Generic — any schema.org type
Schema::type('LocalBusiness', ['name' => 'My Shop', 'url' => '...']);

// Organization / EducationalOrganization
Schema::organization(['name' => 'Acme Corp', 'url' => '...', 'logo' => '...']);
Schema::educationalOrganization(['name' => 'MIT', 'url' => '...']);

// WebSite with SearchAction
Schema::webSite([
    'name' => 'My Site',
    'url' => 'https://example.com',
    'potentialAction' => Schema::searchAction('https://example.com/search?q={search_term_string}'),
]);

// BreadcrumbList
Schema::breadcrumbList([
    ['name' => 'Home', 'url' => 'https://example.com/'],
    ['name' => 'Products', 'url' => 'https://example.com/products'],
    ['name' => 'Widget', 'url' => 'https://example.com/products/widget'],
]);

// FAQPage
Schema::faqPage([
    ['question' => 'What is this?', 'answer' => 'A great product.'],
    ['question' => 'How much?', 'answer' => '$9.99'],
]);
```

#### E-commerce

```php
// Product with Offer
Schema::product([
    'name' => 'Running Shoes',
    'image' => 'https://example.com/shoes.jpg',
    'sku' => 'SHOE-001',
    'brand' => ['@type' => 'Brand', 'name' => 'Acme'],
    'offers' => Schema::offer([
        'price' => 99.99,
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
    ]),
    'aggregateRating' => Schema::aggregateRating([
        'ratingValue' => 4.5,
        'reviewCount' => 120,
    ]),
]);

// Product with price range
Schema::product([
    'name' => 'T-Shirt',
    'offers' => Schema::aggregateOffer([
        'lowPrice' => 9.99,
        'highPrice' => 29.99,
        'priceCurrency' => 'USD',
        'offerCount' => 5,
    ]),
]);

// Category / listing page
Schema::collectionPage([
    'name' => 'Electronics',
    'url' => 'https://example.com/electronics',
    'description' => 'Browse our electronics catalog',
]);
```

#### Blog / Articles

```php
Schema::blogPosting([
    'headline' => '10 Tips for Better Code',
    'image' => 'https://example.com/blog/tips.jpg',
    'datePublished' => '2026-04-01',
    'dateModified' => '2026-04-10',
    'author' => Schema::person(['name' => 'Jane Doe', 'url' => 'https://example.com/jane']),
    'publisher' => Schema::organization(['name' => 'Tech Blog']),
]);

// Generic article
Schema::article([
    'name' => 'My Article',
    'datePublished' => '2026-01-01',
    'author' => Schema::person(['name' => 'John']),
]);
```

#### Events and Contests

```php
// Physical event
Schema::event([
    'name' => 'Laravel Meetup',
    'startDate' => '2026-05-15T18:00:00+05:45',
    'endDate' => '2026-05-15T21:00:00+05:45',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'location' => Schema::place([
        'name' => 'Tech Hub',
        'address' => Schema::postalAddress([
            'addressLocality' => 'Kathmandu',
            'addressCountry' => 'NP',
        ]),
    ]),
    'organizer' => Schema::organization(['name' => 'Laravel Nepal']),
    'offers' => Schema::offer([
        'price' => 0,
        'priceCurrency' => 'NPR',
        'availability' => 'https://schema.org/InStock',
    ]),
]);

// Online event / contest
Schema::event([
    'name' => 'Coding Contest 2026',
    'startDate' => '2026-06-01T10:00:00Z',
    'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
    'location' => Schema::virtualLocation('https://example.com/contest/live'),
]);
```

#### Helper Types

```php
Schema::person(['name' => 'John Doe', 'url' => '...']);
Schema::postalAddress(['addressLocality' => 'Kathmandu', 'addressCountry' => 'NP']);
Schema::place(['name' => 'Convention Center', 'address' => Schema::postalAddress([...])]);
Schema::virtualLocation('https://example.com/live');
Schema::aggregateRating(['ratingValue' => 4.5, 'reviewCount' => 120]);
Schema::searchAction('https://example.com/search?q={search_term_string}');
```

### JsonLd Collector

Accumulate schemas during a request and render them as script tags:

```php
use RayzenAI\UrlManager\Support\JsonLd;

// Add schemas
JsonLd::add(Schema::organization(['name' => 'Acme']));
JsonLd::add($product->jsonLd());
JsonLd::breadcrumbs($product->breadcrumbs());

// Add multiple at once
JsonLd::addMany(
    Schema::organization(['name' => 'Acme']),
    Schema::webSite(['name' => 'Acme Site']),
);

// Load site-level defaults from config (Organization + WebSite)
JsonLd::defaults();

// Render as HTML script tags (use in Blade)
{!! JsonLd::render() !!}

// Or get raw JSON (for APIs / headless)
$json = JsonLd::toJson();

// Reset between requests (call after rendering)
JsonLd::reset();
```

### Model Integration

The `HasUrl` trait provides three methods models can override for structured data:

```php
class College extends Model
{
    use HasUrl;

    // Schema.org @type
    public function jsonLdType(): string
    {
        return 'EducationalOrganization';
    }

    // Full JSON-LD schema
    public function jsonLd(): array
    {
        return Schema::educationalOrganization([
            'name' => $this->name,
            'url' => url($this->webUrlPath()),
            'address' => Schema::postalAddress([
                'addressLocality' => $this->location,
                'addressCountry' => 'NP',
            ]),
        ]);
    }

    // Breadcrumb trail
    public function breadcrumbs(): array
    {
        return [
            ['name' => 'Home', 'url' => url('/')],
            ['name' => 'Colleges', 'url' => url('/colleges')],
            ['name' => $this->name, 'url' => url($this->webUrlPath())],
        ];
    }
}
```

Then in your controller:

```php
JsonLd::add($college->jsonLd());
JsonLd::breadcrumbs($college->breadcrumbs());
```

### Configuration

Add site-level defaults to `config/url-manager.php`:

```php
'json_ld' => [
    'organization' => [
        'name' => env('APP_NAME', 'My Site'),
        'url' => env('APP_URL', 'https://example.com'),
        'logo' => env('APP_URL', 'https://example.com') . '/favicon.svg',
        'sameAs' => [
            'https://facebook.com/mysite',
            'https://twitter.com/mysite',
        ],
    ],
    'website' => [
        'name' => env('APP_NAME', 'My Site'),
        'url' => env('APP_URL', 'https://example.com'),
        'search_url' => env('APP_URL', 'https://example.com') . '/search?q={search_term_string}',
    ],
],
```

Call `JsonLd::defaults()` once per request (e.g. in a view composer or middleware) to emit the Organization and WebSite schemas automatically.

---

## Open Graph Tags

Models using `HasUrl` get OG tag support via the `ogTags()` method:

```php
public function ogTags(): array
{
    return [
        'title' => $this->name,
        'description' => $this->description,
        'image' => $this->getOgImageUrl(),
        'type' => 'product',
        'url' => $this->webUrl(),
        'site_name' => config('app.name'),
    ];
}
```

The trait provides smart image fallback via `ogImageField()` (default: `og_image`) and `ogImageFallbackField()` (default: `image`).

---

## Sitemaps

### Generate Sitemaps

```bash
# Standard URL sitemap
php artisan sitemap:generate

# Image sitemap (requires kirantimsina/file-manager)
php artisan sitemap:generate-images

# Video sitemap
php artisan sitemap:generate-videos

# All sitemaps + index
php artisan sitemap:generate-all
```

The homepage (`/`) is always included automatically. For sites with >10,000 URLs, multiple sitemap files are generated with an index.

### Custom Static Routes

Include static pages that don't have database URL records:

```php
// config/url-manager.php
'sitemap' => [
    'custom_routes' => [
        ['path' => '/about', 'priority' => 0.7, 'changefreq' => 'monthly'],
        ['path' => '/contact', 'priority' => 0.6, 'changefreq' => 'yearly'],
    ],
],
```

### Submit to Search Engines

```bash
php artisan sitemap:submit
```

Configure Google Search Console API credentials via the Filament admin panel (GSC Settings page). Requires a service account with owner permissions on your Search Console property.

---

## Redirects

### Automatic (via HasUrl)

When a model's slug changes, the old URL is automatically converted to a 301 redirect pointing to the new URL. Circular chains (A->B->A) are detected and blocked.

### Manual

```php
use RayzenAI\UrlManager\Models\Url;

Url::createRedirect('old-page', 'new-page', 301);
Url::createRedirect('summer-sale', 'products/sale', 302);
```

### Via Filament

Create redirects through the admin panel's URL management section.

---

## Visit Tracking

### Via Middleware

```php
Route::get('/products/{slug}', [ProductController::class, 'show'])
    ->middleware('track-url-visits');
```

The middleware captures IP, user agent, referrer, country, device type, and authenticated user.

### Model View Counts

Implement `getViewCountColumn()` on your model to auto-increment a view counter:

```php
public function getViewCountColumn(): ?string
{
    return 'view_count';
}
```

### Visit Analytics

The `url_visits` table tracks individual visits with:
- IP address and country (via MaxMind GeoIP)
- Browser, device type, mobile app detection
- Referrer URL (cleaned of tracking params)
- Authenticated user ID

The Filament admin panel includes widgets for URL stats and top pages.

---

## Facade

```php
use RayzenAI\UrlManager\Facades\UrlManager;

UrlManager::generateUrl($product);
UrlManager::trackVisit($product, auth()->id());
UrlManager::createRedirect('/old', '/new', 301);
UrlManager::findBySlug('products/my-product');
UrlManager::getVisitCount($product);
UrlManager::deleteUrl($product);
```

---

## Filament Components

### UrlInput

Auto-slug generation with unique validation and redirect-chain protection:

```php
use RayzenAI\UrlManager\Filament\Forms\Components\UrlInput;

UrlInput::make('slug')
    ->sourceField('name')
    ->forModel(Product::class)
    ->allowUpdatingSlug(); // Enables editing with automatic redirect creation
```

### Dashboard Widgets

- **UrlStatsOverview** — Total URLs, redirects, visit statistics
- **TopUrlsTable** — Most visited URLs

---

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `urls:generate {model?}` | Generate URL records for models with HasUrl |
| `sitemap:generate` | Generate XML sitemap |
| `sitemap:generate-all` | Generate all sitemaps (URL + image + video) + index |
| `sitemap:generate-images` | Generate image sitemap |
| `sitemap:generate-videos` | Generate video sitemap |
| `sitemap:submit` | Submit sitemap to Google Search Console |
| `url-manager:check {model?}` | Verify model configuration |
| `url-manager:make-model` | Scaffold a new model with HasUrl |
| `url-manager:populate-country-codes` | Resolve country codes for existing visits |

---

## Models Using Enums for Active Status

If your model uses an enum instead of a boolean for its active state, override `shouldHaveUrl()` and `isActiveForUrl()`:

```php
class College extends Model
{
    use HasUrl;

    public function activeUrlField(): string
    {
        return 'status'; // Enum field — triggers wasChanged() detection
    }

    public function shouldHaveUrl(): bool
    {
        return $this->status === CollegeStatus::Approved;
    }

    public function isActiveForUrl(): bool
    {
        return $this->status === CollegeStatus::Approved;
    }
}
```

---

## Testing

```bash
cd url-manager
vendor/bin/pest
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

Created by [Kiran Timsina](https://github.com/kirantimsina) at RayzenAI.
