<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Announcement;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AnnouncementResource;
use App\Http\Requests\Api\Admin\AnnouncementRequest;

class AnnouncementController extends Controller
{
    /**
     * @Permissions("list_announcement", group="announcement", desc="List Announcement")
     */
    public function index()
    {
        $announcements = Announcement::orderBy('sort_order')->get();

        return AnnouncementResource::collection($announcements);
    }

    /**
     * @Permissions("create_announcement", group="announcement", desc="Create Announcement")
     */
    public function store(AnnouncementRequest $request)
    {
        $announcement = Announcement::create($request->validated());

        return response()->json([
            'data' => AnnouncementResource::make($announcement),
            'message' => 'Announcement Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_announcement", group="announcement", desc="Show Announcement")
     */
    public function show(Announcement $announcement)
    {
        return AnnouncementResource::make($announcement);
    }

    /**
     * @Permissions("edit_announcement", group="announcement", desc="Edit Announcement")
     */
    public function update(AnnouncementRequest $request, Announcement $announcement)
    {
        $announcement->update($request->validated());

        return response()->json([
            'data' => AnnouncementResource::make($announcement),
            'message' => 'Announcement Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_announcement", group="announcement", desc="Delete Announcement")
     */
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return response()->json([
            'message' => 'Announcement Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("update_announcement_status", group="announcement", desc="Update Status")
     */
    public function updateStatus(Announcement $announcement)
    {
        $announcement->update([
            'is_active' => ! $announcement->is_active,
        ]);

        return response([
            'is_active' => $announcement->is_active,
            'message' => 'Status updated successfully',
        ]);
    }
}
