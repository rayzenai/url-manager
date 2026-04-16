<?php

namespace RayzenAI\UrlManager\Support;

class Schema
{
    /**
     * Build any schema.org type with @context and @type pre-filled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function type(string $type, array $data = []): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $type,
            ...$data,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, url, description, potentialAction, ...
     * @return array<string, mixed>
     */
    public static function webSite(array $data = []): array
    {
        return self::type('WebSite', $data);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, url, logo, sameAs, contactPoint, ...
     * @return array<string, mixed>
     */
    public static function organization(array $data = []): array
    {
        return self::type('Organization', $data);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, url, logo, address, ...
     * @return array<string, mixed>
     */
    public static function educationalOrganization(array $data = []): array
    {
        return self::type('EducationalOrganization', $data);
    }

    /**
     * @param  array{name: string, url: string}[]  $items  Ordered breadcrumb items.
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $items): array
    {
        $listItems = [];

        foreach (array_values($items) as $i => $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return self::type('BreadcrumbList', [
            'itemListElement' => $listItems,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, url, description, image, datePublished, dateModified, author, ...
     * @return array<string, mixed>
     */
    public static function article(array $data = []): array
    {
        return self::type('Article', $data);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, url, description, image, ...
     * @return array<string, mixed>
     */
    public static function webPage(array $data = []): array
    {
        return self::type('WebPage', $data);
    }

    /**
     * @param  array{question: string, answer: string}[]  $questions
     * @return array<string, mixed>
     */
    public static function faqPage(array $questions): array
    {
        $entities = [];

        foreach ($questions as $qa) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $qa['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $qa['answer'],
                ],
            ];
        }

        return self::type('FAQPage', [
            'mainEntity' => $entities,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: ratingValue, reviewCount, bestRating, worstRating
     * @return array<string, mixed>
     */
    public static function aggregateRating(array $data = []): array
    {
        return [
            '@type' => 'AggregateRating',
            ...array_filter($data, static fn ($v) => $v !== null),
        ];
    }

    /**
     * Build a SearchAction for use inside a WebSite schema.
     *
     * @return array<string, mixed>
     */
    public static function searchAction(string $urlTemplate, string $queryInput = 'required name=search_term_string'): array
    {
        return [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $urlTemplate,
            ],
            'query-input' => $queryInput,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, description, image, sku, brand, offers, aggregateRating, review, ...
     * @return array<string, mixed>
     */
    public static function product(array $data = []): array
    {
        return self::type('Product', $data);
    }

    /**
     * Build an Offer for use inside a Product schema.
     *
     * @param  array<string, mixed>  $data  Keys: price, priceCurrency, availability, url, priceValidUntil, itemCondition, seller, ...
     * @return array<string, mixed>
     */
    public static function offer(array $data = []): array
    {
        return [
            '@type' => 'Offer',
            ...array_filter($data, static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * Build an AggregateOffer for products with price ranges.
     *
     * @param  array<string, mixed>  $data  Keys: lowPrice, highPrice, priceCurrency, offerCount, ...
     * @return array<string, mixed>
     */
    public static function aggregateOffer(array $data = []): array
    {
        return [
            '@type' => 'AggregateOffer',
            ...array_filter($data, static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, url, description, image, numberOfItems, ...
     * @return array<string, mixed>
     */
    public static function collectionPage(array $data = []): array
    {
        return self::type('CollectionPage', $data);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: headline, image, datePublished, dateModified, author, publisher, articleBody, ...
     * @return array<string, mixed>
     */
    public static function blogPosting(array $data = []): array
    {
        return self::type('BlogPosting', $data);
    }

    /**
     * @param  array<string, mixed>  $data  Keys: name, startDate, endDate, location, description, image, organizer, performer, offers, eventAttendanceMode, eventStatus, ...
     * @return array<string, mixed>
     */
    public static function event(array $data = []): array
    {
        return self::type('Event', $data);
    }

    /**
     * Build a VirtualLocation for online events.
     *
     * @return array<string, mixed>
     */
    public static function virtualLocation(string $url): array
    {
        return [
            '@type' => 'VirtualLocation',
            'url' => $url,
        ];
    }

    /**
     * Build a Place for use inside Event schemas.
     *
     * @param  array<string, mixed>  $data  Keys: name, address (use postalAddress()), ...
     * @return array<string, mixed>
     */
    public static function place(array $data = []): array
    {
        return [
            '@type' => 'Place',
            ...array_filter($data, static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * Build a Person for use as author, organizer, performer, etc.
     *
     * @param  array<string, mixed>  $data  Keys: name, url, image, ...
     * @return array<string, mixed>
     */
    public static function person(array $data = []): array
    {
        return [
            '@type' => 'Person',
            ...array_filter($data, static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * Build a PostalAddress for use inside Organization / EducationalOrganization.
     *
     * @param  array<string, mixed>  $data  Keys: streetAddress, addressLocality, addressRegion, postalCode, addressCountry
     * @return array<string, mixed>
     */
    public static function postalAddress(array $data = []): array
    {
        return [
            '@type' => 'PostalAddress',
            ...array_filter($data, static fn ($v) => $v !== null && $v !== ''),
        ];
    }
}
