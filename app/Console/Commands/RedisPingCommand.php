<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RedisPingCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'redis:ping {--connection= : Ping only this connection (default, cache, or queue)}';

    /**
     * @var string
     */
    protected $description = 'Verify Redis connectivity (cache + queues when using REDIS_URL)';

    public function handle(): int
    {
        $names = $this->resolveConnectionNames();

        $failed = false;

        foreach ($names as $name) {
            try {
                $pong = Redis::connection($name)->ping();

                if (is_bool($pong)) {
                    $pong = $pong ? 'PONG' : 'FAIL';
                }

                $this->components->info("Redis [{$name}]: {$pong}");
            } catch (\Throwable $e) {
                $failed = true;
                $this->components->error("Redis [{$name}] failed: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveConnectionNames(): array
    {
        $single = $this->option('connection');

        if (is_string($single) && $single !== '') {
            return [$single];
        }

        return ['default', 'cache', 'queue'];
    }
}
