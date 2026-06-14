<?php

declare(strict_types=1);

namespace AndyDefer\JsonlCache\Config;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Configuration wrapper for JSONL Cache.
 */
final class JsonlCacheConfig extends AbstractRecord
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getBasePath(): string
    {
        return $this->config->get('jsonl-cache.base_path', storage_path('jsonl-cache'));
    }

    public function getDefaultTtl(): int
    {
        return (int) $this->config->get('jsonl-cache.default_ttl', 3600);
    }

    public function getHashLevels(): int
    {
        return (int) $this->config->get('jsonl-cache.hash_levels', 2);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('jsonl-cache.enabled', true);
    }

    public function getPrefix(): string
    {
        return $this->config->get('jsonl-cache.prefix', 'cache');
    }
}
