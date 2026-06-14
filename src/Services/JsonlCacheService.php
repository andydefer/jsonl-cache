<?php

declare(strict_types=1);

namespace AndyDefer\JsonlCache\Services;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\JsonlCache\Config\JsonlCacheConfig;
use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use AndyDefer\JsonlCache\Records\CacheRecord;
use AndyDefer\JsonlCache\Strategies\CachePathStrategy;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use DateInterval;
use InvalidArgumentException;

/**
 * PSR-16 compatible cache service using JSONL storage.
 */
final class JsonlCacheService implements JsonlCacheInterface
{
    public function __construct(
        private readonly JsonlService $jsonl,
        private readonly CachePathStrategy $strategy,
        private readonly JsonlCacheConfig $config,
        private readonly HydrationService $hydration,
        private readonly FileSystemInterface $fs,
    ) {}

    private function getTtlSeconds(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return $this->config->getDefaultTtl();
        }

        if ($ttl instanceof DateInterval) {
            $now = new \DateTimeImmutable;
            $expiresAt = $now->add($ttl);

            return $expiresAt->getTimestamp() - $now->getTimestamp();
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        throw new InvalidArgumentException('TTL must be null, int, or DateInterval');
    }

    private function normalizeKey(string $key): string
    {
        if (strlen($key) > 64) {
            return md5($key);
        }

        return $this->config->getPrefix().'_'.$key;
    }

    private function createExpiresAt(?int $ttlSeconds): ?DateTimeVO
    {
        if ($ttlSeconds === null || $ttlSeconds <= 0) {
            return null;
        }

        return new DateTimeVO('+'.$ttlSeconds.' seconds');
    }

    private function isExpired(CacheRecord $record): bool
    {
        if ($record->expires_at === null) {
            return false;
        }

        return $record->expires_at->isBefore(new DateTimeVO);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $record = $this->getRecord($key);

        if ($record === null || $this->isExpired($record)) {
            if ($record !== null && $this->isExpired($record)) {
                $this->delete($key);
            }

            return $default;
        }

        return json_decode($record->value, true);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        try {
            $ttlSeconds = $this->getTtlSeconds($ttl);
            $expiresAt = $this->createExpiresAt($ttlSeconds);
            $normalizedKey = $this->normalizeKey($key);
            $jsonValue = json_encode($value);

            if ($jsonValue === false) {
                return false;
            }

            // Supprimer l'ancien fichier s'il existe (pour écraser)
            $filePath = $this->strategy->getFilePathForKey($normalizedKey);
            if ($this->fs->exists($filePath)) {
                $this->fs->delete($filePath);
            }

            $record = new CacheRecord(
                key: $normalizedKey,
                value: $jsonValue,
                expires_at: $expiresAt,
                created_at: new DateTimeVO,
            );

            $this->jsonl->write($record);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $normalizedKey = $this->normalizeKey($key);
            $filePath = $this->strategy->getFilePathForKey($normalizedKey);

            if ($this->fs->exists($filePath)) {
                $this->fs->delete($filePath);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            $baseDir = $this->strategy->getBaseDirectory();
            if ($this->fs->isDirectory($baseDir)) {
                $this->fs->deleteDirectory($baseDir);
                $this->fs->makeDirectory($baseDir, PermissionMode::DIRECTORY, true);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    public function has(string $key): bool
    {
        $record = $this->getRecord($key);

        return $record !== null && ! $this->isExpired($record);
    }

    public function getRecord(string $key): ?CacheRecord
    {
        $normalizedKey = $this->normalizeKey($key);
        $filePath = $this->strategy->getFilePathForKey($normalizedKey);

        if (! $this->fs->exists($filePath)) {
            return null;
        }

        $lines = $this->jsonl->readAll($filePath);

        if (empty($lines)) {
            return null;
        }

        $data = $lines[0];

        return $this->hydration->hydrate(CacheRecord::class, [
            'key' => $data['key'] ?? $normalizedKey,
            'value' => $data['value'] ?? '',
            'expires_at' => isset($data['expires_at']) ? new DateTimeVO($data['expires_at']) : null,
            'created_at' => isset($data['created_at']) ? new DateTimeVO($data['created_at']) : null,
        ]);
    }

    public function getRaw(string $key): ?string
    {
        $normalizedKey = $this->normalizeKey($key);
        $filePath = $this->strategy->getFilePathForKey($normalizedKey);

        if (! $this->fs->exists($filePath)) {
            return null;
        }

        $lines = $this->jsonl->readAll($filePath);

        return ! empty($lines) ? json_encode($lines[0]) : null;
    }
}
