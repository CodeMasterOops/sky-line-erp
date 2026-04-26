<?php

namespace App\Http\Controllers\Api\Admin;

use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Bin;
use Illuminate\Http\Request;

class BinController extends Controller
{
    /**
     * @Permissions("list_bin", group="bin", desc="List Bins")
     */
    public function index(Request $request)
    {
        $bins = Bin::when($request->warehouse_id, fn ($q, $wid) => $q->where('warehouse_id', $wid))
            ->with('warehouse:id,name,code')
            ->orderBy('warehouse_id')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $bins]);
    }

    /**
     * @Permissions("create_bin", group="bin", desc="Create Bin")
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'name'         => 'required|string|max:100',
            'code'         => 'nullable|string|max:50',
            'zone'         => 'nullable|string|max:50',
            'rack'         => 'nullable|string|max:50',
            'level'        => 'nullable|string|max:50',
            'is_active'    => 'boolean',
            'description'  => 'nullable|string',
        ]);

        $bin = Bin::create($data);

        return response()->json(['data' => $bin, 'message' => 'Bin created successfully'], 201);
    }

    /**
     * @Permissions("show_bin", group="bin", desc="Show Bin")
     */
    public function show(Bin $bin)
    {
        return response()->json(['data' => $bin->load('warehouse:id,name,code')]);
    }

    /**
     * @Permissions("edit_bin", group="bin", desc="Edit Bin")
     */
    public function update(Request $request, Bin $bin)
    {
        $data = $request->validate([
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'name'         => 'sometimes|required|string|max:100',
            'code'         => 'nullable|string|max:50',
            'zone'         => 'nullable|string|max:50',
            'rack'         => 'nullable|string|max:50',
            'level'        => 'nullable|string|max:50',
            'is_active'    => 'boolean',
            'description'  => 'nullable|string',
        ]);

        $bin->update($data);

        return response()->json(['data' => $bin, 'message' => 'Bin updated successfully']);
    }

    /**
     * @Permissions("delete_bin", group="bin", desc="Delete Bin")
     */
    public function destroy(Bin $bin)
    {
        $bin->delete();

        return response()->json(['message' => 'Bin deleted successfully']);
    }
}
