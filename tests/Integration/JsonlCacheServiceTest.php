<?php

declare(strict_types=1);

namespace AndyDefer\JsonlCache\Tests\Integration;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\JsonlCache\Config\JsonlCacheConfig;
use AndyDefer\JsonlCache\Services\JsonlCacheService;
use AndyDefer\JsonlCache\Strategies\CachePathStrategy;
use AndyDefer\JsonlCache\Tests\IntegrationTestCase;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;
use DateInterval;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class JsonlCacheServiceTest extends IntegrationTestCase
{
    private JsonlCacheService $cache;

    private FileSystemService $fs;

    private string $tempDir;

    private CachePathStrategy $strategy;

    private JsonlService $jsonl;

    private JsonlCacheConfig $config;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new FileSystemService;
        $this->hydration = new HydrationService;
        $this->tempDir = sys_get_temp_dir().'/jsonl_cache_test_'.uniqid();
        $this->fs->makeDirectory($this->tempDir, PermissionMode::DIRECTORY, true);

        // Configuration de test
        $configArray = new \stdClass;
        $configArray->{'jsonl-cache'} = [
            'base_path' => $this->tempDir,
            'default_ttl' => 3600,
            'hash_levels' => 2,
            'enabled' => true,
            'prefix' => 'test',
        ];

        $configRepository = $this->createMock(Repository::class);
        $configRepository->method('get')->willReturnCallback(function ($key, $default = null) use ($configArray) {
            $parts = explode('.', $key);
            if ($parts[0] === 'jsonl-cache') {
                $value = $configArray->{'jsonl-cache'};
                for ($i = 1; $i < count($parts); $i++) {
                    if (! isset($value[$parts[$i]])) {
                        return $default;
                    }
                    $value = $value[$parts[$i]];
                }

                return $value;
            }

            return $default;
        });

        $this->config = new JsonlCacheConfig($configRepository);
        $this->strategy = new CachePathStrategy($this->tempDir, 2);

        $this->jsonl = new JsonlService(
            pathStrategy: $this->strategy,
            fileSystem: $this->fs,
            context: new JsonlContext,
            directoryPermission: PermissionMode::DIRECTORY,
        );

        $this->cache = new JsonlCacheService(
            jsonl: $this->jsonl,
            strategy: $this->strategy,
            config: $this->config,
            hydration: $this->hydration,
            fs: $this->fs,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function getFileContent(string $filePath): string
    {
        return $this->fs->get($filePath);
    }

    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
    }

    // ============================================================
    // Tests basiques PSR-16
    // ============================================================

    public function test_set_and_get_returns_cached_value(): void
    {
        $key = 'user_123';
        $value = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $result = $this->cache->set($key, $value);
        $cached = $this->cache->get($key);

        $this->assertTrue($result);
        $this->assertEquals($value, $cached);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        $key = 'nonexistent_key';
        $default = 'default_value';

        $result = $this->cache->get($key, $default);

        $this->assertSame($default, $result);
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $key = 'existing_key';
        $this->cache->set($key, 'some value');

        $result = $this->cache->has($key);

        $this->assertTrue($result);
    }

    public function test_has_returns_false_for_nonexistent_key(): void
    {
        $key = 'nonexistent_key';

        $result = $this->cache->has($key);

        $this->assertFalse($result);
    }

    public function test_delete_removes_key_from_cache(): void
    {
        $key = 'to_delete';
        $this->cache->set($key, 'some value');
        $this->assertTrue($this->cache->has($key));

        $result = $this->cache->delete($key);

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has($key));
    }

    public function test_delete_returns_true_even_for_nonexistent_key(): void
    {
        $result = $this->cache->delete('nonexistent_key');

        $this->assertTrue($result);
    }

    public function test_clear_removes_all_keys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->clear();

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    // ============================================================
    // Tests de TTL (Time To Live)
    // ============================================================

    public function test_set_with_ttl_seconds_expires_after_time(): void
    {
        $key = 'expiring_key';
        $value = 'temporary value';

        $this->cache->set($key, $value, 1);

        $this->assertEquals($value, $this->cache->get($key));

        sleep(2);

        $this->assertNull($this->cache->get($key));
    }

    public function test_set_with_date_interval_ttl(): void
    {
        $key = 'interval_key';
        $value = 'interval value';
        $interval = new DateInterval('PT1S');

        $this->cache->set($key, $value, $interval);

        $this->assertEquals($value, $this->cache->get($key));

        sleep(2);

        $this->assertNull($this->cache->get($key));
    }

    public function test_set_without_ttl_uses_default_ttl(): void
    {
        $key = 'default_ttl_key';
        $value = 'default ttl value';

        $this->cache->set($key, $value);

        // Utiliser la clé normalisée (avec préfixe) pour le calcul du chemin
        $normalizedKey = 'test_'.$key;
        $hash = md5($normalizedKey);
        $filePath = $this->tempDir.'/'.$hash[0].'/'.$hash[1].'/'.$normalizedKey.'.jsonl';

        $this->assertFileExists($filePath, "Fichier non trouvé: {$filePath}");

        $content = $this->getFileContent($filePath);
        $data = json_decode($content, true);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertNotNull($data['expires_at']);
    }

    private function printDirectoryTree(string $dir, string $prefix = ''): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        $items = array_diff($items, ['.', '..']);

        foreach ($items as $index => $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            $isLast = ($index === array_key_last($items));

            echo $prefix.($isLast ? '└── ' : '├── ').$item;

            if (is_dir($path)) {
                echo "/\n";
                $this->printDirectoryTree($path, $prefix.($isLast ? '    ' : '│   '));
            } else {
                $size = filesize($path);
                echo " ({$size} bytes)\n";
            }
        }
    }

    // ============================================================
    // Tests de normalisation des clés
    // ============================================================

    public function test_long_key_is_hashed(): void
    {
        $longKey = str_repeat('a', 100);
        $value = 'test value';

        $this->cache->set($longKey, $value);
        $cached = $this->cache->get($longKey);

        $this->assertEquals($value, $cached);
    }

    public function test_key_with_dangerous_characters_is_sanitized(): void
    {
        $key = 'user/with/slashes?and&special@chars';
        $value = 'sanitized key test';

        $this->cache->set($key, $value);
        $cached = $this->cache->get($key);

        $this->assertEquals($value, $cached);
    }

    // ============================================================
    // Tests de sérialisation
    // ============================================================

    public function test_store_and_retrieve_array(): void
    {
        $key = 'array_key';
        $value = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->cache->set($key, $value);
        $cached = $this->cache->get($key);

        $this->assertEquals($value, $cached);
    }

    public function test_store_and_retrieve_object(): void
    {
        $key = 'object_key';
        $value = (object) ['name' => 'John', 'age' => 30];

        $this->cache->set($key, $value);
        $cached = $this->cache->get($key);

        $this->assertEquals((array) $value, $cached);
    }

    public function test_store_and_retrieve_scalar_values(): void
    {
        $this->cache->set('string_key', 'hello');
        $this->assertSame('hello', $this->cache->get('string_key'));

        $this->cache->set('int_key', 42);
        $this->assertSame(42, $this->cache->get('int_key'));

        $this->cache->set('float_key', 3.14);
        $this->assertSame(3.14, $this->cache->get('float_key'));

        $this->cache->set('bool_true_key', true);
        $this->assertTrue($this->cache->get('bool_true_key'));

        $this->cache->set('bool_false_key', false);
        $this->assertFalse($this->cache->get('bool_false_key'));

        $this->cache->set('null_key', null);
        $this->assertNull($this->cache->get('null_key'));
    }

    // ============================================================
    // Tests de getMultiple()
    // ============================================================

    public function test_get_multiple_returns_values_for_multiple_keys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
    }

    public function test_get_multiple_returns_default_for_missing_keys(): void
    {
        $this->cache->set('key1', 'value1');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('default', $result['key2']);
        $this->assertEquals('default', $result['key3']);
    }

    // ============================================================
    // Tests de setMultiple()
    // ============================================================

    public function test_set_multiple_stores_multiple_values(): void
    {
        $values = [
            'multi1' => 'value1',
            'multi2' => 'value2',
            'multi3' => 'value3',
        ];

        $result = $this->cache->setMultiple($values);

        $this->assertTrue($result);
        $this->assertEquals('value1', $this->cache->get('multi1'));
        $this->assertEquals('value2', $this->cache->get('multi2'));
        $this->assertEquals('value3', $this->cache->get('multi3'));
    }

    public function test_set_multiple_with_ttl(): void
    {
        $values = [
            'expiring1' => 'temp1',
            'expiring2' => 'temp2',
        ];

        $result = $this->cache->setMultiple($values, 1);

        $this->assertTrue($result);
        $this->assertEquals('temp1', $this->cache->get('expiring1'));
        $this->assertEquals('temp2', $this->cache->get('expiring2'));

        sleep(2);

        $this->assertNull($this->cache->get('expiring1'));
        $this->assertNull($this->cache->get('expiring2'));
    }

    // ============================================================
    // Tests de deleteMultiple()
    // ============================================================

    public function test_delete_multiple_removes_multiple_keys(): void
    {
        $this->cache->set('del1', 'value1');
        $this->cache->set('del2', 'value2');
        $this->cache->set('del3', 'value3');

        $result = $this->cache->deleteMultiple(['del1', 'del2']);

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('del1'));
        $this->assertFalse($this->cache->has('del2'));
        $this->assertTrue($this->cache->has('del3'));
    }

    // ============================================================
    // Tests de getRecord()
    // ============================================================

    public function test_get_record_returns_cache_record(): void
    {
        $key = 'record_key';
        $value = 'record value';
        $this->cache->set($key, $value);

        $record = $this->cache->getRecord($key);

        $this->assertNotNull($record);
        $this->assertStringContainsString('test_'.$key, $record->key);
        $this->assertEquals(json_encode($value), $record->value);
    }

    public function test_get_record_returns_null_for_nonexistent_key(): void
    {
        $record = $this->cache->getRecord('nonexistent');

        $this->assertNull($record);
    }

    // ============================================================
    // Tests de getRaw()
    // ============================================================

    public function test_get_raw_returns_raw_json_content(): void
    {
        $key = 'raw_key';
        $value = ['test' => 'data'];
        $this->cache->set($key, $value);

        $raw = $this->cache->getRaw($key);

        $this->assertNotNull($raw);
        $data = json_decode($raw, true);
        $this->assertIsArray($data);
        $this->assertEquals(json_encode(['test' => 'data']), $data['value']);
    }

    public function test_get_raw_returns_null_for_nonexistent_key(): void
    {
        $raw = $this->cache->getRaw('nonexistent');

        $this->assertNull($raw);
    }

    // ============================================================
    // Tests de surcharge (overwrite)
    // ============================================================

    public function test_set_overwrites_existing_key(): void
    {
        $key = 'overwrite_key';
        $this->cache->set($key, 'old value');
        $this->assertEquals('old value', $this->cache->get($key));

        $this->cache->set($key, 'new value');

        $this->assertEquals('new value', $this->cache->get($key));
    }
}
