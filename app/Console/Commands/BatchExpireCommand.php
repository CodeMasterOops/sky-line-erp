<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Enums\BatchStatusEnum;
use Illuminate\Console\Command;
use App\Services\Modules\CompanyModuleService;

class BatchExpireCommand extends Command
{
    protected $signature = 'batch:expire';

    protected $description = 'Mark active batches whose expiry date has passed as expired';

    public function handle(): int
    {
        // Only sweep companies that still run inventory — a disabled module's
        // rows are preserved untouched, not quietly re-stamped in the background.
        $companyIds = app(CompanyModuleService::class)->companyIdsWith('inventory');

        if ($companyIds === []) {
            $this->info('No company has the Inventory module enabled.');

            return Command::SUCCESS;
        }

        $count = Batch::query()
            ->whereIn('company_id', $companyIds)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->where('status', BatchStatusEnum::Active->value)
            ->update(['status' => BatchStatusEnum::Expired->value]);

        $this->info("Marked {$count} batch(es) as expired.");

        return Command::SUCCESS;
    }
}
