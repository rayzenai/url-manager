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

it('builds a Product schema with Offer', function () {
    $schema = Schema::product([
        'name' => 'Running Shoes',
        'image' => 'https://example.com/shoes.jpg',
        'description' => 'Lightweight running shoes',
        'sku' => 'SHOE-001',
        'brand' => ['@type' => 'Brand', 'name' => 'Acme'],
        'offers' => Schema::offer([
            'price' => 99.99,
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'url' => 'https://example.com/shoes',
        ]),
    ]);

    expect($schema['@type'])->toBe('Product');
    expect($schema['name'])->toBe('Running Shoes');
    expect($schema['sku'])->toBe('SHOE-001');
    expect($schema['offers']['@type'])->toBe('Offer');
    expect($schema['offers']['price'])->toBe(99.99);
    expect($schema['offers']['priceCurrency'])->toBe('USD');
});

it('builds a Product with AggregateOffer', function () {
    $schema = Schema::product([
        'name' => 'T-Shirt',
        'offers' => Schema::aggregateOffer([
            'lowPrice' => 9.99,
            'highPrice' => 29.99,
            'priceCurrency' => 'USD',
            'offerCount' => 5,
        ]),
    ]);

    expect($schema['offers']['@type'])->toBe('AggregateOffer');
    expect($schema['offers']['lowPrice'])->toBe(9.99);
    expect($schema['offers']['offerCount'])->toBe(5);
});

it('builds a CollectionPage schema', function () {
    $schema = Schema::collectionPage([
        'name' => 'Electronics',
        'url' => 'https://example.com/electronics',
        'description' => 'Browse our electronics catalog',
        'numberOfItems' => 42,
    ]);

    expect($schema['@type'])->toBe('CollectionPage');
    expect($schema['name'])->toBe('Electronics');
    expect($schema['numberOfItems'])->toBe(42);
});

it('builds a BlogPosting schema', function () {
    $schema = Schema::blogPosting([
        'headline' => '10 Tips for Better Code',
        'image' => 'https://example.com/blog/tips.jpg',
        'datePublished' => '2026-04-01',
        'dateModified' => '2026-04-10',
        'author' => Schema::person(['name' => 'Jane Doe', 'url' => 'https://example.com/jane']),
        'publisher' => ['@type' => 'Organization', 'name' => 'Tech Blog'],
    ]);

    expect($schema['@type'])->toBe('BlogPosting');
    expect($schema['headline'])->toBe('10 Tips for Better Code');
    expect($schema['author']['@type'])->toBe('Person');
    expect($schema['author']['name'])->toBe('Jane Doe');
    expect($schema['datePublished'])->toBe('2026-04-01');
});

it('builds an Event schema with physical location', function () {
    $schema = Schema::event([
        'name' => 'Laravel Nepal Meetup',
        'startDate' => '2026-05-15T18:00:00+05:45',
        'endDate' => '2026-05-15T21:00:00+05:45',
        'description' => 'Monthly Laravel community meetup',
        'image' => 'https://example.com/meetup.jpg',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => Schema::place([
            'name' => 'Tech Hub Kathmandu',
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

    expect($schema['@type'])->toBe('Event');
    expect($schema['name'])->toBe('Laravel Nepal Meetup');
    expect($schema['location']['@type'])->toBe('Place');
    expect($schema['location']['name'])->toBe('Tech Hub Kathmandu');
    expect($schema['location']['address']['@type'])->toBe('PostalAddress');
    expect($schema['organizer']['@type'])->toBe('Organization');
    expect($schema['offers']['price'])->toBe(0);
});

it('builds an online Event with VirtualLocation', function () {
    $schema = Schema::event([
        'name' => 'Coding Contest 2026',
        'startDate' => '2026-06-01T10:00:00Z',
        'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
        'location' => Schema::virtualLocation('https://example.com/contest/live'),
    ]);

    expect($schema['@type'])->toBe('Event');
    expect($schema['location']['@type'])->toBe('VirtualLocation');
    expect($schema['location']['url'])->toBe('https://example.com/contest/live');
});

it('builds a Person', function () {
    $person = Schema::person([
        'name' => 'John Doe',
        'url' => 'https://example.com/john',
        'image' => 'https://example.com/john.jpg',
    ]);

    expect($person['@type'])->toBe('Person');
    expect($person['name'])->toBe('John Doe');
});

it('builds a Place with address', function () {
    $place = Schema::place([
        'name' => 'Convention Center',
        'address' => Schema::postalAddress([
            'streetAddress' => '123 Main St',
            'addressLocality' => 'Kathmandu',
            'addressCountry' => 'NP',
        ]),
    ]);

    expect($place['@type'])->toBe('Place');
    expect($place['name'])->toBe('Convention Center');
    expect($place['address']['streetAddress'])->toBe('123 Main St');
});
