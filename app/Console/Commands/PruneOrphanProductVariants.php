<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Console\Concerns\SkipsDisabledCompanies;

class PruneOrphanProductVariants extends Command
{
    use SkipsDisabledCompanies;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:prune-orphan-variants {--apply : Soft-delete the orphaned variants (default is a dry run)} {--company= : Restrict to a single company id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Soft-delete stale product variants left behind by earlier edits (zero stock and no transaction history), keeping every variant that holds stock or has been used.';

    /**
     * Transactional tables whose presence means a variant has real history and
     * must never be auto-pruned. The on-hand `stocks` quantity is checked
     * separately; the option pivot is not a transaction.
     *
     * @var list<string>
     */
    private array $historyTables = [
        'batches',
        'bill_items',
        'boms',
        'credit_note_items',
        'damage_report_items',
        'debit_note_items',
        'delivery_challans',
        'goods_received_notes',
        'inventory_valuation_snapshots',
        'invoice_items',
        'opening_stock_entry_items',
        'purchase_order_items',
        'quotation_items',
        'sales_order_items',
        'serial_numbers',
        'stock_adjustment_items',
        'stock_layers',
        'stock_movements',
        'stock_reservations',
        'stock_transfer_items',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $companyId = $this->option('company');

        $historyTables = array_values(array_filter(
            $this->historyTables,
            fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'product_variant_id'),
        ));

        // Soft-deleting variants is a write, so it only ever touches companies
        // that still run Inventory.
        $moduleCompanyIds = $this->companiesWithModule('inventory');

        if (! $this->reportModuleScope('inventory', $moduleCompanyIds)) {
            return self::SUCCESS;
        }

        $products = Product::query()
            ->whereIn('company_id', $moduleCompanyIds)
            ->when($companyId !== null, fn ($q) => $q->where('company_id', (int) $companyId))
            ->has('variants', '>', 1)
            ->get();

        if ($products->isEmpty()) {
            $this->info('No multi-variant products found. Nothing to prune.');

            return self::SUCCESS;
        }

        $totalPrunable = 0;
        $rows = [];

        foreach ($products as $product) {
            $variants = $product->variants()->with('variantOptions')->get();

            $prunable = $variants->filter(
                fn (ProductVariant $variant): bool => $this->isPrunable($variant, $historyTables),
            );

            if ($prunable->count() === $variants->count()) {
                $keepId = $variants->firstWhere('is_default', true)?->id ?? $variants->last()->id;
                $prunable = $prunable->reject(fn (ProductVariant $variant): bool => $variant->id === $keepId);
            }

            foreach ($prunable as $variant) {
                $rows[] = [
                    $product->code,
                    $product->name,
                    $variant->id,
                    $variant->sku ?? '-',
                    $variant->variant_label ?? '-',
                ];
            }

            $totalPrunable += $prunable->count();

            if ($apply && $prunable->isNotEmpty()) {
                ProductVariant::whereIn('id', $prunable->pluck('id'))->delete();
            }
        }

        if ($rows === []) {
            $this->info('No orphaned variants found. Nothing to prune.');

            return self::SUCCESS;
        }

        $this->table(['Product Code', 'Product', 'Variant ID', 'SKU', 'Options'], $rows);

        if ($apply) {
            $this->info("Soft-deleted {$totalPrunable} orphaned variant(s).");
        } else {
            $this->warn("Dry run: {$totalPrunable} variant(s) would be soft-deleted. Re-run with --apply to perform the cleanup.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $historyTables
     */
    private function isPrunable(ProductVariant $variant, array $historyTables): bool
    {
        $hasStock = $variant->stocks()->where('quantity', '>', 0)->exists();

        if ($hasStock) {
            return false;
        }

        foreach ($historyTables as $table) {
            if (DB::table($table)->where('product_variant_id', $variant->id)->exists()) {
                return false;
            }
        }

        return true;
    }
}
