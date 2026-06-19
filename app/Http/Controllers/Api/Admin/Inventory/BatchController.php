<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Batch;
use Illuminate\Http\Request;
use App\Enums\BatchStatusEnum;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Inventory\BatchResource;
use App\Http\Requests\Api\Admin\Inventory\BatchStoreRequest;
use App\Http\Requests\Api\Admin\Inventory\BatchUpdateRequest;

class BatchController extends Controller
{
    #[Permissions('list_batch', group: 'batch', desc: 'List Batches')]
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

        return BatchResource::collection($batches);
    }

    #[Permissions('create_batch', group: 'batch', desc: 'Create Batch')]
    public function store(BatchStoreRequest $request)
    {
        $data = $request->validated();

        $data['remaining_qty'] = $data['initial_qty'];
        $batch = Batch::create($data);
        $batch->load(['productVariant.product', 'warehouse']);

        return response()->json(['data' => BatchResource::make($batch), 'message' => 'Batch created successfully'], 201);
    }

    #[Permissions('show_batch', group: 'batch', desc: 'Show Batch')]
    public function show(Batch $batch)
    {
        $batch->load(['productVariant.product', 'warehouse']);

        return response()->json(['data' => BatchResource::make($batch)]);
    }

    #[Permissions('edit_batch', group: 'batch', desc: 'Edit Batch')]
    public function update(BatchUpdateRequest $request, Batch $batch)
    {
        $batch->update($request->validated());
        $batch->load(['productVariant.product', 'warehouse']);

        return response()->json(['data' => BatchResource::make($batch), 'message' => 'Batch updated successfully']);
    }

    #[Permissions('list_batch', group: 'batch', desc: 'Expiry Alerts')]
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
            ->where('status', BatchStatusEnum::Active->value)
            ->update(['status' => BatchStatusEnum::Expired->value]);

        return response()->json(['data' => $batches, 'days' => $days]);
    }

    #[Permissions('list_batch', group: 'batch', desc: 'Available batches for FEFO picking')]
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
