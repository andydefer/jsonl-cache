<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Base Path
    |--------------------------------------------------------------------------
    |
    | This option determines the base path where cache files will be stored.
    |
    */
    'base_path' => env('JSONL_CACHE_PATH', storage_path('jsonl-cache')),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | This option determines the default time-to-live for cache items in seconds.
    |
    */
    'default_ttl' => (int) env('JSONL_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Hash Levels
    |--------------------------------------------------------------------------
    |
    | Number of directory levels to create from MD5 hash for key distribution.
    | Higher values create deeper directory structures (1-4 recommended).
    |
    */
    'hash_levels' => (int) env('JSONL_CACHE_HASH_LEVELS', 2),

    /*
    |--------------------------------------------------------------------------
    | Cache Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable the cache system.
    |
    */
    'enabled' => (bool) env('JSONL_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix added to all cache keys to avoid collisions.
    |
    */
    'prefix' => env('JSONL_CACHE_PREFIX', 'cache'),
];
