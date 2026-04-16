<?php

use RayzenAI\UrlManager\Support\Schema;

it('builds a generic schema type', function () {
    $schema = Schema::type('Product', ['name' => 'Widget', 'url' => 'https://example.com/widget']);

    expect($schema)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Widget',
        'url' => 'https://example.com/widget',
    ]);
});

it('strips null and empty values', function () {
    $schema = Schema::type('Thing', ['name' => 'Test', 'description' => null, 'image' => '', 'tags' => []]);

    expect($schema)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Thing',
        'name' => 'Test',
    ]);
});

it('builds a WebSite schema', function () {
    $schema = Schema::webSite(['name' => 'My Site', 'url' => 'https://example.com']);

    expect($schema['@type'])->toBe('WebSite');
    expect($schema['name'])->toBe('My Site');
});

it('builds an Organization schema', function () {
    $schema = Schema::organization([
        'name' => 'Acme Corp',
        'url' => 'https://acme.com',
        'logo' => 'https://acme.com/logo.png',
    ]);

    expect($schema['@type'])->toBe('Organization');
    expect($schema['logo'])->toBe('https://acme.com/logo.png');
});

it('builds an EducationalOrganization schema', function () {
    $schema = Schema::educationalOrganization([
        'name' => 'MIT',
        'url' => 'https://mit.edu',
    ]);

    expect($schema['@type'])->toBe('EducationalOrganization');
    expect($schema['name'])->toBe('MIT');
});

it('builds a BreadcrumbList from items', function () {
    $schema = Schema::breadcrumbList([
        ['name' => 'Home', 'url' => 'https://example.com/'],
        ['name' => 'Products', 'url' => 'https://example.com/products'],
        ['name' => 'Widget', 'url' => 'https://example.com/products/widget'],
    ]);

    expect($schema['@type'])->toBe('BreadcrumbList');
    expect($schema['itemListElement'])->toHaveCount(3);
    expect($schema['itemListElement'][0]['position'])->toBe(1);
    expect($schema['itemListElement'][0]['name'])->toBe('Home');
    expect($schema['itemListElement'][2]['position'])->toBe(3);
    expect($schema['itemListElement'][2]['name'])->toBe('Widget');
});

it('builds an Article schema', function () {
    $schema = Schema::article([
        'name' => 'My Article',
        'datePublished' => '2026-01-01',
        'author' => ['@type' => 'Person', 'name' => 'John'],
    ]);

    expect($schema['@type'])->toBe('Article');
    expect($schema['author']['name'])->toBe('John');
});

it('builds a FAQPage schema', function () {
    $schema = Schema::faqPage([
        ['question' => 'What is this?', 'answer' => 'A test.'],
        ['question' => 'Why?', 'answer' => 'Because.'],
    ]);

    expect($schema['@type'])->toBe('FAQPage');
    expect($schema['mainEntity'])->toHaveCount(2);
    expect($schema['mainEntity'][0]['@type'])->toBe('Question');
    expect($schema['mainEntity'][0]['name'])->toBe('What is this?');
    expect($schema['mainEntity'][0]['acceptedAnswer']['text'])->toBe('A test.');
});

it('builds an AggregateRating', function () {
    $rating = Schema::aggregateRating([
        'ratingValue' => 4.5,
        'reviewCount' => 120,
        'bestRating' => 5,
        'worstRating' => 1,
    ]);

    expect($rating['@type'])->toBe('AggregateRating');
    expect($rating['ratingValue'])->toBe(4.5);
    expect($rating['reviewCount'])->toBe(120);
});

it('builds a SearchAction', function () {
    $action = Schema::searchAction('https://example.com/search?q={search_term_string}');

    expect($action['@type'])->toBe('SearchAction');
    expect($action['target']['urlTemplate'])->toBe('https://example.com/search?q={search_term_string}');
});

it('builds a PostalAddress', function () {
    $address = Schema::postalAddress([
        'addressLocality' => 'Kathmandu',
        'addressCountry' => 'NP',
    ]);

    expect($address['@type'])->toBe('PostalAddress');
    expect($address['addressLocality'])->toBe('Kathmandu');
});
