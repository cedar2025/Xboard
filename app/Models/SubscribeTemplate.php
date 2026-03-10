<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SubscribeTemplate extends Model
{
    protected $table = 'v2_subscribe_templates';
    protected $guarded = [];
    protected $casts = [
        'name' => 'string',
        'content' => 'string',
    ];

    private static string $cachePrefix = 'subscribe_template:';

    public static function getContent(string $name): ?string
    {
        $cacheKey = self::$cachePrefix . $name;

        return Cache::store('redis')->remember($cacheKey, 3600, function () use ($name) {
            return self::where('name', $name)->value('content');
        });
    }

    public static function setContent(string $name, ?string $content): void
    {
        self::updateOrCreate(
            ['name' => $name],
            ['content' => $content]
        );
        Cache::store('redis')->forget(self::$cachePrefix . $name);
    }

    public static function getAllContents(): array
    {
        return self::pluck('content', 'name')->toArray();
    }

    public static function flushCache(string $name): void
    {
        Cache::store('redis')->forget(self::$cachePrefix . $name);
    }
}
