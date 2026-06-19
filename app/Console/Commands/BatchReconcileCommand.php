<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;

class BatchReconcileCommand extends Command
{
    protected $signature = 'batch:reconcile {--company= : Limit reconciliation to a single company id}';

    protected $description = 'Recompute every batch remaining quantity from the cost ledger (stock layers)';

    public function handle(): int
    {
        $query = Batch::withoutGlobalScopes()
            ->when($this->option('company'), fn ($q, $id) => $q->where('company_id', $id));

        $count = 0;

        $query->select('id')->chunkById(500, function ($batches) use (&$count): void {
            foreach ($batches as $batch) {
                Batch::reconcileRemaining($batch->id);
                $count++;
            }
        });

        $this->info("Reconciled {$count} batch(es) from the stock ledger.");

        return Command::SUCCESS;
    }
}
