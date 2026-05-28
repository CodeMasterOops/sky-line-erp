<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Currency;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

class CurrencyController extends Controller
{
    /**
     * @Permissions("list_currency", group="currency", desc="List Currencies")
     *
     * Read-only list for selecting currencies on transactions.
     * Currency management is handled by the SaaS owner (super-admin).
     */
    public function index(Request $request)
    {
        $currencies = Currency::query()
            ->where('is_active', true)
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
}
