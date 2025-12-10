<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductResource;
use App\Http\Requests\Api\Admin\ProductRequest;

class ProductController extends Controller
{
    /**
     * @Permissions("list_all_product", group="product", desc="List All Product")
     */
    public function allProducts(Request $request)
    {
        $products = Product::select('id', 'name')->latest()->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * @Permissions("list_product", group="product", desc="List Product")
     */
    public function index(Request $request)
    {
        $products = Product::with('defaultVariant')
            ->withSum('stocks', 'quantity')
            ->filter($request->all())
            ->latest()
            ->paginate($request->query('limit', 25));

        return ProductResource::collection($products);
    }

    /**
     * @Permissions("create_product", group="product", desc="Create Product")
     */
    public function store(ProductRequest $request)
    {
        $product = DB::transaction(function () use ($request) {
            $hasVariants = $request->boolean('has_variants');

            $product = Product::create($request->validated());

            $product->categories()->attach($request->validated('categories'));
            $product->tags()->attach($request->validated('tags'));
            $product->attributeValues()->attach($request->validated('attribute_values'));

            foreach ($request->validated('variants') as $variant) {
                $productVariant = $product->variants()->create([
                    'sku' => $variant['sku'] ?? null,
                    'price' => $variant['price'] ?? 0,
                    'sales_price' => $variant['sales_price'] ?? 0,
                    'weight' => $variant['weight'] ?? 0,
                    'length' => $variant['length'] ?? 0,
                    'width' => $variant['width'] ?? 0,
                    'height' => $variant['height'] ?? 0,
                    'image' => $variant['image'] ?? null,
                    'is_default' => $hasVariants ? $variant['is_default'] : true,
                ]);
                $productVariant->variantOptions()->attach($variant['attribute_values'] ?? []);
            }

            foreach ($request->validated('images', []) as $image) {
                $product->images()->create($image);
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
            'tags',
            'categories',
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'variants.variantOptions.attribute',
            'vendor',
            'attributeValues.attribute',
        ]);

        return ProductResource::make($product);
    }

    /**
     * @Permissions("edit_product", group="product", desc="Edit Product")
     */
    public function update(ProductRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->validated());

            $product->categories()->sync($request->validated('categories'));
            $product->tags()->sync($request->validated('tags'));
            $product->attributeValues()->sync($request->validated('attribute_values'));
        });

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
        $product->tags()->detach();
        $product->categories()->detach();
        $product->images()->delete();
        $product->variants()->delete();
        $product->attributeValues()->detach();
        $product->delete();

        return response()->json([
            'message' => 'Product Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_product_status", group="product", desc="Update Status")
     */
    public function updateStatus(Product $product)
    {
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return response([
            'is_active' => $product->is_active,
            'message' => 'Status updated successfully',
        ]);
    }
}
