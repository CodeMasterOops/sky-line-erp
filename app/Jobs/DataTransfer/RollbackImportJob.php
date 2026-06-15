<?php

namespace App\Jobs\DataTransfer;

use App\Models\Bill;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\SalesOrder;
use App\Models\InvoiceItem;
use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
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

        match ($job->entity_type) {
            DataTransferEntityTypeEnum::Product => $this->rollbackProducts($job),
            DataTransferEntityTypeEnum::Warehouse => $this->rollbackWarehouses($job),
            DataTransferEntityTypeEnum::Party => $this->rollbackParties($job),
            default => throw new \RuntimeException("Rollback not supported for {$job->entity_type->value}."),
        };

        $job->update([
            'status' => DataTransferStatusEnum::RolledBack,
            'finished_at' => now(),
        ]);
    }

    private function rollbackProducts(DataTransferJob $job): void
    {
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
    }

    private function rollbackWarehouses(DataTransferJob $job): void
    {
        $warehouses = Warehouse::query()
            ->where('import_batch_id', $job->batch_id)
            ->orderByDesc('id')
            ->get();

        foreach ($warehouses as $warehouse) {
            $hasStock = Stock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity', '>', 0)
                ->exists();

            if ($hasStock) {
                throw new \RuntimeException(
                    "Cannot rollback warehouse {$warehouse->code}: has stock on hand."
                );
            }

            $hasLineItems = InvoiceItem::query()
                ->where('warehouse_id', $warehouse->id)
                ->exists();

            if ($hasLineItems) {
                throw new \RuntimeException(
                    "Cannot rollback warehouse {$warehouse->code}: used on documents."
                );
            }

            $warehouse->delete();
        }
    }

    private function rollbackParties(DataTransferJob $job): void
    {
        $parties = Party::query()
            ->where('import_batch_id', $job->batch_id)
            ->get();

        foreach ($parties as $party) {
            $hasDependents = Invoice::query()->where('party_id', $party->id)->exists()
                || Bill::query()->where('party_id', $party->id)->exists()
                || SalesOrder::query()->where('party_id', $party->id)->exists();

            if ($hasDependents) {
                throw new \RuntimeException(
                    "Cannot rollback party {$party->code}: used on documents."
                );
            }

            $party->delete();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->dataTransferJob->update([
            'error_summary' => $exception->getMessage(),
        ]);
    }
}
