<?php

namespace RayzenAI\UrlManager\Support;

class JsonLd
{
    /** @var array<int, array<string, mixed>> */
    protected static array $schemas = [];

    /**
     * Add a schema to the page.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function add(array $schema): void
    {
        if ($schema !== []) {
            self::$schemas[] = $schema;
        }
    }

    /**
     * Add multiple schemas at once.
     *
     * @param  array<string, mixed>  ...$schemas
     */
    public static function addMany(array ...$schemas): void
    {
        foreach ($schemas as $schema) {
            self::add($schema);
        }
    }

    /**
     * Convenience: add a BreadcrumbList schema.
     *
     * @param  array{name: string, url: string}[]  $items
     */
    public static function breadcrumbs(array $items): void
    {
        self::add(Schema::breadcrumbList($items));
    }

    /**
     * Add site-level schemas from config (Organization + WebSite).
     * Call once per request, typically in a service provider or middleware.
     */
    public static function defaults(): void
    {
        $org = config('url-manager.json_ld.organization');

        if (is_array($org) && ! empty($org['name'])) {
            self::add(Schema::organization($org));
        }

        $site = config('url-manager.json_ld.website');

        if (is_array($site) && ! empty($site['name'])) {
            $searchUrl = $site['search_url'] ?? null;
            unset($site['search_url']);

            if ($searchUrl) {
                $site['potentialAction'] = Schema::searchAction($searchUrl);
            }

            self::add(Schema::webSite($site));
        }
    }

    /**
     * Get all collected schemas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function schemas(): array
    {
        return self::$schemas;
    }

    /**
     * Render all schemas as HTML script tags.
     * Safe for use with {!! !!} in Blade.
     */
    public static function render(): string
    {
        if (self::$schemas === []) {
            return '';
        }

        $tags = [];

        foreach (self::$schemas as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $tags[] = '<script type="application/ld+json">' . $json . '</script>';
        }

        return implode("\n", $tags);
    }

    /**
     * Get all schemas as a single JSON string (for APIs / headless use).
     */
    public static function toJson(): string
    {
        if (count(self::$schemas) === 1) {
            return json_encode(self::$schemas[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode(self::$schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Clear all collected schemas. Called automatically between requests
     * in long-running processes, or manually in tests.
     */
    public static function reset(): void
    {
        self::$schemas = [];
    }
}
