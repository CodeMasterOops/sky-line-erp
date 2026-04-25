<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::orderBy('code')->get();

        return response()->json(['data' => $currencies]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'          => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name'          => ['required', 'string', 'max:100'],
            'symbol'        => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_base'       => ['boolean'],
            'is_active'     => ['boolean'],
            'rate_date'     => ['nullable', 'date'],
        ]);

        if (! empty($validated['is_base'])) {
            Currency::where('is_base', true)->update(['is_base' => false]);
        }

        $currency = Currency::create($validated);

        return response()->json([
            'data'    => $currency,
            'message' => 'Currency created successfully.',
        ], 201);
    }

    public function show(Currency $currency)
    {
        return response()->json(['data' => $currency]);
    }

    public function update(Request $request, Currency $currency)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'symbol'        => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_base'       => ['boolean'],
            'is_active'     => ['boolean'],
            'rate_date'     => ['nullable', 'date'],
        ]);

        if (! empty($validated['is_base'])) {
            Currency::where('is_base', true)->where('id', '!=', $currency->id)->update(['is_base' => false]);
        }

        $currency->update($validated);

        return response()->json([
            'data'    => $currency->fresh(),
            'message' => 'Currency updated successfully.',
        ]);
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_base) {
            return response()->json(['message' => 'Base currency cannot be deleted.'], 422);
        }

        $currency->delete();

        return response()->json(['message' => 'Currency deleted successfully.']);
    }
}
