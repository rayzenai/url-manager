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

    /*
    |--------------------------------------------------------------------------
    | Google Search Console Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Google Search Console API integration using Service Account
    |
    */
    'google_search_console' => [
        'enabled' => env('GOOGLE_SEARCH_CONSOLE_ENABLED', false),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS', null),
        'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL', null),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL', null),
    ],
];