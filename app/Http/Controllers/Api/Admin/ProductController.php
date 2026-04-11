<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Product;
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
     * @Permissions("list_product", group="product", desc="List Product")
     */
    public function index(Request $request)
    {
        $products = Product::with(['productCategory', 'brand', 'unit', 'defaultVariant', 'variants'])
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
            'variants.variantOptions.attribute',
        ]);

        return ProductResource::make($product);
    }

    /**
     * @Permissions("edit_product", group="product", desc="Edit Product")
     */
    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->validated());

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
