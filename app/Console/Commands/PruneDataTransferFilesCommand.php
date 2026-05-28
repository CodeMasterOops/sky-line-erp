<?php

namespace App\Console\Commands;

use App\Models\DataTransferJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDataTransferFilesCommand extends Command
{
    protected $signature = 'data-transfer:prune {--days= : Retention days override}';

    protected $description = 'Delete expired data transfer source and result files';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('data_transfer.retention_days', 14));
        $cutoff = now()->subDays($days);

        $pruned = 0;

        DataTransferJob::query()
            ->where('expires_at', '<', now())
            ->orWhere('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($jobs) use (&$pruned) {
                foreach ($jobs as $job) {
                    if ($job->file_disk && $job->file_path) {
                        Storage::disk($job->file_disk)->delete($job->file_path);
                    }
                    if ($job->result_disk && $job->result_path) {
                        Storage::disk($job->result_disk)->delete($job->result_path);
                    }
                    $pruned++;
                }
            });

        $this->info("Pruned {$pruned} data transfer file sets.");

        return self::SUCCESS;
    }
}
