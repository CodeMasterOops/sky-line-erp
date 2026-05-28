<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QueueWorkAppCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:work-app
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the worker}
                            {--queue= : Override queue names (comma-separated)}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : The number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job}
                            {--json : Output logs as JSON}';

    /**
     * @var string
     */
    protected $description = 'Process application queues (data transfer, default, IRD sync)';

    public function handle(): int
    {
        $queues = $this->option('queue') ?: config('queue.worker_queues');

        $parameters = [
            'connection' => $this->argument('connection'),
            '--name' => $this->option('name'),
            '--queue' => $queues,
            '--max-jobs' => $this->option('max-jobs'),
            '--max-time' => $this->option('max-time'),
            '--memory' => $this->option('memory'),
            '--sleep' => $this->option('sleep'),
            '--timeout' => $this->option('timeout'),
            '--tries' => $this->option('tries'),
        ];

        foreach (['once', 'stop-when-empty', 'force', 'json'] as $flag) {
            if ($this->option($flag)) {
                $parameters['--'.$flag] = true;
            }
        }

        return $this->call('queue:work', $parameters);
    }
}
