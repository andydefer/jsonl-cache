title: "JsonlCacheInterface"
category: "Contracts"
order: 1
---

# JsonlCacheInterface - Référence Technique

## Description

Interface de cache compatible PSR-16 (Common Interface for Caching Libraries) avec des méthodes additionnelles pour l'accès aux données brutes et aux enregistrements complets.

## Hiérarchie / Implémentations

```
Psr\SimpleCache\CacheInterface (PSR-16)
    └── JsonlCacheInterface (étend)

Implémentations :
    └── JsonlCacheService
```

## Rôle principal

Définir le contrat pour un système de cache persistant basé sur JSONL. Cette interface étend la norme PSR-16 en ajoutant des méthodes spécifiques pour :
- L'accès aux enregistrements bruts (`getRecord()`)
- La récupération du JSON non désérialisé (`getRaw()`)

L'utilisation de l'interface PSR-16 garantit l'interchangeabilité avec d'autres implémentations de cache (Redis, Memcached, APC, etc.).

## DETAILS

[Voir la classe JsonlCacheInterface](https://github.com/andydefer/jsonl-cache/blob/main/src/Contracts/JsonlCacheInterface.php)

## API / Méthodes publiques

### `get(string $key, mixed $default = null): mixed`

Récupère une valeur du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |
| `$default` | `mixed` | Valeur par défaut si la clé n'existe pas |

**Retourne :** `mixed` - La valeur stockée (désérialisée) ou `$default`

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si la clé est invalide

**Exemple :**
```php
$value = $cache->get('user_123');
$value = $cache->get('user_123', 'default_value');
```

---

### `set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool`

Stocke une valeur dans le cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |
| `$value` | `mixed` | Valeur à stocker (doit être sérialisable en JSON) |
| `$ttl` | `null\|int\|DateInterval` | Durée de vie (null = valeur par défaut) |

**Retourne :** `bool` - `true` si succès, `false` sinon

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si la clé est invalide

**Exemple :**
```php
// Stocker pour 1 heure
$cache->set('user_123', $userData, 3600);

// Stocker avec DateInterval
$cache->set('user_123', $userData, new DateInterval('PT1H'));

// Stocker avec TTL par défaut
$cache->set('user_123', $userData);
```

---

### `delete(string $key): bool`

Supprime un élément du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `bool` - `true` si succès, `false` sinon

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si la clé est invalide

**Exemple :**
```php
$cache->delete('user_123');
```

---

### `clear(): bool`

Vide complètement le cache (supprime tous les éléments).

**Retourne :** `bool` - `true` si succès, `false` sinon

**Exemple :**
```php
$cache->clear(); // Tout supprimer
```

---

### `getMultiple(iterable $keys, mixed $default = null): iterable`

Récupère plusieurs éléments du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$keys` | `iterable<string>` | Liste des clés à récupérer |
| `$default` | `mixed` | Valeur par défaut pour les clés manquantes |

**Retourne :** `iterable<string, mixed>` - Tableau associatif clé → valeur

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si une clé est invalide

**Exemple :**
```php
$values = $cache->getMultiple(['user_123', 'user_456', 'user_789'], 'not found');
// ['user_123' => [...], 'user_456' => 'not found', ...]
```

---

### `setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool`

Stocke plusieurs éléments dans le cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$values` | `iterable<string, mixed>` | Tableau associatif clé → valeur |
| `$ttl` | `null\|int\|DateInterval` | Durée de vie commune |

**Retourne :** `bool` - `true` si tous les éléments ont été stockés, `false` sinon

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si une clé est invalide

**Exemple :**
```php
$cache->setMultiple([
    'user_123' => ['name' => 'John'],
    'user_456' => ['name' => 'Jane'],
], 3600);
```

---

### `deleteMultiple(iterable $keys): bool`

Supprime plusieurs éléments du cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$keys` | `iterable<string>` | Liste des clés à supprimer |

**Retourne :** `bool` - `true` si tous les éléments ont été supprimés, `false` sinon

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si une clé est invalide

**Exemple :**
```php
$cache->deleteMultiple(['user_123', 'user_456']);
```

---

### `has(string $key): bool`

Vérifie si un élément existe et n'est pas expiré.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `bool` - `true` si l'élément existe et est valide, `false` sinon

**Exceptions :** `Psr\SimpleCache\InvalidArgumentException` - Si la clé est invalide

**Exemple :**
```php
if ($cache->has('user_123')) {
    echo "Cache hit!";
}
```

---

### `getRecord(string $key): ?CacheRecord` *(Méthode additionnelle)*

Récupère l'enregistrement brut du cache (objet `CacheRecord` contenant toutes les métadonnées).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `CacheRecord|null` - L'enregistrement ou `null` s'il n'existe pas

**Exemple :**
```php
$record = $cache->getRecord('user_123');
if ($record) {
    echo $record->key;          // 'cache_user_123'
    echo $record->value;        // '{"name":"John"}'
    echo $record->expires_at;   // DateTimeVO
    echo $record->created_at;   // DateTimeVO
}
```

---

### `getRaw(string $key): ?string` *(Méthode additionnelle)*

Récupère le contenu JSON brut du fichier cache.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé unique de l'élément |

**Retourne :** `string|null` - Le JSON brut ou `null` s'il n'existe pas

**Exemple :**
```php
$raw = $cache->getRaw('user_123');
// '{"key":"cache_user_123","value":"{\"name\":\"John\"}","expires_at":"..."}'
```

---

## Cas d'utilisation

### Cas 1 : Cache d'API avec PSR-16 standard

```php
<?php

declare(strict_types=1);

final class ApiClient
{
    public function __construct(
        private readonly JsonlCacheInterface $cache,
        private readonly HttpClient $http,
    ) {}

    public function getUsers(): array
    {
        $cacheKey = 'api_users_list';
        
        // Tentative de lecture (méthode PSR-16 standard)
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Appel API
        $users = $this->http->get('/users');
        
        // Stockage (méthode PSR-16 standard)
        $this->cache->set($cacheKey, $users, 3600);
        
        return $users;
    }
}
```

### Cas 2 : Inspection des métadonnées avec getRecord()

```php
<?php

declare(strict_types=1);

final class CacheInspector
{
    public function __construct(
        private readonly JsonlCacheInterface $cache,
    ) {}

    public function inspect(string $key): array
    {
        // Utilisation de la méthode additionnelle getRecord()
        $record = $this->cache->getRecord($key);
        
        if ($record === null) {
            return ['status' => 'not_found'];
        }
        
        $now = new DateTimeVO();
        $isExpired = $record->expires_at && $record->expires_at->isBefore($now);
        
        return [
            'status' => $isExpired ? 'expired' : 'active',
            'created_at' => $record->created_at->value,
            'expires_at' => $record->expires_at?->value,
            'ttl_remaining' => $record->expires_at 
                ? $now->diff($record->expires_at) 
                : null,
            'value_preview' => substr($record->value, 0, 100),
        ];
    }
}
```

### Cas 3 : Debug avec getRaw()

```php
<?php

declare(strict_types=1);

final class CacheDebugger
{
    public function __construct(
        private readonly JsonlCacheInterface $cache,
    ) {}

    public function debug(string $key): void
    {
        // Méthode PSR-16 standard
        $value = $this->cache->get($key);
        echo "Valeur désérialisée: " . print_r($value, true) . "\n";
        
        // Méthode additionnelle getRaw()
        $raw = $this->cache->getRaw($key);
        echo "JSON brut: {$raw}\n";
        
        // Méthode additionnelle getRecord()
        $record = $this->cache->getRecord($key);
        if ($record) {
            echo "Créé le: {$record->created_at->value}\n";
            echo "Expire le: {$record->expires_at?->value}\n";
        }
    }
}
```

### Cas 4 : Migration depuis un autre cache PSR-16

```php
<?php

declare(strict_types=1);

final class CacheMigrator
{
    public function __construct(
        private readonly JsonlCacheInterface $newCache,
        private readonly \Psr\SimpleCache\CacheInterface $oldCache,
    ) {}

    public function migrate(string $key): void
    {
        // Méthode PSR-16 standard - fonctionne avec n'importe quel cache
        $value = $oldCache->get($key);
        
        if ($value !== null) {
            // Compatible avec l'interface PSR-16
            $this->newCache->set($key, $value);
        }
    }
    
    public function migrateAll(array $keys): void
    {
        // Utilisation de getMultiple() (PSR-16)
        $values = $oldCache->getMultiple($keys);
        
        // Utilisation de setMultiple() (PSR-16)
        $this->newCache->setMultiple($values);
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Clé vide | `InvalidArgumentException` | `Cache key cannot be empty` |
| Clé non string | `InvalidArgumentException` | `Cache key must be a string` |
| TTL invalide (type non supporté) | `InvalidArgumentException` | `TTL must be null, int, or DateInterval` |
| Valeur non sérialisable | `false` retourné (pas d'exception) | - |

---

## Intégration

Cette interface est utilisée par :
- `JsonlCacheService` - Implémentation concrète

```php
// Enregistrement dans Laravel
$this->app->singleton(JsonlCacheInterface::class, function (Application $app) {
    return new JsonlCacheService(
        jsonl: $app->make('jsonl.cache'),
        strategy: $app->make(CachePathStrategy::class),
        config: $app->make(JsonlCacheConfig::class),
        hydration: $app->make(HydrationService::class),
        fs: $app->make(FileSystemInterface::class),
    );
});

// Injection
$cache = app(JsonlCacheInterface::class);
```

---

## Compatibilité PSR-16

| Méthode PSR-16 | Support | Notes |
|----------------|---------|-------|
| `get()` | ✅ | Complète |
| `set()` | ✅ | Avec support TTL |
| `delete()` | ✅ | Complète |
| `clear()` | ✅ | Complète |
| `getMultiple()` | ✅ | Complète |
| `setMultiple()` | ✅ | Avec support TTL |
| `deleteMultiple()` | ✅ | Complète |
| `has()` | ✅ | Complète |

**Méthodes additionnelles (non PSR-16) :**
- `getRecord()` - Accès aux métadonnées
- `getRaw()` - Accès au JSON brut

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Requis (readonly properties) |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ❌ |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\JsonlCache\Contracts\JsonlCacheInterface;

final class UserService
{
    private const CACHE_TTL = 3600; // 1 heure

    public function __construct(
        private readonly JsonlCacheInterface $cache,
        private readonly UserRepository $repository,
    ) {}

    public function find(int $id): ?array
    {
        $cacheKey = "user_{$id}";
        
        // Méthode PSR-16 standard
        $user = $this->cache->get($cacheKey);
        
        if ($user !== null) {
            return $user;
        }
        
        $user = $this->repository->find($id);
        
        if ($user) {
            // Méthode PSR-16 standard
            $this->cache->set($cacheKey, $user, self::CACHE_TTL);
        }
        
        return $user;
    }
    
    public function invalidate(int $id): void
    {
        $cacheKey = "user_{$id}";
        
        // Méthode PSR-16 standard
        $this->cache->delete($cacheKey);
    }
    
    public function getCacheInfo(int $id): ?array
    {
        $cacheKey = "user_{$id}";
        
        // Méthode additionnelle
        $record = $this->cache->getRecord($cacheKey);
        
        if ($record === null) {
            return null;
        }
        
        // Méthode additionnelle
        $raw = $this->cache->getRaw($cacheKey);
        
        return [
            'exists' => true,
            'created_at' => $record->created_at->value,
            'expires_at' => $record->expires_at?->value,
            'raw_json' => $raw,
        ];
    }
}
```

---

## Voir aussi

- `JsonlCacheService` - Implémentation concrète
- `CacheRecord` - Structure de données utilisée par `getRecord()`
- `Psr\SimpleCache\CacheInterface` - Interface PSR-16 parente
- `DateTimeVO` - Value Object pour les dates
---