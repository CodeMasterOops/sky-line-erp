<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Banner;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BannerResource;
use App\Http\Requests\Api\Admin\BannerRequest;

class BannerController extends Controller
{
    /**
     * @Permissions("list_banner", group="banner", desc="List Banner")
     */
    public function index()
    {
        $banners = Banner::orderBy('sort_order')->get();

        return BannerResource::collection($banners);
    }

    /**
     * @Permissions("create_banner", group="banner", desc="Create Banner")
     */
    public function store(BannerRequest $request)
    {
        $banner = Banner::create($request->validated());

        return response()->json([
            'data' => BannerResource::make($banner),
            'message' => 'Banner Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_banner", group="banner", desc="Show Banner")
     */
    public function show(Banner $banner)
    {
        return BannerResource::make($banner);
    }

    /**
     * @Permissions("edit_banner", group="banner", desc="Edit Banner")
     */
    public function update(BannerRequest $request, Banner $banner)
    {
        $banner->update($request->validated());

        return response()->json([
            'data' => BannerResource::make($banner),
            'message' => 'Banner Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_banner", group="banner", desc="Delete Banner")
     */
    public function destroy(Banner $banner)
    {
        $banner->delete();

        return response()->json([
            'message' => 'Banner Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_banner_status", group="banner", desc="Update Status")
     */
    public function updateStatus(Banner $banner)
    {
        $banner->update([
            'status' => ! $banner->status,
        ]);

        return response([
            'status' => $banner->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
