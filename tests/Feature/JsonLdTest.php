<?php

use RayzenAI\UrlManager\Support\JsonLd;
use RayzenAI\UrlManager\Support\Schema;

beforeEach(function () {
    JsonLd::reset();
});

it('collects and renders schemas', function () {
    JsonLd::add(Schema::organization(['name' => 'Acme', 'url' => 'https://acme.com']));

    $schemas = JsonLd::schemas();
    expect($schemas)->toHaveCount(1);
    expect($schemas[0]['@type'])->toBe('Organization');

    $html = JsonLd::render();
    expect($html)->toContain('<script type="application/ld+json">');
    expect($html)->toContain('"@type": "Organization"');
    expect($html)->toContain('"name": "Acme"');
});

it('adds multiple schemas', function () {
    JsonLd::addMany(
        Schema::organization(['name' => 'Acme']),
        Schema::webSite(['name' => 'Acme Site']),
    );

    expect(JsonLd::schemas())->toHaveCount(2);
});

it('ignores empty schemas', function () {
    JsonLd::add([]);

    expect(JsonLd::schemas())->toHaveCount(0);
    expect(JsonLd::render())->toBe('');
});

it('adds breadcrumbs via convenience method', function () {
    JsonLd::breadcrumbs([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Products', 'url' => '/products'],
    ]);

    $schemas = JsonLd::schemas();
    expect($schemas)->toHaveCount(1);
    expect($schemas[0]['@type'])->toBe('BreadcrumbList');
    expect($schemas[0]['itemListElement'])->toHaveCount(2);
});

it('resets state', function () {
    JsonLd::add(Schema::organization(['name' => 'Acme']));
    expect(JsonLd::schemas())->toHaveCount(1);

    JsonLd::reset();
    expect(JsonLd::schemas())->toHaveCount(0);
});

it('renders JSON for headless use', function () {
    JsonLd::add(Schema::organization(['name' => 'Acme']));

    $json = JsonLd::toJson();
    $decoded = json_decode($json, true);

    expect($decoded['@type'])->toBe('Organization');
    expect($decoded['name'])->toBe('Acme');
});

it('renders JSON array when multiple schemas', function () {
    JsonLd::addMany(
        Schema::organization(['name' => 'Acme']),
        Schema::webSite(['name' => 'Acme Site']),
    );

    $json = JsonLd::toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toHaveCount(2);
    expect($decoded[0]['@type'])->toBe('Organization');
    expect($decoded[1]['@type'])->toBe('WebSite');
});

it('loads defaults from config', function () {
    config()->set('url-manager.json_ld.organization', [
        'name' => 'Test Org',
        'url' => 'https://test.com',
    ]);
    config()->set('url-manager.json_ld.website', [
        'name' => 'Test Site',
        'url' => 'https://test.com',
        'search_url' => 'https://test.com/search?q={search_term_string}',
    ]);

    JsonLd::defaults();

    $schemas = JsonLd::schemas();
    expect($schemas)->toHaveCount(2);
    expect($schemas[0]['@type'])->toBe('Organization');
    expect($schemas[0]['name'])->toBe('Test Org');
    expect($schemas[1]['@type'])->toBe('WebSite');
    expect($schemas[1]['potentialAction']['@type'])->toBe('SearchAction');
});

it('skips defaults when config is empty', function () {
    config()->set('url-manager.json_ld.organization', []);
    config()->set('url-manager.json_ld.website', []);

    JsonLd::defaults();

    expect(JsonLd::schemas())->toHaveCount(0);
});
