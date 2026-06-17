<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Inventory\ProductCategoryResource;
use App\Http\Requests\Api\Admin\Inventory\ProductCategoryRequest;

class ProductCategoryController extends Controller
{
    #[Permissions('list_product_category', group: 'product_category', desc: 'List Product Category')]
    public function index(Request $request)
    {
        $productCategories = ProductCategory::query()
            ->with('parent:id,name')
            ->withCount('children')
            ->orderByRaw('parent_id is null desc')
            ->orderBy('name')
            ->paginate($request->integer('limit', 25));

        return ProductCategoryResource::collection($productCategories);
    }

    #[Permissions('create_product_category', group: 'product_category', desc: 'Create Product Category')]
    public function store(ProductCategoryRequest $request)
    {
        $productCategory = ProductCategory::create($request->validated());
        $productCategory->load('parent:id,name');
        $productCategory->loadCount('children');

        return response()->json([
            'data' => ProductCategoryResource::make($productCategory),
            'message' => 'Product Category Added Successfully',
        ], 201);
    }

    #[Permissions('show_product_category', group: 'product_category', desc: 'Show ProductCategory')]
    public function show(ProductCategory $productCategory)
    {
        $productCategory->load('parent:id,name');
        $productCategory->loadCount('children');

        return ProductCategoryResource::make($productCategory);
    }

    #[Permissions('edit_product_category', group: 'product_category', desc: 'Edit ProductCategory')]
    public function update(ProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validated());
        $productCategory->load('parent:id,name');
        $productCategory->loadCount('children');

        return response()->json([
            'data' => ProductCategoryResource::make($productCategory),
            'message' => 'Product Category Updated Successfully',
        ]);
    }

    #[Permissions('delete_product_category', group: 'product_category', desc: 'Delete ProductCategory')]
    public function destroy(ProductCategory $productCategory)
    {
        if ($productCategory->children()->exists()) {
            return response()->json([
                'message' => __('Cannot delete a category that has subcategories. Remove or reassign them first.'),
            ], 422);
        }

        if ($productCategory->products()->exists()) {
            return response()->json([
                'message' => __('Cannot delete a category assigned to products.'),
            ], 422);
        }

        $productCategory->delete();

        return response()->json([
            'message' => 'Product Category Deleted Successfully',
        ]);
    }
}
