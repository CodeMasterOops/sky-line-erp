<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Warehouse;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\WarehouseResource;
use App\Http\Requests\Api\Admin\WarehouseRequest;

class WarehouseController extends Controller
{
    /**
     * @Permissions("list_warehouse", group="warehouse", desc="List Warehouse")
     */
    public function index()
    {
        $warehouses = Warehouse::query()
            ->with('parent:id,name,code')
            ->orderByRaw('parent_id is null desc')
            ->orderBy('name')
            ->get();

        return WarehouseResource::collection($warehouses);
    }

    /**
     * @Permissions("create_warehouse", group="warehouse", desc="Create Warehouse")
     */
    public function store(WarehouseRequest $request)
    {
        $warehouse = Warehouse::create($request->validated());
        $warehouse->load('parent:id,name,code');

        return response()->json([
            'data' => WarehouseResource::make($warehouse),
            'message' => 'Warehouse Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_warehouse", group="warehouse", desc="Show Warehouse")
     */
    public function show(Warehouse $warehouse)
    {
        $warehouse->load('parent:id,name,code');

        return WarehouseResource::make($warehouse);
    }

    /**
     * @Permissions("edit_warehouse", group="warehouse", desc="Edit Warehouse")
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());
        $warehouse->load('parent:id,name,code');

        return response()->json([
            'data' => WarehouseResource::make($warehouse),
            'message' => 'Warehouse Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_warehouse", group="warehouse", desc="Delete Warehouse")
     */
    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->children()->exists()) {
            return response()->json([
                'message' => __('Cannot delete a warehouse that has sub-warehouses. Remove or reassign them first.'),
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse Deleted Successfully',
        ]);
    }
}
