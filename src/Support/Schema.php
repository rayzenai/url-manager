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
