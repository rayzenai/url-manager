<?php

return [
    /*
    |--------------------------------------------------------------------------
    | URL Manager Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the URL management system
    |
    */

    'table_name' => 'urls',

    /*
    |--------------------------------------------------------------------------
    | URL Types
    |--------------------------------------------------------------------------
    |
    | Define the types of URLs your application supports
    |
    */
    'types' => [
        'entity' => 'Entity',
        'category' => 'Category',
        'seller' => 'Seller',
        'menu' => 'Menu',
        'brand' => 'Brand',
        'page' => 'Page',
        'blog' => 'Blog',
        'redirect' => 'Redirect',
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Statuses
    |--------------------------------------------------------------------------
    |
    | Define the possible statuses for URLs
    |
    */
    'statuses' => [
        'active' => 'Active',
        'redirect' => 'Redirect',
        'inactive' => 'Inactive',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Redirect Code
    |--------------------------------------------------------------------------
    |
    | The default HTTP status code for redirects
    |
    */
    'default_redirect_code' => 301,

    /*
    |--------------------------------------------------------------------------
    | Maximum Redirect Depth
    |--------------------------------------------------------------------------
    |
    | Maximum number of redirects to follow to prevent infinite loops
    |
    */
    'max_redirect_depth' => 5,

    /*
    |--------------------------------------------------------------------------
    | Sitemap Configuration
    |--------------------------------------------------------------------------
    |
    | Configure sitemap generation
    |
    */
    'sitemap' => [
        'enabled' => true,
        'path' => public_path('sitemap.xml'),
        'max_urls_per_file' => 10000,
        'default_changefreq' => 'weekly',
        'default_priority' => 0.5,
        'priorities' => [
            'entity' => 0.8,
            'category' => 0.9,
            'seller' => 0.7,
            'menu' => 0.6,
            'brand' => 0.7,
            'page' => 0.6,
            'blog' => 0.6,
        ],
        
        /*
        |--------------------------------------------------------------------------
        | Image Sitemap Configuration
        |--------------------------------------------------------------------------
        |
        | Configure which models to include or exclude in image sitemaps
        |
        | Note: Image sitemap generation requires the kirantimsina/file-manager package.
        | For optimal performance, run 'php artisan file-manager:populate-seo-titles' 
        | to pre-populate SEO titles and avoid polymorphic queries during sitemap generation.
        |
        */
        'images' => [
            'enabled' => true,
            'max_images_per_file' => 5000,
            
            /*
            | Image size to use in sitemap URLs
            | Options:
            | - 'auto': Automatically select best size (600-1000px range)
            | - 'thumb', 'medium', 'large', etc.: Use specific size from file-manager config
            | - null: Use original image (not recommended for performance)
            */
            'image_size' => 'auto',
        ],
        
        /*
        |--------------------------------------------------------------------------
        | Video Sitemap Configuration
        |--------------------------------------------------------------------------
        |
        | Configure which models to include or exclude in video sitemaps
        |
        */
        'videos' => [
            'enabled' => true,
            'max_videos_per_file' => 5000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Visit Tracking
    |--------------------------------------------------------------------------
    |
    | Configure visit tracking behavior
    |
    */
    'track_visits' => true,
    'visit_queue' => 'low',
    
    /*
    |--------------------------------------------------------------------------
    | API Route Path Conversions
    |--------------------------------------------------------------------------
    |
    | Define how API routes should be converted to URL Manager slugs.
    | This allows tracking visits from API endpoints that don't match
    | the exact slug format stored in the URLs table.
    |
    */
    'api_path_conversions' => [
        // API path pattern => URL slug pattern
        '/^api\/V[0-9]+\/products\/(.+)$/' => 'product/$1',     // api/V2/products/slug -> product/slug
        '/^api\/V[0-9]+\/category\/(.+)$/' => 'category/$1',    // api/V2/category/slug -> category/slug  
        '/^api\/V[0-9]+\/categories\/(.+)$/' => 'category/$1',  // api/V2/categories/slug -> category/slug
        '/^api\/V[0-9]+\/blog\/(.+)$/' => 'blog/$1',            // api/V2/blog/slug -> blog/slug
        '/^api\/V[0-9]+\/occasion\/(.+)$/' => 'occasion/$1',    // api/V2/occasion/slug -> occasion/slug
        '/^api\/V[0-9]+\/product\/(.+)$/' => 'product/$1',      // api/V2/product/slug -> product/slug
        '/^V[0-9]+\/products\/(.+)$/' => 'product/$1',          // V2/products/slug -> product/slug (fallback)
        '/^V[0-9]+\/category\/(.+)$/' => 'category/$1',         // V2/category/slug -> category/slug (fallback)
        '/^V[0-9]+\/categories\/(.+)$/' => 'category/$1',       // V2/categories/slug -> category/slug (fallback)
        '/^V[0-9]+\/blog\/(.+)$/' => 'blog/$1',                 // V2/blog/slug -> blog/slug (fallback)
        '/^V[0-9]+\/occasion\/(.+)$/' => 'occasion/$1',         // V2/occasion/slug -> occasion/slug (fallback)
        '/^V[0-9]+\/product\/(.+)$/' => 'product/$1',           // V2/product/slug -> product/slug (fallback)
        '/^products\/(.+)$/' => 'product/$1',                   // products/slug -> product/slug
        '/^category\/(.+)$/' => 'category/$1',                  // category/slug -> category/slug
        '/^categories\/(.+)$/' => 'category/$1',                // categories/slug -> category/slug
        '/^blog\/(.+)$/' => 'blog/$1',                          // blog/slug -> blog/slug
        '/^occasion\/(.+)$/' => 'occasion/$1',                  // occasion/slug -> occasion/slug
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the URL visit tracking middleware
    |
    */
    'middleware' => [
        'enabled' => true,
        'alias' => 'track-url-visits',
        'auto_apply' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Resource
    |--------------------------------------------------------------------------
    |
    | Configure the Filament resource
    |
    */
    'filament' => [
        'enabled' => true,
        'navigation_group' => 'System',
        'navigation_icon' => 'heroicon-o-link',
        'navigation_sort' => 100,
    ],
];