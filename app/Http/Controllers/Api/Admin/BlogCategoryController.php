<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\BlogCategory;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BlogCategoryResource;
use App\Http\Requests\Api\Admin\BlogCategoryRequest;

class BlogCategoryController extends Controller
{
    /**
     * @Permissions("list_blog_category", group="blog_category", desc="List Blog Category")
     */
    public function index(Request $request)
    {
        $blogCategories = BlogCategory::orderBy('sort_order')->get();

        return BlogCategoryResource::collection($blogCategories);
    }

    /**
     * @Permissions("create_blog_category", group="blog_category", desc="Create Blog Category")
     */
    public function store(BlogCategoryRequest $request)
    {
        $blogCategory = BlogCategory::create($request->validated());

        return response()->json([
            'data' => BlogCategoryResource::make($blogCategory),
            'message' => 'Blog Category Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_blog_category", group="blog_category", desc="Show Blog Category")
     */
    public function show(BlogCategory $blogCategory)
    {
        return BlogCategoryResource::make($blogCategory);
    }

    /**
     * @Permissions("edit_blog_category", group="blog_category", desc="Edit Blog Category")
     */
    public function update(BlogCategoryRequest $request, BlogCategory $blogCategory)
    {
        $blogCategory->update($request->validated());

        return response()->json([
            'data' => BlogCategoryResource::make($blogCategory),
            'message' => 'Blog Category Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_blog_category", group="blog_category", desc="Delete Blog Category")
     */
    public function destroy(BlogCategory $blogCategory)
    {
        $blogCategory->delete();

        return response()->json([
            'message' => 'Blog Category Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_blog_category_status", group="blog_category", desc="Update Status")
     */
    public function updateStatus(BlogCategory $blogCategory)
    {
        $blogCategory->update([
            'status' => ! $blogCategory->status,
        ]);

        return response([
            'status' => $blogCategory->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
