<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QueueListenAppCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:listen-app
                            {connection? : The name of the queue connection}
                            {--name=default : The name of the worker}
                            {--queue= : Override queue names (comma-separated)}
                            {--delay=0 : Delay failed jobs}
                            {--backoff=0 : Backoff between retries}
                            {--force : Force in maintenance mode}
                            {--memory=128 : Memory limit in megabytes}
                            {--sleep=3 : Seconds to sleep when no job is available}
                            {--rest=0 : Seconds to rest between jobs}
                            {--timeout=60 : Max seconds per job}
                            {--tries=1 : Number of attempts}';

    /**
     * @var string
     */
    protected $description = 'Listen to application queues (reloads on code changes during local dev)';

    public function handle(): int
    {
        $queues = $this->option('queue') ?: config('queue.worker_queues');

        $parameters = [
            'connection' => $this->argument('connection'),
            '--name' => $this->option('name'),
            '--queue' => $queues,
            '--delay' => $this->option('delay'),
            '--backoff' => $this->option('backoff'),
            '--memory' => $this->option('memory'),
            '--sleep' => $this->option('sleep'),
            '--rest' => $this->option('rest'),
            '--timeout' => $this->option('timeout'),
            '--tries' => $this->option('tries'),
        ];

        if ($this->option('force')) {
            $parameters['--force'] = true;
        }

        return $this->call('queue:listen', $parameters);
    }
}
