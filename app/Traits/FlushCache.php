<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait FlushCache
{
    public static function bootFlushCache()
    {
        static::created(function ($model) {
            static::flushCache($model);
        });
        static::updated(function ($model) {
            static::flushCache($model);
        });
        static::deleted(function ($model) {
            static::flushCache($model);
        });
    }

    private static function flushCache($model): void
    {
        $keys = method_exists($model, 'cacheKeys') ? $model->cacheKeys() : [];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $table = $model->getTable();

        $urls = match ($table) {
            // 'pages' => [route('page-detail', $model->slug), route('setting'), route('sitemap.index', route('sitemap.pages'))],
            'menus' => [route('setting')],
            'banners' => [route('home')],
            // 'blog_categories' => [route('home'), route('blog-category.list'), route('blog-category.detail', $model->slug), route('blog-category.blogs', $model->slug)],
            default => []
        };

        if (count($urls)) {
            flushCloudflareCache($urls);
        }
    }
}
