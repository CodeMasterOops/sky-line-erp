<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Enums\EntityCodeType;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\GeneratesEntityCode;
use App\Http\Resources\Admin\Inventory\ProductResource;
use App\Http\Requests\Api\Admin\Inventory\ProductRequest;
use App\Http\Resources\Admin\Inventory\ProductVariantResource;

class ProductController extends Controller
{
    use GeneratesEntityCode;

    #[Permissions('list_product_variant', group: 'product', desc: 'List Product Variant')]
    public function productVariants(Request $request)
    {
        $limit = min((int) $request->get('limit', 500), 1000);
        $search = trim((string) $request->get('search', ''));

        $query = ProductVariant::with(['product:id,name,unit_id,product_type', 'variantOptions']);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like)->orWhere('code', 'like', $like));
            });
        }

        $variants = $query->limit($limit)->get();

        return ProductVariantResource::collection($variants);
    }

    /**
     * Paginated product variant search by SKU, product name, or product code (for sales order / POS pickers).
     */
    #[Permissions('list_product_variant', group: 'product', desc: 'List Product Variant')]
    public function searchProductVariants(Request $request)
    {
        $perPage = min(max((int) $request->get('limit', 20), 1), 50);
        $q = trim((string) $request->get('q', ''));
        $barcode = trim((string) $request->get('barcode', ''));

        $query = ProductVariant::query()
            ->with([
                'product:id,name,code,unit_id,product_type',
                'variantOptions.attribute',
            ]);

        if ($request->boolean('physical_only')) {
            $query->whereHas('product', fn ($q) => $q->where('product_type', ProductTypeEnum::PRODUCT->value));
        }

        if ($barcode !== '') {
            $query->where(function ($sub) use ($barcode) {
                $sub->where('barcode', $barcode)
                    ->orWhere('sku', $barcode)
                    ->orWhereHas('product', function ($product) use ($barcode) {
                        $product->where('code', $barcode);
                    });
            });
        } elseif (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('barcode', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('product', function ($product) use ($like) {
                        $product->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        $variants = $query->latest('product_variants.id')->paginate($perPage);

        return ProductVariantResource::collection($variants);
    }

    #[Permissions('list_product', group: 'product', desc: 'List Product')]
    public function index(Request $request)
    {
        $isServiceOnly = $request->input('product_type') === ProductTypeEnum::SERVICE->value;
        $warehouseIds = Product::parseWarehouseIds($request->input('warehouse_ids'));

        $variantRelations = $isServiceOnly ? [] : [
            'stocks' => fn ($sq) => $sq
                ->select('id', 'product_variant_id', 'warehouse_id', 'quantity')
                ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
                ->with('warehouse:id,name,code'),
        ];

        if (! $isServiceOnly && $request->boolean('include_inventory_value')) {
            $variantRelations['stockLayers'] = fn ($lq) => $lq
                ->where('qty_remaining', '>', 0)
                ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds));
        }

        $products = Product::with([
            'productCategory.parent',
            'brand',
            'unit',
            'tax',
            'defaultVariant',
            'variants' => fn ($q) => $q->with($variantRelations),
        ])
            ->filter($request->all())
            ->latest()
            ->paginate($request->limit ?? 25);

        return ProductResource::collection($products);
    }

    #[Permissions('create_product', group: 'product', desc: 'Create Product')]
    public function nextCode()
    {
        return $this->nextCodeResponse(EntityCodeType::Product);
    }

    #[Permissions('create_product', group: 'product', desc: 'Create Product')]
    public function store(ProductRequest $request)
    {
        $validated = $request->validated();
        $this->assignEntityCode($validated, EntityCodeType::Product);
        $formData = $this->sanitizeProductData($validated);

        $product = DB::transaction(function () use ($request, $formData) {
            $hasVariants = $request->boolean('has_variants');
            $productData = Arr::only($formData, $this->productFillableKeys());
            $productData['company_id'] = auth('admin')->user()->company_id;

            $product = Product::create($productData);

            foreach ($formData['variants'] ?? [] as $variant) {
                $productVariant = $product->variants()->create([
                    'company_id' => $product->company_id,
                    'sku' => $variant['sku'] ?? null,
                    'barcode' => $variant['barcode'] ?? null,
                    'sales_price' => $variant['sales_price'] ?? 0,
                    'purchase_price' => $variant['purchase_price'] ?? 0,
                    'is_default' => $hasVariants ? $variant['is_default'] : true,
                ]);

                $productVariant->variantOptions()->attach($variant['attribute_values'] ?? []);
            }

            return $product;
        });

        $product->load('tax');

        return response()->json([
            'data' => ProductResource::make($product),
            'message' => 'Product Added Successfully',
        ], 201);
    }

    #[Permissions('show_product', group: 'product', desc: 'Show Product')]
    public function show(Product $product)
    {
        $product->load([
            'productCategory.parent',
            'tax',
            'variants.variantOptions.attribute',
            'variants.stocks.warehouse',
        ]);

        return ProductResource::make($product);
    }

    #[Permissions('edit_product', group: 'product', desc: 'Edit Product')]
    public function update(ProductRequest $request, Product $product)
    {
        $validated = $this->sanitizeProductData($request->validated());

        $productData = Arr::only($validated, $this->productFillableKeys());

        DB::transaction(function () use ($request, $validated, $productData, $product) {
            $product->update($productData);

            $hasVariants = $request->boolean('has_variants');

            foreach ($validated['variants'] ?? [] as $variant) {
                $variantId = $variant['id'] ?? null;
                $attrValues = $variant['attribute_values'] ?? [];

                if ($variantId) {
                    $productVariant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->where('id', $variantId)
                        ->first();

                    if ($productVariant) {
                        $productVariant->update([
                            'sku' => $variant['sku'] ?? null,
                            'barcode' => $variant['barcode'] ?? null,
                            'sales_price' => $variant['sales_price'] ?? 0,
                            'purchase_price' => $variant['purchase_price'] ?? 0,
                            'is_default' => $hasVariants ? (bool) ($variant['is_default'] ?? false) : true,
                        ]);
                        $productVariant->variantOptions()->sync($attrValues);
                    }
                } else {
                    $productVariant = $product->variants()->create([
                        'company_id' => $product->company_id,
                        'sku' => $variant['sku'] ?? null,
                        'barcode' => $variant['barcode'] ?? null,
                        'sales_price' => $variant['sales_price'] ?? 0,
                        'purchase_price' => $variant['purchase_price'] ?? 0,
                        'is_default' => $hasVariants ? (bool) ($variant['is_default'] ?? false) : true,
                    ]);

                    $productVariant->variantOptions()->attach($attrValues);
                }
            }
        });

        $product->load([
            'tax',
            'variants.variantOptions.attribute',
            'variants.stocks.warehouse',
        ]);

        return response()->json([
            'data' => ProductResource::make($product),
            'message' => 'Product Updated Successfully',
        ]);
    }

    #[Permissions('delete_product', group: 'product', desc: 'Delete Product')]
    public function destroy(Product $product)
    {
        $hasStock = $product->variants()
            ->whereHas('stocks', fn ($q) => $q->where('quantity', '>', 0))
            ->exists();

        if ($hasStock) {
            return response()->json([
                'message' => 'This product has stock on hand and cannot be deleted. Please adjust stock to zero before deleting.',
            ], 422);
        }

        DB::transaction(function () use ($product) {
            $product->variants()->delete();
            $product->delete();
        });

        return response()->json([
            'message' => 'Product Deleted Successfully',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeProductData(array $data): array
    {
        if (($data['product_type'] ?? '') === ProductTypeEnum::SERVICE->value) {
            $data['brand_id'] = null;
            $data['min_stock_level'] = 0;
            $data['reorder_quantity'] = 0;
            $data['has_variants'] = false;
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function productFillableKeys(): array
    {
        return [
            'product_category_id',
            'product_type',
            'name',
            'code',
            'hsn_code',
            'unit_id',
            'brand_id',
            'tax_id',
            'has_variants',
            'reorder_quantity',
            'min_stock_level',
            'description',
        ];
    }
}
