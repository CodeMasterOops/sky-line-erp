<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;

class AdminNotificationController extends Controller
{
    public function allNotifications(Request $request)
    {
        $notifications = auth('admin')->user()->notifications()->paginate($request->query('limit', 25));

        return NotificationResource::collection($notifications);
    }

    public function unreadNotifications()
    {
        $notifications = auth('admin')->user()->unreadNotifications;

        return NotificationResource::collection($notifications);
    }

    public function markAsRead($id = null)
    {
        if ($id) {
            auth('admin')->user()->unreadNotifications()->find($id)?->markAsRead();
        } else {
            auth()->user()->unreadNotifications?->markAsRead();
        }

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }
}
