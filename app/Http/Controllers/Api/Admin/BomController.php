<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Bom;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class BomController extends Controller
{
    /**
     * @Permissions("list_bom", group="bom", desc="List BOMs")
     */
    public function index(Request $request)
    {
        $boms = Bom::query()
            ->when($request->product_variant_id, fn ($q, $id) => $q->where('product_variant_id', $id))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->with(['productVariant.product:id,name', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $boms]);
    }

    /**
     * @Permissions("create_bom", group="bom", desc="Create BOM")
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'name' => 'required|string|max:200',
            'version' => 'nullable|string|max:20',
            'output_qty' => 'required|numeric|min:0.0001',
            'output_unit_id' => 'nullable|exists:units,id',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.item_type' => 'nullable|in:material,labour,overhead',
            'items.*.wastage_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $items = $data['items'];
            unset($data['items']);

            // Only one default BOM per product variant
            if (! empty($data['is_default'])) {
                Bom::where('product_variant_id', $data['product_variant_id'])
                    ->update(['is_default' => false]);
            }

            $bom = Bom::create($data);

            foreach ($items as $item) {
                $bom->items()->create($item);
            }

            return response()->json([
                'data' => $bom->load(['productVariant.product', 'items.productVariant.product', 'items.unit']),
                'message' => 'BOM created successfully',
            ], 201);
        });
    }

    /**
     * @Permissions("show_bom", group="bom", desc="Show BOM")
     */
    public function show(Bom $bom)
    {
        return response()->json([
            'data' => $bom->load(['productVariant.product', 'outputUnit', 'items.productVariant.product', 'items.unit']),
        ]);
    }

    /**
     * @Permissions("edit_bom", group="bom", desc="Update BOM")
     */
    public function update(Request $request, Bom $bom)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'version' => 'nullable|string|max:20',
            'output_qty' => 'sometimes|numeric|min:0.0001',
            'output_unit_id' => 'nullable|exists:units,id',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'remarks' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.item_type' => 'nullable|in:material,labour,overhead',
            'items.*.wastage_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data, $bom) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            if (! empty($data['is_default'])) {
                Bom::where('product_variant_id', $bom->product_variant_id)
                    ->where('id', '!=', $bom->id)
                    ->update(['is_default' => false]);
            }

            $bom->update($data);

            if ($items !== null) {
                $bom->items()->delete();
                foreach ($items as $item) {
                    $bom->items()->create($item);
                }
            }

            return response()->json([
                'data' => $bom->fresh()->load(['productVariant.product', 'items.productVariant.product', 'items.unit']),
                'message' => 'BOM updated successfully',
            ]);
        });
    }

    /**
     * @Permissions("delete_bom", group="bom", desc="Delete BOM")
     */
    public function destroy(Bom $bom)
    {
        $bom->delete();

        return response()->json(['message' => 'BOM deleted successfully']);
    }
}
