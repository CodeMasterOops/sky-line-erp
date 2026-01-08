<?php

namespace App\Http\Controllers\Api\Admin;

use App\Annotation\Permissions;
use App\Models\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductCategoryResource;
use App\Http\Requests\Api\Admin\ProductCategoryRequest;

class ProductCategoryController extends Controller
{
    /**
     * @Permissions("list_product_category", group="product_category", desc="List Product Category")
     */
    public function index()
    {
        $productCategories = ProductCategory::all();

        return ProductCategoryResource::collection($productCategories);
    }

    /**
     * @Permissions("create_product_category", group="product_category", desc="Create Product Category")
     */
    public function store(ProductCategoryRequest $request)
    {
        $productCategory = ProductCategory::create($request->validated());

        return response()->json([
            'data' => ProductCategoryResource::make($productCategory),
            'message' => 'Product Category Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_product_category", group="product_category", desc="Show ProductCategory")
     */
    public function show(ProductCategory $productCategory)
    {
        return ProductCategoryResource::make($productCategory);
    }

    /**
     * @Permissions("edit_product_category", group="product_category", desc="Edit ProductCategory")
     */
    public function update(ProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validated());

        return response()->json([
            'data' => ProductCategoryResource::make($productCategory),
            'message' => 'Product Category Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_product_category", group="product_category", desc="Delete ProductCategory")
     */
    public function destroy(ProductCategory $productCategory)
    {
        $productCategory->delete();

        return response()->json([
            'message' => 'Product Category Deleted Successfully',
        ]);
    }
}
