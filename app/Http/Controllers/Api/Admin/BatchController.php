<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Batch;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

class BatchController extends Controller
{
    /**
     * @Permissions("list_batch", group="batch", desc="List Batches")
     */
    public function index(Request $request)
    {
        $batches = Batch::query()
            ->when($request->product_variant_id, fn ($q, $id) => $q->where('product_variant_id', $id))
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->expiring_days, fn ($q, $d) => $q->expiringSoon((int) $d))
            ->with(['productVariant.product:id,name', 'warehouse:id,name,code'])
            ->orderByRaw('expiry_date IS NULL ASC')
            ->orderBy('expiry_date')
            ->paginate($request->per_page ?? 25);

        return response()->json($batches);
    }

    /**
     * @Permissions("create_batch", group="batch", desc="Create Batch")
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'batch_no' => 'required|string|max:100',
            'lot_no' => 'nullable|string|max:100',
            'mfg_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:mfg_date',
            'initial_qty' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $data['remaining_qty'] = $data['initial_qty'];
        $batch = Batch::create($data);

        return response()->json(['data' => $batch, 'message' => 'Batch created successfully'], 201);
    }

    /**
     * @Permissions("show_batch", group="batch", desc="Show Batch")
     */
    public function show(Batch $batch)
    {
        return response()->json(['data' => $batch->load(['productVariant.product', 'warehouse'])]);
    }

    /**
     * @Permissions("edit_batch", group="batch", desc="Edit Batch")
     */
    public function update(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'batch_no' => 'sometimes|string|max:100',
            'lot_no' => 'nullable|string|max:100',
            'mfg_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'status' => 'sometimes|in:active,expired,depleted',
            'remarks' => 'nullable|string',
        ]);

        $batch->update($data);

        return response()->json(['data' => $batch, 'message' => 'Batch updated successfully']);
    }

    /**
     * @Permissions("list_batch", group="batch", desc="Expiry Alerts")
     */
    public function expiryAlerts(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $company = auth()->user()->company;

        $batches = Batch::where('company_id', $company->id)
            ->expiringSoon($days)
            ->with(['productVariant.product:id,name', 'warehouse:id,name,code'])
            ->get();

        // Also mark expired batches
        Batch::where('company_id', $company->id)
            ->expired()
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        return response()->json(['data' => $batches, 'days' => $days]);
    }

    /**
     * @Permissions("list_batch", group="batch", desc="Available batches for FEFO picking")
     */
    public function fefoList(Request $request)
    {
        $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $batches = Batch::where('product_variant_id', $request->product_variant_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->fefo()
            ->get(['id', 'batch_no', 'lot_no', 'expiry_date', 'remaining_qty', 'unit_cost']);

        return response()->json(['data' => $batches]);
    }
}
