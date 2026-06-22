<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Bom;
use App\Models\BomOperation;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

class BomOperationController extends Controller
{
    #[Permissions('edit_bom', group: 'bom', desc: 'List BOM Operations')]
    public function index(Bom $bom)
    {
        return response()->json([
            'data' => $bom->operations()->get(),
        ]);
    }

    #[Permissions('edit_bom', group: 'bom', desc: 'Add BOM Operation')]
    public function store(Request $request, Bom $bom)
    {
        $data = $request->validate([
            'sequence' => 'required|integer|min:1',
            'name' => 'required|string|max:200',
            'work_center' => 'nullable|string|max:200',
            'duration_minutes' => 'nullable|integer|min:1',
            'cost_per_hour' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        $company = auth()->user()->company;

        abort_if($bom->company_id !== $company->id, 403);

        $operation = $bom->operations()->create([
            'company_id' => $company->id,
            ...$data,
        ]);

        return response()->json([
            'data' => $operation,
            'message' => 'BOM operation added successfully.',
        ], 201);
    }

    #[Permissions('edit_bom', group: 'bom', desc: 'Update BOM Operation')]
    public function update(Request $request, Bom $bom, BomOperation $bomOperation)
    {
        abort_if($bomOperation->bom_id !== $bom->id, 404);

        $data = $request->validate([
            'sequence' => 'sometimes|integer|min:1',
            'name' => 'sometimes|string|max:200',
            'work_center' => 'nullable|string|max:200',
            'duration_minutes' => 'nullable|integer|min:1',
            'cost_per_hour' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        $bomOperation->update($data);

        return response()->json([
            'data' => $bomOperation->fresh(),
            'message' => 'BOM operation updated successfully.',
        ]);
    }

    #[Permissions('edit_bom', group: 'bom', desc: 'Remove BOM Operation')]
    public function destroy(Bom $bom, BomOperation $bomOperation)
    {
        abort_if($bomOperation->bom_id !== $bom->id, 404);

        $bomOperation->delete();

        return response()->json(['message' => 'BOM operation deleted successfully.']);
    }
}
