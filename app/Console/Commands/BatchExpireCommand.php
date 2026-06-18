<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;

class BatchExpireCommand extends Command
{
    protected $signature = 'batch:expire';

    protected $description = 'Mark batches whose expiry date has passed as expired';

    public function handle(): int
    {
        $count = Batch::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->whereNotIn('status', ['expired', 'depleted'])
            ->update(['status' => 'expired']);

        $this->info("Marked {$count} batch(es) as expired.");

        return Command::SUCCESS;
    }
}
