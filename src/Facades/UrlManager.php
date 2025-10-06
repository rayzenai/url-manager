<?php

namespace RayzenAI\UrlManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void trackVisit(\Illuminate\Database\Eloquent\Model $model, ?int $userId = null, array $metadata = [])
 * @method static \RayzenAI\UrlManager\Models\Url|null generateUrl(\Illuminate\Database\Eloquent\Model $model)
 * @method static \RayzenAI\UrlManager\Models\Url createRedirect(string $from, string $to, int $statusCode = 301)
 * @method static \RayzenAI\UrlManager\Models\Url|null getUrl(\Illuminate\Database\Eloquent\Model $model)
 * @method static bool deleteUrl(\Illuminate\Database\Eloquent\Model $model)
 * @method static \RayzenAI\UrlManager\Models\Url|null findBySlug(string $slug)
 * @method static \Illuminate\Support\Collection getRedirects()
 * @method static int getVisitCount(\Illuminate\Database\Eloquent\Model $model)
 *
 * @see \RayzenAI\UrlManager\Services\UrlManagerService
 */
class UrlManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'url-manager';
    }
}
