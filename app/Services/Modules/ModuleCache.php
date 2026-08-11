<?php

namespace App\Services\Modules;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the resolved set of enabled module keys per company. Module checks run
 * on every request (middleware, menus, permission catalogue), so the resolution
 * must not hit the database each time.
 *
 * The store is deliberately conservative: an empty resolution is NEVER written.
 * A company always has at least the always-on core module, so an empty result
 * means the resolver could not do its job — most often because the
 * `company_modules` table does not exist yet (fresh install, mid-migration) or
 * because the connection was swapped underneath us (RefreshDatabase restoring
 * an in-memory SQLite PDO after the container booted). Caching that forever
 * would lock every company out of every module. Same lesson as the
 * `allTables` cache regression.
 */
class ModuleCache
{
    /**
     * The key carries the registry fingerprint, so a deploy that changes
     * config/modules.php invalidates every company's entry on its own — see
     * ModuleRegistry::fingerprint().
     */
    public const KEY_PREFIX = 'company_modules:';

    /**
     * Tracks which company keys have been written so the whole set can be
     * dropped without relying on cache tags (the database store has none).
     */
    public const INDEX_KEY = 'company_modules:index';

    public static function keyFor(int $companyId): string
    {
        return self::KEY_PREFIX.app(ModuleRegistry::class)->fingerprint().':'.$companyId;
    }

    /**
     * @param  Closure():list<string>  $resolver
     * @return list<string>
     */
    public function remember(int $companyId, Closure $resolver): array
    {
        $cached = $this->get($companyId);

        if ($cached !== null) {
            return $cached;
        }

        $resolved = array_values($resolver());

        $this->put($companyId, $resolved);

        return $resolved;
    }

    /**
     * @return list<string>|null
     */
    public function get(int $companyId): ?array
    {
        $cached = Cache::get(self::keyFor($companyId));

        return is_array($cached) && $cached !== [] ? array_values($cached) : null;
    }

    /**
     * @param  list<string>  $keys
     */
    public function put(int $companyId, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        Cache::forever(self::keyFor($companyId), array_values($keys));

        $index = $this->index();

        if (! in_array($companyId, $index, true)) {
            $index[] = $companyId;
            Cache::forever(self::INDEX_KEY, $index);
        }
    }

    public function forget(int $companyId): void
    {
        Cache::forget(self::keyFor($companyId));

        $index = array_values(array_filter($this->index(), fn (int $id): bool => $id !== $companyId));

        if ($index === []) {
            Cache::forget(self::INDEX_KEY);

            return;
        }

        Cache::forever(self::INDEX_KEY, $index);
    }

    /**
     * Drop every cached company. Called when the registry itself changes and
     * from the test bootstrap so module state never leaks between tests.
     */
    public function flush(): void
    {
        foreach ($this->index() as $companyId) {
            Cache::forget(self::keyFor($companyId));
        }

        Cache::forget(self::INDEX_KEY);
    }

    /**
     * @return list<int>
     */
    private function index(): array
    {
        $index = Cache::get(self::INDEX_KEY, []);

        return is_array($index) ? array_values(array_map('intval', $index)) : [];
    }
}
