<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Brand;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BrandResource;
use App\Http\Requests\Api\Admin\BrandRequest;

class BrandController extends Controller
{
    /**
     * @Permissions("list_brand", group="brand", desc="List Brand")
     */
    public function index()
    {
        $brands = Brand::all();

        return BrandResource::collection($brands);
    }

    /**
     * @Permissions("create_brand", group="brand", desc="Create Brand")
     */
    public function store(BrandRequest $request)
    {
        $brand = Brand::create($request->validated());

        return response()->json([
            'data' => BrandResource::make($brand),
            'message' => 'Brand Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_brand", group="brand", desc="Show Brand")
     */
    public function show(Brand $brand)
    {
        return BrandResource::make($brand);
    }

    /**
     * @Permissions("edit_brand", group="brand", desc="Edit Brand")
     */
    public function update(BrandRequest $request, Brand $brand)
    {
        $brand->update($request->validated());

        return response()->json([
            'data' => BrandResource::make($brand),
            'message' => 'Brand Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_brand", group="brand", desc="Delete Brand")
     */
    public function destroy(Brand $brand)
    {
        $brand->delete();

        return response()->json([
            'message' => 'Brand Deleted Successfully',
        ]);
    }
}
