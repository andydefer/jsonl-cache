# JSONL Cache

**Un système de cache persistant compatible PSR-16 basé sur des fichiers JSONL pour PHP 8.1+**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Concepts fondamentaux](#concepts-fondamentaux)
5. [Utilisation de base](#utilisation-de-base)
6. [Opérations avancées](#opérations-avancées)
7. [Gestion du TTL](#gestion-du-ttl)
8. [Intégration Laravel](#intégration-laravel)
9. [Tests](#tests)
10. [Architecture technique](#architecture-technique)
11. [Référence technique](#référence-technique)
12. [Licence](#licence)

---

## Introduction

### Le problème

Les caches traditionnels (Redis, Memcached, APC) sont performants mais nécessitent :
- Des services externes supplémentaires
- Une configuration complexe
- Une gestion mémoire spécifique
- Un déploiement particulier

### La solution : JSONL Cache

**JSONL Cache** est un système de cache persistant basé sur des fichiers **JSONL** (JSON Lines), compatible avec l'interface **PSR-16** (Common Interface for Caching Libraries).

| Problème | Solution JSONL Cache |
|----------|---------------------|
| Dépendance à Redis/Memcached | Stockage fichiers - 100% PHP |
| Configuration complexe | Zéro configuration, prêt à l'emploi |
| Perte de données au redémarrage | Persistance automatique |
| Pas de TTL natif | Support complet du Time To Live |
| Interface propriétaire | PSR-16 : changez de driver sans modifier votre code |

---

## Installation

```bash
composer require andydefer/jsonl-cache
```

Pour Laravel, le package s'enregistre automatiquement via son Service Provider.

### Publication de la configuration (Laravel)

```bash
php artisan vendor:publish --tag=jsonl-cache-config
```

---

## Configuration

### Fichier de configuration

```php
// config/jsonl-cache.php

return [
    // Chemin de base pour les fichiers de cache
    'base_path' => env('JSONL_CACHE_PATH', storage_path('jsonl-cache')),

    // TTL par défaut en secondes (null = pas d'expiration)
    'default_ttl' => (int) env('JSONL_CACHE_TTL', 3600),

    // Nombre de niveaux de hash (1-4)
    'hash_levels' => (int) env('JSONL_CACHE_HASH_LEVELS', 2),

    // Activation/désactivation du cache
    'enabled' => (bool) env('JSONL_CACHE_ENABLED', true),

    // Préfixe ajouté aux clés
    'prefix' => env('JSONL_CACHE_PREFIX', 'cache'),
];
```

### Variables d'environnement

```env
JSONL_CACHE_PATH=/custom/cache/path
JSONL_CACHE_TTL=7200
JSONL_CACHE_HASH_LEVELS=2
JSONL_CACHE_ENABLED=true
JSONL_CACHE_PREFIX=app
```

---

## Concepts fondamentaux

### Une entrée = un fichier JSONL

```
storage/jsonl-cache/
├── 0/
│   ├── d/
│   │   └── user_123.jsonl
│   └── f/
│       └── session_abc.jsonl
├── e/
│   └── 1/
│       └── product_456.jsonl
└── f/
    └── 3/
        └── config_app.jsonl
```

### Structure d'un fichier cache

```json
{
    "key": "cache_user_123",
    "value": "{\"name\":\"John Doe\",\"email\":\"john@example.com\"}",
    "expires_at": "2026-06-14T15:30:00+00:00",
    "created_at": "2026-06-14T14:30:00+00:00"
}
```

### Organisation par hash MD5

| Niveau | Description | Exemple |
|--------|-------------|---------|
| 1 | Premier caractère du MD5 | `e` |
| 2 | Second caractère du MD5 | `1` |
| Fichier | Clé nettoyée | `user_123.jsonl` |

**Pourquoi ?** Éviter d'avoir trop de fichiers dans un même répertoire (limites du système de fichiers).

---

## Utilisation de base

### Instanciation du service

**Sans Laravel :**

```php
use AndyDefer\JsonlCache\Services\JsonlCacheService;
use AndyDefer\JsonlCache\Config\JsonlCacheConfig;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\JsonlCache\Strategies\CachePathStrategy;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpServices\Services\FileSystemService;

$config = new JsonlCacheConfig(app('config'));
$strategy = new CachePathStrategy('/tmp/cache', 2);
$fs = new FileSystemService();
$hydration = new HydrationService();
$jsonl = new JsonlService($strategy, $fs, new JsonlContext());

$cache = new JsonlCacheService($jsonl, $strategy, $config, $hydration, $fs);
```

**Avec Laravel :**

```php
use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;

class MyController extends Controller
{
    public function __construct(
        private readonly JsonlCacheInterface $cache,
    ) {}

    public function index()
    {
        // Utilisation directe
    }
}
```

### Stocker une valeur

```php
// Stocker avec TTL par défaut (config)
$cache->set('user_123', ['name' => 'John Doe']);

// Stocker pour 1 heure
$cache->set('user_123', $userData, 3600);

// Stocker sans expiration
$cache->set('config_app', $config, null);
```

### Lire une valeur

```php
// Lecture simple
$user = $cache->get('user_123');

// Avec valeur par défaut
$user = $cache->get('user_123', ['name' => 'Guest']);

// Vérifier l'existence
if ($cache->has('user_123')) {
    echo "Cache hit!";
}
```

### Supprimer une valeur

```php
// Supprimer une entrée
$cache->delete('user_123');

// Vider tout le cache
$cache->clear();
```

### Types de valeurs supportés

```php
// Tableau
$cache->set('array_key', ['a' => 1, 'b' => 2]);

// Objet (devient tableau)
$cache->set('object_key', (object) ['name' => 'John']);

// Scalaires
$cache->set('string_key', 'hello');
$cache->set('int_key', 42);
$cache->set('float_key', 3.14);
$cache->set('bool_key', true);
$cache->set('null_key', null);
```

---

## Opérations avancées

### Opérations par lots (Multiple)

```php
// Lecture multiple
$values = $cache->getMultiple(['user_123', 'user_456', 'user_789'], 'default');

// Stockage multiple
$cache->setMultiple([
    'user_123' => ['name' => 'John'],
    'user_456' => ['name' => 'Jane'],
    'user_789' => ['name' => 'Bob'],
], 3600);

// Suppression multiple
$cache->deleteMultiple(['user_123', 'user_456']);
```

### Accès aux données brutes

```php
// Récupérer l'enregistrement complet
$record = $cache->getRecord('user_123');
if ($record) {
    echo $record->key;         // 'cache_user_123'
    echo $record->value;       // '{"name":"John"}'
    echo $record->expires_at;  // DateTimeVO
    echo $record->created_at;  // DateTimeVO
}

// Récupérer le JSON brut
$raw = $cache->getRaw('user_123');
// '{"key":"cache_user_123","value":"{\"name\":\"John\"}","expires_at":"..."}'
```

### Écrasement automatique

La méthode `set()` écrase automatiquement la valeur existante :

```php
$cache->set('key', 'old value');
$cache->set('key', 'new value'); // Écrase l'ancienne
```

---

## Gestion du TTL

### Différents formats de TTL

```php
// TTL en secondes (int)
$cache->set('key', $value, 3600);     // 1 heure
$cache->set('key', $value, 60);       // 1 minute

// TTL via DateInterval
$cache->set('key', $value, new DateInterval('PT1H'));  // 1 heure
$cache->set('key', $value, new DateInterval('P1D'));   // 1 jour

// Pas de TTL (null = valeur par défaut de la config)
$cache->set('key', $value, null);

// Expiration désactivée (0)
$cache->set('key', $value, 0);
```

### Comportement de l'expiration

```php
// Stocker pour 1 seconde
$cache->set('expiring_key', 'temporary', 1);

// Immédiatement disponible
echo $cache->get('expiring_key'); // 'temporary'

// Attendre l'expiration
sleep(2);

// Plus disponible
echo $cache->get('expiring_key', 'default'); // 'default'
$cache->has('expiring_key'); // false
```

### TTL par défaut

La configuration `default_ttl` s'applique automatiquement :

```php
// config/jsonl-cache.php
'default_ttl' => 3600,  // 1 heure par défaut

// Utilisation
$cache->set('key', $value); // Expire dans 1 heure
$cache->set('key', $value, 0); // Jamais
```

---

## Intégration Laravel

### Service Provider

Le package enregistre automatiquement :

```php
// Aliases disponibles
$cache = app(JsonlCacheInterface::class);
$cache = app('jsonl-cache');
```

### Exemple dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;
use App\Models\User;

final class UserController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly JsonlCacheInterface $cache,
    ) {}

    public function show(int $id): JsonResponse
    {
        $cacheKey = "user_{$id}";

        // Tentative de lecture du cache
        $user = $this->cache->get($cacheKey);

        if ($user === null) {
            $user = User::find($id);
            $this->cache->set($cacheKey, $user->toArray(), self::CACHE_TTL);
        }

        return response()->json($user);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        // Mise à jour en base...
        $user = User::find($id);
        $user->update($request->validated());

        // Invalidation du cache
        $this->cache->delete("user_{$id}");

        return response()->json(['message' => 'Updated']);
    }
}
```

### Exemple dans un service

```php
<?php

namespace App\Services;

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;

final class WeatherService
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly JsonlCacheInterface $cache,
        private readonly WeatherApiClient $api,
    ) {}

    public function getForecast(string $city): array
    {
        $cacheKey = "weather_{$city}";

        $forecast = $this->cache->get($cacheKey);
        if ($forecast !== null) {
            return $forecast;
        }

        $forecast = $this->api->fetchForecast($city);
        $this->cache->set($cacheKey, $forecast, self::CACHE_TTL);

        return $forecast;
    }
}
```

---

## Tests

### Tester le cache

```php
<?php

namespace Tests\Unit;

use AndyDefer\JsonlCache\Services\JsonlCacheService;
use Tests\TestCase;

final class CacheTest extends TestCase
{
    private JsonlCacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = app(JsonlCacheInterface::class);
    }

    public function test_cache_set_and_get(): void
    {
        $key = 'test_key';
        $value = ['name' => 'John', 'email' => 'john@example.com'];

        $this->cache->set($key, $value);
        $cached = $this->cache->get($key);

        $this->assertEquals($value, $cached);
    }

    public function test_cache_ttl(): void
    {
        $key = 'expiring_key';
        $value = 'temporary';

        $this->cache->set($key, $value, 1);
        $this->assertEquals($value, $this->cache->get($key));

        sleep(2);
        $this->assertNull($this->cache->get($key));
    }

    public function test_cache_delete(): void
    {
        $key = 'to_delete';
        $this->cache->set($key, 'value');
        $this->assertTrue($this->cache->has($key));

        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }
}
```

---

## Architecture technique

### Composants principaux

| Composant | Rôle |
|-----------|------|
| `JsonlCacheService` | Service principal (implémentation PSR-16) |
| `CachePathStrategy` | Stratégie de chemin (organisation par hash MD5) |
| `CacheRecord` | DTO des données de cache |
| `JsonlService` | Service de lecture/écriture JSONL (package `laravel-jsonl`) |
| `JsonlCacheConfig` | Wrapper de configuration |
| `JsonlCacheInterface` | Interface PSR-16 étendue |

### Dépendances

```
JsonlCacheService
    ├── JsonlService (laravel-jsonl)
    ├── CachePathStrategy
    ├── JsonlCacheConfig
    ├── HydrationService
    └── FileSystemInterface
```

### Flux d'exécution (set)

```
set($key, $value, $ttl)
    │
    ├── normalizeKey() → ajout préfixe, hash si >64
    ├── getTtlSeconds() → conversion TTL
    ├── createExpiresAt() → DateTimeVO
    ├── json_encode($value) → sérialisation
    ├── Suppression ancien fichier
    ├── Création CacheRecord
    └── jsonl->write() → écriture JSONL
```

### Flux d'exécution (get)

```
get($key)
    │
    ├── normalizeKey()
    ├── getRecord()
    │   ├── strategy->getFilePathForKey()
    │   ├── fs->exists()
    │   └── jsonl->readAll()
    ├── isExpired() → vérification expiration
    ├── delete() si expiré
    └── json_decode($record->value) → désérialisation
```

---

## Référence technique

### Services

| Service | Description | Documentation |
|---------|-------------|---------------|
| `JsonlCacheService` | Service principal PSR-16 | [Voir référence](./docs/api-reference/services/jsonl-cache-service.md) |

### Stratégies

| Stratégie | Description | Documentation |
|-----------|-------------|---------------|
| `CachePathStrategy` | Organisation par hash MD5 | [Voir référence](./docs/api-reference/strategies/cache-path-strategy.md) |

### Interfaces

| Interface | Description | Documentation |
|-----------|-------------|---------------|
| `JsonlCacheInterface` | PSR-16 + méthodes additionnelles | [Voir référence](./docs/api-reference/contracts/jsonl-cache-interface.md) |


---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
---