<?php

namespace App\Jobs\DataTransfer;

use App\Models\Product;
use App\Models\InvoiceItem;
use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Jobs\DataTransfer\Concerns\SetsTenantFromDataTransferJob;

class RollbackImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SetsTenantFromDataTransferJob;

    public int $tries = 2;

    public function __construct(
        public DataTransferJob $dataTransferJob,
    ) {
        $this->onQueue(config('data_transfer.queue', 'data-transfer'));
    }

    public function handle(): void
    {
        $job = $this->dataTransferJob->fresh();
        $this->setTenantFromJob($job);

        if (! $job->batch_id) {
            throw new \RuntimeException('Import batch not found.');
        }

        $products = Product::query()
            ->where('import_batch_id', $job->batch_id)
            ->get();

        foreach ($products as $product) {
            $variantIds = $product->variants()->pluck('id');

            $hasDependents = InvoiceItem::query()
                ->whereIn('product_variant_id', $variantIds)
                ->exists();

            if ($hasDependents) {
                throw new \RuntimeException(
                    "Cannot rollback product {$product->code}: used on invoices."
                );
            }

            $product->variants()->delete();
            $product->delete();
        }

        $job->update([
            'status' => DataTransferStatusEnum::RolledBack,
            'finished_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->dataTransferJob->update([
            'error_summary' => $exception->getMessage(),
        ]);
    }
}
