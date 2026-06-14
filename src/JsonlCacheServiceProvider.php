<?php

declare(strict_types=1);

namespace AndyDefer\JsonlCache;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\JsonlCache\Config\JsonlCacheConfig;
use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\JsonlCache\Services\JsonlCacheService;
use AndyDefer\JsonlCache\Strategies\CachePathStrategy;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class JsonlCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jsonl-cache.php', 'jsonl-cache');

        // Register config wrapper
        $this->app->singleton(JsonlCacheConfig::class, function (Application $app) {
            return new JsonlCacheConfig($app->make('config'));
        });

        // Register path strategy
        $this->app->singleton(CachePathStrategy::class, function (Application $app) {
            $config = $app->make(JsonlCacheConfig::class);

            return new CachePathStrategy(
                $config->getBasePath(),
                $config->getHashLevels()
            );
        });

        // Register JsonlService with cache strategy
        $this->app->singleton('jsonl.cache', function (Application $app) {
            $strategy = $app->make(CachePathStrategy::class);
            $fs = $app->make(FileSystemInterface::class);
            $context = new JsonlContext;

            return new JsonlService(
                pathStrategy: $strategy,
                fileSystem: $fs,
                context: $context,
                directoryPermission: PermissionMode::DIRECTORY,
            );
        });

        // Register main cache service
        $this->app->singleton(JsonlCacheInterface::class, function (Application $app) {
            return new JsonlCacheService(
                jsonl: $app->make('jsonl.cache'),
                strategy: $app->make(CachePathStrategy::class),
                config: $app->make(JsonlCacheConfig::class),
                hydration: $app->make(HydrationService::class),
                fs: $app->make(FileSystemInterface::class),
            );
        });

        $this->app->alias(JsonlCacheInterface::class, 'jsonl-cache');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/jsonl-cache.php' => config_path('jsonl-cache.php'),
        ], 'jsonl-cache-config');
    }
}
