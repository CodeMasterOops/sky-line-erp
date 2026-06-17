<?php

namespace App\Http\Controllers\Admin;

use App\Models\TaxGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Tax\TaxCalculationEngine;

class TaxCalculationController extends Controller
{
    public function __construct(private TaxCalculationEngine $engine) {}

    /**
     * Calculate tax amounts for a given base amount and tax group.
     *
     * Used by the invoice form frontend to compute accurate inclusive/compound
     * tax amounts server-side rather than approximating in JavaScript.
     *
     * POST /api/admin/tax/calculate
     * Body: { base_amount: float, tax_group_id: int, party_id?: int, date?: string }
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_amount' => ['required', 'numeric', 'min:0'],
            'tax_group_id' => ['required', 'integer', 'exists:tax_groups,id'],
            'party_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $group = TaxGroup::withoutGlobalScopes()
            ->where('company_id', auth('admin')->user()->company_id)
            ->where('is_active', true)
            ->findOrFail($validated['tax_group_id']);

        $result = $this->engine->calculateForGroup(
            baseAmount: (float) $validated['base_amount'],
            group: $group,
            partyId: isset($validated['party_id']) ? (int) $validated['party_id'] : null,
            exemptionDate: $validated['date'] ?? null,
        );

        return response()->json($result->toArray());
    }
}
