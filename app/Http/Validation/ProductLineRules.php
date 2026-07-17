<?php

namespace App\Http\Validation;

use App\Tenancy\TRule;
use App\Enums\ProductTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Rules\WarehouseIsStockLocation;

class ProductLineRules
{
    /**
     * Reject service product variants on documents that only accept physical stock.
     *
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function physicalProductVariantId(?string $failureMessage = null): array
    {
        $message = $failureMessage ?? __('Services cannot be used here.');

        return [
            function (string $attribute, mixed $value, \Closure $fail) use ($message): void {
                if ($value === null || $value === '') {
                    return;
                }

                $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

                $productTypeQuery = DB::table('product_variants')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->where('product_variants.id', $value)
                    ->whereNull('product_variants.deleted_at')
                    ->whereNull('products.deleted_at');

                if ($companyId) {
                    $productTypeQuery
                        ->where('product_variants.company_id', $companyId)
                        ->where('products.company_id', $companyId);
                }

                $productType = $productTypeQuery->value('products.product_type');

                if ($productType === ProductTypeEnum::SERVICE->value) {
                    $fail($message);
                }
            },
        ];
    }

    /**
     * Warehouse is required for physical product lines; nullable for service lines.
     *
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function warehouseId(): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! preg_match('/items\.(\d+)\.warehouse_id/', $attribute, $matches)) {
                    return;
                }

                $index = (int) $matches[1];
                $items = request()->input('items', []);
                $variantId = $items[$index]['product_variant_id'] ?? null;

                if ($variantId === null || $variantId === '') {
                    return;
                }

                $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

                $productTypeQuery = DB::table('product_variants')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->where('product_variants.id', $variantId)
                    ->whereNull('product_variants.deleted_at')
                    ->whereNull('products.deleted_at');

                if ($companyId) {
                    $productTypeQuery
                        ->where('product_variants.company_id', $companyId)
                        ->where('products.company_id', $companyId);
                }

                $productType = $productTypeQuery->value('products.product_type');

                if ($productType === null) {
                    return;
                }

                if ($productType === ProductTypeEnum::SERVICE->value) {
                    if ($value !== null && $value !== '') {
                        $existsRule = TRule::exists('warehouses', 'id')->withoutTrashed();
                        $existsValidator = validator(['warehouse_id' => $value], ['warehouse_id' => [$existsRule]]);
                        if ($existsValidator->fails()) {
                            $fail($existsValidator->errors()->first('warehouse_id'));
                        }
                    }

                    return;
                }

                if ($value === null || $value === '') {
                    $fail(__('Warehouse is required for product lines.'));

                    return;
                }

                $existsRule = TRule::exists('warehouses', 'id')->withoutTrashed();
                $existsValidator = validator(['warehouse_id' => $value], ['warehouse_id' => [$existsRule]]);
                if ($existsValidator->fails()) {
                    $fail($existsValidator->errors()->first('warehouse_id'));

                    return;
                }

                (new WarehouseIsStockLocation)->validate($attribute, $value, $fail);
            },
        ];
    }
}
