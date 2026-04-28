<?php

namespace Database\Seeders;

use App\Models\Tax;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\Party;
use App\Models\Company;
use App\Models\Product;
use App\Enums\PartyTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds large volumes of customers and products for UI / performance testing.
 *
 * Run: php artisan db:seed --class=BulkTestCatalogSeeder
 *
 * Optional env: BULK_SEED_COMPANY_ID, BULK_SEED_CUSTOMERS, BULK_SEED_PRODUCTS (defaults below).
 */
class BulkTestCatalogSeeder extends Seeder
{
    /** @var int Customer parties to create */
    public int $customerCount = 100;

    /** @var int Products (each with one default variant) to create */
    public int $productCount = 150;

    public function run(): void
    {
        $companyId = (int) (env('BULK_SEED_COMPANY_ID') ?: Company::query()->value('id'));

        if ($companyId === 0) {
            $this->command?->error('No company row found. Run php artisan db:seed (or CompanySeeder) first, or set BULK_SEED_COMPANY_ID.');

            return;
        }

        Company::query()->findOrFail($companyId);

        $customers = (int) env('BULK_SEED_CUSTOMERS', $this->customerCount);
        $products = (int) env('BULK_SEED_PRODUCTS', $this->productCount);
        $customers = max(1, $customers);
        $products = max(1, $products);

        $deps = $this->ensureCatalogBasics($companyId);
        $faker = fake();

        $this->command?->info(sprintf(
            'Bulk seeding company #%d: ~%d customers, ~%d products…',
            $companyId,
            $customers,
            $products
        ));

        for ($i = 1; $i <= $customers; $i++) {
            $code = sprintf('BULK-C-%d-%06d', $companyId, $i);
            Party::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $code,
                ],
                [
                    'type' => PartyTypeEnum::CUSTOMER,
                    'name' => $faker->name().' (Test)',
                    'phone' => '98'.$faker->numerify('########'),
                    'email' => 'bulk.cust.'.$companyId.'.'.$i.'@example.test',
                    'address' => $faker->optional(0.8)->streetAddress(),
                    'is_active' => true,
                    'credit_limit' => $faker->randomElement([0, 5000, 10000, 25000]),
                ]
            );
        }

        $categoryIds = $deps['category_ids'];
        $catCount = count($categoryIds);

        for ($j = 1; $j <= $products; $j++) {
            $code = sprintf('BULK-P-%d-%06d', $companyId, $j);
            $product = Product::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $code,
                ],
                [
                    'product_category_id' => $categoryIds[($j - 1) % $catCount],
                    'product_type' => ProductTypeEnum::PRODUCT,
                    'name' => $faker->words(3, true).' (Test)',
                    'hsn_code' => $faker->optional(0.3)->numerify('######'),
                    'unit_id' => $deps['unit_id'],
                    'brand_id' => $deps['brand_id'],
                    'tax_id' => $deps['tax_id'],
                    'has_variants' => false,
                    'reorder_quantity' => $faker->numberBetween(0, 50),
                    'min_stock_level' => $faker->randomFloat(2, 0, 20),
                    'description' => $faker->optional(0.4)->sentence(),
                ]
            );

            $sku = sprintf('SKU-BULK-%d-%06d', $companyId, $j);
            ProductVariant::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'product_id' => $product->id,
                    'sku' => $sku,
                ],
                [
                    'sales_price' => round($faker->randomFloat(2, 5, 5000), 2),
                    'purchase_price' => round($faker->randomFloat(2, 2, 4000), 2),
                    'is_default' => true,
                ]
            );
        }

        $this->command?->info('Bulk test catalog seeding finished.');
    }

    /**
     * @return array{unit_id: int, brand_id: int|null, category_ids: array<int, int>, tax_id: int|null}
     */
    private function ensureCatalogBasics(int $companyId): array
    {
        $unit = Unit::firstOrCreate(
            [
                'company_id' => $companyId,
                'code' => 'PCS',
            ],
            [
                'name' => 'Piece',
            ]
        );

        $brand = Brand::firstOrCreate(
            [
                'company_id' => $companyId,
                'code' => 'HB',
            ],
            [
                'name' => 'House Brand',
            ]
        );

        $categoryNames = ['General', 'Beverages', 'Grocery'];
        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $categoryIds[] = ProductCategory::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $name,
                ],
                [
                    'parent_id' => null,
                    'description' => null,
                ]
            )->id;
        }

        $vatTax = Tax::query()
            ->where('company_id', $companyId)
            ->lineItem()
            ->orderByDesc('rate')
            ->first();

        return [
            'unit_id' => $unit->id,
            'brand_id' => $brand->id,
            'category_ids' => $categoryIds,
            'tax_id' => $vatTax?->id,
        ];
    }
}
