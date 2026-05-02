<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Unit;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Inventory\UnitResource;
use App\Http\Requests\Api\Admin\Inventory\UnitRequest;

class UnitController extends Controller
{
    /**
     * @Permissions("list_unit", group="unit", desc="List Unit")
     */
    public function index()
    {
        $units = Unit::all();

        return UnitResource::collection($units);
    }

    /**
     * @Permissions("create_unit", group="unit", desc="Create Unit")
     */
    public function store(UnitRequest $request)
    {
        $unit = Unit::create($request->validated());

        return response()->json([
            'data' => UnitResource::make($unit),
            'message' => 'Unit Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_unit", group="unit", desc="Show Unit")
     */
    public function show(Unit $unit)
    {
        return UnitResource::make($unit);
    }

    /**
     * @Permissions("edit_unit", group="unit", desc="Edit Unit")
     */
    public function update(UnitRequest $request, Unit $unit)
    {
        $unit->update($request->validated());

        return response()->json([
            'data' => UnitResource::make($unit),
            'message' => 'Unit Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_unit", group="unit", desc="Delete Unit")
     */
    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json([
            'message' => 'Unit Deleted Successfully',
        ]);
    }
}
