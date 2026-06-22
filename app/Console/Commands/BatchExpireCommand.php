<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Enums\BatchStatusEnum;
use Illuminate\Console\Command;

class BatchExpireCommand extends Command
{
    protected $signature = 'batch:expire';

    protected $description = 'Mark active batches whose expiry date has passed as expired';

    public function handle(): int
    {
        $count = Batch::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->where('status', BatchStatusEnum::Active->value)
            ->update(['status' => BatchStatusEnum::Expired->value]);

        $this->info("Marked {$count} batch(es) as expired.");

        return Command::SUCCESS;
    }
}
