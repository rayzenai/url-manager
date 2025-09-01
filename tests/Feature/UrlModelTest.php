<?php

use RayzenAI\UrlManager\Models\Url;

it('can create a url', function () {
    $url = Url::create([
        'slug' => 'test-page',
        'urable_type' => 'App\Models\Page',
        'urable_id' => 1,
        'type' => Url::TYPE_PAGE,
        'status' => Url::STATUS_ACTIVE,
    ]);

    expect($url)->toBeInstanceOf(Url::class);
    expect($url->slug)->toBe('test-page');
    expect($url->status)->toBe(Url::STATUS_ACTIVE);
});

it('can create a redirect', function () {
    $redirect = Url::createRedirect('old-page', 'new-page', 301);

    expect($redirect)->toBeInstanceOf(Url::class);
    expect($redirect->slug)->toBe('old-page');
    expect($redirect->redirect_to)->toBe('new-page');
    expect($redirect->redirect_code)->toBe(301);
    expect($redirect->status)->toBe(Url::STATUS_REDIRECT);
    expect($redirect->type)->toBe(Url::TYPE_REDIRECT);
});

it('generates unique slugs', function () {
    // Create mock model
    $model = new class {
        public $id = 1;
        public $name = 'Test Product';
    };

    $slug = Url::generateUniqueSlug($model);
    expect($slug)->toBe('test-product');

    // Create a URL with that slug
    Url::create([
        'slug' => $slug,
        'urable_type' => 'App\Models\Product',
        'urable_id' => 1,
        'type' => Url::TYPE_ENTITY,
        'status' => Url::STATUS_ACTIVE,
    ]);

    // Generate another slug for the same name
    $model2 = new class {
        public $id = 2;
        public $name = 'Test Product';
    };

    $slug2 = Url::generateUniqueSlug($model2);
    expect($slug2)->toBe('test-product-1');
});

it('prevents infinite redirect loops', function () {
    // Create a circular redirect chain
    Url::create([
        'slug' => 'page-a',
        'redirect_to' => 'page-b',
        'status' => Url::STATUS_REDIRECT,
        'type' => Url::TYPE_REDIRECT,
        'urable_type' => Url::class,
        'urable_id' => 0,
    ]);

    Url::create([
        'slug' => 'page-b',
        'redirect_to' => 'page-a',
        'status' => Url::STATUS_REDIRECT,
        'type' => Url::TYPE_REDIRECT,
        'urable_type' => Url::class,
        'urable_id' => 0,
    ]);

    // Try to find the URL (should return null due to depth limit)
    $result = Url::findBySlug('page-a');
    expect($result)->toBeNull();
});

it('tracks visits', function () {
    $url = Url::create([
        'slug' => 'popular-page',
        'urable_type' => 'App\Models\Page',
        'urable_id' => 1,
        'type' => Url::TYPE_PAGE,
        'status' => Url::STATUS_ACTIVE,
        'visits' => 0,
    ]);

    $url->recordVisit();

    expect($url->fresh()->visits)->toBe(1);
    expect($url->fresh()->last_visited_at)->not->toBeNull();
});