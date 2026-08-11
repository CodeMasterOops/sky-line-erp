<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Modules\ModuleCache;

/**
 * Drop every company's cached module resolution.
 *
 * The cache is written with `forever` and invalidated by row writes, so a
 * change that touches no row — editing a category's defaults, shipping a new
 * module — needs an explicit push. The registry fingerprint in the cache key
 * covers a config change automatically; this exists for deploys that change
 * behaviour without changing the manifest, and as the manual escape hatch when
 * something looks stale.
 */
class FlushModuleCacheCommand extends Command
{
    protected $signature = 'modules:flush-cache';

    protected $description = 'Drop the cached module resolution for every company';

    public function handle(ModuleCache $cache): int
    {
        $cache->flush();

        $this->info('Module cache flushed for every company.');

        return self::SUCCESS;
    }
}
