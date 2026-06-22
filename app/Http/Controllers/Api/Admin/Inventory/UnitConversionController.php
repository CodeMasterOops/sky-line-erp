<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use Illuminate\Http\Request;
use App\Models\UnitConversion;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

class UnitConversionController extends Controller
{
    #[Permissions('list_unit_conversion', group: 'unit_conversion', desc: 'List Unit Conversions')]
    public function index(Request $request)
    {
        $conversions = UnitConversion::with(['fromUnit', 'toUnit'])
            ->when($request->from_unit_id, fn ($q, $id) => $q->where('from_unit_id', $id))
            ->when($request->to_unit_id, fn ($q, $id) => $q->where('to_unit_id', $id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($conversions);
    }

    #[Permissions('create_unit_conversion', group: 'unit_conversion', desc: 'Create Unit Conversion')]
    public function store(Request $request)
    {
        $data = $request->validate([
            'from_unit_id' => 'required|integer|exists:units,id|different:to_unit_id',
            'to_unit_id' => 'required|integer|exists:units,id',
            'factor' => 'required|numeric|min:0.000001',
            'remarks' => 'nullable|string|max:500',
        ]);

        $company = auth()->user()->company;
        $data['company_id'] = $company->id;

        $conversion = UnitConversion::create($data);

        return response()->json([
            'data' => $conversion->load(['fromUnit', 'toUnit']),
            'message' => 'Unit conversion created successfully.',
        ], 201);
    }

    #[Permissions('show_unit_conversion', group: 'unit_conversion', desc: 'Show Unit Conversion')]
    public function show(UnitConversion $unitConversion)
    {
        return response()->json([
            'data' => $unitConversion->load(['fromUnit', 'toUnit']),
        ]);
    }

    #[Permissions('edit_unit_conversion', group: 'unit_conversion', desc: 'Update Unit Conversion')]
    public function update(Request $request, UnitConversion $unitConversion)
    {
        $data = $request->validate([
            'from_unit_id' => 'sometimes|integer|exists:units,id|different:to_unit_id',
            'to_unit_id' => 'sometimes|integer|exists:units,id',
            'factor' => 'sometimes|numeric|min:0.000001',
            'remarks' => 'nullable|string|max:500',
        ]);

        $unitConversion->update($data);

        return response()->json([
            'data' => $unitConversion->fresh()->load(['fromUnit', 'toUnit']),
            'message' => 'Unit conversion updated successfully.',
        ]);
    }

    #[Permissions('delete_unit_conversion', group: 'unit_conversion', desc: 'Delete Unit Conversion')]
    public function destroy(UnitConversion $unitConversion)
    {
        $unitConversion->delete();

        return response()->json(['message' => 'Unit conversion deleted successfully.']);
    }
}
