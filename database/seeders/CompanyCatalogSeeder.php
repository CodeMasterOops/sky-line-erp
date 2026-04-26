<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\Brand;
use App\Models\Party;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Enums\PartyTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\ProductCategory;

class CompanyCatalogSeeder
{
    /**
     * Idempotent: skips if the company already has at least one product.
     */
    public static function seedForCompany(int $companyId): void
    {
        Company::query()->findOrFail($companyId);

        if (Product::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        $cfg = config('company_catalog', []);

        $unitsByCode = self::mapUnits($companyId, $cfg['units'] ?? []);
        $brandsByCode = self::mapBrands($companyId, $cfg['brands'] ?? []);
        $categoriesByName = self::mapCategories($companyId, $cfg['categories'] ?? []);

        foreach ($cfg['additional_warehouses'] ?? [] as $wh) {
            if (empty($wh['code']) || empty($wh['name'])) {
                continue;
            }
            Warehouse::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $wh['code'],
                ],
                [
                    'name' => $wh['name'],
                ]
            );
        }

        foreach ($cfg['parties'] ?? [] as $party) {
            $type = $party['type'] ?? null;
            if (! is_string($type) || $type === '') {
                continue;
            }
            $enum = PartyTypeEnum::tryFrom($type);
            if ($enum === null) {
                continue;
            }
            Party::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $party['code'] ?? '',
                ],
                [
                    'type' => $enum,
                    'name' => $party['name'] ?? 'Party',
                    'phone' => $party['phone'] ?? null,
                    'email' => $party['email'] ?? null,
                    'address' => $party['address'] ?? null,
                    'is_active' => $party['is_active'] ?? true,
                    'credit_limit' => $party['credit_limit'] ?? 0,
                ]
            );
        }

        foreach ($cfg['products'] ?? [] as $p) {
            $catName = $p['category'] ?? 'General';
            $unitCode = $p['unit_code'] ?? 'PCS';
            $brandCode = $p['brand_code'] ?? 'HB';
            $categoryId = $categoriesByName[$catName] ?? $categoriesByName['General'] ?? null;
            $unitId = $unitsByCode[$unitCode] ?? null;
            $brandId = $brandsByCode[$brandCode] ?? null;
            if ($categoryId === null || $unitId === null) {
                continue;
            }

            $product = Product::create([
                'company_id' => $companyId,
                'product_category_id' => $categoryId,
                'product_type' => ProductTypeEnum::PRODUCT,
                'name' => $p['name'] ?? 'Product',
                'code' => $p['code'] ?? 'ITEM',
                'hsn_code' => $p['hsn_code'] ?? null,
                'unit_id' => $unitId,
                'brand_id' => $brandId,
                'has_variants' => false,
                'reorder_quantity' => 0,
                'min_stock_level' => 0,
                'description' => $p['description'] ?? null,
            ]);

            ProductVariant::create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'sku' => $p['sku'] ?? $product->code,
                'sales_price' => (float) ($p['sales_price'] ?? 0),
                'purchase_price' => (float) ($p['purchase_price'] ?? 0),
                'is_default' => true,
            ]);
        }
    }

    /**
     * @return array<string, int> code => id
     */
    private static function mapUnits(int $companyId, array $rows): array
    {
        $byCode = [];
        foreach ($rows as $u) {
            if (empty($u['code'])) {
                continue;
            }
            $unit = Unit::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $u['code'],
                ],
                [
                    'name' => $u['name'] ?? $u['code'],
                ]
            );
            $byCode[$u['code']] = $unit->id;
        }

        return $byCode;
    }

    /**
     * @return array<string, int> code => id
     */
    private static function mapBrands(int $companyId, array $rows): array
    {
        $byCode = [];
        foreach ($rows as $b) {
            if (empty($b['code'])) {
                continue;
            }
            $brand = Brand::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $b['code'],
                ],
                [
                    'name' => $b['name'] ?? $b['code'],
                ]
            );
            $byCode[$b['code']] = $brand->id;
        }

        return $byCode;
    }

    /**
     * @return array<string, int> name => id
     */
    private static function mapCategories(int $companyId, array $rows): array
    {
        $byName = [];
        foreach ($rows as $c) {
            if (empty($c['name'])) {
                continue;
            }
            $cat = ProductCategory::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $c['name'],
                ],
                [
                    'parent_id' => null,
                    'description' => $c['description'] ?? null,
                ]
            );
            $byName[$c['name']] = $cat->id;
        }

        return $byName;
    }
}
