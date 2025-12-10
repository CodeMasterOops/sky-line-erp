<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
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
    public function index(Request $request)
    {
        $productCategories = ProductCategory::with('children')->whereNull('parent_id')->orderBy('sort_order')->get();

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
     * @Permissions("show_product_category", group="product_category", desc="Show Product Category")
     */
    public function show(ProductCategory $productCategory)
    {
        return ProductCategoryResource::make($productCategory);
    }

    /**
     * @Permissions("edit_product_category", group="product_category", desc="Edit Product Category")
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
     * @Permissions("delete_product_category", group="product_category", desc="Delete Product Category")
     */
    public function destroy(ProductCategory $productCategory)
    {
        if ($productCategory->children()->count() > 0) {
            return response()->json([
                'message' => 'Parent Category cannot be deleted',
            ], 400);
        }
        $productCategory->delete();

        return response()->json([
            'message' => 'Product Category Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_product_category_status", group="product_category", desc="Update Status")
     */
    public function updateStatus(ProductCategory $productCategory)
    {
        $productCategory->update([
            'status' => ! $productCategory->status,
        ]);

        return response([
            'status' => $productCategory->status,
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * @Permissions("update_product_category_featured_status", group="product_category", desc="Update Featured Status")
     */
    public function updateFeaturedStatus(ProductCategory $productCategory)
    {
        $productCategory->update([
            'is_featured' => ! $productCategory->is_featured,
        ]);

        return response([
            'is_featured' => $productCategory->is_featured,
            'message' => 'Status updated successfully',
        ]);
    }
}
