<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
    <url>
        <loc>{{ url('/') }}</loc>
        <lastmod>{{ now()->toW3cString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    @foreach($urls as $url)
        @if($url->shouldIndex())
        <url>
            <loc>{{ $url->getAbsoluteUrl() }}</loc>
            @if($url->last_modified_at)
            <lastmod>{{ $url->last_modified_at->toW3cString() }}</lastmod>
            @endif
            <changefreq>{{ $url->getSitemapChangefreq() }}</changefreq>
            <priority>{{ $url->getSitemapPriority() }}</priority>
        </url>
        @endif
    @endforeach
</urlset>