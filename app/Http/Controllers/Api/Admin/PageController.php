<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Page;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PageResource;
use App\Http\Requests\Api\Admin\PageRequest;

class PageController extends Controller
{
    /**
     * @Permissions("list_page", group="page", desc="List Page")
     */
    public function index(Request $request)
    {
        $pages = Page::filter($request->all())
            ->orderBy('sort_order')
            ->paginate($request->query('limit', 25));

        return PageResource::collection($pages);
    }

    /**
     * @Permissions("create_page", group="page", desc="Create Page")
     */
    public function store(PageRequest $request)
    {
        $page = Page::create($request->validated());

        return response()->json([
            'data' => PageResource::make($page),
            'message' => 'Page Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_page", group="page", desc="Show Page")
     */
    public function show(Page $page)
    {
        return PageResource::make($page);
    }

    /**
     * @Permissions("edit_page", group="page", desc="Edit Page")
     */
    public function update(PageRequest $request, Page $page)
    {
        $page->update($request->validated());

        return response()->json([
            'data' => PageResource::make($page),
            'message' => 'Page Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_page", group="page", desc="Delete Page")
     */
    public function destroy(Page $page)
    {
        $page->delete();

        return response()->json([
            'message' => 'Page Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_page_status", group="page", desc="Update Status")
     */
    public function updateStatus(Page $page)
    {
        $page->update([
            'status' => ! $page->status,
        ]);

        return response([
            'status' => $page->status,
            'message' => 'Status updated successfully',
        ]);
    }
}
