<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Annotation\Permissions;
use App\Enums\FixedAssetDepreciationMethodEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FixedAssetController extends Controller
{
    /**
     * @Permissions("list_fixed_asset", group="fixed_asset", desc="List Fixed Assets")
     */
    public function index(Request $request)
    {
        $company = auth('admin')->user()->company;

        $assets = FixedAsset::with('category', 'assetAccount')
            ->where('company_id', $company->id)
            ->filter($request->all())
            ->orderByDesc('purchase_date')
            ->paginate($request->input('per_page', 15));

        return response()->json($assets);
    }

    /**
     * @Permissions("show_fixed_asset", group="fixed_asset", desc="Show Fixed Asset")
     */
    public function show(FixedAsset $fixedAsset)
    {
        return response()->json([
            'data' => $fixedAsset->load(['category', 'assetAccount', 'depreciationAccount', 'accumulatedDepreciationAccount']),
        ]);
    }

    /**
     * @Permissions("create_fixed_asset", group="fixed_asset", desc="Create Fixed Asset")
     */
    public function store(Request $request)
    {
        $validated = $this->validateAsset($request);
        $company = auth('admin')->user()->company;
        $validated['company_id'] = $company->id;
        $validated['asset_code'] = $this->generateAssetCode($company->id);
        $validated['status'] = 'active';

        $asset = FixedAsset::create($validated);

        return response()->json([
            'data' => $asset->load('category', 'assetAccount'),
            'message' => 'Fixed asset created successfully.',
        ], 201);
    }

    /**
     * @Permissions("edit_fixed_asset", group="fixed_asset", desc="Update Fixed Asset")
     */
    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $this->validateAsset($request);
        $fixedAsset->update($validated);

        return response()->json([
            'data' => $fixedAsset->fresh()->load('category', 'assetAccount'),
            'message' => 'Fixed asset updated successfully.',
        ]);
    }

    /**
     * @Permissions("delete_fixed_asset", group="fixed_asset", desc="Delete Fixed Asset")
     */
    public function destroy(FixedAsset $fixedAsset)
    {
        $fixedAsset->delete();

        return response()->json(['message' => 'Fixed asset deleted successfully.']);
    }

    /**
     * @Permissions("list_fixed_asset_category", group="fixed_asset", desc="List Fixed Asset Categories")
     */
    public function categories(Request $request)
    {
        $company = auth('admin')->user()->company;
        $categories = FixedAssetCategory::where('company_id', $company->id)->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * @Permissions("create_fixed_asset_category", group="fixed_asset", desc="Create Asset Category")
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'depreciation_method' => ['required', 'string', 'in:slm,wdv'],
            'useful_life_years' => ['required', 'numeric', 'min:0.5'],
            'salvage_value_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'asset_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'depreciation_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $company = auth('admin')->user()->company;
        $category = FixedAssetCategory::create(['company_id' => $company->id] + $validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully.',
        ], 201);
    }

    /**
     * @Permissions("list_fixed_asset", group="fixed_asset", desc="Fixed Asset Schedule Report")
     */
    public function schedule(Request $request)
    {
        $company = auth('admin')->user()->company;

        $assets = FixedAsset::with('category')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->get()
            ->map(function (FixedAsset $asset) {
                return [
                    'id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'name' => $asset->name,
                    'category' => $asset->category?->name,
                    'purchase_date' => $asset->purchase_date?->toDateString(),
                    'purchase_cost' => $asset->purchase_cost,
                    'accumulated_depreciation' => $asset->accumulated_depreciation,
                    'net_book_value' => $asset->netBookValue(),
                    'annual_depreciation' => $asset->annualDepreciation(),
                    'useful_life_years' => $asset->useful_life_years,
                    'depreciation_method' => $asset->depreciation_method?->label(),
                ];
            });

        return response()->json([
            'data' => $assets,
            'summary' => [
                'total_cost' => round($assets->sum('purchase_cost'), 2),
                'total_accumulated_depreciation' => round($assets->sum('accumulated_depreciation'), 2),
                'total_net_book_value' => round($assets->sum('net_book_value'), 2),
            ],
        ]);
    }

    private function validateAsset(Request $request): array
    {
        return $request->validate([
            'fixed_asset_category_id' => ['nullable', 'integer', 'exists:fixed_asset_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'numeric', 'min:0.5'],
            'depreciation_method' => ['required', 'string', 'in:slm,wdv'],
            'asset_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'depreciation_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'accumulated_depreciation_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function generateAssetCode(int $companyId): string
    {
        $count = FixedAsset::where('company_id', $companyId)->withTrashed()->count();

        return 'FA-'.str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
