<?php

use App\Services\Modules\ModuleCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->cache = app(ModuleCache::class);
    $this->cache->flush();
});

it('resolves once and serves the cached value afterwards', function () {
    $calls = 0;

    $resolver = function () use (&$calls): array {
        $calls++;

        return ['core', 'sales'];
    };

    expect($this->cache->remember(7, $resolver))->toBe(['core', 'sales'])
        ->and($this->cache->remember(7, $resolver))->toBe(['core', 'sales'])
        ->and($calls)->toBe(1);
});

it('never caches an empty resolution', function () {
    // An empty set means the resolver could not do its job (missing table,
    // swapped connection). Caching it forever would lock the company out of
    // every module, so the value is returned but not stored.
    $calls = 0;

    $resolver = function () use (&$calls): array {
        $calls++;

        return [];
    };

    expect($this->cache->remember(7, $resolver))->toBe([])
        ->and($this->cache->remember(7, $resolver))->toBe([])
        ->and($calls)->toBe(2)
        ->and(Cache::has(ModuleCache::keyFor(7)))->toBeFalse();
});

it('ignores an empty array written directly', function () {
    $this->cache->put(7, []);

    expect(Cache::has(ModuleCache::keyFor(7)))->toBeFalse()
        ->and($this->cache->get(7))->toBeNull();
});

it('keeps companies isolated from each other', function () {
    $this->cache->put(1, ['core', 'sales']);
    $this->cache->put(2, ['core', 'gym']);

    expect($this->cache->get(1))->toBe(['core', 'sales'])
        ->and($this->cache->get(2))->toBe(['core', 'gym']);

    $this->cache->forget(1);

    expect($this->cache->get(1))->toBeNull()
        ->and($this->cache->get(2))->toBe(['core', 'gym']);
});

it('flushes every cached company through its index', function () {
    $this->cache->put(1, ['core']);
    $this->cache->put(2, ['core']);
    $this->cache->put(3, ['core']);

    $this->cache->flush();

    expect($this->cache->get(1))->toBeNull()
        ->and($this->cache->get(2))->toBeNull()
        ->and($this->cache->get(3))->toBeNull()
        ->and(Cache::has(ModuleCache::INDEX_KEY))->toBeFalse();
});

it('drops the index once the last company is forgotten', function () {
    $this->cache->put(1, ['core']);
    $this->cache->forget(1);

    expect(Cache::has(ModuleCache::INDEX_KEY))->toBeFalse();
});

it('does not grow the index on repeated writes for the same company', function () {
    $this->cache->put(1, ['core']);
    $this->cache->put(1, ['core', 'sales']);
    $this->cache->put(1, ['core', 'sales', 'crm']);

    expect(Cache::get(ModuleCache::INDEX_KEY))->toBe([1])
        ->and($this->cache->get(1))->toBe(['core', 'sales', 'crm']);
});
