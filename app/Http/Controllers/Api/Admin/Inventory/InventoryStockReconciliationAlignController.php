<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\InventoryStockReconciliationAlignService;
use App\Http\Requests\Api\Admin\Inventory\InventoryStockReconciliationAlignRequest;

class InventoryStockReconciliationAlignController extends Controller
{
    public function __construct(
        private InventoryStockReconciliationAlignService $alignService,
    ) {}

    /**
     * @Permissions("create_stock_adjustment", group="stock_adjustment", desc="Align stock reconciliation")
     */
    public function __invoke(InventoryStockReconciliationAlignRequest $request)
    {
        $user = auth('admin')->user();
        $companyId = (int) $user->company_id;
        $variantId = (int) $request->validated('product_variant_id');
        $warehouseId = (int) $request->validated('warehouse_id');
        $strategy = $request->validated('strategy');

        $variant = ProductVariant::query()
            ->where('id', $variantId)
            ->where('company_id', $companyId)
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => __('Invalid product variant for this company.'),
            ]);
        }

        $warehouse = Warehouse::query()
            ->where('id', $warehouseId)
            ->where('company_id', $companyId)
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Invalid warehouse for this company.'),
            ]);
        }

        $product = Product::query()
            ->where('id', $variant->product_id)
            ->where('company_id', $companyId)
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_variant_id' => __('Product is not in this company.'),
            ]);
        }

        $company = Company::query()->findOrFail($companyId);

        if ($strategy === 'valued_to_stock') {
            $unitCost = $request->validated('unit_cost');
            $unitCostFloat = $unitCost !== null && $unitCost !== '' ? (float) $unitCost : null;

            $this->alignService->alignValuedQuantityToOnHand(
                $company,
                $variantId,
                $warehouseId,
                $unitCostFloat,
            );
        } else {
            $this->alignService->alignOnHandQuantityToValued(
                $company,
                $variantId,
                $warehouseId,
            );
        }

        return response()->json([
            'message' => __('Quantities aligned successfully.'),
        ]);
    }
}
