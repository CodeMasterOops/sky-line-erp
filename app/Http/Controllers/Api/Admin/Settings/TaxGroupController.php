<?php

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Models\TaxGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class TaxGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = TaxGroup::query()
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->with('taxGroupMembers.tax:id,name,rate,type,is_inclusive,is_compound,sequence')
            ->orderBy('name')
            ->paginate($request->integer('limit', 100));

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'taxes' => ['required', 'array', 'min:1'],
            'taxes.*.tax_id' => ['required', 'integer', 'exists:taxes,id'],
            'taxes.*.sequence' => ['nullable', 'integer', 'min:1'],
        ]);

        $group = TaxGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach ($validated['taxes'] as $index => $taxLine) {
            $group->taxGroupMembers()->create([
                'tax_id' => $taxLine['tax_id'],
                'sequence' => $taxLine['sequence'] ?? ($index + 1),
            ]);
        }

        return response()->json([
            'data' => $group->load('taxGroupMembers.tax'),
            'message' => 'Tax Group created successfully.',
        ], 201);
    }

    public function show(TaxGroup $taxGroup): JsonResponse
    {
        return response()->json($taxGroup->load('taxGroupMembers.tax'));
    }

    public function update(Request $request, TaxGroup $taxGroup): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'taxes' => ['required', 'array', 'min:1'],
            'taxes.*.tax_id' => ['required', 'integer', 'exists:taxes,id'],
            'taxes.*.sequence' => ['nullable', 'integer', 'min:1'],
        ]);

        $taxGroup->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $taxGroup->description,
            'is_active' => $validated['is_active'] ?? $taxGroup->is_active,
        ]);

        $taxGroup->taxGroupMembers()->delete();

        foreach ($validated['taxes'] as $index => $taxLine) {
            $taxGroup->taxGroupMembers()->create([
                'tax_id' => $taxLine['tax_id'],
                'sequence' => $taxLine['sequence'] ?? ($index + 1),
            ]);
        }

        return response()->json([
            'data' => $taxGroup->load('taxGroupMembers.tax'),
            'message' => 'Tax Group updated successfully.',
        ]);
    }

    public function destroy(TaxGroup $taxGroup): JsonResponse
    {
        $taxGroup->delete();

        return response()->json(['message' => 'Tax Group deleted successfully.']);
    }
}
