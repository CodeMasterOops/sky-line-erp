<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Stock;
use App\Models\Product;
use App\Models\StockHistory;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\StockResource;
use App\Http\Resources\Admin\StockHistoryResource;

class StockController extends Controller
{
    /**
     * @Permissions("list_stock", group="stock", desc="List Stock")
     */
    public function index(Request $request)
    {
        $products = Product::with(['variants' => function ($query) {
            $query->with('stock', 'variantOptions.attribute');
        }])
            ->filter($request->all())
            ->latest()
            ->paginate($request->query('limit', 25));

        return StockResource::collection($products);
    }

    /**
     * @Permissions("update_stock", group="stock", desc="Update Stock")
     */
    public function updateStock(Request $request, ProductVariant $productVariant)
    {
        $formData = $request->validate([
            'type' => ['required', Rule::enum(ChangeTypeEnum::class)],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $stock = Stock::where('product_variant_id', $productVariant->id)->first();

        $currentStock = $stock->quantity ?? 0;

        $adjustQty = $formData['type'] == ChangeTypeEnum::PURCHASE->value ? ($currentStock + $formData['quantity']) : ($currentStock - $formData['quantity']);

        DB::transaction(function () use ($adjustQty, $productVariant, $formData) {
            Stock::updateOrCreate(
                ['product_variant_id' => $productVariant->id],
                [
                    'quantity' => $adjustQty,
                ]
            );

            $productVariant->stockHistories()->create([
                'type' => $formData['type'],
                'quantity' => $formData['quantity'],
            ]);
        });

        return response()->json([
            'message' => 'Stock Updated Successfully',
        ], 201);
    }

    /**
     * @Permissions("list_stock_history", group="stock", desc="Stock History")
     */
    public function stockHistory(Request $request, ProductVariant $productVariant)
    {
        $stockHistories = StockHistory::where('product_variant_id', $productVariant->id)->latest()->paginate($request->query('limit', 25));

        $historyCollection = StockHistoryResource::collection($stockHistories);

        return response()->json([
            'variant' => $productVariant,
            'histories' => $historyCollection->response()->getData(),
        ]);
    }
}
