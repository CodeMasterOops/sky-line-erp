<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Currency;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('symbol', 'like', $term);
            }))
            ->orderBy('code')
            ->paginate($request->integer('limit', 25));

        return response()->json([
            'data' => $currencies->items(),
            'meta' => [
                'current_page' => $currencies->currentPage(),
                'from' => $currencies->firstItem(),
                'last_page' => $currencies->lastPage(),
                'per_page' => $currencies->perPage(),
                'to' => $currencies->lastItem(),
                'total' => $currencies->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_base' => ['boolean'],
            'is_active' => ['boolean'],
            'rate_date' => ['nullable', 'date'],
        ]);

        if (! empty($validated['is_base'])) {
            Currency::where('is_base', true)->update(['is_base' => false]);
        }

        $currency = Currency::create($validated);

        return response()->json([
            'data' => $currency,
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
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_base' => ['boolean'],
            'is_active' => ['boolean'],
            'rate_date' => ['nullable', 'date'],
        ]);

        if (! empty($validated['is_base'])) {
            Currency::where('is_base', true)->where('id', '!=', $currency->id)->update(['is_base' => false]);
        }

        $currency->update($validated);

        return response()->json([
            'data' => $currency->fresh(),
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
