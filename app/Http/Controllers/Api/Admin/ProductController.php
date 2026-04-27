<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductResource;
use App\Http\Requests\Api\Admin\ProductRequest;
use App\Http\Resources\Admin\ProductVariantResource;

class ProductController extends Controller
{
    /**
     * @Permissions("list_product_variant", group="product", desc="List Product Variant")
     */
    public function productVariants(Request $request)
    {
        $variants = ProductVariant::with(['product:id,name,unit_id', 'variantOptions'])
            ->get();

        return ProductVariantResource::collection($variants);
    }

    /**
     * Paginated product variant search by SKU, product name, or product code (for sales order / POS pickers).
     *
     * @Permissions("list_product_variant", group="product", desc="List Product Variant")
     */
    public function searchProductVariants(Request $request)
    {
        $perPage = min(max((int) $request->get('limit', 20), 1), 50);
        $q = trim((string) $request->get('q', ''));
        $barcode = trim((string) $request->get('barcode', ''));

        $query = ProductVariant::query()
            ->with([
                'product:id,name,code,unit_id',
                'variantOptions.attribute',
            ]);

        if ($barcode !== '') {
            $query->where(function ($sub) use ($barcode) {
                $sub->where('sku', $barcode)
                    ->orWhereHas('product', function ($product) use ($barcode) {
                        $product->where('code', $barcode);
                    });
            });
        } elseif (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('sku', 'like', $like)
                    ->orWhereHas('product', function ($product) use ($like) {
                        $product->where('name', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        } else {
            $query->whereRaw('0 = 1');
        }

        $variants = $query->latest('product_variants.id')->paginate($perPage);

        return ProductVariantResource::collection($variants);
    }

    /**
     * @Permissions("list_product", group="product", desc="List Product")
     */
    public function index(Request $request)
    {
        $variantRelations = [
            'stocks' => fn ($sq) => $sq->with('warehouse'),
        ];

        if ($request->boolean('include_inventory_value')) {
            $variantRelations['stockLayers'] = fn ($lq) => $lq->where('qty_remaining', '>', 0);
        }

        $products = Product::with([
            'productCategory',
            'brand',
            'unit',
            'tax',
            'defaultVariant',
            'variants' => fn ($q) => $q->with($variantRelations),
        ])
            ->filter($request->all())
            ->paginate($request->limit ?? 25);

        return ProductResource::collection($products);
    }

    /**
     * @Permissions("create_product", group="product", desc="Create Product")
     */
    public function store(ProductRequest $request)
    {
        $formData = $request->validated();

        $product = DB::transaction(function () use ($request, $formData) {
            $hasVariants = $request->boolean('has_variants');

            $product = Product::create($formData);

            foreach ($formData['variants'] ?? [] as $variant) {
                $productVariant = $product->variants()->create([
                    'vendor_id' => $product->vendor_id,
                    'sku' => $variant['sku'] ?? null,
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

    /**
     * @Permissions("show_product", group="product", desc="Show Product")
     */
    public function show(Product $product)
    {
        $product->load([
            'tax',
            'variants.variantOptions.attribute',
            'variants.stocks.warehouse',
        ]);

        return ProductResource::make($product);
    }

    /**
     * @Permissions("edit_product", group="product", desc="Edit Product")
     */
    public function update(ProductRequest $request, Product $product)
    {
        $validated = $request->validated();

        $productData = Arr::only($validated, [
            'product_category_id',
            'product_type',
            'name',
            'code',
            'unit_id',
            'brand_id',
            'tax_id',
            'has_variants',
            'reorder_quantity',
            'description',
        ]);

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
                            'sales_price' => $variant['sales_price'] ?? 0,
                            'purchase_price' => $variant['purchase_price'] ?? 0,
                            'is_default' => $hasVariants ? (bool) ($variant['is_default'] ?? false) : true,
                        ]);
                        $productVariant->variantOptions()->sync($attrValues);
                    }
                } else {
                    $productVariant = $product->variants()->create([
                        'vendor_id' => $product->vendor_id,
                        'sku' => $variant['sku'] ?? null,
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

    /**
     * @Permissions("delete_product", group="product", desc="Delete Product")
     */
    public function destroy(Product $product)
    {
        $product->variants()->delete();
        $product->delete();

        return response()->json([
            'message' => 'Product Deleted Successfully',
        ]);
    }
}
