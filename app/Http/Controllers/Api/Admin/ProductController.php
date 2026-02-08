<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductResource;
use App\Http\Requests\Api\Admin\ProductRequest;

class ProductController extends Controller
{
    /**
     * @Permissions("list_product", group="product", desc="List Product")
     */
    public function index(Request $request)
    {
        $products = Product::with(['productCategory', 'brand', 'unit'])
            ->filter($request->all())
            ->paginate($request->limit ?? 25);

        return ProductResource::collection($products);
    }

    /**
     * @Permissions("create_product", group="product", desc="Create Product")
     */
    public function store(ProductRequest $request)
    {
        $product = Product::create($request->validated());

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
        $product->delete();

        return response()->json([
            'message' => 'Product Deleted Successfully',
        ]);
    }
}
