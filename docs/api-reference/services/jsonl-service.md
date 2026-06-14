# JsonlCacheService - Référence Technique

## Description

Service de cache compatible PSR-16 utilisant le stockage JSONL. Implémente toutes les méthodes standard d'un cache (get, set, delete, clear, multiple) avec support du TTL (Time To Live).

## Hiérarchie / Implémentations

```
JsonlCacheInterface (PSR-16 compatible)
    └── JsonlCacheService
```

## Rôle principal

Fournir une implémentation de cache persistant basée sur des fichiers JSONL, avec une organisation par clé et une gestion automatique de l'expiration. Le service décompose les opérations en :
- Normalisation des clés (préfixe + hash des clés longues)
- Calcul du TTL en secondes
- Sérialisation/désérialisation JSON des valeurs
- Gestion des fichiers via `JsonlService`

## DETAILS

[Voir la classe JsonlCacheService](https://github.com/andydefer/jsonl-cache/blob/main/src/Services/JsonlCacheService.php)

## API / Méthodes publiques

### `__construct(JsonlService $jsonl, CachePathStrategy $strategy, JsonlCacheConfig $config, HydrationService $hydration, FileSystemInterface $fs): void`

Injecte les dépendances nécessaires au fonctionnement du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$jsonl` | `JsonlService` | Service de lecture/écriture JSONL |
| `$strategy` | `CachePathStrategy` | Stratégie de génération des chemins |
| `$config` | `JsonlCacheConfig` | Configuration du cache |
| `$hydration` | `HydrationService` | Service d'hydratation des objets |
| `$fs` | `FileSystemInterface` | Service de système de fichiers |

### `get(string $key, mixed $default = null): mixed`

Récupère une valeur du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |
| `$default` | `mixed` | Valeur par défaut si la clé n'existe pas |

**Retourne :** `mixed` - La valeur stockée ou `$default`

**Exemple :**
```php
$value = $cache->get('user_123', 'default');
```

### `set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool`

Stocke une valeur dans le cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |
| `$value` | `mixed` | Valeur à stocker (sérialisable en JSON) |
| `$ttl` | `null|int|DateInterval` | Durée de vie (secondes ou DateInterval) |

**Retourne :** `bool` - `true` si succès, `false` sinon

**Exemple :**
```php
// Stocker pour 1 heure
$cache->set('user_123', ['name' => 'John'], 3600);

// Stocker avec DateInterval
$cache->set('user_123', $data, new DateInterval('PT1H'));
```

### `delete(string $key): bool`

Supprime un élément du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `bool` - `true` si succès, `false` sinon

### `clear(): bool`

Vide complètement le cache (supprime tous les fichiers).

**Retourne :** `bool` - `true` si succès, `false` sinon

### `getMultiple(iterable $keys, mixed $default = null): iterable`

Récupère plusieurs éléments du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$keys` | `iterable<string>` | Liste des clés |
| `$default` | `mixed` | Valeur par défaut pour les clés manquantes |

**Retourne :** `iterable<string, mixed>` - Tableau associatif clé → valeur

**Exemple :**
```php
$values = $cache->getMultiple(['key1', 'key2', 'key3'], 'not found');
```

### `setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool`

Stocke plusieurs éléments dans le cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$values` | `iterable<string, mixed>` | Tableau associatif clé → valeur |
| `$ttl` | `null|int|DateInterval` | Durée de vie commune |

**Retourne :** `bool` - `true` si tous les éléments ont été stockés, `false` sinon

**Exemple :**
```php
$cache->setMultiple([
    'user1' => ['name' => 'John'],
    'user2' => ['name' => 'Jane'],
], 3600);
```

### `deleteMultiple(iterable $keys): bool`

Supprime plusieurs éléments du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$keys` | `iterable<string>` | Liste des clés à supprimer |

**Retourne :** `bool` - `true` si tous les éléments ont été supprimés, `false` sinon

### `has(string $key): bool`

Vérifie si un élément existe et n'est pas expiré.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `bool` - `true` si l'élément existe et est valide, `false` sinon

### `getRecord(string $key): ?CacheRecord`

Récupère l'enregistrement brut du cache (objet `CacheRecord`).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `CacheRecord|null` - L'enregistrement ou `null` s'il n'existe pas

### `getRaw(string $key): ?string`

Récupère le contenu JSON brut du fichier cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `string|null` - Le JSON brut ou `null` s'il n'existe pas

## Cas d'utilisation

### Cas 1 : Cache de session utilisateur

```php
<?php

declare(strict_types=1);

final class UserSessionManager
{
    public function __construct(
        private readonly JsonlCacheService $cache,
    ) {}

    public function storeUserSession(int $userId, array $userData): void
    {
        $key = "user_session_{$userId}";
        $this->cache->set($key, $userData, 3600); // 1 heure
    }

    public function getUserSession(int $userId): ?array
    {
        $key = "user_session_{$userId}";
        return $this->cache->get($key);
    }
}
```

### Cas 2 : Mise en cache d'API externe

```php
<?php

declare(strict_types=1);

final class ApiClient
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly JsonlCacheService $cache,
        private readonly HttpClient $http,
    ) {}

    public function getUsers(): array
    {
        $cacheKey = 'api_users_list';

        // Tentative de lecture du cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Appel API
        $users = $this->http->get('/users');

        // Stockage en cache
        $this->cache->set($cacheKey, $users, self::CACHE_TTL);

        return $users;
    }
}
```

### Cas 3 : Opérations par lots

```php
<?php

declare(strict_types=1);

final class BatchCacheProcessor
{
    public function __construct(
        private readonly JsonlCacheService $cache,
    ) {}

    public function warmupCache(array $items): void
    {
        $this->cache->setMultiple($items, 86400); // 24 heures
    }

    public function getMultiplePreferences(array $userIds): array
    {
        $keys = array_map(fn($id) => "user_prefs_{$id}", $userIds);
        return $this->cache->getMultiple($keys, []);
    }

    public function clearUserData(array $userIds): void
    {
        $keys = array_map(fn($id) => "user_data_{$id}", $userIds);
        $this->cache->deleteMultiple($keys);
    }
}
```

## Gestion des erreurs

| Situation | Comportement | Retour |
|-----------|--------------|--------|
| TTL invalide | `InvalidArgumentException` | Exception levée |
| Sérialisation JSON impossible | Log interne, retour `false` | `false` |
| Fichier introuvable pour `get()` | Retourne la valeur par défaut | `$default` |
| Élément expiré | Suppression automatique + retour `$default` | `$default` |
| Erreur d'écriture fichier | Log interne | `false` |

## Flux d'exécution (set)

```
set($key, $value, $ttl)
    │
    ├── getTtlSeconds($ttl) → conversion en secondes
    ├── createExpiresAt() → création DateTimeVO
    ├── normalizeKey() → ajout préfixe, hash si >64
    ├── json_encode($value) → sérialisation
    │
    ├── Suppression ancien fichier
    │   └── strategy->getFilePathForKey($normalizedKey)
    │
    ├── Création CacheRecord
    │   └── new CacheRecord(key, value, expires_at, created_at)
    │
    └── jsonl->write($record) → écriture JSONL
```

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `get()` | O(1) | Lecture d'un seul fichier |
| `set()` | O(1) | Écriture d'un seul fichier |
| `has()` | O(1) | Lecture + vérification expiration |
| `clear()` | O(n) | Suppression récursive |
| `getMultiple()` | O(k) | k = nombre de clés |
| `setMultiple()` | O(k) | k = nombre de clés |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Requis (readonly properties) |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ❌ |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\JsonlCache\Services\JsonlCacheService;
use AndyDefer\JsonlCache\Config\JsonlCacheConfig;
use AndyDefer\LaravelJsonl\JsonlService;
use AndyDefer\LaravelJsonl\Contexts\JsonlContext;
use AndyDefer\JsonlCache\Strategies\CachePathStrategy;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\PhpServices\Enums\PermissionMode;

// Configuration
$fs = new FileSystemService();
$hydration = new HydrationService();
$config = new JsonlCacheConfig(app('config'));
$strategy = new CachePathStrategy('/tmp/cache', 2);
$jsonl = new JsonlService($strategy, $fs, new JsonlContext(), directoryPermission: PermissionMode::DIRECTORY);

$cache = new JsonlCacheService($jsonl, $strategy, $config, $hydration, $fs);

// Stockage
$cache->set('user_123', ['name' => 'John Doe', 'email' => 'john@example.com'], 3600);
$cache->set('user_456', ['name' => 'Jane Smith'], 3600);

// Lecture
$user = $cache->get('user_123');
if ($user) {
    echo $user['name']; // John Doe
}

// Vérification
if ($cache->has('user_123')) {
    echo "Cache hit";
}

// Opérations par lots
$users = $cache->getMultiple(['user_123', 'user_456']);
foreach ($users as $key => $data) {
    echo "{$key}: {$data['name']}\n";
}

// Suppression
$cache->delete('user_123');
$cache->clear(); // Tout supprimer
```

## Voir aussi

- `JsonlCacheInterface` - Interface PSR-16 implémentée
- `CacheRecord` - Record de données
- `CachePathStrategy` - Stratégie de chemin
- `JsonlService` - Service de base JSONL
- `JsonlCacheConfig` - Configuration du cache
- `DateTimeVO` - Value Object pour les dates
---